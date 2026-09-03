<?php

use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Livewire\Entities\Form;
use App\Livewire\Maps\Viewer;
use App\Models\Campaign;
use App\Models\Entity;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Pan and zoom are CSS and pointer events, so no test in this file pinches anything.
 * What a test can hold is the shape: that the frame is wired to Alpine and not to
 * Livewire, that a map nobody may read never resolves, and that the render costs the
 * same whatever the map holds. The gestures are checked in a browser, and the plan
 * says so rather than pretending otherwise.
 */
beforeEach(fn () => Storage::fake('public'));

function mapWithAnImage(Campaign $campaign, string $name = 'The Duchy of Vell', string $visibility = 'players'): Entity
{
    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'maps'])
        ->set('name', $name)
        ->set('visibility', $visibility)
        ->set('image', UploadedFile::fake()->image('map.png', 2400, 1600))
        ->call('save')
        ->assertHasNoErrors();

    return Entity::query()->where('name', $name)->sole();
}

it('renders the image inside a frame that Alpine drives, not Livewire', function () {
    $campaign = Campaign::factory()->create();
    $map = mapWithAnImage($campaign);

    $html = Livewire::actingAs(ownerOf($campaign))
        ->test(Viewer::class, ['campaign' => $campaign, 'mapId' => $map->id])
        ->assertOk()
        ->html();

    // Panning and zooming never reach the server: the frame carries pointer handlers
    // and a transform, and no wire:model, wire:click or wire:poll at all.
    expect($html)->toContain('x-data="mapViewer(')
        ->and($html)->toContain('x-on:pointerdown')
        ->and($html)->toContain('touch-none')
        ->and($html)->toContain($map->imageUrl())
        ->and($html)->not->toContain('wire:model')
        ->and($html)->not->toContain('wire:poll');
});

it('gives the GM zoom controls and a readout', function () {
    $campaign = Campaign::factory()->create();
    $map = mapWithAnImage($campaign);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Viewer::class, ['campaign' => $campaign, 'mapId' => $map->id])
        ->assertSeeHtml('aria-label="Zoom in"')
        ->assertSeeHtml('aria-label="Zoom out"')
        ->assertSee('Fit');
});

it('says what is missing when a map has no picture yet', function () {
    $campaign = Campaign::factory()->create();

    $map = Entity::factory()->for($campaign)->type(EntityType::Map)->forPlayers()
        ->create(['name' => 'The Undercity', 'slug' => 'undercity']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Viewer::class, ['campaign' => $campaign, 'mapId' => $map->id])
        ->assertOk()
        ->assertSee('No image yet');
});

it('shows a player a shared map and 404s a GM-only one', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    $shared = mapWithAnImage($campaign, 'The Duchy of Vell');
    $secret = mapWithAnImage($campaign, 'The Undercity', 'dm');

    Livewire::actingAs($player)
        ->test(Viewer::class, ['campaign' => $campaign, 'mapId' => $shared->id])
        ->assertOk();

    Livewire::actingAs($player)
        ->test(Viewer::class, ['campaign' => $campaign, 'mapId' => $secret->id])
        ->assertNotFound();
});

it('404s a map from another campaign, and one that never existed', function () {
    $campaign = Campaign::factory()->create();
    $elsewhere = mapWithAnImage(Campaign::factory()->create(), 'Somebody else world');

    foreach ([$elsewhere->id, '01thisisnotamapatall00000000'] as $id) {
        Livewire::actingAs(ownerOf($campaign))
            ->test(Viewer::class, ['campaign' => $campaign, 'mapId' => $id])
            ->assertNotFound();
    }
});

it('renders the viewer on the entity page and nowhere else', function () {
    $campaign = Campaign::factory()->create();
    $owner = ownerOf($campaign);

    $map = mapWithAnImage($campaign);
    $place = Entity::factory()->for($campaign)->type(EntityType::Location)->forPlayers()
        ->create(['name' => 'Harrowgate', 'slug' => 'harrowgate']);

    $this->actingAs($owner)->get($map->url())->assertOk()->assertSee('x-data="mapViewer(', false);
    $this->actingAs($owner)->get($place->url())->assertOk()->assertDontSee('x-data="mapViewer(', false);
});

it('stops rendering the map to a member who was removed mid-session', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $map = mapWithAnImage($campaign);

    $viewer = Livewire::actingAs($player)
        ->test(Viewer::class, ['campaign' => $campaign, 'mapId' => $map->id])
        ->assertOk();

    $campaign->members()->where('user_id', $player->id)->delete();
    $campaign->forgetMemberCache();

    $viewer->call('$refresh')->assertNotFound();
});

it('costs the same number of queries whatever the map is', function () {
    $campaign = Campaign::factory()->create();
    $owner = ownerOf($campaign);
    $map = mapWithAnImage($campaign);

    $count = function () use ($owner, $campaign, $map): int {
        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::actingAs($owner)->test(Viewer::class, ['campaign' => $campaign, 'mapId' => $map->id]);

        $queries = count(DB::getQueryLog());

        DB::disableQueryLog();

        return $queries;
    };

    // One warm-up first: Campaign caches its member lookup per instance.
    $count();

    expect($count())->toBe($count());
});
