<?php

namespace App\Providers;

use App\Models\Campaign;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\Scene;
use App\Support\CurrentCampaign;
use App\View\Composers\SidebarComposer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CurrentCampaign::class);
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        Relation::enforceMorphMap([
            'campaign' => Campaign::class,
            'entity' => Entity::class,
            'game_session' => GameSession::class,
            'scene' => Scene::class,
        ]);

        View::composer('partials.sidebar', SidebarComposer::class);
    }
}
