<?php

namespace App\Livewire\Encounters;

use App\Actions\Encounters\DeleteEncounter;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Campaign;
use App\Models\Encounter;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * One fight on its own page, for a GM who wants the tracker full width or is building
 * an encounter before the session exists.
 *
 * The encounter is resolved in mount() after enterCampaign(), not by route model
 * binding, so the campaign scope is already in place. Slice 2 settled that for
 * /sessions/{number} and the reasoning is the same.
 */
class Show extends Component
{
    use InteractsWithCampaign;

    public Encounter $encounter;

    public string $name = '';

    public function mount(Campaign $campaign, string $encounterId): void
    {
        $this->enterCampaign($campaign);

        $found = Encounter::query()->whereKey($encounterId)->first();

        abort_if($found === null || ! $this->user()->can('view', $found), 404);

        $this->encounter = $found;
        $this->name = $found->name;
    }

    public function rename(): void
    {
        $this->authorize('update', $this->encounter);

        $validated = $this->validate(['name' => ['required', 'string', 'max:120']]);

        $this->encounter->update(['name' => trim($validated['name'])]);

        session()->flash('status', 'Encounter renamed.');
    }

    public function delete(DeleteEncounter $deleteEncounter): void
    {
        $this->authorize('delete', $this->encounter);

        $deleteEncounter->handle($this->encounter);

        $this->redirectRoute('encounters.index', $this->campaign);
    }

    public function render(): View
    {
        return view('livewire.encounters.show')->title($this->encounter->name);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
