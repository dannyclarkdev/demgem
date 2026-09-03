<?php

namespace App\Livewire\Campaigns;

use App\Actions\Campaigns\DeleteCampaign;
use App\Actions\Campaigns\TransferOwnership;
use App\Enums\CampaignRole;
use App\Enums\Ruleset;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Campaign;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Title('Settings')]
class Settings extends Component
{
    use InteractsWithCampaign, WithFileUploads;

    public ?TemporaryUploadedFile $cover = null;

    public bool $removeCover = false;

    public string $name = '';

    public string $description = '';

    public string $ruleset = '';

    public string $timezone = 'UTC';

    public string $newOwnerId = '';

    public string $deleteConfirmation = '';

    public function mount(Campaign $campaign): void
    {
        $this->enterCampaign($campaign);
        $this->authorize('update', $campaign);

        $this->name = $campaign->name;
        $this->description = $campaign->description ?? '';
        $this->ruleset = $campaign->ruleset->value;
        $this->timezone = $campaign->timezone;
    }

    public function save(): void
    {
        $this->authorize('update', $this->campaign);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'ruleset' => ['required', Rule::enum(Ruleset::class)],
            'timezone' => ['required', 'timezone'],
            'cover' => ['nullable', 'image', 'max:8192'],
        ]);

        if ($this->removeCover) {
            $this->campaign->clearMediaCollection('cover');
        }

        if ($this->cover !== null) {
            $this->campaign->addMedia($this->cover->getRealPath())
                ->usingFileName($this->cover->getClientOriginalName())
                ->toMediaCollection('cover');
        }

        $this->campaign->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'ruleset' => $validated['ruleset'],
            'timezone' => $validated['timezone'],
        ]);

        session()->flash('status', 'Campaign settings saved.');

        $this->redirectRoute('campaigns.settings', $this->campaign);
    }

    public function transfer(TransferOwnership $transferOwnership): void
    {
        $this->authorize('transferOwnership', $this->campaign);

        $this->validate([
            'newOwnerId' => ['required', Rule::exists('campaign_members', 'id')->where('campaign_id', $this->campaign->id)],
        ]);

        $newOwner = $this->campaign->members()->findOrFail($this->newOwnerId);

        $transferOwnership->handle($this->campaign, $newOwner);

        session()->flash('status', "{$newOwner->user->name} now owns {$this->campaign->name}. You are a co-GM.");

        $this->redirectRoute('campaigns.show', $this->campaign);
    }

    public function delete(DeleteCampaign $deleteCampaign): void
    {
        $this->authorize('delete', $this->campaign);

        if ($this->deleteConfirmation !== $this->campaign->name) {
            $this->addError('deleteConfirmation', 'Type the campaign name exactly to confirm.');

            return;
        }

        $deleteCampaign->handle($this->campaign);

        session()->flash('status', "{$this->campaign->name} was deleted.");

        $this->redirectRoute('campaigns.index');
    }

    public function render(): View
    {
        $role = $this->role();

        return view('livewire.campaigns.settings', [
            'role' => $role,
            'rulesets' => Ruleset::cases(),
            'timezones' => timezone_identifiers_list(),
            'transferCandidates' => $role === CampaignRole::Owner
                ? $this->campaign->members()->with('user')->where('role', '!=', CampaignRole::Owner)->get()->sortBy('user.name')
                : collect(),
        ]);
    }
}
