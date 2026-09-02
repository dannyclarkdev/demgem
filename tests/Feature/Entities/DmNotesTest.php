<?php

use App\Enums\CampaignRole;
use App\Models\Campaign;
use App\Models\Entity;

it('shows GM notes to DM roles and never to players', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    Entity::factory()->for($campaign)->forPlayers()->withDmNotes('She is secretly the Drowned Duke.')->create(['slug' => 'mara-voss']);

    $this->actingAs(ownerOf($campaign))
        ->get(route('entities.show', [$campaign, 'characters', 'mara-voss']))
        ->assertSee('secretly the Drowned Duke');

    $this->actingAs($player)
        ->get(route('entities.show', [$campaign, 'characters', 'mara-voss']))
        ->assertOk()
        ->assertDontSee('secretly the Drowned Duke')
        ->assertDontSee('GM notes');
});

it('keeps GM notes out of the form a player uses for their PC', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    Entity::factory()->for($campaign)->pcOf($player)->withDmNotes('Her sister serves the Duke.')->create(['slug' => 'wren']);

    $this->actingAs($player)
        ->get(route('entities.edit', [$campaign, 'characters', 'wren']))
        ->assertOk()
        ->assertDontSee('serves the Duke')
        ->assertDontSee('GM notes');
});

it('escapes dangerous content in the body and GM notes', function () {
    $campaign = Campaign::factory()->create();
    Entity::factory()->for($campaign)
        ->withDmNotes("<script>alert('notes')</script>")
        ->create(['slug' => 'trap', 'body' => "<img src=x onerror=alert(1)>\n\n[click](javascript:alert(2))"]);

    $response = $this->actingAs(ownerOf($campaign))->get(route('entities.show', [$campaign, 'characters', 'trap']));

    $response->assertOk();
    $html = $response->getContent();

    expect($html)->not->toContain("<script>alert('notes')")
        ->not->toContain('onerror=alert')
        ->not->toContain('href="javascript:');
});
