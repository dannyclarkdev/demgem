<?php

namespace App\Support\Storage;

use App\Models\Campaign;
use App\Models\Entity;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The three questions about a campaign's files: the ceiling, what is used, and
 * whether a given number of bytes still fits. The use is a sum over the media rows
 * the campaign owns, on every read; a stored total is the second source of truth
 * that drifts, and the media table already carries the sizes.
 */
final class CampaignStorage
{
    public static function limitBytes(): int
    {
        return (int) round(max(0.0, (float) config('campaigns.storage_limit_mb', 0)) * 1_048_576);
    }

    public static function isLimited(): bool
    {
        return self::limitBytes() > 0;
    }

    /**
     * Every original the campaign owns. Conversions are derived from these and are
     * not counted; the ceiling is for what the GM chose to keep.
     */
    public static function usedBytes(Campaign $campaign): int
    {
        $entityIds = Entity::withoutGlobalScopes()
            ->where('campaign_id', $campaign->id)
            ->select('id');

        return (int) Media::query()
            ->where(function ($query) use ($campaign, $entityIds): void {
                $query->where(function ($own) use ($campaign): void {
                    $own->where('model_type', $campaign->getMorphClass())->where('model_id', $campaign->id);
                })->orWhere(function ($theirs) use ($entityIds): void {
                    $theirs->where('model_type', (new Entity)->getMorphClass())->whereIn('model_id', $entityIds);
                });
            })
            ->sum('size');
    }

    public static function freeBytes(Campaign $campaign): int
    {
        return self::isLimited() ? max(0, self::limitBytes() - self::usedBytes($campaign)) : PHP_INT_MAX;
    }

    public static function canStore(Campaign $campaign, int $bytes): bool
    {
        return ! self::isLimited() || self::usedBytes($campaign) + $bytes <= self::limitBytes();
    }

    /**
     * The sentence a refused upload shows, with the numbers in it.
     */
    public static function refusal(Campaign $campaign): string
    {
        return 'That would put the campaign over its '.self::format(self::limitBytes()).'. '
            .self::format(self::freeBytes($campaign)).' is free. Delete a file first, or pick a smaller one.';
    }

    /**
     * "12.4 MB", "500 MB", "3.1 GB". One decimal when it matters, none for a round number.
     */
    public static function format(int $bytes): string
    {
        if ($bytes >= 1_073_741_824) {
            return rtrim(rtrim(number_format($bytes / 1_073_741_824, 1), '0'), '.').' GB';
        }

        if ($bytes >= 1_048_576) {
            return rtrim(rtrim(number_format($bytes / 1_048_576, 1), '0'), '.').' MB';
        }

        if ($bytes >= 1024) {
            return rtrim(rtrim(number_format($bytes / 1024, 1), '0'), '.').' KB';
        }

        return $bytes.' B';
    }
}
