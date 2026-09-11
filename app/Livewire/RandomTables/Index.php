<?php

namespace App\Livewire\RandomTables;

use App\Actions\RandomTables\CreateRandomTable;
use App\Actions\RandomTables\DeleteRandomTable;
use App\Actions\RandomTables\InstallGenerator;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Campaign;
use App\Models\RandomTable;
use App\Models\User;
use App\Support\Generators\Generators;
use App\Support\Generators\GeneratorSet;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
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

    /**
     * One shipped set into this campaign, or every set that is still out.
     */
    public function install(string $key, Generators $generators, InstallGenerator $installGenerator): void
    {
        $this->authorize('create', [RandomTable::class, $this->campaign]);

        $set = $generators->find($key);

        abort_if($set === null, 404);

        if (InstallGenerator::isInstalled($this->campaign, $set)) {
            return;
        }

        $made = $installGenerator->handle($this->campaign, $this->user(), $set);

        session()->flash('status', "{$set->name}: {$made->count()} ".Str::plural('table', $made->count()).' added.');
    }

    public function installAll(Generators $generators, InstallGenerator $installGenerator): void
    {
        $this->authorize('create', [RandomTable::class, $this->campaign]);

        $added = 0;

        foreach ($generators->all() as $set) {
            if (! InstallGenerator::isInstalled($this->campaign, $set)) {
                $added += $installGenerator->handle($this->campaign, $this->user(), $set)->count();
            }
        }

        session()->flash('status', $added.' '.Str::plural('table', $added).' added.');
    }

    public function delete(string $tableId, DeleteRandomTable $deleteRandomTable): void
    {
        $table = RandomTable::query()->whereKey($tableId)->firstOrFail();

        $this->authorize('delete', $table);

        $deleteRandomTable->handle($table);

        session()->flash('status', "{$table->name} was deleted.");
    }

    public function render(Generators $generators): View
    {
        $installedKeys = RandomTable::query()->whereNotNull('generator_key')->distinct()->pluck('generator_key')->flip()->all();

        $sets = $generators->all()->map(fn (GeneratorSet $set) => [
            'set' => $set,
            'installed' => isset($installedKeys[$set->key]),
        ])->values();

        return view('livewire.random-tables.index', [
            'tables' => RandomTable::query()->with('entries')->orderBy('name')->get(),
            'sets' => $sets,
            'anySetOut' => $sets->contains(fn (array $row) => ! $row['installed']),
        ])->title('Tables');
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
