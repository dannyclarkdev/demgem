<?php

use App\Actions\Campaigns\ImportCampaign;
use App\Actions\Campaigns\ReadCampaignFile;
use App\Actions\Entities\DeleteEntity;
use App\Actions\Table\SetScreen;
use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Enums\ScreenFocus;
use App\Enums\Visibility;
use App\Events\HandoutRevealed;
use App\Events\ScreenChanged;
use App\Livewire\Table\ScreenControls;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\User;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * The GM's side of the screen: the action that writes the two columns, the card on
 * the Run screen that calls it, and the event that tells every open screen.
 */
beforeEach(fn () => Storage::fake('public'));

function screenControlsWorld(): array
{
    $campaign = Campaign::factory()->create(['name' => 'The Drowned Duchy']);
    GameSession::factory()->for($campaign)->number(3)->create(['title' => 'The Toll']);

    $hidden = Entity::factory()->for($campaign)->type(EntityType::Handout)->dmOnly()
        ->create(['name' => 'The sealed orders', 'slug' => 'sealed-orders']);

    $shownMap = Entity::factory()->for($campaign)->type(EntityType::Map)->forPlayers()
        ->create(['name' => 'The Duchy of Vell', 'slug' => 'duchy']);
    $duchy = UploadedFile::fake()->image('duchy.png', 1600, 1200);
    $shownMap->addMedia($duchy->getRealPath())->usingFileName('duchy.png')->toMediaCollection('image');

    $hiddenMap = Entity::factory()->for($campaign)->type(EntityType::Map)->dmOnly()
        ->create(['name' => 'The drowned court', 'slug' => 'drowned-court']);
    $court = UploadedFile::fake()->image('court.png', 1600, 1200);
    $hiddenMap->addMedia($court->getRealPath())->usingFileName('court.png')->toMediaCollection('image');

    $bareMap = Entity::factory()->for($campaign)->type(EntityType::Map)->forPlayers()
        ->create(['name' => 'The unfinished sketch', 'slug' => 'sketch']);

    return [$campaign, compact('hidden', 'shownMap', 'hiddenMap', 'bareMap')];
}

it('puts a hidden handout on the screen and shows the party on the way', function () {
    [$campaign, $world] = screenControlsWorld();

    Event::fake([HandoutRevealed::class, ScreenChanged::class]);

    app(SetScreen::class)->show($campaign, $world['hidden'], ownerOf($campaign));

    $campaign->refresh();

    expect($world['hidden']->fresh()->visibility)->toBe(Visibility::Players)
        ->and($campaign->screen_focus)->toBe(ScreenFocus::Handout)
        ->and($campaign->screen_entity_id)->toBe($world['hidden']->id);

    Event::assertDispatched(HandoutRevealed::class);
    Event::assertDispatched(ScreenChanged::class, fn (ScreenChanged $event) => $event->campaignId === $campaign->id);
});

it('puts a map on the screen, and refuses one the party cannot see', function () {
    [$campaign, $world] = screenControlsWorld();
    $gm = ownerOf($campaign);

    app(SetScreen::class)->show($campaign, $world['shownMap'], $gm);

    expect($campaign->fresh()->screen_focus)->toBe(ScreenFocus::Map)
        ->and($campaign->fresh()->screen_entity_id)->toBe($world['shownMap']->id);

    expect(fn () => app(SetScreen::class)->show($campaign, $world['hiddenMap'], $gm))
        ->toThrow(InvalidArgumentException::class);

    // The refusal left the screen as it was.
    expect($campaign->fresh()->screen_entity_id)->toBe($world['shownMap']->id)
        ->and($world['hiddenMap']->fresh()->visibility)->toBe(Visibility::Dm);
});

it('refuses a page that is neither a handout nor a map', function () {
    [$campaign] = screenControlsWorld();

    $npc = Entity::factory()->for($campaign)->forPlayers()->create(['name' => 'Mara Voss']);

    expect(fn () => app(SetScreen::class)->show($campaign, $npc, ownerOf($campaign)))
        ->toThrow(InvalidArgumentException::class);
});

it('shows the fight or the clocks, and clears the page on the way', function () {
    [$campaign, $world] = screenControlsWorld();

    $set = app(SetScreen::class);

    $set->show($campaign, $world['shownMap'], ownerOf($campaign));
    $set->focus($campaign, ScreenFocus::Fight);

    expect($campaign->fresh()->screen_focus)->toBe(ScreenFocus::Fight)
        ->and($campaign->fresh()->screen_entity_id)->toBeNull();

    $set->focus($campaign, ScreenFocus::Clocks);

    expect($campaign->fresh()->screen_focus)->toBe(ScreenFocus::Clocks);

    $set->focus($campaign, null);

    expect($campaign->fresh()->screen_focus)->toBeNull()
        ->and($campaign->fresh()->screen_entity_id)->toBeNull();
});

it('refuses a focus that needs a page through focus()', function () {
    [$campaign] = screenControlsWorld();

    expect(fn () => app(SetScreen::class)->focus($campaign, ScreenFocus::Handout))
        ->toThrow(InvalidArgumentException::class);
});

it('sits on the Run screen and works from there', function () {
    [$campaign, $world] = screenControlsWorld();
    $gm = ownerOf($campaign);

    $this->actingAs($gm)->get(route('sessions.run', [$campaign, 3]))
        ->assertOk()
        ->assertSee('On the screen')
        ->assertSee(route('screen', $campaign), false);

    $component = Livewire::actingAs($gm)
        ->test(ScreenControls::class, ['campaign' => $campaign])
        ->assertSee('The sealed orders')
        ->assertSee('The Duchy of Vell')
        // A map the party cannot see, and a map with no picture, are not offered.
        ->assertDontSee('The drowned court')
        ->assertDontSee('The unfinished sketch');

    $component->call('showPage', $world['hidden']->id);

    expect($campaign->fresh()->screen_focus)->toBe(ScreenFocus::Handout)
        ->and($world['hidden']->fresh()->visibility)->toBe(Visibility::Players);

    $component->call('showPage', $world['shownMap']->id);

    expect($campaign->fresh()->screen_focus)->toBe(ScreenFocus::Map);

    $component->call('focus', 'fight');

    expect($campaign->fresh()->screen_focus)->toBe(ScreenFocus::Fight)
        ->and($campaign->fresh()->screen_entity_id)->toBeNull();

    $component->call('clear');

    expect($campaign->fresh()->screen_focus)->toBeNull();
});

it('is a GM tool', function () {
    [$campaign, $world] = screenControlsWorld();

    foreach ([CampaignRole::Player, CampaignRole::Spectator] as $role) {
        Livewire::actingAs(memberOf($campaign, $role))
            ->test(ScreenControls::class, ['campaign' => $campaign])
            ->call('showPage', $world['shownMap']->id)
            ->assertForbidden();
    }

    expect($campaign->fresh()->screen_focus)->toBeNull();
});

it('gives the card a 404 for a page the party may not see', function () {
    [$campaign, $world] = screenControlsWorld();

    Livewire::actingAs(ownerOf($campaign))
        ->test(ScreenControls::class, ['campaign' => $campaign])
        ->call('showPage', $world['hiddenMap']->id)
        ->assertNotFound();
});

it('says where it broadcasts, what it is called, and that it carries nothing', function () {
    $event = new ScreenChanged('01campaign');

    expect($event)->toBeInstanceOf(ShouldBroadcast::class)
        ->and($event)->toBeInstanceOf(ShouldRescue::class)
        ->and($event->broadcastAs())->toBe('screen.changed')
        ->and($event->broadcastWith())->toBe([]);

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(PresenceChannel::class)
        ->and($channels[0]->name)->toBe('presence-campaign.01campaign');
});

it('clears the screen when the page on it is deleted', function () {
    [$campaign, $world] = screenControlsWorld();

    app(SetScreen::class)->show($campaign, $world['shownMap'], ownerOf($campaign));
    app(DeleteEntity::class)->handle($world['shownMap']);

    expect($campaign->fresh()->screen_focus)->toBeNull()
        ->and($campaign->fresh()->screen_entity_id)->toBeNull();
});

it('travels in the export and comes back pointing at the copy', function () {
    [$campaign, $world] = screenControlsWorld();

    app(SetScreen::class)->show($campaign, $world['shownMap'], ownerOf($campaign));

    $document = exportedArray($campaign);

    expect($document['campaign']['screen_focus'])->toBe('map')
        ->and($document['campaign']['screen_entity_id'])->toBe($world['shownMap']->id);

    $read = app(ReadCampaignFile::class)->handle(json_encode($document, JSON_THROW_ON_ERROR));

    expect($read->succeeded())->toBeTrue();

    $copy = app(ImportCampaign::class)->handle($read->document, User::factory()->create());
    $copiedMap = $copy->entities()->where('slug', 'duchy')->firstOrFail();

    expect($copy->screen_focus)->toBe(ScreenFocus::Map)
        ->and($copy->screen_entity_id)->toBe($copiedMap->id)
        ->and($copy->screen_entity_id)->not->toBe($world['shownMap']->id);
});

it('reads an older file that never heard of the screen', function () {
    [$campaign] = screenControlsWorld();

    $document = exportedArray($campaign);
    unset($document['campaign']['screen_focus'], $document['campaign']['screen_entity_id']);

    $read = app(ReadCampaignFile::class)->handle(json_encode($document, JSON_THROW_ON_ERROR));

    expect($read->succeeded())->toBeTrue()
        ->and($read->document['campaign']['screen_focus'])->toBeNull()
        ->and($read->document['campaign']['screen_entity_id'])->toBeNull();
});

it('refuses a file whose screen points at a page it does not carry', function () {
    [$campaign, $world] = screenControlsWorld();

    app(SetScreen::class)->show($campaign, $world['shownMap'], ownerOf($campaign));

    $document = exportedArray($campaign);
    $document['campaign']['screen_entity_id'] = '01JNOTINTHISFILE0000000000';

    $read = app(ReadCampaignFile::class)->handle(json_encode($document, JSON_THROW_ON_ERROR));

    expect($read->succeeded())->toBeFalse()
        ->and(implode(' ', $read->errors))->toContain('the screen');
});
