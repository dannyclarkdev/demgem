<?php

namespace App\Models;

use App\Enums\CampaignRole;
use App\Enums\EntityType;
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
 * @property Visibility $visibility
 * @property string|null $parent_id
 * @property bool $is_pc
 * @property int|null $player_user_id
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
 */
#[ObservedBy([EntityObserver::class])]
#[Fillable([
    'campaign_id', 'type', 'name', 'slug', 'body', 'dm_notes', 'visibility',
    'parent_id', 'is_pc', 'player_user_id', 'created_by', 'updated_by',
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
            'is_pc' => 'boolean',
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
     * @return list<string>
     */
    public function mentionableFields(): array
    {
        return ['body', 'dm_notes'];
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
     * Search covers the name and the body only. GM notes never enter the index.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'name' => $this->name,
            'body' => $this->body,
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
