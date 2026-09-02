<?php

use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Enums\Visibility;
use App\Models\Campaign;
use App\Models\Entity;

it('shows every entity to DM roles', function (CampaignRole $role) {
    $campaign = Campaign::factory()->create();
    $user = memberOf($campaign, $role);
    Entity::factory()->for($campaign)->dmOnly()->create(['name' => 'Hidden Duke', 'slug' => 'hidden-duke']);

    $this->actingAs($user)->get(route('entities.index', [$campaign, 'characters']))->assertSee('Hidden Duke');
    $this->actingAs($user)->get(route('entities.show', [$campaign, 'characters', 'hidden-duke']))->assertOk();
})->with(['owner' => CampaignRole::Owner, 'co_gm' => CampaignRole::CoGm]);

it('hides GM-only entities from players and spectators on the index and returns 404 on the page', function (CampaignRole $role) {
    $campaign = Campaign::factory()->create();
    $user = memberOf($campaign, $role);
    Entity::factory()->for($campaign)->dmOnly()->create(['name' => 'Hidden Duke', 'slug' => 'hidden-duke']);
    Entity::factory()->for($campaign)->forPlayers()->create(['name' => 'Known Ally']);

    $this->actingAs($user)
        ->get(route('entities.index', [$campaign, 'characters']))
        ->assertOk()
        ->assertSee('Known Ally')
        ->assertDontSee('Hidden Duke');

    $this->actingAs($user)
        ->get(route('entities.show', [$campaign, 'characters', 'hidden-duke']))
        ->assertNotFound();
})->with(['player' => CampaignRole::Player, 'spectator' => CampaignRole::Spectator]);

it('shows a selected entity only to the chosen players', function () {
    $campaign = Campaign::factory()->create();
    $chosen = memberOf($campaign, CampaignRole::Player);
    $other = memberOf($campaign, CampaignRole::Player);
    Entity::factory()->for($campaign)->selectedFor($chosen)->create(['name' => 'Secret Letter', 'slug' => 'secret-letter']);

    $this->actingAs($chosen)->get(route('entities.show', [$campaign, 'characters', 'secret-letter']))->assertOk();
    $this->actingAs($other)->get(route('entities.show', [$campaign, 'characters', 'secret-letter']))->assertNotFound();
    $this->actingAs($other)->get(route('entities.index', [$campaign, 'characters']))->assertDontSee('Secret Letter');
});

it('always shows a player their own PC', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $other = memberOf($campaign, CampaignRole::Player);
    Entity::factory()->for($campaign)->pcOf($player)->dmOnly()->create(['name' => 'Wren', 'slug' => 'wren']);

    $this->actingAs($player)->get(route('entities.show', [$campaign, 'characters', 'wren']))->assertOk();
    $this->actingAs($other)->get(route('entities.show', [$campaign, 'characters', 'wren']))->assertNotFound();
});

it('returns 404 for an entity of another campaign even with the right slug', function () {
    $mine = Campaign::factory()->create();
    $theirs = Campaign::factory()->create();
    Entity::factory()->for($theirs)->forPlayers()->create(['slug' => 'shared-slug']);

    $this->actingAs(ownerOf($mine))
        ->get(route('entities.show', [$mine, 'characters', 'shared-slug']))
        ->assertNotFound();
});

it('returns 404 when the slug exists under a different type', function () {
    $campaign = Campaign::factory()->create();
    Entity::factory()->for($campaign)->type(EntityType::Location)->forPlayers()->create(['slug' => 'vell']);

    $this->actingAs(ownerOf($campaign))
        ->get(route('entities.show', [$campaign, 'items', 'vell']))
        ->assertNotFound();
});

it('lets a DM filter the index by visibility', function () {
    $campaign = Campaign::factory()->create();
    Entity::factory()->for($campaign)->dmOnly()->create(['name' => 'Hidden Duke']);
    Entity::factory()->for($campaign)->forPlayers()->create(['name' => 'Known Ally']);

    $this->actingAs(ownerOf($campaign))
        ->get(route('entities.index', [$campaign, 'characters', 'visibility' => Visibility::Dm->value]))
        ->assertSee('Hidden Duke')
        ->assertDontSee('Known Ally');
});

it('filters the index by name', function () {
    $campaign = Campaign::factory()->create();
    Entity::factory()->for($campaign)->forPlayers()->create(['name' => 'Mara Voss']);
    Entity::factory()->for($campaign)->forPlayers()->create(['name' => 'Abbess Corvane']);

    $this->actingAs(ownerOf($campaign))
        ->get(route('entities.index', [$campaign, 'characters', 'q' => 'mara']))
        ->assertSee('Mara Voss')
        ->assertDontSee('Abbess Corvane');
});

it('counts only visible entities in the sidebar', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    Entity::factory()->for($campaign)->dmOnly()->count(3)->create();
    Entity::factory()->for($campaign)->forPlayers()->create();

    $this->actingAs($player)->get(route('campaigns.show', $campaign))->assertSeeInOrder(['Characters', '1']);
    $this->actingAs(ownerOf($campaign))->get(route('campaigns.show', $campaign))->assertSeeInOrder(['Characters', '4']);
});
