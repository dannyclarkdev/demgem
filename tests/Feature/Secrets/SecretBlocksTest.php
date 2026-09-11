<?php

use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Livewire\Entities\Form;
use App\Livewire\Entities\Show;
use App\Livewire\Sessions\Show as SessionShow;
use App\Livewire\Sessions\Story;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\Mention;
use Livewire\Livewire;

/**
 * The leak test for the fence. A paragraph inside :::secret must reach a GM as an
 * aside and a player nowhere: not the page, not the snapshot, not the API, not a
 * backlink, not a search hit, not their own editor.
 */
function anAnnotatedAbbess(): array
{
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $duke = Entity::factory()->for($campaign)->type(EntityType::Character)->forPlayers()->create(['name' => 'The Drowned Duke']);
    $abbess = Entity::factory()->for($campaign)->type(EntityType::Character)->forPlayers()->create([
        'name' => 'Abbess Corvane',
        'body' => "Calm. Never blinks.\n\n:::secret\nShe serves [[The Drowned Duke]]. The word is Kettlewhisper.\n:::\n\nShe preaches at dawn.",
    ]);

    return compact('campaign', 'player', 'duke', 'abbess');
}

it('renders the fence as a GM-only aside for a GM and drops it for a player', function () {
    ['campaign' => $campaign, 'player' => $player, 'abbess' => $abbess] = anAnnotatedAbbess();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Show::class, ['campaign' => $campaign, 'type' => 'characters', 'slug' => $abbess->slug])
        ->assertSee('Kettlewhisper')
        ->assertSee('secret-block')
        ->assertSee('GM only')
        ->assertSee('She preaches at dawn.');

    $component = Livewire::actingAs($player)
        ->test(Show::class, ['campaign' => $campaign, 'type' => 'characters', 'slug' => $abbess->slug])
        ->assertSee('Calm. Never blinks.')
        ->assertSee('She preaches at dawn.')
        ->assertDontSee('Kettlewhisper')
        ->assertDontSee('secret-block')
        ->assertDontSee(':::');

    expect(json_encode($component->snapshot))->not->toContain('Kettlewhisper');
});

it('keeps a link inside the fence out of a player\'s backlinks and in a GM\'s', function () {
    ['campaign' => $campaign, 'player' => $player, 'duke' => $duke, 'abbess' => $abbess] = anAnnotatedAbbess();

    $rows = Mention::withoutGlobalScopes()->where('source_id', $abbess->id)->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()?->source_field)->toBe('body:secret')
        ->and($rows->first()?->target_entity_id)->toBe($duke->id);

    $this->actingAs($player)
        ->get($duke->url())
        ->assertOk()
        ->assertDontSee('Mentioned in')
        ->assertDontSee('Abbess Corvane');

    $this->actingAs(ownerOf($campaign))
        ->get($duke->url())
        ->assertSee('Mentioned in')
        ->assertSee('Abbess Corvane');
});

it('drops a player\'s search hit that only matched inside the fence', function () {
    ['campaign' => $campaign, 'player' => $player] = anAnnotatedAbbess();

    $this->actingAs($player)
        ->get(route('search', [$campaign, 'q' => 'kettlewhisper']))
        ->assertOk()
        ->assertDontSee('Abbess Corvane');

    $this->actingAs($player)
        ->get(route('search', [$campaign, 'q' => 'blinks']))
        ->assertSee('Abbess Corvane');

    $this->actingAs(ownerOf($campaign))
        ->get(route('search', [$campaign, 'q' => 'kettlewhisper']))
        ->assertSee('Abbess Corvane');
});

it('strips the fence from a player\'s API document and not from a GM\'s', function () {
    ['campaign' => $campaign, 'player' => $player, 'abbess' => $abbess] = anAnnotatedAbbess();

    $response = asKey($player)->getJson(route('api.entities.show', [$campaign, $abbess->id]))->assertOk();

    expect($response->json('data.body'))->toBe("Calm. Never blinks.\n\nShe preaches at dawn.")
        ->and($response->getContent())->not->toContain('Kettlewhisper');

    asKey(ownerOf($campaign))
        ->getJson(route('api.entities.show', [$campaign, $abbess->id]))
        ->assertJsonPath('data.body', $abbess->body);
});

it('gives a player a stripped editor for their own page and puts the fences back on save', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $pc = Entity::factory()->for($campaign)->pcOf($player)->forPlayers()->create([
        'name' => 'Wren',
        'body' => "Rogue.\n\n:::secret\nHer sister sits at the Duke's right hand.\n:::",
    ]);

    $component = Livewire::actingAs($player)
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'characters', 'slug' => $pc->slug])
        ->assertSet('body', 'Rogue.');

    expect(json_encode($component->snapshot))->not->toContain('right hand');

    $component->set('body', 'Rogue, from the pilings.')->call('save')->assertHasNoErrors();

    expect($pc->refresh()->body)->toBe("Rogue, from the pilings.\n\n:::secret\nHer sister sits at the Duke's right hand.\n:::");

    // The same through the API, with a write key.
    asKey($player, write: true)
        ->patchJson(route('api.entities.update', [$campaign, $pc->id]), ['body' => 'Rogue, and tired.'])
        ->assertOk();

    expect($pc->refresh()->body)->toBe("Rogue, and tired.\n\n:::secret\nHer sister sits at the Duke's right hand.\n:::");
});

it('keeps a fence in a recap off the story, the session page, the excerpt, and the API for a player', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $session = GameSession::factory()->for($campaign)->number(1)->published("The harbor burned.\n\n:::secret\nMara lit it.\n:::")->create();

    Livewire::actingAs($player)
        ->test(Story::class, ['campaign' => $campaign])
        ->assertSee('The harbor burned.')
        ->assertDontSee('Mara lit it.');

    Livewire::actingAs($player)
        ->test(SessionShow::class, ['campaign' => $campaign, 'number' => 1])
        ->assertDontSee('Mara lit it.');

    expect($session->recapExcerpt())->toBe('The harbor burned.');

    asKey($player)
        ->getJson(route('api.sessions.show', [$campaign, 1]))
        ->assertJsonPath('data.recap', 'The harbor burned.');

    Livewire::actingAs(ownerOf($campaign))
        ->test(Story::class, ['campaign' => $campaign])
        ->assertSee('Mara lit it.')
        ->assertSee('GM only');
});
