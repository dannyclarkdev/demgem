<?php

use App\Enums\EntityType;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\Mention;

it('records resolved and unresolved mentions from the body and GM notes', function () {
    $campaign = Campaign::factory()->create();
    $mara = Entity::factory()->for($campaign)->create(['name' => 'Mara Voss']);
    $source = Entity::factory()->for($campaign)->create([
        'body' => 'Ally of [[Mara Voss]]. Wants the [[item:Signet]]. Also [[mara voss]] again.',
        'dm_notes' => 'Secretly serves [[The Duke]].',
    ]);

    $mentions = Mention::withoutGlobalScopes()->where('source_id', $source->id)->get();

    expect($mentions)->toHaveCount(3)
        ->and($mentions->firstWhere('target_name', 'Mara Voss'))->target_entity_id->toBe($mara->id)->source_field->toBe('body')
        ->and($mentions->firstWhere('target_name', 'Signet'))->target_entity_id->toBeNull()->target_type->toBe(EntityType::Item->value)
        ->and($mentions->firstWhere('target_name', 'The Duke'))->target_entity_id->toBeNull()->source_field->toBe('dm_notes');
});

it('rebuilds mentions when the body changes and removes stale ones', function () {
    $campaign = Campaign::factory()->create();
    $source = Entity::factory()->for($campaign)->create(['body' => '[[One]] [[Two]]']);

    $source->update(['body' => '[[Two]] [[Three]]']);

    expect(Mention::withoutGlobalScopes()->where('source_id', $source->id)->pluck('target_name')->sort()->values()->all())
        ->toBe(['Three', 'Two']);
});

it('resolves unresolved mentions when the named entity is created', function () {
    $campaign = Campaign::factory()->create();
    $source = Entity::factory()->for($campaign)->create(['body' => 'Meet [[Abbess Corvane]] and [[location:Vell]].']);

    $abbess = Entity::factory()->for($campaign)->create(['name' => 'abbess corvane']);
    $vellAsFaction = Entity::factory()->for($campaign)->type(EntityType::Faction)->create(['name' => 'Vell']);

    $mentions = Mention::withoutGlobalScopes()->where('source_id', $source->id)->get();

    expect($mentions->firstWhere('target_name', 'Abbess Corvane')->target_entity_id)->toBe($abbess->id)
        ->and($mentions->firstWhere('target_name', 'Vell')->target_entity_id)->toBeNull();

    $vell = Entity::factory()->for($campaign)->type(EntityType::Location)->create(['name' => 'Vell', 'slug' => 'vell-2']);

    expect(Mention::withoutGlobalScopes()->where('source_id', $source->id)->firstWhere('target_name', 'Vell')->target_entity_id)->toBe($vell->id);
});

it('unresolves mentions when the target is soft deleted and resolves them again on restore', function () {
    $campaign = Campaign::factory()->create();
    $target = Entity::factory()->for($campaign)->create(['name' => 'Mara Voss']);
    $source = Entity::factory()->for($campaign)->create(['body' => '[[Mara Voss]]']);

    $target->delete();
    expect(Mention::withoutGlobalScopes()->where('source_id', $source->id)->first()->target_entity_id)->toBeNull();

    $target->restore();
    expect(Mention::withoutGlobalScopes()->where('source_id', $source->id)->first()->target_entity_id)->toBe($target->id);
});
