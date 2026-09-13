<?php

use App\Actions\Encounters\NextTurn;
use App\Actions\Handouts\RevealHandout;
use App\Actions\Table\SetScreen;
use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Enums\ScreenFocus;
use App\Enums\Visibility;
use App\Livewire\Table\Screen;
use App\Models\Campaign;
use App\Models\Clock;
use App\Models\Combatant;
use App\Models\Encounter;
use App\Models\Entity;
use App\Models\MapMarker;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * /screen is the page on the television at the end of the table. These tests are
 * about what it says, and about the one rule that makes it safe to put on a wall:
 * whoever opened it, it shows what the party may see and nothing else.
 */
beforeEach(fn () => Storage::fake('public'));

function screenWorld(): array
{
    $campaign = Campaign::factory()->create(['name' => 'The Drowned Duchy']);
    $player = memberOf($campaign, CampaignRole::Player);

    $pc = Entity::factory()->for($campaign)->pcOf($player)->forPlayers()
        ->create(['name' => 'Wren Aldercross', 'slug' => 'wren']);

    $encounter = Encounter::factory()->for($campaign)->active(3)->create(['name' => 'The betrayal at the ford']);

    Combatant::factory()->inEncounter($encounter, 0)->forEntity($pc)->shownToPlayers()->withHealth(24, 30)->create();
    $ogre = Combatant::factory()->inEncounter($encounter, 1)->shownToPlayers()->withHealth(9, 60)
        ->create(['name' => 'Ogre chieftain', 'ac' => 17]);
    Combatant::factory()->inEncounter($encounter, 2)->withHealth(12, 12)->create(['name' => 'Cellar lurker']);
    $encounter->update(['active_combatant_id' => $ogre->id]);

    $letter = Entity::factory()->for($campaign)->type(EntityType::Handout)->forPlayers()
        ->create(['name' => 'The dukes letter', 'slug' => 'dukes-letter']);
    $orders = Entity::factory()->for($campaign)->type(EntityType::Handout)->dmOnly()
        ->create(['name' => 'The sealed orders', 'slug' => 'sealed-orders']);

    foreach ([$letter, $orders] as $handout) {
        $file = UploadedFile::fake()->image($handout->slug.'.png', 900, 1200);
        $handout->addMedia($file->getRealPath())->usingFileName($handout->slug.'.png')->toMediaCollection('files');
    }

    $map = Entity::factory()->for($campaign)->type(EntityType::Map)->forPlayers()
        ->create(['name' => 'The Duchy of Vell', 'slug' => 'duchy']);
    $picture = UploadedFile::fake()->image('duchy.png', 1600, 1200);
    $map->addMedia($picture->getRealPath())->usingFileName('duchy.png')->toMediaCollection('image');

    MapMarker::factory()->onMap($map)->shownToPlayers()->create(['label' => 'The Salt Cathedral']);
    MapMarker::factory()->onMap($map)->create(['label' => 'The smugglers stair']);

    Clock::factory()->inCampaign($campaign)->shownToPlayers()->create(['name' => 'The tide takes the lower town']);
    Clock::factory()->inCampaign($campaign)->create(['name' => 'The Drowned Court finds the sister']);

    return [$campaign, $player, compact('encounter', 'pc', 'letter', 'orders', 'map')];
}

it('opens to every member, and to nobody else', function () {
    [$campaign] = screenWorld();

    foreach (CampaignRole::cases() as $role) {
        $member = $role === CampaignRole::Owner ? ownerOf($campaign) : memberOf($campaign, $role);

        $this->actingAs($member)->get(route('screen', $campaign))->assertOk();
    }

    $this->actingAs(User::factory()->create())->get(route('screen', $campaign))->assertNotFound();
});

it('has no sidebar, no search, and no header, because it is on a wall', function () {
    [$campaign, $player] = screenWorld();

    $this->actingAs($player)->get(route('screen', $campaign))
        ->assertOk()
        ->assertSee('The Drowned Duchy')
        ->assertDontSee('Open navigation')
        ->assertDontSee('Search the campaign');
});

it('shows the fight as the party sees it, even to the GM who opened it', function () {
    [$campaign] = screenWorld();

    app(SetScreen::class)->focus($campaign, ScreenFocus::Fight);

    $this->actingAs(ownerOf($campaign))->get(route('screen', $campaign))
        ->assertOk()
        ->assertSee('Round 3')
        ->assertSee('Wren Aldercross')
        ->assertSee('Ogre chieftain')
        ->assertSee('Badly hurt')
        ->assertSee('is up')
        ->assertDontSee('9/60')
        ->assertDontSee('24/30')
        ->assertDontSee('Cellar lurker')
        ->assertDontSee('Hidden');
});

it('shows the handout the GM put up, full size', function () {
    [$campaign, $player, $world] = screenWorld();

    app(SetScreen::class)->show($campaign, $world['letter'], ownerOf($campaign));

    $this->actingAs($player)->get(route('screen', $campaign))
        ->assertOk()
        ->assertSee('The dukes letter')
        ->assertSee($world['letter']->files()->first()->getUrl(), false);
});

it('shows nothing of a handout the GM took back, though the column still names it', function () {
    [$campaign, $player, $world] = screenWorld();
    $gm = ownerOf($campaign);

    app(SetScreen::class)->show($campaign, $world['letter'], ownerOf($campaign));
    app(RevealHandout::class)->takeBack($world['letter'], $gm);

    expect($campaign->fresh()->screen_entity_id)->toBe($world['letter']->id);

    $this->actingAs($gm)->get(route('screen', $campaign))
        ->assertOk()
        ->assertSee('The Drowned Duchy')
        ->assertDontSee('The dukes letter')
        ->assertDontSee($world['letter']->files()->first()->getUrl(), false);
});

it('never shows a handout shared with selected players, even to one of them', function () {
    [$campaign, $player] = screenWorld();

    $chosen = Entity::factory()->for($campaign)->type(EntityType::Handout)->selectedFor($player)
        ->create(['name' => 'The whispered name', 'slug' => 'whispered-name']);

    $campaign->forceFill(['screen_focus' => ScreenFocus::Handout, 'screen_entity_id' => $chosen->id])->save();

    $this->actingAs($player)->get(route('screen', $campaign))
        ->assertOk()
        ->assertDontSee('The whispered name');
});

it('keeps a hidden handout out of the snapshot when the column points at it', function () {
    [$campaign, , $world] = screenWorld();

    $campaign->forceFill(['screen_focus' => ScreenFocus::Handout, 'screen_entity_id' => $world['orders']->id])->save();

    $component = Livewire::actingAs(ownerOf($campaign))
        ->test(Screen::class, ['campaign' => $campaign])
        ->assertOk()
        ->assertDontSee('The sealed orders');

    $payload = json_encode($component->snapshot, JSON_THROW_ON_ERROR);

    expect($payload)->not->toContain('The sealed orders')
        ->and($payload)->not->toContain($world['orders']->files()->first()->getUrl());
});

it('shows the map with the pins the party found', function () {
    [$campaign, $player, $world] = screenWorld();

    app(SetScreen::class)->show($campaign, $world['map'], ownerOf($campaign));

    $this->actingAs($player)->get(route('screen', $campaign))
        ->assertOk()
        ->assertSee('The Duchy of Vell')
        ->assertSee($world['map']->imageUrl(), false)
        ->assertSee('The Salt Cathedral')
        ->assertDontSee('The smugglers stair');
});

it('shows the revealed clocks large as the focus, and in a strip otherwise', function () {
    [$campaign, $player] = screenWorld();

    app(SetScreen::class)->focus($campaign, ScreenFocus::Clocks);

    $this->actingAs($player)->get(route('screen', $campaign))
        ->assertOk()
        ->assertSee('The tide takes the lower town')
        ->assertDontSee('The Drowned Court finds the sister');

    app(SetScreen::class)->focus($campaign, ScreenFocus::Fight);

    $this->actingAs($player)->get(route('screen', $campaign))
        ->assertSee('Round 3')
        ->assertSee('The tide takes the lower town')
        ->assertDontSee('The Drowned Court finds the sister');
});

it('falls back to the fight, and then to the party, when nothing is chosen', function () {
    [$campaign, $player, $world] = screenWorld();

    $this->actingAs($player)->get(route('screen', $campaign))
        ->assertOk()
        ->assertSee('Round 3');

    app(NextTurn::class)->end($world['encounter']);

    $this->actingAs($player)->get(route('screen', $campaign))
        ->assertOk()
        ->assertDontSee('Round 3')
        ->assertSee('The Drowned Duchy')
        ->assertSee('Wren Aldercross');
});

it('falls back when the fight it was showing ends', function () {
    [$campaign, $player, $world] = screenWorld();

    app(SetScreen::class)->focus($campaign, ScreenFocus::Fight);
    app(NextTurn::class)->end($world['encounter']);

    $this->actingAs($player)->get(route('screen', $campaign))
        ->assertOk()
        ->assertDontSee('Round 3')
        ->assertSee('The Drowned Duchy');
});

it('follows the GM without a refresh', function () {
    [$campaign, $player, $world] = screenWorld();

    $component = Livewire::actingAs($player)
        ->test(Screen::class, ['campaign' => $campaign])
        ->assertSee('Round 3')
        ->assertDontSee('The dukes letter');

    app(SetScreen::class)->show($campaign, $world['letter'], ownerOf($campaign));

    $component->dispatch('echo-presence:campaign.'.$campaign->id.',.screen.changed')
        ->assertSee('The dukes letter')
        ->assertDontSee('Round 3');
});

it('keeps the poll as a backstop, at a minute', function () {
    [$campaign, $player] = screenWorld();

    $this->actingAs($player)->get(route('screen', $campaign))
        ->assertSee('wire:poll.visible.'.Screen::POLL_SECONDS.'s', false);
});

it('offers the screen from the table page', function () {
    [$campaign, $player] = screenWorld();

    $this->actingAs($player)->get(route('table', $campaign))
        ->assertOk()
        ->assertSee(route('screen', $campaign), false);
});

it('resolves a focus that needs a page to nothing when the page is not there', function () {
    [$campaign, , $world] = screenWorld();

    $campaign->forceFill(['screen_focus' => ScreenFocus::Map, 'screen_entity_id' => null])->save();

    expect($campaign->screen()->focus)->toBeNull()
        ->and($campaign->screen()->entity)->toBeNull();

    $campaign->forceFill(['screen_focus' => ScreenFocus::Handout, 'screen_entity_id' => $world['orders']->id])->save();

    expect($campaign->screen()->focus)->toBeNull();

    $world['orders']->update(['visibility' => Visibility::Players]);

    expect($campaign->fresh()->screen()->focus)->toBe(ScreenFocus::Handout)
        ->and($campaign->fresh()->screen()->entity?->id)->toBe($world['orders']->id);
});
