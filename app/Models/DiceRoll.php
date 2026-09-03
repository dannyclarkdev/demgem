<?php

namespace App\Models;

use App\Enums\CampaignRole;
use App\Models\Concerns\BelongsToCampaign;
use Database\Factories\DiceRollFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One roll, kept so the log survives a refresh at the table.
 *
 * The log is shared from slice 5 on. private is the GM's screen: a private roll is
 * read by the person who made it and by nobody else, and only a GM may set it.
 *
 * @property string $id
 * @property string $campaign_id
 * @property string|null $game_session_id
 * @property int $user_id
 * @property string $formula
 * @property string|null $label
 * @property int $total
 * @property array{terms: list<array{expression: string, sign: int, faces: list<int>, dropped: list<int>, subtotal: int}>, modifier: int} $detail
 * @property bool $private
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Campaign $campaign
 * @property-read GameSession|null $gameSession
 * @property-read User $user
 */
#[Fillable(['campaign_id', 'game_session_id', 'user_id', 'formula', 'label', 'total', 'detail', 'private'])]
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
            'private' => 'boolean',
        ];
    }

    /**
     * The one filter for the shared log. Everything public, plus this viewer's own
     * private rolls, whatever their role: a GM reading their own screen is the whole
     * point of the column.
     *
     * @param  Builder<DiceRoll>  $query
     * @return Builder<DiceRoll>
     */
    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        return $query->where(function (Builder $visible) use ($viewer): void {
            $visible->where($visible->qualifyColumn('private'), false)
                ->orWhere($visible->qualifyColumn('user_id'), $viewer->id);
        });
    }

    /**
     * Who may roll behind the screen. A player's roll is never private: a roll nobody
     * else sees is a roll they did not make.
     */
    public static function mayRollPrivately(?CampaignRole $role): bool
    {
        return $role?->isDm() ?? false;
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
