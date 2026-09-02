<?php

use App\Actions\Invites\CreateInvite;
use App\Enums\CampaignRole;
use App\Livewire\Campaigns\Members;
use App\Models\Campaign;
use App\Models\CampaignInvite;
use Livewire\Livewire;

it('lets a co-GM create an invite with a role, expiry, and use limit', function () {
    $campaign = Campaign::factory()->create();
    $coGm = memberOf($campaign, CampaignRole::CoGm);

    Livewire::actingAs($coGm)
        ->test(Members::class, ['campaign' => $campaign])
        ->set('inviteRole', CampaignRole::Spectator->value)
        ->set('inviteExpiresIn', '7')
        ->set('inviteMaxUses', '5')
        ->call('createInvite')
        ->assertHasNoErrors();

    $invite = $campaign->invites()->firstOrFail();

    expect($invite->role)->toBe(CampaignRole::Spectator)
        ->and($invite->max_uses)->toBe(5)
        ->and($invite->expires_at->isSameDay(now()->addDays(7)))->toBeTrue()
        ->and(strlen($invite->token))->toBe(40)
        ->and($invite->created_by)->toBe($coGm->id)
        ->and($invite->isValid())->toBeTrue();
});

it('creates an invite that never expires with unlimited uses', function () {
    $campaign = Campaign::factory()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Members::class, ['campaign' => $campaign])
        ->set('inviteExpiresIn', '')
        ->set('inviteMaxUses', '')
        ->call('createInvite')
        ->assertHasNoErrors();

    $invite = $campaign->invites()->firstOrFail();

    expect($invite->expires_at)->toBeNull()
        ->and($invite->max_uses)->toBeNull();
});

it('forbids players from creating invites', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    Livewire::actingAs($player)
        ->test(Members::class, ['campaign' => $campaign])
        ->call('createInvite')
        ->assertForbidden();

    expect($campaign->invites()->count())->toBe(0);
});

it('rejects an invite for the owner role', function () {
    $campaign = Campaign::factory()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Members::class, ['campaign' => $campaign])
        ->set('inviteRole', CampaignRole::Owner->value)
        ->call('createInvite')
        ->assertHasErrors(['inviteRole']);

    expect(fn () => app(CreateInvite::class)->handle($campaign, ownerOf($campaign), CampaignRole::Owner))
        ->toThrow(InvalidArgumentException::class);
});

it('revokes an invite', function () {
    $campaign = Campaign::factory()->create();
    $invite = CampaignInvite::factory()->for($campaign)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Members::class, ['campaign' => $campaign])
        ->call('revokeInvite', $invite->id)
        ->assertHasNoErrors();

    expect($invite->fresh()->isRevoked())->toBeTrue()
        ->and($invite->fresh()->isValid())->toBeFalse();
});

it('shows only valid invites on the members page', function () {
    $campaign = Campaign::factory()->create();
    $valid = CampaignInvite::factory()->for($campaign)->create();
    $expired = CampaignInvite::factory()->for($campaign)->expired()->create();
    $revoked = CampaignInvite::factory()->for($campaign)->revoked()->create();

    $this->actingAs(ownerOf($campaign))
        ->get(route('campaigns.members', $campaign))
        ->assertSee($valid->token)
        ->assertDontSee($expired->token)
        ->assertDontSee($revoked->token);
});
