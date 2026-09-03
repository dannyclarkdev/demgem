<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCampaign;
use Database\Factories\QuestObjectiveFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One step of a quest. Objectives inherit the quest's visibility: a player who can
 * read the quest can read its steps, and a step that must stay hidden belongs in the
 * quest's GM notes or in a secret.
 *
 * Objectives render their [[wiki links]] but are not indexed as mention sources. The
 * backlinks query resolves a source id straight to an entity, and an objective would
 * need a second hop through its quest's visibility inside the app's riskiest query.
 * The quest's rewards field carries the wiki-link payoff instead.
 *
 * @property string $id
 * @property string $campaign_id
 * @property string $entity_id
 * @property int $position
 * @property string $body
 * @property Carbon|null $completed_at
 * @property string|null $completed_in_session_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Campaign $campaign
 * @property-read Entity $quest
 * @property-read GameSession|null $completedInSession
 */
#[Fillable(['campaign_id', 'entity_id', 'position', 'body', 'completed_at', 'completed_in_session_id'])]
class QuestObjective extends Model
{
    use BelongsToCampaign;

    /** @use HasFactory<QuestObjectiveFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Entity, $this>
     */
    public function quest(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'entity_id');
    }

    /**
     * The session the party finished this in, when it was ticked from the Run screen.
     *
     * @return BelongsTo<GameSession, $this>
     */
    public function completedInSession(): BelongsTo
    {
        return $this->belongsTo(GameSession::class, 'completed_in_session_id');
    }

    /**
     * @param  Builder<QuestObjective>  $query
     * @return Builder<QuestObjective>
     */
    public function scopeComplete(Builder $query): Builder
    {
        return $query->whereNotNull($query->qualifyColumn('completed_at'));
    }

    public function isComplete(): bool
    {
        return $this->completed_at !== null;
    }
}
