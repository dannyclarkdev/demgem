<?php

use App\Models\Campaign;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\Mention;
use App\Models\Scene;

it('records wiki links found in a session as mentions', function () {
    $campaign = Campaign::factory()->create();
    $mara = Entity::factory()->for($campaign)->create(['name' => 'Mara Voss']);

    $session = GameSession::factory()->for($campaign)->create([
        'strong_start' => 'The door opens and [[Mara Voss]] is already sitting there.',
        'dm_notes' => 'Keep [[Mara Voss]] calm.',
    ]);

    $mentions = Mention::query()->where('source_id', $session->id)->get();

    expect($mentions)->toHaveCount(2)
        ->and($mentions->pluck('source_type')->unique()->all())->toBe(['game_session'])
        ->and($mentions->pluck('source_field')->sort()->values()->all())->toBe(['dm_notes', 'strong_start'])
        ->and($mentions->pluck('target_entity_id')->unique()->all())->toBe([$mara->id]);
});

it('records wiki links found in a scene as mentions', function () {
    $campaign = Campaign::factory()->create();
    $inn = Entity::factory()->for($campaign)->create(['name' => 'The Grey Lantern']);
    $session = GameSession::factory()->for($campaign)->create();

    $scene = Scene::factory()->inSession($session)->withNotes('They meet at [[The Grey Lantern]].')->create();

    $mention = Mention::query()->where('source_id', $scene->id)->sole();

    expect($mention->source_type)->toBe('scene')
        ->and($mention->source_field)->toBe('notes')
        ->and($mention->target_entity_id)->toBe($inn->id);
});

it('rewrites links in session and scene prose when an entity is renamed', function () {
    $campaign = Campaign::factory()->create();
    $mara = Entity::factory()->for($campaign)->create(['name' => 'Mara Voss']);
    $session = GameSession::factory()->for($campaign)->create([
        'strong_start' => 'Start with [[Mara Voss|the broker]].',
    ]);
    $scene = Scene::factory()->inSession($session)->withNotes('[[character:Mara Voss]] names a price.')->create();

    $mara->update(['name' => 'Mara Vell']);

    expect($session->refresh()->strong_start)->toBe('Start with [[Mara Vell|the broker]].')
        ->and($scene->refresh()->notes)->toBe('[[character:Mara Vell]] names a price.');
});

it('resolves a session link written before the entity existed', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->create(['recap' => 'They asked about [[The Ashen Court]].']);

    expect(Mention::query()->where('source_id', $session->id)->sole()->target_entity_id)->toBeNull();

    $court = Entity::factory()->for($campaign)->create(['name' => 'The Ashen Court']);

    expect(Mention::query()->where('source_id', $session->id)->sole()->target_entity_id)->toBe($court->id);
});

it('keeps the mention rows of a soft-deleted session', function () {
    $campaign = Campaign::factory()->create();
    Entity::factory()->for($campaign)->create(['name' => 'Mara Voss']);
    $session = GameSession::factory()->for($campaign)->create(['strong_start' => '[[Mara Voss]] waits.']);

    $session->delete();

    expect(Mention::query()->where('source_id', $session->id)->count())->toBe(1);
});

it('removes the mention rows of a session and its scenes on a force delete', function () {
    $campaign = Campaign::factory()->create();
    Entity::factory()->for($campaign)->create(['name' => 'Mara Voss']);
    $session = GameSession::factory()->for($campaign)->create(['strong_start' => '[[Mara Voss]] waits.']);
    $scene = Scene::factory()->inSession($session)->withNotes('[[Mara Voss]] again.')->create();

    $session->forceDelete();

    expect(Mention::query()->where('source_id', $session->id)->count())->toBe(0)
        ->and(Mention::query()->where('source_id', $scene->id)->count())->toBe(0);
});

it('removes the mention rows of a deleted scene', function () {
    $campaign = Campaign::factory()->create();
    Entity::factory()->for($campaign)->create(['name' => 'Mara Voss']);
    $session = GameSession::factory()->for($campaign)->create();
    $scene = Scene::factory()->inSession($session)->withNotes('[[Mara Voss]] waits.')->create();

    $scene->delete();

    expect(Mention::query()->where('source_id', $scene->id)->count())->toBe(0);
});

it('writes no mention rows when a save leaves the links unchanged', function () {
    $campaign = Campaign::factory()->create();
    Entity::factory()->for($campaign)->create(['name' => 'Mara Voss']);
    $session = GameSession::factory()->for($campaign)->create(['strong_start' => '[[Mara Voss]] waits.']);

    $before = Mention::query()->where('source_id', $session->id)->pluck('id')->all();

    $session->update(['live_notes' => 'They argued about the toll for twenty minutes.']);

    expect(Mention::query()->where('source_id', $session->id)->pluck('id')->all())->toBe($before);

    $session->update(['live_notes' => 'Then [[Mara Voss]] paid it.']);

    expect(Mention::query()->where('source_id', $session->id)->count())->toBe(2);
});
