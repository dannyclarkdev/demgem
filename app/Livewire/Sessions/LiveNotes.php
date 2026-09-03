<?php

namespace App\Livewire\Sessions;

use App\Actions\Sessions\UpdateSession;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Campaign;
use App\Models\GameSession;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Live notes are their own component, not a section of the Run page. Livewire sends
 * only a child's state when the child updates and re-renders only the child, so typing
 * at the table does not re-render the scene list, the secrets, or the prepped entities.
 *
 * The trait matters as much as the split: hydrateInteractsWithCampaign() re-checks
 * membership on every round trip, and it runs per component. Without it a co-GM removed
 * mid-session would keep autosaving.
 */
class LiveNotes extends Component
{
    use InteractsWithCampaign;

    public GameSession $session;

    public string $notes = '';

    public ?string $savedAt = null;

    public function mount(Campaign $campaign, GameSession $session): void
    {
        $this->enterCampaign($campaign);
        $this->authorize('update', $session);

        $this->session = $session;
        $this->notes = $session->live_notes ?? '';
    }

    /**
     * Livewire calls this after the debounce lands the new value on the server.
     */
    public function updatedNotes(string $value): void
    {
        $this->authorize('update', $this->session);

        app(UpdateSession::class)->handle($this->session, $this->user(), [
            'live_notes' => $value === '' ? null : $value,
        ]);

        $this->savedAt = now()->setTimezone($this->campaign->timezone)->format('H:i');
    }

    public function render(): View
    {
        return view('livewire.sessions.live-notes');
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
