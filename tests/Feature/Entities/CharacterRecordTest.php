<?php

use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Livewire\Entities\Form;
use App\Models\Campaign;
use App\Models\Entity;
use Livewire\Livewire;

it('lets a GM set a class, a level, and a sheet link on any character', function () {
    $campaign = Campaign::factory()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'characters'])
        ->set('name', 'Sister Vale')
        ->set('character_class', 'Cleric')
        ->set('level', 7)
        ->set('sheet_url', 'https://www.dndbeyond.com/characters/12345')
        ->call('save')
        ->assertHasNoErrors();

    expect($campaign->entities()->firstOrFail())
        ->character_class->toBe('Cleric')
        ->level->toBe(7)
        ->sheet_url->toBe('https://www.dndbeyond.com/characters/12345');
});

it('lets a player set the record on their own PC', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $pc = Entity::factory()->for($campaign)->pcOf($player)->create(['slug' => 'wren']);

    Livewire::actingAs($player)
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'characters', 'slug' => 'wren'])
        ->set('character_class', 'Rogue')
        ->set('level', 5)
        ->set('sheet_url', 'https://example.test/wren')
        ->call('save')
        ->assertHasNoErrors();

    expect($pc->fresh())
        ->character_class->toBe('Rogue')
        ->level->toBe(5)
        ->sheet_url->toBe('https://example.test/wren');
});

it('keeps a player away from another player\'s record', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $other = memberOf($campaign, CampaignRole::Player);
    Entity::factory()->for($campaign)->pcOf($other)->forPlayers()->create(['slug' => 'tobin']);

    $this->actingAs($player)
        ->get(route('entities.edit', [$campaign, 'characters', 'tobin']))
        ->assertForbidden();
});

it('refuses a javascript sheet link and writes nothing', function () {
    $campaign = Campaign::factory()->create();
    $pc = Entity::factory()->for($campaign)->type(EntityType::Character)->create(['slug' => 'wren']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'characters', 'slug' => 'wren'])
        ->set('sheet_url', 'javascript:alert(document.cookie)')
        ->call('save')
        ->assertHasErrors(['sheet_url' => 'url']);

    expect($pc->fresh()->sheet_url)->toBeNull();
});

it('takes http and https and nothing else', function (string $url, bool $valid) {
    $campaign = Campaign::factory()->create();
    Entity::factory()->for($campaign)->type(EntityType::Character)->create(['slug' => 'wren']);

    $component = Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'characters', 'slug' => 'wren'])
        ->set('sheet_url', $url)
        ->call('save');

    $valid ? $component->assertHasNoErrors() : $component->assertHasErrors(['sheet_url']);
})->with([
    ['https://example.test/sheet', true],
    ['http://example.test/sheet', true],
    ['', true],
    ['javascript:alert(1)', false],
    ['data:text/html,<script>alert(1)</script>', false],
    ['not a url at all', false],
]);

it('renders the sheet link with a safe rel and the host as its label', function () {
    $campaign = Campaign::factory()->create();
    Entity::factory()->for($campaign)
        ->withRecord('Bard', 5, 'https://www.dndbeyond.com/characters/99')
        ->create(['name' => 'Wren', 'slug' => 'wren']);

    $this->actingAs(ownerOf($campaign))
        ->get(route('entities.show', [$campaign, 'characters', 'wren']))
        ->assertOk()
        ->assertSee('rel="noopener noreferrer nofollow"', false)
        ->assertSee('target="_blank"', false)
        ->assertSee('dndbeyond.com')
        ->assertSee('Bard')
        ->assertSee('5');
});

it('renders no record row for a character with nothing recorded', function () {
    $campaign = Campaign::factory()->create();
    Entity::factory()->for($campaign)->type(EntityType::Character)->create(['name' => 'Nobody', 'slug' => 'nobody']);

    $this->actingAs(ownerOf($campaign))
        ->get(route('entities.show', [$campaign, 'characters', 'nobody']))
        ->assertOk()
        ->assertDontSee('>Class<', false)
        ->assertDontSee('>Level<', false);
});

it('prohibits the character fields on every other type', function (EntityType $type) {
    $campaign = Campaign::factory()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => $type->slug()])
        ->set('name', 'Not a character')
        ->set('character_class', 'Bard')
        ->set('level', 5)
        ->set('sheet_url', 'https://example.test/sheet')
        ->call('save')
        ->assertHasErrors(['character_class' => 'prohibited', 'level' => 'prohibited', 'sheet_url' => 'prohibited']);

    expect(Entity::count())->toBe(0);
})->with(fn () => collect(EntityType::cases())
    ->reject(fn (EntityType $type) => $type === EntityType::Character)
    ->mapWithKeys(fn (EntityType $type) => [$type->value => $type])
    ->all());

it('takes an empty class, an empty level, and an empty sheet link', function () {
    $campaign = Campaign::factory()->create();
    $pc = Entity::factory()->for($campaign)->withRecord()->create(['slug' => 'wren']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'characters', 'slug' => 'wren'])
        ->set('character_class', '')
        ->set('level', null)
        ->set('sheet_url', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($pc->fresh())
        ->character_class->toBeNull()
        ->level->toBeNull()
        ->sheet_url->toBeNull();
});

it('keeps the level between 1 and 100', function (?int $level, bool $valid) {
    $campaign = Campaign::factory()->create();
    Entity::factory()->for($campaign)->type(EntityType::Character)->create(['slug' => 'wren']);

    $component = Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'characters', 'slug' => 'wren'])
        ->set('level', $level)
        ->call('save');

    $valid ? $component->assertHasNoErrors() : $component->assertHasErrors(['level']);
})->with([
    [1, true],
    [100, true],
    [0, false],
    [101, false],
    [null, true],
]);

it('puts the class in the search index and keeps the level out', function () {
    $campaign = Campaign::factory()->create();
    $pc = Entity::factory()->for($campaign)->withRecord('Warlock', 3)->create();

    $searchable = $pc->toSearchableArray();

    expect($searchable)->toHaveKey('character_class')
        ->and($searchable['character_class'])->toBe('Warlock')
        ->and($searchable)->not->toHaveKey('level');
});
