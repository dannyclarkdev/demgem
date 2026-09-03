<?php

use App\Enums\CampaignRole;
use App\Livewire\Sessions\Story;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('reads oldest first, so page one is where the campaign started', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    GameSession::factory()->for($campaign)->number(1)->published('They took the job.')->create();
    GameSession::factory()->for($campaign)->number(2)->published('They lost the cart.')->create();
    GameSession::factory()->for($campaign)->number(3)->published('They found the barrow.')->create();

    Livewire::actingAs($player)
        ->test(Story::class, ['campaign' => $campaign])
        ->assertSeeInOrder(['They took the job.', 'They lost the cart.', 'They found the barrow.']);
});

it('never shows an unpublished recap to a player', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    GameSession::factory()->for($campaign)->number(1)->published('The published one.')->create();
    GameSession::factory()->for($campaign)->number(2)->withRecap('The draft nobody may read.')->create();

    $component = Livewire::actingAs($player)
        ->test(Story::class, ['campaign' => $campaign])
        ->assertSee('The published one.')
        ->assertDontSee('The draft nobody may read.');

    // The snapshot travels to the browser, so the words must not be in it either.
    expect(json_encode($component->snapshot))->not->toContain('The draft nobody may read.');
});

it('keeps a published recap on a hidden session away from players', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    GameSession::factory()->for($campaign)->number(1)->hidden()->published('The session they never knew about.')->create();

    Livewire::actingAs($player)
        ->test(Story::class, ['campaign' => $campaign])
        ->assertDontSee('The session they never knew about.')
        ->assertSee('No story yet');
});

it('shows a GM their drafts and the recaps they still owe', function () {
    $campaign = Campaign::factory()->create();

    GameSession::factory()->for($campaign)->number(1)->published('The published one.')->create();
    GameSession::factory()->for($campaign)->number(2)->withRecap('The draft.')->create();
    GameSession::factory()->for($campaign)->number(3)->played()->create(['title' => 'The Silent Bell']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Story::class, ['campaign' => $campaign])
        ->assertSee('The published one.')
        ->assertSee('The draft.')
        ->assertSee('Draft, not published')
        ->assertSee('The Silent Bell')
        ->assertSee('No recap yet')
        ->assertSee('Write the recap');
});

it('leaves a planned session out of the story for everybody', function () {
    $campaign = Campaign::factory()->create();

    GameSession::factory()->for($campaign)->number(1)->planned()->create(['title' => 'Next Thursday']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Story::class, ['campaign' => $campaign])
        ->assertDontSee('Next Thursday');
});

it('resolves wiki links through the viewer, not the writer', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    Entity::factory()->for($campaign)->forPlayers()->create(['name' => 'Harrowgate', 'slug' => 'harrowgate']);
    Entity::factory()->for($campaign)->dmOnly()->create(['name' => 'The Cellar', 'slug' => 'the-cellar']);

    GameSession::factory()->for($campaign)->number(1)
        ->published('They rode to [[Harrowgate]] and slept above [[The Cellar]].')
        ->create();

    $html = Livewire::actingAs($player)
        ->test(Story::class, ['campaign' => $campaign])
        ->html();

    expect($html)->toContain('harrowgate')
        ->and($html)->toContain('The Cellar')
        ->and($html)->not->toContain('the-cellar');
});

it('pages twenty at a time', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    foreach (range(1, 21) as $number) {
        GameSession::factory()->for($campaign)->number($number)->published("Recap number {$number}.")->create();
    }

    Livewire::actingAs($player)
        ->test(Story::class, ['campaign' => $campaign])
        ->assertSee('Recap number 1.')
        ->assertDontSee('Recap number 21.')
        ->call('gotoPage', 2)
        ->assertSee('Recap number 21.')
        ->assertDontSee('Recap number 1.');
});

it('costs the same whether the page holds three recaps or twenty', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    foreach (range(1, 3) as $number) {
        GameSession::factory()->for($campaign)->number($number)->published()->create();
    }

    // One warm-up render: Campaign caches its member lookup per instance, so the
    // first call costs a query the second does not.
    countQueriesForStory($player, $campaign);

    $small = countQueriesForStory($player, $campaign);

    foreach (range(4, 20) as $number) {
        GameSession::factory()->for($campaign)->number($number)->published()->create();
    }

    expect(countQueriesForStory($player, $campaign))->toBe($small);
});

it('returns 404 for a non-member', function () {
    $campaign = Campaign::factory()->create();

    $this->actingAs(User::factory()->create())->get(route('story', $campaign))->assertNotFound();
});

it('redirects a guest to login', function () {
    $campaign = Campaign::factory()->create();

    $this->get(route('story', $campaign))->assertRedirect(route('login'));
});

it('shows the story of this campaign only', function () {
    $campaign = Campaign::factory()->create();
    $other = Campaign::factory()->create();

    GameSession::factory()->for($campaign)->number(1)->published('Ours.')->create();
    GameSession::factory()->for($other)->number(1)->published('Somebody else\'s.')->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Story::class, ['campaign' => $campaign])
        ->assertSee('Ours.')
        ->assertDontSee('Somebody else\'s.');
});

function countQueriesForStory(User $user, Campaign $campaign): int
{
    $count = 0;
    DB::listen(function () use (&$count): void {
        $count++;
    });

    Livewire::actingAs($user)->test(Story::class, ['campaign' => $campaign]);

    return $count;
}
