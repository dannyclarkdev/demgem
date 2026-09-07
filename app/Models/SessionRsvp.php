<?php

namespace App\Models;

use App\Enums\Rsvp;
use App\Models\Concerns\BelongsToCampaign;
use Database\Factories\SessionRsvpFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One member's answer about one session: what they said beforehand, and whether
 * they were there. Either half may be unset; a row with neither is deleted.
 *
 * @property int $id
 * @property string $campaign_id
 * @property string $game_session_id
 * @property int $user_id
 * @property Rsvp|null $rsvp
 * @property bool|null $attended
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Campaign $campaign
 * @property-read GameSession $gameSession
 * @property-read User $user
 */
#[Fillable(['campaign_id', 'game_session_id', 'user_id', 'rsvp', 'attended'])]
class SessionRsvp extends Model
{
    use BelongsToCampaign;

    /** @use HasFactory<SessionRsvpFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'rsvp' => Rsvp::class,
            'attended' => 'boolean',
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
     * The GM's mark when there is one, and the member's own yes until then. Reads
     * the same everywhere: the card, the headcount, and the Markdown front matter.
     */
    public function wasThere(): bool
    {
        return $this->attended ?? $this->rsvp === Rsvp::Yes;
    }

    public function isEmpty(): bool
    {
        return $this->rsvp === null && $this->attended === null;
    }
}
