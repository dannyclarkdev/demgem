<?php

use App\Enums\CampaignRole;
use App\Livewire\Campaigns\Members;
use App\Livewire\Campaigns\Settings;
use App\Models\Campaign;
use Livewire\Livewire;

it('renders the members page for every role', function (CampaignRole $role) {
    $campaign = Campaign::factory()->create();
    $user = memberOf($campaign, $role);

    $this->actingAs($user)
        ->get(route('campaigns.members', $campaign))
        ->assertOk()
        ->assertSeeLivewire(Members::class)
        ->assertSee($user->name);
})->with([
    'owner' => CampaignRole::Owner,
    'co_gm' => CampaignRole::CoGm,
    'player' => CampaignRole::Player,
    'spectator' => CampaignRole::Spectator,
]);

it('shows invite controls to DM roles only', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    $this->actingAs(ownerOf($campaign))->get(route('campaigns.members', $campaign))->assertSee('Invite links');
    $this->actingAs($player)->get(route('campaigns.members', $campaign))->assertDontSee('Invite links');
});

it('lets the owner remove a player', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $member = $campaign->memberFor($player);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Members::class, ['campaign' => $campaign])
        ->call('remove', $member->id)
        ->assertHasNoErrors();

    $this->assertModelMissing($member);
});

it('lets a co-GM remove a player but not another co-GM', function () {
    $campaign = Campaign::factory()->create();
    $coGm = memberOf($campaign, CampaignRole::CoGm);
    $otherCoGm = memberOf($campaign, CampaignRole::CoGm);
    $player = memberOf($campaign, CampaignRole::Player);

    Livewire::actingAs($coGm)
        ->test(Members::class, ['campaign' => $campaign])
        ->call('remove', $campaign->memberFor($player)->id)
        ->assertHasNoErrors();

    Livewire::actingAs($coGm)
        ->test(Members::class, ['campaign' => $campaign])
        ->call('remove', $campaign->memberFor($otherCoGm)->id)
        ->assertForbidden();

    expect($campaign->fresh()->roleFor($player))->toBeNull()
        ->and($campaign->fresh()->roleFor($otherCoGm))->toBe(CampaignRole::CoGm);
});

it('forbids the owner from removing themselves', function () {
    $campaign = Campaign::factory()->create();
    $owner = ownerOf($campaign);

    Livewire::actingAs($owner)
        ->test(Members::class, ['campaign' => $campaign])
        ->call('remove', $campaign->owner()->firstOrFail()->id)
        ->assertForbidden();

    expect($campaign->fresh()->roleFor($owner))->toBe(CampaignRole::Owner);
});

it('lets the owner change a player into a co-GM', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Members::class, ['campaign' => $campaign])
        ->call('changeRole', $campaign->memberFor($player)->id, CampaignRole::CoGm->value)
        ->assertHasNoErrors();

    expect($campaign->fresh()->roleFor($player))->toBe(CampaignRole::CoGm);
});

it('forbids a co-GM from changing roles', function () {
    $campaign = Campaign::factory()->create();
    $coGm = memberOf($campaign, CampaignRole::CoGm);
    $player = memberOf($campaign, CampaignRole::Player);

    Livewire::actingAs($coGm)
        ->test(Members::class, ['campaign' => $campaign])
        ->call('changeRole', $campaign->memberFor($player)->id, CampaignRole::CoGm->value)
        ->assertForbidden();

    expect($campaign->fresh()->roleFor($player))->toBe(CampaignRole::Player);
});

it('does not grant the owner role through changeRole', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Members::class, ['campaign' => $campaign])
        ->call('changeRole', $campaign->memberFor($player)->id, CampaignRole::Owner->value)
        ->assertForbidden();

    expect($campaign->fresh()->owner()->count())->toBe(1);
});

it('lets a player leave', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    Livewire::actingAs($player)
        ->test(Members::class, ['campaign' => $campaign])
        ->call('leave')
        ->assertRedirect(route('campaigns.index'));

    expect($campaign->fresh()->roleFor($player))->toBeNull();
});

it('forbids the owner from leaving', function () {
    $campaign = Campaign::factory()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Members::class, ['campaign' => $campaign])
        ->call('leave')
        ->assertForbidden();

    expect($campaign->fresh()->owner()->count())->toBe(1);
});

it('transfers ownership in one step', function () {
    $campaign = Campaign::factory()->create();
    $owner = ownerOf($campaign);
    $player = memberOf($campaign, CampaignRole::Player);

    Livewire::actingAs($owner)
        ->test(Settings::class, ['campaign' => $campaign])
        ->set('newOwnerId', (string) $campaign->memberFor($player)->id)
        ->call('transfer')
        ->assertHasNoErrors()
        ->assertRedirect(route('campaigns.show', $campaign));

    $campaign = $campaign->fresh();

    expect($campaign->roleFor($player))->toBe(CampaignRole::Owner)
        ->and($campaign->roleFor($owner))->toBe(CampaignRole::CoGm)
        ->and($campaign->owner()->count())->toBe(1);
});

it('rejects a transfer to a member of another campaign', function () {
    $campaign = Campaign::factory()->create();
    $other = Campaign::factory()->create();
    $stranger = memberOf($other, CampaignRole::Player);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Settings::class, ['campaign' => $campaign])
        ->set('newOwnerId', (string) $other->memberFor($stranger)->id)
        ->call('transfer')
        ->assertHasErrors(['newOwnerId']);

    expect($campaign->fresh()->roleFor(ownerOf($campaign)))->toBe(CampaignRole::Owner);
});
