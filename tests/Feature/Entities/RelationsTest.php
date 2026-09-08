<?php

use App\Actions\Campaigns\ImportCampaign;
use App\Actions\Campaigns\ReadCampaignFile;
use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Livewire\Entities\Relations;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\EntityRelation;
use App\Models\User;
use Livewire\Livewire;

function duchy(): array
{
    $campaign = Campaign::factory()->create();
    $duke = Entity::factory()->for($campaign)->type(EntityType::Character)->forPlayers()
        ->create(['name' => 'The Drowned Duke', 'slug' => 'drowned-duke']);
    $mara = Entity::factory()->for($campaign)->type(EntityType::Character)->forPlayers()
        ->create(['name' => 'Mara Voss', 'slug' => 'mara-voss']);

    return [$campaign, $duke, $mara];
}

function relationsOf(Entity $entity)
{
    return Livewire::actingAs(ownerOf($entity->campaign))
        ->test(Relations::class, ['campaign' => $entity->campaign, 'entity' => $entity]);
}

it('lets a GM relate two entities and both pages know', function () {
    [$campaign, $duke, $mara] = duchy();

    relationsOf($duke)
        ->set('targetId', $mara->id)
        ->set('label', 'employer of')
        ->set('reverseLabel', 'works for')
        ->call('relate')
        ->assertHasNoErrors()
        ->assertSee('employer of')
        ->assertSee('Mara Voss');

    $row = EntityRelation::withoutGlobalScopes()->where('entity_id', $duke->id)->firstOrFail();

    expect($row->target_entity_id)->toBe($mara->id)
        ->and($row->label)->toBe('employer of')
        ->and($row->reverse_label)->toBe('works for')
        ->and($row->player_visible)->toBeFalse();

    relationsOf($mara)
        ->assertSee('works for')
        ->assertSee('The Drowned Duke')
        ->assertDontSee('employer of');
});

it('names the other side first when there is no reverse label', function () {
    [$campaign, $duke, $mara] = duchy();

    EntityRelation::factory()->between($duke, $mara)->create(['label' => 'employer of', 'reverse_label' => null]);

    relationsOf($mara)
        ->assertSeeInOrder(['The Drowned Duke', '· employer of']);
});

it('lets a GM remove a relationship', function () {
    [$campaign, $duke, $mara] = duchy();
    $row = EntityRelation::factory()->between($duke, $mara)->create(['label' => 'sworn enemy of']);

    relationsOf($duke)
        ->assertSee('sworn enemy of')
        ->call('remove', $row->id)
        ->assertDontSee('sworn enemy of');

    expect(EntityRelation::withoutGlobalScopes()->count())->toBe(0);
});

it('refuses a player, a self link, a stranger, and a long label', function () {
    [$campaign, $duke, $mara] = duchy();
    $player = memberOf($campaign, CampaignRole::Player);
    $stranger = Entity::factory()->create(['name' => 'Somebody Elsewhere']);

    Livewire::actingAs($player)
        ->test(Relations::class, ['campaign' => $campaign, 'entity' => $duke])
        ->assertDontSeeHtml('wire:submit="relate"')
        ->set('targetId', $mara->id)
        ->set('label', 'employer of')
        ->call('relate')
        ->assertForbidden();

    relationsOf($duke)
        ->set('targetId', $duke->id)
        ->set('label', 'twin of')
        ->call('relate')
        ->assertHasErrors('targetId');

    relationsOf($duke)
        ->set('targetId', $stranger->id)
        ->set('label', 'twin of')
        ->call('relate')
        ->assertHasErrors('targetId');

    relationsOf($duke)
        ->set('targetId', $mara->id)
        ->set('label', str_repeat('x', 61))
        ->call('relate')
        ->assertHasErrors('label');

    expect(EntityRelation::withoutGlobalScopes()->count())->toBe(0);
});

it('exports both ends and the round trip remaps them', function () {
    [$campaign, $duke, $mara] = duchy();
    $row = EntityRelation::factory()->between($duke, $mara)->create(['label' => 'employer of', 'reverse_label' => 'works for', 'player_visible' => true]);

    $exported = exportedArray($campaign);
    $dukeRow = collect($exported['entities'])->firstWhere('slug', 'drowned-duke');

    expect($dukeRow['relations'])->toBe([[
        'id' => $row->id,
        'target_entity_id' => $mara->id,
        'label' => 'employer of',
        'reverse_label' => 'works for',
        'player_visible' => true,
        'position' => 0,
    ]]);

    $result = app(ReadCampaignFile::class)->handle(json_encode($exported, JSON_THROW_ON_ERROR));

    expect($result->errors)->toBe([]);

    $copy = app(ImportCampaign::class)->handle($result->document, User::factory()->create());
    $restored = EntityRelation::withoutGlobalScopes()->where('campaign_id', $copy->id)->firstOrFail();
    $newDuke = Entity::withoutGlobalScopes()->where('campaign_id', $copy->id)->where('slug', 'drowned-duke')->firstOrFail();
    $newMara = Entity::withoutGlobalScopes()->where('campaign_id', $copy->id)->where('slug', 'mara-voss')->firstOrFail();

    expect($restored->id)->not->toBe($row->id)
        ->and($restored->entity_id)->toBe($newDuke->id)
        ->and($restored->target_entity_id)->toBe($newMara->id)
        ->and($restored->reverse_label)->toBe('works for')
        ->and($restored->player_visible)->toBeTrue();
});

it('refuses a file whose relationship points at a page that is not in it', function () {
    [$campaign, $duke, $mara] = duchy();
    EntityRelation::factory()->between($duke, $mara)->create(['label' => 'employer of']);

    $exported = exportedArray($campaign);
    $exported['entities'] = array_values(array_filter($exported['entities'], fn ($row) => $row['slug'] !== 'mara-voss'));

    $result = app(ReadCampaignFile::class)->handle(json_encode($exported, JSON_THROW_ON_ERROR));

    // A dangling reference is refused, as a pin's is: writing half a relationship
    // would be a quiet wrong answer, and the file is the thing to fix.
    expect($result->succeeded())->toBeFalse()
        ->and(implode(' ', $result->errors))->toContain('employer of');
});
