<?php

namespace App\Livewire\Ledger;

use App\Actions\Ledger\DeleteLedgerEntry;
use App\Actions\Ledger\RecordLedgerEntry;
use App\Enums\EntityType;
use App\Enums\LedgerKind;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The party's purse and pack. Every member reads it and writes to it; the query
 * asks nobody's role, because nothing here is gated. The one link on a row, the
 * session, is loaded through GameSession::visibleTo() the clock's way.
 *
 * The balance and the inventory are computed from the rows on every render. A
 * stored total is the second source of truth that drifts.
 */
class Index extends Component
{
    use InteractsWithCampaign, WithPagination;

    public string $kind = LedgerKind::Coin->value;

    public string $amount = '';

    public string $itemName = '';

    public string $quantity = '1';

    public string $entityId = '';

    public string $note = '';

    public string $sessionId = '';

    /** 'find' or 'spend': the sign the form applies before it writes. */
    public string $direction = 'find';

    public function mount(Campaign $campaign): void
    {
        $this->enterCampaign($campaign);
        $this->authorize('viewAny', [LedgerEntry::class, $campaign]);
    }

    public function record(RecordLedgerEntry $recordLedgerEntry): void
    {
        $this->authorize('create', [LedgerEntry::class, $this->campaign]);

        $isCoin = $this->kind === LedgerKind::Coin->value;

        $validated = $this->validate([
            'kind' => ['required', Rule::enum(LedgerKind::class)],
            'amount' => $isCoin ? ['required', 'numeric', 'min:0.01', 'max:'.LedgerEntry::MAX_AMOUNT] : ['nullable'],
            'itemName' => $isCoin ? ['nullable'] : ['required', 'string', 'max:'.LedgerEntry::MAX_ITEM_NAME_LENGTH],
            'quantity' => $isCoin ? ['nullable'] : ['required', 'integer', 'min:1', 'max:'.LedgerEntry::MAX_QUANTITY],
            'entityId' => $isCoin ? ['nullable'] : $this->itemRule(),
            'note' => ['nullable', 'string', 'max:'.LedgerEntry::MAX_NOTE_LENGTH],
            'sessionId' => $this->sessionRule(),
            'direction' => ['required', Rule::in(['find', 'spend'])],
        ]);

        $sign = $validated['direction'] === 'spend' ? -1 : 1;
        $session = $this->sessionFor($validated['sessionId'] ?? '');

        if ($isCoin) {
            $recordLedgerEntry->coin($this->campaign, $this->user(), $sign * (float) $validated['amount'], $validated['note'] ?? null, $session);
        } else {
            $recordLedgerEntry->item(
                $this->campaign,
                $this->user(),
                $validated['itemName'],
                $sign * (int) $validated['quantity'],
                $this->itemPage($validated['entityId'] ?? ''),
                $validated['note'] ?? null,
                $session,
            );
        }

        $this->reset('amount', 'itemName', 'entityId', 'note', 'direction');
        $this->quantity = '1';
        $this->resetPage();
    }

    public function delete(string $entryId, DeleteLedgerEntry $deleteLedgerEntry): void
    {
        /** @var LedgerEntry $entry */
        $entry = LedgerEntry::query()->whereKey($entryId)->firstOrFail();

        $this->authorize('delete', $entry);

        $deleteLedgerEntry->handle($entry);
    }

    public function render(): View
    {
        $role = $this->role();
        $user = $this->user();

        // Two reads over the whole ledger: one sum for the purse, one list for the
        // pack. The page below is the same rows, newest first, paged.
        $balance = (float) LedgerEntry::query()->coin()->sum('amount');
        $inventory = LedgerEntry::inventory(LedgerEntry::query()->items()->orderBy('created_at')->get());

        $entries = LedgerEntry::query()
            ->with('author')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(50);

        /** @var Collection<int, LedgerEntry> $rows */
        $rows = collect($entries->items());

        return view('livewire.ledger.index', [
            'role' => $role,
            'currency' => $this->campaign->currency,
            'balance' => $balance,
            'inventory' => $inventory,
            'entries' => $entries,
            'sessionLinks' => $this->sessionLinks($rows),
            'itemLinks' => $this->itemLinks($rows->pluck('entity_id')->merge($inventory->pluck('entity_id'))),
            'kinds' => LedgerKind::cases(),
            'sessionOptions' => GameSession::query()->visibleTo($role)->orderByDesc('number')->get(['id', 'number', 'title']),
            // The Item pages this member may see, for the optional link.
            'itemOptions' => Entity::query()->ofType(EntityType::Item)->visibleTo($user, $role)->orderBy('name')->get(['id', 'name']),
        ])->title('Ledger');
    }

    /**
     * The session each row happened in, keyed by id, and only the ones this viewer
     * may see. One query, with the filter in it.
     *
     * @param  Collection<int, LedgerEntry>  $rows
     * @return Collection<string, GameSession>
     */
    private function sessionLinks(Collection $rows): Collection
    {
        /** @var Collection<int, string> $ids */
        $ids = $rows->pluck('game_session_id')->filter()->unique()->values();

        if ($ids->isEmpty()) {
            /** @var Collection<string, GameSession> $empty */
            $empty = new Collection;

            return $empty;
        }

        return GameSession::query()->visibleTo($this->role())->whereKey($ids->all())->get()->keyBy('id');
    }

    /**
     * The Item pages the rows point at, through the viewer's gate. A row whose page
     * is hidden keeps its name and loses the link.
     *
     * @param  Collection<int, string|null>  $ids
     * @return Collection<string, Entity>
     */
    private function itemLinks(Collection $ids): Collection
    {
        $ids = $ids->filter()->unique()->values();

        if ($ids->isEmpty()) {
            /** @var Collection<string, Entity> $empty */
            $empty = new Collection;

            return $empty;
        }

        return Entity::query()->visibleTo($this->user(), $this->role())->whereKey($ids->all())->get()->keyBy('id');
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

    /**
     * @return list<mixed>
     */
    private function itemRule(): array
    {
        return [
            'nullable',
            Rule::exists('entities', 'id')
                ->where('campaign_id', $this->campaign->id)
                ->where('type', EntityType::Item->value)
                ->whereNull('deleted_at'),
        ];
    }

    private function sessionFor(string $id): ?GameSession
    {
        return $id === '' ? null : GameSession::query()->whereKey($id)->first();
    }

    /**
     * Through the viewer's gate: a member cannot link a row to a page they may not see.
     */
    private function itemPage(string $id): ?Entity
    {
        return $id === '' ? null : Entity::query()->visibleTo($this->user(), $this->role())->whereKey($id)->first();
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
