<?php

use App\Enums\CampaignRole;
use App\Livewire\Sessions\LiveNotes;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\Mention;
use Livewire\Livewire;

it('saves the notes as the GM types', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(LiveNotes::class, ['campaign' => $campaign, 'session' => $session])
        ->set('notes', 'They bribed the guard for 40 gold.')
        ->assertSet('savedAt', now()->format('H:i'));

    expect($session->refresh()->live_notes)->toBe('They bribed the guard for 40 gold.');
});

it('loads what was typed before the browser reloaded', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->create(['live_notes' => 'Half a sentence']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(LiveNotes::class, ['campaign' => $campaign, 'session' => $session])
        ->assertSet('notes', 'Half a sentence');
});

it('stamps the save time in the campaign timezone', function () {
    $campaign = Campaign::factory()->create(['timezone' => 'Australia/Sydney']);
    $session = GameSession::factory()->for($campaign)->number(1)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(LiveNotes::class, ['campaign' => $campaign, 'session' => $session])
        ->set('notes', 'Something happened.')
        ->assertSet('savedAt', now()->setTimezone('Australia/Sydney')->format('H:i'));
});

it('clears the column when the GM empties the field', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->create(['live_notes' => 'Something']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(LiveNotes::class, ['campaign' => $campaign, 'session' => $session])
        ->set('notes', '');

    expect($session->refresh()->live_notes)->toBeNull();
});

it('indexes wiki links typed at the table', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->create();
    $mara = Entity::factory()->for($campaign)->create(['name' => 'Mara Voss']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(LiveNotes::class, ['campaign' => $campaign, 'session' => $session])
        ->set('notes', 'They finally met [[Mara Voss]].');

    $mention = Mention::query()->where('source_id', $session->id)->sole();

    expect($mention->source_field)->toBe('live_notes')
        ->and($mention->target_entity_id)->toBe($mara->id);
});

it('writes no mention rows while the GM types prose', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->create();
    Entity::factory()->for($campaign)->create(['name' => 'Mara Voss']);

    $component = Livewire::actingAs(ownerOf($campaign))
        ->test(LiveNotes::class, ['campaign' => $campaign, 'session' => $session])
        ->set('notes', 'They met [[Mara Voss]].');

    $rowIds = Mention::query()->where('source_id', $session->id)->pluck('id')->all();

    $component->set('notes', 'They met [[Mara Voss]]. She wanted the ring.')
        ->set('notes', 'They met [[Mara Voss]]. She wanted the ring, badly.');

    expect(Mention::query()->where('source_id', $session->id)->pluck('id')->all())->toBe($rowIds);
});

it('stops a player from reaching the notes component at all', function (CampaignRole $role) {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->create();

    Livewire::actingAs(memberOf($campaign, $role))
        ->test(LiveNotes::class, ['campaign' => $campaign, 'session' => $session])
        ->assertForbidden();
})->with(['player' => CampaignRole::Player, 'spectator' => CampaignRole::Spectator]);

it('stops a GM who was demoted mid-session from saving another word', function () {
    $campaign = Campaign::factory()->create();
    $coGm = memberOf($campaign, CampaignRole::CoGm);
    $session = GameSession::factory()->for($campaign)->number(1)->create();

    $component = Livewire::actingAs($coGm)
        ->test(LiveNotes::class, ['campaign' => $campaign, 'session' => $session])
        ->set('notes', 'Written while trusted.');

    $campaign->members()->where('user_id', $coGm->id)->update(['role' => CampaignRole::Player]);
    $campaign->forgetMemberCache();

    $component->set('notes', 'Written after the demotion.')->assertForbidden();

    expect($session->refresh()->live_notes)->toBe('Written while trusted.');
});
