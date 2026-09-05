<?php

use App\Models\Campaign;
use App\Models\Entity;
use App\Models\User;
use Database\Seeders\DemoCampaignSeeder;

/**
 * The same reader and the same writer, reached from a terminal. What is tested here
 * is the plumbing around them: the file, the user, and the exit code.
 */
function aFileOnDisk(array $document): string
{
    $path = tempnam(sys_get_temp_dir(), 'demgem-import').'.json';

    file_put_contents($path, json_encode($document, JSON_THROW_ON_ERROR));

    return $path;
}

it('imports a campaign and says what it did', function () {
    $source = Campaign::factory()->create(['name' => 'The Drowned Duchy']);
    Entity::factory()->for($source)->count(2)->create();

    $owner = User::factory()->create(['email' => 'gm@demgem.test']);

    $this->artisan('demgem:import', [
        'file' => aFileOnDisk(exportedArray($source)),
        '--user' => 'gm@demgem.test',
    ])
        ->expectsOutputToContain('entities')
        ->expectsOutputToContain('The Drowned Duchy')
        ->assertSuccessful();

    $copy = Campaign::query()->where('id', '!=', $source->id)->sole();

    expect($copy->roleFor($owner)?->isDm())->toBeTrue()
        ->and(Entity::withoutGlobalScopes()->where('campaign_id', $copy->id)->count())->toBe(2);
});

it('names the losses on its way past them', function () {
    $this->seed(DemoCampaignSeeder::class);

    $source = Campaign::query()->firstOrFail();
    User::factory()->create(['email' => 'gm@demgem.test']);

    $this->artisan('demgem:import', [
        'file' => aFileOnDisk(exportedArray($source)),
        '--user' => 'gm@demgem.test',
    ])
        ->expectsOutputToContain('cannot come across')
        ->assertSuccessful();
});

it('stops on a file that is not there', function () {
    User::factory()->create(['email' => 'gm@demgem.test']);

    $this->artisan('demgem:import', ['file' => '/nowhere/at/all.json', '--user' => 'gm@demgem.test'])
        ->expectsOutputToContain('No readable file')
        ->assertFailed();

    expect(Campaign::query()->count())->toBe(0);
});

it('stops when it does not know who to give the campaign to', function () {
    $source = Campaign::factory()->create();
    $path = aFileOnDisk(exportedArray($source));

    $this->artisan('demgem:import', ['file' => $path])
        ->expectsOutputToContain('Say who will own')
        ->assertFailed();

    $this->artisan('demgem:import', ['file' => $path, '--user' => 'nobody@demgem.test'])
        ->expectsOutputToContain('No user with the email')
        ->assertFailed();

    expect(Campaign::query()->count())->toBe(1);
});

it('stops on a document it cannot read, and says why', function () {
    User::factory()->create(['email' => 'gm@demgem.test']);

    $this->artisan('demgem:import', [
        'file' => aFileOnDisk(['format' => 'obsidian.vault', 'version' => 1]),
        '--user' => 'gm@demgem.test',
    ])
        ->expectsOutputToContain('cannot be imported')
        ->expectsOutputToContain('obsidian.vault')
        ->assertFailed();

    expect(Campaign::query()->count())->toBe(0);
});
