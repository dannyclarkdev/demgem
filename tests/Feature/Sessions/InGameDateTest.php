<?php

use App\Actions\Campaigns\ImportCampaign;
use App\Actions\Campaigns\ReadCampaignFile;
use App\Actions\Campaigns\WriteCampaignMarkdown;
use App\Livewire\Sessions\Form;
use App\Livewire\Sessions\Show;
use App\Livewire\Sessions\Story;
use App\Models\Calendar;
use App\Models\Campaign;
use App\Models\GameSession;
use App\Models\User;
use Livewire\Livewire;

/**
 * The days the party spent in the world, on a session.
 */
it('sets an in-game range on a session and prints it', function () {
    $campaign = Campaign::factory()->create();
    Calendar::factory()->inCampaign($campaign)->create();
    $session = GameSession::factory()->create(['campaign_id' => $campaign->id, 'number' => 2, 'title' => 'Under The Pilings']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'number' => 2])
        ->assertSee('In the world')
        ->set('inGameStart', ['year' => 1042, 'month' => 2, 'day' => 1])
        ->set('inGameEnd', ['year' => 1042, 'month' => 2, 'day' => 3])
        ->call('save')
        ->assertHasNoErrors();

    $session->refresh();

    expect($session->in_game_start?->toArray())->toBe(['year' => 1042, 'month' => 2, 'day' => 1])
        ->and($session->in_game_end?->toArray())->toBe(['year' => 1042, 'month' => 2, 'day' => 3]);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Show::class, ['campaign' => $campaign, 'number' => 2])
        ->assertSee('1 Harvest 1042 AR to 3 Harvest 1042 AR');

    $session->update(['recap' => 'The pilings gave way.', 'recap_published_at' => now()]);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Story::class, ['campaign' => $campaign])
        ->assertSee('1 Harvest 1042 AR to 3 Harvest 1042 AR');
});

it('prints a single day when the session ends the day it starts', function () {
    $campaign = Campaign::factory()->create();
    Calendar::factory()->inCampaign($campaign)->create();
    GameSession::factory()->create([
        'campaign_id' => $campaign->id,
        'number' => 1,
        'in_game_start_year' => 1042,
        'in_game_start_month' => 2,
        'in_game_start_day' => 5,
    ]);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Show::class, ['campaign' => $campaign, 'number' => 1])
        ->assertSee('Sunday, 5 Harvest 1042 AR');
});

it('offers no in-game fields without a calendar', function () {
    $campaign = Campaign::factory()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign])
        ->assertDontSee('In the world');
});

it('refuses an end before the start, a partial date, and a day the month lacks', function () {
    $campaign = Campaign::factory()->create();
    Calendar::factory()->inCampaign($campaign)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign])
        ->set('inGameStart', ['year' => 1042, 'month' => 2, 'day' => 5])
        ->set('inGameEnd', ['year' => 1042, 'month' => 2, 'day' => 4])
        ->call('save')
        ->assertHasErrors(['inGameEnd.day']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign])
        ->set('inGameStart', ['year' => 1042, 'month' => '', 'day' => 5])
        ->call('save')
        ->assertHasErrors(['inGameStart.month']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign])
        ->set('inGameStart', ['year' => 1042, 'month' => 1, 'day' => 11])
        ->call('save')
        ->assertHasErrors(['inGameStart.day']);

    expect(GameSession::query()->count())->toBe(0);
});

it('clears the range when every part is blank', function () {
    $campaign = Campaign::factory()->create();
    Calendar::factory()->inCampaign($campaign)->create();
    $session = GameSession::factory()->create([
        'campaign_id' => $campaign->id,
        'number' => 1,
        'in_game_start_year' => 1042,
        'in_game_start_month' => 2,
        'in_game_start_day' => 5,
    ]);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'number' => 1])
        ->assertSet('inGameStart.day', 5)
        ->set('inGameStart', ['year' => '', 'month' => '', 'day' => ''])
        ->call('save')
        ->assertHasNoErrors();

    expect($session->refresh()->in_game_start)->toBeNull();
});

it('carries the range through the round trip and into the front matter', function () {
    $campaign = Campaign::factory()->create();
    Calendar::factory()->inCampaign($campaign)->create();
    GameSession::factory()->create([
        'campaign_id' => $campaign->id,
        'number' => 1,
        'title' => 'Under the pilings',
        'in_game_start_year' => 1042,
        'in_game_start_month' => 2,
        'in_game_start_day' => 1,
        'in_game_end_year' => 1042,
        'in_game_end_month' => 2,
        'in_game_end_day' => 3,
    ]);

    $document = exportedArray($campaign);

    expect($document['sessions'][0]['in_game_start'])->toBe(['year' => 1042, 'month' => 2, 'day' => 1])
        ->and($document['sessions'][0]['in_game_end'])->toBe(['year' => 1042, 'month' => 2, 'day' => 3]);

    $read = app(ReadCampaignFile::class)->handle(json_encode($document, JSON_THROW_ON_ERROR));
    $copy = app(ImportCampaign::class)->handle($read->document, User::factory()->create());
    $restored = $copy->gameSessions()->sole();

    expect($restored->in_game_start?->toArray())->toBe(['year' => 1042, 'month' => 2, 'day' => 1])
        ->and($restored->in_game_end?->toArray())->toBe(['year' => 1042, 'month' => 2, 'day' => 3]);

    $files = app(WriteCampaignMarkdown::class)->handle($campaign);

    expect($files['markdown/sessions/01-under-the-pilings.md'])
        ->toContain('in_game_start: "Sunday, 1 Harvest 1042 AR"')
        ->toContain('in_game_end: "Sunday, 3 Harvest 1042 AR"');
});

it('drops an imported date the bounds refuse', function () {
    $campaign = Campaign::factory()->create();
    GameSession::factory()->create(['campaign_id' => $campaign->id, 'number' => 1]);

    $document = exportedArray($campaign);
    $document['sessions'][0]['in_game_start'] = ['year' => 0, 'month' => 1, 'day' => 1];
    $document['sessions'][0]['in_game_end'] = 'tomorrow';

    $read = app(ReadCampaignFile::class)->handle(json_encode($document, JSON_THROW_ON_ERROR));
    $copy = app(ImportCampaign::class)->handle($read->document, User::factory()->create());

    expect($copy->gameSessions()->sole()->in_game_start)->toBeNull()
        ->and($read->report->truncated)->toBe(2);
});
