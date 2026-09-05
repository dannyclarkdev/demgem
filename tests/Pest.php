<?php

use App\Actions\Campaigns\ExportCampaign;
use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
 * The same, from an export instance a test already holds. Only the archive tests
 * need this, and only because forArchive() returns a clone worth keeping hold of.
 *
 * @return array<string, mixed>
 */
function exportedArrayFrom(ExportCampaign $export, Campaign $campaign): array
{
    $walked = array_map(
        fn ($section) => is_iterable($section) && ! is_array($section) ? iterator_to_array($section) : $section,
        $export->handle($campaign),
    );

    return json_decode(json_encode($walked, JSON_THROW_ON_ERROR), true);
}

/**
 * A campaign with a map image and a two-page handout, which is the smallest thing
 * that exercises both media collections. Shared, because the archive tests all need
 * a campaign with real bytes in it.
 */
function aCampaignWithPictures(): Campaign
{
    $campaign = Campaign::factory()->create(['name' => 'The Drowned Duchy']);

    $map = Entity::factory()->for($campaign)->type(EntityType::Map)->forPlayers()
        ->create(['name' => 'The Duchy of Vell', 'slug' => 'vell']);

    $file = UploadedFile::fake()->image('the duchy of vell.png', 400, 300);
    $map->addMedia($file->getRealPath())->usingFileName('the duchy of vell.png')->toMediaCollection('image');

    $handout = Entity::factory()->for($campaign)->type(EntityType::Handout)->dmOnly()
        ->create(['name' => "The duke's letter", 'slug' => 'dukes-letter']);

    foreach (['front.png', 'back.png'] as $page) {
        $scan = UploadedFile::fake()->image($page, 300, 400);
        $handout->addMedia($scan->getRealPath())->usingFileName($page)->toMediaCollection('files');
    }

    return $campaign;
}

/**
 * The owner user created by the campaign factory.
 */
function ownerOf(Campaign $campaign): User
{
    return $campaign->owner()->firstOrFail()->user;
}
