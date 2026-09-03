<?php

namespace App\Models;

use App\Enums\CampaignRole;
use App\Enums\PrepRole;
use App\Enums\SessionStatus;
use App\Enums\Visibility;
use App\Models\Concerns\BelongsToCampaign;
use App\Observers\GameSessionObserver;
use Database\Factories\GameSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * One game night: what the GM prepped, what happened, and what the party may read.
 *
 * The table is game_sessions because the database session driver owns "sessions".
 *
 * @property string $id
 * @property string $campaign_id
 * @property int $number
 * @property string|null $title
 * @property Carbon|null $scheduled_at
 * @property SessionStatus $status
 * @property Visibility $visibility
 * @property string|null $strong_start
 * @property string|null $live_notes
 * @property string|null $recap
 * @property Carbon|null $recap_published_at
 * @property string|null $dm_notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Campaign $campaign
 * @property-read Collection<int, Scene> $scenes
 * @property-read Collection<int, Secret> $secrets
 * @property-read Collection<int, Entity> $entities
 */
#[ObservedBy([GameSessionObserver::class])]
#[Fillable([
    'campaign_id', 'number', 'title', 'scheduled_at', 'status', 'visibility',
    'strong_start', 'live_notes', 'recap', 'recap_published_at', 'dm_notes',
    'created_by', 'updated_by',
])]
class GameSession extends Model
{
    use BelongsToCampaign;

    /** @use HasFactory<GameSessionFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'scheduled_at' => 'datetime',
            'recap_published_at' => 'datetime',
            'status' => SessionStatus::class,
            'visibility' => Visibility::class,
        ];
    }

    /**
     * @return HasMany<Scene, $this>
     */
    public function scenes(): HasMany
    {
        return $this->hasMany(Scene::class)->orderBy('position');
    }

    /**
     * Secrets prepared for this session, revealed or not.
     *
     * @return HasMany<Secret, $this>
     */
    public function secrets(): HasMany
    {
        return $this->hasMany(Secret::class)->orderBy('position');
    }

    /**
     * Secrets that came out during this session, wherever they were prepared.
     *
     * @return HasMany<Secret, $this>
     */
    public function revealedSecrets(): HasMany
    {
        return $this->hasMany(Secret::class, 'revealed_in_session_id')->orderBy('revealed_at');
    }

    /**
     * @return BelongsToMany<Entity, $this>
     */
    public function entities(): BelongsToMany
    {
        return $this->belongsToMany(Entity::class, 'game_session_entities')
            ->withPivot(['role', 'position'])
            ->orderBy('game_session_entities.position');
    }

    /**
     * @return BelongsToMany<Entity, $this>
     */
    public function prepped(PrepRole $role): BelongsToMany
    {
        return $this->entities()->wherePivot('role', $role->value);
    }

    /**
     * Outbound [[links]] found in this session's Markdown fields.
     *
     * @return MorphMany<Mention, $this>
     */
    public function mentions(): MorphMany
    {
        return $this->morphMany(Mention::class, 'source');
    }

    /**
     * Fields the mention scanner and rename rewriter look at.
     *
     * @return list<string>
     */
    public function mentionableFields(): array
    {
        return ['strong_start', 'live_notes', 'recap', 'dm_notes'];
    }

    /**
     * Fields a player may never see. Everything that is not the recap.
     *
     * @return list<string>
     */
    public static function dmOnlyFields(): array
    {
        return ['strong_start', 'live_notes', 'dm_notes'];
    }

    /**
     * The one row-level filter. Sessions index, session page, dashboard cards,
     * sidebar count, and the "Appears in sessions" panel all go through it.
     *
     * @param  Builder<GameSession>  $query
     * @return Builder<GameSession>
     */
    public function scopeVisibleTo(Builder $query, CampaignRole $role): Builder
    {
        return $role->isDm()
            ? $query
            : $query->where($query->qualifyColumn('visibility'), Visibility::Players->value);
    }

    public function isVisibleTo(CampaignRole $role): bool
    {
        return $role->isDm() || $this->visibility === Visibility::Players;
    }

    public function hasPublishedRecap(): bool
    {
        return $this->recap_published_at !== null && filled($this->recap);
    }

    /**
     * A player reads a recap only when the GM published it on a session they can see.
     */
    public function isRecapVisibleTo(CampaignRole $role): bool
    {
        if ($role->isDm()) {
            return filled($this->recap);
        }

        return $this->isVisibleTo($role) && $this->hasPublishedRecap();
    }

    public function needsRecap(): bool
    {
        return $this->status === SessionStatus::Played && ! $this->hasPublishedRecap();
    }

    /**
     * Planned, dated, and the date has passed. The prompt to mark it played.
     */
    public function isOverdue(): bool
    {
        return $this->status === SessionStatus::Planned
            && $this->scheduled_at !== null
            && $this->scheduled_at->isPast();
    }

    public function scheduledAtIn(string $timezone): ?Carbon
    {
        return $this->scheduled_at?->copy()->setTimezone($timezone);
    }

    /**
     * "Session 12". How GMs speak about a session, whether or not it has a title.
     */
    public function label(): string
    {
        return "Session {$this->number}";
    }

    public function displayTitle(): string
    {
        return filled($this->title) ? (string) $this->title : $this->label();
    }

    public function url(): string
    {
        return route('sessions.show', [$this->campaign_id, $this->number]);
    }

    public function editUrl(): string
    {
        return route('sessions.edit', [$this->campaign_id, $this->number]);
    }

    public function prepUrl(): string
    {
        return route('sessions.prep', [$this->campaign_id, $this->number]);
    }

    public function runUrl(): string
    {
        return route('sessions.run', [$this->campaign_id, $this->number]);
    }
}
