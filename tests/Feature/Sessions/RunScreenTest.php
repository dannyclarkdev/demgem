<?php

use App\Enums\CampaignRole;
use App\Enums\PrepRole;
use App\Enums\SessionStatus;
use App\Livewire\Sessions\LiveNotes;
use App\Livewire\Sessions\Run;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\Scene;
use App\Models\Secret;
use Livewire\Livewire;

it('puts the prep in front of the GM at the table', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->create([
        'strong_start' => 'The bell above the door rings, and nobody opened it.',
    ]);
    Scene::factory()->inSession($session)->create(['title' => 'The toll bridge']);
    Secret::factory()->for($campaign)->preparedFor($session)->create(['body' => 'The ring is a forgery.']);
    $mara = Entity::factory()->for($campaign)->create(['name' => 'Mara Voss']);
    $session->entities()->attach($mara->id, ['role' => PrepRole::Npc->value, 'position' => 0]);

    $this->actingAs(ownerOf($campaign))
        ->get(route('sessions.run', [$campaign, 1]))
        ->assertOk()
        ->assertSee('The bell above the door rings')
        ->assertSee('The toll bridge')
        ->assertSee('The ring is a forgery.')
        ->assertSee('Mara Voss')
        ->assertSeeLivewire(LiveNotes::class);
});

it('reveals a secret from the table and records this session', function () {
    $campaign = Campaign::factory()->create();
    $first = GameSession::factory()->for($campaign)->number(1)->create();
    $second = GameSession::factory()->for($campaign)->number(2)->create();
    $secret = Secret::factory()->for($campaign)->preparedFor($first)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Run::class, ['campaign' => $campaign, 'number' => 2])
        ->call('revealSecret', $secret->id);

    expect($secret->refresh()->revealed_in_session_id)->toBe($second->id);
});

it('shows carried secrets alongside the ones prepped for tonight', function () {
    $campaign = Campaign::factory()->create();
    $first = GameSession::factory()->for($campaign)->number(1)->create();
    $second = GameSession::factory()->for($campaign)->number(2)->create();
    Secret::factory()->for($campaign)->preparedFor($first)->create(['body' => 'Left over from last time']);
    Secret::factory()->for($campaign)->preparedFor($second)->create(['body' => 'Written for tonight']);

    $ready = Livewire::actingAs(ownerOf($campaign))
        ->test(Run::class, ['campaign' => $campaign, 'number' => 2])
        ->viewData('readySecrets');

    expect($ready->pluck('body')->all())->toBe(['Written for tonight', 'Left over from last time']);
});

it('undoes a reveal from the table', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->create();
    $secret = Secret::factory()->for($campaign)->preparedFor($session)->revealedIn($session)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Run::class, ['campaign' => $campaign, 'number' => 1])
        ->call('unrevealSecret', $secret->id);

    expect($secret->refresh()->isRevealed())->toBeFalse();
});

it('marks the session played and puts it back again', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->create();

    $component = Livewire::actingAs(ownerOf($campaign))->test(Run::class, ['campaign' => $campaign, 'number' => 1]);

    $component->call('setStatus', SessionStatus::Played->value);

    expect($session->refresh()->status)->toBe(SessionStatus::Played);

    $component->call('setStatus', SessionStatus::Planned->value);

    expect($session->refresh()->status)->toBe(SessionStatus::Planned);
});

it('publishes nothing when the session is marked played', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->create(['recap' => 'A draft.']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Run::class, ['campaign' => $campaign, 'number' => 1])
        ->call('setStatus', SessionStatus::Played->value);

    expect($session->refresh()->recap_published_at)->toBeNull()
        ->and($session->hasPublishedRecap())->toBeFalse();
});

it('keeps players off the run screen', function (CampaignRole $role) {
    $campaign = Campaign::factory()->create();
    GameSession::factory()->for($campaign)->number(1)->create();

    $this->actingAs(memberOf($campaign, $role))
        ->get(route('sessions.run', [$campaign, 1]))
        ->assertForbidden();
})->with(['player' => CampaignRole::Player, 'spectator' => CampaignRole::Spectator]);

it('loads a full table without a lazy query, which strict mode would throw on', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->withPrep()->create();
    Scene::factory()->inSession($session)->count(5)->create();
    Secret::factory()->for($campaign)->preparedFor($session)->count(8)->create();
    $player = memberOf($campaign, CampaignRole::Player);
    Entity::factory()->for($campaign)->pcOf($player)->count(1)->create();

    foreach (PrepRole::cases() as $prepRole) {
        $entities = Entity::factory()->for($campaign)->count(3)->create();

        foreach ($entities as $position => $entity) {
            $session->entities()->attach($entity->id, ['role' => $prepRole->value, 'position' => $position]);
        }
    }

    $this->actingAs(ownerOf($campaign))
        ->get(route('sessions.run', [$campaign, 1]))
        ->assertOk();
});
