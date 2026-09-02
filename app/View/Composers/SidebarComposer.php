<?php

namespace App\View\Composers;

use App\Enums\EntityType;
use App\Models\Entity;
use App\Models\User;
use App\Support\CurrentCampaign;
use Illuminate\Contracts\View\View;

/**
 * Feeds the sidebar. Keeps queries out of the Blade partial.
 */
class SidebarComposer
{
    public function __construct(private readonly CurrentCampaign $current) {}

    public function compose(View $view): void
    {
        /** @var User|null $user */
        $user = auth()->user();
        $campaign = $this->current->get();
        $role = $this->current->role();

        $counts = [];

        if ($user !== null && $campaign !== null && $role !== null) {
            $counts = Entity::query()
                ->visibleTo($user, $role)
                ->toBase()
                ->selectRaw('type, count(*) as aggregate')
                ->groupBy('type')
                ->pluck('aggregate', 'type')
                ->all();
        }

        $view->with([
            'currentCampaign' => $campaign,
            'currentRole' => $role,
            'entityTypes' => EntityType::cases(),
            'entityCounts' => $counts,
            'userCampaigns' => $user?->campaigns()->orderBy('name')->get() ?? collect(),
        ]);
    }
}
