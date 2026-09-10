<?php

use App\Enums\Ruleset;
use App\Livewire\Compendium\Index;
use App\Livewire\Compendium\Show;
use App\Models\Campaign;
use App\Models\StatBlock;
use Livewire\Livewire;

/**
 * CC BY 4.0 licenses the text of the SRD and grants no trademark rights at all, so
 * these two rules are not style: they are the terms the dataset ships under.
 *
 * See database/srd/ATTRIBUTION.md.
 */
it('never uses the trademark in anything a user reads', function () {
    foreach (Ruleset::cases() as $ruleset) {
        expect($ruleset->label())
            ->not->toContain('D&D')
            ->not->toContain('Dungeons');
    }
});

it('keeps the trademark off the compendium screens', function () {
    $campaign = Campaign::factory()->create(['ruleset' => Ruleset::Srd5e2024]);
    StatBlock::factory()->named('Goblin Warrior')->create();

    $index = Livewire::actingAs(ownerOf($campaign))
        ->test(Index::class, ['campaign' => $campaign])
        ->html();

    $show = Livewire::actingAs(ownerOf($campaign))
        ->test(Show::class, ['campaign' => $campaign, 'statBlockSlug' => 'goblin-warrior'])
        ->html();

    foreach ([$index, $show] as $html) {
        expect($html)
            ->not->toContain('Dungeons &amp; Dragons')
            ->not->toContain('Dungeons & Dragons');
    }
});

it('names the document and the licence in the notice', function () {
    $notice = (string) config('compendium.attribution');

    expect($notice)
        ->toContain('System Reference Document 5.2.1')
        ->toContain('Wizards of the Coast')
        ->toContain('Creative Commons Attribution 4.0 International License')
        ->and(config('compendium.license'))->toBe('CC-BY-4.0');
});

it('ships the licence and the provenance beside the data', function () {
    expect(is_file(database_path('srd/ATTRIBUTION.md')))->toBeTrue()
        ->and(is_file(database_path('srd/LICENSE-CC-BY-4.0.txt')))->toBeTrue()
        ->and(is_file(database_path('srd/README.md')))->toBeTrue();

    expect(file_get_contents(database_path('srd/LICENSE-CC-BY-4.0.txt')))
        ->toContain('Attribution 4.0 International');
});
