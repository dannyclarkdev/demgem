<?php

use App\Actions\Campaigns\ExportCampaign;
use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Livewire\Entities\Form;
use App\Models\Campaign;
use App\Models\Entity;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * A handout is an entity type, so most of what it does is already tested elsewhere:
 * visibility, search, wiki links, backlinks, tags and the export all belong to
 * Entity and are guarded by their own files. What is new here is the files.
 */
beforeEach(fn () => Storage::fake('public'));

function aHandout(Campaign $campaign, string $name = 'The duke\'s letter'): Entity
{
    return Entity::factory()->for($campaign)->type(EntityType::Handout)->dmOnly()
        ->create(['name' => $name, 'slug' => str($name)->slug()->value()]);
}

/**
 * A fake PDF with real bytes in it.
 *
 * UploadedFile::fake()->create() truncates a file to a size and writes nothing, so
 * Media Library sniffs application/x-empty and the collection refuses it. The magic
 * number is the whole point of the acceptsMimeTypes check, so the fixture has to
 * carry one.
 */
function aPdf(string $name = 'ledger.pdf'): File
{
    $handle = tmpfile();

    fwrite($handle, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n");

    return new File($name, $handle);
}

function attach(Entity $handout, UploadedFile $file): void
{
    // Hold the upload in a variable while the media is added: PHP deletes the fake
    // file the moment the object is collected, and an inline chain collects it first.
    $handout->addMedia($file->getRealPath())->usingFileName($file->getClientOriginalName())->toMediaCollection('files');
}

it('creates a handout with prose and files in one form', function () {
    $campaign = Campaign::factory()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'handouts'])
        ->set('name', 'The duke\'s letter')
        ->set('body', 'Transcribed, in case the scan is hard to read.')
        ->set('files', [
            UploadedFile::fake()->image('letter-front.png', 1200, 1600),
            UploadedFile::fake()->image('letter-back.png', 1200, 1600),
            aPdf(),
        ])
        ->call('save')
        ->assertHasNoErrors();

    $handout = Entity::query()->where('name', 'The duke\'s letter')->sole();

    expect($handout->type)->toBe(EntityType::Handout)
        ->and($handout->files())->toHaveCount(3)
        ->and($handout->files()->pluck('file_name')->all())
        ->toBe(['letter-front.png', 'letter-back.png', 'ledger.pdf']);
});

it('gives an image a tile and never asks a PDF for one', function () {
    $campaign = Campaign::factory()->create();
    $handout = aHandout($campaign);

    attach($handout, UploadedFile::fake()->image('letter-front.png', 1200, 1600));
    attach($handout, aPdf());

    $files = $handout->fresh()->files();

    // The conversion is scoped to its collection, so a PDF is an icon and a filename
    // rather than a broken img whose src depends on Ghostscript being installed.
    expect($files->firstWhere('file_name', 'letter-front.png')->hasGeneratedConversion('tile'))->toBeTrue()
        ->and($files->firstWhere('file_name', 'ledger.pdf')->hasGeneratedConversion('tile'))->toBeFalse();
});

it('keeps the crop off the files collection', function () {
    $campaign = Campaign::factory()->create();
    $handout = aHandout($campaign);

    attach($handout, UploadedFile::fake()->image('letter-front.png', 1200, 1600));

    // thumb belongs to the portrait beside the prose. An unscoped conversion would
    // run here too, which is what sent PDFs to a crop before the collections were
    // named.
    expect($handout->fresh()->files()->first()->hasGeneratedConversion('thumb'))->toBeFalse();
});

it('refuses more files than a handout carries', function () {
    $campaign = Campaign::factory()->create();
    $handout = aHandout($campaign);

    foreach (range(1, Entity::MAX_FILES) as $page) {
        attach($handout, UploadedFile::fake()->image("page-{$page}.png"));
    }

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'handouts', 'slug' => $handout->slug])
        ->set('files', [UploadedFile::fake()->image('one-too-many.png')])
        ->call('save')
        ->assertHasErrors('files');

    expect($handout->fresh()->files())->toHaveCount(Entity::MAX_FILES);
});

it('lets a GM swap the last file in a single save', function () {
    $campaign = Campaign::factory()->create();
    $handout = aHandout($campaign);

    foreach (range(1, Entity::MAX_FILES) as $page) {
        attach($handout, UploadedFile::fake()->image("page-{$page}.png"));
    }

    $doomed = $handout->files()->last();

    // Removals run before additions, so a GM at their own ceiling is not stopped by it.
    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'handouts', 'slug' => $handout->slug])
        ->set('removeFileIds', [$doomed->id])
        ->set('files', [UploadedFile::fake()->image('the-right-page.png')])
        ->call('save')
        ->assertHasNoErrors();

    $files = $handout->fresh()->files();

    expect($files)->toHaveCount(Entity::MAX_FILES)
        ->and($files->pluck('file_name'))->toContain('the-right-page.png')
        ->and($files->pluck('file_name'))->not->toContain($doomed->file_name);
});

it('refuses a file bigger than the cap and a type it does not take', function () {
    $campaign = Campaign::factory()->create();

    $component = fn () => Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'handouts'])
        ->set('name', 'The duke\'s letter');

    $component()
        ->set('files', [UploadedFile::fake()->create('enormous.pdf', Form::FILE_KB + 1, 'application/pdf')])
        ->call('save')
        ->assertHasErrors('files.0');

    $component()
        ->set('files', [UploadedFile::fake()->create('macro.xlsm', 40, 'application/vnd.ms-excel.sheet.macroEnabled.12')])
        ->call('save')
        ->assertHasErrors('files.0');

    expect(Entity::query()->ofType(EntityType::Handout)->count())->toBe(0);
});

it('prohibits files on every other type', function () {
    $campaign = Campaign::factory()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'notes'])
        ->set('name', 'A note that wants attachments')
        ->set('files', [UploadedFile::fake()->image('nope.png')])
        ->call('save')
        ->assertHasErrors('files');
});

it('shows a player a shared handout and never a hidden one', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    $shared = Entity::factory()->for($campaign)->type(EntityType::Handout)->forPlayers()
        ->create(['name' => 'The tide table', 'slug' => 'tide-table']);
    attach($shared, UploadedFile::fake()->image('tides.png'));

    $hidden = aHandout($campaign, 'The informant\'s note');
    attach($hidden, UploadedFile::fake()->image('informant.png'));

    $this->actingAs($player)
        ->get(route('entities.index', [$campaign, 'handouts']))
        ->assertOk()
        ->assertSee('The tide table')
        ->assertDontSee('The informant');

    $this->actingAs($player)
        ->get(route('entities.show', [$campaign, 'handouts', 'tide-table']))
        ->assertOk()
        ->assertSee($shared->files()->first()->getUrl('tile'), false);

    $this->actingAs($player)
        ->get(route('entities.show', [$campaign, 'handouts', $hidden->slug]))
        ->assertNotFound();
});

it('resolves a wiki link to a handout', function () {
    $campaign = Campaign::factory()->create();

    $letter = Entity::factory()->for($campaign)->type(EntityType::Handout)->forPlayers()
        ->create(['name' => 'The tide table', 'slug' => 'tide-table']);

    $note = Entity::factory()->for($campaign)->forPlayers()
        ->create(['name' => 'Harbour lore', 'slug' => 'harbour-lore', 'body' => 'See [[The tide table]].']);

    $this->actingAs(ownerOf($campaign))
        ->get(route('entities.show', [$campaign, 'characters', 'harbour-lore']))
        ->assertOk()
        ->assertSee($letter->url(), false);
});

it('carries a handout and its files into the export', function () {
    $campaign = Campaign::factory()->create();
    $handout = aHandout($campaign);

    attach($handout, UploadedFile::fake()->image('letter-front.png'));
    attach($handout, aPdf());

    $export = app(ExportCampaign::class)->handle($campaign);

    $row = collect($export['entities'])->firstWhere('name', 'The duke\'s letter');

    expect($row['type'])->toBe('handout')
        ->and($row['files'])->toHaveCount(2)
        ->and($row['files'][0]['file_name'])->toBe('letter-front.png')
        ->and($row['files'][1]['mime_type'])->toBe('application/pdf')
        ->and($row['files'][1]['url'])->toBeString();
});

it('finds a handout in search like every other type', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    Entity::factory()->for($campaign)->type(EntityType::Handout)->forPlayers()
        ->create(['name' => 'The tide table', 'slug' => 'tide-table', 'body' => 'High water at the ninth bell.']);

    aHandout($campaign, 'The informants note');

    $this->actingAs($player)
        ->get(route('search', [$campaign, 'q' => 'tide']))
        ->assertOk()
        ->assertSee('The tide table');

    $this->actingAs($player)
        ->get(route('search', [$campaign, 'q' => 'informant']))
        ->assertOk()
        ->assertDontSee('The informants note');
});

it('counts handouts in the sidebar like every other type', function () {
    $campaign = Campaign::factory()->create();
    Entity::factory()->for($campaign)->type(EntityType::Handout)->forPlayers()->count(2)->create();

    $this->actingAs(ownerOf($campaign))
        ->get(route('campaigns.show', $campaign))
        ->assertOk()
        ->assertSee('Handouts')
        ->assertSee(route('entities.index', [$campaign, 'handouts']), false);
});
