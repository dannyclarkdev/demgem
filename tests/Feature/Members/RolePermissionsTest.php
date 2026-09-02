<?php

use App\Enums\CampaignRole;
use App\Models\Campaign;
use App\Models\User;

$matrix = [
    'view' => ['owner' => true, 'co_gm' => true, 'player' => true, 'spectator' => true],
    'update' => ['owner' => true, 'co_gm' => true, 'player' => false, 'spectator' => false],
    'delete' => ['owner' => true, 'co_gm' => false, 'player' => false, 'spectator' => false],
    'manageMembers' => ['owner' => true, 'co_gm' => true, 'player' => false, 'spectator' => false],
    'changeRoles' => ['owner' => true, 'co_gm' => false, 'player' => false, 'spectator' => false],
    'transferOwnership' => ['owner' => true, 'co_gm' => false, 'player' => false, 'spectator' => false],
    'createInvite' => ['owner' => true, 'co_gm' => true, 'player' => false, 'spectator' => false],
    'leave' => ['owner' => false, 'co_gm' => true, 'player' => true, 'spectator' => true],
];

$cases = [];
foreach ($matrix as $ability => $roles) {
    foreach ($roles as $role => $allowed) {
        $cases["{$role} ".($allowed ? 'can' : 'cannot')." {$ability}"] = [CampaignRole::from($role), $ability, $allowed];
    }
}

test('campaign policy matrix', function (CampaignRole $role, string $ability, bool $expected) {
    $campaign = Campaign::factory()->create();
    $user = memberOf($campaign, $role);

    expect($user->can($ability, $campaign))->toBe($expected);
})->with($cases);

test('a non-member has no abilities', function (string $ability) {
    $campaign = Campaign::factory()->create();

    expect(User::factory()->create()->can($ability, $campaign))->toBeFalse();
})->with(['view', 'update', 'delete', 'manageMembers', 'changeRoles', 'transferOwnership', 'createInvite', 'leave']);

test('remove member rules', function (CampaignRole $actor, CampaignRole $target, bool $expected) {
    $campaign = Campaign::factory()->create();
    $actorUser = memberOf($campaign, $actor);
    $targetMember = $campaign->members()->create(['user_id' => User::factory()->create()->id, 'role' => $target]);

    expect($actorUser->can('removeMember', [$campaign, $targetMember]))->toBe($expected);
})->with([
    'owner removes co_gm' => [CampaignRole::Owner, CampaignRole::CoGm, true],
    'owner removes player' => [CampaignRole::Owner, CampaignRole::Player, true],
    'owner removes spectator' => [CampaignRole::Owner, CampaignRole::Spectator, true],
    'co_gm removes player' => [CampaignRole::CoGm, CampaignRole::Player, true],
    'co_gm removes spectator' => [CampaignRole::CoGm, CampaignRole::Spectator, true],
    'co_gm cannot remove co_gm' => [CampaignRole::CoGm, CampaignRole::CoGm, false],
    'player cannot remove player' => [CampaignRole::Player, CampaignRole::Player, false],
    'spectator cannot remove player' => [CampaignRole::Spectator, CampaignRole::Player, false],
]);

test('nobody removes the owner or themselves through removeMember', function () {
    $campaign = Campaign::factory()->create();
    $owner = ownerOf($campaign);
    $coGm = memberOf($campaign, CampaignRole::CoGm);
    $ownerMember = $campaign->owner()->firstOrFail();
    $coGmMember = $campaign->memberFor($coGm);

    expect($coGm->can('removeMember', [$campaign, $ownerMember]))->toBeFalse()
        ->and($owner->can('removeMember', [$campaign, $ownerMember]))->toBeFalse()
        ->and($coGm->can('removeMember', [$campaign, $coGmMember]))->toBeFalse();
});

test('a member of another campaign cannot be removed through this campaign', function () {
    $campaign = Campaign::factory()->create();
    $other = Campaign::factory()->create();
    $otherMember = $other->members()->create(['user_id' => User::factory()->create()->id, 'role' => CampaignRole::Player]);

    expect(ownerOf($campaign)->can('removeMember', [$campaign, $otherMember]))->toBeFalse();
});
