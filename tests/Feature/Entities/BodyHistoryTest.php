<?php

use App\Actions\Entities\RestoreEntityBody;
use App\Actions\Entities\UpdateEntity;
use App\Enums\CampaignRole;
use App\Livewire\Entities\History;
use App\Livewire\Entities\Show;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\EntityBodyRevision;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

it('preserves displaced bodies through stale saves and restores only the body', function () {
    $campaign = Campaign::factory()->create();
    $owner = ownerOf($campaign);
    $entity = Entity::factory()->for($campaign)->create(['body' => 'First body', 'dm_notes' => 'Private note']);
    $stale = $entity->fresh();
    $update = app(UpdateEntity::class);
    $this->freezeTime();
    $update->handle($entity, $owner, ['body' => 'Second body']);
    $this->travel(1)->seconds();
    $updated = $update->handle($stale, $owner, ['body' => 'Third body']);
    expect($entity->bodyRevisions()->orderBy('recorded_at')->pluck('body')->all())->toBe(['First body', 'Second body']);
    $first = $entity->bodyRevisions()->oldest('recorded_at')->firstOrFail();
    expect($first->replaced_by)->toBe($owner->id)->and($first->replaced_by_name)->toBe($owner->name);
    $restored = app(RestoreEntityBody::class)->handle($updated, $owner, $first->id);
    expect($restored->body)->toBe('First body')->and($restored->dm_notes)->toBe('Private note');
    expect($entity->bodyRevisions()->pluck('body')->all())->toBe(['First body', 'Second body', 'Third body']);
    app(RestoreEntityBody::class)->handle($restored, $owner, $first->id);
    expect($entity->bodyRevisions()->count())->toBe(3);
});

it('records neither creation nor unchanged bodies nor unrelated changes', function () {
    $campaign = Campaign::factory()->create();
    $entity = Entity::factory()->for($campaign)->create(['body' => null]);
    $update = app(UpdateEntity::class);
    $owner = ownerOf($campaign);
    expect($entity->bodyRevisions()->count())->toBe(0);
    $update->handle($entity, $owner, ['body' => '']);
    $update->handle($entity, $owner, ['dm_notes' => 'Changed notes']);
    expect($entity->bodyRevisions()->count())->toBe(0);
    $updated = $update->handle($entity, $owner, ['body' => "    Code\n\n"]);
    expect($entity->bodyRevisions()->sole()->body)->toBeNull();
    $empty = $entity->bodyRevisions()->sole();
    app(RestoreEntityBody::class)->handle($updated, $owner, $empty->id);
    expect($entity->fresh()->body)->toBeNull();
    expect($entity->bodyRevisions()->whereNotNull('body')->sole()->body)->toBe("    Code\n\n");
});

it('rolls the revision back when the entity save fails', function () {
    $campaign = Campaign::factory()->create();
    $entity = Entity::factory()->for($campaign)->create(['body' => 'Keep me']);
    $owner = ownerOf($campaign);
    expect(fn () => app(UpdateEntity::class)->handle($entity, $owner, ['body' => 'Failed save', 'parent_id' => 'missing-parent']))
        ->toThrow(QueryException::class);
    expect($entity->fresh()->body)->toBe('Keep me');
    expect($entity->bodyRevisions()->count())->toBe(0);
});

it('keeps history immutable while automatic rename housekeeping changes current links', function () {
    $campaign = Campaign::factory()->create();
    $owner = ownerOf($campaign);
    $target = Entity::factory()->for($campaign)->create(['name' => 'Old Duke', 'body' => 'I am [[Old Duke]].']);
    $source = Entity::factory()->for($campaign)->create(['body' => 'Knows [[Old Duke]].']);
    $update = app(UpdateEntity::class);
    $update->handle($source, $owner, ['body' => 'Fears [[Old Duke]].']);
    $update->handle($target, $owner, ['name' => 'New Duke']);
    expect($target->fresh()->body)->toBe('I am [[New Duke]].');
    expect($source->fresh()->body)->toBe('Fears [[New Duke]].');
    expect($source->bodyRevisions()->sole()->body)->toBe('Knows [[Old Duke]].');
    expect($target->bodyRevisions()->count())->toBe(0);
});

it('gates historical text even after an entity is revealed and its player may edit', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $entity = Entity::factory()->for($campaign)->pcOf($player)->forPlayers()->create(['body' => 'Secret royal parentage']);
    app(UpdateEntity::class)->handle($entity, $player, ['body' => 'Party knows this']);
    Livewire::actingAs($player)->test(Show::class, ['campaign' => $campaign, 'type' => 'characters', 'slug' => $entity->slug])
        ->assertDontSee('Body history')->assertDontSee('Secret royal parentage');
    Livewire::actingAs($player)->test(History::class, ['campaign' => $campaign, 'entity' => $entity])->assertForbidden();
    expect($entity->bodyRevisions()->sole()->replaced_by)->toBe($player->id);
});

it('lets only GM roles inspect and restore historical bodies', function (CampaignRole $role, bool $allowed) {
    $campaign = Campaign::factory()->create();
    $user = memberOf($campaign, $role);
    $entity = Entity::factory()->for($campaign)->create();
    expect(Gate::forUser($user)->allows('viewHistory', $entity))->toBe($allowed)
        ->and(Gate::forUser($user)->allows('restoreBody', $entity))->toBe($allowed);
})->with([
    [CampaignRole::CoGm, true],
    [CampaignRole::Player, false],
    [CampaignRole::Spectator, false],
]);

it('loads history on demand, escapes source text, and restores from the panel', function () {
    $campaign = Campaign::factory()->create();
    $owner = ownerOf($campaign);
    $entity = Entity::factory()->for($campaign)->create(['body' => 'Current prose']);
    $revision = EntityBodyRevision::factory()->for($entity)->create(['body' => '<script>alert("history")</script>']);
    Livewire::actingAs($owner)->test(History::class, ['campaign' => $campaign, 'entity' => $entity])
        ->assertDontSee('alert')->set('open', true)->call('select', $revision->id)
        ->assertSee('Current prose')->assertSee('<script>alert("history")</script>')
        ->assertDontSee('<script>alert("history")</script>', false)
        ->call('restore')->assertDispatched('entity-body-restored');
    expect($entity->fresh()->body)->toBe('<script>alert("history")</script>');
    expect($entity->bodyRevisions()->where('body', 'Current prose')->exists())->toBeTrue();
});

it('refuses revisions from another entity and revoked membership', function () {
    $campaign = Campaign::factory()->create();
    $owner = ownerOf($campaign);
    $entity = Entity::factory()->for($campaign)->create();
    $other = Entity::factory()->for($campaign)->create();
    $revision = EntityBodyRevision::factory()->for($other)->create();
    Livewire::actingAs($owner)->test(History::class, ['campaign' => $campaign, 'entity' => $entity])
        ->call('select', $revision->id)->assertNotFound();
    $screen = Livewire::actingAs($owner)->test(History::class, ['campaign' => $campaign, 'entity' => $entity]);
    $campaign->members()->where('user_id', $owner->id)->delete();
    $screen->call('select', $revision->id)->assertNotFound();
});

it('retains bodies after soft deletion and actor deletion but cascades with the entity', function () {
    $entity = Entity::factory()->create();
    $actor = User::factory()->create();
    $revision = EntityBodyRevision::factory()->for($entity)->create(['replaced_by' => $actor->id, 'replaced_by_name' => 'Former editor']);
    $actor->delete();
    expect($revision->fresh()->replaced_by)->toBeNull()->and($revision->fresh()->replaced_by_name)->toBe('Former editor');
    $entity->delete();
    expect(EntityBodyRevision::query()->find($revision->id))->not->toBeNull();
    $entity->forceDelete();
    expect(EntityBodyRevision::query()->find($revision->id))->toBeNull();
});
