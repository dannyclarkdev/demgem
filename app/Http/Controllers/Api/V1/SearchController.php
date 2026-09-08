<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\EntityResource;
use App\Models\Campaign;
use App\Models\Entity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Livewire\Search without the view: Scout, then the visibility scope on the rows it
 * found, so GM notes never enter the index and a hidden page never leaves it.
 */
class SearchController extends ApiController
{
    public const LIMIT = 50;

    public function __invoke(Request $request, Campaign $campaign): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'max:120'],
        ]);

        $user = $this->viewer();
        $role = $this->role();

        $results = Entity::search(trim($validated['q']))
            ->where('campaign_id', $campaign->id)
            ->query(function (Builder $query) use ($user, $role): void {
                /** @var Builder<Entity> $query */
                $query->visibleTo($user, $role)->with(['tags', 'media', 'objectives'])->orderBy('name');
            })
            ->take(self::LIMIT)
            ->get();

        return EntityResource::collection($results);
    }
}
