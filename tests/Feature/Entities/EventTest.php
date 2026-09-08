<?php

use App\Actions\Campaigns\ImportCampaign;
use App\Actions\Campaigns\ReadCampaignFile;
use App\Actions\Campaigns\WriteCampaignMarkdown;
use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Livewire\Entities\Form;
use App\Livewire\Entities\Index;
use App\Livewire\Entities\Show;
use App\Models\Calendar;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\User;
use App\Support\Reckoning\GameDate;
use Livewire\Livewire;

/**
 * An event: an entity with a day in the world.
 */
it('creates an event with a date and prints it on the page', function () {
    $campaign = Campaign::factory()->create();
    Calendar::factory()->inCampaign($campaign)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'events'])
        ->assertSee('When')
        ->set('name', 'The Harbor Fire')
        ->set('happensOn', ['year' => 1042, 'month' => 1, 'day' => 4])
        ->set('visibility', 'players')
        ->call('save')
        ->assertHasNoErrors();

    $event = Entity::query()->where('name', 'The Harbor Fire')->sole();

    expect($event->type)->toBe(EntityType::Event)
        ->and($event->happens_on?->toArray())->toBe(['year' => 1042, 'month' => 1, 'day' => 4]);

    Livewire::actingAs(memberOf($campaign, CampaignRole::Player))
        ->test(Show::class, ['campaign' => $campaign, 'type' => 'events', 'slug' => $event->slug])
        // Day number 62723 is odd, so a Moonday.
        ->assertSee('Moonday, 4 Thaw 1042 AR');

    Livewire::actingAs(memberOf($campaign, CampaignRole::Player))
        ->test(Index::class, ['campaign' => $campaign, 'type' => 'events'])
        ->assertSee('The Harbor Fire');
});

it('allows an event with no date, and refuses a date the calendar lacks', function () {
    $campaign = Campaign::factory()->create();
    Calendar::factory()->inCampaign($campaign)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'events'])
        ->set('name', 'A Rumor')
        ->call('save')
        ->assertHasNoErrors();

    expect(Entity::query()->where('name', 'A Rumor')->sole()->happens_on)->toBeNull();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'events'])
        ->set('name', 'Too Late In Thaw')
        ->set('happensOn', ['year' => 1042, 'month' => 1, 'day' => 11])
        ->call('save')
        ->assertHasErrors(['happensOn.day']);
});

it('prohibits a date on any other type', function () {
    $campaign = Campaign::factory()->create();
    Calendar::factory()->inCampaign($campaign)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'locations'])
        ->set('name', 'Vell')
        ->set('happensOn', ['year' => 1042, 'month' => 1, 'day' => 4])
        ->call('save')
        ->assertHasErrors(['happensOn']);
});

it('carries the date through the round trip and the front matter', function () {
    $campaign = Campaign::factory()->create();
    Calendar::factory()->inCampaign($campaign)->create();
    Entity::factory()->event(new GameDate(1042, 1, 4))->create(['campaign_id' => $campaign->id, 'name' => 'The Harbor Fire']);

    $document = exportedArray($campaign);

    expect($document['entities'][0]['happens_on'])->toBe(['year' => 1042, 'month' => 1, 'day' => 4]);

    $read = app(ReadCampaignFile::class)->handle(json_encode($document, JSON_THROW_ON_ERROR));
    $copy = app(ImportCampaign::class)->handle($read->document, User::factory()->create());

    expect($copy->entities()->sole()->happens_on?->toArray())->toBe(['year' => 1042, 'month' => 1, 'day' => 4]);

    $files = app(WriteCampaignMarkdown::class)->handle($campaign);

    expect($files['markdown/events/the-harbor-fire.md'])->toContain('happens_on: "Moonday, 4 Thaw 1042 AR"');
});
