<?php

use App\Enums\CampaignRole;
use App\Models\Campaign;
use App\Models\Decision;
use App\Models\Encounter;
use App\Models\Entity;
use App\Models\EntityTemplate;
use App\Models\GameSession;
use App\Models\LedgerEntry;
use App\Models\MapMarker;
use App\Models\RandomTable;
use App\Models\ReputationChange;
use App\Models\User;
use App\Support\CurrentCampaign;
use Database\Seeders\DemoCampaignSeeder;

/**
 * The demo world is how anybody meets demgem for the first time, and it is the one
 * piece of the app no feature test touches. It breaks quietly whenever a model gains
 * a column, so it gets a test of its own.
 */
it('seeds a world a GM can open and a player can read', function () {
    $this->seed(DemoCampaignSeeder::class);

    $campaign = Campaign::query()->firstOrFail();
    $dm = User::query()->where('email', 'dev@demgem.test')->firstOrFail();
    $player = User::query()->where('email', 'tobin@demgem.test')->firstOrFail();

    app(CurrentCampaign::class)->set($campaign, CampaignRole::Owner);

    expect($campaign->name)->toBe('The Drowned Duchy')
        ->and($campaign->roleFor($dm))->toBe(CampaignRole::Owner)
        ->and($campaign->roleFor($player))->toBe(CampaignRole::Player)
        ->and(Entity::query()->count())->toBeGreaterThan(10)
        ->and(EntityTemplate::query()->count())->toBe(2)
        ->and(GameSession::query()->count())->toBe(4)
        ->and(Encounter::query()->count())->toBe(1)
        ->and(RandomTable::query()->count())->toBe(2)
        // Two maps, one nested in the other, with half the pins revealed. A demo
        // that shows an empty map sells the feature badly.
        ->and(Entity::query()->where('type', 'map')->count())->toBe(2)
        ->and(MapMarker::query()->count())->toBe(8)
        ->and(MapMarker::query()->where('player_visible', true)->count())->toBe(5)
        ->and(MapMarker::query()->whereNotNull('target_entity_id')->count())->toBeGreaterThan(0);

    // Slice 4's own features have to be in the demo, or they sell themselves badly.
    $party = Entity::query()->where('is_pc', true)->orderBy('name')->get();

    expect($party)->toHaveCount(2)
        ->and($party->pluck('character_class')->filter()->all())->not->toBeEmpty()
        ->and($party->firstWhere('name', 'Wren Ashgrove')->customFields())
        ->toBe([['key' => 'Race', 'value' => 'Human'], ['key' => 'Background', 'value' => 'Urchin']]);

    $sessions = GameSession::query()->orderBy('number')->get();

    expect($sessions[0]->hasPublishedRecap())->toBeTrue()
        ->and($sessions[1]->hasPublishedRecap())->toBeFalse()
        ->and(filled($sessions[1]->recap))->toBeTrue()
        ->and($sessions[1]->needsRecap())->toBeTrue();

    // Slice 18: a chapter with quests and sessions in it, a reward, and a decision
    // the party can read beside one they cannot.
    $arc = Entity::query()->where('type', 'arc')->firstOrFail();

    expect(Entity::query()->where('arc_id', $arc->id)->count())->toBeGreaterThan(0)
        ->and(GameSession::query()->where('arc_id', $arc->id)->count())->toBe(3)
        ->and($sessions[0]->rewardLine())->toBe('900 XP · Owed a favour by the Tidewardens')
        ->and(Decision::query()->count())->toBe(3)
        ->and(Decision::query()->where('player_visible', true)->count())->toBe(2)
        ->and(Decision::query()->whereNotNull('consequence')->count())->toBe(2);

    // Slice 19: the player's two pages, one private, and a ledger that sums.
    $journals = Entity::query()->where('type', 'journal')->get();

    expect($journals)->toHaveCount(2)
        ->and($journals->pluck('player_user_id')->unique()->all())->toBe([$player->id])
        ->and($journals->where('visibility', 'dm')->count())->toBe(1)
        ->and((float) LedgerEntry::query()->coin()->sum('amount'))->toBe(110.0)
        ->and(collect(LedgerEntry::inventory(LedgerEntry::query()->items()->get()))->pluck('quantity', 'name')->all())->toBe(['Tidewarden Signet' => 1, 'Torch' => 4]);

    // Slice 20: a fence on the Abbess, and a standing the party reads differently.
    $abbess = Entity::query()->where('name', 'Abbess Corvane')->firstOrFail();

    expect($abbess->body)->toContain(':::secret')
        ->and((int) ReputationChange::query()->sum('delta'))->toBe(0)
        ->and((int) ReputationChange::query()->where('player_visible', true)->sum('delta'))->toBe(2);
});

it('renders every demo screen for the GM it seeds', function () {
    $this->seed(DemoCampaignSeeder::class);

    $campaign = Campaign::query()->firstOrFail();
    $dm = User::query()->where('email', 'dev@demgem.test')->firstOrFail();

    foreach ([
        route('campaigns.show', $campaign),
        route('sessions.index', $campaign),
        route('story', $campaign),
        route('encounters.index', $campaign),
        route('tables.index', $campaign),
        route('entities.index', [$campaign, 'characters']),
        route('entities.index', [$campaign, 'quests']),
        route('entities.index', [$campaign, 'maps']),
        route('entities.show', [$campaign, 'maps', 'the-duchy-of-vell']),
        route('entities.index', [$campaign, 'events']),
        route('entities.show', [$campaign, 'events', 'the-harbor-fire']),
        route('entities.index', [$campaign, 'arcs']),
        route('entities.show', [$campaign, 'arcs', 'the-duke-beneath']),
        route('decisions.index', $campaign),
        route('ledger.index', $campaign),
        route('entities.index', [$campaign, 'journals']),
        route('entities.show', [$campaign, 'journals', 'after-the-fire']),
        route('entities.index', [$campaign, 'factions']),
        route('entities.show', [$campaign, 'factions', 'tidewardens']),
        route('entities.show', [$campaign, 'characters', 'abbess-corvane']),
        route('sessions.show', [$campaign, 1]),
        route('sessions.run', [$campaign, 3]),
        route('calendar.show', $campaign),
        route('calendar.edit', $campaign),
        route('timeline', $campaign),
        route('campaigns.export', $campaign),
    ] as $url) {
        $this->actingAs($dm)->get($url)->assertOk();
    }
});

it('refuses to seed the same world twice', function () {
    $this->seed(DemoCampaignSeeder::class);
    $this->seed(DemoCampaignSeeder::class);

    expect(Campaign::query()->count())->toBe(1);
});
