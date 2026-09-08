<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Sessions\PublishRecap;
use App\Actions\Sessions\UpdateSession;
use App\Enums\SessionStatus;
use App\Http\Resources\Api\V1\SessionResource;
use App\Models\Campaign;
use App\Models\GameSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Sessions\Index and Sessions\Show, in JSON. Every query goes through
 * GameSession::visibleTo(); the DM-only fields are the resource's decision; every
 * write is the action the page calls, behind the same policy.
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
        $session = $this->find($number);

        return $this->present($session);
    }

    /**
     * The notes a GM writes before, during, and after. Never the publish stamp: that
     * is its own verb, below, because it has a side effect.
     */
    public function update(Request $request, Campaign $campaign, string $number, UpdateSession $updateSession): SessionResource
    {
        $session = $this->find($number);

        Gate::authorize('update', $session);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:120'],
            'status' => ['string', Rule::enum(SessionStatus::class)],
            'strong_start' => ['nullable', 'string', 'max:100000'],
            'live_notes' => ['nullable', 'string', 'max:100000'],
            'recap' => ['nullable', 'string', 'max:100000'],
            'dm_notes' => ['nullable', 'string', 'max:100000'],
        ]);

        $data = [];

        foreach (['title', 'strong_start', 'live_notes', 'recap', 'dm_notes'] as $key) {
            if (array_key_exists($key, $validated)) {
                $data[$key] = filled($validated[$key]) ? trim((string) $validated[$key]) : null;
            }
        }

        if (array_key_exists('status', $validated)) {
            $data['status'] = SessionStatus::from($validated['status']);
        }

        $updateSession->handle($session, $this->viewer(), $data);

        return $this->present($session);
    }

    /**
     * Publish what is there, or save a recap and publish it in one call. Publishing a
     * published recap changes nothing and posts nothing.
     */
    public function publishRecap(Request $request, Campaign $campaign, string $number, PublishRecap $publishRecap): SessionResource
    {
        $session = $this->find($number);

        Gate::authorize('publishRecap', $session);

        $validated = $request->validate([
            'recap' => ['nullable', 'string', 'max:100000'],
        ]);

        $publishRecap->handle($session, $this->viewer(), filled($validated['recap'] ?? null) ? trim((string) $validated['recap']) : null);

        return $this->present($session);
    }

    private function find(string $number): GameSession
    {
        $session = GameSession::query()
            ->visibleTo($this->role())
            ->where('number', (int) $number)
            ->first();

        abort_if($session === null, 404);

        return $session;
    }

    /**
     * The prep, for the roles that prepared it. A player's resource never asks.
     */
    private function present(GameSession $session): SessionResource
    {
        if ($this->role()->isDm()) {
            $session->load(['scenes', 'secrets', 'entities']);
        }

        return new SessionResource($session);
    }
}
