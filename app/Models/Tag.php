<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCampaign;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property string $id
 * @property string $campaign_id
 * @property string $name
 * @property string $slug
 * @property string|null $color
 */
#[Fillable(['campaign_id', 'name', 'slug', 'color'])]
class Tag extends Model
{
    use BelongsToCampaign;

    /** @use HasFactory<TagFactory> */
    use HasFactory, HasUlids;

    /**
     * @return BelongsToMany<Entity, $this>
     */
    public function entities(): BelongsToMany
    {
        return $this->belongsToMany(Entity::class);
    }
}
