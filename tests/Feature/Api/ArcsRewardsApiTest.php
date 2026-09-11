<?php

use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\GameSession;

it('writes the arc and the reward on a session through the API', function () {
    $campaign = Campaign::factory()->create();
    $arc = Entity::factory()->for($campaign)->arc()->create(['name' => 'The Drowned Duke']);
    $session = GameSession::factory()->for($campaign)->number(2)->played()->create();

    asKey(ownerOf($campaign), write: true)
        ->patchJson(route('api.sessions.update', [$campaign, 2]), [
            'arc_id' => $arc->id,
            'xp_awarded' => 450,
            'milestone' => 'Reached the Drowned Court',
        ])
        ->assertOk()
        ->assertJsonPath('data.arc_id', $arc->id)
        ->assertJsonPath('data.xp_awarded', 450)
        ->assertJsonPath('data.milestone', 'Reached the Drowned Court');

    expect($session->refresh()->arc_id)->toBe($arc->id);
});

it('refuses a session arc that is not an arc', function () {
    $campaign = Campaign::factory()->create();
    $character = Entity::factory()->for($campaign)->type(EntityType::Character)->create();
    GameSession::factory()->for($campaign)->number(2)->create();

    asKey(ownerOf($campaign), write: true)
        ->patchJson(route('api.sessions.update', [$campaign, 2]), ['arc_id' => $character->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['arc_id']);
});

it('files a quest under an arc through the API, and prohibits it elsewhere', function () {
    $campaign = Campaign::factory()->create();
    $arc = Entity::factory()->for($campaign)->arc()->forPlayers()->create(['name' => 'The Drowned Duke']);
    $quest = Entity::factory()->for($campaign)->quest()->forPlayers()->create();
    $note = Entity::factory()->for($campaign)->type(EntityType::Note)->create();

    asKey(ownerOf($campaign), write: true)
        ->patchJson(route('api.entities.update', [$campaign, $quest->id]), ['arc_id' => $arc->id])
        ->assertOk()
        ->assertJsonPath('data.quest.arc.name', 'The Drowned Duke');

    asKey(ownerOf($campaign), write: true)
        ->patchJson(route('api.entities.update', [$campaign, $note->id]), ['arc_id' => $arc->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['arc_id']);
});

it('leaves a hidden arc out of a player\'s quest document', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $arc = Entity::factory()->for($campaign)->arc()->dmOnly()->create(['name' => 'The Drowned Duke']);
    $quest = Entity::factory()->for($campaign)->quest()->forPlayers()->inArc($arc)->create();

    $response = asKey($player)
        ->getJson(route('api.entities.show', [$campaign, $quest->id]))
        ->assertOk()
        ->assertJsonPath('data.quest.arc', null);

    expect($response->getContent())->not->toContain('The Drowned Duke');
});
