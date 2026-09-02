<?php

use App\Enums\CampaignRole;
use App\Livewire\Entities\Form;
use App\Models\Campaign;
use App\Models\Entity;
use Livewire\Livewire;

it('previews the body with resolved wiki links', function () {
    $campaign = Campaign::factory()->create();
    $target = Entity::factory()->for($campaign)->forPlayers()->create(['name' => 'Vell', 'slug' => 'vell']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'notes'])
        ->set('body', "# Title\n\nSee [[Vell]] and <script>x</script>.")
        ->call('previewBody')
        ->assertSet('bodyPreview', fn (string $html) => str_contains($html, '<h1>Title</h1>')
            && str_contains($html, 'href="'.$target->url().'"')
            && ! str_contains($html, '<script>'));
});

it('does not preview GM notes for a player editing their PC', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    Entity::factory()->for($campaign)->pcOf($player)->create(['slug' => 'wren']);

    Livewire::actingAs($player)
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'characters', 'slug' => 'wren'])
        ->set('dm_notes', 'nope')
        ->call('previewDmNotes')
        ->assertSet('dmNotesPreview', '');
});
