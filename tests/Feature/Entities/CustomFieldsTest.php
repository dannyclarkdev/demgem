<?php

use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Livewire\Entities\Form;
use App\Models\Campaign;
use App\Models\Entity;
use Livewire\Livewire;

it('saves pairs in the order the GM typed them', function () {
    $campaign = Campaign::factory()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'characters'])
        ->set('name', 'Ilma')
        ->call('addCustomField')
        ->set('custom_fields.0.key', 'Race')
        ->set('custom_fields.0.value', 'Tiefling')
        ->call('addCustomField')
        ->set('custom_fields.1.key', 'Patron')
        ->set('custom_fields.1.value', 'The Drowned Duke')
        ->call('save')
        ->assertHasNoErrors();

    expect($campaign->entities()->firstOrFail()->customFields())->toBe([
        ['key' => 'Race', 'value' => 'Tiefling'],
        ['key' => 'Patron', 'value' => 'The Drowned Duke'],
    ]);
});

it('renders the pairs on the entity page', function () {
    $campaign = Campaign::factory()->create();
    Entity::factory()->for($campaign)->create([
        'name' => 'Ilma',
        'slug' => 'ilma',
        'custom_fields' => [['key' => 'Race', 'value' => 'Tiefling']],
    ]);

    $this->actingAs(ownerOf($campaign))
        ->get(route('entities.show', [$campaign, 'characters', 'ilma']))
        ->assertOk()
        ->assertSee('Race')
        ->assertSee('Tiefling');
});

it('drops a row with no key and keeps a key with no value', function () {
    $campaign = Campaign::factory()->create();
    $entity = Entity::factory()->for($campaign)->create(['slug' => 'ilma']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'characters', 'slug' => 'ilma'])
        ->set('custom_fields', [
            ['key' => 'Race', 'value' => 'Tiefling'],
            ['key' => '', 'value' => 'orphaned'],
            ['key' => 'Sworn to', 'value' => ''],
        ])
        ->call('save')
        ->assertHasNoErrors();

    expect($entity->fresh()->customFields())->toBe([
        ['key' => 'Race', 'value' => 'Tiefling'],
        ['key' => 'Sworn to', 'value' => ''],
    ]);
});

it('writes null rather than an empty list when every row is empty', function () {
    $campaign = Campaign::factory()->create();
    $entity = Entity::factory()->for($campaign)->create([
        'slug' => 'ilma',
        'custom_fields' => [['key' => 'Race', 'value' => 'Tiefling']],
    ]);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'characters', 'slug' => 'ilma'])
        ->set('custom_fields', [['key' => '', 'value' => '']])
        ->call('save')
        ->assertHasNoErrors();

    expect($entity->fresh()->custom_fields)->toBeNull()
        ->and($entity->fresh()->customFields())->toBe([]);
});

it('trims the pairs and strips control characters', function () {
    $campaign = Campaign::factory()->create();
    $entity = Entity::factory()->for($campaign)->create(['slug' => 'ilma']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'characters', 'slug' => 'ilma'])
        ->set('custom_fields', [['key' => "  Race\t", 'value' => "  Tief\x00ling  "]])
        ->call('save')
        ->assertHasNoErrors();

    expect($entity->fresh()->customFields())->toBe([['key' => 'Race', 'value' => 'Tiefling']]);
});

it('caps the pairs, the key, and the value', function () {
    $campaign = Campaign::factory()->create();
    Entity::factory()->for($campaign)->create(['slug' => 'ilma']);

    $component = Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'characters', 'slug' => 'ilma']);

    $component->set('custom_fields', array_fill(0, 21, ['key' => 'k', 'value' => 'v']))
        ->call('save')
        ->assertHasErrors(['custom_fields']);

    $component->set('custom_fields', [['key' => str_repeat('k', 41), 'value' => 'v']])
        ->call('save')
        ->assertHasErrors(['custom_fields.0.key']);

    $component->set('custom_fields', [['key' => 'k', 'value' => str_repeat('v', 201)]])
        ->call('save')
        ->assertHasErrors(['custom_fields.0.value']);
});

it('stops the editor adding a twenty-first row', function () {
    $campaign = Campaign::factory()->create();

    $component = Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'characters'])
        ->set('custom_fields', array_fill(0, 20, ['key' => 'k', 'value' => 'v']))
        ->call('addCustomField');

    expect($component->get('custom_fields'))->toHaveCount(20);
});

it('removes a row and closes the gap', function () {
    $campaign = Campaign::factory()->create();

    $component = Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'characters'])
        ->set('custom_fields', [
            ['key' => 'One', 'value' => '1'],
            ['key' => 'Two', 'value' => '2'],
            ['key' => 'Three', 'value' => '3'],
        ])
        ->call('removeCustomField', 1);

    expect($component->get('custom_fields'))->toBe([
        ['key' => 'One', 'value' => '1'],
        ['key' => 'Three', 'value' => '3'],
    ]);
});

it('renders a value as text, never as markup', function () {
    $campaign = Campaign::factory()->create();
    Entity::factory()->for($campaign)->create([
        'slug' => 'ilma',
        'custom_fields' => [['key' => 'Motto', 'value' => '<script>alert(1)</script>']],
    ]);

    $response = $this->actingAs(ownerOf($campaign))
        ->get(route('entities.show', [$campaign, 'characters', 'ilma']))
        ->assertOk();

    expect($response->getContent())->not->toContain('<script>alert(1)</script>')
        ->and($response->getContent())->toContain('&lt;script&gt;');
});

it('finds an entity by a custom field value', function () {
    $campaign = Campaign::factory()->create();
    Entity::factory()->for($campaign)->forPlayers()->create([
        'name' => 'Ilma',
        'body' => 'Keeps a raven.',
        'custom_fields' => [['key' => 'Race', 'value' => 'Tiefling']],
    ]);
    Entity::factory()->for($campaign)->forPlayers()->create(['name' => 'Coll', 'body' => 'Runs the docks.']);

    $this->actingAs(ownerOf($campaign))
        ->get(route('search', [$campaign, 'q' => 'tiefling']))
        ->assertOk()
        ->assertSee('Ilma')
        ->assertDontSee('Coll');
});

it('lets a player set fields on their own PC', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $pc = Entity::factory()->for($campaign)->pcOf($player)->create(['slug' => 'wren']);

    Livewire::actingAs($player)
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'characters', 'slug' => 'wren'])
        ->set('custom_fields', [['key' => 'Background', 'value' => 'Urchin']])
        ->call('save')
        ->assertHasNoErrors();

    expect($pc->fresh()->customFields())->toBe([['key' => 'Background', 'value' => 'Urchin']]);
});

it('carries the fields on every entity type', function (EntityType $type) {
    $campaign = Campaign::factory()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => $type->slug()])
        ->set('name', 'A thing')
        ->set('custom_fields', [['key' => 'Notable', 'value' => 'Yes']])
        ->call('save')
        ->assertHasNoErrors();

    expect($campaign->entities()->firstOrFail()->customFields())->toBe([['key' => 'Notable', 'value' => 'Yes']]);
})->with(fn () => collect(EntityType::cases())->mapWithKeys(fn (EntityType $t) => [$t->value => $t])->all());
