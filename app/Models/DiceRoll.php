<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCampaign;
use Database\Factories\DiceRollFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One roll, kept so the log survives a refresh at the table.
 *
 * @property string $id
 * @property string $campaign_id
 * @property string|null $game_session_id
 * @property int $user_id
 * @property string $formula
 * @property string|null $label
 * @property int $total
 * @property array{terms: list<array{expression: string, sign: int, faces: list<int>, dropped: list<int>, subtotal: int}>, modifier: int} $detail
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Campaign $campaign
 * @property-read GameSession|null $gameSession
 * @property-read User $user
 */
#[Fillable(['campaign_id', 'game_session_id', 'user_id', 'formula', 'label', 'total', 'detail'])]
class DiceRoll extends Model
{
    use BelongsToCampaign;

    /** @use HasFactory<DiceRollFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total' => 'integer',
            'detail' => 'array',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Every face rolled, dropped ones included, for a one-line summary under the total.
     *
     * @return list<array{face: int, dropped: bool}>
     */
    public function faces(): array
    {
        $faces = [];

        foreach ($this->detail['terms'] as $term) {
            foreach ($term['faces'] as $face) {
                $faces[] = ['face' => $face, 'dropped' => false];
            }

            foreach ($term['dropped'] as $face) {
                $faces[] = ['face' => $face, 'dropped' => true];
            }
        }

        return $faces;
    }
}
