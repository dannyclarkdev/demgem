<?php

use App\Actions\Campaigns\ImportCampaign;
use App\Actions\Campaigns\ReadCampaignFile;
use App\Actions\Campaigns\WriteCampaignMarkdown;
use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Livewire\Entities\Index;
use App\Livewire\Entities\Show;
use App\Livewire\Factions\Reputation;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\ReputationChange;
use App\Models\User;
use App\Support\Reputation\Standing;
use Livewire\Livewire;

/**
 * The Tidewardens: two moments the party noticed, worth +2 together, and one they
 * have not, worth −3. The party reads Friendly, +2. The GM reads that and −1.
 */
function theTidewardens(): array
{
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $faction = Entity::factory()->for($campaign)->type(EntityType::Faction)->forPlayers()->create(['name' => 'Tidewardens']);
    $draft = GameSession::factory()->for($campaign)->number(2)->hidden()->create(['title' => 'The Secret Night']);

    ReputationChange::factory()->about($faction)->by(1, 'Pulled two of them from the water.')->shownToPlayers()->create();
    ReputationChange::factory()->about($faction)->by(1, 'Returned the tide charts.')->shownToPlayers()->create();
    ReputationChange::factory()->about($faction)->by(-3, 'Mara found out about the ledger.')->madeIn($draft)->create();

    return compact('campaign', 'player', 'faction');
}

it('reads the bands from the config', function () {
    expect((new Standing(4))->label())->toBe('Allied')
        ->and((new Standing(1))->label())->toBe('Friendly')
        ->and((new Standing(0))->label())->toBe('Neutral')
        ->and((new Standing(-1))->label())->toBe('Unfriendly')
        ->and((new Standing(-3))->label())->toBe('Hostile')
        ->and((new Standing(-3))->signed())->toBe('−3')
        ->and((new Standing(2))->signed())->toBe('+2')
        ->and((new Standing(0))->signed())->toBe('0');
});

it('shows the party the sum of what they noticed, and the GM the truth beside it', function () {
    ['campaign' => $campaign, 'player' => $player, 'faction' => $faction] = theTidewardens();

    $component = Livewire::actingAs($player)
        ->test(Show::class, ['campaign' => $campaign, 'type' => 'factions', 'slug' => $faction->slug])
        ->assertSee('Standing with the party')
        ->assertSee('Friendly')
        ->assertSee('+2')
        ->assertSee('Returned the tide charts.')
        ->assertDontSee('In truth')
        ->assertDontSee('Mara found out')
        ->assertDontSee('The Secret Night')
        ->assertDontSee('Record it');

    expect(json_encode($component->snapshot))->not->toContain('Mara found out');

    Livewire::actingAs(ownerOf($campaign))
        ->test(Show::class, ['campaign' => $campaign, 'type' => 'factions', 'slug' => $faction->slug])
        ->assertSee('As the party sees it')
        ->assertSee('In truth')
        ->assertSee('Unfriendly')
        ->assertSee('−1')
        ->assertSee('Mara found out');
});

it('shows a player no card at all when nothing has been revealed', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $faction = Entity::factory()->for($campaign)->type(EntityType::Faction)->forPlayers()->create(['name' => 'Drowned Court']);
    ReputationChange::factory()->about($faction)->by(-2, 'They know.')->create();

    Livewire::actingAs($player)
        ->test(Show::class, ['campaign' => $campaign, 'type' => 'factions', 'slug' => $faction->slug])
        ->assertDontSee('Standing with the party')
        ->assertDontSee('They know.');

    Livewire::actingAs(ownerOf($campaign))
        ->test(Show::class, ['campaign' => $campaign, 'type' => 'factions', 'slug' => $faction->slug])
        ->assertSee('Standing with the party')
        ->assertSee('The party has no read on them yet.')
        ->assertSee('Unfriendly');
});

it('lets a GM record, reveal, reword, and delete a change, and refuses zero', function () {
    $campaign = Campaign::factory()->create();
    $faction = Entity::factory()->for($campaign)->type(EntityType::Faction)->create();
    $session = GameSession::factory()->for($campaign)->number(1)->played()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Reputation::class, ['campaign' => $campaign, 'faction' => $faction])
        ->set('newDelta', '0')
        ->call('adjust')
        ->assertHasErrors('newDelta')
        ->set('newDelta', '2')
        ->set('newReason', 'Returned the signet.')
        ->set('newSessionId', $session->id)
        ->call('adjust')
        ->assertHasNoErrors();

    $change = ReputationChange::query()->firstOrFail();

    expect($change->delta)->toBe(2)
        ->and($change->game_session_id)->toBe($session->id)
        ->and($change->player_visible)->toBeFalse();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Reputation::class, ['campaign' => $campaign, 'faction' => $faction])
        ->call('toggleVisibility', $change->id)
        ->call('edit', $change->id)
        ->set('editingReason', 'Returned the signet, eventually.')
        ->call('saveReason')
        ->assertHasNoErrors();

    $change->refresh();

    expect($change->player_visible)->toBeTrue()
        ->and($change->reason)->toBe('Returned the signet, eventually.')
        ->and($change->delta)->toBe(2);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Reputation::class, ['campaign' => $campaign, 'faction' => $faction])
        ->call('delete', $change->id);

    expect(ReputationChange::query()->count())->toBe(0);
});

it('refuses a player who tries to move a standing', function () {
    ['campaign' => $campaign, 'player' => $player, 'faction' => $faction] = theTidewardens();

    Livewire::actingAs($player)
        ->test(Reputation::class, ['campaign' => $campaign, 'faction' => $faction])
        ->set('newDelta', '5')
        ->call('adjust')
        ->assertForbidden();

    expect(ReputationChange::query()->count())->toBe(3);
});

it('badges the factions index with the band the viewer may see', function () {
    ['campaign' => $campaign, 'player' => $player, 'faction' => $faction] = theTidewardens();
    Entity::factory()->for($campaign)->type(EntityType::Faction)->forPlayers()->create(['name' => 'Salt Guild']);

    Livewire::actingAs($player)
        ->test(Index::class, ['campaign' => $campaign, 'type' => 'factions'])
        ->assertSee('Friendly · +2')
        ->assertDontSee('Unfriendly');

    Livewire::actingAs(ownerOf($campaign))
        ->test(Index::class, ['campaign' => $campaign, 'type' => 'factions'])
        ->assertSee('Unfriendly · −1');
});

it('travels in the export, comes back remapped, and lands in the vault', function () {
    ['campaign' => $campaign, 'faction' => $faction] = theTidewardens();

    $document = exportedArray($campaign);

    expect($document['reputation'])->toHaveCount(3);

    $importer = User::factory()->create();
    $read = app(ReadCampaignFile::class)->handle(json_encode($document, JSON_THROW_ON_ERROR));
    $copy = app(ImportCampaign::class)->handle($read->document, $importer);

    $newFaction = Entity::withoutGlobalScopes()->where('campaign_id', $copy->id)->where('name', 'Tidewardens')->firstOrFail();
    $rows = ReputationChange::withoutGlobalScopes()->where('campaign_id', $copy->id)->get();

    expect($rows)->toHaveCount(3)
        ->and($rows->pluck('entity_id')->unique()->all())->toBe([$newFaction->id])
        ->and((int) $rows->sum('delta'))->toBe(-1)
        ->and($rows->where('player_visible', true)->count())->toBe(2)
        ->and($rows->firstWhere('delta', -3)?->game_session_id)->not->toBeNull();

    $vault = app(WriteCampaignMarkdown::class)->handle($campaign)['markdown/factions/'.$faction->slug.'.md'];

    expect($vault)->toContain('standing: "−1"')
        ->toContain('## Standing with the party')
        ->toContain('- +1 Returned the tide charts.')
        ->toContain('- −3 Mara found out about the ledger. *(Session 2)* *(GM only)*');
});
