<?php

use App\Actions\Campaigns\ImportCampaign;
use App\Actions\Campaigns\ReadCampaignFile;
use App\Actions\Campaigns\WriteCampaignMarkdown;
use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Livewire\Campaigns\Settings;
use App\Livewire\Ledger\Index;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\LedgerEntry;
use App\Models\User;
use Livewire\Livewire;

it('lets a player record coin and an item, and sums the purse and the pack', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    Livewire::actingAs($player)
        ->test(Index::class, ['campaign' => $campaign])
        ->set('kind', 'coin')->set('amount', '150')->set('note', 'Starting purse')
        ->call('record')->assertHasNoErrors()
        ->set('kind', 'coin')->set('direction', 'spend')->set('amount', '40')->set('note', 'The gate sergeant')
        ->call('record')->assertHasNoErrors()
        ->set('kind', 'item')->set('itemName', 'Torch')->set('quantity', '5')
        ->call('record')->assertHasNoErrors()
        ->set('kind', 'item')->set('direction', 'spend')->set('itemName', 'torch')->set('quantity', '2')
        ->call('record')->assertHasNoErrors()
        ->assertViewHas('balance', 110.0)
        ->assertViewHas('inventory', fn (array $inventory) => count($inventory) === 1
            && $inventory[0]['quantity'] === 3
            && $inventory[0]['name'] === 'torch')
        ->assertSee('110.00')
        ->assertSee('Starting purse')
        ->assertSee('The gate sergeant');

    expect(LedgerEntry::query()->count())->toBe(4)
        ->and(LedgerEntry::query()->where('note', 'The gate sergeant')->firstOrFail()->amount)->toBe('-40.00')
        ->and(LedgerEntry::query()->where('note', 'The gate sergeant')->firstOrFail()->created_by)->toBe($player->id);
});

it('prints the campaign currency, set in settings', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Settings::class, ['campaign' => $campaign])
        ->set('currency', 'crowns')
        ->call('save')
        ->assertHasNoErrors();

    expect($campaign->refresh()->currency)->toBe('crowns');

    LedgerEntry::factory()->inCampaign($campaign)->coin(12.5)->create();

    Livewire::actingAs($player)
        ->test(Index::class, ['campaign' => $campaign])
        ->assertSee('12.50')
        ->assertSee('crowns');
});

it('refuses a coin row with no amount, and an item row with no name', function () {
    $campaign = Campaign::factory()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Index::class, ['campaign' => $campaign])
        ->set('kind', 'coin')->set('amount', '')
        ->call('record')->assertHasErrors('amount')
        ->set('kind', 'item')->set('itemName', '')->set('quantity', '1')
        ->call('record')->assertHasErrors('itemName');

    expect(LedgerEntry::query()->count())->toBe(0);
});

it('links an item row to a page the writer may see, and hides the link from one who may not', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $signet = Entity::factory()->for($campaign)->type(EntityType::Item)->dmOnly()->create(['name' => 'Tidewarden Signet']);

    // The picker never offered the hidden page, so an id for it is a forged request,
    // and a forged request is downgraded silently: the row is written without the link.
    Livewire::actingAs($player)
        ->test(Index::class, ['campaign' => $campaign])
        ->set('kind', 'item')->set('itemName', 'A ring')->set('quantity', '1')->set('entityId', $signet->id)
        ->call('record')->assertHasNoErrors();

    expect(LedgerEntry::query()->where('item_name', 'A ring')->firstOrFail()->entity_id)->toBeNull();

    LedgerEntry::factory()->inCampaign($campaign)->item('Tidewarden Signet', 1, $signet)->create();

    $component = Livewire::actingAs($player)
        ->test(Index::class, ['campaign' => $campaign])
        ->assertSee('Tidewarden Signet')
        ->assertViewHas('itemLinks', fn ($links) => $links->isEmpty());

    expect(json_encode($component->snapshot))->not->toContain($signet->slug);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Index::class, ['campaign' => $campaign])
        ->assertViewHas('itemLinks', fn ($links) => $links->has($signet->id));
});

it('shows the session link only to a viewer who may see the session', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $draft = GameSession::factory()->for($campaign)->number(2)->hidden()->create(['title' => 'The Secret Night']);
    LedgerEntry::factory()->madeIn($draft)->coin(40, 'Loot from the crypt')->create();

    $component = Livewire::actingAs($player)
        ->test(Index::class, ['campaign' => $campaign])
        ->assertSee('Loot from the crypt')
        ->assertDontSee('The Secret Night')
        ->assertDontSee('Session 2');

    expect(json_encode($component->snapshot))->not->toContain('The Secret Night');
});

it('lets the author or a GM delete a row, and nobody else', function () {
    $campaign = Campaign::factory()->create();
    $author = memberOf($campaign, CampaignRole::Player);
    $other = memberOf($campaign, CampaignRole::Player);
    $row = LedgerEntry::factory()->inCampaign($campaign)->by($author)->coin(10)->create();

    Livewire::actingAs($other)
        ->test(Index::class, ['campaign' => $campaign])
        ->call('delete', $row->id)
        ->assertForbidden();

    Livewire::actingAs($author)
        ->test(Index::class, ['campaign' => $campaign])
        ->call('delete', $row->id);

    expect(LedgerEntry::query()->count())->toBe(0);

    $row = LedgerEntry::factory()->inCampaign($campaign)->by($author)->coin(10)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Index::class, ['campaign' => $campaign])
        ->call('delete', $row->id);

    expect(LedgerEntry::query()->count())->toBe(0);
});

it('travels in the export, comes back remapped, and lands in the vault', function () {
    $campaign = Campaign::factory()->create(['currency' => 'crowns']);
    $session = GameSession::factory()->for($campaign)->number(1)->played()->create();
    $signet = Entity::factory()->for($campaign)->type(EntityType::Item)->forPlayers()->create(['name' => 'Tidewarden Signet']);
    LedgerEntry::factory()->inCampaign($campaign)->coin(150, 'Starting purse')->create(['created_at' => now()->subDay()]);
    LedgerEntry::factory()->madeIn($session)->coin(-40, 'The gate sergeant')->create();
    LedgerEntry::factory()->madeIn($session)->item('Tidewarden Signet', 1, $signet)->create();

    $document = exportedArray($campaign);

    expect($document['campaign']['currency'])->toBe('crowns')
        ->and($document['ledger'])->toHaveCount(3);

    $importer = User::factory()->create();
    $read = app(ReadCampaignFile::class)->handle(json_encode($document, JSON_THROW_ON_ERROR));
    $copy = app(ImportCampaign::class)->handle($read->document, $importer);

    $newSession = GameSession::withoutGlobalScopes()->where('campaign_id', $copy->id)->firstOrFail();
    $newSignet = Entity::withoutGlobalScopes()->where('campaign_id', $copy->id)->where('name', 'Tidewarden Signet')->firstOrFail();
    $rows = LedgerEntry::withoutGlobalScopes()->where('campaign_id', $copy->id)->orderBy('created_at')->get();

    expect($copy->currency)->toBe('crowns')
        ->and($rows)->toHaveCount(3)
        ->and($rows[1]->game_session_id)->toBe($newSession->id)
        ->and($rows[1]->amount)->toBe('-40.00')
        ->and($rows[2]->entity_id)->toBe($newSignet->id)
        ->and($rows[2]->quantity)->toBe(1)
        ->and((float) LedgerEntry::withoutGlobalScopes()->where('campaign_id', $copy->id)->coin()->sum('amount'))->toBe(110.0);

    $vault = app(WriteCampaignMarkdown::class)->handle($campaign)['markdown/ledger.md'];

    expect($vault)->toContain('currency: "crowns"')
        ->toContain("## In the purse\n\n110.00 crowns")
        ->toContain('- 1 × Tidewarden Signet')
        ->toContain('- −40.00 crowns — The gate sergeant *(Session 1)*');
});
