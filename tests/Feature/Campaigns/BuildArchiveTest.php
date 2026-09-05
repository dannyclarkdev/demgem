<?php

use App\Actions\Campaigns\BuildCampaignArchive;
use App\Actions\Campaigns\ExportCampaign;
use App\Enums\CampaignRole;
use App\Models\Campaign;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('public'));

/**
 * Opens an archive and hands back its entries, so a test can say what is in one
 * without any of them learning how a zip works.
 *
 * @return array<string, string>
 */
function archiveEntries(string $path): array
{
    $zip = new ZipArchive;

    expect($zip->open($path))->toBeTrue();

    $entries = [];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string) $zip->getNameIndex($i);
        $entries[$name] = (string) $zip->getFromIndex($i);
    }

    $zip->close();

    return $entries;
}

it('puts the document, the media and a readme in one file', function () {
    $campaign = aCampaignWithPictures();

    $entries = archiveEntries(app(BuildCampaignArchive::class)->handle($campaign));

    expect($entries)->toHaveKey('campaign.json')
        ->and($entries)->toHaveKey('README.md')
        ->and($entries['README.md'])->toContain('The Drowned Duchy')
        ->and(array_keys($entries))->toContain('media/0001-the-duchy-of-vell.png');

    $media = collect(array_keys($entries))->filter(fn (string $name) => str_starts_with($name, 'media/'));

    expect($media)->toHaveCount(3);
});

it('names every media entry itself, from an ordinal and a slug', function () {
    $campaign = aCampaignWithPictures();

    $entries = array_keys(archiveEntries(app(BuildCampaignArchive::class)->handle($campaign)));

    // The name is generated here rather than taken from the file, which is the
    // writing half of the rule the importer's safety rests on. A space in the
    // uploaded filename never reaches the archive.
    expect($entries)->toContain('media/0001-the-duchy-of-vell.png')
        ->and(collect($entries)->filter(fn ($n) => str_contains($n, ' ')))->toBeEmpty();
});

it('points every archive_path at an entry that is really there', function () {
    $campaign = aCampaignWithPictures();

    $path = app(BuildCampaignArchive::class)->handle($campaign);
    $entries = archiveEntries($path);

    $document = json_decode($entries['campaign.json'], true, 512, JSON_THROW_ON_ERROR);

    $referenced = [];

    foreach ($document['entities'] as $entity) {
        if (isset($entity['image']['archive_path'])) {
            $referenced[] = $entity['image']['archive_path'];
        }

        foreach ($entity['files'] ?? [] as $file) {
            if (isset($file['archive_path'])) {
                $referenced[] = $file['archive_path'];
            }
        }
    }

    expect($referenced)->toHaveCount(3);

    foreach ($referenced as $entry) {
        expect($entries)->toHaveKey($entry)
            ->and($entries[$entry])->not->toBe('');
    }
});

it('leaves the plain JSON export exactly as it was', function () {
    $campaign = aCampaignWithPictures();

    $plain = exportedArray($campaign);

    // archive_path belongs to an archive. Anybody already reading the JSON download
    // must see the document they have always seen.
    foreach ($plain['entities'] as $entity) {
        expect($entity['image'] ?? [])->not->toHaveKey('archive_path');

        foreach ($entity['files'] ?? [] as $file) {
            expect($file)->not->toHaveKey('archive_path');
        }
    }
});

it('does not leak the archive flag into the next export', function () {
    $campaign = aCampaignWithPictures();
    $export = app(ExportCampaign::class);

    // forArchive() clones rather than setting a flag, because the action comes from
    // the container and a shared instance would carry it into a plain download.
    json_encode(exportedArrayFrom($export->forArchive(), $campaign), JSON_THROW_ON_ERROR);

    $plain = exportedArrayFrom($export, $campaign);

    expect($plain['entities'][0]['image'] ?? [])->not->toHaveKey('archive_path');
});

it('builds a valid archive for a campaign with no pictures at all', function () {
    $campaign = Campaign::factory()->create(['name' => 'Bare bones']);

    $entries = archiveEntries(app(BuildCampaignArchive::class)->handle($campaign));

    expect($entries)->toHaveKey('campaign.json')
        ->and(collect(array_keys($entries))->filter(fn ($n) => str_starts_with($n, 'media/')))->toBeEmpty();
});

it('downloads the archive for a GM and refuses a player', function () {
    $campaign = aCampaignWithPictures();
    $player = memberOf($campaign, CampaignRole::Player);

    $response = $this->actingAs(ownerOf($campaign))->get(route('campaigns.archive', $campaign));

    $response->assertOk()
        ->assertHeader('content-disposition', 'attachment; filename=demgem-the-drowned-duchy-'.now()->format('Y-m-d').'.zip');

    $this->actingAs($player)->get(route('campaigns.archive', $campaign))->assertForbidden();
});
