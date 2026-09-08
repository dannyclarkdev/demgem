<?php

use App\Actions\Entities\SyncTags;
use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\EntityRelation;
use App\Models\User;
use App\Support\Reckoning\GameDate;

/**
 * @return array{Campaign, User, User}
 */
function apiWorld(): array
{
    $campaign = Campaign::factory()->create(['name' => 'The Drowned Duchy']);
    $player = memberOf($campaign, CampaignRole::Player, User::factory()->create(['name' => 'Tobin Ash']));

    return [$campaign, ownerOf($campaign), $player];
}

function entityNames(array $json): array
{
    return array_map(fn (array $row) => $row['name'], $json['data']);
}

it('lists what the key holder may see, and nothing else', function () {
    [$campaign, $owner, $player] = apiWorld();
    $other = memberOf($campaign, CampaignRole::Player);
    Entity::factory()->for($campaign)->type(EntityType::Location)->forPlayers()->create(['name' => 'Bell Tower']);
    Entity::factory()->for($campaign)->type(EntityType::Character)->dmOnly()->withDmNotes('He is the twin.')->create(['name' => 'The Drowned Duke']);
    Entity::factory()->for($campaign)->type(EntityType::Item)->selectedFor($player)->create(['name' => 'A sealed letter']);
    Entity::factory()->for($campaign)->pcOf($player)->create(['name' => 'Tobin Ash']);
    Entity::factory()->for($campaign)->pcOf($other)->dmOnly()->create(['name' => 'Somebody else']);

    $asPlayer = asKey($player)
        ->getJson("/api/v1/campaigns/{$campaign->id}/entities")
        ->assertOk()
        ->assertJsonMissingPath('data.0.dm_notes')
        ->assertJsonMissingPath('data.0.visibility');

    expect(entityNames($asPlayer->json()))->toBe(['A sealed letter', 'Bell Tower', 'Tobin Ash']);

    $asOwner = asKey($owner)
        ->getJson("/api/v1/campaigns/{$campaign->id}/entities")
        ->assertOk()
        ->assertJsonPath('data.0.visibility', 'selected')
        ->assertJsonPath('data.3.name', 'The Drowned Duke')
        ->assertJsonPath('data.3.dm_notes', 'He is the twin.');

    expect(entityNames($asOwner->json()))->toHaveCount(5);
});

it('filters by type, by tag, and by a name fragment, and refuses an unknown type', function () {
    [$campaign, $owner] = apiWorld();
    $cathedral = Entity::factory()->for($campaign)->type(EntityType::Location)->forPlayers()->create(['name' => 'Salt Cathedral']);
    $mara = Entity::factory()->for($campaign)->type(EntityType::Character)->forPlayers()->create(['name' => 'Mara Vell']);
    Entity::factory()->for($campaign)->type(EntityType::Location)->forPlayers()->create(['name' => 'Bell Tower']);
    app(SyncTags::class)->handle($cathedral, ['Harbor']);
    app(SyncTags::class)->handle($mara, ['Harbor']);

    $base = "/api/v1/campaigns/{$campaign->id}/entities";

    expect(entityNames(asKey($owner)->getJson("{$base}?type=locations")->assertOk()->json()))->toBe(['Bell Tower', 'Salt Cathedral'])
        ->and(entityNames(asKey($owner)->getJson("{$base}?tag=Harbor")->assertOk()->json()))->toBe(['Mara Vell', 'Salt Cathedral'])
        ->and(entityNames(asKey($owner)->getJson("{$base}?q=bell")->assertOk()->json()))->toBe(['Bell Tower'])
        ->and(asKey($owner)->getJson("{$base}?q=bell")->json('data.0.tags'))->toBe([])
        ->and(asKey($owner)->getJson("{$base}?tag=Harbor")->json('data.0.tags'))->toBe(['Harbor']);

    asKey($owner)->getJson("{$base}?type=dragons")->assertUnprocessable();
});

it('pages fifty at a time', function () {
    [$campaign, $owner] = apiWorld();
    Entity::factory()->for($campaign)->type(EntityType::Note)->forPlayers()->count(60)->create();

    $base = "/api/v1/campaigns/{$campaign->id}/entities";

    asKey($owner)->getJson($base)->assertOk()->assertJsonCount(50, 'data')->assertJsonPath('meta.total', 60);
    asKey($owner)->getJson("{$base}?page=2")->assertOk()->assertJsonCount(10, 'data');
});

it('shows an entity with its neighbours, each through its own gate', function () {
    [$campaign, $owner, $player] = apiWorld();
    $vell = Entity::factory()->for($campaign)->type(EntityType::Location)->forPlayers()->create(['name' => 'The Duchy of Vell']);
    $cathedral = Entity::factory()->for($campaign)->type(EntityType::Location)->forPlayers()->childOf($vell)->withDmNotes('The crypt is flooded on purpose.')->create(['name' => 'Salt Cathedral']);
    Entity::factory()->for($campaign)->type(EntityType::Location)->forPlayers()->childOf($cathedral)->create(['name' => 'The Nave']);
    Entity::factory()->for($campaign)->type(EntityType::Location)->dmOnly()->childOf($cathedral)->create(['name' => 'The Undercroft']);
    $duke = Entity::factory()->for($campaign)->type(EntityType::Character)->dmOnly()->create(['name' => 'The Drowned Duke']);
    $mara = Entity::factory()->for($campaign)->type(EntityType::Character)->forPlayers()->create(['name' => 'Mara Vell']);
    $tower = Entity::factory()->for($campaign)->type(EntityType::Location)->forPlayers()->create(['name' => 'Bell Tower']);
    EntityRelation::factory()->between($cathedral, $duke)->shownToPlayers()->create(['label' => 'seat of']);
    EntityRelation::factory()->between($cathedral, $mara)->shownToPlayers()->create(['label' => 'tended by', 'reverse_label' => 'tends']);
    EntityRelation::factory()->between($cathedral, $tower)->create(['label' => 'overlooks']);
    EntityRelation::factory()->between($mara, $cathedral)->shownToPlayers()->create(['label' => 'prays at']);

    $asPlayer = asKey($player)
        ->getJson("/api/v1/campaigns/{$campaign->id}/entities/{$cathedral->id}")
        ->assertOk()
        ->assertJsonPath('data.name', 'Salt Cathedral')
        ->assertJsonPath('data.parent.name', 'The Duchy of Vell')
        ->assertJsonPath('data.children.0.name', 'The Nave')
        ->assertJsonCount(1, 'data.children')
        ->assertJsonCount(1, 'data.relations')
        ->assertJsonPath('data.relations.0.label', 'tended by')
        ->assertJsonPath('data.relations.0.target.name', 'Mara Vell')
        ->assertJsonPath('data.incoming_relations.0.label', 'Mara Vell · prays at')
        ->assertJsonMissingPath('data.dm_notes')
        ->assertJsonMissingPath('data.visibility');

    expect($asPlayer->getContent())->not->toContain('Drowned Duke')
        ->and($asPlayer->getContent())->not->toContain('Undercroft')
        ->and($asPlayer->getContent())->not->toContain('flooded on purpose');

    asKey($owner)
        ->getJson("/api/v1/campaigns/{$campaign->id}/entities/{$cathedral->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data.children')
        ->assertJsonCount(3, 'data.relations')
        ->assertJsonPath('data.dm_notes', 'The crypt is flooded on purpose.')
        ->assertJsonPath('data.visibility', 'players');
});

it('hides a parent the viewer may not see, and the whole entity when it is theirs to hide', function () {
    [$campaign, $owner, $player] = apiWorld();
    $region = Entity::factory()->for($campaign)->type(EntityType::Location)->dmOnly()->create(['name' => 'The Hidden Reach']);
    $tower = Entity::factory()->for($campaign)->type(EntityType::Location)->forPlayers()->childOf($region)->create(['name' => 'Bell Tower']);

    asKey($player)
        ->getJson("/api/v1/campaigns/{$campaign->id}/entities/{$tower->id}")
        ->assertOk()
        ->assertJsonPath('data.parent', null);

    asKey($owner)
        ->getJson("/api/v1/campaigns/{$campaign->id}/entities/{$tower->id}")
        ->assertOk()
        ->assertJsonPath('data.parent.name', 'The Hidden Reach');

    asKey($player)
        ->getJson("/api/v1/campaigns/{$campaign->id}/entities/{$region->id}")
        ->assertNotFound();

    asKey($player)
        ->getJson("/api/v1/campaigns/{$campaign->id}/entities/01ARZ3NDEKTSV4RRFFQ69G5FAV")
        ->assertNotFound();
});

it('shows a quest with its objectives, and only a giver the viewer may see', function () {
    [$campaign, $owner, $player] = apiWorld();
    $duke = Entity::factory()->for($campaign)->type(EntityType::Character)->dmOnly()->create(['name' => 'The Drowned Duke']);
    $quest = Entity::factory()->for($campaign)->quest()->forPlayers()->givenBy($duke)->withObjectives(3, 1)->withRewards('A boat.')->create(['name' => 'Raise the bell']);

    asKey($player)
        ->getJson("/api/v1/campaigns/{$campaign->id}/entities/{$quest->id}")
        ->assertOk()
        ->assertJsonPath('data.quest.status', 'available')
        ->assertJsonPath('data.quest.rewards', 'A boat.')
        ->assertJsonPath('data.quest.giver', null)
        ->assertJsonCount(3, 'data.quest.objectives');

    asKey($owner)
        ->getJson("/api/v1/campaigns/{$campaign->id}/entities/{$quest->id}")
        ->assertOk()
        ->assertJsonPath('data.quest.giver.name', 'The Drowned Duke');
});

it('shows the character record and an event’s day', function () {
    [$campaign, $owner, $player] = apiWorld();
    $pc = Entity::factory()->for($campaign)->pcOf($player)->withRecord('Bard', 5)->create(['name' => 'Tobin Ash']);
    $fire = Entity::factory()->for($campaign)->event(new GameDate(1042, 3, 7))->forPlayers()->create(['name' => 'The night the bridge fell']);

    asKey($player)
        ->getJson("/api/v1/campaigns/{$campaign->id}/entities/{$pc->id}")
        ->assertOk()
        ->assertJsonPath('data.character.class', 'Bard')
        ->assertJsonPath('data.character.level', 5)
        ->assertJsonPath('data.character.is_pc', true)
        ->assertJsonPath('data.character.player_user_id', $player->id)
        ->assertJsonMissingPath('data.quest');

    asKey($player)
        ->getJson("/api/v1/campaigns/{$campaign->id}/entities/{$fire->id}")
        ->assertOk()
        ->assertJsonPath('data.happens_on', ['year' => 1042, 'month' => 3, 'day' => 7]);
});

it('searches the visible pages only', function () {
    [$campaign, $owner, $player] = apiWorld();
    Entity::factory()->for($campaign)->type(EntityType::Location)->forPlayers()->create(['name' => 'Bell Tower', 'body' => 'The bells ring at dusk.']);
    Entity::factory()->for($campaign)->type(EntityType::Character)->dmOnly()->create(['name' => 'The Drowned Duke', 'body' => 'He rings the bells himself.']);

    $asPlayer = asKey($player)
        ->getJson("/api/v1/campaigns/{$campaign->id}/search?q=bells")
        ->assertOk();

    expect(entityNames($asPlayer->json()))->toBe(['Bell Tower']);

    $asOwner = asKey($owner)
        ->getJson("/api/v1/campaigns/{$campaign->id}/search?q=bells")
        ->assertOk();

    expect(entityNames($asOwner->json()))->toBe(['Bell Tower', 'The Drowned Duke']);

    asKey($owner)
        ->getJson("/api/v1/campaigns/{$campaign->id}/search")
        ->assertUnprocessable();
});

it('is a 404 for a non-member and a 401 without a key', function () {
    [$campaign] = apiWorld();
    $entity = Entity::factory()->for($campaign)->forPlayers()->create();
    $stranger = User::factory()->create();

    foreach ([
        "/api/v1/campaigns/{$campaign->id}/entities",
        "/api/v1/campaigns/{$campaign->id}/entities/{$entity->id}",
        "/api/v1/campaigns/{$campaign->id}/search?q=anything",
    ] as $url) {
        asKey($stranger)->getJson($url)->assertNotFound();
        withoutKey()->getJson($url)->assertUnauthorized();
    }
});
