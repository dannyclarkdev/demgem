<?php

use App\Enums\CampaignRole;
use App\Models\Campaign;
use App\Models\Encounter;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\RandomTable;
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
        ->and(GameSession::query()->count())->toBe(3)
        ->and(Encounter::query()->count())->toBe(1)
        ->and(RandomTable::query()->count())->toBe(2);

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
