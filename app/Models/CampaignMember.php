<?php

namespace App\Models;

use App\Enums\CampaignRole;
use Database\Factories\CampaignMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Membership row. Doubles as the pivot for Campaign::users() so the role cast applies there too.
 *
 * @property int $id
 * @property string $campaign_id
 * @property int $user_id
 * @property CampaignRole $role
 * @property-read Campaign $campaign
 * @property-read User $user
 */
#[Fillable(['campaign_id', 'user_id', 'role'])]
class CampaignMember extends Pivot
{
    /** @use HasFactory<CampaignMemberFactory> */
    use HasFactory;

    public $incrementing = true;

    protected $table = 'campaign_members';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => CampaignRole::class,
        ];
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOwner(): bool
    {
        return $this->role === CampaignRole::Owner;
    }

    public function isDm(): bool
    {
        return $this->role->isDm();
    }
}
