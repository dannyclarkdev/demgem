<?php

namespace App\Livewire\Sessions;

use App\Actions\Sessions\AddDateOption;
use App\Actions\Sessions\ClearDateOptions;
use App\Actions\Sessions\PickDate;
use App\Actions\Sessions\RecordAttendance;
use App\Actions\Sessions\RemoveDateOption;
use App\Actions\Sessions\RespondToSession;
use App\Actions\Sessions\ToggleDateVote;
use App\Actions\Sessions\UpdateSession;
use App\Enums\Rsvp;
use App\Enums\SessionStatus;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\GameSession;
use App\Models\SessionDateOption;
use App\Models\SessionRsvp;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Who is coming, and afterwards who came. Nested in Sessions\Show, and it writes, so
 * it re-checks membership itself on every round trip.
 */
class Attendance extends Component
{
    use InteractsWithCampaign;

    public GameSession $session;

    /** A datetime-local string in the campaign's zone, from the add form. */
    public string $newOption = '';

    public function mount(Campaign $campaign, GameSession $session): void
    {
        $this->enterCampaign($campaign);
        $this->authorize('view', $session);

        $this->session = $session;
    }

    public function respond(?string $rsvp): void
    {
        $this->authorize('respond', $this->session);

        $validated = validator(['rsvp' => $rsvp], [
            'rsvp' => ['nullable', Rule::enum(Rsvp::class)],
        ])->validate();

        app(RespondToSession::class)->handle(
            $this->session,
            $this->user(),
            $validated['rsvp'] === null ? null : Rsvp::from($validated['rsvp']),
        );
    }

    public function recordAttendance(int $userId, bool $attended): void
    {
        $this->authorize('recordAttendance', $this->session);

        $member = $this->members()->firstWhere('user_id', $userId);

        abort_if($member === null, 404);

        app(RecordAttendance::class)->handle($this->session, $member->user, $attended);
    }

    public function addDateOption(): void
    {
        $this->authorize('poll', $this->session);

        $validated = $this->validate(['newOption' => ['required', 'date']]);
        $startsAt = Carbon::parse((string) $validated['newOption'], $this->campaign->timezone)->utc();

        if ($this->session->dateOptions()->where('starts_at', $startsAt)->exists()) {
            $this->addError('newOption', 'That time is already on the list.');

            return;
        }

        app(AddDateOption::class)->handle($this->session, $startsAt);
        $this->newOption = '';
    }

    public function removeDateOption(string $optionId): void
    {
        $this->authorize('poll', $this->session);

        app(RemoveDateOption::class)->handle($this->option($optionId));
    }

    public function toggleDateVote(string $optionId): void
    {
        $this->authorize('vote', $this->session);

        app(ToggleDateVote::class)->handle($this->option($optionId), $this->user());
    }

    public function pickDate(string $optionId): void
    {
        $this->authorize('poll', $this->session);

        $this->session = app(PickDate::class)->handle($this->session, $this->option($optionId), $this->user(), app(UpdateSession::class));

        session()->flash('status', $this->session->label().' has a date. The party can say whether they are coming.');
        $this->redirect($this->session->url());
    }

    public function clearDateOptions(): void
    {
        $this->authorize('poll', $this->session);

        app(ClearDateOptions::class)->handle($this->session);
    }

    /**
     * An option is only ever looked up through its own session, so an id from some
     * other campaign's poll is a 404 rather than a write.
     */
    private function option(string $optionId): SessionDateOption
    {
        $option = $this->session->dateOptions()->whereKey($optionId)->first();

        abort_if($option === null, 404);

        return $option;
    }

    public function render(): View
    {
        $members = $this->members();
        $answers = $this->answers();

        $options = $this->session->dateOptions()->with('votes')->get();

        return view('livewire.sessions.attendance', [
            'role' => $this->role(),
            'members' => $members,
            'answers' => $answers,
            'options' => $options,
            'canPoll' => $this->user()->can('poll', $this->session),
            'canVote' => $this->user()->can('vote', $this->session),
            'mine' => $answers->get($this->user()->id),
            'headcount' => $this->headcount($members, $answers),
            'canRespond' => $this->user()->can('respond', $this->session),
            'canRecord' => $this->user()->can('recordAttendance', $this->session),
        ]);
    }

    /**
     * Everyone who can see this session. A player is never listed against a GM-only
     * session, because they cannot see the question.
     *
     * @return Collection<int, CampaignMember>
     */
    private function members(): Collection
    {
        return $this->campaign->members()
            ->with('user')
            ->get()
            ->filter(fn (CampaignMember $member) => $this->session->isVisibleTo($member->role))
            ->sortBy(fn (CampaignMember $member) => [$member->role->weight(), $member->user->name])
            ->values();
    }

    /**
     * @return Collection<int, SessionRsvp> keyed by user id
     */
    private function answers(): Collection
    {
        return $this->session->rsvps()->get()->keyBy('user_id');
    }

    /**
     * @param  Collection<int, CampaignMember>  $members
     * @param  Collection<int, SessionRsvp>  $answers
     */
    private function headcount(Collection $members, Collection $answers): string
    {
        $this->session->setRelation('rsvps', $answers->values());

        if ($this->session->status === SessionStatus::Played) {
            $there = $members->filter(fn (CampaignMember $member) => $answers->get($member->user_id)?->wasThere() ?? false)->count();

            return $there.' there';
        }

        $parts = array_filter([$this->session->rsvpSummary()]);
        $silent = $members->filter(fn (CampaignMember $member) => $answers->get($member->user_id)?->rsvp === null)->count();

        if ($silent > 0) {
            $parts[] = $silent.' not answered';
        }

        return implode(' · ', $parts);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
