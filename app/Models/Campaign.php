<?php

namespace App\Models;

use App\Enums\CampaignRole;
use App\Enums\Ruleset;
use Database\Factories\CampaignFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property Ruleset $ruleset
 * @property string $timezone
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CampaignMember|null $owner
 */
#[Fillable(['name', 'description', 'ruleset', 'timezone', 'created_by'])]
class Campaign extends Model implements HasMedia
{
    /** @use HasFactory<CampaignFactory> */
    use HasFactory, HasUlids, InteractsWithMedia, SoftDeletes;

    /** @var array<int, CampaignMember|null> */
    private array $memberCache = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ruleset' => Ruleset::class,
        ];
    }

    /**
     * @return HasMany<CampaignMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(CampaignMember::class);
    }

    /**
     * @return BelongsToMany<User, $this, CampaignMember>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'campaign_members')
            ->using(CampaignMember::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return HasOne<CampaignMember, $this>
     */
    public function owner(): HasOne
    {
        return $this->hasOne(CampaignMember::class)->where('role', CampaignRole::Owner);
    }

    /**
     * @return HasMany<CampaignInvite, $this>
     */
    public function invites(): HasMany
    {
        return $this->hasMany(CampaignInvite::class);
    }

    /**
     * @return HasMany<Entity, $this>
     */
    public function entities(): HasMany
    {
        return $this->hasMany(Entity::class);
    }

    /**
     * @return HasMany<Tag, $this>
     */
    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    /**
     * @return HasMany<GameSession, $this>
     */
    public function gameSessions(): HasMany
    {
        return $this->hasMany(GameSession::class);
    }

    /**
     * @return HasMany<Secret, $this>
     */
    public function secrets(): HasMany
    {
        return $this->hasMany(Secret::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')->singleFile()->useDisk(config('media-library.disk_name'));
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('card')->nonQueued()->fit(Fit::Crop, 960, 400);
    }

    public function coverUrl(string $conversion = ''): ?string
    {
        $url = $this->getFirstMediaUrl('cover', $conversion);

        return $url !== '' ? $url : null;
    }

    public function memberFor(?User $user): ?CampaignMember
    {
        if ($user === null) {
            return null;
        }

        if (! array_key_exists($user->id, $this->memberCache)) {
            $this->memberCache[$user->id] = $this->members()->where('user_id', $user->id)->first();
        }

        return $this->memberCache[$user->id];
    }

    public function roleFor(?User $user): ?CampaignRole
    {
        return $this->memberFor($user)?->role;
    }

    public function forgetMemberCache(): void
    {
        $this->memberCache = [];
    }
}
