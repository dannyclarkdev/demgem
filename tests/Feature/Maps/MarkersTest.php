<?php

use App\Actions\Maps\Coordinate;
use App\Actions\Maps\PlaceMarker;
use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Livewire\Maps\Viewer;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\MapMarker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * Placing, naming, moving and removing a pin. What a player sees of one is
 * MarkerVisibilityTest, which is a leak test and lives on its own.
 */
beforeEach(fn () => Storage::fake('public'));

function aMap(Campaign $campaign, string $name = 'The Duchy of Vell'): Entity
{
    $map = Entity::factory()->for($campaign)->type(EntityType::Map)->forPlayers()
        ->create(['name' => $name, 'slug' => Str::slug($name)]);

    // Hold the upload in a variable while the media is added: PHP deletes the fake
    // file the moment the object is collected, and an inline chain collects it first.
    $file = UploadedFile::fake()->image('map.png', 2400, 1600);

    $map->addMedia($file->getRealPath())->usingFileName('map.png')->toMediaCollection('image');

    return $map;
}

it('drops a pin where the GM clicked and opens it to be named', function () {
    $campaign = Campaign::factory()->create();
    $map = aMap($campaign);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Viewer::class, ['campaign' => $campaign, 'mapId' => $map->id])
        ->call('placeMarker', 41.25, 68.9)
        ->assertHasNoErrors()
        ->assertSet('label', 'Unnamed');

    $pin = MapMarker::query()->sole();

    expect($pin->x)->toBe(41.25)
        ->and($pin->y)->toBe(68.9)
        ->and($pin->entity_id)->toBe($map->id)
        ->and($pin->campaign_id)->toBe($campaign->id)
        ->and($pin->target_entity_id)->toBeNull()
        // Everything the GM adds waits for the eye, exactly as a combatant does.
        ->and($pin->player_visible)->toBeFalse();
});

it('clamps a coordinate a browser made up', function (float $sent, float $stored) {
    expect(Coordinate::clamp($sent))->toBe($stored);
})->with([
    'inside' => [41.25, 41.25],
    'rounded to three places' => [41.2567, 41.257],
    'off the left' => [-30.0, 0.0],
    'off the right' => [4000.0, 100.0],
    'the far corner' => [100.0, 100.0],
]);

it('writes a clamped coordinate rather than refusing the click', function () {
    $campaign = Campaign::factory()->create();
    $map = aMap($campaign);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Viewer::class, ['campaign' => $campaign, 'mapId' => $map->id])
        ->call('placeMarker', -12.0, 900.0);

    $pin = MapMarker::query()->sole();

    expect($pin->x)->toBe(0.0)->and($pin->y)->toBe(100.0);
});

it('copies the name of whatever the pin points at', function () {
    $campaign = Campaign::factory()->create();
    $map = aMap($campaign);
    $place = Entity::factory()->for($campaign)->type(EntityType::Location)
        ->create(['name' => 'The Salt Cathedral']);

    $pin = app(PlaceMarker::class)->handle($map, 10, 10, null, $place);

    expect($pin->label)->toBe('The Salt Cathedral')
        ->and($pin->target_entity_id)->toBe($place->id);
});

it('keeps a label the GM typed over the name of the thing it points at', function () {
    $campaign = Campaign::factory()->create();
    $map = aMap($campaign);
    $place = Entity::factory()->for($campaign)->type(EntityType::Location)
        ->create(['name' => 'The Salt Cathedral']);

    $pin = app(PlaceMarker::class)->handle($map, 10, 10, 'The back door', $place);

    expect($pin->label)->toBe('The back door');
});

it('keeps the label and loses the link when the target is deleted', function () {
    $campaign = Campaign::factory()->create();
    $map = aMap($campaign);
    $place = Entity::factory()->for($campaign)->type(EntityType::Location)
        ->create(['name' => 'The Salt Cathedral']);

    $pin = app(PlaceMarker::class)->handle($map, 10, 10, null, $place);

    $place->forceDelete();

    expect($pin->fresh()->label)->toBe('The Salt Cathedral')
        ->and($pin->fresh()->target_entity_id)->toBeNull();
});

it('renames a pin and points it somewhere else', function () {
    $campaign = Campaign::factory()->create();
    $map = aMap($campaign);
    $place = Entity::factory()->for($campaign)->type(EntityType::Location)
        ->create(['name' => 'The Salt Cathedral']);

    $pin = MapMarker::factory()->onMap($map)->create(['label' => 'Unnamed']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Viewer::class, ['campaign' => $campaign, 'mapId' => $map->id])
        ->call('openMarker', $pin->id)
        ->assertSet('label', 'Unnamed')
        ->set('label', 'The Salt Cathedral')
        ->set('targetId', $place->id)
        ->call('saveMarker')
        ->assertHasNoErrors()
        ->assertSet('editing', null);

    expect($pin->fresh()->label)->toBe('The Salt Cathedral')
        ->and($pin->fresh()->target_entity_id)->toBe($place->id);
});

it('drags a pin to a new place', function () {
    $campaign = Campaign::factory()->create();
    $map = aMap($campaign);
    $pin = MapMarker::factory()->onMap($map)->at(10, 10)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Viewer::class, ['campaign' => $campaign, 'mapId' => $map->id])
        ->call('moveMarker', $pin->id, 77.5, 22.25);

    expect($pin->fresh()->x)->toBe(77.5)->and($pin->fresh()->y)->toBe(22.25);
});

it('takes a pin off the map and leaves the thing it pointed at alone', function () {
    $campaign = Campaign::factory()->create();
    $map = aMap($campaign);
    $place = Entity::factory()->for($campaign)->type(EntityType::Location)
        ->create(['name' => 'The Salt Cathedral']);
    $pin = MapMarker::factory()->onMap($map)->pointingAt($place)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Viewer::class, ['campaign' => $campaign, 'mapId' => $map->id])
        ->call('removeMarker', $pin->id);

    expect(MapMarker::query()->count())->toBe(0)
        ->and($place->fresh())->not->toBeNull();
});

it('takes every pin with the map when the map goes', function () {
    $campaign = Campaign::factory()->create();
    $map = aMap($campaign);
    MapMarker::factory()->count(3)->onMap($map)->create();

    $map->forceDelete();

    expect(MapMarker::query()->count())->toBe(0);
});

it('refuses a pin from anyone who cannot edit the map', function (CampaignRole $role) {
    $campaign = Campaign::factory()->create();
    $map = aMap($campaign);
    $pin = MapMarker::factory()->onMap($map)->at(10, 10)->create();

    $viewer = Livewire::actingAs(memberOf($campaign, $role))
        ->test(Viewer::class, ['campaign' => $campaign, 'mapId' => $map->id]);

    $viewer->call('placeMarker', 20, 20)->assertForbidden();

    expect(MapMarker::query()->count())->toBe(1)
        ->and($pin->fresh()->x)->toBe(10.0);
})->with([CampaignRole::Player, CampaignRole::Spectator]);

it('refuses a pin on a map in another campaign', function () {
    $campaign = Campaign::factory()->create();
    $map = aMap($campaign);
    $elsewhere = aMap(Campaign::factory()->create(), 'Somebody else world');
    $theirPin = MapMarker::factory()->onMap($elsewhere)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Viewer::class, ['campaign' => $campaign, 'mapId' => $map->id])
        ->call('moveMarker', $theirPin->id, 50, 50)
        ->assertNotFound();
});

it('gives a GM the pin controls and a player none of them', function () {
    $campaign = Campaign::factory()->create();
    $map = aMap($campaign);
    MapMarker::factory()->onMap($map)->shownToPlayers()->create(['label' => 'The Salt Cathedral']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Viewer::class, ['campaign' => $campaign, 'mapId' => $map->id])
        ->assertSee('Add a pin')
        ->assertSee('Reveal all')
        ->assertSee('The Salt Cathedral');

    Livewire::actingAs(memberOf($campaign, CampaignRole::Player))
        ->test(Viewer::class, ['campaign' => $campaign, 'mapId' => $map->id])
        ->assertDontSee('Add a pin')
        ->assertDontSee('Reveal all')
        ->assertSee('The Salt Cathedral');
});

it('opens a pin on a click, so a keyboard reaches it too', function () {
    $campaign = Campaign::factory()->create();
    $map = aMap($campaign);
    $pin = MapMarker::factory()->onMap($map)->create(['label' => 'The Salt Cathedral']);

    $html = Livewire::actingAs(ownerOf($campaign))
        ->test(Viewer::class, ['campaign' => $campaign, 'mapId' => $map->id])
        ->html();

    // Dragging is pointer events, but opening is a click. A pin is a button, and a
    // keyboard user presses Enter on it: a pointer-only handler leaves them no way
    // in, which is how this was found in the browser.
    expect($html)->toContain('x-on:click="onPinClick(')
        ->and($html)->toContain('x-on:pointerdown="startPinDrag(');
});

it('carries the pins into the export', function () {
    $campaign = Campaign::factory()->create();
    $owner = ownerOf($campaign);
    $map = aMap($campaign);
    MapMarker::factory()->onMap($map)->at(41.25, 68.9)->shownToPlayers()
        ->create(['label' => 'The Salt Cathedral']);

    $export = $this->actingAs($owner)->get(route('campaigns.export', $campaign))->assertOk();
    $payload = json_decode($export->streamedContent(), true, 512, JSON_THROW_ON_ERROR);

    $exported = collect($payload['entities'])->firstWhere('name', 'The Duchy of Vell');

    expect($exported['markers'])->toHaveCount(1)
        ->and($exported['markers'][0]['label'])->toBe('The Salt Cathedral')
        ->and($exported['markers'][0]['x'])->toBe(41.25)
        ->and($exported['markers'][0]['player_visible'])->toBeTrue();
});
