<?php

use App\Enums\CampaignRole;
use App\Livewire\Entities\Form;
use App\Models\Campaign;
use App\Models\Entity;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(fn () => Storage::fake('public'));

it('stores an uploaded image on create and shows it on the page', function () {
    $campaign = Campaign::factory()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'characters'])
        ->set('name', 'Mara Voss')
        ->set('image', UploadedFile::fake()->image('mara.png', 600, 400))
        ->call('save')
        ->assertHasNoErrors();

    $entity = $campaign->entities()->firstOrFail();
    $media = $entity->getFirstMedia('image');

    expect($media)->not->toBeNull()
        ->and($entity->imageUrl())->not->toBeNull();

    Storage::disk('public')->assertExists($media->getPathRelativeToRoot());

    $this->actingAs(ownerOf($campaign))
        ->get($entity->url())
        ->assertSee($entity->imageUrl(), false);
});

it('replaces the previous image and can remove it', function () {
    $campaign = Campaign::factory()->create();
    $entity = Entity::factory()->for($campaign)->create(['slug' => 'mara-voss']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'characters', 'slug' => 'mara-voss'])
        ->set('image', UploadedFile::fake()->image('one.png'))
        ->call('save');
    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'characters', 'slug' => 'mara-voss'])
        ->set('image', UploadedFile::fake()->image('two.png'))
        ->call('save');

    expect($entity->fresh()->getMedia('image'))->toHaveCount(1)
        ->and($entity->fresh()->getFirstMedia('image')->file_name)->toBe('two.png');

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'characters', 'slug' => 'mara-voss'])
        ->set('removeImage', true)
        ->call('save');

    expect($entity->fresh()->getMedia('image'))->toHaveCount(0);
});

it('lets a player add an image to their own PC', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $pc = Entity::factory()->for($campaign)->pcOf($player)->create(['slug' => 'wren']);

    Livewire::actingAs($player)
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'characters', 'slug' => 'wren'])
        ->set('image', UploadedFile::fake()->image('wren.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    expect($pc->fresh()->imageUrl())->not->toBeNull();
});

it('rejects a file that is not an image', function () {
    $campaign = Campaign::factory()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'characters'])
        ->set('name', 'Mara Voss')
        ->set('image', UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'))
        ->call('save')
        ->assertHasErrors(['image']);

    expect(Entity::count())->toBe(0);
});
