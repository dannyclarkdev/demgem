<?php

use App\Enums\CampaignRole;
use App\Livewire\Sessions\Prep;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\Mention;
use App\Models\Scene;
use Livewire\Livewire;

it('adds a scene and opens it for editing', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Prep::class, ['campaign' => $campaign, 'number' => 1])
        ->set('newSceneTitle', 'The toll bridge')
        ->call('addScene')
        ->assertHasNoErrors()
        ->assertSet('newSceneTitle', '')
        ->assertSet('sceneTitle', 'The toll bridge');

    $scene = $session->scenes()->sole();

    expect($scene->title)->toBe('The toll bridge')
        ->and($scene->position)->toBe(0)
        ->and($scene->campaign_id)->toBe($campaign->id);
});

it('requires a scene title', function () {
    $campaign = Campaign::factory()->create();
    GameSession::factory()->for($campaign)->number(1)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Prep::class, ['campaign' => $campaign, 'number' => 1])
        ->set('newSceneTitle', '')
        ->call('addScene')
        ->assertHasErrors('newSceneTitle');

    expect(Scene::count())->toBe(0);
});

it('saves a scene title and notes', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->create();
    $scene = Scene::factory()->inSession($session)->create(['title' => 'Old', 'notes' => null]);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Prep::class, ['campaign' => $campaign, 'number' => 1])
        ->call('editScene', $scene->id)
        ->assertSet('sceneTitle', 'Old')
        ->set('sceneTitle', 'The toll bridge')
        ->set('sceneNotes', 'A troll asks for [[Mara Voss]].')
        ->call('saveScene')
        ->assertHasNoErrors()
        ->assertSet('editingSceneId', null);

    $scene->refresh();

    expect($scene->title)->toBe('The toll bridge')
        ->and($scene->notes)->toBe('A troll asks for [[Mara Voss]].');
});

it('renders scene notes with working wiki links', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->create();
    $mara = Entity::factory()->for($campaign)->create(['name' => 'Mara Voss']);
    Scene::factory()->inSession($session)->withNotes('A troll asks for [[Mara Voss]].')->create();

    $this->actingAs(ownerOf($campaign))
        ->get(route('sessions.prep', [$campaign, 1]))
        ->assertOk()
        ->assertSee($mara->url());
});

it('removes a scene and its notes leave the mention index', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->create();
    Entity::factory()->for($campaign)->create(['name' => 'Mara Voss']);
    $scene = Scene::factory()->inSession($session)->withNotes('[[Mara Voss]] waits.')->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Prep::class, ['campaign' => $campaign, 'number' => 1])
        ->call('removeScene', $scene->id);

    expect(Scene::count())->toBe(0)
        ->and(Mention::query()->where('source_id', $scene->id)->count())->toBe(0);
});

it('keeps players and spectators off the prep screen', function (CampaignRole $role) {
    $campaign = Campaign::factory()->create();
    GameSession::factory()->for($campaign)->number(1)->create();

    $this->actingAs(memberOf($campaign, $role))
        ->get(route('sessions.prep', [$campaign, 1]))
        ->assertForbidden();
})->with(['player' => CampaignRole::Player, 'spectator' => CampaignRole::Spectator]);

it('never shows prep content to a player anywhere they can reach', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $session = GameSession::factory()->for($campaign)->number(1)->create([
        'strong_start' => 'A bell rings and nobody opened the door.',
    ]);
    Scene::factory()->inSession($session)->create(['title' => 'The toll bridge', 'notes' => 'The troll is bluffing.']);

    $this->actingAs($player)
        ->get(route('sessions.show', [$campaign, 1]))
        ->assertOk()
        ->assertDontSee('A bell rings')
        ->assertDontSee('The toll bridge')
        ->assertDontSee('The troll is bluffing');
});

it('saves the strong start and GM notes', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Prep::class, ['campaign' => $campaign, 'number' => 1])
        ->set('strong_start', 'The bell above the door rings, and nobody opened it.')
        ->set('dm_notes', 'Keep the duke off screen.')
        ->call('saveNotes')
        ->assertHasNoErrors();

    $session->refresh();

    expect($session->strong_start)->toBe('The bell above the door rings, and nobody opened it.')
        ->and($session->dm_notes)->toBe('Keep the duke off screen.')
        ->and($session->updated_by)->toBe(ownerOf($campaign)->id);
});
