<?php

use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Livewire\Entities\Form;
use App\Models\Campaign;
use App\Models\Entity;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * A map is an entity, so almost nothing here is new code. That is the point of the
 * decision, and this file is what proves it: visibility, wiki links, search, and the
 * export all work on a map because they work on every entity, and none of them
 * learned the word "map" to do it.
 */
beforeEach(fn () => Storage::fake('public'));

it('lets a GM make a map and put a picture on it', function () {
    $campaign = Campaign::factory()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'maps'])
        ->set('name', 'The Duchy of Vell')
        ->set('image', UploadedFile::fake()->image('vell.png', 2400, 1600))
        ->call('save')
        ->assertHasNoErrors();

    $map = $campaign->entities()->firstOrFail();

    expect($map->type)->toBe(EntityType::Map)
        ->and($map->isMap())->toBeTrue()
        ->and($map->imageUrl())->not->toBeNull();
});

it('lets a GM make the row tonight and find the file tomorrow', function () {
    $campaign = Campaign::factory()->create();
    $owner = ownerOf($campaign);

    Livewire::actingAs($owner)
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'maps'])
        ->set('name', 'The Undercity')
        ->call('save')
        ->assertHasNoErrors();

    $map = $campaign->entities()->firstOrFail();

    // The page says what is missing rather than refusing to exist.
    $this->actingAs($owner)->get($map->url())
        ->assertOk()
        ->assertSee('No image yet')
        ->assertSee('Upload the map');
});

it('takes a map twice the size of a portrait, and no more', function () {
    $campaign = Campaign::factory()->create();

    // 8 MB: too big for a character, right for a hand-drawn scan.
    $big = UploadedFile::fake()->create('vell.png', 8192, 'image/png');

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'maps'])
        ->set('name', 'The Duchy of Vell')
        ->set('image', $big)
        ->call('save')
        ->assertHasNoErrors();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'characters'])
        ->set('name', 'Mara Voss')
        ->set('image', UploadedFile::fake()->create('mara.png', 8192, 'image/png'))
        ->call('save')
        ->assertHasErrors('image');
});

it('refuses a map bigger than the cap', function () {
    $campaign = Campaign::factory()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'maps'])
        ->set('name', 'The Duchy of Vell')
        ->set('image', UploadedFile::fake()->create('vell.png', Form::MAP_IMAGE_KB + 1, 'image/png'))
        ->call('save')
        ->assertHasErrors('image');

    expect(Entity::query()->count())->toBe(0);
});

it('hides a GM-only map from a player and shows a shared one', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    Entity::factory()->for($campaign)->type(EntityType::Map)->forPlayers()
        ->create(['name' => 'The Duchy of Vell', 'slug' => 'vell']);
    Entity::factory()->for($campaign)->type(EntityType::Map)->dmOnly()
        ->create(['name' => 'The Undercity', 'slug' => 'undercity']);

    $this->actingAs($player)->get(route('entities.index', [$campaign, 'maps']))
        ->assertOk()
        ->assertSee('The Duchy of Vell')
        ->assertDontSee('The Undercity');

    $this->actingAs($player)->get(route('entities.show', [$campaign, 'maps', 'undercity']))
        ->assertNotFound();
});

it('resolves a wiki link to a map, and prefers the place of the same name', function () {
    $campaign = Campaign::factory()->create();
    $owner = ownerOf($campaign);

    Entity::factory()->for($campaign)->type(EntityType::Map)->forPlayers()
        ->create(['name' => 'Harrowgate map', 'slug' => 'harrowgate-map']);
    $place = Entity::factory()->for($campaign)->type(EntityType::Location)->forPlayers()
        ->create(['name' => 'Harrowgate', 'slug' => 'harrowgate']);
    Entity::factory()->for($campaign)->type(EntityType::Map)->forPlayers()
        ->create(['name' => 'Harrowgate', 'slug' => 'harrowgate-2']);

    $note = Entity::factory()->for($campaign)->forPlayers()
        ->create(['name' => 'Field notes', 'slug' => 'field-notes', 'body' => 'We reached [[Harrowgate]] and read the [[Harrowgate map]].']);

    $html = $this->actingAs($owner)->get($note->url())->assertOk()->getContent();

    // A bare name that matches both resolves to the place: a map sits last in the
    // priority order, because prose about Harrowgate means the town.
    expect($html)->toContain($place->url())
        ->and($html)->toContain('Harrowgate map');
});

it('puts a map in search, in the sidebar count, and in the export', function () {
    $campaign = Campaign::factory()->create();
    $owner = ownerOf($campaign);

    Entity::factory()->for($campaign)->type(EntityType::Map)->forPlayers()
        ->create(['name' => 'The Duchy of Vell', 'slug' => 'vell', 'body' => 'Salt and sea walls.']);

    $this->actingAs($owner)->get(route('search', [$campaign, 'q' => 'Duchy of Vell']))
        ->assertOk()
        ->assertSee('The Duchy of Vell');

    $this->actingAs($owner)->get(route('campaigns.show', $campaign))
        ->assertOk()
        ->assertSee(route('entities.index', [$campaign, 'maps']), false);

    $export = $this->actingAs($owner)->get(route('campaigns.export', $campaign))->assertOk();
    $payload = json_decode($export->streamedContent(), true, 512, JSON_THROW_ON_ERROR);

    expect(collect($payload['entities'])->pluck('name'))->toContain('The Duchy of Vell')
        ->and(collect($payload['entities'])->firstWhere('name', 'The Duchy of Vell')['type'])->toBe('map');
});

it('renders the map through the viewer and keeps it out of the sidebar', function () {
    $campaign = Campaign::factory()->create();
    $owner = ownerOf($campaign);

    Livewire::actingAs($owner)
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'maps'])
        ->set('name', 'The Duchy of Vell')
        ->set('visibility', 'players')
        ->set('image', UploadedFile::fake()->image('vell.png', 2400, 1600))
        ->call('save')
        ->assertHasNoErrors();

    $map = $campaign->entities()->firstOrFail();

    $html = $this->actingAs($owner)->get($map->url())->assertOk()->getContent();

    // The map gets the viewer and the whole width. The aside's picture markup, which
    // wraps a thumbnail in a link to the original, is absent: a map is not a
    // thumbnail of anything.
    expect($html)->toContain('x-data="mapViewer"')
        ->and($html)->not->toContain('rounded-lg border border-line object-cover');
});
