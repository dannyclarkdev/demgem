<?php

use App\Enums\CampaignRole;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => $this->withoutVite())
    ->in('Feature');

/**
 * Adds a member with the given role to the campaign and returns the user.
 */
function memberOf(Campaign $campaign, CampaignRole $role, ?User $user = null): User
{
    $user ??= User::factory()->create();

    $campaign->members()->create(['user_id' => $user->id, 'role' => $role]);

    return $user;
}

/**
 * The owner user created by the campaign factory.
 */
function ownerOf(Campaign $campaign): User
{
    return $campaign->owner()->firstOrFail()->user;
}
