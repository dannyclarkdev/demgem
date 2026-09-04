<?php

use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Enums\PrepRole;
use App\Livewire\Clocks\Index;
use App\Livewire\Clocks\Panel;
use App\Models\Campaign;
use App\Models\Clock;
use App\Models\Entity;
use App\Models\GameSession;
use Livewire\Livewire;

/**
 * What a clock is about, and where a handout turns up. Both are links to things that
 * already existed, which is the point: neither needed a column.
 */
it('points a clock at an entity from the form', function () {
    $campaign = Campaign::factory()->create();
    $cult = Entity::factory()->for($campaign)->type(EntityType::Faction)->forPlayers()
        ->create(['name' => 'The Ashen Choir', 'slug' => 'ashen-choir']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Index::class, ['campaign' => $campaign])
        ->set('newName', 'The ritual')
        ->set('newSegments', 8)
        ->set('newEntityId', $cult->id)
        ->call('create')
        ->assertHasNoErrors();

    expect(Clock::query()->sole()->entity_id)->toBe($cult->id);
});

it('repoints a clock and unpoints it again', function () {
    $campaign = Campaign::factory()->create();
    $cult = Entity::factory()->for($campaign)->type(EntityType::Faction)->create(['name' => 'The Ashen Choir']);
    $clock = Clock::factory()->inCampaign($campaign)->create(['name' => 'The ritual']);

    $edit = fn () => Livewire::actingAs(ownerOf($campaign))
        ->test(Index::class, ['campaign' => $campaign])
        ->call('edit', $clock->id);

    $edit()->set('editingEntityId', $cult->id)->call('save')->assertHasNoErrors();
    expect($clock->refresh()->entity_id)->toBe($cult->id);

    $edit()->set('editingEntityId', '')->call('save')->assertHasNoErrors();
    expect($clock->refresh()->entity_id)->toBeNull();
});

it('refuses an entity from another campaign', function () {
    $campaign = Campaign::factory()->create();
    $elsewhere = Entity::factory()->for(Campaign::factory()->create())->create(['name' => 'Somebody Else']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Index::class, ['campaign' => $campaign])
        ->set('newName', 'The ritual')
        ->set('newEntityId', $elsewhere->id)
        ->call('create')
        ->assertHasErrors('newEntityId');

    expect(Clock::query()->count())->toBe(0);
});

it('shows the clocks about an entity on that entity page', function () {
    $campaign = Campaign::factory()->create();
    $cult = Entity::factory()->for($campaign)->type(EntityType::Faction)->forPlayers()
        ->create(['name' => 'The Ashen Choir', 'slug' => 'ashen-choir']);

    Clock::factory()->about($cult)->shownToPlayers()->sized(8)->filled(5)->create(['name' => 'The ritual']);
    Clock::factory()->inCampaign($campaign)->shownToPlayers()->create(['name' => 'The drowning tide']);

    // Only the ones about this entity. The campaign-wide panel is a different screen.
    Livewire::actingAs(ownerOf($campaign))
        ->test(Panel::class, ['campaign' => $campaign, 'entityId' => $cult->id])
        ->assertSee('The ritual')
        ->assertDontSee('The drowning tide');
});

it('gives a player the dial and not the name of what it is about', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    $court = Entity::factory()->for($campaign)->type(EntityType::Faction)->dmOnly()
        ->create(['name' => 'The Drowned Court', 'slug' => 'drowned-court']);

    Clock::factory()->about($court)->shownToPlayers()->sized(4)->filled(1)
        ->create(['name' => 'The tally of debts']);

    // The GM revealed the dial on purpose, so the dial stands. The court's page is a
    // separate decision, and it also stands. This is deliberately not the map pin's
    // rule: a pin is the link, and a clock only mentions one.
    $component = Livewire::actingAs($player)
        ->test(Panel::class, ['campaign' => $campaign])
        ->assertSee('The tally of debts')
        ->assertDontSee('The Drowned Court');

    expect(json_encode($component->snapshot, JSON_THROW_ON_ERROR))
        ->not->toContain('The Drowned Court');
});

it('leaves the clock behind when the entity it was about is deleted', function () {
    $campaign = Campaign::factory()->create();
    $cult = Entity::factory()->for($campaign)->type(EntityType::Faction)->create(['name' => 'The Ashen Choir']);
    $clock = Clock::factory()->about($cult)->create(['name' => 'The ritual']);

    $cult->forceDelete();

    expect($clock->refresh()->entity_id)->toBeNull()
        ->and($clock->name)->toBe('The ritual');
});

it('preps a handout into a session with no new column', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->planned()->create(['title' => 'The Cellar']);

    $handout = Entity::factory()->for($campaign)->type(EntityType::Handout)->dmOnly()
        ->create(['name' => 'The sealed orders', 'slug' => 'sealed-orders']);

    // PrepRole::suggestedTypes() sorts the picker and never limits what a GM may
    // attach, so a handout drops into tonight's Treasure bucket with nothing added.
    $session->entities()->attach($handout->id, ['role' => PrepRole::Treasure->value, 'position' => 0]);

    $this->actingAs(ownerOf($campaign))
        ->get(route('sessions.run', [$campaign, 1]))
        ->assertOk()
        ->assertSee('The sealed orders');

    expect($session->entities()->wherePivot('role', PrepRole::Treasure->value)->pluck('entities.id')->all())
        ->toBe([$handout->id]);
});
