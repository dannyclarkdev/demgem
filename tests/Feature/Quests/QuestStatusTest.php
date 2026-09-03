<?php

use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Enums\QuestStatus;
use App\Livewire\Campaigns\Show as CampaignShow;
use App\Livewire\Entities\Form;
use App\Livewire\Entities\Index;
use App\Models\Campaign;
use App\Models\Entity;
use Livewire\Livewire;

it('creates a quest as available by default', function () {
    $campaign = Campaign::factory()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'quests'])
        ->assertSet('quest_status', QuestStatus::Available->value)
        ->set('name', 'The Toll Bridge')
        ->call('save')
        ->assertHasNoErrors();

    expect(Entity::query()->where('name', 'The Toll Bridge')->sole()->quest_status)->toBe(QuestStatus::Available);
});

it('changes the status and the rewards', function () {
    $campaign = Campaign::factory()->create();
    $quest = Entity::factory()->for($campaign)->quest()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'quests', 'slug' => $quest->slug])
        ->set('quest_status', QuestStatus::Active->value)
        ->set('rewards', '500 gold and a favour.')
        ->call('save')
        ->assertHasNoErrors();

    expect($quest->refresh()->quest_status)->toBe(QuestStatus::Active)
        ->and($quest->rewards)->toBe('500 gold and a favour.');
});

it('rejects a status that is not one of the four', function () {
    $campaign = Campaign::factory()->create();
    $quest = Entity::factory()->for($campaign)->quest()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'quests', 'slug' => $quest->slug])
        ->set('quest_status', 'abandoned')
        ->call('save')
        ->assertHasErrors('quest_status');
});

it('prohibits the quest fields on the other five types', function () {
    $campaign = Campaign::factory()->create();
    $note = Entity::factory()->for($campaign)->type(EntityType::Note)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'notes', 'slug' => $note->slug])
        ->set('quest_status', QuestStatus::Active->value)
        ->call('save')
        ->assertHasErrors('quest_status');

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'notes', 'slug' => $note->slug])
        ->set('rewards', 'Gold')
        ->call('save')
        ->assertHasErrors('rewards');

    expect($note->refresh()->quest_status)->toBeNull()
        ->and($note->rewards)->toBeNull();
});

it('filters the quest log by status', function () {
    $campaign = Campaign::factory()->create();

    Entity::factory()->for($campaign)->quest(QuestStatus::Active)->create(['name' => 'The Toll Bridge']);
    Entity::factory()->for($campaign)->quest(QuestStatus::Completed)->create(['name' => 'The Lost Cat']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Index::class, ['campaign' => $campaign, 'type' => 'quests'])
        ->assertSee('The Toll Bridge')
        ->assertSee('The Lost Cat')
        ->set('questStatus', QuestStatus::Active->value)
        ->assertSee('The Toll Bridge')
        ->assertDontSee('The Lost Cat');
});

it('composes the status filter onto the visibility filter, never around it', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    Entity::factory()->for($campaign)->quest(QuestStatus::Active)->dmOnly()->create(['name' => 'The Secret Errand']);
    Entity::factory()->for($campaign)->quest(QuestStatus::Active)->forPlayers()->create(['name' => 'The Toll Bridge']);

    Livewire::actingAs($player)
        ->test(Index::class, ['campaign' => $campaign, 'type' => 'quests'])
        ->set('questStatus', QuestStatus::Active->value)
        ->assertSee('The Toll Bridge')
        ->assertDontSee('The Secret Errand');
});

it('ignores the status filter on the other five types', function () {
    $campaign = Campaign::factory()->create();
    Entity::factory()->for($campaign)->type(EntityType::Note)->create(['name' => 'House Rules']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Index::class, ['campaign' => $campaign, 'type' => 'notes'])
        ->set('questStatus', QuestStatus::Completed->value)
        ->assertSee('House Rules');
});

it('shows active quests with progress on the dashboard, filtered by visibility', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    Entity::factory()->for($campaign)->quest(QuestStatus::Active)->forPlayers()->withObjectives(4, 3)->create(['name' => 'The Toll Bridge']);
    Entity::factory()->for($campaign)->quest(QuestStatus::Active)->dmOnly()->create(['name' => 'The Secret Errand']);
    Entity::factory()->for($campaign)->quest(QuestStatus::Available)->forPlayers()->create(['name' => 'Not Taken Yet']);

    Livewire::actingAs($player)
        ->test(CampaignShow::class, ['campaign' => $campaign])
        ->assertSee('The Toll Bridge')
        ->assertSee('3 of 4')
        ->assertDontSee('The Secret Errand')
        ->assertDontSee('Not Taken Yet');
});
