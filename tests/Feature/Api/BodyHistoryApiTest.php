<?php

use App\Enums\CampaignRole;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\EntityBodyRevision;
use App\Models\User;

it('records API edits and exposes paginated summaries and a restorable body', function () {
    $campaign = Campaign::factory()->create();
    $owner = ownerOf($campaign);
    $entity = Entity::factory()->for($campaign)->create(['body' => "    Earlier prose\n"]);
    $base = "/api/v1/campaigns/{$campaign->id}/entities/{$entity->id}";
    asKey($owner, write: true)->patchJson($base, ['body' => 'Current prose'])->assertOk();
    $revision = $entity->bodyRevisions()->sole();
    asKey($owner)->getJson("{$base}/body-revisions")->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonMissingPath('data.0.body')->assertJsonPath('data.0.replaced_by_name', $owner->name);
    asKey($owner)->getJson("{$base}/body-revisions/{$revision->id}")->assertOk()->assertJsonPath('data.body', "    Earlier prose\n");
    asKey($owner, write: true)->postJson("{$base}/body-revisions/{$revision->id}/restore")
        ->assertOk()->assertJsonPath('data.body', "    Earlier prose\n");
    expect($entity->bodyRevisions()->count())->toBe(2);
    expect($entity->bodyRevisions()->where('body', 'Current prose')->exists())->toBeTrue();
});

it('refuses player history even on their own PC and keeps history out of entity JSON', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $entity = Entity::factory()->for($campaign)->pcOf($player)->forPlayers()->create();
    $revision = EntityBodyRevision::factory()->for($entity)->create(['body' => 'Secret royal parentage']);
    $base = "/api/v1/campaigns/{$campaign->id}/entities/{$entity->id}";
    asKey($player)->getJson($base)->assertOk()->assertJsonMissingPath('data.body_revisions')->assertDontSee('Secret royal parentage');
    asKey($player)->getJson("{$base}/body-revisions")->assertForbidden();
    asKey($player)->getJson("{$base}/body-revisions/{$revision->id}")->assertForbidden();
    asKey($player, write: true)->postJson("{$base}/body-revisions/{$revision->id}/restore")->assertForbidden();
});

it('refuses foreign revisions, missing credentials, nonmembers and read-only restores', function () {
    $campaign = Campaign::factory()->create();
    $owner = ownerOf($campaign);
    $entity = Entity::factory()->for($campaign)->create(['body' => 'Current body']);
    $other = Entity::factory()->for($campaign)->create();
    $revision = EntityBodyRevision::factory()->for($entity)->create();
    $otherRevision = EntityBodyRevision::factory()->for($other)->create();
    $foreignRevision = EntityBodyRevision::factory()->create();
    $base = "/api/v1/campaigns/{$campaign->id}/entities/{$entity->id}/body-revisions";
    foreach ([$otherRevision, $foreignRevision] as $wrongRevision) {
        asKey($owner)->getJson("{$base}/{$wrongRevision->id}")->assertNotFound();
        asKey($owner, write: true)->postJson("{$base}/{$wrongRevision->id}/restore")->assertNotFound();
    }
    asKey($owner)->postJson("{$base}/{$revision->id}/restore")->assertForbidden();
    asKey(User::factory()->create())->getJson($base)->assertNotFound();
    withoutKey()->getJson($base)->assertUnauthorized();
    asKey($owner, write: true)->postJson("{$base}/{$revision->id}/restore", ['body' => 'Smuggled text'])
        ->assertUnprocessable()->assertJsonValidationErrors('body');
    expect($entity->fresh()->body)->toBe('Current body');
    $entity->delete();
    asKey($owner)->getJson($base)->assertNotFound();
});

it('pages history without loading full bodies', function () {
    $entity = Entity::factory()->create();
    $campaign = $entity->campaign;
    EntityBodyRevision::factory()->for($entity)->count(26)->create(['body' => 'Historical secret']);
    $base = "/api/v1/campaigns/{$campaign->id}/entities/{$entity->id}/body-revisions";
    asKey(ownerOf($campaign))->getJson($base)->assertOk()->assertJsonCount(25, 'data')
        ->assertJsonPath('meta.total', 26)->assertDontSee('Historical secret');
    asKey(ownerOf($campaign))->getJson("{$base}?page=2")->assertOk()->assertJsonCount(1, 'data');
});
