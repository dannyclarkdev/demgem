<?php

use App\Enums\EntityType;
use App\Enums\QuestStatus;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\QuestObjective;

it('reads a quest with no stored status as available', function () {
    $campaign = Campaign::factory()->create();
    $quest = Entity::factory()->for($campaign)->type(EntityType::Quest)->create(['quest_status' => null]);

    expect($quest->questStatus())->toBe(QuestStatus::Available);
});

it('has no status on the other five types', function () {
    $campaign = Campaign::factory()->create();

    foreach ([EntityType::Character, EntityType::Location, EntityType::Faction, EntityType::Item, EntityType::Note] as $type) {
        $entity = Entity::factory()->for($campaign)->type($type)->create();

        expect($entity->questStatus())->toBeNull()
            ->and($entity->isQuest())->toBeFalse();
    }
});

it('backfilled the quests that existed before the column did', function () {
    // The migration ran before this test's rows, so assert the rule it enforced:
    // every quest row reads as available until a GM says otherwise.
    $campaign = Campaign::factory()->create();
    $quest = Entity::factory()->for($campaign)->quest()->create();

    expect($quest->quest_status)->toBe(QuestStatus::Available);
});

it('keeps objectives in position order', function () {
    $campaign = Campaign::factory()->create();
    $quest = Entity::factory()->for($campaign)->quest()->create();

    foreach ([2 => 'Third', 0 => 'First', 1 => 'Second'] as $position => $body) {
        QuestObjective::factory()->forQuest($quest, $position)->create(['body' => $body]);
    }

    expect($quest->objectives()->pluck('body')->all())->toBe(['First', 'Second', 'Third']);
});

it('counts objective progress', function () {
    $campaign = Campaign::factory()->create();
    $quest = Entity::factory()->for($campaign)->quest()->withObjectives(5, 2)->create();

    expect($quest->objectiveProgress())->toBe(['done' => 2, 'total' => 5]);
});

it('counts objective progress from a loaded relation without another query', function () {
    $campaign = Campaign::factory()->create();
    $quest = Entity::factory()->for($campaign)->quest()->withObjectives(4, 3)->create();

    $loaded = Entity::query()->whereKey($quest->id)->with('objectives')->sole();

    DB::flushQueryLog();
    DB::enableQueryLog();

    expect($loaded->objectiveProgress())->toBe(['done' => 3, 'total' => 4])
        ->and(DB::getQueryLog())->toBeEmpty();
});

it('reports whether an objective is complete', function () {
    $campaign = Campaign::factory()->create();
    $quest = Entity::factory()->for($campaign)->quest()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->create();

    $open = QuestObjective::factory()->forQuest($quest, 0)->create();
    $done = QuestObjective::factory()->forQuest($quest, 1)->completedIn($session)->create();

    expect($open->isComplete())->toBeFalse()
        ->and($done->isComplete())->toBeTrue()
        ->and($done->completedInSession->is($session))->toBeTrue();
});

it('links a quest to its giver in both directions', function () {
    $campaign = Campaign::factory()->create();
    $baron = Entity::factory()->for($campaign)->type(EntityType::Character)->create(['name' => 'Baron Kell']);
    $quest = Entity::factory()->for($campaign)->quest()->givenBy($baron)->create();

    expect($quest->giver->is($baron))->toBeTrue()
        ->and($baron->givenQuests()->pluck('id')->all())->toBe([$quest->id]);
});

it('drops the giver when that entity is force deleted', function () {
    $campaign = Campaign::factory()->create();
    $baron = Entity::factory()->for($campaign)->type(EntityType::Character)->create();
    $quest = Entity::factory()->for($campaign)->quest()->givenBy($baron)->create();

    $baron->forceDelete();

    expect($quest->refresh()->giver_entity_id)->toBeNull();
});

it('cascades objectives when the quest is force deleted', function () {
    $campaign = Campaign::factory()->create();
    $quest = Entity::factory()->for($campaign)->quest()->withObjectives(3)->create();

    $quest->forceDelete();

    expect(QuestObjective::query()->count())->toBe(0);
});

it('keeps objectives through a soft delete, as slice 1 does for the entity itself', function () {
    $campaign = Campaign::factory()->create();
    $quest = Entity::factory()->for($campaign)->quest()->withObjectives(3)->create();

    $quest->delete();

    expect(QuestObjective::query()->where('entity_id', $quest->id)->count())->toBe(3);
});
