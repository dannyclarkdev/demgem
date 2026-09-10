<?php

use App\Models\StatBlock;

/**
 * The dataset is licensed material with a recorded provenance, so these tests are
 * about where it came from as much as what is in it. See database/srd/README.md.
 */
it('ships a dataset that matches the checksum in configuration', function () {
    $path = (string) config('compendium.dataset');

    expect(is_file($path))->toBeTrue();
    expect(hash_file('sha256', $path))->toBe(config('compendium.dataset_sha256'));
});

it('loads every creature with its source and licence', function () {
    $this->artisan('demgem:import-srd')->assertSuccessful();

    expect(StatBlock::query()->count())->toBe(330)
        ->and(StatBlock::query()->where('source', 'SRD 5.2.1')->count())->toBe(330)
        ->and(StatBlock::query()->where('license', 'CC-BY-4.0')->count())->toBe(330)
        ->and(StatBlock::query()->where('ruleset', 'srd-5e-2024')->count())->toBe(330);
});

it('reads a stat block the way the book prints it', function () {
    $this->artisan('demgem:import-srd')->assertSuccessful();

    $goblin = StatBlock::query()->where('slug', 'goblin-warrior')->sole();

    expect($goblin->name)->toBe('Goblin Warrior')
        ->and($goblin->type_line)->toBe('Small Fey (Goblinoid), Chaotic Neutral')
        ->and($goblin->size)->toBe('Small')
        ->and($goblin->creature_type)->toBe('Fey')
        ->and($goblin->ac)->toBe(15)
        ->and($goblin->hp)->toBe(10)
        ->and($goblin->hit_dice)->toBe('3d6')
        ->and($goblin->cr)->toBe('1/4')
        ->and($goblin->cr_value)->toBe(0.25)
        ->and($goblin->xp)->toBe(50)
        ->and($goblin->ability_scores['dex']['score'])->toBe(15)
        ->and(array_keys($goblin->sections()))->toBe(['Actions', 'Bonus Actions']);
});

it('reads a swarm as its own size and creature', function () {
    $this->artisan('demgem:import-srd')->assertSuccessful();

    $swarm = StatBlock::query()->where('slug', 'swarm-of-rats')->sole();

    expect($swarm->type_line)->toBe('Medium Swarm of Tiny Beasts, Unaligned')
        ->and($swarm->is_swarm)->toBeTrue()
        ->and($swarm->size)->toBe('Medium')
        ->and($swarm->creature_type)->toBe('Beast');
});

it('changes no ids when it runs twice', function () {
    $this->artisan('demgem:import-srd')->assertSuccessful();

    $before = StatBlock::query()->pluck('id', 'slug');

    $this->artisan('demgem:import-srd')->assertSuccessful();

    expect(StatBlock::query()->pluck('id', 'slug')->all())->toBe($before->all());
});

it('refuses a dataset that does not match its checksum', function () {
    config()->set('compendium.dataset_sha256', str_repeat('a', 64));

    $this->artisan('demgem:import-srd')->assertFailed();

    expect(StatBlock::query()->count())->toBe(0);
});

it('leaves a row the dataset no longer names', function () {
    $kept = StatBlock::factory()->named('The Drowned Duke')->create();

    $this->artisan('demgem:import-srd')->assertSuccessful();

    expect($kept->fresh())->not->toBeNull();
});
