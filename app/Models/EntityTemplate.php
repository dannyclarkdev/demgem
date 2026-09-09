<?php

namespace App\Models;

use App\Enums\EntityType;
use App\Models\Concerns\BelongsToCampaign;
use Database\Factories\EntityTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $campaign_id
 * @property EntityType $type
 * @property string $name
 * @property string|null $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Campaign $campaign
 */
#[Fillable(['campaign_id', 'type', 'name', 'body'])]
class EntityTemplate extends Model
{
    use BelongsToCampaign;

    /** @use HasFactory<EntityTemplateFactory> */
    use HasFactory, HasUlids;

    protected function casts(): array
    {
        return ['type' => EntityType::class];
    }
}
