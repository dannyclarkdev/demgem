<?php

use App\Actions\Clocks\Segments;
use App\Actions\Clocks\TickClock;
use App\Enums\CampaignRole;
use App\Events\ClockChanged;
use App\Livewire\Clocks\Index;
use App\Livewire\Clocks\Panel;
use App\Models\Campaign;
use App\Models\Clock;
use App\Models\Entity;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

/**
 * Making, turning, resizing, reordering and removing a clock. What a player sees of
 * one is ClockVisibilityTest, which is a leak test and lives on its own.
 */
it('makes a clock empty and hidden', function () {
    $campaign = Campaign::factory()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Panel::class, ['campaign' => $campaign])
        ->set('newName', 'The ritual')
        ->set('newSegments', 8)
        ->call('create')
        ->assertHasNoErrors();

    $clock = Clock::query()->sole();

    expect($clock->name)->toBe('The ritual')
        ->and($clock->segments)->toBe(8)
        ->and($clock->filled)->toBe(0)
        ->and($clock->campaign_id)->toBe($campaign->id)
        ->and($clock->entity_id)->toBeNull()
        // Everything a GM adds waits for the eye, as a pin and a combatant do.
        ->and($clock->player_visible)->toBeFalse();
});

it('puts a new clock at the end of the list', function () {
    $campaign = Campaign::factory()->create();
    Clock::factory()->inCampaign($campaign)->create(['name' => 'The storm', 'position' => 0]);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Panel::class, ['campaign' => $campaign])
        ->set('newName', 'The ritual')
        ->call('create');

    $clock = Clock::query()->where('name', 'The ritual')->sole();

    expect($clock->position)->toBe(1);
});

it('fills and empties a wedge at a time', function () {
    $campaign = Campaign::factory()->create();
    $clock = Clock::factory()->inCampaign($campaign)->sized(6)->create();

    $panel = Livewire::actingAs(ownerOf($campaign))
        ->test(Panel::class, ['campaign' => $campaign]);

    $panel->call('tick', $clock->id, 1);
    expect($clock->refresh()->filled)->toBe(1);

    $panel->call('tick', $clock->id, 1);
    expect($clock->refresh()->filled)->toBe(2);

    $panel->call('tick', $clock->id, -1);
    expect($clock->refresh()->filled)->toBe(1);
});

it('sets the fill to the wedge the GM clicked', function () {
    $campaign = Campaign::factory()->create();
    $clock = Clock::factory()->inCampaign($campaign)->sized(8)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Panel::class, ['campaign' => $campaign])
        ->call('setTo', $clock->id, 5);

    expect($clock->refresh()->filled)->toBe(5);
});

it('clamps a fill a browser made up', function (int $segments, int $sent, int $stored) {
    expect(Segments::clampFill($sent, $segments))->toBe($stored);
})->with([
    'inside' => [6, 3, 3],
    'exactly full' => [6, 6, 6],
    'over the top' => [6, 99, 6],
    'below the bottom' => [6, -4, 0],
]);

it('clamps a dial size a browser made up', function (int $sent, int $stored) {
    expect(Segments::clamp($sent))->toBe($stored);
})->with([
    'inside' => [8, 8],
    'too small' => [0, 2],
    'negative' => [-3, 2],
    'too large' => [400, 12],
]);

it('never writes a fill outside the dial', function () {
    $campaign = Campaign::factory()->create();
    $clock = Clock::factory()->inCampaign($campaign)->sized(4)->filled(4)->create();

    $tick = app(TickClock::class);

    $tick->by($clock, 1);
    expect($clock->refresh()->filled)->toBe(4);

    $tick->to($clock, 0);
    $tick->by($clock, -1);
    expect($clock->refresh()->filled)->toBe(0);
});

it('brings the fill down when the dial shrinks', function () {
    $campaign = Campaign::factory()->create();
    $clock = Clock::factory()->inCampaign($campaign)->sized(12)->filled(9)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Index::class, ['campaign' => $campaign])
        ->call('edit', $clock->id)
        ->set('editingName', 'The ritual, sooner')
        ->set('editingSegments', 4)
        ->call('save')
        ->assertHasNoErrors();

    $clock->refresh();

    expect($clock->name)->toBe('The ritual, sooner')
        ->and($clock->segments)->toBe(4)
        ->and($clock->filled)->toBe(4);
});

it('leaves the fill alone when the dial grows', function () {
    $campaign = Campaign::factory()->create();
    $clock = Clock::factory()->inCampaign($campaign)->sized(4)->filled(3)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Index::class, ['campaign' => $campaign])
        ->call('edit', $clock->id)
        ->set('editingName', $clock->name)
        ->set('editingSegments', 12)
        ->call('save');

    expect($clock->refresh()->filled)->toBe(3);
});

it('reorders clocks and keeps the positions contiguous', function () {
    $campaign = Campaign::factory()->create();
    $storm = Clock::factory()->inCampaign($campaign)->create(['name' => 'The storm', 'position' => 0]);
    $ritual = Clock::factory()->inCampaign($campaign)->create(['name' => 'The ritual', 'position' => 1]);
    $siege = Clock::factory()->inCampaign($campaign)->create(['name' => 'The siege', 'position' => 2]);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Index::class, ['campaign' => $campaign])
        ->call('reorder', $siege->id, 0);

    expect(Clock::query()->orderBy('position')->pluck('name')->all())
        ->toBe(['The siege', 'The storm', 'The ritual'])
        ->and(Clock::query()->orderBy('position')->pluck('position')->all())
        ->toBe([0, 1, 2]);

    expect($ritual->refresh()->position)->toBe(2);
});

it('steps a clock one place with the arrows, whatever the stored numbers are', function () {
    $campaign = Campaign::factory()->create();
    // A deleted clock leaves a hole in the numbers until the next drag rewrites them.
    // The arrows have to move one row, not one number.
    Clock::factory()->inCampaign($campaign)->create(['name' => 'The storm', 'position' => 0]);
    Clock::factory()->inCampaign($campaign)->create(['name' => 'The ritual', 'position' => 7]);
    $siege = Clock::factory()->inCampaign($campaign)->create(['name' => 'The siege', 'position' => 40]);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Index::class, ['campaign' => $campaign])
        ->call('move', $siege->id, -1);

    expect(Clock::query()->orderBy('position')->pluck('name')->all())
        ->toBe(['The storm', 'The siege', 'The ritual']);
});

it('deletes a clock and leaves what it was about alone', function () {
    $campaign = Campaign::factory()->create();
    $cult = Entity::factory()->for($campaign)->create(['name' => 'The Ashen Choir']);
    $clock = Clock::factory()->about($cult)->create(['name' => 'The ritual']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Index::class, ['campaign' => $campaign])
        ->call('delete', $clock->id);

    expect(Clock::query()->count())->toBe(0)
        ->and($cult->fresh())->not->toBeNull();
});

it('lets go of the clock when the entity it was about is deleted', function () {
    $campaign = Campaign::factory()->create();
    $cult = Entity::factory()->for($campaign)->create(['name' => 'The Ashen Choir']);
    $clock = Clock::factory()->about($cult)->create();

    $cult->forceDelete();

    expect($clock->refresh()->entity_id)->toBeNull();
});

it('keeps a player out of every write', function () {
    $campaign = Campaign::factory()->create();
    $clock = Clock::factory()->inCampaign($campaign)->shownToPlayers()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    $panel = fn () => Livewire::actingAs($player)->test(Panel::class, ['campaign' => $campaign]);

    $panel()->set('newName', 'Mine now')->call('create')->assertForbidden();
    $panel()->call('tick', $clock->id, 1)->assertForbidden();
    $panel()->call('setTo', $clock->id, 3)->assertForbidden();
    $panel()->call('delete', $clock->id)->assertForbidden();

    expect($clock->refresh()->filled)->toBe(0)
        ->and(Clock::query()->count())->toBe(1);
});

it('shows the GM the page with the dial on it', function () {
    $campaign = Campaign::factory()->create();
    Clock::factory()->inCampaign($campaign)->sized(8)->filled(3)->create(['name' => 'The drowning tide']);

    $this->actingAs(ownerOf($campaign))
        ->get(route('clocks.index', $campaign))
        ->assertOk()
        ->assertSee('The drowning tide')
        ->assertSee('3 of 8');
});

it('shuts a player out of the clocks page', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    // 403 rather than 404, which is what /tables does: the page exists and is not
    // theirs. A single clock is a different question, and the visibility scope
    // answers that one by never loading the row.
    $this->actingAs($player)
        ->get(route('clocks.index', $campaign))
        ->assertForbidden();
});

it('broadcasts the fact and nothing about it', function () {
    Event::fake([ClockChanged::class]);

    $campaign = Campaign::factory()->create();
    $clock = Clock::factory()->inCampaign($campaign)->sized(6)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Panel::class, ['campaign' => $campaign])
        ->call('tick', $clock->id, 1);

    Event::assertDispatchedTimes(ClockChanged::class, 1);

    Event::assertDispatched(ClockChanged::class, function (ClockChanged $event) use ($campaign, $clock) {
        return $event->campaignId === $campaign->id
            && $event->clockId === $clock->id
            && $event->broadcastWith() === ['clockId' => $clock->id];
    });
});

it('says nothing when a tick changes nothing', function () {
    Event::fake([ClockChanged::class]);

    $campaign = Campaign::factory()->create();
    $clock = Clock::factory()->inCampaign($campaign)->sized(6)->filled(6)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Panel::class, ['campaign' => $campaign])
        ->call('tick', $clock->id, 1);

    Event::assertNotDispatched(ClockChanged::class);
});
