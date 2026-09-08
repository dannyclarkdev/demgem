<?php

use App\Http\Controllers\Api\V1\CampaignController;
use App\Http\Controllers\Api\V1\EntityController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\SessionController;
use App\Http\Middleware\EnsureCampaignMember;
use Illuminate\Support\Facades\Route;

// Every route needs a key. auth:sanctum also accepts the web session, which is how a
// logged-in browser tab can read the same JSON; nothing here depends on that.
//
// The file is mounted under /api by bootstrap/app.php, with throttle:api on it.
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::get('/me', MeController::class)->name('api.me');
    Route::get('/campaigns', [CampaignController::class, 'index'])->name('api.campaigns.index');

    // The same middleware the pages use. A non-member is a 404, so a key that guesses
    // an id learns nothing, and CurrentCampaign is set for the scopes and policies.
    Route::prefix('/campaigns/{campaign}')
        ->middleware(EnsureCampaignMember::class)
        ->scopeBindings()
        ->group(function () {
            Route::get('/', [CampaignController::class, 'show'])->name('api.campaigns.show');

            // By id, not slug: a slug changes when a name does, and a script holding one
            // would break on a rename. The response carries both.
            Route::get('/entities', [EntityController::class, 'index'])->name('api.entities.index');
            Route::get('/entities/{entityId}', [EntityController::class, 'show'])->name('api.entities.show');
            Route::get('/search', SearchController::class)->name('api.search');

            Route::get('/sessions', [SessionController::class, 'index'])->name('api.sessions.index');
            Route::get('/sessions/{number}', [SessionController::class, 'show'])->whereNumber('number')->name('api.sessions.show');
        });
});
