<?php

use App\Enums\CampaignRole;
use App\Enums\QuestStatus;
use App\Enums\Visibility;
use App\Models\Campaign;
use App\Models\Combatant;
use App\Models\Encounter;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\QuestObjective;
use App\Models\RandomTable;

/**
 * The Run screen now carries four nested components: live notes, the tracker, an
 * objectives list per active quest, and the two tools in the drawer. This renders the
 * whole page through HTTP with all of them present, which is the only place they are
 * exercised together.
 */
function loadedRunScreen(): array
{
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $session = GameSession::factory()->for($campaign)->number(4)->create(['title' => 'The Salt Stair']);

    Entity::factory()->for($campaign)->pcOf($player)->create(['name' => 'Wren Ashgrove']);

    $quest = Entity::factory()->for($campaign)->quest(QuestStatus::Active)->create(['name' => 'Seal the Undercity']);
    QuestObjective::factory()->forQuest($quest, 0)->create(['body' => 'Get the tide charts']);
    QuestObjective::factory()->forQuest($quest, 1)->complete()->create(['body' => 'Find a way in']);

    $encounter = Encounter::factory()->inSession($session)->active()->create(['name' => 'The stair']);
    Combatant::factory()->inEncounter($encounter, 0)->withInitiative(19)->withHealth(22)->create(['name' => 'Drowned thrall']);

    RandomTable::factory()->for($campaign)->withEntries(['A caravan is late'])->create(['name' => 'Tavern rumours']);

    return [$campaign, $session];
}

it('renders every tool on the Run screen in one request', function () {
    [$campaign, $session] = loadedRunScreen();

    $this->actingAs(ownerOf($campaign))
        ->get($session->runUrl())
        ->assertOk()
        ->assertSee('The stair')
        ->assertSee('Drowned thrall')
        ->assertSee('Seal the Undercity')
        ->assertSee('Get the tide charts')
        ->assertSee('Tavern rumours')
        ->assertSee('Round 1')
        ->assertSeeHtml('wire:poll.visible')
        ->assertSee('Live notes');
});

it('renders the encounter page and the table page for a GM', function () {
    [$campaign] = loadedRunScreen();

    $encounter = Encounter::query()->withoutGlobalScopes()->where('campaign_id', $campaign->id)->sole();
    $table = RandomTable::query()->withoutGlobalScopes()->where('campaign_id', $campaign->id)->sole();

    $owner = ownerOf($campaign);

    $this->actingAs($owner)->get(route('encounters.index', $campaign))->assertOk()->assertSee('The stair');
    $this->actingAs($owner)->get(route('encounters.show', [$campaign, $encounter->id]))->assertOk()->assertSee('Drowned thrall');
    $this->actingAs($owner)->get(route('tables.index', $campaign))->assertOk()->assertSee('Tavern rumours');
    $this->actingAs($owner)->get(route('tables.show', [$campaign, $table->id]))->assertOk()->assertSee('A caravan is late');
});

it('keeps every tool out of a player\'s Run screen, which they cannot reach anyway', function () {
    [$campaign, $session] = loadedRunScreen();
    $player = $campaign->members()->where('role', CampaignRole::Player)->sole()->user;

    // A GM-only screen on a session the player *can* see answers 403, which is what
    // slice 2 settled and tests. 404 is for a session they may not know exists.
    $this->actingAs($player)->get($session->runUrl())->assertForbidden();

    // The quest page is theirs, and it must carry the objectives but no fight.
    $quest = Entity::query()->withoutGlobalScopes()->where('name', 'Seal the Undercity')->sole();
    $quest->update(['visibility' => Visibility::Players]);

    $this->actingAs($player)
        ->get($quest->url())
        ->assertOk()
        ->assertSee('Get the tide charts')
        ->assertDontSee('Drowned thrall')
        ->assertDontSee('The stair');
});

it('shows the quests panel on the dashboard for both roles', function () {
    [$campaign] = loadedRunScreen();
    $player = $campaign->members()->where('role', CampaignRole::Player)->sole()->user;

    $quest = Entity::query()->withoutGlobalScopes()->where('name', 'Seal the Undercity')->sole();
    $quest->update(['visibility' => Visibility::Players]);

    foreach ([ownerOf($campaign), $player] as $user) {
        $this->actingAs($user)
            ->get(route('campaigns.show', $campaign))
            ->assertOk()
            ->assertSee('Quests in play')
            ->assertSee('Seal the Undercity')
            ->assertSee('1 of 2');
    }
});
