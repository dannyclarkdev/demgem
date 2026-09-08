<?php

use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Livewire\Entities\Relations;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\EntityRelation;
use Livewire\Livewire;

/**
 * The leak test of this slice. A relationship is a link with two ends, and the
 * party may be standing at either, so every case here is checked from both pages.
 *
 * The proofs are labels and names with spaces in them. Every id is a ULID and a
 * bare number turns up in one by chance.
 */
function court(): array
{
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    $duke = Entity::factory()->for($campaign)->type(EntityType::Character)->forPlayers()
        ->create(['name' => 'The Drowned Duke', 'slug' => 'drowned-duke']);
    $mara = Entity::factory()->for($campaign)->type(EntityType::Character)->forPlayers()
        ->create(['name' => 'Mara Voss', 'slug' => 'mara-voss']);
    $twin = Entity::factory()->for($campaign)->type(EntityType::Character)->dmOnly()
        ->create(['name' => 'The Living Twin', 'slug' => 'living-twin']);

    $rows = [
        // Revealed, both ends known. The party sees this one from both pages.
        'shown' => EntityRelation::factory()->between($duke, $mara)->shownToPlayers()
            ->create(['label' => 'employer of', 'reverse_label' => 'works for']),
        // Not revealed. Both ends known, and still nothing.
        'hidden' => EntityRelation::factory()->between($mara, $duke)
            ->create(['label' => 'plotting against', 'reverse_label' => 'watched by']),
        // Revealed by mistake, pointing at a GM-only page. The second gate catches it.
        'targetHidden' => EntityRelation::factory()->between($duke, $twin)->shownToPlayers()
            ->create(['label' => 'twin of', 'reverse_label' => 'sibling of']),
        // Revealed by mistake, written from a GM-only page. The third gate catches it.
        'sourceHidden' => EntityRelation::factory()->between($twin, $mara)->shownToPlayers()
            ->create(['label' => 'hunting', 'reverse_label' => 'hunted by']),
    ];

    return [$campaign, $player, $duke, $mara, $twin, $rows];
}

it('shows a player only the relationship the GM revealed between pages they know, from both ends', function () {
    [$campaign, $player, $duke, $mara] = court();

    $dukePage = Livewire::actingAs($player)->test(Relations::class, ['campaign' => $campaign, 'entity' => $duke]);

    $dukePage->assertSee('employer of')->assertSee('Mara Voss')
        ->assertDontSee('plotting against')->assertDontSee('watched by')
        ->assertDontSee('twin of')->assertDontSee('sibling of')->assertDontSee('The Living Twin');

    expect($dukePage->html())->not->toContain('Living Twin')->not->toContain('plotting');

    $maraPage = Livewire::actingAs($player)->test(Relations::class, ['campaign' => $campaign, 'entity' => $mara]);

    $maraPage->assertSee('works for')->assertSee('The Drowned Duke')
        ->assertDontSee('plotting against')->assertDontSee('watched by')
        ->assertDontSee('hunting')->assertDontSee('hunted by')->assertDontSee('The Living Twin');

    expect($maraPage->html())->not->toContain('Living Twin')->not->toContain('plotting')->not->toContain('hunt');
});

it('shows the GM all four and which is which', function () {
    [$campaign, $player, $duke, $mara, $twin] = court();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Relations::class, ['campaign' => $campaign, 'entity' => $duke])
        ->assertSee('employer of')->assertSee('watched by')->assertSee('twin of')
        ->assertSee('Shown')->assertSee('Hidden');

    Livewire::actingAs(ownerOf($campaign))
        ->test(Relations::class, ['campaign' => $campaign, 'entity' => $twin])
        ->assertSee('sibling of')->assertSee('hunting');
});

it('keeps the card off a player page that has nothing for them', function () {
    [$campaign, $player, $duke, $mara, $twin, $rows] = court();
    $rows['shown']->delete();

    $html = Livewire::actingAs($player)
        ->test(Relations::class, ['campaign' => $campaign, 'entity' => $duke])
        ->html();

    expect($html)->not->toContain('Relationships');
});

it('lets the GM reveal and hide a relationship, and refuses a player', function () {
    [$campaign, $player, $duke, $mara, $twin, $rows] = court();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Relations::class, ['campaign' => $campaign, 'entity' => $mara])
        ->call('setVisibility', $rows['hidden']->id, true);

    expect($rows['hidden']->fresh()->player_visible)->toBeTrue();

    Livewire::actingAs($player)
        ->test(Relations::class, ['campaign' => $campaign, 'entity' => $duke])
        ->assertSee('watched by');

    Livewire::actingAs(ownerOf($campaign))
        ->test(Relations::class, ['campaign' => $campaign, 'entity' => $duke])
        ->call('setVisibility', $rows['hidden']->id, false);

    expect($rows['hidden']->fresh()->player_visible)->toBeFalse();

    Livewire::actingAs($player)
        ->test(Relations::class, ['campaign' => $campaign, 'entity' => $duke])
        ->assertDontSeeHtml('setVisibility')
        ->call('setVisibility', $rows['shown']->id, false)
        ->assertForbidden();

    expect($rows['shown']->fresh()->player_visible)->toBeTrue();
});
