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
 * @property int $session_length_minutes
 * @property int|null $reminder_lead_hours
 * @property string $currency
 * @property string|null $discord_webhook_url Decrypted on read. Never exported.
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CampaignMember|null $owner
 * @property-read Calendar|null $calendar
 */
#[Fillable(['name', 'description', 'ruleset', 'timezone', 'session_length_minutes', 'reminder_lead_hours', 'currency', 'discord_webhook_url', 'created_by'])]
class Campaign extends Model implements HasMedia
{
    /** @use HasFactory<CampaignFactory> */
    use HasFactory, HasUlids, InteractsWithMedia, SoftDeletes;

    public const MAX_CURRENCY_LENGTH = 12;

    /**
     * The column has the same default; this one is for an instance that has not been
     * read back from the row yet, such as one a factory just made.
     *
     * @var array<string, mixed>
     */
    protected $attributes = ['currency' => 'gp'];

    /** @var array<int, CampaignMember|null> */
    private array $memberCache = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ruleset' => Ruleset::class,
            'session_length_minutes' => 'integer',
            'reminder_lead_hours' => 'integer',
            'discord_webhook_url' => 'encrypted',
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

    /**
     * @return HasMany<Clock, $this>
     */
    public function clocks(): HasMany
    {
        return $this->hasMany(Clock::class)->orderBy('position');
    }

    /**
     * The party's purse and pack, oldest first.
     *
     * @return HasMany<LedgerEntry, $this>
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class)->orderBy('created_at');
    }

    /**
     * The choices the party made, oldest first.
     *
     * @return HasMany<Decision, $this>
     */
    public function decisions(): HasMany
    {
        return $this->hasMany(Decision::class)->orderBy('created_at');
    }

    /**
     * The world's calendar, or null while the GM has not defined one.
     *
     * @return HasOne<Calendar, $this>
     */
    public function calendar(): HasOne
    {
        return $this->hasOne(Calendar::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')->singleFile()->useDisk(config('media-library.disk_name'));
    }

    /**
     * The collection is named even though there is only one of them.
     *
     * An unscoped conversion runs on every collection a model has, and this is exactly
     * the shape that bit Entity in slice 7 the moment a second collection appeared.
     * Naming it now costs one call and makes the rule in .ai/rules/models.md true
     * everywhere rather than in one model.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('card')
            ->performOnCollections('cover')
            ->nonQueued()
            ->fit(Fit::Crop, 960, 400);
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

    /**
     * "a day before", for the settings screen and the members page. Null means off.
     *
     * @return array<int|string, string> hours => label, with '' for off. PHP turns the
     *                                   numeric keys into ints, so both key types appear.
     */
    public static function reminderLeadOptions(): array
    {
        return [
            '' => 'Off',
            '24' => 'A day before',
            '48' => 'Two days before',
            '168' => 'A week before',
        ];
    }

    public function reminderLeadLabel(): string
    {
        return strtolower(self::reminderLeadOptions()[(string) ($this->reminder_lead_hours ?? '')] ?? 'off');
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
