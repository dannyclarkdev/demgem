<?php

namespace App\Livewire\Campaigns;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Campaigns')]
class Index extends Component
{
    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();

        return view('livewire.campaigns.index', [
            'campaigns' => $user->campaigns()->withCount('members')->with('media')->orderBy('name')->get(),
        ]);
    }
}
