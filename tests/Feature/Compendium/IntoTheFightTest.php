<?php

use App\Actions\Dice\RollDice;
use App\Actions\Encounters\AddCombatants;
use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Enums\Ruleset;
use App\Livewire\Encounters\Tracker;
use App\Livewire\Table\Fight;
use App\Models\Campaign;
use App\Models\Combatant;
use App\Models\Encounter;
use App\Models\Entity;
use App\Models\StatBlock;
use App\Support\Dice\DiceRoller;
use Livewire\Livewire;
use Random\Engine\Mt19937;
use Random\Randomizer;

beforeEach(function () {
    $this->campaign = Campaign::factory()->create(['ruleset' => Ruleset::Srd5e2024]);
    $this->owner = ownerOf($this->campaign);
    $this->encounter = Encounter::factory()->for($this->campaign)->create();

    $this->goblin = StatBlock::factory()->named('Goblin Warrior', [
        'hp' => 10,
        'ac' => 15,
        'initiative_bonus' => 2,
        'hit_dice' => '3d6',
        'actions' => [['name' => 'Scimitar', 'text' => 'A wicked curved blade.']],
    ])->create();
});

it('brings the numbers from the book', function () {
    app(AddCombatants::class)->fromStatBlock($this->encounter, $this->goblin, 4);

    $combatants = $this->encounter->combatants()->orderBy('position')->get();

    expect($combatants)->toHaveCount(4)
        ->and($combatants->pluck('name')->all())->toBe([
            'Goblin Warrior 1', 'Goblin Warrior 2', 'Goblin Warrior 3', 'Goblin Warrior 4',
        ])
        ->and($combatants->pluck('hp')->unique()->all())->toBe([10])
        ->and($combatants->pluck('ac')->unique()->all())->toBe([15])
        ->and($combatants->pluck('initiative_bonus')->unique()->all())->toBe([2])
        ->and($combatants->pluck('stat_block_id')->unique()->all())->toBe([$this->goblin->id]);
});

it('keeps a creature hidden from the party until the GM says otherwise', function () {
    app(AddCombatants::class)->fromStatBlock($this->encounter, $this->goblin, 2);

    expect($this->encounter->combatants()->where('player_visible', true)->count())->toBe(0);
});

it('rolls a different total for each copy when asked', function () {
    $addCombatants = new AddCombatants(
        new RollDice(new DiceRoller(new Randomizer(new Mt19937(1))))
    );

    $addCombatants->fromStatBlock($this->encounter, $this->goblin, 6, rollHitPoints: true);

    $totals = $this->encounter->combatants()->pluck('hp');

    expect($totals->unique()->count())->toBeGreaterThan(1)
        ->and($totals->min())->toBeGreaterThanOrEqual(3)
        ->and($totals->max())->toBeLessThanOrEqual(18);

    $this->encounter->combatants()->get()->each(
        fn (Combatant $combatant) => expect($combatant->hp)->toBe($combatant->max_hp)
    );
});

it('takes the average when it is not asked to roll', function () {
    app(AddCombatants::class)->fromStatBlock($this->encounter, $this->goblin, 3);

    expect($this->encounter->combatants()->pluck('hp')->unique()->all())->toBe([10]);
});

it('leaves a fight whole when the creature leaves the dataset', function () {
    app(AddCombatants::class)->fromStatBlock($this->encounter, $this->goblin, 1);

    $this->goblin->delete();

    $combatant = $this->encounter->combatants()->sole();

    expect($combatant->name)->toBe('Goblin Warrior')
        ->and($combatant->hp)->toBe(10)
        ->and($combatant->ac)->toBe(15)
        ->and($combatant->stat_block_id)->toBeNull();
});

it('fills the numbers when an NPC names what it fights as', function () {
    $npc = Entity::factory()->for($this->campaign)->create([
        'type' => EntityType::Character,
        'name' => 'The Drowned Duke',
        'stat_block_id' => $this->goblin->id,
    ]);

    app(AddCombatants::class)->fromEntities($this->encounter, collect([$npc]));

    $combatant = $this->encounter->combatants()->sole();

    expect($combatant->name)->toBe('The Drowned Duke')
        ->and($combatant->hp)->toBe(10)
        ->and($combatant->ac)->toBe(15)
        ->and($combatant->stat_block_id)->toBe($this->goblin->id);
});

it('adds from the tracker picker', function () {
    Livewire::actingAs($this->owner)
        ->test(Tracker::class, ['campaign' => $this->campaign, 'encounter' => $this->encounter])
        ->set('compendiumSearch', 'goblin')
        ->assertSee('Goblin Warrior')
        ->set('newQuantity', 3)
        ->call('addFromCompendium', $this->goblin->id)
        ->assertHasNoErrors();

    expect($this->encounter->combatants()->count())->toBe(3);
});

it('runs no compendium query until the GM types', function () {
    Livewire::actingAs($this->owner)
        ->test(Tracker::class, ['campaign' => $this->campaign, 'encounter' => $this->encounter])
        ->assertDontSee('Goblin Warrior');
});

it('never sends a creature\'s prose to the party', function () {
    app(AddCombatants::class)->fromStatBlock($this->encounter, $this->goblin, 1);

    $this->encounter->combatants()->update(['player_visible' => true]);

    $player = memberOf($this->campaign, CampaignRole::Player);

    Livewire::actingAs($player)
        ->test(Fight::class, ['campaign' => $this->campaign, 'encounterId' => $this->encounter->id])
        ->assertDontSee('A wicked curved blade')
        ->assertDontSee('Scimitar');
});
