<?php

use App\Enums\CampaignRole;
use App\Livewire\Dice\Log;
use App\Livewire\Dice\Tray;
use App\Models\Campaign;
use App\Models\DiceRoll;
use App\Models\GameSession;
use Livewire\Livewire;

it('rolls a formula and logs it', function () {
    $campaign = Campaign::factory()->create();
    $owner = ownerOf($campaign);

    Livewire::actingAs($owner)
        ->test(Tray::class, ['campaign' => $campaign])
        ->set('formula', '2d6+3')
        ->set('label', 'Goblin attack')
        ->call('roll')
        ->assertHasNoErrors()
        ->assertSet('label', '');

    $roll = DiceRoll::query()->sole();

    expect($roll->formula)->toBe('2d6+3')
        ->and($roll->label)->toBe('Goblin attack')
        ->and($roll->user_id)->toBe($owner->id)
        ->and($roll->campaign_id)->toBe($campaign->id)
        ->and($roll->game_session_id)->toBeNull()
        ->and($roll->total)->toBeGreaterThanOrEqual(5)->toBeLessThanOrEqual(15)
        ->and($roll->faces())->toHaveCount(2);
});

it('records the session when the tray is opened from the Run screen', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(4)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Tray::class, ['campaign' => $campaign, 'session' => $session])
        ->call('roll');

    expect(DiceRoll::query()->sole()->game_session_id)->toBe($session->id);
});

it('rolls a quick die and clears advantage for anything but a d20', function () {
    $campaign = Campaign::factory()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Tray::class, ['campaign' => $campaign])
        ->set('advantage', 'kh')
        ->call('rollQuick', 6)
        ->assertSet('formula', 'd6')
        ->assertSet('advantage', '');

    expect(DiceRoll::query()->sole()->formula)->toBe('1d6');
});

it('turns a d20 into 2d20kh1 with advantage', function () {
    $campaign = Campaign::factory()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Tray::class, ['campaign' => $campaign])
        ->set('formula', 'd20')
        ->set('advantage', 'kh')
        ->call('roll');

    $roll = DiceRoll::query()->sole();

    expect($roll->formula)->toBe('2d20kh1')
        ->and($roll->faces())->toHaveCount(2)
        ->and(collect($roll->faces())->where('dropped', true))->toHaveCount(1);
});

it('turns a d20 into 2d20kl1 with disadvantage', function () {
    $campaign = Campaign::factory()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Tray::class, ['campaign' => $campaign])
        ->set('formula', 'd20')
        ->set('advantage', 'kl')
        ->call('roll');

    expect(DiceRoll::query()->sole()->formula)->toBe('2d20kl1');
});

it('refuses an impossible formula and writes nothing', function () {
    $campaign = Campaign::factory()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Tray::class, ['campaign' => $campaign])
        ->set('formula', '999d999')
        ->call('roll')
        ->assertHasErrors('formula');

    expect(DiceRoll::query()->count())->toBe(0);
});

it('refuses nonsense with a readable message', function () {
    $campaign = Campaign::factory()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Tray::class, ['campaign' => $campaign])
        ->set('formula', 'drop table users')
        ->call('roll')
        ->assertHasErrors('formula');

    expect(DiceRoll::query()->count())->toBe(0);
});

it('shows the most recent rolls and no more', function () {
    $campaign = Campaign::factory()->create();
    $owner = ownerOf($campaign);

    DiceRoll::factory()->count(30)->for($campaign)->by($owner)->create();

    // The log left the tray in slice 5: it is the campaign's now, not this
    // component's, so both screens that show a tray show the same one under it.
    Livewire::actingAs($owner)
        ->test(Log::class, ['campaign' => $campaign])
        ->assertViewHas('rolls', fn ($rolls) => $rolls->count() === Log::LIMIT);
});

it('clears only this user\'s rolls', function () {
    $campaign = Campaign::factory()->create();
    $owner = ownerOf($campaign);
    $coGm = memberOf($campaign, CampaignRole::CoGm);

    DiceRoll::factory()->count(3)->for($campaign)->by($owner)->create();
    DiceRoll::factory()->count(2)->for($campaign)->by($coGm)->create();

    Livewire::actingAs($owner)
        ->test(Log::class, ['campaign' => $campaign])
        ->call('clearLog');

    expect(DiceRoll::query()->count())->toBe(2);
});

it('is closed to a spectator, who is read-only by definition', function () {
    $campaign = Campaign::factory()->create();

    Livewire::actingAs(memberOf($campaign, CampaignRole::Spectator))
        ->test(Tray::class, ['campaign' => $campaign])
        ->assertForbidden();
});

it('opens to a player, because the log is shared now', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    Livewire::actingAs($player)
        ->test(Tray::class, ['campaign' => $campaign])
        ->call('roll')
        ->assertHasNoErrors();

    expect(DiceRoll::query()->sole()->user_id)->toBe($player->id);
});

it('refuses a roll from a co-GM demoted to spectator mid-session', function () {
    $campaign = Campaign::factory()->create();
    $coGm = memberOf($campaign, CampaignRole::CoGm);

    $component = Livewire::actingAs($coGm)->test(Tray::class, ['campaign' => $campaign]);

    $campaign->members()->where('user_id', $coGm->id)->update(['role' => CampaignRole::Spectator]);

    $component->call('roll')->assertForbidden();

    expect(DiceRoll::query()->count())->toBe(0);
});

it('scopes the log to the campaign', function () {
    $campaign = Campaign::factory()->create();
    $other = Campaign::factory()->create();

    DiceRoll::factory()->for($campaign)->by(ownerOf($campaign))->create(['formula' => '1d4']);
    DiceRoll::factory()->for($other)->by(ownerOf($other))->create(['formula' => '1d12']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Log::class, ['campaign' => $campaign])
        ->assertSee('1d4')
        ->assertDontSee('1d12');
});
