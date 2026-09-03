<?php

namespace App\Livewire\Sessions;

use App\Actions\Sessions\CreateSession;
use App\Actions\Sessions\UpdateSession;
use App\Enums\SessionStatus;
use App\Enums\Visibility;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Campaign;
use App\Models\GameSession;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    use InteractsWithCampaign;

    public ?GameSession $session = null;

    public string $number = '';

    public string $title = '';

    public string $scheduled_at = '';

    public string $status = SessionStatus::Planned->value;

    public string $visibility = Visibility::Players->value;

    public function mount(Campaign $campaign, ?int $number = null): void
    {
        $this->enterCampaign($campaign);

        if ($number === null) {
            $this->authorize('create', [GameSession::class, $campaign]);
            $this->number = (string) app(CreateSession::class)->nextNumber($campaign);

            return;
        }

        $session = GameSession::query()->where('number', $number)->first();

        abort_if($session === null, 404);

        $this->authorize('update', $session);

        $this->session = $session;
        $this->number = (string) $session->number;
        $this->title = $session->title ?? '';
        $this->scheduled_at = $session->scheduledAtIn($campaign->timezone)?->format('Y-m-d\TH:i') ?? '';
        $this->status = $session->status->value;
        $this->visibility = $session->visibility->value;
    }

    public function save(CreateSession $createSession, UpdateSession $updateSession): void
    {
        $isEdit = $this->session !== null;

        if ($isEdit) {
            $this->authorize('update', $this->session);
        } else {
            $this->authorize('create', [GameSession::class, $this->campaign]);
        }

        // Trashed sessions keep their number, and this rule sees them, exactly like
        // the unique index it mirrors.
        $validated = $this->validate([
            'number' => [
                'required', 'integer', 'min:0', 'max:9999',
                Rule::unique('game_sessions', 'number')
                    ->where('campaign_id', $this->campaign->id)
                    ->ignore($this->session?->id),
            ],
            'title' => ['nullable', 'string', 'max:120'],
            'scheduled_at' => ['nullable', 'date'],
            'status' => ['required', Rule::enum(SessionStatus::class)],
            'visibility' => ['required', Rule::enum(Visibility::class)->only([Visibility::Dm, Visibility::Players])],
        ]);

        $data = [
            'number' => (int) $validated['number'],
            'title' => filled($validated['title']) ? trim((string) $validated['title']) : null,
            'scheduled_at' => filled($validated['scheduled_at'])
                ? Carbon::parse((string) $validated['scheduled_at'], $this->campaign->timezone)->utc()
                : null,
            'status' => SessionStatus::from($validated['status']),
            'visibility' => Visibility::from($validated['visibility']),
        ];

        try {
            $session = $isEdit
                ? $updateSession->handle($this->session, $this->user(), $data)
                : $createSession->handle($this->campaign, $this->user(), $data);
        } catch (UniqueConstraintViolationException) {
            $this->addError('number', 'Another session already uses that number. Pick a different one.');

            return;
        }

        session()->flash('status', $isEdit ? "{$session->label()} saved." : "{$session->label()} created.");

        $this->redirect($session->url());
    }

    public function render(): View
    {
        return view('livewire.sessions.form', [
            'isEdit' => $this->session !== null,
            'statuses' => SessionStatus::cases(),
            'visibilities' => [Visibility::Players, Visibility::Dm],
            'timezone' => $this->campaign->timezone,
        ])->title($this->session !== null ? 'Edit session' : 'New session');
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
