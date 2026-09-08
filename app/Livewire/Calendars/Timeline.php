<?php

namespace App\Livewire\Calendars;

use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Calendar;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\User;
use App\Support\Reckoning\GameDate;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Every dated event and every dated session the viewer may see, in world order,
 * grouped by year, with a marker for today.
 *
 * Two queries, each through its own gate, merged and sorted here. The view draws a
 * list it is handed and decides nothing. There is no pagination: a timeline is read
 * whole, and the day a campaign dates a thousand things is the day to revisit that.
 */
#[Title('Timeline')]
class Timeline extends Component
{
    use InteractsWithCampaign;

    public function mount(Campaign $campaign): void
    {
        $this->enterCampaign($campaign);
    }

    public function render(): View
    {
        $calendar = Calendar::query()->first();
        $role = $this->role();

        return view('livewire.calendars.timeline', [
            'calendar' => $calendar,
            'role' => $role,
            'years' => $calendar === null ? [] : $this->years($calendar, $role),
        ]);
    }

    /**
     * @return list<array{label: string, rows: list<array{kind: string, date: GameDate, when: string, title: string, url: string|null, subtitle: string|null}>}>
     */
    private function years(Calendar $calendar, CampaignRole $role): array
    {
        $reckoning = $calendar->reckoning();
        $today = $calendar->today();
        $rows = [];

        foreach ($this->events($role) as $event) {
            /** @var GameDate $date */
            $date = $event->happens_on;

            $rows[] = [
                'kind' => 'event',
                'date' => $date,
                'when' => $reckoning->format($date),
                'title' => $event->name,
                'url' => $event->url(),
                'subtitle' => null,
            ];
        }

        foreach ($this->sessions($role) as $session) {
            /** @var GameDate $date */
            $date = $session->in_game_start;

            $rows[] = [
                'kind' => 'session',
                'date' => $date,
                'when' => $reckoning->formatRange($date, $session->in_game_end),
                'title' => $session->displayTitle(),
                'url' => $session->url(),
                'subtitle' => $session->label(),
            ];
        }

        $rows[] = [
            'kind' => 'today',
            'date' => $today,
            'when' => $reckoning->format($today),
            'title' => 'Today',
            'url' => null,
            'subtitle' => null,
        ];

        // Today sorts after anything on the same day, so the marker reads "up to here".
        usort($rows, fn (array $a, array $b): int => $a['date']->compare($b['date']) ?: ($a['kind'] === 'today') <=> ($b['kind'] === 'today'));

        // A marker on its own is an empty timeline, not a one-row one.
        if (count($rows) === 1) {
            return [];
        }

        $years = [];

        foreach ($rows as $row) {
            $years[$row['date']->year]['label'] = trim($row['date']->year.' '.$reckoning->era);
            $years[$row['date']->year]['rows'][] = $row;
        }

        return array_values($years);
    }

    /**
     * @return Collection<int, Entity>
     */
    private function events(CampaignRole $role)
    {
        return Entity::query()
            ->ofType(EntityType::Event)
            ->visibleTo($this->user(), $role)
            ->whereNotNull('happens_year')
            ->orderBy('happens_year')
            ->orderBy('happens_month')
            ->orderBy('happens_day')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, GameSession>
     */
    private function sessions(CampaignRole $role)
    {
        return GameSession::query()
            ->visibleTo($role)
            ->whereNotNull('in_game_start_year')
            ->orderBy('in_game_start_year')
            ->orderBy('in_game_start_month')
            ->orderBy('in_game_start_day')
            ->orderBy('number')
            ->get();
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
