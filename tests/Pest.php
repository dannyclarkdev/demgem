<?php

use App\Actions\Campaigns\ExportCampaign;
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
 * A campaign's export as a plain array.
 *
 * ExportCampaign streams: every section is a LazyCollection so the download starts at
 * once, which means a test has to walk them before json_encode can. Several import
 * tests need this, so it lives here rather than in whichever file wrote it first.
 *
 * @return array<string, mixed>
 */
function exportedArray(Campaign $campaign): array
{
    $export = app(ExportCampaign::class)->handle($campaign);

    $walked = array_map(
        fn ($section) => is_iterable($section) && ! is_array($section) ? iterator_to_array($section) : $section,
        $export,
    );

    return json_decode(json_encode($walked, JSON_THROW_ON_ERROR), true);
}

/**
 * The owner user created by the campaign factory.
 */
function ownerOf(Campaign $campaign): User
{
    return $campaign->owner()->firstOrFail()->user;
}
