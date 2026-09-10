<?php

use App\Http\Controllers\Api\V1\CampaignController;
use App\Http\Controllers\Api\V1\EntityBodyRevisionController;
use App\Http\Controllers\Api\V1\EntityController;
use App\Http\Controllers\Api\V1\EntityTemplateController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\SessionController;
use App\Http\Controllers\Api\V1\StatBlockController;
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
            Route::get('/entity-templates', [EntityTemplateController::class, 'index'])->name('api.entity-templates.index');
            Route::get('/entity-templates/{templateId}', [EntityTemplateController::class, 'show'])->name('api.entity-templates.show');
            Route::get('/entities/{entityId}/body-revisions', [EntityBodyRevisionController::class, 'index'])->name('api.body-revisions.index');
            Route::get('/entities/{entityId}/body-revisions/{revisionId}', [EntityBodyRevisionController::class, 'show'])->name('api.body-revisions.show');
            Route::get('/search', SearchController::class)->name('api.search');

            // The compendium. Read-only on purpose: these rows are shipped reference
            // data with a checksum behind them, so no key writes them. By slug, because
            // a stat block is not renamed the way an entity is.
            Route::get('/compendium/stat-blocks', [StatBlockController::class, 'index'])->name('api.stat-blocks.index');
            Route::get('/compendium/stat-blocks/{slug}', [StatBlockController::class, 'show'])->name('api.stat-blocks.show');

            Route::get('/sessions', [SessionController::class, 'index'])->name('api.sessions.index');
            Route::get('/sessions/{number}', [SessionController::class, 'show'])->whereNumber('number')->name('api.sessions.show');

            // Everything that changes something needs a key made with the box ticked.
            // The API creates and changes; it never deletes. Deleting is a screen with
            // a confirmation, and a key in a script has no confirm button.
            Route::middleware('abilities:write')->group(function () {
                Route::post('/entity-templates', [EntityTemplateController::class, 'store'])->name('api.entity-templates.store');
                Route::patch('/entity-templates/{templateId}', [EntityTemplateController::class, 'update'])->name('api.entity-templates.update');
                Route::post('/entities/{entityId}/body-revisions/{revisionId}/restore', [EntityBodyRevisionController::class, 'restore'])->name('api.body-revisions.restore');
                Route::post('/entities', [EntityController::class, 'store'])->name('api.entities.store');
                Route::patch('/entities/{entityId}', [EntityController::class, 'update'])->name('api.entities.update');
                Route::patch('/sessions/{number}', [SessionController::class, 'update'])->whereNumber('number')->name('api.sessions.update');
                Route::post('/sessions/{number}/publish-recap', [SessionController::class, 'publishRecap'])->whereNumber('number')->name('api.sessions.publish-recap');
            });
        });
});
