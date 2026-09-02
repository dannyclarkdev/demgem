<?php

use App\Enums\CampaignRole;
use App\Models\Campaign;
use App\Models\Entity;

it('lists visible sources that mention the entity and hides the rest from players', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $target = Entity::factory()->for($campaign)->forPlayers()->create(['name' => 'Mara Voss', 'slug' => 'mara-voss']);
    Entity::factory()->for($campaign)->forPlayers()->create(['name' => 'Public Note', 'body' => 'About [[Mara Voss]].']);
    Entity::factory()->for($campaign)->dmOnly()->create(['name' => 'Secret Note', 'body' => 'Also about [[Mara Voss]].']);
    Entity::factory()->for($campaign)->forPlayers()->create(['name' => 'Notes Only Note', 'dm_notes' => 'GM: [[Mara Voss]].']);

    $this->actingAs($player)
        ->get($target->url())
        ->assertSee('Mentioned in')
        ->assertSee('Public Note')
        ->assertDontSee('Secret Note')
        ->assertDontSee('Notes Only Note');

    $this->actingAs(ownerOf($campaign))
        ->get($target->url())
        ->assertSeeInOrder(['Mentioned in', 'Notes Only Note', 'Public Note', 'Secret Note']);
});

it('does not list the entity itself as a backlink', function () {
    $campaign = Campaign::factory()->create();
    $target = Entity::factory()->for($campaign)->forPlayers()->create(['name' => 'Mara Voss', 'slug' => 'mara-voss', 'body' => 'I am [[Mara Voss]].']);

    $this->actingAs(ownerOf($campaign))
        ->get($target->url())
        ->assertDontSee('Mentioned in');
});

it('renders wiki links in the body according to the viewer', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $secret = Entity::factory()->for($campaign)->dmOnly()->create(['name' => 'The Duke', 'slug' => 'the-duke']);
    $public = Entity::factory()->for($campaign)->forPlayers()->create(['name' => 'Vell', 'slug' => 'vell']);
    $source = Entity::factory()->for($campaign)->forPlayers()->create(['slug' => 'source', 'body' => '[[The Duke]] rules [[Vell]]. [[Nobody]] knows.']);

    $this->actingAs($player)
        ->get($source->url())
        ->assertSee('href="'.$public->url().'"', false)
        ->assertDontSee('href="'.$secret->url().'"', false)
        ->assertDontSee('wiki-link--missing');

    $this->actingAs(ownerOf($campaign))
        ->get($source->url())
        ->assertSee('href="'.$secret->url().'"', false)
        ->assertSee('wiki-link--missing');
});
