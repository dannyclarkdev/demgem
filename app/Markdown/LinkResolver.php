<?php

namespace App\Markdown;

use App\Enums\EntityType;
use App\Models\Entity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Resolves link names to entities inside one campaign. Visibility is not its job.
 * A bare name that matches several types picks the type with the lowest priority number.
 */
final class LinkResolver
{
    /** @var array<string, Entity|null> */
    private array $cache = [];

    public function __construct(private readonly string $campaignId) {}

    /**
     * Loads every candidate for the given tokens in one query.
     *
     * @param  iterable<WikiLinkToken>  $tokens
     */
    public function preload(iterable $tokens): void
    {
        $names = [];

        foreach ($tokens as $token) {
            $names[mb_strtolower($token->name)] = true;
        }

        if ($names === []) {
            return;
        }

        $candidates = $this->query()
            ->whereIn(DB::raw('lower(name)'), array_keys($names))
            ->get()
            ->groupBy(fn (Entity $entity) => mb_strtolower($entity->name));

        foreach (array_keys($names) as $lower) {
            /** @var Collection<int, Entity> $group */
            $group = $candidates->get($lower, new Collection);

            $this->cache['|'.$lower] = $group->sortBy(fn (Entity $entity) => $entity->type->priority())->first();

            foreach (EntityType::cases() as $type) {
                $this->cache[$type->value.'|'.$lower] = $group->first(fn (Entity $entity) => $entity->type === $type);
            }
        }
    }

    public function resolve(string $name, ?EntityType $type): ?Entity
    {
        $lower = mb_strtolower(trim($name));
        $key = ($type !== null ? $type->value : '').'|'.$lower;

        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $candidates = $this->query()
            ->whereRaw('lower(name) = ?', [$lower])
            ->when($type !== null, fn ($query) => $query->where('type', $type?->value))
            ->get();

        return $this->cache[$key] = $candidates->sortBy(fn (Entity $entity) => $entity->type->priority())->first();
    }

    /**
     * @return Builder<Entity>
     */
    private function query(): Builder
    {
        return Entity::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('campaign_id', $this->campaignId);
    }
}
