<?php

use App\Enums\CampaignRole;
use App\Enums\Ruleset;
use App\Livewire\Campaigns\Create;
use App\Models\Campaign;
use App\Models\User;
use Livewire\Livewire;

it('renders the create page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('campaigns.create'))
        ->assertOk()
        ->assertSeeLivewire(Create::class);
});

it('creates a campaign and makes the creator its owner', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Create::class)
        ->set('name', 'Curse of the Ember Throne')
        ->set('description', 'A slow-burn horror campaign.')
        ->set('ruleset', Ruleset::Srd5e2024->value)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $campaign = Campaign::where('name', 'Curse of the Ember Throne')->firstOrFail();

    expect($campaign->ruleset)->toBe(Ruleset::Srd5e2024)
        ->and($campaign->created_by)->toBe($user->id)
        ->and($campaign->roleFor($user))->toBe(CampaignRole::Owner)
        ->and($campaign->members()->count())->toBe(1);
});

it('rejects a campaign without a name', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(Create::class)
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name' => 'required']);

    expect(Campaign::count())->toBe(0);
});

it('rejects an unknown ruleset', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(Create::class)
        ->set('name', 'Test')
        ->set('ruleset', 'pathfinder-1e')
        ->call('save')
        ->assertHasErrors(['ruleset']);
});

it('lists only the campaigns the user belongs to', function () {
    $user = User::factory()->create();
    $mine = Campaign::factory()->ownedBy($user)->create(['name' => 'Mine Alone']);
    $joined = Campaign::factory()->withMember($user, CampaignRole::Player)->create(['name' => 'Joined Table']);
    Campaign::factory()->create(['name' => 'Someone Elses Game']);

    $this->actingAs($user)
        ->get(route('campaigns.index'))
        ->assertOk()
        ->assertSee($mine->name)
        ->assertSee($joined->name)
        ->assertDontSee('Someone Elses Game');
});
