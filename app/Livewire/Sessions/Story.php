<?php

namespace App\Livewire\Sessions;

use App\Enums\SessionStatus;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Markdown\MarkdownRenderer;
use App\Markdown\WikiLink\WikiLinkRenderer;
use App\Models\Campaign;
use App\Models\GameSession;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The campaign read as a story: published recaps, oldest first.
 *
 * This is not the sessions index. That page is a schedule, grouped by status and
 * sorted newest first. This one is prose, read from the beginning, which is why the
 * pagination runs ascending: page one is where the campaign started.
 *
 * A GM additionally sees drafts and the sessions that still owe a recap, so the page
 * doubles as their homework list.
 */
class Story extends Component
{
    use InteractsWithCampaign, WithPagination;

    public function mount(Campaign $campaign): void
    {
        $this->enterCampaign($campaign);
        $this->authorize('viewAny', [GameSession::class, $campaign]);
    }

    public function render(MarkdownRenderer $renderer): View
    {
        $role = $this->role();
        $isDm = $role->isDm();
        $wikiLinks = WikiLinkRenderer::for($this->campaign, $this->user(), $role);

        $sessions = GameSession::query()
            ->visibleTo($role)
            ->where(function (Builder $query) use ($isDm): void {
                $query->where(function (Builder $written): void {
                    $written->whereNotNull('recap')->where('recap', '!=', '');
                });

                // A played session with no recap is a gap in the story, and only the
                // person who can fill it needs to see it.
                if ($isDm) {
                    $query->orWhere('status', SessionStatus::Played->value);
                }
            })
            ->when(! $isDm, fn (Builder $query) => $query->whereNotNull('recap_published_at'))
            ->orderBy('number')
            ->paginate(20);

        // Rendered per session through the same visibility rule slice 2 wrote, so an
        // unpublished recap never reaches the view for a player, let alone the HTML.
        $recaps = collect($sessions->items())
            ->filter(fn (GameSession $session) => $session->isRecapVisibleTo($role))
            ->mapWithKeys(fn (GameSession $session) => [
                $session->id => $renderer->render($session->recap, $wikiLinks),
            ]);

        return view('livewire.sessions.story', [
            'role' => $role,
            'timezone' => $this->campaign->timezone,
            'sessions' => $sessions,
            'recaps' => $recaps,
        ])->title('Story');
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
