<?php

use App\Actions\Clocks\CreateClock;
use App\Actions\Clocks\DeleteClock;
use App\Actions\Clocks\ReorderClocks;
use App\Actions\Clocks\SetClockVisibility;
use App\Actions\Clocks\TickClock;
use App\Actions\Clocks\UpdateClock;
use App\Enums\CampaignRole;
use App\Events\ClockChanged;
use App\Livewire\Clocks\Panel;
use App\Livewire\Table\Show;
use App\Models\Campaign;
use App\Models\Clock;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

/**
 * Clocks join the channel slice 5 built. No new channel, no new callback, and the
 * same promise: the wire carries ids, and every screen decides for itself what those
 * ids mean under its own viewer's role.
 */
it('says where it broadcasts and what it is called', function () {
    $event = new ClockChanged('01campaign', '01clock');

    expect($event)->toBeInstanceOf(ShouldBroadcast::class)
        ->and($event)->toBeInstanceOf(ShouldRescue::class)
        ->and($event->broadcastAs())->toBe('clock.changed');

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(PresenceChannel::class)
        ->and($channels[0]->name)->toBe('presence-campaign.01campaign');
});

it('puts two ids on the wire and nothing else', function () {
    $event = new ClockChanged('01campaign', '01clock');

    expect($event->broadcastWith())->toBe(['clockId' => '01clock']);
});

it('dispatches once for every write a GM can make', function (string $write) {
    Event::fake([ClockChanged::class]);

    $campaign = Campaign::factory()->create();
    $clock = Clock::factory()->inCampaign($campaign)->sized(6)->filled(2)->create(['name' => 'The ritual']);

    match ($write) {
        'create' => app(CreateClock::class)->handle($campaign, 'The storm', 8),
        'tick' => app(TickClock::class)->by($clock, 1),
        'set' => app(TickClock::class)->to($clock, 5),
        'rename' => app(UpdateClock::class)->handle($clock, 'The ritual, sooner', 6),
        'reveal' => app(SetClockVisibility::class)->handle($clock, true),
        'reorder' => app(ReorderClocks::class)->handle($campaign, $clock->id, 0),
        'delete' => app(DeleteClock::class)->handle($clock),
    };

    Event::assertDispatchedTimes(ClockChanged::class, 1);
})->with(['create', 'tick', 'set', 'rename', 'reveal', 'reorder', 'delete']);

it('says exactly the same thing whether a clock is revealed or hidden', function () {
    $campaign = Campaign::factory()->create();
    $clock = Clock::factory()->inCampaign($campaign)->create();

    $payloads = [];

    Event::listen(ClockChanged::class, function (ClockChanged $event) use (&$payloads) {
        $payloads[] = [$event->campaignId, $event->clockId, $event->broadcastWith()];
    });

    $visibility = app(SetClockVisibility::class);

    $visibility->handle($clock, true);
    $visibility->handle($clock, false);

    // Two opposite decisions, one indistinguishable message. There is no payload to
    // filter, so there is none to leak: a player whose screen re-renders after a
    // change they may not see finds nothing new.
    expect($payloads)->toHaveCount(2)
        ->and($payloads[0])->toBe($payloads[1]);
});

it('stays quiet when nothing actually moved', function () {
    Event::fake([ClockChanged::class]);

    $campaign = Campaign::factory()->create();
    $clock = Clock::factory()->inCampaign($campaign)->shownToPlayers()->create();

    app(SetClockVisibility::class)->handle($clock, true);

    Event::assertNotDispatched(ClockChanged::class);
});

it('listens on the panel and on the party table screen', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    Clock::factory()->inCampaign($campaign)->shownToPlayers()->create(['name' => 'The drowning tide']);

    Livewire::actingAs($player)
        ->test(Panel::class, ['campaign' => $campaign])
        ->dispatch('echo-presence:campaign.'.$campaign->id.',.clock.changed')
        ->assertOk()
        ->assertSee('The drowning tide');

    Livewire::actingAs($player)
        ->test(Show::class, ['campaign' => $campaign])
        ->dispatch('echo-presence:campaign.'.$campaign->id.',.clock.changed')
        ->assertOk()
        ->assertSee('What is coming');
});

it('re-renders under the listener own viewer, not the sender', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $hidden = Clock::factory()->inCampaign($campaign)->create(['name' => 'The smugglers stair']);

    // The GM ticks a clock the party cannot see. The party's panel hears the same
    // event the GM's does and finds nothing new, because the re-render runs under the
    // player's own role rather than the GM's.
    app(TickClock::class)->by($hidden, 1);

    Livewire::actingAs($player)
        ->test(Panel::class, ['campaign' => $campaign])
        ->dispatch('echo-presence:campaign.'.$campaign->id.',.clock.changed')
        ->assertDontSee('The smugglers stair');
});
