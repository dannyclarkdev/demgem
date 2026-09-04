<?php

use App\Actions\Handouts\RevealHandout;
use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Enums\Visibility;
use App\Events\HandoutRevealed;
use App\Livewire\Entities\Show as EntityShow;
use App\Livewire\Handouts\Panel;
use App\Livewire\Table\Show as TableShow;
use App\Models\Campaign;
use App\Models\Entity;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * The reveal, and the leak test that goes with it. A handout's reveal is the
 * visibility column every entity carries, so what is proved here is that the button
 * writes that column and nothing else, and that an unrevealed handout is absent from
 * the party's screen in the markup and in the snapshot alike.
 */
beforeEach(fn () => Storage::fake('public'));

function theTable(): array
{
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    $hidden = Entity::factory()->for($campaign)->type(EntityType::Handout)->dmOnly()
        ->create(['name' => 'The informants note', 'slug' => 'informants-note', 'body' => 'The prose nobody may read.']);

    $shown = Entity::factory()->for($campaign)->type(EntityType::Handout)->forPlayers()
        ->create(['name' => 'The tide table', 'slug' => 'tide-table']);

    foreach ([$hidden, $shown] as $handout) {
        $file = UploadedFile::fake()->image($handout->slug.'.png', 900, 1200);
        $handout->addMedia($file->getRealPath())->usingFileName($handout->slug.'.png')->toMediaCollection('files');
    }

    return [$campaign, $player, $hidden->fresh(), $shown->fresh()];
}

it('writes visibility and nothing else', function () {
    [$campaign, , $hidden] = theTable();

    $before = $hidden->only(['name', 'slug', 'body', 'dm_notes', 'parent_id']);

    app(RevealHandout::class)->show($hidden, ownerOf($campaign));

    $after = $hidden->fresh();

    expect($after->visibility)->toBe(Visibility::Players)
        ->and($after->only(['name', 'slug', 'body', 'dm_notes', 'parent_id']))->toBe($before);
});

it('keeps an unrevealed handout off the party table, markup and snapshot alike', function () {
    [$campaign, $player, $hidden] = theTable();

    $component = Livewire::actingAs($player)
        ->test(Panel::class, ['campaign' => $campaign])
        ->assertOk()
        ->assertSee('The tide table')
        ->assertDontSee('The informants note');

    $payload = json_encode($component->snapshot, JSON_THROW_ON_ERROR);

    expect($payload)->not->toContain('The informants note')
        ->and($payload)->not->toContain('The prose nobody may read.')
        // The file URL is the leak that a careful name check would miss.
        ->and($payload)->not->toContain($hidden->files()->first()->getUrl());
});

it('keeps an unrevealed handout out of the whole table page', function () {
    [$campaign, $player, $hidden] = theTable();

    $this->actingAs($player)
        ->get(route('table', $campaign))
        ->assertOk()
        ->assertSee('The tide table')
        ->assertDontSee('The informants note')
        ->assertDontSee($hidden->files()->first()->getUrl(), false);
});

it('puts a handout on the table with one press and takes it back with another', function () {
    [$campaign, $player, $hidden] = theTable();

    Livewire::actingAs(ownerOf($campaign))
        ->test(EntityShow::class, ['campaign' => $campaign, 'type' => 'handouts', 'slug' => $hidden->slug])
        ->call('reveal', true);

    expect($hidden->fresh()->visibility)->toBe(Visibility::Players);

    Livewire::actingAs($player)
        ->test(Panel::class, ['campaign' => $campaign])
        ->assertSee('The informants note');

    Livewire::actingAs(ownerOf($campaign))
        ->test(EntityShow::class, ['campaign' => $campaign, 'type' => 'handouts', 'slug' => $hidden->slug])
        ->call('reveal', false);

    expect($hidden->fresh()->visibility)->toBe(Visibility::Dm);

    Livewire::actingAs($player)
        ->test(Panel::class, ['campaign' => $campaign])
        ->assertDontSee('The informants note');
});

it('reveals from the panel as well as from the page', function () {
    [$campaign, , $hidden] = theTable();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Panel::class, ['campaign' => $campaign])
        ->call('reveal', $hidden->id);

    expect($hidden->fresh()->visibility)->toBe(Visibility::Players);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Panel::class, ['campaign' => $campaign])
        ->call('takeBack', $hidden->id);

    expect($hidden->fresh()->visibility)->toBe(Visibility::Dm);
});

it('leaves a handout shared with selected players alone', function () {
    $campaign = Campaign::factory()->create();
    $chosen = memberOf($campaign, CampaignRole::Player);

    $handout = Entity::factory()->for($campaign)->type(EntityType::Handout)
        ->create(['name' => 'The sealed orders', 'slug' => 'sealed-orders', 'visibility' => Visibility::Selected]);
    $handout->viewers()->sync([$chosen->id]);

    // Taking it back is the only button a Selected handout offers, and it means Dm.
    // Nothing here quietly promotes Selected to Everyone.
    Livewire::actingAs(ownerOf($campaign))
        ->test(Panel::class, ['campaign' => $campaign])
        ->call('takeBack', $handout->id);

    expect($handout->fresh()->visibility)->toBe(Visibility::Dm);
});

it('keeps a player out of the reveal', function () {
    [$campaign, $player, $hidden, $shown] = theTable();

    Livewire::actingAs($player)
        ->test(Panel::class, ['campaign' => $campaign])
        ->call('takeBack', $shown->id)
        ->assertForbidden();

    // The hidden one is a 404 rather than a 403: a player may not learn it exists.
    Livewire::actingAs($player)
        ->test(EntityShow::class, ['campaign' => $campaign, 'type' => 'handouts', 'slug' => $hidden->slug])
        ->assertNotFound();

    expect($shown->fresh()->visibility)->toBe(Visibility::Players);
});

it('says where it broadcasts, what it is called, and how little it carries', function () {
    $event = new HandoutRevealed('01campaign', '01handout');

    expect($event)->toBeInstanceOf(ShouldBroadcast::class)
        ->and($event)->toBeInstanceOf(ShouldRescue::class)
        ->and($event->broadcastAs())->toBe('handout.revealed')
        ->and($event->broadcastWith())->toBe(['handoutId' => '01handout']);

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(PresenceChannel::class)
        ->and($channels[0]->name)->toBe('presence-campaign.01campaign');
});

it('fires once a press, and says the same thing either way', function () {
    [$campaign, , $hidden] = theTable();

    $payloads = [];

    Event::listen(HandoutRevealed::class, function (HandoutRevealed $event) use (&$payloads) {
        $payloads[] = [$event->campaignId, $event->handoutId, $event->broadcastWith()];
    });

    $reveal = app(RevealHandout::class);
    $gm = ownerOf($campaign);

    $reveal->show($hidden, $gm);
    $reveal->takeBack($hidden, $gm);

    expect($payloads)->toHaveCount(2)
        ->and($payloads[0])->toBe($payloads[1]);
});

it('brings the handouts card in on the first reveal', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $handout = Entity::factory()->for($campaign)->type(EntityType::Handout)->dmOnly()
        ->create(['name' => 'The tide table', 'slug' => 'tide-table']);

    Livewire::actingAs($player)
        ->test(TableShow::class, ['campaign' => $campaign])
        ->assertDontSee('Handouts');

    app(RevealHandout::class)->show($handout, ownerOf($campaign));

    // The parent renders the card; the panel inside it is a nested component and
    // renders its own contents on its own round trip, which the test above covers.
    Livewire::actingAs($player)
        ->test(TableShow::class, ['campaign' => $campaign])
        ->dispatch('echo-presence:campaign.'.$campaign->id.',.handout.revealed')
        ->assertSee('Handouts');

    Livewire::actingAs($player)
        ->test(Panel::class, ['campaign' => $campaign])
        ->assertSee('The tide table');
});
