<?php

namespace App\Livewire\Sessions;

use App\Actions\Sessions\RevealSecret;
use App\Actions\Sessions\UpdateSession;
use App\Enums\PrepRole;
use App\Enums\SessionStatus;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Markdown\MarkdownRenderer;
use App\Markdown\WikiLink\WikiLinkRenderer;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\Scene;
use App\Models\Secret;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The screen the GM keeps open at the table. GM roles only; the route 404s otherwise.
 * Live notes live in a child component so typing does not re-render everything here.
 */
class Run extends Component
{
    use InteractsWithCampaign;

    public GameSession $session;

    public function mount(Campaign $campaign, int $number): void
    {
        $this->enterCampaign($campaign);

        $session = GameSession::query()->where('number', $number)->first();

        abort_if($session === null, 404);

        $this->authorize('update', $session);

        $this->session = $session;
    }

    public function setStatus(string $status, UpdateSession $updateSession): void
    {
        $this->authorize('update', $this->session);

        $updateSession->handle($this->session, $this->user(), [
            'status' => SessionStatus::from($status),
        ]);
    }

    public function revealSecret(string $secretId, RevealSecret $revealSecret): void
    {
        $this->authorize('update', $this->session);

        $revealSecret->handle($this->secret($secretId), $this->session);
    }

    public function unrevealSecret(string $secretId, RevealSecret $revealSecret): void
    {
        $this->authorize('update', $this->session);

        $revealSecret->undo($this->secret($secretId));
    }

    public function render(MarkdownRenderer $renderer): View
    {
        $role = $this->role();
        $wikiLinks = WikiLinkRenderer::for($this->campaign, $this->user(), $role);

        $scenes = $this->session->scenes()->get();

        // Everything still in play tonight: prepped for this session, or carried in.
        $ready = $this->session->secrets()->unrevealed()->get()
            ->concat(Secret::query()->carriedInto($this->session)->orderBy('created_at')->get());

        $revealed = $this->session->revealedSecrets()->get();

        $buckets = [];

        foreach (PrepRole::cases() as $prepRole) {
            $buckets[$prepRole->value] = $this->session->prepped($prepRole)->with('media')->get();
        }

        return view('livewire.sessions.run', [
            'role' => $role,
            'scenes' => $scenes,
            'sceneNotesHtml' => $scenes->mapWithKeys(fn (Scene $scene) => [
                $scene->id => $renderer->render($scene->notes, $wikiLinks),
            ]),
            'strongStartHtml' => $renderer->render($this->session->strong_start, $wikiLinks),
            'dmNotesHtml' => $renderer->render($this->session->dm_notes, $wikiLinks),
            'readySecrets' => $ready,
            'revealedSecrets' => $revealed,
            'secretHtml' => $ready->concat($revealed)->mapWithKeys(fn (Secret $secret) => [
                $secret->id => $renderer->render($secret->body, $wikiLinks),
            ]),
            'prepRoles' => PrepRole::cases(),
            'buckets' => $buckets,
            'party' => Entity::query()->where('is_pc', true)->with('player')->orderBy('name')->get(),
        ])->title('Running '.$this->session->label());
    }

    private function secret(string $secretId): Secret
    {
        return Secret::query()->whereKey($secretId)->firstOrFail();
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
