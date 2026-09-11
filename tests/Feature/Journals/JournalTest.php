<?php

use App\Actions\Campaigns\WriteCampaignMarkdown;
use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Enums\Visibility;
use App\Livewire\Entities\Form;
use App\Livewire\Entities\Index;
use App\Livewire\Entities\Show;
use App\Models\Campaign;
use App\Models\Entity;
use Livewire\Livewire;

it('lets a player write a journal that only they and the GM can read', function () {
    $campaign = Campaign::factory()->create();
    $author = memberOf($campaign, CampaignRole::Player);
    $other = memberOf($campaign, CampaignRole::Player);

    Livewire::actingAs($author)
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'journals'])
        ->set('name', 'Night one')
        ->set('body', 'We met the abbess. I do not trust her.')
        ->call('save')
        ->assertHasNoErrors();

    $journal = Entity::query()->where('name', 'Night one')->firstOrFail();

    expect($journal->type)->toBe(EntityType::Journal)
        ->and($journal->player_user_id)->toBe($author->id)
        ->and($journal->visibility)->toBe(Visibility::Dm)
        ->and($journal->is_pc)->toBeFalse();

    Livewire::actingAs($author)
        ->test(Show::class, ['campaign' => $campaign, 'type' => 'journals', 'slug' => $journal->slug])
        ->assertSee('I do not trust her');

    Livewire::actingAs(ownerOf($campaign))
        ->test(Show::class, ['campaign' => $campaign, 'type' => 'journals', 'slug' => $journal->slug])
        ->assertSee('I do not trust her');

    $this->actingAs($other)
        ->get(route('entities.show', [$campaign, 'journals', $journal->slug]))
        ->assertNotFound();
});

it('lets the author share a journal with the party, and take it back', function () {
    $campaign = Campaign::factory()->create();
    $author = memberOf($campaign, CampaignRole::Player);
    $other = memberOf($campaign, CampaignRole::Player);
    $journal = Entity::factory()->for($campaign)->journalBy($author)->create(['name' => 'Night One']);

    Livewire::actingAs($author)
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'journals', 'slug' => $journal->slug])
        ->assertSet('visibility', 'dm')
        ->set('visibility', 'players')
        ->call('save')
        ->assertHasNoErrors();

    expect($journal->refresh()->visibility)->toBe(Visibility::Players);

    Livewire::actingAs($other)
        ->test(Show::class, ['campaign' => $campaign, 'type' => 'journals', 'slug' => $journal->slug])
        ->assertSee('Night One');

    Livewire::actingAs($author)
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'journals', 'slug' => $journal->slug])
        ->set('visibility', 'selected')
        ->call('save')
        ->assertHasErrors('visibility');
});

it('refuses a player who tries to write anything but a journal', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    $this->actingAs($player)
        ->get(route('entities.create', [$campaign, 'notes']))
        ->assertForbidden();

    // A DM field from a non-DM is never read, as on a PC's form; nothing is written.
    Livewire::actingAs($player)
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'journals'])
        ->set('name', 'Mine')
        ->set('dm_notes', 'A forged note')
        ->call('save')
        ->assertHasNoErrors();

    expect(Entity::query()->where('name', 'Mine')->firstOrFail()->dm_notes)->toBeNull();
});

it('keeps the author when a GM edits a journal', function () {
    $campaign = Campaign::factory()->create();
    $author = memberOf($campaign, CampaignRole::Player);
    $journal = Entity::factory()->for($campaign)->journalBy($author)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'journals', 'slug' => $journal->slug])
        ->set('dm_notes', 'She is right not to trust the abbess.')
        ->call('save')
        ->assertHasNoErrors();

    $journal->refresh();

    expect($journal->player_user_id)->toBe($author->id)
        ->and($journal->dm_notes)->toBe('She is right not to trust the abbess.');
});

it('lets the author delete their own journal and nobody else\'s', function () {
    $campaign = Campaign::factory()->create();
    $author = memberOf($campaign, CampaignRole::Player);
    $other = memberOf($campaign, CampaignRole::Player);
    $journal = Entity::factory()->for($campaign)->journalBy($author)->forPlayers()->create();

    Livewire::actingAs($other)
        ->test(Show::class, ['campaign' => $campaign, 'type' => 'journals', 'slug' => $journal->slug])
        ->call('delete')
        ->assertForbidden();

    Livewire::actingAs($author)
        ->test(Show::class, ['campaign' => $campaign, 'type' => 'journals', 'slug' => $journal->slug])
        ->call('delete');

    expect($journal->refresh()->trashed())->toBeTrue();
});

it('lists journals newest first with their author, and offers a player the button', function () {
    $campaign = Campaign::factory()->create();
    $author = memberOf($campaign, CampaignRole::Player);
    $other = memberOf($campaign, CampaignRole::Player);

    Entity::factory()->for($campaign)->journalBy($author)->forPlayers()->create(['name' => 'Alpha Night', 'created_at' => now()->subDays(2)]);
    Entity::factory()->for($campaign)->journalBy($author)->forPlayers()->create(['name' => 'Beta Night', 'created_at' => now()->subDay()]);
    Entity::factory()->for($campaign)->journalBy($author)->dmOnly()->create(['name' => 'Private Night']);

    Livewire::actingAs($other)
        ->test(Index::class, ['campaign' => $campaign, 'type' => 'journals'])
        ->assertSeeInOrder(['Beta Night', 'Alpha Night'])
        ->assertSee('by '.$author->name)
        ->assertSee('New journal')
        ->assertDontSee('Private Night');
});

it('creates a journal through the API for a player, and refuses a note', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    $response = asKey($player, write: true)
        ->postJson(route('api.entities.store', $campaign), ['type' => 'journals', 'name' => 'Night one', 'visibility' => 'players'])
        ->assertCreated()
        ->assertJsonPath('data.journal.author_user_id', $player->id);

    expect(Entity::query()->find($response->json('data.id'))?->visibility)->toBe(Visibility::Players);

    asKey($player, write: true)
        ->postJson(route('api.entities.store', $campaign), ['type' => 'journals', 'name' => 'Sneaky', 'visibility' => 'selected'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['visibility']);

    asKey($player, write: true)
        ->postJson(route('api.entities.store', $campaign), ['type' => 'notes', 'name' => 'Not mine to write'])
        ->assertForbidden();
});

it('names the author in the vault', function () {
    $campaign = Campaign::factory()->create();
    $author = memberOf($campaign, CampaignRole::Player);
    Entity::factory()->for($campaign)->journalBy($author)->create(['name' => 'Night One', 'slug' => 'night-one']);

    expect(app(WriteCampaignMarkdown::class)->handle($campaign)['markdown/journals/night-one.md'])
        ->toContain('author: "'.$author->name.'"');
});
