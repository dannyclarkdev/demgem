<?php

use App\Actions\Campaigns\ExportCampaign;
use App\Livewire\Campaigns\Import;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\User;
use Database\Seeders\DemoCampaignSeeder;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(fn () => Storage::fake('local'));

/**
 * An uploaded file with real bytes in it. UploadedFile::fake()->create() truncates to
 * a size and writes nothing, which is not a document.
 */
function aCampaignFile(array $document, string $name = 'campaign.json'): File
{
    $handle = tmpfile();

    fwrite($handle, json_encode($document, JSON_THROW_ON_ERROR));

    return new File($name, $handle);
}

function exportedArray(Campaign $campaign): array
{
    return json_decode(json_encode(array_map(
        fn ($section) => is_iterable($section) && ! is_array($section) ? iterator_to_array($section) : $section,
        app(ExportCampaign::class)->handle($campaign),
    ), JSON_THROW_ON_ERROR), true);
}

it('reports on a file without writing anything', function () {
    $source = Campaign::factory()->create(['name' => 'The Drowned Duchy']);
    Entity::factory()->for($source)->count(4)->create();

    $before = Campaign::query()->count();

    Livewire::actingAs(User::factory()->create())
        ->test(Import::class)
        ->set('file', aCampaignFile(exportedArray($source)))
        ->assertHasNoErrors()
        ->assertSet('read', true)
        ->assertSet('problems', [])
        ->assertSee('What will come across')
        ->assertSee('entities');

    expect(Campaign::query()->count())->toBe($before);
});

it('names the four things that will not come across before the button', function () {
    $this->seed(DemoCampaignSeeder::class);

    $source = Campaign::query()->firstOrFail();

    Livewire::actingAs(User::factory()->create())
        ->test(Import::class)
        ->set('file', aCampaignFile(exportedArray($source)))
        ->assertSee('What will not')
        ->assertSee('cannot come across')
        ->assertSee('cannot be re-linked')
        ->assertSee('will be left behind');
});

it('builds the campaign on confirm and goes to it', function () {
    $source = Campaign::factory()->create(['name' => 'The Drowned Duchy']);
    Entity::factory()->for($source)->count(3)->create();

    $importer = User::factory()->create();

    $component = Livewire::actingAs($importer)
        ->test(Import::class)
        ->set('file', aCampaignFile(exportedArray($source)))
        ->call('import');

    $copy = Campaign::query()->where('id', '!=', $source->id)->sole();

    $component->assertRedirect(route('campaigns.show', $copy));

    expect($copy->name)->toBe('The Drowned Duchy')
        ->and($copy->roleFor($importer)?->isDm())->toBeTrue()
        ->and(Entity::withoutGlobalScopes()->where('campaign_id', $copy->id)->count())->toBe(3);
});

it('shows the reason and writes nothing when the file is wrong', function () {
    $before = Campaign::query()->count();

    $component = Livewire::actingAs(User::factory()->create())
        ->test(Import::class)
        ->set('file', aCampaignFile(['format' => 'obsidian.vault', 'version' => 1]))
        ->assertSet('read', true)
        ->assertSee('cannot be imported')
        ->assertSee('obsidian.vault')
        ->assertDontSee('What will come across');

    $component->call('import');

    expect(Campaign::query()->count())->toBe($before);
});

it('refuses to build from a file that went bad between the report and the button', function () {
    $source = Campaign::factory()->create();

    $component = Livewire::actingAs(User::factory()->create())
        ->test(Import::class)
        ->set('file', aCampaignFile(exportedArray($source)))
        ->assertSet('problems', []);

    // The confirm re-reads the file rather than trusting the summary this component
    // remembered, so a file that is no longer a campaign cannot slip through.
    $component->set('file', aCampaignFile(['format' => 'nope']))->call('import');

    expect(Campaign::query()->count())->toBe(1);
});

it('sends a guest to the login page', function () {
    $this->get(route('campaigns.import'))->assertRedirect(route('login'));
});

it('offers the import beside the new campaign button', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('campaigns.index'))
        ->assertOk()
        ->assertSee(route('campaigns.import'), false);
});
