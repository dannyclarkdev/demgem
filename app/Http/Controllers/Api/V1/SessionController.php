<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\SessionResource;
use App\Models\Campaign;
use App\Models\GameSession;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sessions\Index and Sessions\Show, in JSON. Every query goes through
 * GameSession::visibleTo(); the DM-only fields are the resource's decision.
 */
class SessionController extends ApiController
{
    public const PER_PAGE = 50;

    public function index(Campaign $campaign): AnonymousResourceCollection
    {
        $sessions = GameSession::query()
            ->visibleTo($this->role())
            ->orderBy('number')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return SessionResource::collection($sessions);
    }

    public function show(Campaign $campaign, string $number): SessionResource
    {
        $role = $this->role();

        $session = GameSession::query()
            ->visibleTo($role)
            ->where('number', (int) $number)
            ->first();

        abort_if($session === null, 404);

        // The prep, for the roles that prepared it. A player's resource never asks.
        if ($role->isDm()) {
            $session->load(['scenes', 'secrets', 'entities']);
        }

        return new SessionResource($session);
    }
}
