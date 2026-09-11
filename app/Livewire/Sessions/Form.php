<?php

namespace App\Livewire\Sessions;

use App\Actions\Sessions\CreateSession;
use App\Actions\Sessions\UpdateSession;
use App\Enums\EntityType;
use App\Enums\SessionStatus;
use App\Enums\Visibility;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Calendar;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\User;
use App\Support\Reckoning\Bounds;
use App\Support\Reckoning\GameDate;
use App\Support\Reckoning\Reckoning;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidatorInstance;
use Livewire\Component;

class Form extends Component
{
    use InteractsWithCampaign;

    public ?GameSession $session = null;

    public string $number = '';

    public string $title = '';

    public string $scheduled_at = '';

    /**
     * The days the party spent in the world. Three parts each, all blank or all set;
     * the fields only show when the campaign has a calendar.
     *
     * @var array{year: int|string, month: int|string, day: int|string}
     */
    public array $inGameStart = ['year' => '', 'month' => '', 'day' => ''];

    /** @var array{year: int|string, month: int|string, day: int|string} */
    public array $inGameEnd = ['year' => '', 'month' => '', 'day' => ''];

    /**
     * The chapter this session was spent on. Must name an arc of this campaign.
     */
    public string $arc_id = '';

    /**
     * What the party earned. Either, both, or neither; a string so a blank input
     * stays a blank rather than a zero.
     */
    public string $xp_awarded = '';

    public string $milestone = '';

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
        $this->inGameStart = $session->in_game_start?->toArray() ?? $this->inGameStart;
        $this->inGameEnd = $session->in_game_end?->toArray() ?? $this->inGameEnd;
        $this->arc_id = $session->arc_id ?? '';
        $this->xp_awarded = $session->xp_awarded === null ? '' : (string) $session->xp_awarded;
        $this->milestone = $session->milestone ?? '';
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
            'arc_id' => [
                'nullable',
                Rule::exists('entities', 'id')
                    ->where('campaign_id', $this->campaign->id)
                    ->where('type', EntityType::Arc->value)
                    ->whereNull('deleted_at'),
            ],
            'xp_awarded' => ['nullable', 'integer', 'min:0', 'max:'.GameSession::MAX_XP],
            'milestone' => ['nullable', 'string', 'max:'.GameSession::MAX_MILESTONE_LENGTH],
            'status' => ['required', Rule::enum(SessionStatus::class)],
            'visibility' => ['required', Rule::enum(Visibility::class)->only([Visibility::Dm, Visibility::Players])],
        ]);

        $reckoning = $this->reckoning();
        $start = $reckoning === null ? null : $this->gameDate('inGameStart', $reckoning);
        $end = $reckoning === null ? null : $this->gameDate('inGameEnd', $reckoning);

        if ($reckoning !== null && $start !== null && $end !== null && $end->isBefore($start)) {
            $this->addError('inGameEnd.day', 'The session cannot end before it starts.');
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $data = [
            'number' => (int) $validated['number'],
            'in_game_start' => $start,
            'in_game_end' => $start === null ? null : $end,
            'arc_id' => ($validated['arc_id'] ?? '') !== '' ? $validated['arc_id'] : null,
            'xp_awarded' => ($validated['xp_awarded'] ?? '') !== '' && $validated['xp_awarded'] !== null ? (int) $validated['xp_awarded'] : null,
            'milestone' => filled($validated['milestone'] ?? null) ? trim((string) $validated['milestone']) : null,
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
        $reckoning = $this->reckoning();

        return view('livewire.sessions.form', [
            'months' => $reckoning === null ? [] : $reckoning->months,
            'arcOptions' => Entity::query()->ofType(EntityType::Arc)->orderBy('name')->get(['id', 'name']),
            'isEdit' => $this->session !== null,
            'statuses' => SessionStatus::cases(),
            'visibilities' => [Visibility::Players, Visibility::Dm],
            'timezone' => $this->campaign->timezone,
        ])->title($this->session !== null ? 'Edit session' : 'New session');
    }

    /**
     * One of the two date fields, checked against the calendar. All blank is null;
     * anything else has to be a whole date the calendar can place, and a part that
     * fails lands its error on its own input.
     */
    private function gameDate(string $field, Reckoning $reckoning): ?GameDate
    {
        $parts = $this->{$field};

        if (($parts['year'] ?? '') === '' && ($parts['month'] ?? '') === '' && ($parts['day'] ?? '') === '') {
            return null;
        }

        $validator = Validator::make([$field => $parts], [
            $field.'.year' => ['required', 'integer', 'min:'.Bounds::MIN_YEAR, 'max:'.Bounds::MAX_YEAR],
            $field.'.month' => ['required', 'integer', 'min:1', 'max:'.$reckoning->monthCount()],
            $field.'.day' => ['required', 'integer', 'min:1', 'max:'.Bounds::MAX_DAYS],
        ], [
            $field.'.*.required' => 'Fill in the day, the month, and the year, or leave all three blank.',
        ]);

        $validator->after(function (ValidatorInstance $validator) use ($field, $parts, $reckoning): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $date = new GameDate((int) $parts['year'], (int) $parts['month'], (int) $parts['day']);

            if (! $reckoning->isValid($date)) {
                $validator->errors()->add($field.'.day', $reckoning->monthName($date->month).' has '.$reckoning->daysInMonth($date->month, $date->year).' days that year.');
            }
        });

        if ($validator->fails()) {
            $this->getErrorBag()->merge($validator->errors());

            return null;
        }

        return new GameDate((int) $parts['year'], (int) $parts['month'], (int) $parts['day']);
    }

    private function reckoning(): ?Reckoning
    {
        return Calendar::query()->first()?->reckoning();
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
