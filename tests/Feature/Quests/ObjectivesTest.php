<?php

use App\Enums\CampaignRole;
use App\Livewire\Quests\Objectives;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\QuestObjective;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

function quest(?Campaign $campaign = null): Entity
{
    $campaign ??= Campaign::factory()->create();

    return Entity::factory()->for($campaign)->quest()->forPlayers()->create(['name' => 'The Toll Bridge']);
}

it('adds an objective at the end of the list', function () {
    $quest = quest();

    Livewire::actingAs(ownerOf($quest->campaign))
        ->test(Objectives::class, ['campaign' => $quest->campaign, 'quest' => $quest])
        ->set('newBody', 'Find who paid the guard')
        ->call('add')
        ->assertHasNoErrors()
        ->assertSet('newBody', '');

    $objective = $quest->objectives()->sole();

    expect($objective->body)->toBe('Find who paid the guard')
        ->and($objective->position)->toBe(0)
        ->and($objective->campaign_id)->toBe($quest->campaign_id)
        ->and($objective->completed_at)->toBeNull();
});

it('requires a body and caps its length', function () {
    $quest = quest();

    $component = Livewire::actingAs(ownerOf($quest->campaign))
        ->test(Objectives::class, ['campaign' => $quest->campaign, 'quest' => $quest]);

    $component->set('newBody', '')->call('add')->assertHasErrors('newBody');
    $component->set('newBody', str_repeat('a', 201))->call('add')->assertHasErrors('newBody');

    expect($quest->objectives()->count())->toBe(0);
});

it('edits and removes an objective', function () {
    $quest = quest();
    $objective = QuestObjective::factory()->forQuest($quest)->create(['body' => 'Old wording']);

    $component = Livewire::actingAs(ownerOf($quest->campaign))
        ->test(Objectives::class, ['campaign' => $quest->campaign, 'quest' => $quest])
        ->call('edit', $objective->id)
        ->assertSet('editingBody', 'Old wording')
        ->set('editingBody', 'New wording')
        ->call('save')
        ->assertSet('editingId', null);

    expect($objective->refresh()->body)->toBe('New wording');

    $component->call('remove', $objective->id);

    expect($quest->objectives()->count())->toBe(0);
});

it('ticks and unticks an objective', function () {
    $quest = quest();
    $objective = QuestObjective::factory()->forQuest($quest)->create();

    $component = Livewire::actingAs(ownerOf($quest->campaign))
        ->test(Objectives::class, ['campaign' => $quest->campaign, 'quest' => $quest]);

    $component->call('toggle', $objective->id);

    expect($objective->refresh()->isComplete())->toBeTrue()
        ->and($objective->completed_in_session_id)->toBeNull();

    $component->call('toggle', $objective->id);

    expect($objective->refresh()->isComplete())->toBeFalse();
});

it('reorders by drag and by button and keeps positions contiguous', function () {
    $quest = quest();

    $objectives = collect(['First', 'Second', 'Third'])
        ->map(fn (string $body, int $index) => QuestObjective::factory()->forQuest($quest, $index)->create(['body' => $body]));

    $component = Livewire::actingAs(ownerOf($quest->campaign))
        ->test(Objectives::class, ['campaign' => $quest->campaign, 'quest' => $quest]);

    $component->call('reorder', $objectives[2]->id, 0);

    expect($quest->objectives()->pluck('body')->all())->toBe(['Third', 'First', 'Second']);

    $component->call('move', $objectives[2]->id, 1);

    expect($quest->objectives()->pluck('body')->all())->toBe(['First', 'Third', 'Second'])
        ->and($quest->objectives()->pluck('position')->all())->toBe([0, 1, 2]);
});

it('lets a player read the objectives on a quest they can see', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $quest = quest($campaign);

    QuestObjective::factory()->forQuest($quest, 0)->complete()->create(['body' => 'Cross the bridge']);
    QuestObjective::factory()->forQuest($quest, 1)->create(['body' => 'Pay nobody']);

    Livewire::actingAs($player)
        ->test(Objectives::class, ['campaign' => $campaign, 'quest' => $quest])
        ->assertSet('canManage', false)
        ->assertSee('Cross the bridge')
        ->assertSee('Pay nobody')
        ->assertSee('1 of 2');
});

it('refuses every write from a player, including on their own PC campaign', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $quest = quest($campaign);
    $objective = QuestObjective::factory()->forQuest($quest)->create();

    $component = fn () => Livewire::actingAs($player)
        ->test(Objectives::class, ['campaign' => $campaign, 'quest' => $quest]);

    $component()->set('newBody', 'Mine now')->call('add')->assertForbidden();
    $component()->call('toggle', $objective->id)->assertForbidden();
    $component()->call('remove', $objective->id)->assertForbidden();
    $component()->call('edit', $objective->id)->assertForbidden();
    $component()->call('reorder', $objective->id, 0)->assertForbidden();

    expect($objective->refresh()->isComplete())->toBeFalse()
        ->and($quest->objectives()->count())->toBe(1);
});

it('404s for a player who cannot see the quest at all', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $hidden = Entity::factory()->for($campaign)->quest()->dmOnly()->create();

    Livewire::actingAs($player)
        ->test(Objectives::class, ['campaign' => $campaign, 'quest' => $hidden])
        ->assertForbidden();
});

it('refuses an objective from another quest', function () {
    $campaign = Campaign::factory()->create();
    $quest = quest($campaign);
    $other = Entity::factory()->for($campaign)->quest()->create();
    $stranger = QuestObjective::factory()->forQuest($other)->create();

    expect(fn () => Livewire::actingAs(ownerOf($campaign))
        ->test(Objectives::class, ['campaign' => $campaign, 'quest' => $quest])
        ->call('toggle', $stranger->id))->toThrow(ModelNotFoundException::class);

    expect($stranger->refresh()->isComplete())->toBeFalse();
});

it('does not let a co-GM removed from the campaign keep ticking', function () {
    $campaign = Campaign::factory()->create();
    $coGm = memberOf($campaign, CampaignRole::CoGm);
    $quest = quest($campaign);
    $objective = QuestObjective::factory()->forQuest($quest)->create();

    $component = Livewire::actingAs($coGm)
        ->test(Objectives::class, ['campaign' => $campaign, 'quest' => $quest]);

    $campaign->members()->where('user_id', $coGm->id)->delete();

    $component->call('toggle', $objective->id)->assertStatus(404);

    expect($objective->refresh()->isComplete())->toBeFalse();
});
