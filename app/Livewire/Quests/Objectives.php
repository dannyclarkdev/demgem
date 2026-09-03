<?php

namespace App\Livewire\Quests;

use App\Actions\Quests\ToggleObjective;
use App\Actions\Support\ReorderPositions;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Markdown\MarkdownRenderer;
use App\Markdown\WikiLink\WikiLinkRenderer;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\QuestObjective;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * The objective checklist. It is its own component so a tick re-renders the list and
 * nothing else, and so the Run screen can drop one of these per active quest.
 *
 * Nested components re-authorise themselves: hydrateInteractsWithCampaign() runs per
 * component, not per page, so this calls enterCampaign() in its own mount(). Without
 * it a co-GM removed mid-session would keep ticking boxes.
 *
 * A player mounts this too, read-only, because objectives inherit the quest's
 * visibility. Every write goes through the manageQuest ability, which is GM-only.
 */
class Objectives extends Component
{
    use InteractsWithCampaign;

    public Entity $quest;

    /** Set on the Run screen so a tick records the night it happened. */
    public ?GameSession $session = null;

    public bool $compact = false;

    public string $newBody = '';

    public ?string $editingId = null;

    public string $editingBody = '';

    public function mount(Campaign $campaign, Entity $quest, ?GameSession $session = null, bool $compact = false): void
    {
        $this->enterCampaign($campaign);
        $this->authorize('view', $quest);

        $this->quest = $quest;
        $this->session = $session;
        $this->compact = $compact;
    }

    public function add(): void
    {
        $this->authorize('manageQuest', $this->quest);

        $validated = $this->validate(['newBody' => ['required', 'string', 'max:200']]);

        $this->quest->objectives()->create([
            'campaign_id' => $this->quest->campaign_id,
            'body' => trim($validated['newBody']),
            'position' => $this->nextPosition(),
        ]);

        $this->newBody = '';
    }

    public function edit(string $objectiveId): void
    {
        $this->authorize('manageQuest', $this->quest);

        $objective = $this->objective($objectiveId);

        $this->editingId = $objective->id;
        $this->editingBody = $objective->body;
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->editingBody = '';
    }

    public function save(): void
    {
        $this->authorize('manageQuest', $this->quest);

        $objective = $this->objective((string) $this->editingId);

        $validated = $this->validate(['editingBody' => ['required', 'string', 'max:200']]);

        $objective->update(['body' => trim($validated['editingBody'])]);

        $this->cancelEdit();
    }

    public function remove(string $objectiveId): void
    {
        $this->authorize('manageQuest', $this->quest);

        $this->objective($objectiveId)->delete();

        if ($this->editingId === $objectiveId) {
            $this->cancelEdit();
        }
    }

    public function toggle(string $objectiveId, ToggleObjective $toggleObjective): void
    {
        $this->authorize('manageQuest', $this->quest);

        $toggleObjective->toggle($this->objective($objectiveId), $this->session);
    }

    /**
     * Drag and drop. Livewire hands us the item id and its new zero-based position.
     */
    public function reorder(string $objectiveId, int $position, ReorderPositions $reorder): void
    {
        $this->authorize('manageQuest', $this->quest);

        $reorder->handle($this->quest->objectives()->getQuery(), $objectiveId, $position);
    }

    /**
     * The keyboard and tablet path to the same place.
     */
    public function move(string $objectiveId, int $offset, ReorderPositions $reorder): void
    {
        $this->authorize('manageQuest', $this->quest);

        $objective = $this->objective($objectiveId);

        $reorder->move($this->quest->objectives()->getQuery(), $objective->id, $objective->position, $offset);
    }

    public function render(MarkdownRenderer $renderer): View
    {
        $role = $this->role();
        $wikiLinks = WikiLinkRenderer::for($this->campaign, $this->user(), $role);
        $objectives = $this->quest->objectives()->with('completedInSession')->get();

        return view('livewire.quests.objectives', [
            'objectives' => $objectives,
            'canManage' => $this->user()->can('manageQuest', $this->quest),
            'bodyHtml' => $objectives->mapWithKeys(fn (QuestObjective $objective) => [
                $objective->id => $renderer->renderInline($objective->body, $wikiLinks),
            ]),
            'sessionLabels' => $this->sessionLabels($objectives),
            'progress' => [
                'done' => $objectives->filter(fn (QuestObjective $o) => $o->isComplete())->count(),
                'total' => $objectives->count(),
            ],
        ]);
    }

    /**
     * "Finished in Session 7" is fine to show a player, unless session 7 is a draft the
     * GM has hidden. Anything they may not see resolves to no label at all.
     *
     * @param  Collection<int, QuestObjective>  $objectives
     * @return Collection<string, string>
     */
    private function sessionLabels(Collection $objectives): Collection
    {
        $role = $this->role();

        return $objectives
            ->filter(fn (QuestObjective $objective) => $objective->completedInSession !== null)
            ->filter(fn (QuestObjective $objective) => $objective->completedInSession->isVisibleTo($role))
            ->mapWithKeys(fn (QuestObjective $objective) => [
                $objective->id => $objective->completedInSession->label(),
            ]);
    }

    private function nextPosition(): int
    {
        $max = $this->quest->objectives()->max('position');

        return $max === null ? 0 : ((int) $max) + 1;
    }

    private function objective(string $objectiveId): QuestObjective
    {
        return $this->quest->objectives()->whereKey($objectiveId)->firstOrFail();
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
