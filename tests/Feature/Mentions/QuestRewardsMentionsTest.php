<?php

use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Enums\Visibility;
use App\Livewire\Entities\Show;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\Mention;
use Livewire\Livewire;

it('indexes a link written in the rewards field', function () {
    $campaign = Campaign::factory()->create();
    $sword = Entity::factory()->for($campaign)->type(EntityType::Item)->create(['name' => 'Sunblade']);
    $quest = Entity::factory()->for($campaign)->quest()->create([
        'body' => 'Clear the barrow.',
        'rewards' => 'The [[Sunblade]] and 500 gold.',
    ]);

    $mention = Mention::withoutGlobalScopes()
        ->where('source_id', $quest->id)
        ->where('source_field', 'rewards')
        ->sole();

    expect($mention->target_entity_id)->toBe($sword->id)
        ->and($mention->target_name)->toBe('Sunblade');
});

it('rewrites a rewards link when the item is renamed', function () {
    $campaign = Campaign::factory()->create();
    $sword = Entity::factory()->for($campaign)->type(EntityType::Item)->create(['name' => 'Sunblade']);
    $quest = Entity::factory()->for($campaign)->quest()->create(['rewards' => 'The [[Sunblade]], if it survives.']);

    $sword->update(['name' => 'Dawnbrand']);

    expect($quest->fresh()->rewards)->toBe('The [[Dawnbrand]], if it survives.');
});

it('shows a player a backlink earned through rewards', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    $sword = Entity::factory()->for($campaign)->type(EntityType::Item)->forPlayers()->create(['name' => 'Sunblade']);
    $quest = Entity::factory()->for($campaign)->quest()->forPlayers()->create([
        'name' => 'The Barrow',
        'rewards' => 'The [[Sunblade]].',
    ]);

    Livewire::actingAs($player)
        ->test(Show::class, ['campaign' => $campaign, 'type' => 'items', 'slug' => $sword->slug])
        ->assertViewHas('backlinks', fn ($backlinks) => $backlinks->contains('id', $quest->id));
});

it('hides a backlink earned only through GM notes from a player', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    $sword = Entity::factory()->for($campaign)->type(EntityType::Item)->forPlayers()->create(['name' => 'Sunblade']);
    $quest = Entity::factory()->for($campaign)->quest()->forPlayers()->create([
        'name' => 'The Barrow',
        'body' => 'Clear the barrow.',
        'dm_notes' => 'The [[Sunblade]] is cursed.',
    ]);

    Livewire::actingAs($player)
        ->test(Show::class, ['campaign' => $campaign, 'type' => 'items', 'slug' => $sword->slug])
        ->assertViewHas('backlinks', fn ($backlinks) => $backlinks->doesntContain('id', $quest->id));

    Livewire::actingAs(ownerOf($campaign))
        ->test(Show::class, ['campaign' => $campaign, 'type' => 'items', 'slug' => $sword->slug])
        ->assertViewHas('backlinks', fn ($backlinks) => $backlinks->contains('id', $quest->id));
});

it('hides a rewards backlink when the quest itself is GM only', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    $sword = Entity::factory()->for($campaign)->type(EntityType::Item)->forPlayers()->create(['name' => 'Sunblade']);
    Entity::factory()->for($campaign)->quest()->visibility(Visibility::Dm)->create([
        'name' => 'The Barrow',
        'rewards' => 'The [[Sunblade]].',
    ]);

    Livewire::actingAs($player)
        ->test(Show::class, ['campaign' => $campaign, 'type' => 'items', 'slug' => $sword->slug])
        ->assertViewHas('backlinks', fn ($backlinks) => $backlinks->isEmpty());
});

it('puts rewards in the search index and keeps GM notes out', function () {
    $campaign = Campaign::factory()->create();
    $quest = Entity::factory()->for($campaign)->quest()->create([
        'body' => 'Clear the barrow.',
        'rewards' => 'The Sunblade.',
        'dm_notes' => 'It is cursed.',
    ]);

    // Strict on purpose: the point of this assertion is that dm_notes is absent.
    expect($quest->toSearchableArray())->toBe([
        'name' => $quest->name,
        'body' => 'Clear the barrow.',
        'rewards' => 'The Sunblade.',
        'character_class' => null,
    ]);
});
