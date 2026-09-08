<?php

use App\Actions\Sessions\PublishRecap;
use App\Actions\Sessions\SendSessionReminders;
use App\Discord\DiscordWebhook;
use App\Enums\CampaignRole;
use App\Jobs\PostToDiscord;
use App\Livewire\Campaigns\Settings;
use App\Livewire\Sessions\Show;
use App\Models\Campaign;
use App\Models\GameSession;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Sleep;
use Livewire\Livewire;

const DISCORD_WEBHOOK = 'https://discord.com/api/webhooks/123456789012345678/AbC-dEf_123xyz';

// Http::fake() is called per test rather than here: fakes merge, and the first stub
// that answers wins, so a catch-all 200 registered up front would swallow the 503 and
// 404 the failure tests register later.
beforeEach(function () {
    Carbon::setTestNow('2026-09-07 12:00:00');
    Http::preventStrayRequests();
    Mail::fake();
});

function discordCampaign(?string $url = DISCORD_WEBHOOK): Campaign
{
    return Campaign::factory()->create([
        'name' => 'The Drowned Duchy',
        'timezone' => 'Europe/London',
        'reminder_lead_hours' => 24,
        'discord_webhook_url' => $url,
    ]);
}

function discordSession(Campaign $campaign): GameSession
{
    return GameSession::factory()->for($campaign)->number(12)->planned()->withPrep()->create([
        'title' => 'The Salt Cathedral',
        'scheduled_at' => now()->addHours(20),
        'live_notes' => 'Mara lied about the key.',
        'recap' => 'They burned the bridge behind them.',
    ]);
}

/**
 * @return list<string>
 */
function discordPosts(): array
{
    $posts = [];

    Http::assertSent(function (Request $request) use (&$posts) {
        $posts[] = $request->url().' '.$request['content'];

        return true;
    });

    return $posts;
}

it('accepts a Discord webhook URL and nothing else', function (string $url, bool $accepted) {
    expect(DiscordWebhook::isValid($url))->toBe($accepted);
})->with([
    'discord.com' => ['https://discord.com/api/webhooks/123456789/abc-DEF_9', true],
    'discordapp.com' => ['https://discordapp.com/api/webhooks/123456789/abc', true],
    'with whitespace around it' => ['  https://discord.com/api/webhooks/1/a  ', true],
    'plain http' => ['http://discord.com/api/webhooks/123/abc', false],
    'a query string' => ['https://discord.com/api/webhooks/123/abc?wait=true', false],
    'a lookalike host' => ['https://discord.com.evil.example/api/webhooks/123/abc', false],
    'discord.com as the user part' => ['https://discord.com@169.254.169.254/api/webhooks/123/abc', false],
    'the cloud metadata address' => ['http://169.254.169.254/latest/meta-data/', false],
    'a private address' => ['https://10.0.0.1/api/webhooks/123/abc', false],
    'another Discord path' => ['https://discord.com/api/channels/123/messages', false],
    'a port' => ['https://discord.com:8443/api/webhooks/123/abc', false],
    'not a url' => ['a webhook', false],
    'empty' => ['', false],
]);

it('saves the URL from settings, encrypted, refuses another host, and clears on empty', function () {
    $campaign = Campaign::factory()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Settings::class, ['campaign' => $campaign])
        ->set('discordWebhookUrl', DISCORD_WEBHOOK)
        ->call('saveDiscord')
        ->assertHasNoErrors()
        ->assertRedirect(route('campaigns.settings', $campaign));

    $stored = DB::table('campaigns')->where('id', $campaign->id)->value('discord_webhook_url');

    expect($campaign->fresh()->discord_webhook_url)->toBe(DISCORD_WEBHOOK)
        ->and($stored)->not->toContain('discord.com');

    Livewire::actingAs(ownerOf($campaign))
        ->test(Settings::class, ['campaign' => $campaign])
        ->set('discordWebhookUrl', 'http://169.254.169.254/latest/meta-data/')
        ->call('saveDiscord')
        ->assertHasErrors('discordWebhookUrl');

    expect($campaign->fresh()->discord_webhook_url)->toBe(DISCORD_WEBHOOK);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Settings::class, ['campaign' => $campaign])
        ->set('discordWebhookUrl', '')
        ->call('saveDiscord')
        ->assertHasNoErrors();

    expect($campaign->fresh()->discord_webhook_url)->toBeNull();
});

it('keeps the URL out of the export', function () {
    $campaign = discordCampaign();

    $document = json_encode(exportedArray($campaign), JSON_THROW_ON_ERROR);

    expect($document)->not->toContain('webhooks')
        ->and($document)->not->toContain('discord');
});

it('sends a test message from settings, and asks for a URL first when there is none', function () {
    Http::fake();
    $campaign = discordCampaign();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Settings::class, ['campaign' => $campaign])
        ->assertSee('Send a test message')
        ->call('sendDiscordTest')
        ->assertHasNoErrors();

    $posts = discordPosts();

    expect($posts)->toHaveCount(1)
        ->and($posts[0])->toStartWith(DISCORD_WEBHOOK.' ')
        ->and($posts[0])->toContain('**The Drowned Duchy** is connected to this channel.');

    $quiet = discordCampaign(null);

    Livewire::actingAs(ownerOf($quiet))
        ->test(Settings::class, ['campaign' => $quiet])
        ->assertDontSee('Send a test message')
        ->call('sendDiscordTest')
        ->assertHasErrors('discordWebhookUrl');

    Http::assertSentCount(1);
});

it('posts one line when a recap is published, with no prose in it, and not again', function () {
    Http::fake();
    $campaign = discordCampaign();
    $session = discordSession($campaign);

    app(PublishRecap::class)->handle($session, ownerOf($campaign));

    $posts = discordPosts();

    expect($posts)->toHaveCount(1)
        ->and($posts[0])->toContain('**The Drowned Duchy** · Session 12: The Salt Cathedral · The recap is up: '.$session->url());

    foreach (['burned the bridge', 'nobody opened it', 'Mara lied', 'off screen'] as $prose) {
        expect($posts[0])->not->toContain($prose);
    }

    app(PublishRecap::class)->handle($session, ownerOf($campaign), 'They burned the bridge, and then the ferry.');

    Http::assertSentCount(1);

    expect($session->fresh()->recap)->toBe('They burned the bridge, and then the ferry.');
});

it('posts from the page too, and posts nothing for a campaign without a channel', function () {
    Http::fake();
    $connected = discordCampaign();
    discordSession($connected);

    Livewire::actingAs(ownerOf($connected))
        ->test(Show::class, ['campaign' => $connected, 'number' => 12])
        ->set('recap', 'They burned the bridge behind them.')
        ->call('publishRecap')
        ->assertHasNoErrors();

    Http::assertSentCount(1);

    $quiet = discordCampaign(null);
    $session = discordSession($quiet);

    app(PublishRecap::class)->handle($session, ownerOf($quiet));

    Http::assertSentCount(1);
});

it('posts the reminder to the channel beside the mail, with the time in the campaign zone', function () {
    Http::fake();
    $campaign = discordCampaign();
    $session = discordSession($campaign);
    memberOf($campaign, CampaignRole::Player);

    expect(app(SendSessionReminders::class)->handle())->toBe(2);

    $posts = discordPosts();

    expect($posts)->toHaveCount(1)
        ->and($posts[0])->toContain("**The Drowned Duchy** · Session 12: The Salt Cathedral · Tue 8 Sep 2026 at 09:00 BST · Say whether you're coming: ".$session->url())
        ->and($posts[0])->not->toContain('nobody opened it');

    $quiet = discordCampaign(null);
    discordSession($quiet);

    app(SendSessionReminders::class)->handle();

    Http::assertSentCount(1);
});

it('tries three times through an outage, logs, and still stamps the reminder', function () {
    Http::fake(['*' => Http::response('discord is down', 503)]);
    Sleep::fake();
    Log::spy();

    $campaign = discordCampaign();
    $session = discordSession($campaign);

    app(SendSessionReminders::class)->handle();

    Http::assertSentCount(3);
    Log::shouldHaveReceived('warning')->once();

    expect($session->fresh()->reminder_sent_at)->not->toBeNull();
});

it('asks once when Discord says the webhook is gone, and logs that', function () {
    Http::fake(['*' => Http::response('no such webhook', 404)]);
    Sleep::fake();
    Log::spy();

    $campaign = discordCampaign();

    dispatch(PostToDiscord::test($campaign));

    Http::assertSentCount(1);
    Log::shouldHaveReceived('warning')->once();
});

it('posts nothing when the URL was removed before the job ran', function () {
    Http::fake();
    $campaign = discordCampaign();
    $job = PostToDiscord::test($campaign);

    $campaign->update(['discord_webhook_url' => null]);

    $job->handle();

    Http::assertNothingSent();
});
