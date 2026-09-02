<?php

namespace App\Actions\Entities;

use App\Models\Entity;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class SyncTags
{
    /**
     * @param  list<string>  $names
     */
    public function handle(Entity $entity, array $names): void
    {
        $tagIds = collect($names)
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->unique(fn (string $name) => Str::slug($name))
            ->take(30)
            ->map(function (string $name) use ($entity): string {
                return Tag::withoutGlobalScopes()->firstOrCreate(
                    ['campaign_id' => $entity->campaign_id, 'slug' => Str::slug($name)],
                    ['name' => Str::limit($name, 60, '')],
                )->id;
            })
            ->all();

        $entity->tags()->sync($tagIds);

        Tag::withoutGlobalScopes()
            ->where('campaign_id', $entity->campaign_id)
            ->whereDoesntHave('entities', function (Builder $query): void {
                /** @var Builder<Entity> $query */
                $query->withTrashed();
            })
            ->delete();
    }
}
