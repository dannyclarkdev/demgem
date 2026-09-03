<?php

use App\Enums\CampaignRole;
use App\Livewire\Sessions\Prep;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\Mention;
use App\Models\Secret;
use Livewire\Livewire;

it('adds a secret to the session', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Prep::class, ['campaign' => $campaign, 'number' => 1])
        ->set('newSecretBody', "The duke's signet ring is a forgery.")
        ->call('addSecret')
        ->assertHasNoErrors()
        ->assertSet('newSecretBody', '');

    $secret = $session->secrets()->sole();

    expect($secret->body)->toBe("The duke's signet ring is a forgery.")
        ->and($secret->position)->toBe(0)
        ->and($secret->campaign_id)->toBe($campaign->id)
        ->and($secret->created_by)->toBe(ownerOf($campaign)->id)
        ->and($secret->revealed_at)->toBeNull();
});

it('requires a body', function () {
    $campaign = Campaign::factory()->create();
    GameSession::factory()->for($campaign)->number(1)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Prep::class, ['campaign' => $campaign, 'number' => 1])
        ->set('newSecretBody', '')
        ->call('addSecret')
        ->assertHasErrors('newSecretBody');

    expect(Secret::count())->toBe(0);
});

it('edits and deletes a secret', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->create();
    $secret = Secret::factory()->for($campaign)->preparedFor($session)->create(['body' => 'Old wording']);

    $component = Livewire::actingAs(ownerOf($campaign))->test(Prep::class, ['campaign' => $campaign, 'number' => 1]);

    $component->call('editSecret', $secret->id)
        ->assertSet('secretBody', 'Old wording')
        ->set('secretBody', 'Better wording')
        ->call('saveSecret')
        ->assertHasNoErrors()
        ->assertSet('editingSecretId', null);

    expect($secret->refresh()->body)->toBe('Better wording');

    $component->call('removeSecret', $secret->id);

    expect(Secret::count())->toBe(0);
});

it('renders wiki links inside a secret without indexing it', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->create();
    $mara = Entity::factory()->for($campaign)->create(['name' => 'Mara Voss']);
    $secret = Secret::factory()->for($campaign)->preparedFor($session)->create(['body' => '[[Mara Voss]] forged the ring.']);

    $this->actingAs(ownerOf($campaign))
        ->get(route('sessions.prep', [$campaign, 1]))
        ->assertOk()
        ->assertSee($mara->url());

    expect(Mention::query()->where('source_id', $secret->id)->count())->toBe(0);
});

it('never shows a secret to a player, revealed or not', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $session = GameSession::factory()->for($campaign)->number(1)->published('We published this.')->create();
    Secret::factory()->for($campaign)->preparedFor($session)->create(['body' => 'The duke is lying.']);
    Secret::factory()->for($campaign)->preparedFor($session)->revealedIn($session)->create(['body' => 'The ring is a forgery.']);

    $this->actingAs($player)
        ->get(route('sessions.show', [$campaign, 1]))
        ->assertOk()
        ->assertSee('We published this.')
        ->assertDontSee('The duke is lying.')
        ->assertDontSee('The ring is a forgery.');

    $this->actingAs($player)
        ->get(route('sessions.prep', [$campaign, 1]))
        ->assertForbidden();
});

it('stops a player from touching a secret through the component', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->create();
    Secret::factory()->for($campaign)->preparedFor($session)->create();

    Livewire::actingAs(memberOf($campaign, CampaignRole::Player))
        ->test(Prep::class, ['campaign' => $campaign, 'number' => 1])
        ->assertForbidden();
});
