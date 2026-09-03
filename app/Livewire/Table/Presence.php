<?php

namespace App\Livewire\Table;

use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Campaign;
use App\Models\CampaignMember;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Who has the campaign open right now.
 *
 * A presence channel answers "who is here" as part of being a private channel, so
 * this strip costs no new channel and no new authorisation callback. It shows every
 * member with a lit or unlit dot, because "three of four" is the useful sentence and
 * "three" on its own is not.
 *
 * The roster comes from the database, not from the wire. Only user ids are read out
 * of the Echo payload, and every name and role on the screen is read back from
 * campaign_members. A browser can call a Livewire method with anything it likes, and
 * the worst a forged payload can do here is light a dot on the forger's own screen.
 *
 * Nested, so it calls enterCampaign() in its own mount: the hydrate hook runs per
 * component, and a member removed mid-session must stop reading the roster.
 */
class Presence extends Component
{
    use InteractsWithCampaign;

    /** @var list<int> */
    public array $presentIds = [];

    public function mount(Campaign $campaign): void
    {
        $this->enterCampaign($campaign);
    }

    /**
     * The roster Echo hands over on subscribing. It replaces what we hold, because it
     * is the whole truth about the channel at that moment.
     *
     * @param  array<mixed>  $members
     */
    #[On('echo-presence:campaign.{campaign.id},here')]
    public function here(array $members): void
    {
        $this->presentIds = $this->idsIn($members);
    }

    /**
     * @param  array<mixed>  $member
     */
    #[On('echo-presence:campaign.{campaign.id},joining')]
    public function joining(array $member): void
    {
        $this->presentIds = array_values(array_unique([
            ...$this->presentIds,
            ...$this->idsIn($member),
        ]));
    }

    /**
     * @param  array<mixed>  $member
     */
    #[On('echo-presence:campaign.{campaign.id},leaving')]
    public function leaving(array $member): void
    {
        $left = $this->idsIn($member);

        $this->presentIds = array_values(array_filter(
            $this->presentIds,
            fn (int $id) => ! in_array($id, $left, true),
        ));
    }

    public function render(): View
    {
        $members = $this->campaign->members()->with('user')->get()
            ->sortBy(fn (CampaignMember $member) => [$member->role->weight(), $member->user->name])
            ->values();

        $here = $members->filter(fn (CampaignMember $member) => in_array($member->user_id, $this->presentIds, true));

        return view('livewire.table.presence', [
            'members' => $members,
            'hereIds' => $here->pluck('user_id')->all(),
            'hereCount' => $here->count(),
        ]);
    }

    /**
     * Ids out of an Echo payload, and nothing else. It takes one member or a list of
     * them, because "here" sends the roster and "joining" sends one person.
     *
     * @param  array<mixed>  $payload
     * @return list<int>
     */
    private function idsIn(array $payload): array
    {
        $rows = array_key_exists('id', $payload) ? [$payload] : $payload;

        return (new Collection($rows))
            ->filter(fn (mixed $row) => is_array($row) && isset($row['id']) && is_numeric($row['id']))
            ->map(fn (array $row) => (int) $row['id'])
            ->unique()
            ->values()
            ->all();
    }
}
