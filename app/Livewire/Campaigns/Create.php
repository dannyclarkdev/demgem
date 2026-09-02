<?php

namespace App\Livewire\Campaigns;

use App\Actions\Campaigns\CreateCampaign;
use App\Enums\Ruleset;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('New campaign')]
class Create extends Component
{
    public string $name = '';

    public string $description = '';

    public string $ruleset = Ruleset::Srd5e2024->value;

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'ruleset' => ['required', Rule::enum(Ruleset::class)],
        ];
    }

    public function save(CreateCampaign $createCampaign): void
    {
        $validated = $this->validate();

        /** @var User $user */
        $user = auth()->user();

        $campaign = $createCampaign->handle($user, [
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'ruleset' => $validated['ruleset'],
        ]);

        session()->flash('status', "{$campaign->name} is ready. Invite your players from the members page.");

        $this->redirectRoute('campaigns.show', $campaign);
    }

    public function render(): View
    {
        return view('livewire.campaigns.create', ['rulesets' => Ruleset::cases()]);
    }
}
