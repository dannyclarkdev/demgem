<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Entity;
use App\Models\User;
use App\Support\CurrentCampaign;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Feeds the [[ and @ editor autocomplete. Filtered by what the viewer may see.
 */
class AutocompleteController extends Controller
{
    public function __invoke(Request $request, Campaign $campaign, CurrentCampaign $current): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $role = $current->role();

        abort_if($role === null, 404);

        $query = mb_strtolower(trim((string) $request->query('q', '')));

        $entities = Entity::query()
            ->visibleTo($user, $role)
            ->when($query !== '', fn (Builder $q) => $q->whereRaw('lower(name) like ?', ['%'.$query.'%']))
            ->orderByRaw('case when lower(name) like ? then 0 else 1 end', [$query.'%'])
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'campaign_id', 'type', 'name', 'slug']);

        $names = $entities->map(fn (Entity $entity) => mb_strtolower($entity->name))->unique()->all();

        $collisions = Entity::query()
            ->whereIn(DB::raw('lower(name)'), $names)
            ->toBase()
            ->selectRaw('lower(name) as lower_name, count(*) as aggregate')
            ->groupBy('lower_name')
            ->pluck('aggregate', 'lower_name');

        return response()->json($entities->map(fn (Entity $entity) => [
            'name' => $entity->name,
            'type' => $entity->type->value,
            'typeLabel' => $entity->type->label(),
            'slug' => $entity->slug,
            'url' => $entity->url(),
            'needsPrefix' => ($collisions[mb_strtolower($entity->name)] ?? 1) > 1,
        ])->values());
    }
}
