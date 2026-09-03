<?php

use App\Actions\RandomTables\RollRandomTable;
use App\Models\Campaign;
use App\Models\RandomTable;
use App\Models\RandomTableEntry;
use Random\Engine\Mt19937;
use Random\Randomizer;

function roller(int $seed = 1234): RollRandomTable
{
    return new RollRandomTable(new Randomizer(new Mt19937($seed)));
}

it('rolls one entry from a flat table', function () {
    $campaign = Campaign::factory()->create();
    $table = RandomTable::factory()->for($campaign)->withEntries(['One', 'Two', 'Three'])->create();

    $result = roller()->handle($table);

    expect($result)->toHaveCount(1)
        ->and($result[0]['entry']->body)->toBeIn(['One', 'Two', 'Three'])
        ->and($result[0]['roll'])->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(3)
        ->and($result[0]['note'])->toBeNull();
});

it('respects the weights', function () {
    $campaign = Campaign::factory()->create();
    $table = RandomTable::factory()->for($campaign)->create();

    // Rare is number 1; Common is 2 through 100.
    RandomTableEntry::factory()->inTable($table, 0)->weighing(1)->create(['body' => 'Rare']);
    RandomTableEntry::factory()->inTable($table, 1)->weighing(99)->create(['body' => 'Common']);

    $roller = roller(42);
    $counts = ['Rare' => 0, 'Common' => 0];

    for ($i = 0; $i < 500; $i++) {
        $counts[$roller->handle($table)[0]['entry']->body]++;
    }

    expect($counts['Common'])->toBeGreaterThan($counts['Rare'] * 10)
        ->and($counts['Rare'] + $counts['Common'])->toBe(500);
});

it('picks the entry whose range the roll lands in', function () {
    $campaign = Campaign::factory()->create();
    $table = RandomTable::factory()->for($campaign)->create();

    RandomTableEntry::factory()->inTable($table, 0)->weighing(5)->create(['body' => 'First five']);
    RandomTableEntry::factory()->inTable($table, 1)->weighing(5)->create(['body' => 'Second five']);

    $roller = roller(7);

    for ($i = 0; $i < 100; $i++) {
        $result = $roller->handle($table)[0];
        $expected = $result['roll'] <= 5 ? 'First five' : 'Second five';

        expect($result['entry']->body)->toBe($expected);
    }
});

it('reports an empty table rather than failing', function () {
    $campaign = Campaign::factory()->create();
    $table = RandomTable::factory()->for($campaign)->create(['name' => 'Rumours']);

    $result = roller()->handle($table);

    expect($result)->toHaveCount(1)
        ->and($result[0]['entry'])->toBeNull()
        ->and($result[0]['note'])->toBe('Rumours has nothing in it yet.');
});

it('follows a nested table and returns the whole chain', function () {
    $campaign = Campaign::factory()->create();
    $names = RandomTable::factory()->for($campaign)->withEntries(['Kell'])->create(['name' => 'Names']);
    $rumours = RandomTable::factory()->for($campaign)->create(['name' => 'Rumours']);

    RandomTableEntry::factory()->inTable($rumours)->nesting($names)->create(['body' => 'Somebody is looking for']);

    $result = roller()->handle($rumours);

    expect($result)->toHaveCount(2)
        ->and($result[0]['table'])->toBe('Rumours')
        ->and($result[0]['entry']->body)->toBe('Somebody is looking for')
        ->and($result[1]['table'])->toBe('Names')
        ->and($result[1]['entry']->body)->toBe('Kell');
});

it('stops an A to B to A loop and names it', function () {
    $campaign = Campaign::factory()->create();
    $a = RandomTable::factory()->for($campaign)->create(['name' => 'A']);
    $b = RandomTable::factory()->for($campaign)->create(['name' => 'B']);

    RandomTableEntry::factory()->inTable($a)->nesting($b)->create(['body' => 'From A']);
    RandomTableEntry::factory()->inTable($b)->nesting($a)->create(['body' => 'From B']);

    $result = roller()->handle($a);

    expect($result)->toHaveCount(3)
        ->and($result[2]['entry'])->toBeNull()
        ->and($result[2]['note'])->toBe('Stopped: A nests back into itself.');
});

it('stops a long chain at the depth limit', function () {
    $campaign = Campaign::factory()->create();

    // Six tables, each nesting the next. The sixth is one past the limit.
    $tables = collect(range(1, 6))
        ->map(fn (int $n) => RandomTable::factory()->for($campaign)->create(['name' => "Table {$n}"]));

    foreach ($tables as $index => $table) {
        $next = $tables[$index + 1] ?? null;

        RandomTableEntry::factory()->inTable($table)
            ->when($next !== null, fn ($factory) => $factory->nesting($next))
            ->create(['body' => "Result {$index}"]);
    }

    $result = roller()->handle($tables[0]);

    expect($result)->toHaveCount(RollRandomTable::MAX_DEPTH + 1)
        ->and(end($result)['note'])->toBe('Stopped after '.RollRandomTable::MAX_DEPTH.' nested tables.');
});

it('rolls the same chain from the same seed', function () {
    $campaign = Campaign::factory()->create();
    $table = RandomTable::factory()->for($campaign)->withEntries(['One', 'Two', 'Three', 'Four'])->create();

    expect(roller(99)->handle($table)[0]['entry']->id)
        ->toBe(roller(99)->handle($table)[0]['entry']->id);
});
