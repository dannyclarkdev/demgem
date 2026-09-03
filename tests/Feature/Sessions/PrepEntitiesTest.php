<?php

use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Enums\PrepRole;
use App\Livewire\Sessions\Prep;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\GameSession;
use Livewire\Livewire;

it('attaches an entity to a bucket', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->create();
    $mara = Entity::factory()->for($campaign)->create(['name' => 'Mara Voss']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Prep::class, ['campaign' => $campaign, 'number' => 1])
        ->call('openPicker', PrepRole::Npc->value)
        ->call('attachEntity', $mara->id)
        ->assertSet('pickerRole', '');

    expect($session->prepped(PrepRole::Npc)->pluck('name')->all())->toBe(['Mara Voss']);
});

it('puts the same entity in two buckets but never twice in one', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->create();
    $troll = Entity::factory()->for($campaign)->create(['name' => 'The Bridge Troll']);

    $component = Livewire::actingAs(ownerOf($campaign))->test(Prep::class, ['campaign' => $campaign, 'number' => 1]);

    $component->call('openPicker', PrepRole::Npc->value)->call('attachEntity', $troll->id);
    $component->call('openPicker', PrepRole::Monster->value)->call('attachEntity', $troll->id);
    $component->call('openPicker', PrepRole::Npc->value)->call('attachEntity', $troll->id);

    expect($session->prepped(PrepRole::Npc)->count())->toBe(1)
        ->and($session->prepped(PrepRole::Monster)->count())->toBe(1)
        ->and($session->entities()->count())->toBe(2);
});

it('detaches from one bucket and leaves the other alone', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->create();
    $troll = Entity::factory()->for($campaign)->create(['name' => 'The Bridge Troll']);

    $component = Livewire::actingAs(ownerOf($campaign))->test(Prep::class, ['campaign' => $campaign, 'number' => 1]);
    $component->call('openPicker', PrepRole::Npc->value)->call('attachEntity', $troll->id);
    $component->call('openPicker', PrepRole::Monster->value)->call('attachEntity', $troll->id);

    $component->call('detachEntity', $troll->id, PrepRole::Npc->value);

    expect($session->prepped(PrepRole::Npc)->count())->toBe(0)
        ->and($session->prepped(PrepRole::Monster)->count())->toBe(1);
});

it('drops a deleted entity out of the bucket', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->create();
    $mara = Entity::factory()->for($campaign)->create(['name' => 'Mara Voss']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Prep::class, ['campaign' => $campaign, 'number' => 1])
        ->call('openPicker', PrepRole::Npc->value)
        ->call('attachEntity', $mara->id);

    $mara->delete();

    expect($session->prepped(PrepRole::Npc)->count())->toBe(0);

    $this->actingAs(ownerOf($campaign))
        ->get(route('sessions.prep', [$campaign, 1]))
        ->assertOk()
        ->assertDontSee('Mara Voss');
});

it('offers the suggested types first and filters by name', function () {
    $campaign = Campaign::factory()->create();
    GameSession::factory()->for($campaign)->number(1)->create();
    Entity::factory()->for($campaign)->type(EntityType::Note)->create(['name' => 'Aaa note']);
    Entity::factory()->for($campaign)->type(EntityType::Location)->create(['name' => 'Zzz place']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Prep::class, ['campaign' => $campaign, 'number' => 1])
        ->call('openPicker', PrepRole::Location->value)
        ->assertSeeInOrder(['Zzz place', 'Aaa note'])
        ->set('pickerSearch', 'aaa')
        ->assertSee('Aaa note')
        ->assertDontSee('Zzz place');
});

it('never offers an entity the picker already holds', function () {
    $campaign = Campaign::factory()->create();
    GameSession::factory()->for($campaign)->number(1)->create();
    $mara = Entity::factory()->for($campaign)->create(['name' => 'Mara Voss']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Prep::class, ['campaign' => $campaign, 'number' => 1])
        ->call('openPicker', PrepRole::Npc->value)
        ->call('attachEntity', $mara->id)
        ->call('openPicker', PrepRole::Npc->value)
        ->assertSee('Nothing left to add');
});

it('keeps prepped entities off every screen a player can reach', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->create();
    $hidden = Entity::factory()->for($campaign)->dmOnly()->create(['name' => 'The Real Villain']);
    $session->entities()->attach($hidden->id, ['role' => PrepRole::Npc->value, 'position' => 0]);

    $this->actingAs(memberOf($campaign, CampaignRole::Player))
        ->get(route('sessions.show', [$campaign, 1]))
        ->assertOk()
        ->assertDontSee('The Real Villain');

    $this->actingAs(memberOf($campaign, CampaignRole::Player))
        ->get(route('sessions.prep', [$campaign, 1]))
        ->assertForbidden();
});
