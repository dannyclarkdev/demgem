<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCampaign;
use App\Observers\SceneObserver;
use Database\Factories\SceneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * One thing that might happen tonight. Scenes link entities through [[wiki links]]
 * in their notes, not through a second pivot.
 *
 * @property string $id
 * @property string $campaign_id
 * @property string $game_session_id
 * @property int $position
 * @property string $title
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Campaign $campaign
 * @property-read GameSession $gameSession
 */
#[ObservedBy([SceneObserver::class])]
#[Fillable(['campaign_id', 'game_session_id', 'position', 'title', 'notes'])]
class Scene extends Model
{
    use BelongsToCampaign;

    /** @use HasFactory<SceneFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
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
     * @return MorphMany<Mention, $this>
     */
    public function mentions(): MorphMany
    {
        return $this->morphMany(Mention::class, 'source');
    }

    /**
     * @return list<string>
     */
    public function mentionableFields(): array
    {
        return ['notes'];
    }
}
