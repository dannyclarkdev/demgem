<?php

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
 * Nesting needed no parent column: a pin whose target is a map opens it, and "which
 * maps hold this one" is the backlinks query the app already runs for prose.
 *
 * The reverse lookup answers something a parent column could not: two maps may pin
 * the same city, and both answers are correct.
 */
beforeEach(fn () => Storage::fake('public'));

function aNestedMap(Campaign $campaign, string $name): Entity
{
    $map = Entity::factory()->for($campaign)->type(EntityType::Map)->forPlayers()
        ->create(['name' => $name, 'slug' => Str::slug($name)]);

    $file = UploadedFile::fake()->image('map.png', 1200, 800);
    $map->addMedia($file->getRealPath())->usingFileName('map.png')->toMediaCollection('image');

    return $map;
}

it('opens the map a pin points at', function () {
    $campaign = Campaign::factory()->create();
    $world = aNestedMap($campaign, 'The world');
    $duchy = aNestedMap($campaign, 'The Duchy of Vell');

    MapMarker::factory()->onMap($world)->pointingAt($duchy)->shownToPlayers()->create();

    $html = Livewire::actingAs(memberOf($campaign, CampaignRole::Player))
        ->test(Viewer::class, ['campaign' => $campaign, 'mapId' => $world->id])
        ->assertSee('The Duchy of Vell')
        ->html();

    // @js() escapes the slashes, so match the path the way the attribute carries it.
    expect($html)->toContain('window.location = ')
        ->and($html)->toContain(str_replace('/', '\\/', $duchy->url()));
});

it('tells a map which maps it appears on', function () {
    $campaign = Campaign::factory()->create();
    $owner = ownerOf($campaign);
    $world = aNestedMap($campaign, 'The world');
    $sea = aNestedMap($campaign, 'The inner sea');
    $duchy = aNestedMap($campaign, 'The Duchy of Vell');

    // Two maps pin the same place, which a parent column could not represent.
    MapMarker::factory()->onMap($world)->pointingAt($duchy)->shownToPlayers()->create();
    MapMarker::factory()->onMap($sea)->pointingAt($duchy)->shownToPlayers()->create();

    $this->actingAs($owner)->get($duchy->url())
        ->assertOk()
        ->assertSee('Appears on')
        ->assertSee('The world')
        ->assertSee('The inner sea');
});

it('tells any entity which maps pin it, not only a map', function () {
    $campaign = Campaign::factory()->create();
    $map = aNestedMap($campaign, 'The Duchy of Vell');
    $place = Entity::factory()->for($campaign)->type(EntityType::Location)->forPlayers()
        ->create(['name' => 'The Salt Cathedral', 'slug' => 'salt-cathedral']);

    MapMarker::factory()->onMap($map)->pointingAt($place)->shownToPlayers()->create();

    $this->actingAs(memberOf($campaign, CampaignRole::Player))->get($place->url())
        ->assertOk()
        ->assertSee('Appears on')
        ->assertSee('The Duchy of Vell');
});

it('says nothing about a map the party cannot open', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    $secretMap = Entity::factory()->for($campaign)->type(EntityType::Map)->dmOnly()
        ->create(['name' => 'The Undercity', 'slug' => 'undercity']);
    $place = Entity::factory()->for($campaign)->type(EntityType::Location)->forPlayers()
        ->create(['name' => 'The Salt Cathedral', 'slug' => 'salt-cathedral']);

    MapMarker::factory()->onMap($secretMap)->pointingAt($place)->shownToPlayers()->create();

    $this->actingAs($player)->get($place->url())
        ->assertOk()
        ->assertDontSee('Appears on')
        ->assertDontSee('The Undercity');
});

it('says nothing about a pin the party has not found', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    $map = aNestedMap($campaign, 'The Duchy of Vell');
    $place = Entity::factory()->for($campaign)->type(EntityType::Location)->forPlayers()
        ->create(['name' => 'The Salt Cathedral', 'slug' => 'salt-cathedral']);

    // The pin exists and is hidden. The party must not learn the place is on the map.
    MapMarker::factory()->onMap($map)->pointingAt($place)->create();

    $this->actingAs($player)->get($place->url())
        ->assertOk()
        ->assertDontSee('Appears on');

    $this->actingAs(ownerOf($campaign))->get($place->url())
        ->assertOk()
        ->assertSee('Appears on');
});

it('renders a cycle and does not hang', function () {
    $campaign = Campaign::factory()->create();
    $owner = ownerOf($campaign);
    $north = aNestedMap($campaign, 'The north');
    $south = aNestedMap($campaign, 'The south');

    // Each map pins the other. The viewer follows one link per click and walks no
    // chain, so there is nothing here to guard against.
    MapMarker::factory()->onMap($north)->pointingAt($south)->shownToPlayers()->create();
    MapMarker::factory()->onMap($south)->pointingAt($north)->shownToPlayers()->create();

    $this->actingAs($owner)->get($north->url())->assertOk()->assertSee('The south');
    $this->actingAs($owner)->get($south->url())->assertOk()->assertSee('The north');
});
