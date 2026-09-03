<?php

use App\Enums\CampaignRole;
use App\Livewire\Table\Presence;
use App\Models\Campaign;
use App\Models\GameSession;
use App\Models\User;
use Livewire\Livewire;

/**
 * Presence membership itself comes from the channel callback, which ChannelAuthTest
 * covers. This file is about the strip: what it counts, and what it refuses to
 * believe from the wire.
 */
function tableWithFourMembers(): array
{
    $campaign = Campaign::factory()->create();

    $gm = ownerOf($campaign);
    $gm->update(['name' => 'Danny the GM']);

    $wren = memberOf($campaign, CampaignRole::Player);
    $wren->update(['name' => 'Tobin Ashgrove']);

    $mara = memberOf($campaign, CampaignRole::Player);
    $mara->update(['name' => 'Mara Voss']);

    $watcher = memberOf($campaign, CampaignRole::Spectator);
    $watcher->update(['name' => 'Coll the Watcher']);

    return [$campaign, $gm, $wren, $mara, $watcher];
}

/**
 * @return array<int, array{id: int, name: string, role: string}>
 */
function echoRoster(User ...$users): array
{
    return array_map(fn (User $user) => [
        'id' => $user->id,
        'name' => $user->name,
        'role' => 'player',
    ], $users);
}

it('says how many of the table are here, and names everyone either way', function () {
    [$campaign, $gm, $wren, $mara, $watcher] = tableWithFourMembers();

    Livewire::actingAs($gm)
        ->test(Presence::class, ['campaign' => $campaign])
        ->call('here', echoRoster($gm, $wren, $mara))
        ->assertSee('3 of 4 here')
        ->assertSee('Danny the GM')
        ->assertSee('Mara Voss')
        ->assertSee('Coll the Watcher');
});

it('counts nobody before the channel answers', function () {
    [$campaign, $gm] = tableWithFourMembers();

    Livewire::actingAs($gm)
        ->test(Presence::class, ['campaign' => $campaign])
        ->assertSee('0 of 4 here');
});

it('follows somebody opening the campaign and closing it again', function () {
    [$campaign, $gm, $wren, $mara] = tableWithFourMembers();

    Livewire::actingAs($gm)
        ->test(Presence::class, ['campaign' => $campaign])
        ->call('here', echoRoster($gm))
        ->assertSee('1 of 4 here')
        ->call('joining', ['id' => $wren->id, 'name' => $wren->name, 'role' => 'player'])
        ->assertSee('2 of 4 here')
        ->call('joining', ['id' => $mara->id, 'name' => $mara->name, 'role' => 'player'])
        ->assertSee('3 of 4 here')
        ->call('leaving', ['id' => $wren->id, 'name' => $wren->name, 'role' => 'player'])
        ->assertSee('2 of 4 here');
});

it('counts a person once however many times they arrive', function () {
    [$campaign, $gm, $wren] = tableWithFourMembers();

    $joining = ['id' => $wren->id, 'name' => $wren->name, 'role' => 'player'];

    Livewire::actingAs($gm)
        ->test(Presence::class, ['campaign' => $campaign])
        ->call('joining', $joining)
        ->call('joining', $joining)
        ->assertSee('1 of 4 here');
});

it('reads the name off the database and never off the wire', function () {
    [$campaign, $gm, $wren] = tableWithFourMembers();

    // A browser can call a Livewire method with anything. The payload here claims a
    // different name and a GM role for a player; the strip renders neither.
    Livewire::actingAs($gm)
        ->test(Presence::class, ['campaign' => $campaign])
        ->call('here', [['id' => $wren->id, 'name' => 'Somebody Else', 'role' => 'owner']])
        ->assertSee('Tobin Ashgrove')
        ->assertDontSee('Somebody Else')
        ->assertSee('1 of 4 here');
});

it('lights no dot for somebody who is not in the campaign', function () {
    [$campaign, $gm] = tableWithFourMembers();
    $stranger = User::factory()->create(['name' => 'Passing Stranger']);

    Livewire::actingAs($gm)
        ->test(Presence::class, ['campaign' => $campaign])
        ->call('here', echoRoster($gm, $stranger))
        ->assertSee('1 of 4 here')
        ->assertDontSee('Passing Stranger');
});

it('shrugs off a payload with no ids in it', function () {
    [$campaign, $gm] = tableWithFourMembers();

    Livewire::actingAs($gm)
        ->test(Presence::class, ['campaign' => $campaign])
        ->call('here', [['name' => 'No id here'], 'not even an array'])
        ->assertOk()
        ->assertSee('0 of 4 here');
});

it('puts the strip on the table and on the Run screen', function () {
    [$campaign, $gm, $wren] = tableWithFourMembers();

    $this->actingAs($gm)->get(route('table', $campaign))
        ->assertOk()
        ->assertSee('0 of 4 here');

    GameSession::factory()->for($campaign)->number(1)->create();

    $this->actingAs($gm)->get(route('sessions.run', [$campaign, 1]))
        ->assertOk()
        ->assertSee('0 of 4 here');

    $this->actingAs($wren)->get(route('table', $campaign))
        ->assertOk()
        ->assertSee('0 of 4 here');
});

it('stops showing the roster to a member who was removed mid-session', function () {
    [$campaign, $gm, $wren] = tableWithFourMembers();

    $strip = Livewire::actingAs($wren)
        ->test(Presence::class, ['campaign' => $campaign])
        ->assertSee('Danny the GM');

    $campaign->members()->where('user_id', $wren->id)->delete();
    $campaign->forgetMemberCache();

    $strip->call('here', echoRoster($gm))->assertNotFound();
});
