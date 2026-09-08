<?php

use App\Http\Controllers\Api\V1\MeController;
use Illuminate\Support\Facades\Route;

// Every route needs a key. auth:sanctum also accepts the web session, which is how a
// logged-in browser tab can read the same JSON; nothing here depends on that.
//
// The file is mounted under /api by bootstrap/app.php, with throttle:api on it.
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::get('/me', MeController::class)->name('api.me');
});
