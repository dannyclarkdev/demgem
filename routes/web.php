<?php

use App\Enums\EntityType;
use App\Http\Controllers\AutocompleteController;
use App\Http\Controllers\InviteController;
use App\Http\Middleware\EnsureCampaignMember;
use App\Livewire\Campaigns\Create as CampaignsCreate;
use App\Livewire\Campaigns\Index as CampaignsIndex;
use App\Livewire\Campaigns\Members as CampaignsMembers;
use App\Livewire\Campaigns\Settings as CampaignsSettings;
use App\Livewire\Campaigns\Show as CampaignsShow;
use App\Livewire\Entities\Form as EntitiesForm;
use App\Livewire\Entities\Index as EntitiesIndex;
use App\Livewire\Entities\Show as EntitiesShow;
use App\Livewire\Profile\Edit as ProfileEdit;
use App\Livewire\Search;
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
            Route::get('/autocomplete', AutocompleteController::class)->name('entities.autocomplete');
            Route::get('/search', Search::class)->name('search');

            Route::get('/{type}/create', EntitiesForm::class)->name('entities.create');
            Route::get('/{type}', EntitiesIndex::class)->name('entities.index');
            Route::get('/{type}/{slug}', EntitiesShow::class)->name('entities.show');
            Route::get('/{type}/{slug}/edit', EntitiesForm::class)->name('entities.edit');
        });
});
