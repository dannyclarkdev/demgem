<?php

use App\Actions\Campaigns\BuildCampaignArchive;
use App\Livewire\Campaigns\Import;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\User;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(fn () => Storage::fake('public'));

/**
 * An uploaded file holding the bytes of one already on disk.
 */
function anUpload(string $path, string $name): File
{
    $handle = tmpfile();

    fwrite($handle, (string) file_get_contents($path));

    return new File($name, $handle);
}

it('previews an archive without unpacking anything', function () {
    $campaign = aCampaignWithPictures();

    $before = glob(sys_get_temp_dir().'/demgem-media-*') ?: [];

    Livewire::actingAs(User::factory()->create())
        ->test(Import::class)
        ->set('file', anUpload(app(BuildCampaignArchive::class)->handle($campaign), 'campaign.zip'))
        ->assertSet('isArchive', true)
        ->assertSet('problems', [])
        ->assertSee('3 files will come across');

    // A preview that unpacked would leave a temp file for every archive a GM ever
    // opened and changed their mind about.
    expect(glob(sys_get_temp_dir().'/demgem-media-*') ?: [])->toBe($before);
});

it('imports an archive with its pictures', function () {
    $campaign = aCampaignWithPictures();

    Livewire::actingAs(User::factory()->create())
        ->test(Import::class)
        ->set('file', anUpload(app(BuildCampaignArchive::class)->handle($campaign), 'campaign.zip'))
        ->call('import');

    $copy = Campaign::query()->where('id', '!=', $campaign->id)->sole();

    $media = Entity::withoutGlobalScopes()->where('campaign_id', $copy->id)->get()
        ->sum(fn (Entity $entity) => $entity->getMedia('image')->count() + $entity->getMedia('files')->count());

    expect($media)->toBe(3)
        ->and(session('status'))->toContain('with 3 files');
});

it('still imports a plain JSON document, and says the pictures stayed behind', function () {
    // The same campaign, exported the other way. A JSON document names its images and
    // carries none of them, which is the loss the archive exists to close.
    $source = aCampaignWithPictures();

    Livewire::actingAs(User::factory()->create())
        ->test(Import::class)
        ->set('file', aCampaignFile(exportedArray($source)))
        ->assertSet('isArchive', false)
        ->assertSee('cannot come across')
        ->call('import');

    $copy = Campaign::query()->where('id', '!=', $source->id)->sole();

    $media = Entity::withoutGlobalScopes()->where('campaign_id', $copy->id)->get()
        ->sum(fn (Entity $entity) => $entity->getMedia('image')->count() + $entity->getMedia('files')->count());

    expect($copy->name)->toBe('The Drowned Duchy')
        ->and($media)->toBe(0)
        ->and(session('status'))->toContain('without its images');
});

it('judges a file by its first bytes, not its name', function () {
    $campaign = aCampaignWithPictures();

    // A GM who renames their download should still get the right reader.
    Livewire::actingAs(User::factory()->create())
        ->test(Import::class)
        ->set('file', anUpload(app(BuildCampaignArchive::class)->handle($campaign), 'campaign.json'))
        ->assertSet('isArchive', true)
        ->assertSet('problems', []);
});

it('offers both downloads on the settings page', function () {
    $campaign = Campaign::factory()->create();

    $this->actingAs(ownerOf($campaign))
        ->get(route('campaigns.settings', $campaign))
        ->assertOk()
        ->assertSee(route('campaigns.archive', $campaign), false)
        ->assertSee(route('campaigns.export', $campaign), false)
        ->assertSee('Download archive')
        ->assertSee('Obsidian');
});
