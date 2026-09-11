<?php

use App\Actions\Campaigns\BuildCampaignArchive;
use App\Actions\Campaigns\ReadCampaignArchive;
use App\Enums\EntityType;
use App\Livewire\Campaigns\Settings;
use App\Livewire\Entities\Form;
use App\Models\Campaign;
use App\Models\Entity;
use App\Support\Storage\CampaignStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(fn () => Storage::fake('public'));

/**
 * A ceiling set from a real file's size, because the config takes megabytes and a
 * fake image is a few kilobytes. A fake PNG must be a real PNG: Media Library sniffs
 * the bytes, and an empty file is refused as not an image.
 */
function ceilingOf(int $bytes): void
{
    config()->set('campaigns.storage_limit_mb', $bytes / 1_048_576);
}

it('sums every original the campaign owns and nothing else', function () {
    $campaign = Campaign::factory()->create();
    $other = Campaign::factory()->create();

    // Held in variables: a fake upload deletes its file when it is collected.
    $mapFile = UploadedFile::fake()->image('map.png', 200, 200);
    $coverFile = UploadedFile::fake()->image('cover.png', 200, 100);
    $theirFile = UploadedFile::fake()->image('theirs.png', 900, 900);

    $map = Entity::factory()->for($campaign)->type(EntityType::Map)->create();
    $map->addMedia($mapFile->getRealPath())->usingFileName('map.png')->toMediaCollection('image');
    $campaign->addMedia($coverFile->getRealPath())->usingFileName('cover.png')->toMediaCollection('cover');

    $elsewhere = Entity::factory()->for($other)->create();
    $elsewhere->addMedia($theirFile->getRealPath())->usingFileName('theirs.png')->toMediaCollection('image');

    $expected = (int) $map->getFirstMedia('image')?->size + (int) $campaign->getFirstMedia('cover')?->size;

    expect($expected)->toBeGreaterThan(0)
        ->and(CampaignStorage::usedBytes($campaign))->toBe($expected)
        ->and(CampaignStorage::usedBytes($other))->toBe((int) $elsewhere->getFirstMedia('image')?->size);
});

it('refuses an image that would cross the ceiling, before the entity is written', function () {
    $campaign = Campaign::factory()->create();
    $image = UploadedFile::fake()->image('scan.png', 400, 400);

    ceilingOf((int) ($image->getSize() / 2));

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'characters'])
        ->set('name', 'Mara Voss')
        ->set('image', $image)
        ->call('save')
        ->assertHasErrors('image');

    expect(Entity::withoutGlobalScopes()->where('campaign_id', $campaign->id)->count())->toBe(0);
});

it('accepts an image that fits, and then refuses the one that does not', function () {
    $campaign = Campaign::factory()->create();
    $first = UploadedFile::fake()->image('first.png', 400, 400);
    $second = UploadedFile::fake()->image('second.png', 400, 400);

    // Room for one of the two, not both.
    ceilingOf((int) ($first->getSize() * 1.5));

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'characters'])
        ->set('name', 'Mara Voss')
        ->set('image', $first)
        ->call('save')
        ->assertHasNoErrors();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'characters'])
        ->set('name', 'Abbess Corvane')
        ->set('image', $second)
        ->call('save')
        ->assertHasErrors('image');

    expect(Entity::withoutGlobalScopes()->where('campaign_id', $campaign->id)->count())->toBe(1);
});

it('refuses a cover that would cross the ceiling and shows the bar on settings', function () {
    $campaign = Campaign::factory()->create();
    $cover = UploadedFile::fake()->image('cover.png', 400, 200);

    ceilingOf((int) ($cover->getSize() / 2));

    Livewire::actingAs(ownerOf($campaign))
        ->test(Settings::class, ['campaign' => $campaign])
        ->assertSee('Storage')
        ->assertSee('0 B of')
        ->set('cover', $cover)
        ->call('save')
        ->assertHasErrors('cover');

    expect($campaign->getFirstMedia('cover'))->toBeNull();
});

it('turns the check and the bar off at zero', function () {
    config()->set('campaigns.storage_limit_mb', 0);
    $campaign = Campaign::factory()->create();

    expect(CampaignStorage::isLimited())->toBeFalse()
        ->and(CampaignStorage::canStore($campaign, PHP_INT_MAX))->toBeTrue();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Settings::class, ['campaign' => $campaign])
        ->assertSee('no limit on this install');
});

it('trims an archive to the ceiling in document order and says how many it left', function () {
    $campaign = aCampaignWithPictures();
    $path = app(BuildCampaignArchive::class)->handle($campaign);

    // The archive names its media entries by ordinal, in document order.
    $sizes = collect(archiveEntries($path))
        ->filter(fn (string $bytes, string $name) => str_starts_with($name, 'media/'))
        ->sortKeys()
        ->map(fn (string $bytes) => strlen($bytes))
        ->values();

    expect($sizes)->toHaveCount(3);

    // Room for the first two files and not the third.
    ceilingOf($sizes[0] + $sizes[1] + (int) ($sizes[2] / 2));

    $result = app(ReadCampaignArchive::class)->handle($path);
    $report = $result->read?->report;

    expect($result->succeeded())->toBeTrue()
        ->and(count($result->restored))->toBe(2)
        ->and($report?->filesRestored)->toBe(2)
        ->and($report?->filesOverQuota)->toBe(1)
        ->and(collect($report?->losses())->pluck('label')->first(fn (string $label) => str_contains($label, 'storage limit')))
        ->toBe('1 file would put the campaign over its storage limit');

    // With room for everything, nothing is left behind.
    ceilingOf($sizes->sum() + 1);

    $full = app(ReadCampaignArchive::class)->handle($path);

    expect(count($full->restored))->toBe(3)
        ->and($full->read?->report->filesOverQuota)->toBe(0);
});
