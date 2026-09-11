<?php

use App\Actions\Campaigns\ImportCampaign;
use App\Actions\Campaigns\ReadCampaignFile;
use App\Actions\Campaigns\WriteCampaignMarkdown;
use App\Enums\CampaignRole;
use App\Livewire\Decisions\Index;
use App\Livewire\Decisions\Log;
use App\Livewire\Sessions\Run;
use App\Livewire\Sessions\Show as SessionShow;
use App\Models\Campaign;
use App\Models\Decision;
use App\Models\GameSession;
use App\Models\User;
use Livewire\Livewire;

/**
 * A campaign with one revealed decision made in a session the party can see, one
 * revealed decision made in a GM-only session, and one hidden decision. The leak
 * test reads the log from a player's seat and expects exactly the first two, with a
 * session link on the first only.
 */
function aLogOfChoices(): array
{
    $campaign = Campaign::factory()->create();
    $open = GameSession::factory()->for($campaign)->number(1)->played()->create(['title' => 'The Harbor Fire']);
    $draft = GameSession::factory()->for($campaign)->number(2)->hidden()->create(['title' => 'The Secret Night']);

    $revealed = Decision::factory()->madeIn($open)->shownToPlayers()->withConsequence('The harbor guild no longer trusts the party.')->create(['choice' => 'Let the smuggler go with the ledger.']);
    $fromDraft = Decision::factory()->madeIn($draft)->shownToPlayers()->create(['choice' => 'Burned the tide charts.']);
    $hidden = Decision::factory()->inCampaign($campaign)->create(['choice' => 'Trusted the abbess with the signet.']);

    return compact('campaign', 'open', 'draft', 'revealed', 'fromDraft', 'hidden');
}

it('shows a player the revealed rows, and a session link only when they may see the session', function () {
    ['campaign' => $campaign] = aLogOfChoices();
    $player = memberOf($campaign, CampaignRole::Player);

    $component = Livewire::actingAs($player)
        ->test(Log::class, ['campaign' => $campaign])
        ->assertSee('Let the smuggler go with the ledger.')
        ->assertSee('The harbor guild no longer trusts the party.')
        ->assertSee('The Harbor Fire')
        ->assertSee('Burned the tide charts.')
        ->assertDontSee('The Secret Night')
        ->assertDontSee('Trusted the abbess with the signet.')
        ->assertDontSee('Record it');

    expect(json_encode($component->snapshot))->not->toContain('Trusted the abbess')
        ->not->toContain('The Secret Night');
});

it('shows a GM every row with the controls', function () {
    ['campaign' => $campaign] = aLogOfChoices();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Log::class, ['campaign' => $campaign])
        ->assertSee('Trusted the abbess with the signet.')
        ->assertSee('The Secret Night')
        ->assertSee('Record it')
        ->assertSee('No consequence yet.');
});

it('lets a GM record a decision, reveal it, write the consequence later, and delete it', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->played()->create();
    $gm = ownerOf($campaign);

    Livewire::actingAs($gm)
        ->test(Log::class, ['campaign' => $campaign])
        ->set('newChoice', 'Let the smuggler go.')
        ->set('newSessionId', $session->id)
        ->call('record')
        ->assertHasNoErrors()
        ->assertSet('newChoice', '');

    $decision = Decision::query()->firstOrFail();

    expect($decision->choice)->toBe('Let the smuggler go.')
        ->and($decision->game_session_id)->toBe($session->id)
        ->and($decision->player_visible)->toBeFalse()
        ->and($decision->created_by)->toBe($gm->id);

    Livewire::actingAs($gm)
        ->test(Log::class, ['campaign' => $campaign])
        ->call('toggleVisibility', $decision->id)
        ->call('edit', $decision->id)
        ->assertSet('editingChoice', 'Let the smuggler go.')
        ->set('editingConsequence', 'The guild turned on them.')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('editingId', null);

    $decision->refresh();

    expect($decision->player_visible)->toBeTrue()
        ->and($decision->consequence)->toBe('The guild turned on them.');

    Livewire::actingAs($gm)
        ->test(Log::class, ['campaign' => $campaign])
        ->call('delete', $decision->id);

    expect(Decision::query()->count())->toBe(0);
});

it('refuses a player who tries to record one', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    Livewire::actingAs($player)
        ->test(Log::class, ['campaign' => $campaign])
        ->set('newChoice', 'A forged choice.')
        ->call('record')
        ->assertForbidden();

    expect(Decision::query()->count())->toBe(0);
});

it('refuses a session from another campaign', function () {
    $campaign = Campaign::factory()->create();
    $elsewhere = GameSession::factory()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Log::class, ['campaign' => $campaign])
        ->set('newChoice', 'A choice.')
        ->set('newSessionId', $elsewhere->id)
        ->call('record')
        ->assertHasErrors('newSessionId');
});

it('scopes the log to one session on the session page and records under it', function () {
    ['campaign' => $campaign, 'open' => $open] = aLogOfChoices();
    $other = Decision::factory()->inCampaign($campaign)->shownToPlayers()->create(['choice' => 'Made somewhere else entirely.']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Log::class, ['campaign' => $campaign, 'session' => $open])
        ->assertSee('Let the smuggler go with the ledger.')
        ->assertDontSee('Made somewhere else entirely.')
        ->set('newChoice', 'Paid the sergeant.')
        ->call('record')
        ->assertHasNoErrors();

    expect(Decision::query()->where('choice', 'Paid the sergeant.')->firstOrFail()->game_session_id)->toBe($open->id);

    Livewire::actingAs(memberOf($campaign, CampaignRole::Player))
        ->test(SessionShow::class, ['campaign' => $campaign, 'number' => 1])
        ->assertSee('Decisions')
        ->assertSee('Let the smuggler go with the ledger.');
});

it('renders the run screen and the log page with the log on them', function () {
    ['campaign' => $campaign] = aLogOfChoices();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Run::class, ['campaign' => $campaign, 'number' => 1])
        ->assertSee('Let the smuggler go with the ledger.');

    Livewire::actingAs(ownerOf($campaign))
        ->test(Index::class, ['campaign' => $campaign])
        ->assertSee('Decisions');

    $this->actingAs(memberOf($campaign, CampaignRole::Player))
        ->get(route('decisions.index', $campaign))
        ->assertOk()
        ->assertSee('Let the smuggler go with the ledger.')
        ->assertDontSee('Trusted the abbess with the signet.');
});

it('keeps a decision when its session is deleted', function () {
    ['campaign' => $campaign, 'open' => $open, 'revealed' => $revealed] = aLogOfChoices();

    Livewire::actingAs(ownerOf($campaign))
        ->test(SessionShow::class, ['campaign' => $campaign, 'number' => 1])
        ->call('delete');

    expect($revealed->refresh()->choice)->toBe('Let the smuggler go with the ledger.');
});

it('travels in the export, comes back remapped, and lands in the vault', function () {
    ['campaign' => $campaign, 'open' => $open, 'revealed' => $revealed] = aLogOfChoices();

    $document = exportedArray($campaign);

    expect($document['decisions'])->toHaveCount(3)
        ->and(collect($document['decisions'])->firstWhere('id', $revealed->id)['game_session_id'])->toBe($open->id);

    $importer = User::factory()->create();
    $read = app(ReadCampaignFile::class)->handle(json_encode($document, JSON_THROW_ON_ERROR));
    $copy = app(ImportCampaign::class)->handle($read->document, $importer);

    $newSession = GameSession::withoutGlobalScopes()->where('campaign_id', $copy->id)->where('number', 1)->firstOrFail();
    $newDecision = Decision::withoutGlobalScopes()->where('campaign_id', $copy->id)->where('choice', 'Let the smuggler go with the ledger.')->firstOrFail();

    expect($newDecision->game_session_id)->toBe($newSession->id)
        ->and($newDecision->consequence)->toBe('The harbor guild no longer trusts the party.')
        ->and($newDecision->player_visible)->toBeTrue()
        ->and($newDecision->created_by)->toBe($importer->id);

    $vault = app(WriteCampaignMarkdown::class)->handle($campaign)['markdown/decisions.md'];

    expect($vault)->toContain('- Let the smuggler go with the ledger. *(Session 1)*')
        ->toContain('  - The harbor guild no longer trusts the party.')
        ->toContain('Trusted the abbess with the signet. *(GM only)*');
});
