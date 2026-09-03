<?php

namespace App\Livewire\Sessions;

use App\Actions\Sessions\DeleteSession;
use App\Actions\Sessions\UpdateSession;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Markdown\MarkdownRenderer;
use App\Markdown\WikiLink\WikiLinkRenderer;
use App\Models\Campaign;
use App\Models\GameSession;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Show extends Component
{
    use InteractsWithCampaign;

    public GameSession $session;

    public string $recap = '';

    public string $recapPreview = '';

    public function mount(Campaign $campaign, int $number): void
    {
        $this->enterCampaign($campaign);

        $session = GameSession::query()->where('number', $number)->first();

        abort_if($session === null || ! $this->user()->can('view', $session), 404);

        $this->session = $session;

        // Public properties ship in the Livewire snapshot, so the editor's copy of an
        // unpublished recap only exists for GM roles.
        if ($this->user()->can('viewDmFields', $session)) {
            $this->recap = $session->recap ?? '';
        }
    }

    public function saveRecap(UpdateSession $updateSession): void
    {
        $this->authorize('update', $this->session);

        $validated = $this->validate(['recap' => ['nullable', 'string', 'max:100000']]);

        $updateSession->handle($this->session, $this->user(), [
            'recap' => filled($validated['recap']) ? $validated['recap'] : null,
        ]);

        session()->flash('status', 'Recap saved.');
    }

    /**
     * The recap is published on purpose, never as a side effect of marking a session
     * played. A GM sets the status at the table and writes the recap the next day.
     */
    public function publishRecap(UpdateSession $updateSession): void
    {
        $this->authorize('publishRecap', $this->session);

        if (! filled($this->recap)) {
            $this->addError('recap', 'Write the recap before you publish it.');

            return;
        }

        $updateSession->handle($this->session, $this->user(), [
            'recap' => $this->recap,
            'recap_published_at' => now(),
        ]);

        session()->flash('status', 'The party can read the recap now.');
    }

    public function unpublishRecap(UpdateSession $updateSession): void
    {
        $this->authorize('publishRecap', $this->session);

        $updateSession->handle($this->session, $this->user(), ['recap_published_at' => null]);

        session()->flash('status', 'Recap hidden from the party again.');
    }

    /**
     * Ten minutes of retyping, saved. Only offered while the recap is empty.
     */
    public function startRecapFromLiveNotes(UpdateSession $updateSession): void
    {
        $this->authorize('update', $this->session);

        if (filled($this->session->recap) || ! filled($this->session->live_notes)) {
            return;
        }

        $this->recap = (string) $this->session->live_notes;

        $updateSession->handle($this->session, $this->user(), ['recap' => $this->recap]);
    }

    public function previewRecap(MarkdownRenderer $renderer): void
    {
        $this->authorize('update', $this->session);

        $this->recapPreview = $renderer->render($this->recap, WikiLinkRenderer::for($this->campaign, $this->user(), $this->role()));
    }

    public function delete(DeleteSession $deleteSession): void
    {
        $this->authorize('delete', $this->session);

        $label = $this->session->label();

        $deleteSession->handle($this->session);

        session()->flash('status', "{$label} was deleted. Its secrets went back to the pool.");

        $this->redirectRoute('sessions.index', $this->campaign);
    }

    public function render(MarkdownRenderer $renderer): View
    {
        $role = $this->role();

        // The recap is the only session field a player may read, and only once published.
        $recapHtml = $this->session->isRecapVisibleTo($role)
            ? $renderer->render($this->session->recap, WikiLinkRenderer::for($this->campaign, $this->user(), $role))
            : null;

        return view('livewire.sessions.show', [
            'role' => $role,
            'timezone' => $this->campaign->timezone,
            'recapHtml' => $recapHtml,
            'canEdit' => $role->isDm(),
            'autocompleteUrl' => route('entities.autocomplete', $this->campaign),
        ])->title($this->session->label());
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
