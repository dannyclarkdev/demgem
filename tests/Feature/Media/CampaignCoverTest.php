<?php

use App\Livewire\Campaigns\Settings;
use App\Models\Campaign;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(fn () => Storage::fake('public'));

it('stores a cover image and shows it on the campaign list and overview', function () {
    $campaign = Campaign::factory()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Settings::class, ['campaign' => $campaign])
        ->set('cover', UploadedFile::fake()->image('cover.jpg', 1200, 500))
        ->call('save')
        ->assertHasNoErrors();

    $campaign = $campaign->fresh();

    expect($campaign->coverUrl())->not->toBeNull();

    $this->actingAs(ownerOf($campaign))->get(route('campaigns.index'))->assertSee($campaign->coverUrl('card'), false);
    $this->actingAs(ownerOf($campaign))->get(route('campaigns.show', $campaign))->assertSee($campaign->coverUrl('card'), false);
});

it('removes the cover', function () {
    $campaign = Campaign::factory()->create();
    $file = UploadedFile::fake()->image('cover.jpg');
    $campaign->addMedia($file->getRealPath())->toMediaCollection('cover');

    Livewire::actingAs(ownerOf($campaign))
        ->test(Settings::class, ['campaign' => $campaign])
        ->set('removeCover', true)
        ->call('save')
        ->assertHasNoErrors();

    expect($campaign->fresh()->coverUrl())->toBeNull();
});
