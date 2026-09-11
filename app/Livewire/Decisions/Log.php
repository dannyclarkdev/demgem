<?php

namespace App\Livewire\Decisions;

use App\Actions\Decisions\DeleteDecision;
use App\Actions\Decisions\RecordDecision;
use App\Actions\Decisions\SetDecisionVisibility;
use App\Actions\Decisions\UpdateDecision;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Markdown\MarkdownRenderer;
use App\Markdown\WikiLink\WikiLinkRenderer;
use App\Models\Campaign;
use App\Models\Decision;
use App\Models\GameSession;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * The decision log, drawn once for two audiences and in two places.
 *
 * Routed at /decisions it is the whole campaign, oldest first. Nested on the session
 * page and the run screen with a session passed in, it is that session's choices
 * and the form records under it. Same component, one more where clause, which is
 * what Clocks\Panel does with an entity.
 *
 * A GM gets the form and the controls; a player gets the rows the GM revealed. The
 * role decides the query, never the template. The session link on a row is loaded
 * through GameSession::visibleTo() separately, so a revealed decision from a GM-only
 * session keeps its words and loses the link.
 *
 * Nested and it writes, so it re-checks membership itself on every round trip.
 */
class Log extends Component
{
    use InteractsWithCampaign;

    public ?GameSession $session = null;

    public string $newChoice = '';

    public string $newConsequence = '';

    public string $newSessionId = '';

    public ?string $editingId = null;

    public string $editingChoice = '';

    public string $editingConsequence = '';

    public string $editingSessionId = '';

    public function mount(Campaign $campaign, ?GameSession $session = null): void
    {
        $this->enterCampaign($campaign);
        $this->authorize('viewAny', [Decision::class, $campaign]);

        $this->session = $session;
        $this->newSessionId = $session?->id ?? '';
    }

    public function record(RecordDecision $recordDecision): void
    {
        $this->authorize('create', [Decision::class, $this->campaign]);

        $validated = $this->validate([
            'newChoice' => ['required', 'string', 'max:'.Decision::MAX_LENGTH],
            'newConsequence' => ['nullable', 'string', 'max:'.Decision::MAX_LENGTH],
            'newSessionId' => $this->sessionRule(),
        ]);

        $recordDecision->handle(
            $this->campaign,
            $this->user(),
            $validated['newChoice'],
            $validated['newConsequence'] ?? null,
            $this->sessionFor($validated['newSessionId'] ?? ''),
        );

        $this->reset('newChoice', 'newConsequence');
        $this->newSessionId = $this->session?->id ?? '';
    }

    public function edit(string $decisionId): void
    {
        $decision = $this->decision($decisionId);

        $this->authorize('update', $decision);

        $this->editingId = $decision->id;
        $this->editingChoice = $decision->choice;
        $this->editingConsequence = $decision->consequence ?? '';
        $this->editingSessionId = $decision->game_session_id ?? '';
    }

    public function cancelEdit(): void
    {
        $this->reset('editingId', 'editingChoice', 'editingConsequence', 'editingSessionId');
    }

    public function save(UpdateDecision $updateDecision): void
    {
        $decision = $this->decision((string) $this->editingId);

        $this->authorize('update', $decision);

        $validated = $this->validate([
            'editingChoice' => ['required', 'string', 'max:'.Decision::MAX_LENGTH],
            'editingConsequence' => ['nullable', 'string', 'max:'.Decision::MAX_LENGTH],
            'editingSessionId' => $this->sessionRule(),
        ]);

        $updateDecision->handle(
            $decision,
            $validated['editingChoice'],
            $validated['editingConsequence'] ?? null,
            $this->sessionFor($validated['editingSessionId'] ?? ''),
        );

        $this->cancelEdit();
    }

    /**
     * The eye. Off for everything a GM records, so a choice the party has not been
     * told was noted is one they cannot read.
     */
    public function toggleVisibility(string $decisionId, SetDecisionVisibility $setDecisionVisibility): void
    {
        $decision = $this->decision($decisionId);

        $this->authorize('update', $decision);

        $setDecisionVisibility->toggle($decision);
    }

    public function delete(string $decisionId, DeleteDecision $deleteDecision): void
    {
        $decision = $this->decision($decisionId);

        $this->authorize('delete', $decision);

        $deleteDecision->handle($decision);

        if ($this->editingId === $decisionId) {
            $this->cancelEdit();
        }
    }

    public function render(MarkdownRenderer $renderer): View
    {
        $role = $this->role();
        $wikiLinks = WikiLinkRenderer::for($this->campaign, $this->user(), $role);
        $decisions = $this->decisions();

        // Rendered here, per row, so the Blade prints HTML it was handed and never
        // reads a field the query did not load.
        $html = $decisions->mapWithKeys(fn (Decision $decision) => [
            $decision->id => [
                'choice' => $renderer->render($decision->choice, $wikiLinks),
                'consequence' => $decision->hasConsequence() ? $renderer->render($decision->consequence, $wikiLinks) : '',
            ],
        ]);

        return view('livewire.decisions.log', [
            'decisions' => $decisions,
            'html' => $html,
            'sessionLinks' => $this->sessionLinks($decisions),
            'canManage' => $role->isDm(),
            'scoped' => $this->session !== null,
            'sessionOptions' => $role->isDm() && $this->session === null
                ? GameSession::query()->orderByDesc('number')->get(['id', 'number', 'title'])
                : collect(),
        ]);
    }

    /**
     * @return Collection<int, Decision>
     */
    private function decisions(): Collection
    {
        return Decision::query()
            ->visibleTo($this->role())
            ->when($this->session !== null, fn (Builder $query) => $query->madeIn($this->session))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * The session each row was made in, keyed by id, and only the ones this viewer
     * may see. One query for the whole log, with the filter in it, so a GM-only
     * session's number never reaches a player's page beside a revealed choice.
     *
     * @param  Collection<int, Decision>  $decisions
     * @return Collection<string, GameSession>
     */
    private function sessionLinks(Collection $decisions): Collection
    {
        /** @var Collection<int, string> $ids */
        $ids = $decisions->pluck('game_session_id')->filter()->unique()->values();

        if ($ids->isEmpty()) {
            /** @var Collection<string, GameSession> $empty */
            $empty = new Collection;

            return $empty;
        }

        return GameSession::query()
            ->visibleTo($this->role())
            ->whereKey($ids->all())
            ->get()
            ->keyBy('id');
    }

    /**
     * @return list<mixed>
     */
    private function sessionRule(): array
    {
        return [
            'nullable',
            Rule::exists('game_sessions', 'id')
                ->where('campaign_id', $this->campaign->id)
                ->whereNull('deleted_at'),
        ];
    }

    private function sessionFor(string $id): ?GameSession
    {
        if ($id === '') {
            return null;
        }

        return GameSession::query()->whereKey($id)->first();
    }

    private function decision(string $decisionId): Decision
    {
        /** @var Decision $decision */
        $decision = Decision::query()->whereKey($decisionId)->firstOrFail();

        return $decision;
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
