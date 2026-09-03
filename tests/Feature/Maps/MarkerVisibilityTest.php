<?php

use App\Actions\Maps\SetMarkerVisibility;
use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Livewire\Maps\Viewer;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\MapMarker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * The leak test of this slice, in its own file because every leak it guards is the
 * same kind: something the GM has not shown the party turning up on the party's map.
 *
 * A pin has two gates. The eye is the first. The target's own visibility is the
 * second, and it is the one nobody thinks of: a GM who reveals the pin for a GM-only
 * NPC has made a mistake, and the app must not turn a mistake into a leak.
 *
 * The proofs are labels with spaces in them, never coordinates. Every id here is a
 * ULID and a bare number turns up in one by chance.
 */
beforeEach(fn () => Storage::fake('public'));

function mapWithPins(): array
{
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    $map = Entity::factory()->for($campaign)->type(EntityType::Map)->forPlayers()
        ->create(['name' => 'The Duchy of Vell', 'slug' => 'vell']);

    $file = UploadedFile::fake()->image('map.png', 2400, 1600);
    $map->addMedia($file->getRealPath())->usingFileName('map.png')->toMediaCollection('image');

    $known = Entity::factory()->for($campaign)->type(EntityType::Location)->forPlayers()
        ->create(['name' => 'The Salt Cathedral', 'slug' => 'salt-cathedral']);

    $secret = Entity::factory()->for($campaign)->type(EntityType::Location)->dmOnly()
        ->create(['name' => 'The Drowned Court', 'slug' => 'drowned-court']);

    $pins = [
        // Revealed, pointing at somewhere the party knows. The party sees this one.
        'shown' => MapMarker::factory()->onMap($map)->pointingAt($known)->shownToPlayers()->at(20, 30)->create(),
        // Not revealed. The party has not found it.
        'hidden' => MapMarker::factory()->onMap($map)->at(40, 50)->create(['label' => 'The smugglers stair']),
        // Revealed by mistake, pointing at a GM-only place. The second gate catches it.
        'leaky' => MapMarker::factory()->onMap($map)->pointingAt($secret)->shownToPlayers()->at(60, 70)->create(),
        // Revealed, pointing at nothing. An annotation, and the party sees it.
        'annotation' => MapMarker::factory()->onMap($map)->shownToPlayers()->at(80, 90)
            ->create(['label' => 'Here be dragons']),
    ];

    return [$campaign, $player, $map, $pins];
}

it('keeps an unrevealed pin off a player map, markup and snapshot alike', function () {
    [$campaign, $player, $map] = mapWithPins();

    $component = Livewire::actingAs($player)
        ->test(Viewer::class, ['campaign' => $campaign, 'mapId' => $map->id])
        ->assertOk()
        ->assertSee('The Salt Cathedral')
        ->assertDontSee('The smugglers stair');

    expect(json_encode($component->snapshot, JSON_THROW_ON_ERROR))
        ->not->toContain('The smugglers stair');
});

it('catches a pin the GM revealed onto something the party may not see', function () {
    [$campaign, $player, $map] = mapWithPins();

    // The eye says yes and the target says no, so the answer is no. This is the gate
    // that exists because a GM will get the first one wrong one day.
    $component = Livewire::actingAs($player)
        ->test(Viewer::class, ['campaign' => $campaign, 'mapId' => $map->id])
        ->assertDontSee('The Drowned Court');

    expect(json_encode($component->snapshot, JSON_THROW_ON_ERROR))
        ->not->toContain('The Drowned Court');
});

it('shows a revealed pin that points at nothing', function () {
    [$campaign, $player, $map] = mapWithPins();

    Livewire::actingAs($player)
        ->test(Viewer::class, ['campaign' => $campaign, 'mapId' => $map->id])
        ->assertSee('Here be dragons');
});

it('shows the GM every pin and which of them the party can see', function () {
    [$campaign, $player, $map] = mapWithPins();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Viewer::class, ['campaign' => $campaign, 'mapId' => $map->id])
        ->assertSee('The Salt Cathedral')
        ->assertSee('The smugglers stair')
        ->assertSee('The Drowned Court')
        ->assertSee('Here be dragons')
        ->assertSee('4 pins, 3 the party can see');
});

it('carries no hidden coordinate to a player either', function () {
    [$campaign, $player, $map, $pins] = mapWithPins();

    $html = Livewire::actingAs($player)
        ->test(Viewer::class, ['campaign' => $campaign, 'mapId' => $map->id])
        ->html();

    // Not "40" or "50": every id on the page is a ULID and a bare number turns up in
    // one by chance. The style attribute is exact and cannot appear by accident.
    expect($html)->not->toContain('left: 40%; top: 50%')
        ->and($html)->not->toContain('left: 60%; top: 70%')
        ->and($html)->toContain('left: 20%; top: 30%');
});

it('follows the GM revealing a pin mid-session', function () {
    [$campaign, $player, $map, $pins] = mapWithPins();

    $viewer = Livewire::actingAs($player)
        ->test(Viewer::class, ['campaign' => $campaign, 'mapId' => $map->id])
        ->assertDontSee('The smugglers stair');

    app(SetMarkerVisibility::class)->handle($pins['hidden'], true);

    $viewer->call('$refresh')->assertSee('The smugglers stair');
});

it('reveals and hides every pin at once, for the end of a session', function () {
    [$campaign, $player, $map] = mapWithPins();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Viewer::class, ['campaign' => $campaign, 'mapId' => $map->id])
        ->call('setAllVisibility', true)
        ->assertSee('4 pins, 4 the party can see')
        ->call('setAllVisibility', false)
        ->assertSee('4 pins, 0 the party can see');

    // Even with every eye open, the second gate still holds.
    app(SetMarkerVisibility::class)->setAll($map, true);

    Livewire::actingAs($player)
        ->test(Viewer::class, ['campaign' => $campaign, 'mapId' => $map->id])
        ->assertSee('The smugglers stair')
        ->assertDontSee('The Drowned Court');
});

it('gives a player no way to reveal a pin themselves', function (CampaignRole $role) {
    [$campaign, $ignored, $map, $pins] = mapWithPins();

    $member = memberOf($campaign, $role);

    // A fresh component for each call: the first refusal leaves the instance in an
    // error state, and reusing it would test the error rather than the guard.
    foreach ([['toggleVisibility', $pins['hidden']->id], ['setAllVisibility', true]] as [$method, $argument]) {
        Livewire::actingAs($member)
            ->test(Viewer::class, ['campaign' => $campaign, 'mapId' => $map->id])
            ->call($method, $argument)
            ->assertForbidden();
    }

    expect($pins['hidden']->fresh()->player_visible)->toBeFalse();
})->with([CampaignRole::Player, CampaignRole::Spectator]);

it('flips the eye from the GM map', function () {
    [$campaign, $player, $map, $pins] = mapWithPins();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Viewer::class, ['campaign' => $campaign, 'mapId' => $map->id])
        ->call('toggleVisibility', $pins['hidden']->id);

    expect($pins['hidden']->fresh()->player_visible)->toBeTrue();
});

it('stays quiet when the eye is already where the GM wants it', function () {
    [$campaign, $player, $map, $pins] = mapWithPins();

    $before = $pins['hidden']->fresh()->updated_at;

    app(SetMarkerVisibility::class)->handle($pins['hidden'], false);

    expect($pins['hidden']->fresh()->updated_at->equalTo($before))->toBeTrue();
});

it('stops showing the map to a member who was removed mid-session', function () {
    [$campaign, $player, $map] = mapWithPins();

    $viewer = Livewire::actingAs($player)
        ->test(Viewer::class, ['campaign' => $campaign, 'mapId' => $map->id])
        ->assertSee('The Salt Cathedral');

    $campaign->members()->where('user_id', $player->id)->delete();
    $campaign->forgetMemberCache();

    $viewer->call('$refresh')->assertNotFound();
});

it('costs the same number of queries whatever the map holds', function () {
    [$campaign, $player, $map] = mapWithPins();

    $count = function () use ($player, $campaign, $map): int {
        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::actingAs($player)->test(Viewer::class, ['campaign' => $campaign, 'mapId' => $map->id]);

        $queries = count(DB::getQueryLog());

        DB::disableQueryLog();

        return $queries;
    };

    // One warm-up first: Campaign caches its member lookup per instance.
    $count();
    $small = $count();

    MapMarker::factory()->count(40)->onMap($map)->shownToPlayers()
        ->pointingAt(Entity::factory()->for($campaign)->forPlayers()->create())->create();

    expect($count())->toBe($small);
});
