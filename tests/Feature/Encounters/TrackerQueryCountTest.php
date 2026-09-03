<?php

use App\Livewire\Encounters\Tracker;
use App\Models\Campaign;
use App\Models\Combatant;
use App\Models\Encounter;
use App\Models\Entity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * The tracker polls, so its render cost is paid every fifteen seconds on every open
 * device. Strict mode already fails a lazy load; these tests hold the query count.
 */
it('costs the same whether the fight has three combatants or thirty', function () {
    $campaign = Campaign::factory()->create();
    $encounter = Encounter::factory()->for($campaign)->create();
    $entity = Entity::factory()->for($campaign)->create();

    Combatant::factory()->count(3)->inEncounter($encounter)->forEntity($entity)->create();

    $owner = ownerOf($campaign);

    // One warm-up render first: Campaign caches its member lookup per instance, so an
    // unwarmed first call costs one query the second would not.
    countQueriesForTracker($owner, $campaign, $encounter);

    $small = countQueriesForTracker($owner, $campaign, $encounter);

    Combatant::factory()->count(27)->inEncounter($encounter)->forEntity($entity)->create();

    expect(countQueriesForTracker($owner, $campaign, $encounter))->toBe($small);
});

it('does not lazy load an entity per combatant', function () {
    $campaign = Campaign::factory()->create();
    $encounter = Encounter::factory()->for($campaign)->create();

    foreach (range(1, 5) as $ignored) {
        $entity = Entity::factory()->for($campaign)->create();
        Combatant::factory()->inEncounter($encounter)->forEntity($entity)->create();
    }

    // Model::shouldBeStrict() is on outside production, so a lazy load throws here.
    Livewire::actingAs(ownerOf($campaign))
        ->test(Tracker::class, ['campaign' => $campaign, 'encounter' => $encounter])
        ->assertOk();
});

it('polls with the visible modifier so a background tab stops', function () {
    $campaign = Campaign::factory()->create();
    $encounter = Encounter::factory()->for($campaign)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Tracker::class, ['campaign' => $campaign, 'encounter' => $encounter])
        ->assertSeeHtml('wire:poll.visible.'.Tracker::POLL_SECONDS.'s');
});

it('binds the damage box on blur, never live, so a poll cannot clobber it', function () {
    $campaign = Campaign::factory()->create();
    $encounter = Encounter::factory()->for($campaign)->create();
    $combatant = Combatant::factory()->inEncounter($encounter)->withHealth(30)->create();

    $html = Livewire::actingAs(ownerOf($campaign))
        ->test(Tracker::class, ['campaign' => $campaign, 'encounter' => $encounter])
        ->call('openDamage', $combatant->id)
        ->html();

    expect($html)->toContain('wire:model.blur="damage"')
        ->and($html)->not->toContain('wire:model.live');
});

/**
 * Counts only the render. The actor is resolved by the caller, so the helper's own
 * lookups are not part of the number.
 */
function countQueriesForTracker(User $owner, Campaign $campaign, Encounter $encounter): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    Livewire::actingAs($owner)
        ->test(Tracker::class, ['campaign' => $campaign, 'encounter' => $encounter]);

    $count = count(DB::getQueryLog());

    DB::disableQueryLog();

    return $count;
}
