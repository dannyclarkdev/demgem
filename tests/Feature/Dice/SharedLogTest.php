<?php

use App\Actions\Dice\RollDice;
use App\Enums\CampaignRole;
use App\Events\DiceRolled;
use App\Exceptions\TooManyRollsException;
use App\Livewire\Dice\Log;
use App\Livewire\Dice\Tray;
use App\Models\Campaign;
use App\Models\DiceRoll;
use App\Models\User;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

/**
 * The log is the campaign's from this slice on. These tests are about who reads what
 * in it, and about the two things that would spoil it: a private roll reaching the
 * table, and one person filling the whole log.
 *
 * As everywhere in this slice, the proofs are formulas, labels and names rather than
 * totals. Every id here is a ULID and Crockford base32 carries every digit.
 */
function tableOfRollers(): array
{
    $campaign = Campaign::factory()->create();

    return [$campaign, ownerOf($campaign), memberOf($campaign, CampaignRole::Player)];
}

it('carries a player roll to the GM without anyone reading it out', function () {
    [$campaign, $gm, $player] = tableOfRollers();

    Livewire::actingAs($player)
        ->test(Tray::class, ['campaign' => $campaign])
        ->set('formula', 'd20')
        ->set('label', 'Kicking the door')
        ->call('roll')
        ->assertHasNoErrors();

    Livewire::actingAs($gm)
        ->test(Log::class, ['campaign' => $campaign])
        ->assertSee('Kicking the door')
        ->assertSee($player->name);
});

it('keeps a roll behind the screen out of every other viewer, markup and snapshot alike', function () {
    [$campaign, $gm, $player] = tableOfRollers();
    $coGm = memberOf($campaign, CampaignRole::CoGm);

    Livewire::actingAs($gm)
        ->test(Tray::class, ['campaign' => $campaign])
        ->set('private', true)
        ->set('label', 'Does it notice them')
        ->call('roll');

    expect(DiceRoll::query()->sole()->private)->toBeTrue();

    // The GM who rolled it reads it. Nobody else does, and a co-GM is nobody else:
    // the column is one person's screen, not a rank.
    Livewire::actingAs($gm)
        ->test(Log::class, ['campaign' => $campaign])
        ->assertSee('Does it notice them');

    foreach ([$player, $coGm, memberOf($campaign, CampaignRole::Spectator)] as $other) {
        $component = Livewire::actingAs($other)->test(Log::class, ['campaign' => $campaign]);

        $component->assertDontSee('Does it notice them');

        expect(json_encode($component->snapshot, JSON_THROW_ON_ERROR))
            ->not->toContain('Does it notice them');
    }
});

it('refuses to make a player roll private, whoever asks', function () {
    [$campaign, $gm, $player] = tableOfRollers();

    // Set on the component, which a player's own tray never offers. The action is
    // where the answer lives, so a forged request gets the same one.
    Livewire::actingAs($player)
        ->test(Tray::class, ['campaign' => $campaign])
        ->set('private', true)
        ->set('label', 'Sneaking off')
        ->call('roll');

    expect(DiceRoll::query()->sole()->private)->toBeFalse();

    Livewire::actingAs($gm)
        ->test(Log::class, ['campaign' => $campaign])
        ->assertSee('Sneaking off');
});

it('offers the behind-the-screen toggle to a GM and to nobody else', function () {
    [$campaign, $gm, $player] = tableOfRollers();

    Livewire::actingAs($gm)
        ->test(Tray::class, ['campaign' => $campaign])
        ->assertSee('Behind the screen');

    Livewire::actingAs($player)
        ->test(Tray::class, ['campaign' => $campaign])
        ->assertDontSee('Behind the screen');
});

it('lets a spectator read the log and gives them no tray', function () {
    [$campaign, $gm] = tableOfRollers();
    $spectator = memberOf($campaign, CampaignRole::Spectator);

    app(RollDice::class)->handle($campaign, $gm, 'd20', 'The ogre swings');

    Livewire::actingAs($spectator)
        ->test(Log::class, ['campaign' => $campaign])
        ->assertOk()
        ->assertSee('The ogre swings');

    $this->actingAs($spectator)->get(route('table', $campaign))
        ->assertOk()
        ->assertSee('The ogre swings')
        ->assertDontSee('Advantage');
});

it('puts a tray and the shared log on the table for a player', function () {
    [$campaign, $gm, $player] = tableOfRollers();

    app(RollDice::class)->handle($campaign, $gm, 'd20', 'The ogre swings');

    $this->actingAs($player)->get(route('table', $campaign))
        ->assertOk()
        ->assertSee('Advantage')
        ->assertSee('The ogre swings')
        ->assertDontSee('Behind the screen');
});

it('refuses the thirty-first roll in a minute and writes nothing', function () {
    [$campaign, $gm] = tableOfRollers();

    foreach (range(1, RollDice::PER_MINUTE) as $ignored) {
        app(RollDice::class)->handle($campaign, $gm, 'd20');
    }

    expect(fn () => app(RollDice::class)->handle($campaign, $gm, 'd20'))
        ->toThrow(TooManyRollsException::class);

    expect(DiceRoll::query()->count())->toBe(RollDice::PER_MINUTE);
});

it('counts the limit per person, so one stuck key does not stop the table', function () {
    [$campaign, $gm, $player] = tableOfRollers();

    foreach (range(1, RollDice::PER_MINUTE) as $ignored) {
        app(RollDice::class)->handle($campaign, $gm, 'd20');
    }

    app(RollDice::class)->handle($campaign, $player, 'd20', 'Still rolling');

    expect(DiceRoll::query()->where('label', 'Still rolling')->count())->toBe(1);
});

it('tells the roller how long to wait, in the tray, without breaking the page', function () {
    [$campaign, $gm] = tableOfRollers();

    RateLimiter::increment('dice-roll:'.$gm->id, 60, RollDice::PER_MINUTE);

    Livewire::actingAs($gm)
        ->test(Tray::class, ['campaign' => $campaign])
        ->call('roll')
        ->assertOk()
        ->assertHasErrors('formula');

    expect(DiceRoll::query()->count())->toBe(0);
});

it('says where it broadcasts, what it is called, and carries nothing', function () {
    $event = new DiceRolled('01campaign');

    expect($event)->toBeInstanceOf(ShouldBroadcast::class)
        ->and($event)->toBeInstanceOf(ShouldRescue::class)
        ->and($event->broadcastAs())->toBe('dice.rolled')
        ->and($event->broadcastWith())->toBe([]);

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(PresenceChannel::class)
        ->and($channels[0]->name)->toBe('presence-campaign.01campaign');
});

it('broadcasts a private roll exactly like a public one', function () {
    [$campaign, $gm] = tableOfRollers();

    Event::fake([DiceRolled::class]);

    app(RollDice::class)->handle($campaign, $gm, 'd20', 'Does it notice them', null, true);

    // The event carries the campaign and nothing else, so hiding a roll needs no
    // second channel and no filtered payload. Each screen re-reads under its viewer.
    Event::assertDispatchedTimes(DiceRolled::class, 1);
    Event::assertDispatched(DiceRolled::class, fn (DiceRolled $event) => $event->campaignId === $campaign->id);
});

it('says nothing when a roll is refused', function () {
    [$campaign, $gm] = tableOfRollers();

    Event::fake([DiceRolled::class]);

    expect(fn () => app(RollDice::class)->handle($campaign, $gm, '999d999'))->toThrow(Exception::class);

    Event::assertNotDispatched(DiceRolled::class);
});

it('takes the broadcast and re-renders the log', function () {
    [$campaign, $gm, $player] = tableOfRollers();

    $log = Livewire::actingAs($gm)
        ->test(Log::class, ['campaign' => $campaign])
        ->assertDontSee('Kicking the door');

    app(RollDice::class)->handle($campaign, $player, 'd20', 'Kicking the door');

    $log->call('diceRolled')->assertSee('Kicking the door');
});

it('stops showing the log to a member who was removed mid-session', function () {
    [$campaign, $gm] = tableOfRollers();

    $player = memberOf($campaign, CampaignRole::Player);
    app(RollDice::class)->handle($campaign, $gm, 'd20', 'The ogre swings');

    $log = Livewire::actingAs($player)
        ->test(Log::class, ['campaign' => $campaign])
        ->assertSee('The ogre swings');

    $campaign->members()->where('user_id', $player->id)->delete();
    $campaign->forgetMemberCache();

    $log->call('diceRolled')->assertNotFound();
});

it('never lets one campaign hear another table roll', function () {
    [$campaign, $gm] = tableOfRollers();
    $other = Campaign::factory()->create();

    app(RollDice::class)->handle($other, ownerOf($other), 'd20', 'Somebody else fight');

    Livewire::actingAs($gm)
        ->test(Log::class, ['campaign' => $campaign])
        ->assertDontSee('Somebody else fight');
});

it('keeps the poll as a backstop, at a minute', function () {
    [$campaign, $gm] = tableOfRollers();

    expect(Log::POLL_SECONDS)->toBe(60);

    Livewire::actingAs($gm)
        ->test(Log::class, ['campaign' => $campaign])
        ->assertSeeHtml('wire:poll.visible.'.Log::POLL_SECONDS.'s');
});

it('costs the same number of queries whatever the log holds', function () {
    [$campaign, $gm] = tableOfRollers();

    $count = function () use ($gm, $campaign): int {
        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::actingAs($gm)->test(Log::class, ['campaign' => $campaign]);

        $queries = count(DB::getQueryLog());

        DB::disableQueryLog();

        return $queries;
    };

    DiceRoll::factory()->count(3)->for($campaign)->by($gm)->create();

    // One warm-up first: Campaign caches its member lookup per instance.
    $count();
    $small = $count();

    DiceRoll::factory()->count(40)->for($campaign)->by(User::factory()->create())->create();

    expect($count())->toBe($small);
});
