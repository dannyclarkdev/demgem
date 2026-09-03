<?php

use App\Actions\Maps\MoveMarker;
use App\Actions\Maps\PlaceMarker;
use App\Actions\Maps\RemoveMarker;
use App\Actions\Maps\SetMarkerVisibility;
use App\Actions\Maps\UpdateMarker;
use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Events\MapChanged;
use App\Livewire\Maps\Viewer;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\MapMarker;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * The map joins the channel slice 5 built. No new channel, no new callback, and the
 * same promise: the wire carries ids, and every screen decides for itself what those
 * ids mean under its own viewer's role.
 */
beforeEach(fn () => Storage::fake('public'));

function aBroadcastMap(Campaign $campaign): Entity
{
    $map = Entity::factory()->for($campaign)->type(EntityType::Map)->forPlayers()
        ->create(['name' => 'The Duchy of Vell', 'slug' => 'vell']);

    $file = UploadedFile::fake()->image('map.png', 1200, 800);
    $map->addMedia($file->getRealPath())->usingFileName('map.png')->toMediaCollection('image');

    return $map;
}

it('says where it broadcasts and what it is called', function () {
    $event = new MapChanged('01campaign', '01map');

    expect($event)->toBeInstanceOf(ShouldBroadcast::class)
        ->and($event)->toBeInstanceOf(ShouldRescue::class)
        ->and($event->broadcastAs())->toBe('map.changed');

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(PresenceChannel::class)
        ->and($channels[0]->name)->toBe('presence-campaign.01campaign');
});

it('carries two ids and nothing else', function () {
    $campaign = Campaign::factory()->create();
    $map = aBroadcastMap($campaign);

    MapMarker::factory()->onMap($map)->at(41.25, 68.9)->create(['label' => 'The smugglers stair']);

    $payload = json_encode((new MapChanged($campaign->id, $map->id))->broadcastWith(), JSON_THROW_ON_ERROR);

    // Revealing a pin and hiding it send exactly the same two ids, which is why
    // there is nothing here to filter.
    expect(json_decode($payload, true))->toBe(['mapId' => $map->id])
        ->and($payload)->not->toContain('The smugglers stair')
        ->and($payload)->not->toContain('The Duchy of Vell');
});

it('broadcasts once for every change a GM makes', function (string $action) {
    $campaign = Campaign::factory()->create();
    $map = aBroadcastMap($campaign);
    $pin = MapMarker::factory()->onMap($map)->at(10, 10)->create();
    $place = Entity::factory()->for($campaign)->create(['name' => 'The Salt Cathedral']);

    Event::fake([MapChanged::class]);

    match ($action) {
        'place' => app(PlaceMarker::class)->handle($map, 20, 20),
        'move' => app(MoveMarker::class)->handle($pin, 30, 30),
        'rename' => app(UpdateMarker::class)->handle($pin, 'The back door', $place),
        'reveal' => app(SetMarkerVisibility::class)->toggle($pin),
        'reveal all' => app(SetMarkerVisibility::class)->setAll($map, true),
        'remove' => app(RemoveMarker::class)->handle($pin),
    };

    Event::assertDispatchedTimes(MapChanged::class, 1);
})->with(['place', 'move', 'rename', 'reveal', 'reveal all', 'remove']);

it('stays quiet when nothing changed', function () {
    $campaign = Campaign::factory()->create();
    $map = aBroadcastMap($campaign);
    $pin = MapMarker::factory()->onMap($map)->create();

    Event::fake([MapChanged::class]);

    // Already hidden, and every pin already hidden.
    app(SetMarkerVisibility::class)->handle($pin, false);
    app(SetMarkerVisibility::class)->setAll($map, false);

    Event::assertNotDispatched(MapChanged::class);
});

it('names the campaign it belongs to, so another table never hears it', function () {
    $campaign = Campaign::factory()->create();
    $map = aBroadcastMap($campaign);

    Event::fake([MapChanged::class]);

    app(PlaceMarker::class)->handle($map, 20, 20);

    Event::assertDispatched(MapChanged::class, fn (MapChanged $event) => $event->campaignId === $campaign->id
        && $event->mapId === $map->id);
});

it('takes the broadcast and re-renders, and ignores another map', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $map = aBroadcastMap($campaign);
    $elsewhere = Entity::factory()->for($campaign)->type(EntityType::Map)->forPlayers()
        ->create(['name' => 'The world', 'slug' => 'world']);

    $pin = MapMarker::factory()->onMap($map)->create(['label' => 'The smugglers stair']);

    $viewer = Livewire::actingAs($player)
        ->test(Viewer::class, ['campaign' => $campaign, 'mapId' => $map->id])
        ->assertDontSee('The smugglers stair');

    app(SetMarkerVisibility::class)->handle($pin, true);

    $viewer->call('mapChanged', ['mapId' => $elsewhere->id])->assertOk();
    $viewer->call('mapChanged', ['mapId' => $map->id])->assertSee('The smugglers stair');
});
