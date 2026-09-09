<?php

namespace App\Livewire\Entities;

use App\Actions\Entities\RestoreEntityBody;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class History extends Component
{
    use InteractsWithCampaign, WithPagination;

    #[Locked]
    public string $entityId;

    #[Locked]
    public ?string $revisionId = null;

    public bool $open = false;

    public function mount(Campaign $campaign, Entity $entity): void
    {
        $this->enterCampaign($campaign);
        $this->entityId = $entity->id;
        $this->entity();
    }

    public function select(string $revisionId): void
    {
        $entity = $this->entity();
        $this->revisionId = ($entity->bodyRevisions()->find($revisionId) ?? abort(404))->id;
    }

    public function restore(RestoreEntityBody $restore): void
    {
        $entity = $this->entity();
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        abort_if($this->revisionId === null, 404);
        $restore->handle($entity, $actor, $this->revisionId);
        $this->revisionId = null;
        $this->resetPage('historyPage');
        $this->dispatch('entity-body-restored')->to(Show::class);
        session()->flash('status', 'Body restored. The text it replaced is in history.');
    }

    public function render(): View
    {
        $entity = $this->entity();

        return view('livewire.entities.history', [
            'currentBody' => $this->open ? $entity->body : null,
            'revisions' => $this->open ? $entity->bodyRevisions()->orderByDesc('recorded_at')->orderByDesc('id')
                ->paginate(25, ['id', 'recorded_at', 'replaced_by_name'], 'historyPage') : null,
            'selected' => $this->open && $this->revisionId !== null
                ? ($entity->bodyRevisions()->find($this->revisionId) ?? abort(404)) : null,
        ]);
    }

    private function entity(): Entity
    {
        $entity = Entity::query()->find($this->entityId) ?? abort(404);
        $this->authorize('viewHistory', $entity);

        return $entity;
    }
}
