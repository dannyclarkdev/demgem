<?php

use App\Actions\Campaigns\ImportCampaign;
use App\Actions\Campaigns\ReadCampaignFile;
use App\Enums\CampaignRole;
use App\Enums\Rsvp;
use App\Enums\Visibility;
use App\Livewire\Sessions\Attendance;
use App\Models\Campaign;
use App\Models\GameSession;
use App\Models\SessionRsvp;
use App\Models\User;
use Livewire\Livewire;

function datedSession(Campaign $campaign, int $number = 1): GameSession
{
    return GameSession::factory()->for($campaign)->number($number)->planned()->create([
        'scheduled_at' => now()->addDays(3),
    ]);
}

it('lets a member say yes, change to maybe, and clear the answer', function () {
    $campaign = Campaign::factory()->create();
    $session = datedSession($campaign);
    $player = memberOf($campaign, CampaignRole::Player, User::factory()->create(['name' => 'Tobin Ash']));

    $component = Livewire::actingAs($player)
        ->test(Attendance::class, ['campaign' => $campaign, 'session' => $session])
        ->call('respond', 'yes')
        ->assertHasNoErrors();

    expect(SessionRsvp::withoutGlobalScopes()->where('game_session_id', $session->id)->where('user_id', $player->id)->value('rsvp'))->toBe(Rsvp::Yes);

    $component->call('respond', 'maybe');

    expect(SessionRsvp::withoutGlobalScopes()->where('game_session_id', $session->id)->where('user_id', $player->id)->value('rsvp'))->toBe(Rsvp::Maybe);

    $component->call('respond', null);

    expect(SessionRsvp::withoutGlobalScopes()->where('game_session_id', $session->id)->count())->toBe(0);
});

it('shows the GM a headcount', function () {
    $campaign = Campaign::factory()->create();
    $session = datedSession($campaign);
    $tobin = memberOf($campaign, CampaignRole::Player, User::factory()->create(['name' => 'Tobin Ash']));
    memberOf($campaign, CampaignRole::Player, User::factory()->create(['name' => 'Mara Vell']));

    Livewire::actingAs($tobin)
        ->test(Attendance::class, ['campaign' => $campaign, 'session' => $session])
        ->call('respond', 'maybe');

    Livewire::actingAs(ownerOf($campaign))
        ->test(Attendance::class, ['campaign' => $campaign, 'session' => $session])
        ->assertSee('1 maybe')
        ->assertSee('2 not answered')
        ->assertSee('Tobin Ash')
        ->assertSee('Mara Vell');
});

it('keeps two members answers apart', function () {
    $campaign = Campaign::factory()->create();
    $session = datedSession($campaign);
    $tobin = memberOf($campaign, CampaignRole::Player, User::factory()->create(['name' => 'Tobin Ash']));
    $mara = memberOf($campaign, CampaignRole::Player, User::factory()->create(['name' => 'Mara Vell']));

    Livewire::actingAs($tobin)
        ->test(Attendance::class, ['campaign' => $campaign, 'session' => $session])
        ->call('respond', 'yes');

    Livewire::actingAs($mara)
        ->test(Attendance::class, ['campaign' => $campaign, 'session' => $session])
        ->call('respond', 'no');

    $answers = SessionRsvp::withoutGlobalScopes()
        ->where('game_session_id', $session->id)
        ->get()
        ->mapWithKeys(fn (SessionRsvp $row) => [$row->user_id => $row->rsvp?->value])
        ->all();

    expect($answers)->toBe([$tobin->id => 'yes', $mara->id => 'no']);
});

it('refuses a player on a GM-only session and never lists them', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->planned()->create([
        'scheduled_at' => now()->addDays(3),
        'visibility' => Visibility::Dm,
    ]);
    $player = memberOf($campaign, CampaignRole::Player, User::factory()->create(['name' => 'Tobin Ash']));
    memberOf($campaign, CampaignRole::CoGm, User::factory()->create(['name' => 'Ren Holloway']));

    Livewire::actingAs($player)
        ->test(Attendance::class, ['campaign' => $campaign, 'session' => $session])
        ->assertForbidden();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Attendance::class, ['campaign' => $campaign, 'session' => $session])
        ->assertSee('Ren Holloway')
        ->assertDontSee('Tobin Ash');
});

it('shows answers on a cancelled session and takes no new ones', function () {
    $campaign = Campaign::factory()->create();
    $session = datedSession($campaign);
    $tobin = memberOf($campaign, CampaignRole::Player, User::factory()->create(['name' => 'Tobin Ash']));

    Livewire::actingAs($tobin)
        ->test(Attendance::class, ['campaign' => $campaign, 'session' => $session])
        ->call('respond', 'yes');

    $session->update(['status' => 'cancelled']);

    Livewire::actingAs($tobin)
        ->test(Attendance::class, ['campaign' => $campaign, 'session' => $session->fresh()])
        ->assertSee('Tobin Ash')
        ->assertSee('Yes')
        ->call('respond', 'no')
        ->assertForbidden();
});

it('exports the answers by name and the importer leaves them behind', function () {
    $campaign = Campaign::factory()->create();
    $session = datedSession($campaign);
    $tobin = memberOf($campaign, CampaignRole::Player, User::factory()->create(['name' => 'Tobin Ash']));

    Livewire::actingAs($tobin)
        ->test(Attendance::class, ['campaign' => $campaign, 'session' => $session])
        ->call('respond', 'yes');

    $exported = exportedArray($campaign);

    expect($exported['sessions'][0]['attendance'])->toBe([
        ['name' => 'Tobin Ash', 'rsvp' => 'yes', 'attended' => null],
    ]);

    $result = app(ReadCampaignFile::class)->handle(json_encode($exported, JSON_THROW_ON_ERROR));

    expect($result->errors)->toBe([])
        ->and(collect($result->report->losses())->pluck('label')->all())
        ->toContain('1 answer about dates and attendance will be left behind');

    $copy = app(ImportCampaign::class)->handle($result->document, User::factory()->create());

    expect(SessionRsvp::withoutGlobalScopes()->where('campaign_id', $copy->id)->count())->toBe(0);
});

it('shows the headcount and your own answer on the index and the dashboard', function () {
    $campaign = Campaign::factory()->create();
    $session = datedSession($campaign);
    $tobin = memberOf($campaign, CampaignRole::Player, User::factory()->create(['name' => 'Tobin Ash']));
    $mara = memberOf($campaign, CampaignRole::Player, User::factory()->create(['name' => 'Mara Vell']));

    SessionRsvp::factory()->forSession($session, $tobin)->saying(Rsvp::Yes)->create();
    SessionRsvp::factory()->forSession($session, $mara)->saying(Rsvp::Maybe)->create();

    $this->actingAs($tobin)
        ->get(route('sessions.index', $campaign))
        ->assertOk()
        ->assertSee('1 yes · 1 maybe')
        ->assertSee('You said yes');

    $this->actingAs($mara)
        ->get(route('campaigns.show', $campaign))
        ->assertOk()
        ->assertSee('1 yes · 1 maybe')
        ->assertSee('You said maybe');
});
