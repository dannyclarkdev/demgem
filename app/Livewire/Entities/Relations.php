<?php

namespace App\Livewire\Entities;

use App\Actions\Entities\RelateEntities;
use App\Actions\Entities\RemoveRelation;
use App\Actions\Entities\SetRelationVisibility;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\EntityRelation;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

/**
 * The Relationships card: what this entity is to others and what they are to it,
 * both directions in one list. Nested in Entities\Show, and it writes, so it
 * re-checks membership itself on every round trip.
 */
class Relations extends Component
{
    use InteractsWithCampaign;

    public Entity $entity;

    public ?string $targetId = null;

    public string $label = '';

    public string $reverseLabel = '';

    public bool $showParty = false;

    public function mount(Campaign $campaign, Entity $entity): void
    {
        $this->enterCampaign($campaign);
        $this->authorize('view', $entity);

        $this->entity = $entity;
    }

    public function relate(RelateEntities $relateEntities): void
    {
        $this->authorize('manageRelations', $this->entity);

        $validated = $this->validate([
            'targetId' => ['required', 'string', 'different:entity.id'],
            'label' => ['required', 'string', 'max:'.EntityRelation::MAX_LABEL_LENGTH],
            'reverseLabel' => ['nullable', 'string', 'max:'.EntityRelation::MAX_LABEL_LENGTH],
            'showParty' => ['boolean'],
        ], [
            'targetId.different' => 'A page cannot be related to itself.',
        ]);

        if ($validated['targetId'] === $this->entity->id) {
            $this->addError('targetId', 'A page cannot be related to itself.');

            return;
        }

        // Through the campaign scope and the viewer's own visibility, so an id from
        // another campaign is refused rather than linked.
        $target = Entity::query()->visibleTo($this->user(), $this->role())->whereKey($validated['targetId'])->first();

        if ($target === null) {
            $this->addError('targetId', 'Pick a page from this campaign.');

            return;
        }

        $relateEntities->handle(
            $this->entity,
            $target,
            trim($validated['label']),
            filled($validated['reverseLabel']) ? trim($validated['reverseLabel']) : null,
            (bool) $validated['showParty'],
        );

        $this->reset('targetId', 'label', 'reverseLabel', 'showParty');
    }

    public function remove(string $relationId, RemoveRelation $removeRelation): void
    {
        $this->authorize('manageRelations', $this->entity);

        $removeRelation->handle($this->relation($relationId));
    }

    public function setVisibility(string $relationId, bool $visible, SetRelationVisibility $setVisibility): void
    {
        $this->authorize('manageRelations', $this->entity);

        $setVisibility->handle($this->relation($relationId), $visible);
    }

    public function render(): View
    {
        $user = $this->user();
        $role = $this->role();
        $canManage = $user->can('manageRelations', $this->entity);

        return view('livewire.entities.relations', [
            'role' => $role,
            'outgoing' => $this->entity->relations()->visibleTo($user, $role)->with('target')->get(),
            'incoming' => $this->entity->incomingRelations()->visibleTo($user, $role)->with('source')->orderBy('created_at')->get(),
            'canManage' => $canManage,
            'targetOptions' => $canManage ? $this->targetOptions() : collect(),
        ]);
    }

    /**
     * What a relationship may point at: anything in the campaign the viewer may see,
     * this page excluded.
     *
     * @return Collection<int, Entity>
     */
    private function targetOptions(): Collection
    {
        return Entity::query()
            ->visibleTo($this->user(), $this->role())
            ->whereKeyNot($this->entity->id)
            ->orderBy('name')
            ->get(['id', 'name', 'type']);
    }

    /**
     * A row is only ever looked up from one of this entity's two lists, so an id
     * from some other page is a 404 rather than a write.
     */
    private function relation(string $relationId): EntityRelation
    {
        $relation = EntityRelation::query()
            ->whereKey($relationId)
            ->where(fn ($query) => $query->where('entity_id', $this->entity->id)->orWhere('target_entity_id', $this->entity->id))
            ->first();

        abort_if($relation === null, 404);

        return $relation;
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
