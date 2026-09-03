<?php

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/**
 * One presence channel per campaign, and one membership check.
 *
 * Presence rather than private: it is a private channel that also answers "who is
 * at the table", which a group asks out loud every week. The roster it shares is
 * name and role, which the members page already shows to everyone in the campaign.
 *
 * Every event in the app broadcasts here and carries ids only. Each listening
 * Livewire component re-renders under its own viewer's role, so there is no payload
 * to leak and no second channel to keep in step.
 */
Broadcast::channel('campaign.{campaignId}', function (User $user, string $campaignId): array|false {
    $role = Campaign::query()->find($campaignId)?->roleFor($user);

    return $role === null
        ? false
        : ['id' => $user->id, 'name' => $user->name, 'role' => $role->value];
});
