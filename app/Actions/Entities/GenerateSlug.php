<?php

namespace App\Actions\Entities;

use App\Models\Entity;
use Illuminate\Support\Str;

class GenerateSlug
{
    /** Segments the entity routes already use. */
    private const RESERVED = ['create', 'edit'];

    public function handle(string $campaignId, string $name, ?string $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'entity';
        $slug = $base;
        $suffix = 2;

        while ($this->taken($campaignId, $slug, $ignoreId)) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function taken(string $campaignId, string $slug, ?string $ignoreId): bool
    {
        if (in_array($slug, self::RESERVED, true)) {
            return true;
        }

        return Entity::withoutGlobalScopes()
            ->where('campaign_id', $campaignId)
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }
}
