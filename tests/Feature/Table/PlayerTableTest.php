<?php

use App\Actions\Encounters\NextTurn;
use App\Actions\Encounters\SetPlayerVisibility;
use App\Enums\CampaignRole;
use App\Livewire\Table\Fight;
use App\Livewire\Table\Show;
use App\Models\Campaign;
use App\Models\Combatant;
use App\Models\Encounter;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * /table is the one page a player keeps open during a game. These tests are about
 * what it says; CombatantVisibilityTest is about what it must never say.
 *
 * A campaign with one fight in play: the party is on the screen, the ogre is not
 * until the GM says so.
 */
function tableWorld(): array
{
    $campaign = Campaign::factory()->create(['name' => 'The Drowned Duchy']);
    $player = memberOf($campaign, CampaignRole::Player);

    $pc = Entity::factory()->for($campaign)->pcOf($player)->forPlayers()
        ->create(['name' => 'Wren Aldercross', 'slug' => 'wren']);

    $encounter = Encounter::factory()->for($campaign)->active(3)->create(['name' => 'Ambush at the ford']);

    $wren = Combatant::factory()->inEncounter($encounter, 0)->forEntity($pc)->shownToPlayers()
        ->withHealth(24, 30)->create();

    $ogre = Combatant::factory()->inEncounter($encounter, 1)->shownToPlayers()->withHealth(9, 60)
        ->create(['name' => 'Ogre chieftain', 'ac' => 17, 'initiative_bonus' => 4, 'conditions' => ['Prone']]);

    return [$campaign, $player, compact('encounter', 'pc', 'wren', 'ogre')];
}

it('shows the turn order, the round, and whose turn it is', function () {
    [$campaign, $player, $world] = tableWorld();

    $world['encounter']->update(['active_combatant_id' => $world['ogre']->id]);

    $this->actingAs($player)->get(route('table', $campaign))
        ->assertOk()
        ->assertSee('Round 3')
        ->assertSee('Wren Aldercross')
        ->assertSee('Ogre chieftain')
        ->assertSee('is up')
        ->assertSee('Prone');
});

it('marks a player their own character, and nobody else theirs', function () {
    [$campaign, $player, $world] = tableWorld();
    $otherPlayer = memberOf($campaign, CampaignRole::Player);

    $render = fn (User $viewer): string => Livewire::actingAs($viewer)
        ->test(Fight::class, ['campaign' => $campaign, 'encounterId' => $world['encounter']->id])
        ->assertOk()
        ->html();

    $yours = $render($player);

    // The marker sits inside the row it belongs to, so the order of the three proves
    // it landed on Wren and not on the ogre.
    expect($yours)->toContain('Wren Aldercross')
        ->and(mb_strpos($yours, 'You'))->toBeGreaterThan(mb_strpos($yours, 'Wren Aldercross'))
        ->and(mb_strpos($yours, 'You'))->toBeLessThan(mb_strpos($yours, 'Ogre chieftain'))
        ->and($render($otherPlayer))->not->toContain('You');
});

it('reads health as a word, never as a number', function (int $hp, int $maxHp, ?string $word) {
    $combatant = Combatant::factory()->make(['hp' => $hp, 'max_hp' => $maxHp]);

    expect($combatant->healthWord())->toBe($word);
})->with([
    'full' => [30, 30, 'Unhurt'],
    'a scratch' => [29, 30, 'Hurt'],
    'half' => [15, 30, 'Hurt'],
    'a quarter left' => [7, 30, 'Badly hurt'],
    'one point' => [1, 30, 'Badly hurt'],
    'nothing left' => [0, 30, 'Down'],
]);

it('says nothing about health the GM is not tracking', function () {
    expect(Combatant::factory()->make(['hp' => null, 'max_hp' => null])->healthWord())->toBeNull();

    // A current value with no maximum has no fraction to read, so there is no honest
    // word for it. Falling to zero is the exception: that needs no maximum.
    expect(Combatant::factory()->make(['hp' => 12, 'max_hp' => null])->healthWord())->toBeNull()
        ->and(Combatant::factory()->make(['hp' => 0, 'max_hp' => null])->healthWord())->toBe('Down');
});

it('shows the party a word for the ogre and never the number', function () {
    [$campaign, $player, $world] = tableWorld();

    $html = Livewire::actingAs($player)
        ->test(Fight::class, ['campaign' => $campaign, 'encounterId' => $world['encounter']->id])
        ->html();

    expect($html)->toContain('Badly hurt')
        ->and($html)->toContain('Hurt')
        ->and($html)->not->toContain('9/60')
        ->and($html)->not->toContain('24/30');
});

it('follows the GM when a combatant is revealed mid-fight', function () {
    [$campaign, $player, $world] = tableWorld();

    $hidden = Combatant::factory()->inEncounter($world['encounter'], 2)->create(['name' => 'Cellar lurker']);

    $component = Livewire::actingAs($player)
        ->test(Fight::class, ['campaign' => $campaign, 'encounterId' => $world['encounter']->id])
        ->assertDontSee('Cellar lurker');

    app(SetPlayerVisibility::class)->toggle($hidden);

    $component->call('encounterChanged', ['encounterId' => $world['encounter']->id])
        ->assertSee('Cellar lurker');
});

it('names no hidden combatant when it is that combatant taking its turn', function () {
    [$campaign, $player, $world] = tableWorld();

    $hidden = Combatant::factory()->inEncounter($world['encounter'], 2)->create(['name' => 'Cellar lurker']);
    $world['encounter']->update(['active_combatant_id' => $hidden->id]);

    Livewire::actingAs($player)
        ->test(Fight::class, ['campaign' => $campaign, 'encounterId' => $world['encounter']->id])
        ->assertSee('something you cannot see is taking its turn')
        ->assertDontSee('Cellar lurker');
});

it('drops the fight from the page when the GM ends it', function () {
    [$campaign, $player, $world] = tableWorld();

    $this->actingAs($player)->get(route('table', $campaign))->assertSee('Ogre chieftain');

    app(NextTurn::class)->end($world['encounter']);

    $this->actingAs($player)->get(route('table', $campaign))
        ->assertOk()
        ->assertSee('No fight running')
        ->assertDontSee('Ogre chieftain');
});

it('is worth opening with no fight running', function () {
    [$campaign, $player] = tableWorld();

    Encounter::query()->delete();

    GameSession::factory()->for($campaign)->number(1)->published('The party burned the bridge.')
        ->create(['title' => 'The Toll']);

    $this->actingAs($player)->get(route('table', $campaign))
        ->assertOk()
        ->assertSee('No fight running')
        ->assertSee('Wren Aldercross')
        ->assertSee('The party burned the bridge.');
});

it('opens to every member, and to nobody else', function () {
    [$campaign] = tableWorld();

    foreach (CampaignRole::cases() as $role) {
        $member = $role === CampaignRole::Owner ? ownerOf($campaign) : memberOf($campaign, $role);

        $this->actingAs($member)->get(route('table', $campaign))->assertOk();
    }

    $this->actingAs(User::factory()->create())->get(route('table', $campaign))->assertNotFound();
});

it('offers every member the link in the sidebar', function () {
    [$campaign, $player] = tableWorld();

    $this->actingAs($player)->get(route('campaigns.show', $campaign))
        ->assertOk()
        ->assertSee(route('table', $campaign), false)
        ->assertSee('The table');
});

it('keeps the poll as a backstop, at a minute', function () {
    [$campaign, $player, $world] = tableWorld();

    $this->actingAs($player)->get(route('table', $campaign))
        ->assertSee('wire:poll.visible.'.Show::POLL_SECONDS.'s', false)
        ->assertSee('wire:poll.visible.'.Fight::POLL_SECONDS.'s', false);
});

it('costs the same number of queries whatever the fight holds', function () {
    [$campaign, $player, $world] = tableWorld();

    $count = fn (): int => countQueriesForFight($player, $campaign, $world['encounter']);

    // One warm-up first: Campaign caches its member lookup per instance.
    $count();
    $small = $count();

    Combatant::factory()->count(30)->inEncounter($world['encounter'], 5)->shownToPlayers()
        ->forEntity(Entity::factory()->for($campaign)->create())->create();

    expect($count())->toBe($small);
});

function countQueriesForFight(User $viewer, Campaign $campaign, Encounter $encounter): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    Livewire::actingAs($viewer)->test(Fight::class, [
        'campaign' => $campaign,
        'encounterId' => $encounter->id,
    ]);

    $count = count(DB::getQueryLog());

    DB::disableQueryLog();

    return $count;
}
