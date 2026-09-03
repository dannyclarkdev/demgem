<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCampaign;
use Database\Factories\RandomTableFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A weighted table the GM rolls at the table. GM-only in this slice.
 *
 * @property string $id
 * @property string $campaign_id
 * @property string $name
 * @property string|null $description
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Campaign $campaign
 * @property-read Collection<int, RandomTableEntry> $entries
 */
#[Fillable(['campaign_id', 'name', 'description', 'created_by'])]
class RandomTable extends Model
{
    use BelongsToCampaign;

    /** @use HasFactory<RandomTableFactory> */
    use HasFactory, HasUlids;

    /**
     * @return HasMany<RandomTableEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(RandomTableEntry::class)->orderBy('position');
    }

    /**
     * Entries that nest this table, so a delete confirmation can say what breaks.
     *
     * @return HasMany<RandomTableEntry, $this>
     */
    public function nestedBy(): HasMany
    {
        return $this->hasMany(RandomTableEntry::class, 'nested_table_id');
    }

    /**
     * The die this table is rolled on: the sum of its weights.
     */
    public function totalWeight(): int
    {
        return (int) ($this->relationLoaded('entries')
            ? $this->entries->sum('weight')
            : $this->entries()->sum('weight'));
    }

    public function dieLabel(): string
    {
        $total = $this->totalWeight();

        return $total > 0 ? 'd'.$total : 'empty';
    }

    /**
     * The inclusive range each entry occupies on that die, keyed by entry id. This is
     * what makes transcribing a published table line up as the GM types.
     *
     * @return Collection<string, array{from: int, to: int}>
     */
    public function ranges(): Collection
    {
        $cursor = 1;
        /** @var Collection<string, array{from: int, to: int}> $ranges */
        $ranges = new Collection;

        foreach ($this->entries()->get() as $entry) {
            $width = max(1, $entry->weight);
            $ranges->put($entry->id, ['from' => $cursor, 'to' => $cursor + $width - 1]);
            $cursor += $width;
        }

        return $ranges;
    }

    public function url(): string
    {
        return route('tables.show', [$this->campaign_id, $this->id]);
    }
}
