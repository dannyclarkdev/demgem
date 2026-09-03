<?php

use App\Livewire\Sessions\Prep;
use App\Models\Campaign;
use App\Models\GameSession;
use App\Models\Scene;
use Illuminate\Support\Collection;
use Livewire\Livewire;

/**
 * @return array{0: Campaign, 1: GameSession, 2: Collection<int, Scene>}
 */
function threeScenes(): array
{
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->create();

    $scenes = collect(['First', 'Second', 'Third'])
        ->map(fn (string $title, int $index) => Scene::factory()->inSession($session, $index)->create(['title' => $title]));

    return [$campaign, $session, $scenes];
}

it('moves a scene by drag to a new position', function () {
    [$campaign, $session, $scenes] = threeScenes();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Prep::class, ['campaign' => $campaign, 'number' => 1])
        ->call('reorderScenes', $scenes[2]->id, 0);

    expect($session->scenes()->pluck('title')->all())->toBe(['Third', 'First', 'Second']);
});

it('moves a scene one step with the buttons', function () {
    [$campaign, $session, $scenes] = threeScenes();

    $component = Livewire::actingAs(ownerOf($campaign))
        ->test(Prep::class, ['campaign' => $campaign, 'number' => 1]);

    $component->call('moveScene', $scenes[0]->id, 1);

    expect($session->scenes()->pluck('title')->all())->toBe(['Second', 'First', 'Third']);

    $component->call('moveScene', $scenes[2]->id, -1);

    expect($session->scenes()->pluck('title')->all())->toBe(['Second', 'Third', 'First']);
});

it('keeps positions contiguous from zero after every move', function () {
    [$campaign, $session, $scenes] = threeScenes();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Prep::class, ['campaign' => $campaign, 'number' => 1])
        ->call('reorderScenes', $scenes[0]->id, 2);

    expect($session->scenes()->pluck('position')->all())->toBe([0, 1, 2]);
});

it('keeps positions contiguous after a delete', function () {
    [$campaign, $session, $scenes] = threeScenes();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Prep::class, ['campaign' => $campaign, 'number' => 1])
        ->call('removeScene', $scenes[0]->id)
        ->call('reorderScenes', $scenes[2]->id, 0);

    expect($session->scenes()->pluck('title')->all())->toBe(['Third', 'Second'])
        ->and($session->scenes()->pluck('position')->all())->toBe([0, 1]);
});

it('clamps a position past the end of the list', function () {
    [$campaign, $session, $scenes] = threeScenes();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Prep::class, ['campaign' => $campaign, 'number' => 1])
        ->call('reorderScenes', $scenes[0]->id, 99);

    expect($session->scenes()->pluck('title')->all())->toBe(['Second', 'Third', 'First']);
});

it('ignores a scene from another session', function () {
    [$campaign, $session] = threeScenes();
    $other = GameSession::factory()->for($campaign)->number(2)->create();
    $stranger = Scene::factory()->inSession($other)->create(['title' => 'Elsewhere']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Prep::class, ['campaign' => $campaign, 'number' => 1])
        ->call('reorderScenes', $stranger->id, 0);

    expect($session->scenes()->pluck('title')->all())->toBe(['First', 'Second', 'Third'])
        ->and($stranger->refresh()->position)->toBe(0);
});

it('appends a new scene to the end', function () {
    [$campaign, $session] = threeScenes();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Prep::class, ['campaign' => $campaign, 'number' => 1])
        ->set('newSceneTitle', 'Fourth')
        ->call('addScene');

    expect($session->scenes()->pluck('title')->all())->toBe(['First', 'Second', 'Third', 'Fourth'])
        ->and($session->scenes()->pluck('position')->all())->toBe([0, 1, 2, 3]);
});
