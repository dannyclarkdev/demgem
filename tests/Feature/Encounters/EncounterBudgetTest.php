<?php

use App\Enums\EncounterDifficulty;
use App\Livewire\Encounters\Tracker;
use App\Models\Campaign;
use App\Models\Combatant;
use App\Models\Encounter;
use App\Models\Entity;
use App\Models\StatBlock;
use App\Support\Encounters\Budget;
use Livewire\Livewire;

/**
 * What a fight is worth, and what the party in front of it can afford.
 *
 * The numbers are demgem's own, derived from the SRD's own XP ladder by the one rule
 * config/encounters.php states. These tests hold the rule and the derivation, so a
 * change to either is a deliberate one.
 */
it('gives a character a quarter, a half and three quarters of their own level in XP', function () {
    // A CR 5 creature is worth 1800 by the shipped ladder.
    $budget = Budget::forParty([5]);

    expect($budget->low)->toBe(450)
        ->and($budget->moderate)->toBe(900)
        ->and($budget->high)->toBe(1350)
        ->and($budget->characters)->toBe(1);
});

it('adds up over the party', function () {
    expect(Budget::forParty([5, 5, 5, 5])->moderate)->toBe(3600);
});

it('counts a character with no level as level one rather than leaving them out', function () {
    $guessed = Budget::forParty([3, null]);
    $known = Budget::forParty([3, 1]);

    expect($guessed->moderate)->toBe($known->moderate)
        ->and($guessed->characters)->toBe(2);
});

it('holds a level inside the range the ladder covers', function () {
    expect(Budget::forParty([99])->high)->toBe(Budget::forParty([20])->high)
        ->and(Budget::forParty([0])->high)->toBe(Budget::forParty([1])->high);
});

it('reads a rating the SRD names no creature at in a straight line between its neighbours', function () {
    // CR 17 is 18,000 and CR 19 is 22,000, and nothing in the document sits at 18.
    expect(Budget::xpForChallenge(18.0))->toBe(20000);
});

it('takes a rating past either end of the ladder as that end', function () {
    expect(Budget::xpForChallenge(40.0))->toBe(155000)
        ->and(Budget::xpForChallenge(-1.0))->toBe(10);
});

it('keeps the ladder agreeing with the dataset it was read out of', function () {
    $document = json_decode((string) file_get_contents((string) config('compendium.dataset')), true);

    $byChallenge = [];

    foreach ($document['creatures'] as $creature) {
        if (isset($creature['cr_value'], $creature['xp'])) {
            $byChallenge[(string) (float) $creature['cr_value']][] = (int) $creature['xp'];
        }
    }

    foreach (config('encounters.ladder') as $cr => $xp) {
        $printed = $byChallenge[(string) (float) $cr] ?? null;

        expect($printed)->not->toBeNull("The dataset names no creature at CR {$cr}.");

        // The rung is what most creatures of that rating print, ties going higher.
        $counts = array_count_values($printed);
        arsort($counts);
        $winners = array_keys(array_filter($counts, fn (int $count) => $count === max($counts)));

        expect($xp)->toBe(max($winners), "CR {$cr} disagrees with the dataset.");
    }
});

it('names five answers from three thresholds', function () {
    $budget = Budget::forParty([5, 5, 5, 5]);

    expect($budget->difficultyFor(0))->toBe(EncounterDifficulty::Trivial)
        ->and($budget->difficultyFor($budget->low))->toBe(EncounterDifficulty::Low)
        ->and($budget->difficultyFor($budget->moderate))->toBe(EncounterDifficulty::Moderate)
        ->and($budget->difficultyFor($budget->high))->toBe(EncounterDifficulty::High)
        ->and($budget->difficultyFor($budget->high * 2))->toBe(EncounterDifficulty::Deadly);
});

it('prices the fight from the characters in it', function () {
    $campaign = Campaign::factory()->create();
    $encounter = Encounter::factory()->for($campaign)->create();

    $ogre = StatBlock::factory()->named('Ogre')->atChallenge('2', 2.0, 450)->create();

    foreach (range(1, 4) as $ignored) {
        $pc = Entity::factory()->for($campaign)->create(['is_pc' => true, 'level' => 3]);
        Combatant::factory()->inEncounter($encounter)->forEntity($pc)->create();
    }

    Combatant::factory()->inEncounter($encounter)->create(['stat_block_id' => $ogre->id]);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Tracker::class, ['campaign' => $campaign, 'encounter' => $encounter])
        ->assertViewHas('spent', 450)
        ->assertViewHas('difficulty', EncounterDifficulty::Trivial)
        ->assertSee('4 characters');
});

it('counts only what the compendium can price and says how much it could not', function () {
    $campaign = Campaign::factory()->create();
    $encounter = Encounter::factory()->for($campaign)->create();

    $statBlock = StatBlock::factory()->atChallenge('1', 1.0, 200)->create();
    $pc = Entity::factory()->for($campaign)->create(['is_pc' => true, 'level' => 3]);

    Combatant::factory()->inEncounter($encounter)->forEntity($pc)->create();
    Combatant::factory()->inEncounter($encounter)->create(['stat_block_id' => $statBlock->id]);
    Combatant::factory()->count(2)->inEncounter($encounter)->create(['name' => 'A thing the GM typed']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Tracker::class, ['campaign' => $campaign, 'encounter' => $encounter])
        ->assertViewHas('spent', 200)
        ->assertViewHas('unpriced', 2)
        ->assertSee('not priced');
});

it('does not charge the party for being in the fight', function () {
    $campaign = Campaign::factory()->create();
    $encounter = Encounter::factory()->for($campaign)->create();

    // A player character who fights as something, which slice 15 allows.
    $statBlock = StatBlock::factory()->atChallenge('1', 1.0, 200)->create();
    $pc = Entity::factory()->for($campaign)->create(['is_pc' => true, 'level' => 2]);

    Combatant::factory()->inEncounter($encounter)->forEntity($pc)
        ->create(['stat_block_id' => $statBlock->id]);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Tracker::class, ['campaign' => $campaign, 'encounter' => $encounter])
        ->assertViewHas('spent', 0);
});

it('falls back to the campaign party while the fight is still empty', function () {
    $campaign = Campaign::factory()->create();
    $encounter = Encounter::factory()->for($campaign)->create();

    Entity::factory()->count(3)->for($campaign)->create(['is_pc' => true, 'level' => 4]);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Tracker::class, ['campaign' => $campaign, 'encounter' => $encounter])
        ->assertViewHas('budget', fn (Budget $budget) => $budget->characters === 3);
});

it('says there is no budget when the campaign has no characters', function () {
    $campaign = Campaign::factory()->create();
    $encounter = Encounter::factory()->for($campaign)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Tracker::class, ['campaign' => $campaign, 'encounter' => $encounter])
        ->assertViewHas('budget', fn (Budget $budget) => ! $budget->hasParty())
        ->assertSee('Add the party to see what this fight is worth');
});

it('says whose scale it is, because the SRD publishes no budget', function () {
    $campaign = Campaign::factory()->create();
    $encounter = Encounter::factory()->for($campaign)->create();

    Entity::factory()->for($campaign)->create(['is_pc' => true, 'level' => 1]);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Tracker::class, ['campaign' => $campaign, 'encounter' => $encounter])
        ->assertSee('own scale');
});
