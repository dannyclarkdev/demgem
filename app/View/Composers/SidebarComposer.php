<?php

namespace App\View\Composers;

use App\Enums\EntityType;
use App\Models\Clock;
use App\Models\Encounter;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\RandomTable;
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
        $sessionCount = 0;
        $encounterCount = 0;
        $tableCount = 0;
        $clockCount = 0;

        if ($user !== null && $campaign !== null && $role !== null) {
            $counts = Entity::query()
                ->visibleTo($user, $role)
                ->toBase()
                ->selectRaw('type, count(*) as aggregate')
                ->groupBy('type')
                ->pluck('aggregate', 'type')
                ->all();

            $sessionCount = GameSession::query()->visibleTo($role)->count();

            // GM-only tools, so the queries are skipped rather than filtered afterwards.
            if ($role->isDm()) {
                $encounterCount = Encounter::query()->count();
                $tableCount = RandomTable::query()->count();
                $clockCount = Clock::query()->count();
            }
        }

        $view->with([
            'currentCampaign' => $campaign,
            'currentRole' => $role,
            'entityTypes' => EntityType::cases(),
            'entityCounts' => $counts,
            'sessionCount' => $sessionCount,
            'encounterCount' => $encounterCount,
            'tableCount' => $tableCount,
            'clockCount' => $clockCount,
            'userCampaigns' => $user?->campaigns()->orderBy('name')->get() ?? collect(),
        ]);
    }
}
