<?php

use App\Enums\CampaignRole;
use App\Livewire\Table\Fight;
use App\Models\Campaign;
use App\Models\Combatant;
use App\Models\Encounter;
use App\Models\Entity;
use Livewire\Livewire;

/**
 * The one place a player reads a number off the tracker.
 *
 * .ai/rules/table.md says a player gets a word and never a number, and death saves are
 * the argued exception: a dying character's rolls happen in the open and the table is
 * already counting them out loud. What must not move is the gate. A row the GM has not
 * revealed carries nothing, its death saves included, and hit points stay a word for
 * everything the party can see.
 *
 * No assertion here is against a bare number. Every id is a ULID and a two-digit run
 * turns up in one often enough to redden CI; the proofs are the aria-label the pips
 * carry and names with spaces in them.
 */
function aDyingParty(): array
{
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    $pc = Entity::factory()->for($campaign)->pcOf($player)->forPlayers()->create(['name' => 'Wren Aldercross']);

    $encounter = Encounter::factory()->for($campaign)->active(2)->create(['name' => 'Ambush at the ford']);

    $wren = Combatant::factory()->inEncounter($encounter, 0)->forEntity($pc)->shownToPlayers()
        ->down(30)->create(['death_save_successes' => 1, 'death_save_failures' => 2]);

    $hidden = Combatant::factory()->inEncounter($encounter, 1)->down(60)
        ->create(['name' => 'Cellar lurker', 'death_save_failures' => 1]);

    return [$campaign, $player, $encounter, $wren, $hidden];
}

it('shows the party the death saves of a character they can see', function () {
    [$campaign, $player, $encounter] = aDyingParty();

    Livewire::actingAs($player)
        ->test(Fight::class, ['campaign' => $campaign, 'encounterId' => $encounter->id])
        ->assertSee('Death saves: 1 saved, 2 failed');
});

it('carries nothing at all for a row the GM has not revealed', function () {
    [$campaign, $player, $encounter] = aDyingParty();

    $html = Livewire::actingAs($player)
        ->test(Fight::class, ['campaign' => $campaign, 'encounterId' => $encounter->id])
        ->html();

    expect($html)->not->toContain('Cellar lurker')
        ->and($html)->not->toContain('0 saved, 1 failed');
});

it('still gives the party a word rather than a number for health', function () {
    [$campaign, $player, $encounter, $wren] = aDyingParty();

    $html = Livewire::actingAs($player)
        ->test(Fight::class, ['campaign' => $campaign, 'encounterId' => $encounter->id])
        ->html();

    // "Down" is the word healthWord() gives a row on nought. The maximum it fell from
    // is the GM's business and is not on the page.
    //
    // The marker is the faint span a maximum renders inside, never a bare "/30":
    // Tailwind writes opacity the same way and border-ember/30 is on every badge in
    // the kit. CombatantVisibilityTest learned this first.
    expect($html)->toContain('Down')
        ->and($wren->max_hp)->toBe(30)
        ->and($html)->not->toContain('text-ink-faint">/');
});

it('shows no death saves for a row that is still standing', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $encounter = Encounter::factory()->for($campaign)->active(1)->create();

    $pc = Entity::factory()->for($campaign)->pcOf($player)->forPlayers()->create(['name' => 'Wren Aldercross']);

    Combatant::factory()->inEncounter($encounter)->forEntity($pc)->shownToPlayers()
        ->withHealth(20, 30)->create();

    Livewire::actingAs($player)
        ->test(Fight::class, ['campaign' => $campaign, 'encounterId' => $encounter->id])
        ->assertDontSee('Death saves:');
});

it('answers the gate before it answers the mechanic', function () {
    [, , , $wren, $hidden] = aDyingParty();

    expect($wren->deathSavesVisibleToPlayers())->toBeTrue()
        ->and($hidden->deathSavesVisibleToPlayers())->toBeFalse();

    $hidden->update(['player_visible' => true]);

    expect($hidden->refresh()->deathSavesVisibleToPlayers())->toBeTrue();
});

it('gives the party the lair marker without the words on it', function () {
    [$campaign, $player, $encounter] = aDyingParty();

    $encounter->update([
        'lair_action_note' => 'The ford churns and drags a body under.',
        'lair_initiative' => 20,
    ]);

    Livewire::actingAs($player)
        ->test(Fight::class, ['campaign' => $campaign, 'encounterId' => $encounter->id])
        ->assertSee('Lair action')
        ->assertDontSee('The ford churns');
});

it('gives a GM watching the same screen the words', function () {
    [$campaign, , $encounter] = aDyingParty();

    $encounter->update([
        'lair_action_note' => 'The ford churns and drags a body under.',
        'lair_initiative' => 20,
    ]);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Fight::class, ['campaign' => $campaign, 'encounterId' => $encounter->id])
        ->assertSee('The ford churns');
});
