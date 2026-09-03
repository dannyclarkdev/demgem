<?php

namespace App\Models;

use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Enums\QuestStatus;
use App\Enums\Visibility;
use App\Models\Concerns\BelongsToCampaign;
use App\Observers\EntityObserver;
use Database\Factories\EntityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Scout\Searchable;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * One row per thing in a campaign world: character, location, faction, item, quest, note.
 *
 * @property string $id
 * @property string $campaign_id
 * @property EntityType $type
 * @property string $name
 * @property string $slug
 * @property string|null $body
 * @property string|null $dm_notes
 * @property string|null $rewards
 * @property Visibility $visibility
 * @property string|null $parent_id
 * @property bool $is_pc
 * @property int|null $player_user_id
 * @property string|null $character_class
 * @property int|null $level
 * @property string|null $sheet_url
 * @property QuestStatus|null $quest_status
 * @property string|null $giver_entity_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Campaign $campaign
 * @property-read Entity|null $parent
 * @property-read Collection<int, Entity> $children
 * @property-read Collection<int, Tag> $tags
 * @property-read Collection<int, User> $viewers
 * @property-read User|null $player
 * @property-read Entity|null $giver
 * @property-read Collection<int, QuestObjective> $objectives
 * @property-read Pivot|null $pivot Set when the row was loaded through GameSession::entities()
 */
#[ObservedBy([EntityObserver::class])]
#[Fillable([
    'campaign_id', 'type', 'name', 'slug', 'body', 'dm_notes', 'rewards', 'visibility',
    'parent_id', 'is_pc', 'player_user_id', 'character_class', 'level', 'sheet_url',
    'quest_status', 'giver_entity_id',
    'created_by', 'updated_by',
])]
class Entity extends Model implements HasMedia
{
    use BelongsToCampaign;

    /** @use HasFactory<EntityFactory> */
    use HasFactory, HasUlids, InteractsWithMedia, Searchable, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EntityType::class,
            'visibility' => Visibility::class,
            'quest_status' => QuestStatus::class,
            'is_pc' => 'boolean',
            'level' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Entity, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'parent_id');
    }

    /**
     * @return HasMany<Entity, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Entity::class, 'parent_id');
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Players who may see this entity when visibility is "selected".
     *
     * @return BelongsToMany<User, $this>
     */
    public function viewers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'entity_viewers');
    }

    /**
     * Outbound [[links]] found in this entity's fields.
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
     * rewards is player-visible, so Entities\Show widens its player-facing backlink
     * filter to match. Objectives are deliberately absent; see QuestObjective.
     *
     * @return list<string>
     */
    public function mentionableFields(): array
    {
        return ['body', 'dm_notes', 'rewards'];
    }

    /**
     * Fields a player may read on an entity they can see. The backlinks query uses this.
     *
     * @return list<string>
     */
    public static function playerVisibleFields(): array
    {
        return ['body', 'rewards'];
    }

    /**
     * The steps of a quest, in order.
     *
     * @return HasMany<QuestObjective, $this>
     */
    public function objectives(): HasMany
    {
        return $this->hasMany(QuestObjective::class, 'entity_id')->orderBy('position');
    }

    /**
     * Who handed this quest out. Any entity type may be a giver; the picker only
     * suggests characters and factions first.
     *
     * @return BelongsTo<Entity, $this>
     */
    public function giver(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'giver_entity_id');
    }

    /**
     * @return HasMany<Entity, $this>
     */
    public function givenQuests(): HasMany
    {
        return $this->hasMany(Entity::class, 'giver_entity_id');
    }

    public function isQuest(): bool
    {
        return $this->type === EntityType::Quest;
    }

    public function isCharacter(): bool
    {
        return $this->type === EntityType::Character;
    }

    /**
     * Whether there is a record row to render at all. A character with none of the
     * four facts renders no row, rather than an empty one.
     */
    public function hasCharacterRecord(): bool
    {
        return $this->isCharacter()
            && (filled($this->character_class) || $this->level !== null || filled($this->sheet_url) || $this->is_pc);
    }

    /**
     * The host of the sheet link, for a button that says "D&D Beyond" instead of
     * showing 200 characters of URL.
     */
    public function sheetHost(): ?string
    {
        if (blank($this->sheet_url)) {
            return null;
        }

        $host = parse_url((string) $this->sheet_url, PHP_URL_HOST);

        return is_string($host) ? preg_replace('/^www\\./', '', $host) : null;
    }

    /**
     * A quest with no stored status reads as available. The column is nullable because
     * it is meaningless on the other five types, and a row can reach here from a raw
     * insert or an older factory without one.
     */
    public function questStatus(): ?QuestStatus
    {
        if (! $this->isQuest()) {
            return null;
        }

        return $this->quest_status ?? QuestStatus::Available;
    }

    /**
     * Objective counts for a progress bar. Uses the loaded relation when there is one,
     * so a list that eager-loads objectives costs no extra query per row.
     *
     * @return array{done: int, total: int}
     */
    public function objectiveProgress(): array
    {
        if ($this->relationLoaded('objectives')) {
            return [
                'done' => $this->objectives->filter(fn (QuestObjective $o) => $o->isComplete())->count(),
                'total' => $this->objectives->count(),
            ];
        }

        return [
            'done' => $this->objectives()->complete()->count(),
            'total' => $this->objectives()->count(),
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_user_id');
    }

    /**
     * @param  Builder<Entity>  $query
     * @return Builder<Entity>
     */
    public function scopeOfType(Builder $query, EntityType $type): Builder
    {
        return $query->where($query->qualifyColumn('type'), $type->value);
    }

    /**
     * The one visibility filter. Every list, search, autocomplete, backlink, and count goes through it.
     *
     * @param  Builder<Entity>  $query
     * @return Builder<Entity>
     */
    public function scopeVisibleTo(Builder $query, User $user, CampaignRole $role): Builder
    {
        if ($role->isDm()) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user): void {
            $q->where($q->qualifyColumn('visibility'), Visibility::Players->value)
                ->orWhere($q->qualifyColumn('player_user_id'), $user->id)
                ->orWhere(function (Builder $s) use ($user): void {
                    $s->where($s->qualifyColumn('visibility'), Visibility::Selected->value)
                        ->whereIn($s->qualifyColumn('id'), function (QueryBuilder $sub) use ($user): void {
                            $sub->select('entity_id')->from('entity_viewers')->where('user_id', $user->id);
                        });
                });
        });
    }

    public function isVisibleTo(User $user, CampaignRole $role): bool
    {
        if ($role->isDm() || $this->player_user_id === $user->id) {
            return true;
        }

        return match ($this->visibility) {
            Visibility::Players => true,
            Visibility::Selected => $this->viewers()->whereKey($user->id)->exists(),
            Visibility::Dm => false,
        };
    }

    /**
     * Parent chain from the top down. Stops at 20 levels as a loop guard.
     *
     * @return Collection<int, Entity>
     */
    public function ancestors(): Collection
    {
        $ancestors = new Collection;
        $parentId = $this->parent_id;
        $guard = 0;

        while ($parentId !== null && $guard++ < 20) {
            $parent = Entity::query()->find($parentId);

            if ($parent === null || $ancestors->contains('id', $parent->id)) {
                break;
            }

            $ancestors->prepend($parent);
            $parentId = $parent->parent_id;
        }

        return $ancestors;
    }

    /**
     * Search covers the player-visible prose only. GM notes never enter the index.
     * Quest objectives are not indexed either; they live in their own table.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'name' => $this->name,
            'body' => $this->body,
            'rewards' => $this->rewards,
            'character_class' => $this->character_class,
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')->singleFile()->useDisk(config('media-library.disk_name'));
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')->nonQueued()->fit(Fit::Crop, 320, 320);
    }

    public function imageUrl(string $conversion = ''): ?string
    {
        $url = $this->getFirstMediaUrl('image', $conversion);

        return $url !== '' ? $url : null;
    }

    public function url(): string
    {
        return route('entities.show', [$this->campaign_id, $this->type->slug(), $this->slug]);
    }

    public function editUrl(): string
    {
        return route('entities.edit', [$this->campaign_id, $this->type->slug(), $this->slug]);
    }
}
