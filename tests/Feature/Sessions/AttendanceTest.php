<?php

use App\Actions\Campaigns\WriteCampaignMarkdown;
use App\Enums\CampaignRole;
use App\Enums\Rsvp;
use App\Livewire\Sessions\Attendance;
use App\Models\Campaign;
use App\Models\GameSession;
use App\Models\SessionRsvp;
use App\Models\User;
use Livewire\Livewire;

function playedSession(Campaign $campaign, int $number = 1): GameSession
{
    return GameSession::factory()->for($campaign)->number($number)->played()->create([
        'scheduled_at' => now()->subDays(2),
        'title' => 'The cellar',
    ]);
}

it('lets the GM tick who was there and untick one again', function () {
    $campaign = Campaign::factory()->create();
    $session = playedSession($campaign);
    $tobin = memberOf($campaign, CampaignRole::Player, User::factory()->create(['name' => 'Tobin Ash']));
    $mara = memberOf($campaign, CampaignRole::Player, User::factory()->create(['name' => 'Mara Vell']));

    $component = Livewire::actingAs(ownerOf($campaign))
        ->test(Attendance::class, ['campaign' => $campaign, 'session' => $session])
        ->call('recordAttendance', $tobin->id, true)
        ->call('recordAttendance', $mara->id, true)
        ->assertHasNoErrors();

    $attended = fn () => SessionRsvp::withoutGlobalScopes()
        ->where('game_session_id', $session->id)
        ->get()
        ->mapWithKeys(fn (SessionRsvp $row) => [$row->user_id => $row->attended])
        ->all();

    expect($attended())->toBe([$tobin->id => true, $mara->id => true]);

    $component->call('recordAttendance', $mara->id, false);

    expect($attended())->toBe([$tobin->id => true, $mara->id => false]);
});

it('prefills the box from a yes and not from a maybe', function () {
    $campaign = Campaign::factory()->create();
    $session = playedSession($campaign);
    $tobin = memberOf($campaign, CampaignRole::Player, User::factory()->create(['name' => 'Tobin Ash']));
    $mara = memberOf($campaign, CampaignRole::Player, User::factory()->create(['name' => 'Mara Vell']));

    SessionRsvp::factory()->forSession($session, $tobin)->saying(Rsvp::Yes)->create();
    SessionRsvp::factory()->forSession($session, $mara)->saying(Rsvp::Maybe)->create();

    // A ticked box offers to untick, an empty one offers to tick. The offer is the
    // marker, because "checked" can appear in a ULID and a class name cannot.
    Livewire::actingAs(ownerOf($campaign))
        ->test(Attendance::class, ['campaign' => $campaign, 'session' => $session])
        ->assertSeeHtml('wire:click="recordAttendance('.$tobin->id.', false)"')
        ->assertSeeHtml('wire:click="recordAttendance('.$mara->id.', true)"')
        ->assertSee('1 there');
});

it('refuses a player and gives them no box to tick', function () {
    $campaign = Campaign::factory()->create();
    $session = playedSession($campaign);
    $tobin = memberOf($campaign, CampaignRole::Player, User::factory()->create(['name' => 'Tobin Ash']));

    SessionRsvp::factory()->forSession($session, $tobin)->saying(Rsvp::Yes)->attended()->create();

    Livewire::actingAs($tobin)
        ->test(Attendance::class, ['campaign' => $campaign, 'session' => $session])
        ->assertSee('Tobin Ash')
        ->assertSee('There')
        ->assertDontSeeHtml('type="checkbox"')
        ->assertDontSeeHtml('recordAttendance')
        ->call('recordAttendance', $tobin->id, false)
        ->assertForbidden();
});

it('does not record attendance on a session that has not been played', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->planned()->create(['scheduled_at' => now()->addDay()]);
    $tobin = memberOf($campaign, CampaignRole::Player);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Attendance::class, ['campaign' => $campaign, 'session' => $session])
        ->assertDontSeeHtml('recordAttendance')
        ->call('recordAttendance', $tobin->id, true)
        ->assertForbidden();
});

it('writes who was there into the Markdown front matter', function () {
    $campaign = Campaign::factory()->create();
    $session = playedSession($campaign);
    $tobin = memberOf($campaign, CampaignRole::Player, User::factory()->create(['name' => 'Tobin Ash']));
    $mara = memberOf($campaign, CampaignRole::Player, User::factory()->create(['name' => 'Mara "Salt" Vell']));
    $ren = memberOf($campaign, CampaignRole::Player, User::factory()->create(['name' => 'Ren Holloway']));

    SessionRsvp::factory()->forSession($session, $tobin)->saying(Rsvp::Yes)->attended()->create();
    SessionRsvp::factory()->forSession($session, $mara)->saying(Rsvp::Maybe)->attended()->create();
    SessionRsvp::factory()->forSession($session, $ren)->saying(Rsvp::Yes)->attended(false)->create();

    $file = app(WriteCampaignMarkdown::class)->handle($campaign)['markdown/sessions/01-the-cellar.md'];

    expect($file)->toContain('attended: ["Mara \"Salt\" Vell", "Tobin Ash"]');
});

it('leaves the front matter alone when nobody was there', function () {
    $campaign = Campaign::factory()->create();
    $session = playedSession($campaign);
    $tobin = memberOf($campaign, CampaignRole::Player, User::factory()->create(['name' => 'Tobin Ash']));

    // A maybe that the GM never marked is not a record of anyone being there.
    SessionRsvp::factory()->forSession($session, $tobin)->saying(Rsvp::Maybe)->create();

    $file = app(WriteCampaignMarkdown::class)->handle($campaign)['markdown/sessions/01-the-cellar.md'];

    expect($file)->not->toContain('attended:');
});
