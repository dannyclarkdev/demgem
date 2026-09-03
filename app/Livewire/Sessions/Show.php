<?php

namespace App\Livewire\Sessions;

use App\Actions\Sessions\DeleteSession;
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

    public function mount(Campaign $campaign, int $number): void
    {
        $this->enterCampaign($campaign);

        $session = GameSession::query()->where('number', $number)->first();

        abort_if($session === null || ! $this->user()->can('view', $session), 404);

        $this->session = $session;
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
        ])->title($this->session->label());
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
