<?php

namespace App\Livewire\Downtime;

use App\Actions\Downtime\DeleteDowntime;
use App\Actions\Downtime\RecordDowntime;
use App\Actions\Downtime\UpdateDowntime;
use App\Enums\EntityType;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Markdown\MarkdownRenderer;
use App\Markdown\WikiLink\WikiLinkRenderer;
use App\Models\Calendar;
use App\Models\Campaign;
use App\Models\DowntimeActivity;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\User;
use App\Support\Reckoning\Bounds;
use App\Support\Reckoning\GameDate;
use App\Support\Reckoning\Reckoning;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidatorInstance;
use Livewire\Component;

/**
 * The downtime log, drawn once for whoever is looking and in three places.
 *
 * On a character's page it is that character's rows and the form writes under
 * them. On a session's page it is the rows around that session, with a character
 * picker. Routed at /downtime it is the whole campaign, newest first, with both
 * pickers. Same component, one more where clause, the decision log's way, because
 * three forks of the write methods and the gate would drift.
 *
 * What a viewer reads is the query: DowntimeActivity::visibleTo() is a whereIn over
 * the characters they may see. What they may write is the policy: a GM writes on
 * anyone, a player on their own PC. The session link on a row is loaded through
 * GameSession::visibleTo() separately, so a GM-only session's number never reaches
 * a player's page beside a row they may read.
 *
 * Nested and it writes, so it re-checks membership itself on every round trip.
 */
class Log extends Component
{
    use InteractsWithCampaign;

    public ?Entity $character = null;

    public ?GameSession $session = null;

    public string $newCharacterId = '';

    public string $newActivity = '';

    public string $newDays = '1';

    public string $newNotes = '';

    public string $newSessionId = '';

    /** @var array{year: string, month: string, day: string} */
    public array $newStartsOn = ['year' => '', 'month' => '', 'day' => ''];

    public ?string $editingId = null;

    public string $editingActivity = '';

    public string $editingDays = '';

    public string $editingNotes = '';

    public string $editingSessionId = '';

    /** @var array{year: string, month: string, day: string} */
    public array $editingStartsOn = ['year' => '', 'month' => '', 'day' => ''];

    public function mount(Campaign $campaign, ?Entity $character = null, ?GameSession $session = null): void
    {
        $this->enterCampaign($campaign);
        $this->authorize('viewAny', [DowntimeActivity::class, $campaign]);

        $this->character = $character;
        $this->session = $session;
        $this->newSessionId = $session === null ? '' : $session->id;
    }

    public function record(RecordDowntime $recordDowntime): void
    {
        $character = $this->character ?? $this->pickedCharacter();

        if ($character === null) {
            return;
        }

        $this->authorize('create', [DowntimeActivity::class, $character]);

        $validated = $this->validate([
            'newActivity' => ['required', 'string', 'max:'.DowntimeActivity::MAX_ACTIVITY_LENGTH],
            'newDays' => ['required', 'integer', 'min:0', 'max:'.DowntimeActivity::MAX_DAYS],
            'newNotes' => ['nullable', 'string', 'max:'.DowntimeActivity::MAX_NOTES_LENGTH],
            'newSessionId' => $this->sessionRule(),
        ]);

        $startsOn = $this->startsOn('newStartsOn');

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $recordDowntime->handle($this->campaign, $this->user(), $character, [
            'activity' => $validated['newActivity'],
            'days' => (int) $validated['newDays'],
            'notes' => $validated['newNotes'] ?? null,
            'session' => $this->sessionFor($validated['newSessionId'] ?? ''),
            'starts_on' => $startsOn,
        ]);

        $this->reset('newActivity', 'newNotes', 'newStartsOn', 'newCharacterId');
        $this->newDays = '1';
        $this->newSessionId = $this->session === null ? '' : $this->session->id;
    }

    public function edit(string $activityId): void
    {
        $activity = $this->activity($activityId);

        $this->authorize('update', $activity);

        $this->editingId = $activity->id;
        $this->editingActivity = $activity->activity;
        $this->editingDays = (string) $activity->days;
        $this->editingNotes = $activity->notes ?? '';
        $this->editingSessionId = $activity->game_session_id ?? '';
        $this->editingStartsOn = $activity->starts_on === null
            ? ['year' => '', 'month' => '', 'day' => '']
            : array_map(fn (int $part) => (string) $part, $activity->starts_on->toArray());
    }

    public function cancelEdit(): void
    {
        $this->reset('editingId', 'editingActivity', 'editingDays', 'editingNotes', 'editingSessionId', 'editingStartsOn');
    }

    public function save(UpdateDowntime $updateDowntime): void
    {
        $activity = $this->activity((string) $this->editingId);

        $this->authorize('update', $activity);

        $validated = $this->validate([
            'editingActivity' => ['required', 'string', 'max:'.DowntimeActivity::MAX_ACTIVITY_LENGTH],
            'editingDays' => ['required', 'integer', 'min:0', 'max:'.DowntimeActivity::MAX_DAYS],
            'editingNotes' => ['nullable', 'string', 'max:'.DowntimeActivity::MAX_NOTES_LENGTH],
            'editingSessionId' => $this->sessionRule(),
        ]);

        $startsOn = $this->startsOn('editingStartsOn');

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $updateDowntime->handle($activity, [
            'activity' => $validated['editingActivity'],
            'days' => (int) $validated['editingDays'],
            'notes' => $validated['editingNotes'] ?? null,
            'session' => $this->sessionFor($validated['editingSessionId'] ?? ''),
            'starts_on' => $startsOn,
        ]);

        $this->cancelEdit();
    }

    public function delete(string $activityId, DeleteDowntime $deleteDowntime): void
    {
        $activity = $this->activity($activityId);

        $this->authorize('delete', $activity);

        $deleteDowntime->handle($activity);

        if ($this->editingId === $activityId) {
            $this->cancelEdit();
        }
    }

    public function render(MarkdownRenderer $renderer): View
    {
        $role = $this->role();
        $user = $this->user();
        $wikiLinks = WikiLinkRenderer::for($this->campaign, $user, $role);
        $reckoning = $this->reckoning();
        $activities = $this->activities();
        $characterOptions = $this->characterOptions();

        // Rendered here, per row, so the Blade prints what it was handed and never
        // reads a field the query did not load.
        $html = $activities->mapWithKeys(fn (DowntimeActivity $activity) => [
            $activity->id => $activity->hasNotes() ? $renderer->render($activity->notes, $wikiLinks) : '',
        ]);

        $ranges = $activities->mapWithKeys(fn (DowntimeActivity $activity) => [
            $activity->id => $reckoning === null ? null : $activity->range($reckoning),
        ]);

        return view('livewire.downtime.log', [
            'activities' => $activities,
            'html' => $html,
            'ranges' => $ranges,
            'sessionLinks' => $this->sessionLinks($activities),
            'editable' => $activities->filter(fn (DowntimeActivity $activity) => $user->can('update', $activity))->pluck('id')->all(),
            'canWrite' => $this->character !== null
                ? $user->can('create', [DowntimeActivity::class, $this->character])
                : $characterOptions->isNotEmpty(),
            'total' => $this->character === null ? null : (int) $activities->sum('days'),
            'scopedToCharacter' => $this->character !== null,
            'scopedToSession' => $this->session !== null,
            'characterOptions' => $characterOptions,
            'sessionOptions' => $this->session === null
                ? GameSession::query()->visibleTo($role)->orderByDesc('number')->get(['id', 'number', 'title'])
                : collect(),
            'months' => $reckoning === null ? [] : $reckoning->months,
            'hasCalendar' => $reckoning !== null,
        ]);
    }

    /**
     * @return Collection<int, DowntimeActivity>
     */
    private function activities(): Collection
    {
        return DowntimeActivity::query()
            ->visibleTo($this->user(), $this->role())
            ->when($this->character !== null, fn (Builder $query) => $query->forCharacter($this->character))
            ->when($this->session !== null, fn (Builder $query) => $query->around($this->session))
            ->with('character')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * The characters this member may write on: a GM every one, a player their own.
     * The list is also the rule the picker is checked against, so a posted id the
     * picker never offered is a validation error rather than a write.
     *
     * @return Collection<int, Entity>
     */
    private function characterOptions(): Collection
    {
        $user = $this->user();
        $role = $this->role();

        return Entity::query()
            ->ofType(EntityType::Character)
            ->visibleTo($user, $role)
            ->when(! $role->isDm(), fn (Builder $query) => $query->where('player_user_id', $user->id))
            ->orderBy('name')
            ->get(['id', 'name', 'campaign_id', 'player_user_id']);
    }

    private function pickedCharacter(): ?Entity
    {
        $options = $this->characterOptions();

        $this->validate([
            'newCharacterId' => ['required', Rule::in($options->pluck('id')->all())],
        ], [
            'newCharacterId.required' => 'Pick a character.',
            'newCharacterId.in' => 'Pick a character.',
        ]);

        /** @var Entity|null $character */
        $character = Entity::query()->whereKey($this->newCharacterId)->first();

        return $character;
    }

    /**
     * The session each row happened around, keyed by id, and only the ones this
     * viewer may see. One query for the whole log, with the filter in it.
     *
     * @param  Collection<int, DowntimeActivity>  $activities
     * @return Collection<string, GameSession>
     */
    private function sessionLinks(Collection $activities): Collection
    {
        /** @var Collection<int, string> $ids */
        $ids = $activities->pluck('game_session_id')->filter()->unique()->values();

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
     * The start date, checked against the calendar the way the session form checks
     * its two. All blank is null; a part that fails lands its error on its own input.
     * Without a calendar the picker is not offered, and whatever was posted is ignored.
     */
    private function startsOn(string $field): ?GameDate
    {
        $reckoning = $this->reckoning();

        if ($reckoning === null) {
            return null;
        }

        /** @var array{year?: string, month?: string, day?: string} $parts */
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

    /**
     * A row this viewer may read, or a 404: a hidden character's row is one a player
     * may not learn exists.
     */
    private function activity(string $activityId): DowntimeActivity
    {
        /** @var DowntimeActivity $activity */
        $activity = DowntimeActivity::query()
            ->visibleTo($this->user(), $this->role())
            ->whereKey($activityId)
            ->firstOrFail();

        return $activity;
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
