<?php

use App\Actions\Campaigns\RemoveMember;
use App\Enums\CampaignRole;
use App\Enums\Visibility;
use App\Livewire\Entities\Form;
use App\Models\Campaign;
use App\Models\Entity;
use Livewire\Livewire;

it('lets a player edit the name, body, and tags of their own PC', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $pc = Entity::factory()->for($campaign)->pcOf($player)->create(['name' => 'Wren', 'slug' => 'wren']);

    Livewire::actingAs($player)
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'characters', 'slug' => 'wren'])
        ->set('name', 'Wren Ashgrove')
        ->set('body', 'Rogue. Looking for her sister.')
        ->set('tags', 'pc, rogue')
        ->call('save')
        ->assertHasNoErrors();

    expect($pc->fresh())
        ->name->toBe('Wren Ashgrove')
        ->body->toBe('Rogue. Looking for her sister.')
        ->and($pc->fresh()->tags->pluck('name')->sort()->values()->all())->toBe(['pc', 'rogue']);
});

it('ignores DM fields a player sends for their PC', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $pc = Entity::factory()->for($campaign)->pcOf($player)->dmOnly()->withDmNotes('Original notes.')->create(['slug' => 'wren']);

    Livewire::actingAs($player)
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'characters', 'slug' => 'wren'])
        ->set('visibility', Visibility::Players->value)
        ->set('dm_notes', 'I rewrote the GM notes.')
        ->set('is_pc', false)
        ->set('player_user_id', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($pc->fresh())
        ->visibility->toBe(Visibility::Dm)
        ->dm_notes->toBe('Original notes.')
        ->is_pc->toBeTrue()
        ->player_user_id->toBe($player->id);
});

it('forbids a player from editing another player\'s PC', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $other = memberOf($campaign, CampaignRole::Player);
    Entity::factory()->for($campaign)->pcOf($other)->forPlayers()->create(['slug' => 'tobin']);

    $this->actingAs($player)
        ->get(route('entities.edit', [$campaign, 'characters', 'tobin']))
        ->assertForbidden();
});

it('shows the edit button on a PC page to its player only', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $other = memberOf($campaign, CampaignRole::Player);
    Entity::factory()->for($campaign)->pcOf($player)->forPlayers()->create(['slug' => 'wren']);

    $this->actingAs($player)->get(route('entities.show', [$campaign, 'characters', 'wren']))->assertSee('Edit');
    $this->actingAs($other)->get(route('entities.show', [$campaign, 'characters', 'wren']))->assertDontSee('>Edit<', false);
});

it('leaves the PC in place with no player when the player is removed', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $pc = Entity::factory()->for($campaign)->pcOf($player)->create();

    app(RemoveMember::class)->handle($campaign->memberFor($player));

    expect($pc->fresh())->player_user_id->toBeNull()->is_pc->toBeTrue();
});
