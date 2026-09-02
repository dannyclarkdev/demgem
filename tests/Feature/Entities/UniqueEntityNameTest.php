<?php

use App\Enums\EntityType;
use App\Models\Campaign;
use App\Models\Entity;
use App\Rules\UniqueEntityName;

function nameFails(UniqueEntityName $rule, string $value): bool
{
    $failed = false;
    $rule->validate('name', $value, function () use (&$failed) {
        $failed = true;
    });

    return $failed;
}

it('fails for the same name and type regardless of case and surrounding spaces', function () {
    $campaign = Campaign::factory()->create();
    Entity::factory()->for($campaign)->type(EntityType::Location)->create(['name' => 'Harrowgate']);

    $rule = new UniqueEntityName($campaign->id, EntityType::Location);

    expect(nameFails($rule, 'HARROWGATE'))->toBeTrue()
        ->and(nameFails($rule, '  harrowgate '))->toBeTrue();
});

it('passes for a different type, a different campaign, the entity itself, and a trashed twin', function () {
    $campaign = Campaign::factory()->create();
    $existing = Entity::factory()->for($campaign)->type(EntityType::Location)->create(['name' => 'Harrowgate']);
    Entity::factory()->type(EntityType::Location)->create(['name' => 'Harrowgate']);

    expect(nameFails(new UniqueEntityName($campaign->id, EntityType::Item), 'Harrowgate'))->toBeFalse()
        ->and(nameFails(new UniqueEntityName($campaign->id, EntityType::Location, $existing->id), 'Harrowgate'))->toBeFalse();

    $existing->delete();

    expect(nameFails(new UniqueEntityName($campaign->id, EntityType::Location), 'Harrowgate'))->toBeFalse();
});
