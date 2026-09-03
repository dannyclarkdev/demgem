<?php

use App\Enums\CampaignRole;
use App\Enums\QuestStatus;
use App\Enums\Visibility;
use App\Livewire\Quests\Objectives;
use App\Livewire\Sessions\Run;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\QuestObjective;
use Livewire\Livewire;

it('records the session when the tick comes from the Run screen', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(7)->create();
    $quest = Entity::factory()->for($campaign)->quest(QuestStatus::Active)->forPlayers()->create();
    $objective = QuestObjective::factory()->forQuest($quest)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Objectives::class, ['campaign' => $campaign, 'quest' => $quest, 'session' => $session])
        ->call('toggle', $objective->id);

    expect($objective->refresh()->completed_in_session_id)->toBe($session->id);
});

it('clears the session when the objective is reopened', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(7)->create();
    $quest = Entity::factory()->for($campaign)->quest(QuestStatus::Active)->create();
    $objective = QuestObjective::factory()->forQuest($quest)->completedIn($session)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Objectives::class, ['campaign' => $campaign, 'quest' => $quest, 'session' => $session])
        ->call('toggle', $objective->id);

    expect($objective->refresh()->completed_at)->toBeNull()
        ->and($objective->completed_in_session_id)->toBeNull();
});

it('shows the session on the quest page when the player can see it', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    $session = GameSession::factory()->for($campaign)->number(7)->create(['visibility' => Visibility::Players]);
    $quest = Entity::factory()->for($campaign)->quest()->forPlayers()->create();
    QuestObjective::factory()->forQuest($quest)->completedIn($session)->create(['body' => 'Cross the bridge']);

    Livewire::actingAs($player)
        ->test(Objectives::class, ['campaign' => $campaign, 'quest' => $quest])
        ->assertSee('Finished in Session 7');
});

it('never names a hidden session on an objective a player can read', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    $draft = GameSession::factory()->for($campaign)->number(13)->create(['visibility' => Visibility::Dm]);
    $quest = Entity::factory()->for($campaign)->quest()->forPlayers()->create();
    QuestObjective::factory()->forQuest($quest)->completedIn($draft)->create(['body' => 'Cross the bridge']);

    $component = Livewire::actingAs($player)
        ->test(Objectives::class, ['campaign' => $campaign, 'quest' => $quest]);

    $component->assertSee('Cross the bridge')
        ->assertDontSee('Finished in')
        ->assertDontSee('Session 13');

    expect(json_encode($component->snapshot))->not->toContain('Session 13');
});

it('shows a GM the hidden session they completed it in', function () {
    $campaign = Campaign::factory()->create();

    $draft = GameSession::factory()->for($campaign)->number(13)->create(['visibility' => Visibility::Dm]);
    $quest = Entity::factory()->for($campaign)->quest()->forPlayers()->create();
    QuestObjective::factory()->forQuest($quest)->completedIn($draft)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Objectives::class, ['campaign' => $campaign, 'quest' => $quest])
        ->assertSee('Finished in Session 13');
});

it('lists active quests on the Run screen and nothing else', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(7)->create();

    Entity::factory()->for($campaign)->quest(QuestStatus::Active)->create(['name' => 'The Toll Bridge']);
    Entity::factory()->for($campaign)->quest(QuestStatus::Completed)->create(['name' => 'The Lost Cat']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Run::class, ['campaign' => $campaign, 'number' => 7])
        ->assertSee('The Toll Bridge')
        ->assertDontSee('The Lost Cat')
        ->assertSee('Ticking one here records Session 7');
});
