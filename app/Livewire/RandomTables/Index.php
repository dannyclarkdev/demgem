<?php

namespace App\Livewire\RandomTables;

use App\Actions\RandomTables\CreateRandomTable;
use App\Actions\RandomTables\DeleteRandomTable;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Campaign;
use App\Models\RandomTable;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Every table in the campaign. GM-only; the route 404s for a player.
 */
class Index extends Component
{
    use InteractsWithCampaign;

    public string $newName = '';

    public function mount(Campaign $campaign): void
    {
        $this->enterCampaign($campaign);
        $this->authorize('viewAny', [RandomTable::class, $campaign]);
    }

    public function create(CreateRandomTable $createRandomTable): void
    {
        $this->authorize('create', [RandomTable::class, $this->campaign]);

        $validated = $this->validate([
            'newName' => [
                'required', 'string', 'max:120',
                Rule::unique('random_tables', 'name')->where('campaign_id', $this->campaign->id),
            ],
        ]);

        $table = $createRandomTable->handle($this->campaign, $this->user(), $validated['newName']);

        $this->redirect($table->url());
    }

    public function delete(string $tableId, DeleteRandomTable $deleteRandomTable): void
    {
        $table = RandomTable::query()->whereKey($tableId)->firstOrFail();

        $this->authorize('delete', $table);

        $deleteRandomTable->handle($table);

        session()->flash('status', "{$table->name} was deleted.");
    }

    public function render(): View
    {
        return view('livewire.random-tables.index', [
            'tables' => RandomTable::query()->with('entries')->orderBy('name')->get(),
        ])->title('Tables');
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
