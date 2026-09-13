<?php

use App\Actions\Campaigns\ImportCampaign;
use App\Actions\Campaigns\ReadCampaignFile;
use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Enums\Kinship;
use App\Livewire\Entities\Relations;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\EntityRelation;
use App\Models\User;
use Livewire\Livewire;

/**
 * The house of Voss, four generations, with one spouse the party has not been told
 * about and one sister who is a GM-only page. The tree is walked from Mara in the
 * middle, and every leak case is read from the player's seat.
 */
function houseOfVoss(): array
{
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    $character = fn (string $name, string $slug) => Entity::factory()->for($campaign)->type(EntityType::Character)->forPlayers()
        ->create(['name' => $name, 'slug' => $slug]);

    $old = $character('Old Corvane', 'old-corvane');
    $abbess = $character('Abbess Corvane', 'abbess-corvane');
    $mara = $character('Mara Voss', 'mara-voss');
    $sten = $character('Sten Voss', 'sten-voss');
    $little = $character('Little Voss', 'little-voss');
    $tide = $character('Tide Voss', 'tide-voss');
    $wren = Entity::factory()->for($campaign)->pcOf($player)->forPlayers()->create(['name' => 'Wren Ashgrove', 'slug' => 'wren-ashgrove']);
    $iselle = Entity::factory()->for($campaign)->type(EntityType::Character)->dmOnly()->create(['name' => 'Iselle Ashgrove', 'slug' => 'iselle-ashgrove']);

    $kin = fn (Entity $source, Entity $target, Kinship $kinship, bool $shown = true) => EntityRelation::factory()
        ->between($source, $target)
        ->create([
            'label' => $kinship->label(),
            'reverse_label' => $kinship->reverse()->label(),
            'kinship' => $kinship,
            'player_visible' => $shown,
        ]);

    // Written from both directions on purpose: the tree reads a row whichever
    // side the GM happened to write it from.
    $kin($old, $abbess, Kinship::Parent);
    $kin($abbess, $mara, Kinship::Parent);
    $kin($mara, $little, Kinship::Parent);
    $kin($tide, $little, Kinship::Child);
    $kin($sten, $mara, Kinship::Spouse, false);
    $kin($iselle, $wren, Kinship::Sibling);

    return compact('campaign', 'player', 'old', 'abbess', 'mara', 'sten', 'little', 'tide', 'wren', 'iselle');
}

function relationsCardOf(Entity $entity, ?User $viewer = null)
{
    return Livewire::actingAs($viewer ?? ownerOf($entity->campaign))
        ->test(Relations::class, ['campaign' => $entity->campaign, 'entity' => $entity]);
}

it('fills the labels from the kinship when the GM leaves them blank, and keeps typed ones', function () {
    ['abbess' => $abbess, 'mara' => $mara, 'old' => $old] = houseOfVoss();

    relationsCardOf($mara)
        ->set('targetId', $old->id)
        ->set('kinship', 'child')
        ->call('relate')
        ->assertHasNoErrors();

    $row = EntityRelation::withoutGlobalScopes()->where('entity_id', $mara->id)->where('target_entity_id', $old->id)->firstOrFail();

    expect($row->kinship)->toBe(Kinship::Child)
        ->and($row->label)->toBe('child of')
        ->and($row->reverse_label)->toBe('parent of');

    relationsCardOf($abbess)
        ->set('targetId', $old->id)
        ->set('kinship', 'child')
        ->set('label', 'youngest daughter of')
        ->set('reverseLabel', 'mother of')
        ->call('relate')
        ->assertHasNoErrors();

    $typed = EntityRelation::withoutGlobalScopes()->where('entity_id', $abbess->id)->where('target_entity_id', $old->id)->firstOrFail();

    expect($typed->kinship)->toBe(Kinship::Child)
        ->and($typed->label)->toBe('youngest daughter of')
        ->and($typed->reverse_label)->toBe('mother of');
});

it('still wants a label when there is no kinship to take one from', function () {
    ['mara' => $mara, 'old' => $old] = houseOfVoss();

    relationsCardOf($mara)
        ->set('targetId', $old->id)
        ->set('kinship', '')
        ->set('label', '')
        ->call('relate')
        ->assertHasErrors('label');
});

it('draws the generations from typed rows, whichever side they were written from', function () {
    ['mara' => $mara] = houseOfVoss();

    relationsCardOf($mara)
        ->assertSee('Family')
        ->assertSeeInOrder([
            'Grandparents', 'Old Corvane',
            'Parents', 'Abbess Corvane',
            'Spouses', 'Sten Voss',
            'Children', 'Little Voss',
            'Grandchildren', 'Tide Voss',
        ])
        ->assertDontSee('Siblings');
});

it('reads the same family from the other end of the rows', function () {
    ['abbess' => $abbess, 'tide' => $tide] = houseOfVoss();

    // The family data rather than the markup, because the target picker under the
    // card names every character in the campaign.
    $names = fn (Entity $entity): array => collect(relationsCardOf($entity)->viewData('family'))
        ->map(fn ($people) => $people->pluck('entity.name')->all())
        ->all();

    expect($names($abbess))->toBe([
        'Parents' => ['Old Corvane'],
        'Children' => ['Mara Voss'],
        'Grandchildren' => ['Little Voss'],
    ])->and($names($tide))->toBe([
        'Grandparents' => ['Mara Voss'],
        'Parents' => ['Little Voss'],
    ]);
});

it('never shows the party a hidden row or a GM-only relative, and marks both for the GM', function () {
    ['campaign' => $campaign, 'player' => $player, 'mara' => $mara, 'wren' => $wren] = houseOfVoss();

    $component = relationsCardOf($mara, $player)
        ->assertSee('Family')
        ->assertSee('Abbess Corvane')
        ->assertSee('Little Voss')
        ->assertDontSee('Sten Voss')
        ->assertDontSee('Spouses');

    expect(json_encode($component->snapshot))->not->toContain('Sten Voss');

    $wrens = relationsCardOf($wren, $player)
        ->assertDontSee('Family')
        ->assertDontSee('Iselle Ashgrove');

    expect(json_encode($wrens->snapshot))->not->toContain('Iselle');

    relationsCardOf($wren, ownerOf($campaign))
        ->assertSeeInOrder(['Family', 'Siblings', 'Iselle Ashgrove'])
        ->assertSee('Hidden');
});

it('draws no family card without a kinship row', function () {
    ['campaign' => $campaign, 'mara' => $mara] = houseOfVoss();

    $duke = Entity::factory()->for($campaign)->type(EntityType::Character)->forPlayers()->create(['name' => 'The Drowned Duke', 'slug' => 'drowned-duke']);
    EntityRelation::factory()->between($duke, $mara)->shownToPlayers()->create(['label' => 'employer of']);

    relationsCardOf($duke)
        ->assertSee('employer of')
        ->assertDontSee('Family');
});

it('travels in the export and the API, and the reader refuses a kinship it does not know', function () {
    ['campaign' => $campaign, 'player' => $player, 'abbess' => $abbess, 'mara' => $mara] = houseOfVoss();

    $document = exportedArray($campaign);
    $abbessRow = collect($document['entities'])->firstWhere('slug', 'abbess-corvane');

    expect(collect($abbessRow['relations'])->firstWhere('target_entity_id', $mara->id)['kinship'])->toBe('parent');

    $copy = app(ImportCampaign::class)->handle(
        app(ReadCampaignFile::class)->handle(json_encode($document, JSON_THROW_ON_ERROR))->document,
        User::factory()->create(),
    );

    $newAbbess = Entity::withoutGlobalScopes()->where('campaign_id', $copy->id)->where('slug', 'abbess-corvane')->firstOrFail();

    expect(EntityRelation::withoutGlobalScopes()->where('entity_id', $newAbbess->id)->firstOrFail()->kinship)->toBe(Kinship::Parent);

    asKey($player)
        ->getJson("/api/v1/campaigns/{$campaign->id}/entities/{$abbess->id}")
        ->assertOk()
        ->assertJsonPath('data.relations.0.kinship', 'parent')
        ->assertJsonPath('data.incoming_relations.0.kinship', 'child');

    $document['entities'] = array_map(function (array $row): array {
        $row['relations'] = array_map(fn (array $relation) => [...$relation, 'kinship' => 'cousin'], $row['relations']);

        return $row;
    }, $document['entities']);

    $read = app(ReadCampaignFile::class)->handle(json_encode($document, JSON_THROW_ON_ERROR));

    expect($read->succeeded())->toBeFalse()
        ->and(implode(' ', $read->errors))->toContain('cousin');
});

it('reads an older file whose relations carry no kinship', function () {
    ['campaign' => $campaign] = houseOfVoss();

    $document = exportedArray($campaign);
    $document['entities'] = array_map(function (array $row): array {
        $row['relations'] = array_map(function (array $relation): array {
            unset($relation['kinship']);

            return $relation;
        }, $row['relations']);

        return $row;
    }, $document['entities']);

    $read = app(ReadCampaignFile::class)->handle(json_encode($document, JSON_THROW_ON_ERROR));

    expect($read->succeeded())->toBeTrue()
        ->and(collect($read->document['entities'])->flatMap(fn (array $row) => $row['relations'])->pluck('kinship')->unique()->all())->toBe([null]);
});
