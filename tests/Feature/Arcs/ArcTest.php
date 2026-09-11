<?php

use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Enums\QuestStatus;
use App\Livewire\Entities\Form;
use App\Livewire\Entities\Show;
use App\Livewire\Sessions\Form as SessionForm;
use App\Livewire\Sessions\Show as SessionShow;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\GameSession;
use Livewire\Livewire;

/**
 * An arc with two quests and two sessions, one of each hidden from the party. The
 * leak test for the arc page reads it from a player's seat.
 */
function anArcWithChapters(): array
{
    $campaign = Campaign::factory()->create();
    $arc = Entity::factory()->for($campaign)->arc()->forPlayers()->create(['name' => 'The Drowned Duke']);

    $open = Entity::factory()->for($campaign)->quest(QuestStatus::Active)->forPlayers()->inArc($arc)->create(['name' => 'Seal The Undercity']);
    $hidden = Entity::factory()->for($campaign)->quest(QuestStatus::Available)->dmOnly()->inArc($arc)->create(['name' => 'Wake The Sleeper']);

    $played = GameSession::factory()->for($campaign)->number(1)->played()->inArc($arc)->create(['title' => 'The Harbor Fire']);
    $draft = GameSession::factory()->for($campaign)->number(2)->hidden()->inArc($arc)->create(['title' => 'Under The Pilings']);

    return compact('campaign', 'arc', 'open', 'hidden', 'played', 'draft');
}

it('lists an arc\'s quests by status and its sessions in order for a GM', function () {
    ['campaign' => $campaign, 'arc' => $arc] = anArcWithChapters();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Show::class, ['campaign' => $campaign, 'type' => 'arcs', 'slug' => $arc->slug])
        ->assertSee('Seal The Undercity')
        ->assertSee('Wake The Sleeper')
        ->assertSee('The Harbor Fire')
        ->assertSee('Under The Pilings')
        ->assertSeeInOrder(['Available', 'Wake The Sleeper', 'Active', 'Seal The Undercity'])
        ->assertSeeInOrder(['The Harbor Fire', 'Under The Pilings']);
});

it('shows a player only the quests and sessions in an arc that they may see', function () {
    ['campaign' => $campaign, 'arc' => $arc] = anArcWithChapters();
    $player = memberOf($campaign, CampaignRole::Player);

    $component = Livewire::actingAs($player)
        ->test(Show::class, ['campaign' => $campaign, 'type' => 'arcs', 'slug' => $arc->slug])
        ->assertSee('Seal The Undercity')
        ->assertSee('The Harbor Fire')
        ->assertDontSee('Wake The Sleeper')
        ->assertDontSee('Under The Pilings');

    expect(json_encode($component->snapshot))->not->toContain('Wake The Sleeper')
        ->not->toContain('Under The Pilings');
});

it('lets a GM file a quest under an arc from the form, and take it out again', function () {
    $campaign = Campaign::factory()->create();
    $arc = Entity::factory()->for($campaign)->arc()->create(['name' => 'The Salt Road']);
    $quest = Entity::factory()->for($campaign)->quest()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'quests', 'slug' => $quest->slug])
        ->set('arc_id', $arc->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($quest->refresh()->arc_id)->toBe($arc->id);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'quests', 'slug' => $quest->slug])
        ->set('arc_id', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($quest->refresh()->arc_id)->toBeNull();
});

it('refuses an arc that is not an arc, and one from another campaign', function () {
    $campaign = Campaign::factory()->create();
    $notAnArc = Entity::factory()->for($campaign)->type(EntityType::Character)->create();
    $elsewhere = Entity::factory()->arc()->create();
    $quest = Entity::factory()->for($campaign)->quest()->create();

    foreach ([$notAnArc, $elsewhere] as $wrong) {
        Livewire::actingAs(ownerOf($campaign))
            ->test(Form::class, ['campaign' => $campaign, 'type' => 'quests', 'slug' => $quest->slug])
            ->set('arc_id', $wrong->id)
            ->call('save')
            ->assertHasErrors('arc_id');
    }
});

it('refuses an arc on anything that is not a quest', function () {
    $campaign = Campaign::factory()->create();
    $arc = Entity::factory()->for($campaign)->arc()->create();
    $note = Entity::factory()->for($campaign)->type(EntityType::Note)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'notes', 'slug' => $note->slug])
        ->set('arc_id', $arc->id)
        ->call('save')
        ->assertHasErrors('arc_id');
});

it('names the arc on a quest page only when the viewer may see the arc', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $arc = Entity::factory()->for($campaign)->arc()->dmOnly()->create(['name' => 'The Drowned Duke']);
    $quest = Entity::factory()->for($campaign)->quest()->forPlayers()->inArc($arc)->create(['name' => 'The Ledger']);

    $component = Livewire::actingAs($player)
        ->test(Show::class, ['campaign' => $campaign, 'type' => 'quests', 'slug' => $quest->slug])
        ->assertViewHas('arc', null)
        ->assertDontSee('The Drowned Duke')
        ->assertDontSee('Part of');

    expect(json_encode($component->snapshot))->not->toContain('The Drowned Duke');

    Livewire::actingAs(ownerOf($campaign))
        ->test(Show::class, ['campaign' => $campaign, 'type' => 'quests', 'slug' => $quest->slug])
        ->assertViewHas('arc', fn (?Entity $found) => $found?->is($arc))
        ->assertSee('The Drowned Duke');
});

it('lets a GM file a session under an arc, and shows it on the session page', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $arc = Entity::factory()->for($campaign)->arc()->forPlayers()->create(['name' => 'The Salt Road']);
    $session = GameSession::factory()->for($campaign)->number(3)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(SessionForm::class, ['campaign' => $campaign, 'number' => 3])
        ->set('arc_id', $arc->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($session->refresh()->arc_id)->toBe($arc->id);

    Livewire::actingAs($player)
        ->test(SessionShow::class, ['campaign' => $campaign, 'number' => 3])
        ->assertSee('The Salt Road');
});

it('keeps a hidden arc off a session page the party can read', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $arc = Entity::factory()->for($campaign)->arc()->dmOnly()->create(['name' => 'The Drowned Duke']);
    GameSession::factory()->for($campaign)->number(3)->inArc($arc)->create();

    $component = Livewire::actingAs($player)
        ->test(SessionShow::class, ['campaign' => $campaign, 'number' => 3])
        ->assertDontSee('The Drowned Duke');

    expect(json_encode($component->snapshot))->not->toContain('The Drowned Duke');
});

it('leaves the quests and sessions in place when the arc is deleted', function () {
    ['campaign' => $campaign, 'arc' => $arc, 'open' => $quest, 'played' => $session] = anArcWithChapters();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Show::class, ['campaign' => $campaign, 'type' => 'arcs', 'slug' => $arc->slug])
        ->call('delete');

    expect($quest->refresh()->arc_id)->toBeNull()
        ->and($session->refresh()->arc_id)->toBeNull()
        ->and($quest->trashed())->toBeFalse();
});
