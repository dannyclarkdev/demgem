<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property string|null $calendar_token
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'calendar_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @return BelongsToMany<Campaign, $this, CampaignMember>
     */
    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(Campaign::class, 'campaign_members')
            ->using(CampaignMember::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return HasMany<CampaignMember, $this>
     */
    public function campaignMemberships(): HasMany
    {
        return $this->hasMany(CampaignMember::class);
    }

    /**
     * The calendar feed's credential, minted the first time somebody asks for the
     * link. Forty characters from Str::random, the same footing an invite link has.
     */
    public function calendarToken(): string
    {
        // Read from the row rather than the instance: the authenticated user can be an
        // instance built before this column was part of it, and strict mode is on.
        $token = $this->newQuery()->whereKey($this->getKey())->value('calendar_token');

        if (is_string($token) && $token !== '') {
            $this->setAttribute('calendar_token', $token);

            return $token;
        }

        return $this->resetCalendarToken();
    }

    /**
     * A new token, and the old URL stops working the moment this returns.
     */
    public function resetCalendarToken(): string
    {
        $this->forceFill(['calendar_token' => Str::random(40)])->save();

        return $this->calendar_token;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
