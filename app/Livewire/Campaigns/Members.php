<?php

namespace App\Livewire\Campaigns;

use App\Actions\Campaigns\LeaveCampaign;
use App\Actions\Campaigns\RemoveMember;
use App\Actions\Invites\CreateInvite;
use App\Actions\Invites\RevokeInvite;
use App\Enums\CampaignRole;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Members')]
class Members extends Component
{
    use InteractsWithCampaign;

    public string $inviteRole = CampaignRole::Player->value;

    /** Days until the invite expires. Empty means never. */
    public string $inviteExpiresIn = '7';

    /** Empty means unlimited. */
    public string $inviteMaxUses = '';

    public function mount(Campaign $campaign): void
    {
        $this->enterCampaign($campaign);
    }

    public function changeRole(int $memberId, string $role): void
    {
        $this->authorize('changeRoles', $this->campaign);

        $member = $this->campaign->members()->findOrFail($memberId);
        $newRole = CampaignRole::from($role);

        abort_if($member->isOwner() || $newRole === CampaignRole::Owner, 403, 'Use transfer ownership for the owner role.');

        $member->update(['role' => $newRole]);
        $this->campaign->forgetMemberCache();
    }

    public function remove(int $memberId, RemoveMember $removeMember): void
    {
        $member = $this->campaign->members()->findOrFail($memberId);

        $this->authorize('removeMember', [$this->campaign, $member]);

        $removeMember->handle($member);
    }

    public function leave(LeaveCampaign $leaveCampaign): void
    {
        $this->authorize('leave', $this->campaign);

        $leaveCampaign->handle($this->campaign, $this->user());

        session()->flash('status', "You left {$this->campaign->name}.");

        $this->redirectRoute('campaigns.index');
    }

    public function createInvite(CreateInvite $createInvite): void
    {
        $this->authorize('createInvite', $this->campaign);

        $validated = $this->validate([
            'inviteRole' => ['required', Rule::in(array_map(fn (CampaignRole $role) => $role->value, CampaignRole::invitable()))],
            'inviteExpiresIn' => ['nullable', 'integer', 'min:1', 'max:365'],
            'inviteMaxUses' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $createInvite->handle(
            $this->campaign,
            $this->user(),
            CampaignRole::from($validated['inviteRole']),
            $validated['inviteMaxUses'] !== null && $validated['inviteMaxUses'] !== '' ? (int) $validated['inviteMaxUses'] : null,
            $validated['inviteExpiresIn'] !== null && $validated['inviteExpiresIn'] !== '' ? now()->addDays((int) $validated['inviteExpiresIn']) : null,
        );
    }

    public function revokeInvite(string $inviteId, RevokeInvite $revokeInvite): void
    {
        $this->authorize('createInvite', $this->campaign);

        $revokeInvite->handle($this->campaign->invites()->findOrFail($inviteId));
    }

    public function render(): View
    {
        $role = $this->role();

        $members = $this->campaign->members()->with('user')->get()
            ->sortBy(fn ($member) => [$member->role->weight(), $member->user->name]);

        $invites = $role->isDm()
            ? $this->campaign->invites()->whereNull('revoked_at')->latest()->get()->filter->isValid()
            : collect();

        return view('livewire.campaigns.members', [
            'role' => $role,
            'members' => $members,
            'invites' => $invites,
            'invitableRoles' => CampaignRole::invitable(),
            'assignableRoles' => CampaignRole::invitable(),
        ]);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
