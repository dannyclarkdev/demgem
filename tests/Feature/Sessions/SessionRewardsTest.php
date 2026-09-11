<?php

use App\Enums\CampaignRole;
use App\Livewire\Sessions\Form;
use App\Livewire\Sessions\Show;
use App\Livewire\Sessions\Story;
use App\Models\Campaign;
use App\Models\GameSession;
use Livewire\Livewire;

it('lets a GM write the XP and the milestone on the session form', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(2)->played()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'number' => 2])
        ->set('xp_awarded', '450')
        ->set('milestone', 'Reached the Drowned Court')
        ->call('save')
        ->assertHasNoErrors();

    $session->refresh();

    expect($session->xp_awarded)->toBe(450)
        ->and($session->milestone)->toBe('Reached the Drowned Court')
        ->and($session->rewardLine())->toBe('450 XP · Reached the Drowned Court');

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'number' => 2])
        ->set('xp_awarded', '')
        ->set('milestone', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($session->refresh()->hasReward())->toBeFalse();
});

it('refuses an XP award beyond the ceiling', function () {
    $campaign = Campaign::factory()->create();
    GameSession::factory()->for($campaign)->number(2)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'number' => 2])
        ->set('xp_awarded', (string) (GameSession::MAX_XP + 1))
        ->call('save')
        ->assertHasErrors('xp_awarded');
});

it('shows the reward to a player on the session page', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    GameSession::factory()->for($campaign)->number(2)->played()->rewarded(450, 'Reached the Drowned Court')->create();

    Livewire::actingAs($player)
        ->test(Show::class, ['campaign' => $campaign, 'number' => 2])
        ->assertSee('450 XP')
        ->assertSee('Reached the Drowned Court');
});

it('totals the story page over the sessions the viewer may see', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    GameSession::factory()->for($campaign)->number(1)->published('The harbor burned.')->rewarded(300, 'Level 2')->create();
    GameSession::factory()->for($campaign)->number(2)->published('The pilings.')->rewarded(450)->create();
    GameSession::factory()->for($campaign)->number(3)->hidden()->published('The secret night.')->rewarded(1000, 'A milestone the party has not earned')->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Story::class, ['campaign' => $campaign])
        ->assertViewHas('rewards', ['xp' => 1750, 'sessions' => 3, 'milestones' => 2])
        ->assertSee('1,750');

    $component = Livewire::actingAs($player)
        ->test(Story::class, ['campaign' => $campaign])
        ->assertViewHas('rewards', ['xp' => 750, 'sessions' => 2, 'milestones' => 1])
        ->assertSee('750')
        ->assertSee('300 XP · Level 2')
        ->assertSee('450 XP')
        ->assertDontSee('A milestone the party has not earned');

    expect(json_encode($component->snapshot))->not->toContain('A milestone the party has not earned');
});

it('hides the reward strip when no session carries one', function () {
    $campaign = Campaign::factory()->create();
    GameSession::factory()->for($campaign)->number(1)->published()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Story::class, ['campaign' => $campaign])
        ->assertDontSee('XP earned')
        ->assertDontSee('Milestones');
});
