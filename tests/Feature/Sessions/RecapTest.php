<?php

use App\Enums\CampaignRole;
use App\Enums\SessionStatus;
use App\Livewire\Sessions\Show;
use App\Models\Campaign;
use App\Models\GameSession;
use Livewire\Livewire;

it('saves a draft recap that players cannot read', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Show::class, ['campaign' => $campaign, 'number' => 1])
        ->set('recap', 'They burned the bridge behind them.')
        ->call('saveRecap')
        ->assertHasNoErrors();

    $session->refresh();

    expect($session->recap)->toBe('They burned the bridge behind them.')
        ->and($session->recap_published_at)->toBeNull();

    $this->actingAs(memberOf($campaign, CampaignRole::Player))
        ->get(route('sessions.show', [$campaign, 1]))
        ->assertOk()
        ->assertDontSee('They burned the bridge behind them.');
});

it('publishes on purpose and hides again on purpose', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $owner = ownerOf($campaign);

    Livewire::actingAs($owner)
        ->test(Show::class, ['campaign' => $campaign, 'number' => 1])
        ->set('recap', 'They burned the bridge behind them.')
        ->call('publishRecap');

    expect($session->refresh()->hasPublishedRecap())->toBeTrue();

    $this->actingAs($player)
        ->get(route('sessions.show', [$campaign, 1]))
        ->assertOk()
        ->assertSee('They burned the bridge behind them.');

    Livewire::actingAs($owner)
        ->test(Show::class, ['campaign' => $campaign, 'number' => 1])
        ->call('unpublishRecap');

    expect($session->refresh()->recap_published_at)->toBeNull();

    $this->actingAs($player)
        ->get(route('sessions.show', [$campaign, 1]))
        ->assertOk()
        ->assertDontSee('They burned the bridge behind them.');
});

it('refuses to publish an empty recap', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Show::class, ['campaign' => $campaign, 'number' => 1])
        ->set('recap', '')
        ->call('publishRecap')
        ->assertHasErrors('recap');

    expect($session->refresh()->recap_published_at)->toBeNull();
});

it('does not publish anything when the status becomes played', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->withRecap('A draft.')->create();

    expect($session->status)->toBe(SessionStatus::Played)
        ->and($session->hasPublishedRecap())->toBeFalse();

    $this->actingAs(memberOf($campaign, CampaignRole::Player))
        ->get(route('sessions.show', [$campaign, 1]))
        ->assertOk()
        ->assertDontSee('A draft.');
});

it('starts the recap from the live notes only while it is empty', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->create([
        'live_notes' => 'They bribed the guard for 40 gold.',
    ]);

    $component = Livewire::actingAs(ownerOf($campaign))
        ->test(Show::class, ['campaign' => $campaign, 'number' => 1])
        ->call('startRecapFromLiveNotes');

    expect($session->refresh()->recap)->toBe('They bribed the guard for 40 gold.');

    $component->set('recap', 'Edited down.')->call('saveRecap')->call('startRecapFromLiveNotes');

    expect($session->refresh()->recap)->toBe('Edited down.');
});

it('never puts the recap editor state in a player snapshot', function () {
    $campaign = Campaign::factory()->create();
    GameSession::factory()->for($campaign)->number(1)->create(['recap' => 'A draft nobody may read.']);

    Livewire::actingAs(memberOf($campaign, CampaignRole::Player))
        ->test(Show::class, ['campaign' => $campaign, 'number' => 1])
        ->assertSet('recap', '');

    Livewire::actingAs(ownerOf($campaign))
        ->test(Show::class, ['campaign' => $campaign, 'number' => 1])
        ->assertSet('recap', 'A draft nobody may read.');
});

it('stops a player from publishing or editing through the component', function () {
    $campaign = Campaign::factory()->create();
    GameSession::factory()->for($campaign)->number(1)->create();

    Livewire::actingAs(memberOf($campaign, CampaignRole::Player))
        ->test(Show::class, ['campaign' => $campaign, 'number' => 1])
        ->set('recap', 'Mine now.')
        ->call('saveRecap')
        ->assertForbidden();
});

it('shows the published recap on the dashboard and the index', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    GameSession::factory()->for($campaign)->number(1)->published('They burned the bridge.')->create(['title' => 'The Ashfall Road']);

    $this->actingAs($player)
        ->get(route('campaigns.show', $campaign))
        ->assertOk()
        ->assertSeeInOrder(['Latest recap', 'The Ashfall Road', 'They burned the bridge.']);

    $this->actingAs($player)
        ->get(route('sessions.index', $campaign))
        ->assertOk()
        ->assertSeeInOrder(['The Ashfall Road', 'Recap']);
});
