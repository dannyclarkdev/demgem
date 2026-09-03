<?php

use App\Actions\Encounters\AddCombatants;
use App\Actions\Encounters\SetPlayerVisibility;
use App\Enums\CampaignRole;
use App\Events\EncounterChanged;
use App\Livewire\Encounters\Tracker;
use App\Livewire\Table\Fight;
use App\Models\Campaign;
use App\Models\Combatant;
use App\Models\Encounter;
use App\Models\Entity;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

/**
 * The leak test of this slice, in its own file because every leak it guards is the
 * same kind: something the GM is tracking reaching the party's screens.
 *
 * There are deliberately no assertions against bare numbers here. Every id in the app
 * is a ULID, Crockford base32 carries every digit, and a two-digit run turns up in one
 * often enough to redden CI. The proofs are names with spaces, markers from the GM's
 * own markup, and an exact match on the Livewire snapshot.
 */
function fightWithASurprise(): array
{
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    $pc = Entity::factory()->for($campaign)->pcOf($player)->forPlayers()->create(['name' => 'Wren Aldercross']);

    $encounter = Encounter::factory()->for($campaign)->active(2)->create(['name' => 'Ambush at the ford']);

    Combatant::factory()->inEncounter($encounter, 0)->forEntity($pc)->shownToPlayers()
        ->withHealth(24, 30)->create(['ac' => 15, 'initiative_bonus' => 3, 'initiative' => 18]);

    $hidden = Combatant::factory()->inEncounter($encounter, 1)->withHealth(9, 60)
        ->create(['name' => 'Cellar lurker', 'ac' => 17, 'initiative_bonus' => 4, 'initiative' => 12]);

    return [$campaign, $player, $encounter, $hidden];
}

it('keeps a hidden combatant out of a player page, markup and snapshot alike', function () {
    [$campaign, $player] = fightWithASurprise();

    $response = $this->actingAs($player)->get(route('table', $campaign))->assertOk();

    $response->assertSee('Wren Aldercross')
        ->assertDontSee('Cellar lurker')
        ->assertDontSee('Ambush at the ford');

    // Every Livewire snapshot on the page, decoded out of the attribute it hides in.
    preg_match_all('/wire:snapshot="([^"]*)"/', $response->getContent(), $matches);

    expect($matches[1])->not->toBeEmpty();

    foreach ($matches[1] as $snapshot) {
        expect(html_entity_decode($snapshot, ENT_QUOTES))->not->toContain('Cellar lurker')
            ->and(html_entity_decode($snapshot, ENT_QUOTES))->not->toContain('Ambush at the ford');
    }
});

it('carries no combatant at all in the player snapshot', function () {
    [$campaign, $player, $encounter] = fightWithASurprise();

    $snapshot = Livewire::actingAs($player)
        ->test(Fight::class, ['campaign' => $campaign, 'encounterId' => $encounter->id])
        ->snapshot;

    // The exact match is the assertion. The component holds a campaign and an id, and
    // reads every combatant per render under the viewer's own role. Nothing about the
    // fight survives between round trips, so there is nothing there to filter.
    expect(array_keys($snapshot['data']))->toBe(['encounterId', 'campaign'])
        ->and($snapshot['data']['encounterId'])->toBe($encounter->id);
});

it('gives a player no hit points, no armour class, and no initiative', function () {
    [$campaign, $player, $encounter] = fightWithASurprise();

    $html = Livewire::actingAs($player)
        ->test(Fight::class, ['campaign' => $campaign, 'encounterId' => $encounter->id])
        ->assertSee('Wren Aldercross')
        ->html();

    // Markers, never bare numbers: "Armour class" is the tracker's own title
    // attribute, and a maximum renders inside its own faint span. A ULID cannot
    // produce either. A plain "/30" would not do: Tailwind writes opacity the same
    // way, and border-ember/30 is on every badge in the kit.
    expect($html)->toContain('Hurt')
        ->and($html)->not->toContain('Armour class')
        ->and($html)->not->toContain('text-ink-faint">/')
        ->and($html)->not->toContain('Init +');
});

it('shows a GM every row and the numbers they are tracking', function () {
    [$campaign, $player, $encounter] = fightWithASurprise();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Fight::class, ['campaign' => $campaign, 'encounterId' => $encounter->id])
        ->assertSee('Wren Aldercross')
        ->assertSee('Cellar lurker')
        ->assertSee('Hidden')
        ->assertSeeHtml('<span class="text-ink-faint">/60</span>')
        ->assertSeeHtml('<span class="text-ink-faint">/30</span>');
});

it('puts the party on the party screen and leaves everything else off it', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $encounter = Encounter::factory()->for($campaign)->create();

    $pc = Entity::factory()->for($campaign)->pcOf($player)->create(['name' => 'Wren Aldercross']);
    $npc = Entity::factory()->for($campaign)->create(['name' => 'Harbourmaster Coll']);

    app(AddCombatants::class)->fromEntities($encounter, collect([$pc, $npc]));
    app(AddCombatants::class)->handle($encounter, 'Goblin', 4);

    $shown = $encounter->combatants()->get()->keyBy('name')->map->player_visible;

    expect($shown['Wren Aldercross'])->toBeTrue()
        ->and($shown['Harbourmaster Coll'])->toBeFalse()
        ->and($shown['Goblin 1'])->toBeFalse()
        ->and($shown['Goblin 4'])->toBeFalse();
});

it('flips the eye from the tracker and tells every screen once', function () {
    [$campaign, $player, $encounter, $hidden] = fightWithASurprise();

    Event::fake([EncounterChanged::class]);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Tracker::class, ['campaign' => $campaign, 'encounter' => $encounter])
        ->call('toggleVisibility', $hidden->id)
        ->assertOk();

    expect($hidden->fresh()->player_visible)->toBeTrue();

    Event::assertDispatchedTimes(EncounterChanged::class, 1);
});

it('stays quiet when the eye is already where the GM wants it', function () {
    [$campaign, $player, $encounter, $hidden] = fightWithASurprise();

    Event::fake([EncounterChanged::class]);

    app(SetPlayerVisibility::class)->handle($hidden, false);

    Event::assertNotDispatched(EncounterChanged::class);
});

it('keeps the eye out of a player reach', function (CampaignRole $role) {
    [$campaign, $ignored, $encounter, $hidden] = fightWithASurprise();

    Livewire::actingAs(memberOf($campaign, $role))
        ->test(Tracker::class, ['campaign' => $campaign, 'encounter' => $encounter])
        ->assertForbidden();

    expect($hidden->fresh()->player_visible)->toBeFalse();
})->with([CampaignRole::Player, CampaignRole::Spectator]);

it('stops showing the fight to a member who was removed mid-fight', function () {
    [$campaign, $player, $encounter] = fightWithASurprise();

    $component = Livewire::actingAs($player)
        ->test(Fight::class, ['campaign' => $campaign, 'encounterId' => $encounter->id])
        ->assertSee('Wren Aldercross');

    $campaign->members()->where('user_id', $player->id)->delete();
    $campaign->forgetMemberCache();

    $component->call('encounterChanged', ['encounterId' => $encounter->id])->assertNotFound();
});
