<?php

namespace App\Models\Concerns;

use App\Models\Campaign;
use App\Support\CurrentCampaign;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scopes every query to the current campaign when one is set, and fills
 * campaign_id on create. Outside a campaign context (console, tests that
 * query through relationships) no filter applies; policies still guard access.
 */
trait BelongsToCampaign
{
    public static function bootBelongsToCampaign(): void
    {
        static::addGlobalScope('campaign', function (Builder $builder): void {
            $current = app(CurrentCampaign::class);

            if ($current->isSet()) {
                $builder->where($builder->qualifyColumn('campaign_id'), $current->id());
            }
        });

        static::creating(function (Model $model): void {
            if (empty($model->getAttribute('campaign_id')) && ($id = app(CurrentCampaign::class)->id())) {
                $model->setAttribute('campaign_id', $id);
            }
        });
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
