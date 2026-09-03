<?php

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

/**
 * The whole player surface in one place. Four slices built GM tools beside player
 * screens, and this walks a player through every page they can open and checks that
 * none of the GM half reached them: not the prose, not the tools, not the links.
 *
 * Every leak this catches would be a leak of the same kind, so they are tested as a
 * set rather than one at a time in each feature's own file.
 */
function playerWorld(): array
{
    $campaign = Campaign::factory()->create(['name' => 'The Drowned Duchy']);
    $player = memberOf($campaign, CampaignRole::Player);

    $visibleNpc = Entity::factory()->for($campaign)->forPlayers()
        ->create(['name' => 'Harbourmaster Coll', 'slug' => 'coll', 'body' => 'Runs the docks.']);

    Entity::factory()->for($campaign)->dmOnly()
        ->create(['name' => 'The Informant', 'slug' => 'informant', 'body' => 'Sells the party out.']);

    Entity::factory()->for($campaign)->type(EntityType::Location)->forPlayers()->withDmNotes('The GM note nobody may read.')
        ->create(['name' => 'The Salt Cathedral', 'slug' => 'salt-cathedral']);

    $quest = Entity::factory()->for($campaign)->quest(QuestStatus::Active)->forPlayers()->withObjectives(3, 1)
        ->create(['name' => 'The Toll Bridge', 'slug' => 'toll-bridge']);

    $quest->update(['giver_entity_id' => Entity::factory()->for($campaign)->dmOnly()
        ->create(['name' => 'The Hidden Patron'])->id]);

    $pc = Entity::factory()->for($campaign)->pcOf($player)->forPlayers()->withRecord('Rogue', 5, 'https://example.test/wren')
        ->create(['name' => 'Wren', 'slug' => 'wren']);

    $played = GameSession::factory()->for($campaign)->number(1)->published('The party burned the bridge.')
        ->create(['title' => 'The Toll']);

    $played->update([
        'strong_start' => 'The strong start nobody may read.',
        'live_notes' => 'The live notes nobody may read.',
        'dm_notes' => 'The session GM note nobody may read.',
    ]);

    $played->scenes()->create(['campaign_id' => $campaign->id, 'position' => 0, 'title' => 'The Bridge', 'notes' => 'The scene note nobody may read.']);
    $played->entities()->attach($visibleNpc->id, ['role' => PrepRole::Npc->value, 'position' => 0]);

    Secret::factory()->for($campaign)->preparedFor($played)->create(['body' => 'The secret nobody may read.']);

    $upcoming = GameSession::factory()->for($campaign)->number(2)->planned()->create(['title' => 'The Cellar']);

    GameSession::factory()->for($campaign)->number(3)->hidden()->published('The draft session nobody may read.')
        ->create(['title' => 'The Duke']);

    $encounter = Encounter::factory()->for($campaign)->create(['name' => 'Cultists in the nave']);
    Combatant::factory()->inEncounter($encounter)->create(['name' => 'Cultist Bravo']);

    // A roll behind the screen, and a shared one beside it. The GM's own screen is
    // player-facing now: the log renders on /table for everyone in the campaign.
    DiceRoll::factory()->for($campaign)->by(ownerOf($campaign))->behindTheScreen()
        ->create(['label' => 'The roll nobody may read.']);

    $table = RandomTable::factory()->for($campaign)->create(['name' => 'Harbour rumours']);
    RandomTableEntry::factory()->inTable($table)->create(['body' => 'The rumour nobody may read.']);

    return [$campaign, $player, compact('quest', 'pc', 'played', 'upcoming', 'encounter', 'table')];
}

it('shows a player no GM prose on any page they can open', function (string $routeName, array $extra) {
    [$campaign, $player] = playerWorld();

    $response = $this->actingAs($player)->get(route($routeName, [$campaign, ...$extra]));

    $response->assertOk();

    foreach ([
        'The GM note nobody may read.',
        'The session GM note nobody may read.',
        'The strong start nobody may read.',
        'The live notes nobody may read.',
        'The scene note nobody may read.',
        'The secret nobody may read.',
        'The draft session nobody may read.',
        'The rumour nobody may read.',
        'The roll nobody may read.',
        'The Informant',
        'The Hidden Patron',
        'Cultist Bravo',
    ] as $forbidden) {
        $response->assertDontSee($forbidden);
    }
})->with([
    'dashboard' => ['campaigns.show', []],
    'table' => ['table', []],
    'sessions' => ['sessions.index', []],
    'session' => ['sessions.show', [1]],
    'story' => ['story', []],
    'members' => ['campaigns.members', []],
    'quests' => ['entities.index', ['quests']],
    'quest' => ['entities.show', ['quests', 'toll-bridge']],
    'characters' => ['entities.index', ['characters']],
    'character' => ['entities.show', ['characters', 'wren']],
    'location' => ['entities.show', ['locations', 'salt-cathedral']],
]);

it('offers a player no link into the GM half of the app', function () {
    [$campaign, $player, $world] = playerWorld();

    $forbiddenUrls = [
        route('sessions.prep', [$campaign, 1]),
        route('sessions.run', [$campaign, 1]),
        route('encounters.index', $campaign),
        route('tables.index', $campaign),
        route('campaigns.settings', $campaign),
    ];

    foreach (['campaigns.show', 'sessions.index', 'story', 'campaigns.members', 'table'] as $routeName) {
        $response = $this->actingAs($player)->get(route($routeName, $campaign))->assertOk();

        foreach ($forbiddenUrls as $url) {
            $response->assertDontSee($url, false);
        }
    }
});

it('closes every GM-only route to a player', function (string $routeName, array $extra, int $status) {
    [$campaign, $player, $world] = playerWorld();

    $parameters = match ($routeName) {
        'encounters.show' => [$campaign, $world['encounter']->id],
        'tables.show' => [$campaign, $world['table']->id],
        default => [$campaign, ...$extra],
    };

    $this->actingAs($player)->get(route($routeName, $parameters))->assertStatus($status);
})->with([
    'prep' => ['sessions.prep', [1], 403],
    'run' => ['sessions.run', [1], 403],
    'encounters' => ['encounters.index', [], 403],
    'encounter' => ['encounters.show', [], 404],
    'tables' => ['tables.index', [], 403],
    'table' => ['tables.show', [], 404],
    'settings' => ['campaigns.settings', [], 403],
    'hidden session' => ['sessions.show', [3], 404],
    'hidden entity' => ['entities.show', ['characters', 'informant'], 404],
]);

it('shows a player their own character, their party, and the story', function () {
    [$campaign, $player] = playerWorld();

    $this->actingAs($player)->get(route('campaigns.show', $campaign))
        ->assertOk()
        ->assertSee('The party')
        ->assertSee('Wren')
        ->assertSee('The Toll Bridge');

    $this->actingAs($player)->get(route('story', $campaign))
        ->assertOk()
        ->assertSee('The party burned the bridge.');

    $this->actingAs($player)->get(route('entities.show', [$campaign, 'characters', 'wren']))
        ->assertOk()
        ->assertSee('Rogue')
        ->assertSee('Edit');
});

it('treats a spectator exactly as it treats a player', function () {
    [$campaign] = playerWorld();
    $spectator = memberOf($campaign, CampaignRole::Spectator);

    $this->actingAs($spectator)->get(route('story', $campaign))
        ->assertOk()
        ->assertSee('The party burned the bridge.')
        ->assertDontSee('The draft session nobody may read.');

    $this->actingAs($spectator)->get(route('sessions.run', [$campaign, 1]))->assertForbidden();
});
