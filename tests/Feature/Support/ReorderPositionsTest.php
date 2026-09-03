<?php

use App\Actions\Support\ReorderPositions;
use App\Models\Campaign;
use App\Models\GameSession;
use App\Models\Scene;
use Illuminate\Support\Collection;

/**
 * Exercised through scenes because they were the first ordered list in the app.
 * Quest objectives, combatants, and random table entries all reuse this action.
 *
 * @return array{0: GameSession, 1: Collection<int, Scene>}
 */
function orderedRows(): array
{
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->create();

    $rows = collect(['First', 'Second', 'Third', 'Fourth'])
        ->map(fn (string $title, int $index) => Scene::factory()->inSession($session, $index)->create(['title' => $title]));

    return [$session, $rows];
}

it('moves a row down the list', function () {
    [$session, $rows] = orderedRows();

    app(ReorderPositions::class)->handle($session->scenes()->getQuery(), $rows[0]->id, 2);

    expect($session->scenes()->pluck('title')->all())->toBe(['Second', 'Third', 'First', 'Fourth']);
});

it('moves a row up the list', function () {
    [$session, $rows] = orderedRows();

    app(ReorderPositions::class)->handle($session->scenes()->getQuery(), $rows[3]->id, 1);

    expect($session->scenes()->pluck('title')->all())->toBe(['First', 'Fourth', 'Second', 'Third']);
});

it('clamps a position past either end', function () {
    [$session, $rows] = orderedRows();
    $reorder = app(ReorderPositions::class);

    $reorder->handle($session->scenes()->getQuery(), $rows[0]->id, 99);

    expect($session->scenes()->pluck('title')->all())->toBe(['Second', 'Third', 'Fourth', 'First']);

    $reorder->handle($session->scenes()->getQuery(), $rows[0]->id, -5);

    expect($session->scenes()->pluck('title')->all())->toBe(['First', 'Second', 'Third', 'Fourth']);
});

it('ignores an id that is not in the list', function () {
    [$session, $rows] = orderedRows();
    $other = GameSession::factory()->for($session->campaign)->number(2)->create();
    $stranger = Scene::factory()->inSession($other)->create(['title' => 'Elsewhere']);

    app(ReorderPositions::class)->handle($session->scenes()->getQuery(), $stranger->id, 0);

    expect($session->scenes()->pluck('title')->all())->toBe(['First', 'Second', 'Third', 'Fourth'])
        ->and($stranger->refresh()->position)->toBe(0);
});

it('rewrites positions to a contiguous run from zero', function () {
    [$session, $rows] = orderedRows();

    $rows[1]->delete();
    $rows[2]->update(['position' => 47]);

    app(ReorderPositions::class)->handle($session->scenes()->getQuery(), $rows[3]->id, 0);

    expect($session->scenes()->pluck('title')->all())->toBe(['Fourth', 'First', 'Third'])
        ->and($session->scenes()->pluck('position')->all())->toBe([0, 1, 2]);
});

it('moves a row one step in either direction', function () {
    [$session, $rows] = orderedRows();
    $reorder = app(ReorderPositions::class);

    $reorder->move($session->scenes()->getQuery(), $rows[0]->id, 0, 1);

    expect($session->scenes()->pluck('title')->all())->toBe(['Second', 'First', 'Third', 'Fourth']);

    $reorder->move($session->scenes()->getQuery(), $rows[3]->id, 3, -1);

    expect($session->scenes()->pluck('title')->all())->toBe(['Second', 'First', 'Fourth', 'Third']);
});
