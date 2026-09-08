<?php

use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Enums\QuestStatus;
use App\Enums\SessionStatus;
use App\Enums\Visibility;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\User;

it('creates an entity with a write key, and the slug is generated', function () {
    $campaign = Campaign::factory()->create();
    $owner = ownerOf($campaign);
    $vell = Entity::factory()->for($campaign)->type(EntityType::Location)->forPlayers()->create(['name' => 'The Duchy of Vell']);

    $response = asKey($owner, write: true)
        ->postJson("/api/v1/campaigns/{$campaign->id}/entities", [
            'type' => 'locations',
            'name' => 'Salt Cathedral',
            'body' => 'It floods at the spring tide.',
            'dm_notes' => 'The crypt is where the twin sleeps.',
            'visibility' => 'players',
            'parent_id' => $vell->id,
            'tags' => ['Harbor', 'harbor', ' Holy '],
            'custom_fields' => [['key' => 'Founded', 'value' => '812 AR'], ['key' => '', 'value' => 'dropped']],
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Salt Cathedral')
        ->assertJsonPath('data.slug', 'salt-cathedral')
        ->assertJsonPath('data.type', 'location')
        ->assertJsonPath('data.visibility', 'players')
        ->assertJsonPath('data.dm_notes', 'The crypt is where the twin sleeps.')
        ->assertJsonPath('data.parent.name', 'The Duchy of Vell')
        ->assertJsonPath('data.custom_fields', [['key' => 'Founded', 'value' => '812 AR']]);

    expect(collect($response->json('data.tags'))->sort()->values()->all())->toBe(['Harbor', 'Holy']);

    $entity = Entity::withoutGlobalScopes()->where('campaign_id', $campaign->id)->where('slug', 'salt-cathedral')->firstOrFail();

    expect($entity->created_by)->toBe($owner->id)
        ->and($entity->parent_id)->toBe($vell->id);
});

it('refuses a create from a read-only key, from a player, with an unknown type, and with a taken name', function () {
    $campaign = Campaign::factory()->create();
    $owner = ownerOf($campaign);
    $player = memberOf($campaign, CampaignRole::Player);
    Entity::factory()->for($campaign)->type(EntityType::Location)->create(['name' => 'Bell Tower']);

    $url = "/api/v1/campaigns/{$campaign->id}/entities";
    $payload = ['type' => 'locations', 'name' => 'Salt Cathedral'];

    asKey($owner)->postJson($url, $payload)->assertForbidden();
    asKey($player, write: true)->postJson($url, $payload)->assertForbidden();
    asKey($owner, write: true)->postJson($url, ['type' => 'dragons', 'name' => 'Smaug'])->assertUnprocessable()->assertJsonValidationErrors('type');
    asKey($owner, write: true)->postJson($url, ['type' => 'locations', 'name' => 'bell tower'])->assertUnprocessable()->assertJsonValidationErrors('name');
    asKey($owner, write: true)->postJson($url, ['type' => 'locations', 'name' => 'Fine', 'level' => 3])->assertUnprocessable()->assertJsonValidationErrors('level');

    expect(Entity::withoutGlobalScopes()->where('campaign_id', $campaign->id)->count())->toBe(1);
});

it('lets a player change their own PC’s body and record, and refuses the GM fields', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $other = memberOf($campaign, CampaignRole::Player);
    $pc = Entity::factory()->for($campaign)->pcOf($player)->dmOnly()->withDmNotes('Original notes.')->create(['name' => 'Wren']);
    $theirs = Entity::factory()->for($campaign)->pcOf($other)->forPlayers()->create(['name' => 'Not Wren']);
    $hidden = Entity::factory()->for($campaign)->dmOnly()->create(['name' => 'The Drowned Duke']);

    asKey($player, write: true)
        ->patchJson("/api/v1/campaigns/{$campaign->id}/entities/{$pc->id}", [
            'name' => 'Wren Ashgrove',
            'body' => 'Rogue. Looking for her sister.',
            'character_class' => 'Rogue',
            'level' => 4,
            'tags' => ['pc'],
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Wren Ashgrove')
        ->assertJsonPath('data.slug', 'wren-ashgrove')
        ->assertJsonPath('data.character.class', 'Rogue')
        ->assertJsonPath('data.character.level', 4)
        ->assertJsonPath('data.tags', ['pc'])
        ->assertJsonMissingPath('data.dm_notes');

    asKey($player, write: true)
        ->patchJson("/api/v1/campaigns/{$campaign->id}/entities/{$pc->id}", ['visibility' => 'players', 'dm_notes' => 'Rewritten.'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['visibility', 'dm_notes']);

    expect($pc->fresh())
        ->visibility->toBe(Visibility::Dm)
        ->dm_notes->toBe('Original notes.');

    asKey($player, write: true)->patchJson("/api/v1/campaigns/{$campaign->id}/entities/{$theirs->id}", ['body' => 'Mine now.'])->assertForbidden();
    asKey($player, write: true)->patchJson("/api/v1/campaigns/{$campaign->id}/entities/{$hidden->id}", ['body' => 'Found you.'])->assertNotFound();
    asKey($player)->patchJson("/api/v1/campaigns/{$campaign->id}/entities/{$pc->id}", ['body' => 'Read only.'])->assertForbidden();

    expect($theirs->fresh()->body)->not->toBe('Mine now.')
        ->and($hidden->fresh()->body)->not->toBe('Found you.');
});

it('lets a GM change the GM fields and the quest, and refuses an entity as its own parent', function () {
    $campaign = Campaign::factory()->create();
    $owner = ownerOf($campaign);
    $duke = Entity::factory()->for($campaign)->type(EntityType::Character)->dmOnly()->create(['name' => 'The Drowned Duke']);
    $quest = Entity::factory()->for($campaign)->quest()->dmOnly()->create(['name' => 'Raise the bell']);

    asKey($owner, write: true)
        ->patchJson("/api/v1/campaigns/{$campaign->id}/entities/{$quest->id}", [
            'visibility' => 'players',
            'quest_status' => 'active',
            'giver_entity_id' => $duke->id,
            'rewards' => 'A boat.',
            'dm_notes' => 'The bell is cursed.',
        ])
        ->assertOk()
        ->assertJsonPath('data.visibility', 'players')
        ->assertJsonPath('data.quest.status', 'active')
        ->assertJsonPath('data.quest.giver.name', 'The Drowned Duke')
        ->assertJsonPath('data.quest.rewards', 'A boat.')
        ->assertJsonPath('data.dm_notes', 'The bell is cursed.');

    expect($quest->fresh()->quest_status)->toBe(QuestStatus::Active);

    asKey($owner, write: true)
        ->patchJson("/api/v1/campaigns/{$campaign->id}/entities/{$quest->id}", ['parent_id' => $quest->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('parent_id');

    asKey($owner, write: true)
        ->patchJson("/api/v1/campaigns/{$campaign->id}/entities/{$duke->id}", ['quest_status' => 'active'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('quest_status');
});

it('changes a session’s notes and status with a GM’s write key, and never its publish stamp', function () {
    $campaign = Campaign::factory()->create();
    $owner = ownerOf($campaign);
    $player = memberOf($campaign, CampaignRole::Player);
    $session = GameSession::factory()->for($campaign)->number(12)->planned()->create(['title' => 'Old title']);

    asKey($owner, write: true)
        ->patchJson("/api/v1/campaigns/{$campaign->id}/sessions/12", [
            'title' => 'The Salt Cathedral',
            'status' => 'played',
            'live_notes' => 'Mara lied about the key.',
            'recap' => 'They burned the bridge behind them.',
            'recap_published_at' => now()->toIso8601String(),
        ])
        ->assertOk()
        ->assertJsonPath('data.title', 'The Salt Cathedral')
        ->assertJsonPath('data.status', 'played')
        ->assertJsonPath('data.live_notes', 'Mara lied about the key.')
        ->assertJsonPath('data.recap', 'They burned the bridge behind them.')
        ->assertJsonPath('data.recap_published_at', null);

    expect($session->fresh())
        ->status->toBe(SessionStatus::Played)
        ->recap_published_at->toBeNull();

    asKey($owner, write: true)->patchJson("/api/v1/campaigns/{$campaign->id}/sessions/12", ['status' => 'lost'])->assertUnprocessable();
    asKey($owner)->patchJson("/api/v1/campaigns/{$campaign->id}/sessions/12", ['title' => 'Read only'])->assertForbidden();
    asKey($player, write: true)->patchJson("/api/v1/campaigns/{$campaign->id}/sessions/12", ['title' => 'A player'])->assertForbidden();
    asKey(User::factory()->create(), write: true)->patchJson("/api/v1/campaigns/{$campaign->id}/sessions/12", ['title' => 'A stranger'])->assertNotFound();

    expect($session->fresh()->title)->toBe('The Salt Cathedral');
});

it('publishes a recap on purpose, and refuses to publish an empty one', function () {
    $campaign = Campaign::factory()->create();
    $owner = ownerOf($campaign);
    $player = memberOf($campaign, CampaignRole::Player);
    GameSession::factory()->for($campaign)->number(12)->played()->create();

    $url = "/api/v1/campaigns/{$campaign->id}/sessions/12/publish-recap";

    asKey($owner, write: true)->postJson($url)->assertUnprocessable()->assertJsonValidationErrors('recap');

    asKey($owner, write: true)
        ->postJson($url, ['recap' => 'They burned the bridge behind them.'])
        ->assertOk()
        ->assertJsonPath('data.recap', 'They burned the bridge behind them.');

    $session = GameSession::withoutGlobalScopes()->where('campaign_id', $campaign->id)->where('number', 12)->firstOrFail();
    $publishedAt = $session->recap_published_at;

    expect($publishedAt)->not->toBeNull();

    asKey($player)
        ->getJson("/api/v1/campaigns/{$campaign->id}/sessions/12")
        ->assertOk()
        ->assertJsonPath('data.recap', 'They burned the bridge behind them.');

    // Publishing again keeps the first stamp.
    $this->travel(1)->hours();

    asKey($owner, write: true)->postJson($url)->assertOk();

    expect($session->fresh()->recap_published_at?->equalTo($publishedAt))->toBeTrue();

    asKey($player, write: true)->postJson($url)->assertForbidden();
    asKey($owner)->postJson($url)->assertForbidden();
});
