<?php

use App\Actions\Clocks\SetClockVisibility;
use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Livewire\Clocks\Panel;
use App\Livewire\Table\Show;
use App\Models\Campaign;
use App\Models\Clock;
use App\Models\Entity;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * The leak test of this phase, in its own file because every leak it guards is the
 * same kind: something the GM has not shown the party turning up on the party's
 * screen.
 *
 * A clock has one gate on the row and a second on its link, and the second one is
 * the interesting one. It is deliberately *not* the map pin's rule. A pin is nothing
 * but a link, so a pin whose target is hidden has nothing left to show. A clock only
 * mentions one, and a GM who revealed "The Duke's suspicion" meant the party to read
 * those words. So the dial stays and the duke's name does not.
 *
 * The proofs are names with spaces in them, never counts. Every id here is a ULID and
 * a bare number turns up in one by chance.
 */
function clocksOnTheTable(): array
{
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    $court = Entity::factory()->for($campaign)->type(EntityType::Faction)->dmOnly()
        ->create(['name' => 'The Drowned Court', 'slug' => 'drowned-court']);

    $choir = Entity::factory()->for($campaign)->type(EntityType::Faction)->forPlayers()
        ->create(['name' => 'The Ashen Choir', 'slug' => 'ashen-choir']);

    $clocks = [
        // Revealed, about nothing. The party sees it.
        'shown' => Clock::factory()->inCampaign($campaign)->shownToPlayers()->sized(6)->filled(2)
            ->create(['name' => 'The drowning tide']),
        // Not revealed. The party has not been told this one is running.
        'hidden' => Clock::factory()->inCampaign($campaign)->sized(8)->filled(5)
            ->create(['name' => 'The smugglers stair']),
        // Revealed, about a faction the party may not open. The dial shows; the link
        // does not.
        'linked to a secret' => Clock::factory()->about($court)->shownToPlayers()->sized(4)->filled(1)
            ->create(['name' => 'The tally of debts']),
        // Revealed, about a faction they know. Both halves show.
        'linked openly' => Clock::factory()->about($choir)->shownToPlayers()->sized(12)->filled(9)
            ->create(['name' => 'The ritual']),
    ];

    return [$campaign, $player, $clocks];
}

it('keeps a hidden clock off a player screen, markup and snapshot alike', function () {
    [$campaign, $player] = clocksOnTheTable();

    $component = Livewire::actingAs($player)
        ->test(Panel::class, ['campaign' => $campaign])
        ->assertOk()
        ->assertSee('The drowning tide')
        ->assertDontSee('The smugglers stair');

    expect(json_encode($component->snapshot, JSON_THROW_ON_ERROR))
        ->not->toContain('The smugglers stair');
});

it('shows a revealed clock and hides the thing it is about', function () {
    [$campaign, $player] = clocksOnTheTable();

    // The eye said yes, so the dial is the GM's decision and it stands. The duke's
    // page is a separate decision, and it also stands.
    $component = Livewire::actingAs($player)
        ->test(Panel::class, ['campaign' => $campaign])
        ->assertSee('The tally of debts')
        ->assertDontSee('The Drowned Court')
        ->assertSee('The Ashen Choir');

    expect(json_encode($component->snapshot, JSON_THROW_ON_ERROR))
        ->not->toContain('The Drowned Court');
});

it('never loads a hidden clock at all', function () {
    [$campaign, $player] = clocksOnTheTable();

    // The filter is in the query, not the Blade: a hidden row is never in memory, so
    // there is nothing for a template edit to expose later.
    DB::enableQueryLog();

    Livewire::actingAs($player)->test(Panel::class, ['campaign' => $campaign]);

    $clockQueries = collect(DB::getRawQueryLog())
        ->filter(fn (array $query) => str_contains($query['raw_query'], 'from "clocks"'));

    expect($clockQueries)->not->toBeEmpty()
        ->and($clockQueries->every(fn (array $query) => str_contains($query['raw_query'], 'player_visible')))
        ->toBeTrue();

    DB::disableQueryLog();
});

it('gives the GM every clock and says which the party can see', function () {
    [$campaign] = clocksOnTheTable();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Panel::class, ['campaign' => $campaign])
        ->assertSee('The drowning tide')
        ->assertSee('The smugglers stair')
        ->assertSee('The Drowned Court')
        ->assertSee('Hide The drowning tide on the player table')
        ->assertSee('Show The smugglers stair on the player table');
});

it('reveals a clock onto the open table screens and takes it back again', function () {
    [$campaign, $player, $clocks] = clocksOnTheTable();

    $hidden = $clocks['hidden'];

    Livewire::actingAs(ownerOf($campaign))
        ->test(Panel::class, ['campaign' => $campaign])
        ->call('toggleVisibility', $hidden->id);

    expect($hidden->refresh()->player_visible)->toBeTrue();

    Livewire::actingAs($player)
        ->test(Panel::class, ['campaign' => $campaign])
        ->assertSee('The smugglers stair');

    Livewire::actingAs(ownerOf($campaign))
        ->test(Panel::class, ['campaign' => $campaign])
        ->call('toggleVisibility', $hidden->id);

    Livewire::actingAs($player)
        ->test(Panel::class, ['campaign' => $campaign])
        ->assertDontSee('The smugglers stair');
});

it('keeps a player out of the eye', function () {
    [$campaign, $player, $clocks] = clocksOnTheTable();

    Livewire::actingAs($player)
        ->test(Panel::class, ['campaign' => $campaign])
        ->call('toggleVisibility', $clocks['hidden']->id)
        ->assertForbidden();

    expect($clocks['hidden']->refresh()->player_visible)->toBeFalse();
});

it('puts the revealed clocks on the party table screen and nothing else', function () {
    [$campaign, $player] = clocksOnTheTable();

    $this->actingAs($player)
        ->get(route('table', $campaign))
        ->assertOk()
        ->assertSee('The drowning tide')
        ->assertSee('The ritual')
        ->assertDontSee('The smugglers stair')
        ->assertDontSee('The Drowned Court');
});

it('leaves the clocks card off a table screen with nothing to put in it', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    // Hidden, so this player has nothing to see. A campaign that never reveals a clock
    // should not carry a card that says so for the rest of its life.
    Clock::factory()->inCampaign($campaign)->create(['name' => 'The smugglers stair']);

    Livewire::actingAs($player)
        ->test(Show::class, ['campaign' => $campaign])
        ->assertSet('campaign.id', $campaign->id)
        ->assertDontSee('What is coming')
        ->assertDontSee('The smugglers stair');
});

it('brings the clocks card back the moment a GM reveals one', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $clock = Clock::factory()->inCampaign($campaign)->create(['name' => 'The smugglers stair']);

    app(SetClockVisibility::class)->handle($clock, true);

    Livewire::actingAs($player)
        ->test(Show::class, ['campaign' => $campaign])
        ->assertSee('What is coming');
});

it('treats a spectator exactly as it treats a player', function () {
    [$campaign] = clocksOnTheTable();
    $spectator = memberOf($campaign, CampaignRole::Spectator);

    Livewire::actingAs($spectator)
        ->test(Panel::class, ['campaign' => $campaign])
        ->assertSee('The drowning tide')
        ->assertDontSee('The smugglers stair')
        ->assertDontSee('The Drowned Court');
});
