<?php

namespace App\Actions\Ledger;

use App\Enums\LedgerKind;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\LedgerEntry;
use App\Models\User;

class RecordLedgerEntry
{
    /**
     * A coin movement. Negative is a spend.
     */
    public function coin(Campaign $campaign, User $actor, float $amount, ?string $note = null, ?GameSession $session = null): LedgerEntry
    {
        return LedgerEntry::create([
            'campaign_id' => $campaign->id,
            'game_session_id' => $session?->id,
            'kind' => LedgerKind::Coin,
            'amount' => round($amount, 2),
            'note' => filled($note) ? trim((string) $note) : null,
            'created_by' => $actor->id,
        ]);
    }

    /**
     * An item picked up or spent. Negative is a spend. The Item page is optional,
     * and the name is kept beside it so the row still reads after the page goes.
     */
    public function item(Campaign $campaign, User $actor, string $name, int $quantity, ?Entity $page = null, ?string $note = null, ?GameSession $session = null): LedgerEntry
    {
        return LedgerEntry::create([
            'campaign_id' => $campaign->id,
            'game_session_id' => $session?->id,
            'kind' => LedgerKind::Item,
            'item_name' => trim($name),
            'quantity' => $quantity,
            'entity_id' => $page?->id,
            'note' => filled($note) ? trim((string) $note) : null,
            'created_by' => $actor->id,
        ]);
    }
}
