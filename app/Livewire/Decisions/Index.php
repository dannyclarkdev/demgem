<?php

namespace App\Livewire\Decisions;

use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Campaign;
use App\Models\Decision;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The page around the log. Every member; what each reads is the log's own query.
 */
class Index extends Component
{
    use InteractsWithCampaign;

    public function mount(Campaign $campaign): void
    {
        $this->enterCampaign($campaign);
        $this->authorize('viewAny', [Decision::class, $campaign]);
    }

    public function render(): View
    {
        return view('livewire.decisions.index', [
            'role' => $this->role(),
        ])->title('Decisions');
    }
}
