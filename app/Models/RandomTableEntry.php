<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCampaign;
use Database\Factories\RandomTableEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One possible result. weight is how many numbers on the die it occupies.
 *
 * nested_table_id makes the entry roll another table as well. Setting it to the
 * entry's own table is rejected when it is written, which is the mistake a GM
 * actually makes; a longer loop is caught by the visited set at roll time.
 *
 * @property string $id
 * @property string $campaign_id
 * @property string $random_table_id
 * @property int $position
 * @property int $weight
 * @property string $body
 * @property string|null $nested_table_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Campaign $campaign
 * @property-read RandomTable $randomTable
 * @property-read RandomTable|null $nestedTable
 */
#[Fillable(['campaign_id', 'random_table_id', 'position', 'weight', 'body', 'nested_table_id'])]
class RandomTableEntry extends Model
{
    use BelongsToCampaign;

    /** @use HasFactory<RandomTableEntryFactory> */
    use HasFactory, HasUlids;

    public const MAX_WEIGHT = 1000;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'weight' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<RandomTable, $this>
     */
    public function randomTable(): BelongsTo
    {
        return $this->belongsTo(RandomTable::class);
    }

    /**
     * @return BelongsTo<RandomTable, $this>
     */
    public function nestedTable(): BelongsTo
    {
        return $this->belongsTo(RandomTable::class, 'nested_table_id');
    }
}
