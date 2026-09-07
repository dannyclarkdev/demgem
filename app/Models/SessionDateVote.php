<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCampaign;
use Database\Factories\SessionDateVoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * "I can make this." The row is the whole answer.
 *
 * @property int $id
 * @property string $campaign_id
 * @property string $session_date_option_id
 * @property int $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Campaign $campaign
 * @property-read SessionDateOption $option
 * @property-read User $user
 */
#[Fillable(['campaign_id', 'session_date_option_id', 'user_id'])]
class SessionDateVote extends Model
{
    use BelongsToCampaign;

    /** @use HasFactory<SessionDateVoteFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<SessionDateOption, $this>
     */
    public function option(): BelongsTo
    {
        return $this->belongsTo(SessionDateOption::class, 'session_date_option_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
