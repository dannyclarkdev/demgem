<?php

use App\Enums\EntityType;
use App\Http\Controllers\AutocompleteController;
use App\Http\Controllers\CampaignExportController;
use App\Http\Controllers\InviteController;
use App\Http\Middleware\EnsureCampaignMember;
use App\Livewire\Campaigns\Create as CampaignsCreate;
use App\Livewire\Campaigns\Index as CampaignsIndex;
use App\Livewire\Campaigns\Members as CampaignsMembers;
use App\Livewire\Campaigns\Settings as CampaignsSettings;
use App\Livewire\Campaigns\Show as CampaignsShow;
use App\Livewire\Clocks\Index as ClocksIndex;
use App\Livewire\Encounters\Index as EncountersIndex;
use App\Livewire\Encounters\Show as EncountersShow;
use App\Livewire\Entities\Form as EntitiesForm;
use App\Livewire\Entities\Index as EntitiesIndex;
use App\Livewire\Entities\Show as EntitiesShow;
use App\Livewire\Profile\Edit as ProfileEdit;
use App\Livewire\RandomTables\Index as TablesIndex;
use App\Livewire\RandomTables\Show as TablesShow;
use App\Livewire\Search;
use App\Livewire\Sessions\Form as SessionsForm;
use App\Livewire\Sessions\Index as SessionsIndex;
use App\Livewire\Sessions\Prep as SessionsPrep;
use App\Livewire\Sessions\Run as SessionsRun;
use App\Livewire\Sessions\Show as SessionsShow;
use App\Livewire\Sessions\Story as SessionsStory;
use App\Livewire\Table\Show as TableShow;
use Illuminate\Support\Facades\Route;

Route::pattern('type', implode('|', EntityType::slugs()));

Route::get('/', fn () => redirect()->route(auth()->check() ? 'campaigns.index' : 'login'))->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/campaigns', CampaignsIndex::class)->name('campaigns.index');
    Route::get('/campaigns/create', CampaignsCreate::class)->name('campaigns.create');
    Route::get('/profile', ProfileEdit::class)->name('profile.edit');

    Route::get('/invites/{token}', [InviteController::class, 'show'])->name('invites.show');
    Route::post('/invites/{token}', [InviteController::class, 'accept'])
        ->middleware('throttle:20,1')
        ->name('invites.accept');

    Route::prefix('/campaigns/{campaign}')
        ->middleware(EnsureCampaignMember::class)
        ->scopeBindings()
        ->group(function () {
            Route::get('/', CampaignsShow::class)->name('campaigns.show');
            Route::get('/settings', CampaignsSettings::class)->name('campaigns.settings');
            Route::get('/members', CampaignsMembers::class)->name('campaigns.members');

            // Your data leaves with you. Streamed, so a campaign of any size costs the
            // same memory and starts downloading at once. GM roles only.
            Route::get('/export', CampaignExportController::class)
                ->middleware('throttle:5,1')
                ->name('campaigns.export');
            Route::get('/autocomplete', AutocompleteController::class)->name('entities.autocomplete');
            Route::get('/search', Search::class)->name('search');

            Route::get('/sessions', SessionsIndex::class)->name('sessions.index');
            Route::get('/sessions/create', SessionsForm::class)->name('sessions.create');
            Route::get('/sessions/{number}', SessionsShow::class)->whereNumber('number')->name('sessions.show');
            Route::get('/sessions/{number}/edit', SessionsForm::class)->whereNumber('number')->name('sessions.edit');
            Route::get('/sessions/{number}/prep', SessionsPrep::class)->whereNumber('number')->name('sessions.prep');
            Route::get('/sessions/{number}/run', SessionsRun::class)->whereNumber('number')->name('sessions.run');

            // The player's live screen: the fight, and the shared dice log in P3. Open to
            // every role, because it is the one page a player keeps open during a game
            // and a co-GM watches the same thing from a second device.
            //
            // Singular, and it cannot collide with /tables: {type} is patterned to the
            // six entity slugs, so no catch-all below can claim it either.
            Route::get('/table', TableShow::class)->name('table');

            // The recap archive, read oldest first. A page of prose, not a schedule,
            // which is why it is not a tab on the sessions index.
            Route::get('/story', SessionsStory::class)->name('story');

            // Keyed by ULID on purpose. An encounter is a GM tool, not lore: nothing links
            // to it and no player opens it, so it is not worth a slug or the rename trade.
            //
            // The parameter is {encounterId}, not {encounter}: a parameter named after a
            // model is claimed by Livewire's implicit route binding, which resolves it
            // before mount() and before enterCampaign(). Sessions and entities resolve in
            // mount() for the same reason, and their keys just happen not to be model names.
            Route::get('/encounters', EncountersIndex::class)->name('encounters.index');
            Route::get('/encounters/{encounterId}', EncountersShow::class)->name('encounters.show');

            // Clocks a GM turns. The segment is not an entity slug, and this sits above
            // the {type} routes, so nothing below can claim it.
            Route::get('/clocks', ClocksIndex::class)->name('clocks.index');

            Route::get('/tables', TablesIndex::class)->name('tables.index');
            Route::get('/tables/{tableId}', TablesShow::class)->name('tables.show');

            Route::get('/{type}/create', EntitiesForm::class)->name('entities.create');
            Route::get('/{type}', EntitiesIndex::class)->name('entities.index');
            Route::get('/{type}/{slug}', EntitiesShow::class)->name('entities.show');
            Route::get('/{type}/{slug}/edit', EntitiesForm::class)->name('entities.edit');
        });
});
