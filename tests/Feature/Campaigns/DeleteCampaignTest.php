<?php

use App\Enums\CampaignRole;
use App\Livewire\Campaigns\Settings;
use App\Models\Campaign;
use Livewire\Livewire;

it('soft deletes when the owner types the campaign name', function () {
    $campaign = Campaign::factory()->create(['name' => 'Doomed Realm']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Settings::class, ['campaign' => $campaign])
        ->set('deleteConfirmation', 'Doomed Realm')
        ->call('delete')
        ->assertHasNoErrors()
        ->assertRedirect(route('campaigns.index'));

    $this->assertSoftDeleted($campaign);
});

it('rejects a confirmation that does not match', function () {
    $campaign = Campaign::factory()->create(['name' => 'Doomed Realm']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Settings::class, ['campaign' => $campaign])
        ->set('deleteConfirmation', 'doomed realm')
        ->call('delete')
        ->assertHasErrors(['deleteConfirmation']);

    $this->assertNotSoftDeleted($campaign);
});

it('forbids a co-GM from deleting', function () {
    $campaign = Campaign::factory()->create(['name' => 'Doomed Realm']);
    $coGm = memberOf($campaign, CampaignRole::CoGm);

    Livewire::actingAs($coGm)
        ->test(Settings::class, ['campaign' => $campaign])
        ->set('deleteConfirmation', 'Doomed Realm')
        ->call('delete')
        ->assertForbidden();

    $this->assertNotSoftDeleted($campaign);
});

it('hides a deleted campaign from every member', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $campaign->delete();

    $this->actingAs($player)
        ->get(route('campaigns.show', $campaign))
        ->assertNotFound();

    $this->actingAs($player)
        ->get(route('campaigns.index'))
        ->assertDontSee($campaign->name);
});
