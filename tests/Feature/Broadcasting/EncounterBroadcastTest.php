<?php

use App\Actions\Encounters\AddCombatants;
use App\Actions\Encounters\ApplyDamage;
use App\Actions\Encounters\CreateEncounter;
use App\Actions\Encounters\DeleteEncounter;
use App\Actions\Encounters\NextTurn;
use App\Actions\Encounters\RemoveCombatant;
use App\Actions\Encounters\ReorderCombatants;
use App\Actions\Encounters\RollInitiative;
use App\Actions\Encounters\SetConditions;
use App\Actions\Encounters\SetPlayerVisibility;
use App\Actions\Encounters\SortByInitiative;
use App\Enums\CampaignRole;
use App\Events\EncounterChanged;
use App\Livewire\Encounters\Tracker;
use App\Models\Campaign;
use App\Models\Combatant;
use App\Models\Encounter;
use App\Models\Entity;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

it('says where it broadcasts and what it is called', function () {
    $event = new EncounterChanged('01campaign', '01encounter');

    expect($event)->toBeInstanceOf(ShouldBroadcast::class)
        ->and($event)->toBeInstanceOf(ShouldRescue::class)
        ->and($event->broadcastAs())->toBe('encounter.changed');

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(PresenceChannel::class)
        ->and($channels[0]->name)->toBe('presence-campaign.01campaign');
});

it('carries two ids and nothing else', function () {
    $campaign = Campaign::factory()->create();
    $encounter = Encounter::factory()->for($campaign)->create(['name' => 'Ambush at the bridge']);
    Combatant::factory()->inEncounter($encounter)->withHealth(59)->create([
        'name' => 'Ogre chief',
        'conditions' => ['prone'],
    ]);

    $payload = json_encode((new EncounterChanged($campaign->id, $encounter->id))->broadcastWith(), JSON_THROW_ON_ERROR);

    // The whole security design in one assertion: a listener re-renders under its own
    // viewer's role, so the wire carries nothing that would need filtering. Revealing
    // a hidden combatant and hiding it again send exactly these same two ids.
    //
    // The exact match is the assertion that counts; the three strings after it read as
    // documentation. There is deliberately no check for the hit points: the payload is
    // a ULID, Crockford base32 has every digit, and "59" turns up in one by chance.
    expect(json_decode($payload, true))->toBe(['encounterId' => $encounter->id])
        ->and($payload)->not->toContain('Ambush at the bridge')
        ->and($payload)->not->toContain('Ogre chief')
        ->and($payload)->not->toContain('prone');
});

it('broadcasts once for every change a GM makes', function (string $action) {
    $campaign = Campaign::factory()->create();
    $owner = ownerOf($campaign);
    $encounter = Encounter::factory()->for($campaign)->create();
    $combatant = Combatant::factory()->inEncounter($encounter)->withHealth(20)->create(['initiative' => 10]);
    Combatant::factory()->inEncounter($encounter)->create(['position' => 1]);

    Event::fake([EncounterChanged::class]);

    match ($action) {
        'create' => app(CreateEncounter::class)->handle($campaign, $owner, 'A new fight'),
        'add' => app(AddCombatants::class)->handle($encounter, 'Goblin', 3),
        'damage' => app(ApplyDamage::class)->handle($combatant, 5),
        'conditions' => app(SetConditions::class)->handle($combatant, ['prone']),
        'next turn' => app(NextTurn::class)->handle($encounter),
        'end' => app(NextTurn::class)->end($encounter),
        'reopen' => app(NextTurn::class)->reopen($encounter),
        'reset' => app(NextTurn::class)->reset($encounter),
        'reveal' => app(SetPlayerVisibility::class)->toggle($combatant),
        'sort' => app(SortByInitiative::class)->handle($encounter),
        'reorder' => app(ReorderCombatants::class)->handle($encounter, $combatant->id, 1),
        'move' => app(ReorderCombatants::class)->move($encounter, $combatant, 1),
        'remove' => app(RemoveCombatant::class)->handle($combatant),
        'delete' => app(DeleteEncounter::class)->handle($encounter),
    };

    Event::assertDispatchedTimes(EncounterChanged::class, 1);
})->with([
    'create', 'add', 'damage', 'conditions', 'next turn', 'end', 'reopen', 'reset',
    'reveal', 'sort', 'reorder', 'move', 'remove', 'delete',
]);

it('broadcasts once when the whole party joins the fight, not once a head', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $encounter = Encounter::factory()->for($campaign)->create();
    $party = Entity::factory()->count(5)->for($campaign)->pcOf($player)->create();

    Event::fake([EncounterChanged::class]);

    app(AddCombatants::class)->fromEntities($encounter, $party);

    Event::assertDispatchedTimes(EncounterChanged::class, 1);
    expect($encounter->combatants()->count())->toBe(5);
});

it('stays quiet when nothing changed', function () {
    $campaign = Campaign::factory()->create();
    $encounter = Encounter::factory()->for($campaign)->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $pc = Entity::factory()->for($campaign)->pcOf($player)->create();
    Combatant::factory()->inEncounter($encounter)->forEntity($pc)->create();

    Event::fake([EncounterChanged::class]);

    // Only player characters in the fight, so RollInitiative rolls for nobody.
    expect(app(RollInitiative::class)->handle($encounter))->toBe(0);

    Event::assertNotDispatched(EncounterChanged::class);
});

it('names the campaign it belongs to, so another table never hears it', function () {
    $campaign = Campaign::factory()->create();
    $other = Campaign::factory()->create();
    $encounter = Encounter::factory()->for($campaign)->create();
    Combatant::factory()->inEncounter($encounter)->create();

    Event::fake([EncounterChanged::class]);

    app(NextTurn::class)->handle($encounter);

    Event::assertDispatched(EncounterChanged::class, function (EncounterChanged $event) use ($campaign, $other) {
        return $event->campaignId === $campaign->id && $event->campaignId !== $other->id;
    });
});

it('takes the broadcast and re-renders, and ignores another fight', function () {
    $campaign = Campaign::factory()->create();
    $encounter = Encounter::factory()->for($campaign)->create();
    Combatant::factory()->inEncounter($encounter)->create(['name' => 'Ogre chief']);
    $elsewhere = Encounter::factory()->for($campaign)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Tracker::class, ['campaign' => $campaign, 'encounter' => $encounter])
        ->call('encounterChanged', ['encounterId' => $encounter->id])
        ->assertOk()
        ->assertSee('Ogre chief')
        ->call('encounterChanged', ['encounterId' => $elsewhere->id])
        ->assertOk();
});

it('keeps the poll as a backstop, at a minute', function () {
    expect(Tracker::POLL_SECONDS)->toBe(60);
});
