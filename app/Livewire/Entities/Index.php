<?php

namespace App\Livewire\Entities;

use App\Enums\EntityType;
use App\Enums\QuestStatus;
use App\Enums\Visibility;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use InteractsWithCampaign, WithPagination;

    public EntityType $entityType;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $tag = '';

    #[Url]
    public string $visibility = '';

    /** Quests only. A filtered quest log should be a link a GM can paste to the party. */
    #[Url(as: 'status')]
    public string $questStatus = '';

    /** Characters only. The party is a link a GM can paste, so it lives in the URL too. */
    #[Url(as: 'pc')]
    public string $partyOnly = '';

    public function mount(Campaign $campaign, string $type): void
    {
        $this->enterCampaign($campaign);
        $this->entityType = EntityType::fromSlug($type) ?? abort(404);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTag(): void
    {
        $this->resetPage();
    }

    public function updatedVisibility(): void
    {
        $this->resetPage();
    }

    public function updatedQuestStatus(): void
    {
        $this->resetPage();
    }

    public function updatedPartyOnly(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();
        $role = $this->role();
        $search = mb_strtolower(trim($this->search));
        $visibilityFilter = $role->isDm() ? Visibility::tryFrom($this->visibility) : null;
        $isQuest = $this->entityType === EntityType::Quest;
        $isCharacter = $this->entityType === EntityType::Character;
        $statusFilter = $isQuest ? QuestStatus::tryFrom($this->questStatus) : null;
        $partyFilter = $isCharacter && $this->partyOnly !== '';

        $entities = Entity::query()
            ->ofType($this->entityType)
            ->visibleTo($user, $role)
            ->with(['tags', 'parent', 'player', 'media'])
            ->when($isQuest, fn (Builder $q) => $q->with('objectives'))
            ->when($search !== '', fn (Builder $q) => $q->whereRaw('lower(name) like ?', ['%'.$search.'%']))
            ->when($this->tag !== '', fn (Builder $q) => $q->whereHas('tags', fn (Builder $t) => $t->where('slug', $this->tag)))
            ->when($visibilityFilter !== null, fn (Builder $q) => $q->where('visibility', $visibilityFilter?->value))
            ->when($statusFilter !== null, fn (Builder $q) => $q->where('quest_status', $statusFilter?->value))
            ->when($partyFilter, fn (Builder $q) => $q->where('is_pc', true))
            ->orderBy('name')
            ->paginate(25);

        $visibleOfType = function (Builder $q) use ($user, $role): void {
            /** @var Builder<Entity> $q */
            $q->ofType($this->entityType)->visibleTo($user, $role);
        };

        $tags = Tag::query()
            ->whereHas('entities', $visibleOfType)
            ->withCount(['entities' => $visibleOfType])
            ->orderBy('name')
            ->get();

        return view('livewire.entities.index', [
            'type' => $this->entityType,
            'entities' => $entities,
            'tags' => $tags,
            'role' => $role,
            'viewer' => $user,
            'visibilities' => Visibility::cases(),
            'isQuest' => $isQuest,
            'isCharacter' => $isCharacter,
            'questStatuses' => QuestStatus::cases(),
        ])->title($this->entityType->plural());
    }
}
