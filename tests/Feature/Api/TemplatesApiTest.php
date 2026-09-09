<?php

use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\EntityTemplate;
use App\Models\User;

it('creates and edits templates through the API and lists summaries without prose', function () {
    $campaign = Campaign::factory()->create();
    $owner = ownerOf($campaign);
    $base = "/api/v1/campaigns/{$campaign->id}/entity-templates";
    $created = asKey($owner, write: true)->postJson($base, ['type' => 'character', 'name' => 'NPC outline', 'body' => "    Keep indentation\n"])
        ->assertCreated()->assertJsonPath('data.body', "    Keep indentation\n");
    $id = $created->json('data.id');
    asKey($owner)->getJson($base)->assertOk()->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.body');
    asKey($owner, write: true)->patchJson("{$base}/{$id}", ['name' => 'Location outline', 'type' => 'location'])
        ->assertOk()->assertJsonPath('data.type', 'location')->assertJsonPath('data.body', "    Keep indentation\n");
    asKey($owner)->getJson("{$base}?type=character")->assertOk()->assertJsonCount(0, 'data');
    asKey($owner)->getJson("{$base}/{$id}")->assertOk()->assertJsonPath('data.name', 'Location outline');
    expect(EntityTemplate::query()->findOrFail($id)->name)->toBe('Location outline');
});

it('uses a template only as a creation default and preserves an explicit empty override', function () {
    $campaign = Campaign::factory()->create();
    $owner = ownerOf($campaign);
    $template = EntityTemplate::factory()->for($campaign)->create(['body' => "## Wants\n\n"]);
    $base = "/api/v1/campaigns/{$campaign->id}/entities";
    $created = asKey($owner, write: true)->postJson($base, ['name' => 'Mara', 'type' => 'characters', 'template_id' => $template->id])
        ->assertCreated()->assertJsonPath('data.body', "## Wants\n\n");
    asKey($owner, write: true)->postJson($base, ['name' => 'Wren', 'type' => 'characters', 'template_id' => $template->id, 'body' => null])
        ->assertCreated()->assertJsonPath('data.body', null);
    asKey($owner, write: true)->postJson($base, ['name' => 'Tobin', 'type' => 'characters', 'template_id' => $template->id, 'body' => 'Custom body'])
        ->assertCreated()->assertJsonPath('data.body', 'Custom body');
    $template->delete();
    expect(Entity::query()->findOrFail($created->json('data.id'))->body)->toBe("## Wants\n\n");
});

it('refuses wrong type, cross-campaign template defaults, and applying templates on update', function () {
    $campaign = Campaign::factory()->create();
    $owner = ownerOf($campaign);
    $template = EntityTemplate::factory()->for($campaign)->create(['type' => EntityType::Location]);
    $foreign = EntityTemplate::factory()->create();
    $entity = Entity::factory()->for($campaign)->create();
    $base = "/api/v1/campaigns/{$campaign->id}/entities";
    asKey($owner, write: true)->postJson($base, ['name' => 'Mara', 'type' => 'characters', 'template_id' => $template->id])
        ->assertUnprocessable()->assertJsonValidationErrors('template_id');
    asKey($owner, write: true)->postJson($base, ['name' => 'Mara', 'type' => 'characters', 'template_id' => $foreign->id])->assertNotFound();
    asKey($owner, write: true)->patchJson("{$base}/{$entity->id}", ['template_id' => $template->id])
        ->assertUnprocessable()->assertJsonValidationErrors('template_id');
});

it('enforces roles, write abilities, membership, and authentication for templates', function () {
    $campaign = Campaign::factory()->create();
    $owner = ownerOf($campaign);
    $player = memberOf($campaign, CampaignRole::Player);
    $template = EntityTemplate::factory()->for($campaign)->create(['body' => 'Unrevealed story outline']);
    $base = "/api/v1/campaigns/{$campaign->id}/entity-templates";
    asKey($player)->getJson($base)->assertForbidden()->assertDontSee('Unrevealed story outline');
    asKey($player)->getJson("{$base}/{$template->id}")->assertForbidden();
    asKey($player, write: true)->postJson($base, ['type' => 'note', 'name' => 'No access'])->assertForbidden();
    asKey($owner)->postJson($base, ['type' => 'note', 'name' => 'No write ability'])->assertForbidden();
    asKey($owner)->patchJson("{$base}/{$template->id}", ['body' => 'Cannot change'])->assertForbidden();
    asKey(User::factory()->create())->getJson($base)->assertNotFound();
    withoutKey()->getJson($base)->assertUnauthorized();
    $campaign->members()->where('user_id', $owner->id)->delete();
    asKey($owner, write: true)->patchJson("{$base}/{$template->id}", ['body' => 'Removed member'])->assertNotFound();
});

it('rejects invalid and forbidden template fields with named validation errors', function (array $data, string $field) {
    $campaign = Campaign::factory()->create();
    asKey(ownerOf($campaign), write: true)->postJson("/api/v1/campaigns/{$campaign->id}/entity-templates", [
        'name' => 'An outline', 'type' => 'note', ...$data,
    ])->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    [['name' => ' '], 'name'],
    [['name' => str_repeat('n', 121)], 'name'],
    [['type' => 'dragon'], 'type'],
    [['body' => str_repeat('b', 100001)], 'body'],
    [['campaign_id' => 'another-campaign'], 'campaign_id'],
    [['dm_notes' => 'secret'], 'dm_notes'],
]);

it('paginates template summaries and excludes their prose from campaign search', function () {
    $campaign = Campaign::factory()->create();
    EntityTemplate::factory()->for($campaign)->count(51)->create(['body' => 'Unrevealed outline phrase']);
    $owner = ownerOf($campaign);
    $base = "/api/v1/campaigns/{$campaign->id}";
    asKey($owner)->getJson("{$base}/entity-templates")->assertOk()->assertJsonCount(50, 'data')
        ->assertJsonPath('meta.total', 51)->assertDontSee('Unrevealed outline phrase');
    asKey($owner)->getJson("{$base}/entity-templates?page=2")->assertOk()->assertJsonCount(1, 'data');
    asKey($owner)->getJson("{$base}/search?q=Unrevealed")->assertOk()->assertJsonCount(0, 'data');
});
