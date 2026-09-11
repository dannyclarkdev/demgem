<?php

namespace App\Livewire\Factions;

use App\Actions\Factions\AdjustReputation;
use App\Actions\Factions\DeleteReputationChange;
use App\Actions\Factions\SetReputationVisibility;
use App\Actions\Factions\UpdateReputationReason;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\ReputationChange;
use App\Models\User;
use App\Support\Reputation\Standing;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * The standing card on a faction's page, drawn once for two audiences.
 *
 * The rows a viewer sees come through ReputationChange::visibleTo(), and the number
 * they read is the sum of those rows. A GM reads two numbers when they differ: what
 * the party has noticed, and the truth. The session link on a row is loaded through
 * GameSession::visibleTo() separately, the clock's way.
 *
 * Nested and it writes, so it re-checks membership itself on every round trip. The
 * page around it is already gated by Entity::visibleTo().
 */
class Reputation extends Component
{
    use InteractsWithCampaign;

    public Entity $faction;

    public string $newDelta = '1';

    public string $newReason = '';

    public string $newSessionId = '';

    public ?string $editingId = null;

    public string $editingReason = '';

    public function mount(Campaign $campaign, Entity $faction): void
    {
        $this->enterCampaign($campaign);

        abort_unless($faction->campaign_id === $campaign->id && $faction->isFaction(), 404);

        $this->faction = $faction;
    }

    public function adjust(AdjustReputation $adjustReputation): void
    {
        $this->authorize('create', [ReputationChange::class, $this->campaign]);

        $max = Standing::maxDelta();

        $validated = $this->validate([
            'newDelta' => ['required', 'integer', 'min:-'.$max, 'max:'.$max, 'not_in:0'],
            'newReason' => ['nullable', 'string', 'max:'.ReputationChange::MAX_REASON_LENGTH],
            'newSessionId' => $this->sessionRule(),
        ]);

        $adjustReputation->handle(
            $this->faction,
            $this->user(),
            (int) $validated['newDelta'],
            $validated['newReason'] ?? null,
            $this->sessionFor($validated['newSessionId'] ?? ''),
        );

        $this->reset('newReason', 'newSessionId');
        $this->newDelta = '1';
    }

    public function edit(string $changeId): void
    {
        $change = $this->change($changeId);

        $this->authorize('update', $change);

        $this->editingId = $change->id;
        $this->editingReason = $change->reason ?? '';
    }

    public function cancelEdit(): void
    {
        $this->reset('editingId', 'editingReason');
    }

    public function saveReason(UpdateReputationReason $updateReputationReason): void
    {
        $change = $this->change((string) $this->editingId);

        $this->authorize('update', $change);

        $validated = $this->validate([
            'editingReason' => ['nullable', 'string', 'max:'.ReputationChange::MAX_REASON_LENGTH],
        ]);

        $updateReputationReason->handle($change, $validated['editingReason'] ?? null);

        $this->cancelEdit();
    }

    public function toggleVisibility(string $changeId, SetReputationVisibility $setReputationVisibility): void
    {
        $change = $this->change($changeId);

        $this->authorize('update', $change);

        $setReputationVisibility->toggle($change);
    }

    public function delete(string $changeId, DeleteReputationChange $deleteReputationChange): void
    {
        $change = $this->change($changeId);

        $this->authorize('delete', $change);

        $deleteReputationChange->handle($change);

        if ($this->editingId === $changeId) {
            $this->cancelEdit();
        }
    }

    public function render(): View
    {
        $role = $this->role();

        $changes = ReputationChange::query()
            ->about($this->faction)
            ->visibleTo($role)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        // What the party has noticed is the sum of the revealed rows. A GM also reads
        // the truth, the sum of all of them, when the two differ.
        $noticed = new Standing((int) $changes->where('player_visible', true)->sum('delta'));
        $truth = $role->isDm() ? new Standing((int) $changes->sum('delta')) : null;

        return view('livewire.factions.reputation', [
            'changes' => $changes,
            'noticed' => $noticed,
            'truth' => $truth,
            'hasVisibleRows' => $changes->where('player_visible', true)->isNotEmpty(),
            'sessionLinks' => $this->sessionLinks($changes),
            'canManage' => $role->isDm(),
            'deltas' => $this->deltaOptions(),
            'sessionOptions' => $role->isDm()
                ? GameSession::query()->orderByDesc('number')->get(['id', 'number', 'title'])
                : collect(),
        ]);
    }

    /**
     * @return list<int>
     */
    private function deltaOptions(): array
    {
        $max = Standing::maxDelta();
        $options = [];

        for ($delta = $max; $delta >= -$max; $delta--) {
            if ($delta !== 0) {
                $options[] = $delta;
            }
        }

        return $options;
    }

    /**
     * @param  Collection<int, ReputationChange>  $changes
     * @return Collection<string, GameSession>
     */
    private function sessionLinks(Collection $changes): Collection
    {
        /** @var Collection<int, string> $ids */
        $ids = $changes->pluck('game_session_id')->filter()->unique()->values();

        if ($ids->isEmpty()) {
            /** @var Collection<string, GameSession> $empty */
            $empty = new Collection;

            return $empty;
        }

        return GameSession::query()->visibleTo($this->role())->whereKey($ids->all())->get()->keyBy('id');
    }

    /**
     * @return list<mixed>
     */
    private function sessionRule(): array
    {
        return [
            'nullable',
            Rule::exists('game_sessions', 'id')->where('campaign_id', $this->campaign->id)->whereNull('deleted_at'),
        ];
    }

    private function sessionFor(string $id): ?GameSession
    {
        return $id === '' ? null : GameSession::query()->whereKey($id)->first();
    }

    private function change(string $changeId): ReputationChange
    {
        /** @var ReputationChange $change */
        $change = ReputationChange::query()->about($this->faction)->whereKey($changeId)->firstOrFail();

        return $change;
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
