<?php

use App\Actions\Campaigns\BuildCampaignArchive;
use App\Actions\Campaigns\ImportCampaign;
use App\Actions\Campaigns\ReadCampaignArchive;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * The dangerous half. Everything here is about what an archive can and cannot make
 * this application do.
 *
 * The traversal test is written to assert the entry is *inert* rather than that it is
 * caught, because the design is not a check: no string from an archive is ever used
 * as a path, so a ../ entry is simply one nothing asks for.
 */
beforeEach(fn () => Storage::fake('public'));

/**
 * Adds entries to an archive that was built normally, so a test can put something
 * nasty beside a real campaign.
 *
 * @param  array<string, string>  $entries
 */
function withEntries(string $path, array $entries): string
{
    $zip = new ZipArchive;
    $zip->open($path);

    foreach ($entries as $name => $contents) {
        $zip->addFromString($name, $contents);
    }

    $zip->close();

    return $path;
}

function archiveOf(Campaign $campaign): string
{
    return app(BuildCampaignArchive::class)->handle($campaign);
}

it('restores every picture the archive carried', function () {
    $campaign = aCampaignWithPictures();

    $result = app(ReadCampaignArchive::class)->handle(archiveOf($campaign));

    expect($result->succeeded())->toBeTrue()
        ->and($result->restored)->toHaveCount(3)
        ->and($result->read->report->filesRestored)->toBe(3)
        // The member loss is always there; what matters is that no file is lost.
        ->and(collect($result->read->report->losses())->filter(fn (array $loss) => str_contains($loss['label'], 'file')))->toBeEmpty()
        ->and($result->read->report->gains())->toContain('3 files will come across');

    $copy = app(ImportCampaign::class)->handle($result->read->document, User::factory()->create(), $result->restored);

    $entities = Entity::withoutGlobalScopes()->where('campaign_id', $copy->id)->get();

    $images = $entities->sum(fn (Entity $entity) => $entity->getMedia('image')->count());
    $files = $entities->sum(fn (Entity $entity) => $entity->getMedia('files')->count());

    expect($images)->toBe(1)
        ->and($files)->toBe(2);
});

it('leaves an entry that tries to climb out of the folder completely alone', function () {
    $campaign = aCampaignWithPictures();

    $sentinel = sys_get_temp_dir().'/demgem-traversal-sentinel';
    @unlink($sentinel);

    $path = withEntries(archiveOf($campaign), [
        '../../../demgem-traversal-sentinel' => 'this should never be written',
        '../../.env' => 'APP_KEY=stolen',
        '/etc/demgem-absolute' => 'nor this',
    ]);

    $result = app(ReadCampaignArchive::class)->handle($path);

    // It imported fine, because those entries are not entries anything asked for.
    expect($result->succeeded())->toBeTrue()
        ->and($result->restored)->toHaveCount(3)
        ->and(file_exists($sentinel))->toBeFalse()
        ->and(file_exists('/etc/demgem-absolute'))->toBeFalse();

    // And every file it did write is under a name this app generated.
    foreach ($result->restored as $entry => $file) {
        expect($file)->toStartWith(sys_get_temp_dir().'/demgem-media-')
            ->and($file)->not->toContain('..');
    }
});

it('refuses an archive holding more files than it will read', function () {
    $campaign = Campaign::factory()->create();

    $filler = [];

    foreach (range(1, ReadCampaignArchive::MAX_ENTRIES + 1) as $i) {
        $filler["padding/{$i}.txt"] = 'x';
    }

    $result = app(ReadCampaignArchive::class)->handle(withEntries(archiveOf($campaign), $filler));

    expect($result->succeeded())->toBeFalse()
        ->and($result->errors[0])->toContain('at most '.ReadCampaignArchive::MAX_ENTRIES);
});

it('drops a file that is not the kind of file it says it is', function () {
    $campaign = aCampaignWithPictures();

    $path = archiveOf($campaign);

    // Replace one picture's bytes with something that is not a picture at all. The
    // document still calls it a PNG; the archive is measured rather than believed.
    $zip = new ZipArchive;
    $zip->open($path);
    $zip->deleteName('media/0001-the-duchy-of-vell.png');
    $zip->close();

    withEntries($path, ['media/0001-the-duchy-of-vell.png' => "<?php echo 'not a picture';"]);

    $result = app(ReadCampaignArchive::class)->handle($path);

    expect($result->succeeded())->toBeTrue()
        ->and($result->restored)->toHaveCount(2)
        ->and($result->read->report->filesRestored)->toBe(2)
        ->and(collect($result->read->report->losses())->pluck('label')->filter(fn (string $l) => str_contains($l, 'file'))->first())
        ->toContain('1 of 3 files cannot come across');

    // And the campaign still imports, because the campaign is the point.
    $copy = app(ImportCampaign::class)->handle($result->read->document, User::factory()->create(), $result->restored);

    expect(Entity::withoutGlobalScopes()->where('campaign_id', $copy->id)->count())->toBe(2);
});

it('refuses a file that is not a zip', function () {
    $path = tempnam(sys_get_temp_dir(), 'demgem').'.zip';
    file_put_contents($path, 'not a zip at all');

    $result = app(ReadCampaignArchive::class)->handle($path);

    expect($result->succeeded())->toBeFalse()
        ->and($result->errors[0])->toContain('not a zip archive');
});

it('refuses an archive with no campaign in it', function () {
    $path = tempnam(sys_get_temp_dir(), 'demgem').'.zip';

    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('notes.txt', 'just some notes');
    $zip->close();

    $result = app(ReadCampaignArchive::class)->handle($path);

    expect($result->succeeded())->toBeFalse()
        ->and($result->errors[0])->toContain('no campaign.json');
});

it('passes a broken document straight back from the reader', function () {
    $path = tempnam(sys_get_temp_dir(), 'demgem').'.zip';

    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('campaign.json', json_encode(['format' => 'obsidian.vault', 'version' => 1]));
    $zip->close();

    $result = app(ReadCampaignArchive::class)->handle($path);

    // The archive reader is a front half. ReadCampaignFile knows nothing about zips
    // and its errors reach the GM unchanged.
    expect($result->succeeded())->toBeFalse()
        ->and($result->errors[0])->toContain('obsidian.vault');
});

it('tells a zip from a document by looking at it', function () {
    expect(ReadCampaignArchive::looksLikeArchive("PK\x03\x04rest of the zip"))->toBeTrue()
        ->and(ReadCampaignArchive::looksLikeArchive('{"format":"demgem.campaign"}'))->toBeFalse();
});

it('never names a file after anything the document said', function () {
    $campaign = Campaign::factory()->create();
    $entity = Entity::factory()->for($campaign)->create(['name' => 'A page', 'slug' => 'a-page']);

    $file = UploadedFile::fake()->image('../../etc/passwd.png', 20, 20);
    $entity->addMedia($file->getRealPath())->usingFileName('../../etc/passwd.png')->toMediaCollection('image');

    $result = app(ReadCampaignArchive::class)->handle(archiveOf($campaign));
    $copy = app(ImportCampaign::class)->handle($result->read->document, User::factory()->create(), $result->restored);

    $imported = Entity::withoutGlobalScopes()->where('campaign_id', $copy->id)->sole();
    $media = $imported->getFirstMedia('image');

    expect($media)->not->toBeNull()
        ->and($media->file_name)->not->toContain('..')
        ->and($media->file_name)->not->toContain('/')
        ->and($media->getPath())->toContain($media->id.'/');
});
