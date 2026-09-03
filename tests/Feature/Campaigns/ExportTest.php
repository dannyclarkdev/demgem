<?php

use App\Actions\Campaigns\ExportCampaign;
use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Enums\PrepRole;
use App\Enums\QuestStatus;
use App\Models\Campaign;
use App\Models\Combatant;
use App\Models\DiceRoll;
use App\Models\Encounter;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\RandomTable;
use App\Models\RandomTableEntry;
use App\Models\Secret;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedJsonResponse;

/**
 * A campaign with one of everything in it, so the export has something to lose.
 */
function exportableCampaign(): array
{
    $campaign = Campaign::factory()->create(['name' => 'The Drowned Duchy']);
    $player = memberOf($campaign, CampaignRole::Player);

    $npc = Entity::factory()->for($campaign)->forPlayers()->withDmNotes('He takes bribes.')
        ->create(['name' => 'Harbourmaster Coll', 'slug' => 'coll', 'body' => 'Runs the docks.']);

    $npc->tags()->attach(Tag::factory()->for($campaign)->create(['name' => 'harbour'])->id);

    $quest = Entity::factory()->for($campaign)->quest(QuestStatus::Active)->forPlayers()->withObjectives(3, 1)
        ->withRewards('The [[Sunblade]].')->create(['name' => 'The Toll Bridge']);

    $pc = Entity::factory()->for($campaign)->pcOf($player)->withRecord('Rogue', 5, 'https://example.test/wren')
        ->create(['name' => 'Wren']);

    Entity::factory()->for($campaign)->type(EntityType::Location)->create(['name' => 'The Deleted Vault'])->delete();

    $session = GameSession::factory()->for($campaign)->number(1)->published('The party burned the bridge.')
        ->create(['title' => 'The Toll']);

    $session->update(['strong_start' => 'The bell rings.', 'live_notes' => 'They argued.', 'dm_notes' => 'Keep the duke off screen.']);
    $session->scenes()->create(['campaign_id' => $campaign->id, 'position' => 0, 'title' => 'The Bridge', 'notes' => 'Rain.']);
    $session->entities()->attach($npc->id, ['role' => PrepRole::Npc->value, 'position' => 0]);
    Secret::factory()->for($campaign)->preparedFor($session)->create(['body' => 'The duke pays the tolls.']);

    GameSession::factory()->for($campaign)->number(2)->create(['title' => 'The Deleted Session'])->delete();

    $encounter = Encounter::factory()->for($campaign)->create(['name' => 'Cultists in the nave']);
    Combatant::factory()->inEncounter($encounter)->create(['name' => 'Cultist Bravo', 'hp' => 7]);

    $table = RandomTable::factory()->for($campaign)->create(['name' => 'Harbour rumours']);
    RandomTableEntry::factory()->inTable($table)->create(['body' => 'The tide brought a body in.']);

    DiceRoll::factory()->for($campaign)->create(['formula' => '2d6+3', 'total' => 11]);

    return [$campaign, $player, compact('npc', 'quest', 'pc', 'session', 'encounter', 'table')];
}

function exportBody(Campaign $campaign, User $user): array
{
    $response = test()->actingAs($user)->get(route('campaigns.export', $campaign));

    $response->assertOk();

    return json_decode($response->streamedContent(), true, 512, JSON_THROW_ON_ERROR);
}

it('carries the format and the version', function () {
    [$campaign] = exportableCampaign();

    $body = exportBody($campaign, ownerOf($campaign));

    expect($body['format'])->toBe(ExportCampaign::FORMAT)
        ->and($body['version'])->toBe(ExportCampaign::VERSION)
        ->and($body['generated_at'])->toBeString()
        ->and($body['campaign']['name'])->toBe('The Drowned Duchy')
        ->and($body['campaign']['timezone'])->toBe($campaign->timezone);
});

it('holds the whole world, GM half included', function () {
    [$campaign] = exportableCampaign();

    $body = exportBody($campaign, ownerOf($campaign));

    $npc = collect($body['entities'])->firstWhere('name', 'Harbourmaster Coll');
    $quest = collect($body['entities'])->firstWhere('name', 'The Toll Bridge');
    $pc = collect($body['entities'])->firstWhere('name', 'Wren');
    $session = $body['sessions'][0];

    expect($npc['dm_notes'])->toBe('He takes bribes.')
        ->and($npc['tags'])->toBe(['harbour'])
        ->and($quest['quest_status'])->toBe(QuestStatus::Active->value)
        ->and($quest['rewards'])->toBe('The [[Sunblade]].')
        ->and($quest['objectives'])->toHaveCount(3)
        ->and($quest['objectives'][0]['completed_at'])->not->toBeNull()
        ->and($pc['character_class'])->toBe('Rogue')
        ->and($pc['level'])->toBe(5)
        ->and($pc['sheet_url'])->toBe('https://example.test/wren')
        ->and($session['strong_start'])->toBe('The bell rings.')
        ->and($session['live_notes'])->toBe('They argued.')
        ->and($session['dm_notes'])->toBe('Keep the duke off screen.')
        ->and($session['recap'])->toBe('The party burned the bridge.')
        ->and($session['scenes'][0]['title'])->toBe('The Bridge')
        ->and($session['secrets'][0]['body'])->toBe('The duke pays the tolls.')
        ->and($session['prepped'][0]['role'])->toBe(PrepRole::Npc->value)
        ->and($body['encounters'][0]['combatants'][0]['name'])->toBe('Cultist Bravo')
        ->and($body['random_tables'][0]['entries'][0]['body'])->toBe('The tide brought a body in.')
        ->and($body['dice_rolls'][0]['formula'])->toBe('2d6+3')
        ->and($body['members'])->toHaveCount(2);
});

it('takes nobody\'s email address, password, or invite link with it', function () {
    [$campaign, $player] = exportableCampaign();

    $owner = ownerOf($campaign);
    $invite = $campaign->invites()->create([
        'token' => 'a-live-invite-token',
        'role' => CampaignRole::Player,
        'created_by' => $owner->id,
    ]);

    $raw = $this->actingAs($owner)->get(route('campaigns.export', $campaign))->streamedContent();

    expect($raw)->not->toContain($owner->email)
        ->and($raw)->not->toContain($player->email)
        ->and($raw)->not->toContain($owner->password)
        ->and($raw)->not->toContain($invite->token)
        ->and($raw)->not->toContain('remember_token')
        ->and($raw)->not->toContain('two_factor');

    // The names and the roles do travel: an importer needs to know who was at the table.
    expect($raw)->toContain($owner->name)->toContain($player->name);
});

it('carries images as links and facts, not as files', function () {
    Storage::fake('public');

    [$campaign] = exportableCampaign();

    $cover = UploadedFile::fake()->image('cover.jpg');
    $campaign->addMedia($cover->getRealPath())->usingFileName('cover.jpg')->toMediaCollection('cover');

    $portrait = UploadedFile::fake()->image('coll.png');
    $npc = $campaign->entities()->where('name', 'Harbourmaster Coll')->firstOrFail();
    $npc->addMedia($portrait->getRealPath())->usingFileName('coll.png')->toMediaCollection('image');

    $body = exportBody($campaign, ownerOf($campaign));

    expect($body['campaign']['cover']['file_name'])->toBe('cover.jpg')
        ->and($body['campaign']['cover']['url'])->toContain('cover.jpg')
        ->and($body['campaign']['cover']['size'])->toBeInt();

    $exportedNpc = collect($body['entities'])->firstWhere('name', 'Harbourmaster Coll');

    expect($exportedNpc['image']['file_name'])->toBe('coll.png')
        ->and($exportedNpc['image']['mime_type'])->toBe('image/png');
});

it('leaves deleted things behind', function () {
    [$campaign] = exportableCampaign();

    $raw = $this->actingAs(ownerOf($campaign))->get(route('campaigns.export', $campaign))->streamedContent();

    expect($raw)->not->toContain('The Deleted Vault')
        ->and($raw)->not->toContain('The Deleted Session');
});

it('exports this campaign and no other', function () {
    [$campaign] = exportableCampaign();
    $other = Campaign::factory()->create(['name' => 'Somebody Else']);
    Entity::factory()->for($other)->create(['name' => 'Their Secret NPC']);

    $raw = $this->actingAs(ownerOf($campaign))->get(route('campaigns.export', $campaign))->streamedContent();

    expect($raw)->not->toContain('Their Secret NPC');
});

it('names the file after the campaign and the day', function () {
    [$campaign] = exportableCampaign();

    $this->actingAs(ownerOf($campaign))
        ->get(route('campaigns.export', $campaign))
        ->assertHeader('content-disposition', 'attachment; filename="demgem-the-drowned-duchy-'.now()->format('Y-m-d').'.json"');
});

it('streams instead of building the file in memory', function () {
    [$campaign] = exportableCampaign();

    $response = $this->actingAs(ownerOf($campaign))->get(route('campaigns.export', $campaign));

    expect($response->baseResponse)->toBeInstanceOf(StreamedJsonResponse::class);
});

it('is the GM\'s to take, not a player\'s', function (CampaignRole $role) {
    [$campaign] = exportableCampaign();

    $this->actingAs(memberOf($campaign, $role))
        ->get(route('campaigns.export', $campaign))
        ->assertForbidden();
})->with(['player' => CampaignRole::Player, 'spectator' => CampaignRole::Spectator]);

it('lets a co-GM export', function () {
    [$campaign] = exportableCampaign();

    $this->actingAs(memberOf($campaign, CampaignRole::CoGm))
        ->get(route('campaigns.export', $campaign))
        ->assertOk();
});

it('returns 404 for a non-member', function () {
    [$campaign] = exportableCampaign();

    $this->actingAs(User::factory()->create())
        ->get(route('campaigns.export', $campaign))
        ->assertNotFound();
});
