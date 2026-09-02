<?php

use App\Enums\EntityType;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\Mention;

it('rewrites every link form in other entities and keeps labels and prefixes', function () {
    $campaign = Campaign::factory()->create();
    $target = Entity::factory()->for($campaign)->type(EntityType::Location)->create(['name' => 'Old Town']);
    $a = Entity::factory()->for($campaign)->create(['body' => 'Go to [[Old Town]] or [[old town|the slums]].']);
    $b = Entity::factory()->for($campaign)->create(['body' => 'See [[location:Old Town]].', 'dm_notes' => 'Hidden: [[ Old Town ]] again.']);

    $target->update(['name' => 'Harrowgate']);

    expect($a->fresh()->body)->toBe('Go to [[Harrowgate]] or [[Harrowgate|the slums]].')
        ->and($b->fresh()->body)->toBe('See [[location:Harrowgate]].')
        ->and($b->fresh()->dm_notes)->toBe('Hidden: [[Harrowgate]] again.')
        ->and(Mention::withoutGlobalScopes()->where('target_entity_id', $target->id)->pluck('target_name')->unique()->all())->toBe(['Harrowgate']);
});

it('rewrites a self mention and keeps the mention rows pointed at the entity', function () {
    $campaign = Campaign::factory()->create();
    $target = Entity::factory()->for($campaign)->create(['name' => 'Mara', 'body' => 'Everyone calls [[Mara]] the commander.']);

    $target->update(['name' => 'Mara Voss']);

    expect($target->fresh()->body)->toBe('Everyone calls [[Mara Voss]] the commander.')
        ->and(Mention::withoutGlobalScopes()->where('source_id', $target->id)->first()->target_entity_id)->toBe($target->id);
});

it('does not touch entities that only mention a different entity with a similar name', function () {
    $campaign = Campaign::factory()->create();
    $target = Entity::factory()->for($campaign)->create(['name' => 'Mara']);
    $other = Entity::factory()->for($campaign)->create(['name' => 'Mara Voss']);
    $source = Entity::factory()->for($campaign)->create(['body' => '[[Mara Voss]] is not [[Mara]].']);

    $target->update(['name' => 'Maren']);

    expect($source->fresh()->body)->toBe('[[Mara Voss]] is not [[Maren]].');
});

it('does not change updated_by on the rewritten sources', function () {
    $campaign = Campaign::factory()->create();
    $target = Entity::factory()->for($campaign)->create(['name' => 'Old']);
    $source = Entity::factory()->for($campaign)->create(['body' => '[[Old]]', 'updated_by' => ownerOf($campaign)->id]);

    $target->update(['name' => 'New']);

    expect($source->fresh()->updated_by)->toBe(ownerOf($campaign)->id);
});
