<?php

namespace App\Livewire\Entities;

use App\Actions\Entities\RelateEntities;
use App\Actions\Entities\RemoveRelation;
use App\Actions\Entities\SetRelationVisibility;
use App\Enums\CampaignRole;
use App\Enums\Kinship;
use App\Enums\Visibility;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\EntityRelation;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Validation\Rule;
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

    /** A Kinship value, or '' for a relationship that is not family. */
    public string $kinship = '';

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

        // The labels fill in from the kinship when the GM leaves them blank, so a
        // kinship row needs no words; a plain relationship still does.
        $validated = $this->validate([
            'targetId' => ['required', 'string', 'different:entity.id'],
            'kinship' => ['nullable', Rule::enum(Kinship::class)],
            'label' => ['required_without:kinship', 'nullable', 'string', 'max:'.EntityRelation::MAX_LABEL_LENGTH],
            'reverseLabel' => ['nullable', 'string', 'max:'.EntityRelation::MAX_LABEL_LENGTH],
            'showParty' => ['boolean'],
        ], [
            'targetId.different' => 'A page cannot be related to itself.',
            'label.required_without' => 'Say what this page is to the other, or pick a kinship.',
        ]);

        $kinship = filled($validated['kinship'] ?? null) ? Kinship::from($validated['kinship']) : null;

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
            filled($validated['label']) ? trim((string) $validated['label']) : (string) $kinship?->label(),
            filled($validated['reverseLabel']) ? trim((string) $validated['reverseLabel']) : $kinship?->reverse()->label(),
            (bool) $validated['showParty'],
            $kinship,
        );

        $this->reset('targetId', 'label', 'reverseLabel', 'kinship', 'showParty');
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
            'kinships' => Kinship::cases(),
            'family' => $this->family($user, $role),
        ]);
    }

    /**
     * The family, walked from this character two generations up and two down.
     *
     * One query for every kinship row this viewer may see in the campaign, through
     * the relations scope, and then a walk in memory. That is what makes the second
     * hop as safe as the first: a row the scope refused is not in the graph, so a
     * GM-only grandmother is never reached through a revealed mother. A campaign's
     * kinship rows are a handful, and one query for all of them is cheaper than a
     * query per hop.
     *
     * A row is read from either end: the source is its kinship of the target, and
     * the target is the reverse of the source. The "hidden" mark is for a GM, who
     * reads every row, and says the party would not: the row is unrevealed or the
     * relative is not a page the party may see.
     *
     * @return array<string, SupportCollection<int, array{entity: Entity, hidden: bool}>>|null
     */
    private function family(User $user, CampaignRole $role): ?array
    {
        if (! $this->entity->isCharacter()) {
            return null;
        }

        $rows = EntityRelation::query()
            ->visibleTo($user, $role)
            ->whereNotNull('kinship')
            ->with(['source', 'target'])
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        /** @var array<string, list<array{kinship: Kinship, entity: Entity, hidden: bool}>> $edges */
        $edges = [];

        foreach ($rows as $row) {
            if ($row->kinship === null) {
                continue;
            }

            $edges[$row->target_entity_id][] = ['kinship' => $row->kinship, 'entity' => $row->source, 'hidden' => ! $row->player_visible || $row->source->visibility !== Visibility::Players];
            $edges[$row->entity_id][] = ['kinship' => $row->kinship->reverse(), 'entity' => $row->target, 'hidden' => ! $row->player_visible || $row->target->visibility !== Visibility::Players];
        }

        $me = $this->entity->id;

        if (! isset($edges[$me])) {
            return null;
        }

        /** @return SupportCollection<int, array{entity: Entity, hidden: bool}> */
        $of = function (string $id, Kinship $kinship, bool $throughHidden = false) use ($edges): SupportCollection {
            return collect($edges[$id] ?? [])
                ->filter(fn (array $edge) => $edge['kinship'] === $kinship)
                ->map(fn (array $edge) => ['entity' => $edge['entity'], 'hidden' => $edge['hidden'] || $throughHidden])
                ->values();
        };

        $parents = $of($me, Kinship::Parent);
        $children = $of($me, Kinship::Child);

        $generations = [
            'Grandparents' => $parents->flatMap(fn (array $parent) => $of($parent['entity']->id, Kinship::Parent, $parent['hidden'])),
            'Parents' => $parents,
            'Siblings' => $of($me, Kinship::Sibling),
            'Spouses' => $of($me, Kinship::Spouse),
            'Children' => $children,
            'Grandchildren' => $children->flatMap(fn (array $child) => $of($child['entity']->id, Kinship::Child, $child['hidden'])),
        ];

        $family = [];

        foreach ($generations as $heading => $people) {
            $people = $people
                ->reject(fn (array $person) => $person['entity']->id === $me)
                ->unique(fn (array $person) => $person['entity']->id)
                ->sortBy(fn (array $person) => $person['entity']->name)
                ->values();

            if ($people->isNotEmpty()) {
                $family[$heading] = $people;
            }
        }

        return $family === [] ? null : $family;
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
