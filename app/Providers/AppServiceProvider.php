<?php

namespace App\Providers;

use App\Models\Campaign;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\Scene;
use App\Models\User;
use App\Support\CurrentCampaign;
use App\View\Composers\SidebarComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
            // Sanctum's tokens morph to their owner, and the map is enforced.
            'user' => User::class,
        ]);

        // Sixty a minute per key holder, or per address before there is one. The
        // web routes are not throttled this way; a Livewire page makes many requests.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        View::composer('partials.sidebar', SidebarComposer::class);
    }
}
