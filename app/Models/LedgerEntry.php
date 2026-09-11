<?php

namespace App\Models;

use App\Enums\LedgerKind;
use App\Models\Concerns\BelongsToCampaign;
use Database\Factories\LedgerEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * One movement of the party's purse or pack.
 *
 * kind says which columns the row uses: amount for coin, item_name and quantity for
 * an item. Both are signed, so a spend and a sale are the same shape as a find. The
 * balance is a sum and the inventory a group-by, computed on every read.
 *
 * Nothing here is gated. The purse is the party's. The session link is loaded
 * separately through GameSession::visibleTo(), the clock's way.
 *
 * @property string $id
 * @property string $campaign_id
 * @property string|null $game_session_id
 * @property LedgerKind $kind
 * @property string|null $amount
 * @property string|null $item_name
 * @property int|null $quantity
 * @property string|null $entity_id
 * @property string|null $note
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Campaign $campaign
 * @property-read GameSession|null $gameSession
 * @property-read Entity|null $entity
 * @property-read User|null $author
 */
#[Fillable([
    'campaign_id', 'game_session_id', 'kind', 'amount', 'item_name', 'quantity', 'entity_id', 'note', 'created_by',
])]
class LedgerEntry extends Model
{
    use BelongsToCampaign;

    /** @use HasFactory<LedgerEntryFactory> */
    use HasFactory, HasUlids;

    public const MAX_AMOUNT = 999_999_999;

    public const MAX_QUANTITY = 999_999;

    public const MAX_ITEM_NAME_LENGTH = 120;

    public const MAX_NOTE_LENGTH = 500;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => LedgerKind::class,
            'amount' => 'decimal:2',
            'quantity' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<GameSession, $this>
     */
    public function gameSession(): BelongsTo
    {
        return $this->belongsTo(GameSession::class);
    }

    /**
     * The Item page this row is about, when the writer linked one.
     *
     * @return BelongsTo<Entity, $this>
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<LedgerEntry>  $query
     * @return Builder<LedgerEntry>
     */
    public function scopeCoin(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('kind'), LedgerKind::Coin->value);
    }

    /**
     * @param  Builder<LedgerEntry>  $query
     * @return Builder<LedgerEntry>
     */
    public function scopeItems(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('kind'), LedgerKind::Item->value);
    }

    public function isCoin(): bool
    {
        return $this->kind === LedgerKind::Coin;
    }

    /**
     * The key the inventory groups this row under: the Item page when linked, else
     * the name, case-folded, so "Torch" and "torch" are one line.
     */
    public function inventoryKey(): string
    {
        return $this->entity_id ?? 'name:'.mb_strtolower(trim((string) $this->item_name));
    }

    /**
     * "+40.00" or "-12.50", for a row that moves coin.
     */
    public function signedAmount(): string
    {
        $amount = (float) $this->amount;

        return ($amount < 0 ? '−' : '+').number_format(abs($amount), 2);
    }

    /**
     * The pack, summed from the rows: one line per item with a total above zero,
     * named by the last row that mentioned it. Computed, never stored.
     *
     * A plain list rather than a Collection: the shape is the contract, and a
     * Collection's value type is not covariant, so PHPStan cannot hold it.
     *
     * @param  Collection<int, LedgerEntry>  $rows
     * @return list<array{key: string, name: string, quantity: int, entity_id: string|null}>
     */
    public static function inventory(Collection $rows): array
    {
        $lines = [];

        foreach ($rows as $row) {
            if ($row->isCoin()) {
                continue;
            }

            $key = $row->inventoryKey();

            $lines[$key] = [
                'key' => $key,
                'name' => (string) $row->item_name,
                'quantity' => ($lines[$key]['quantity'] ?? 0) + (int) $row->quantity,
                'entity_id' => $row->entity_id,
            ];
        }

        $lines = array_values(array_filter($lines, fn (array $line): bool => $line['quantity'] > 0));

        usort($lines, fn (array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));

        return $lines;
    }
}
