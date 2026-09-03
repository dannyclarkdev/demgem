<?php

namespace App\Livewire\Entities;

use App\Actions\Entities\DeleteEntity;
use App\Actions\Sessions\SessionsMentioning;
use App\Enums\EntityType;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Markdown\MarkdownRenderer;
use App\Markdown\WikiLink\WikiLinkRenderer;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\Mention;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Show extends Component
{
    use InteractsWithCampaign;

    public Entity $entity;

    public function mount(Campaign $campaign, string $type, string $slug): void
    {
        $this->enterCampaign($campaign);

        $entityType = EntityType::fromSlug($type) ?? abort(404);
        $entity = Entity::query()->ofType($entityType)->where('slug', $slug)->first();

        abort_if($entity === null || ! $this->user()->can('view', $entity), 404);

        $this->entity = $entity;
    }

    public function delete(DeleteEntity $deleteEntity): void
    {
        $this->authorize('delete', $this->entity);

        $deleteEntity->handle($this->entity);

        session()->flash('status', "{$this->entity->name} was deleted.");

        $this->redirectRoute('entities.index', [$this->campaign, $this->entity->type->slug()]);
    }

    public function render(MarkdownRenderer $renderer): View
    {
        $user = $this->user();
        $role = $this->role();
        $wikiLinks = WikiLinkRenderer::for($this->campaign, $user, $role);

        $this->entity->load(['tags', 'player', 'media']);

        if ($role->isDm()) {
            $this->entity->load('viewers');
        }

        $backlinkSourceIds = Mention::query()
            ->where('target_entity_id', $this->entity->id)
            ->where('source_type', $this->entity->getMorphClass())
            ->where('source_id', '!=', $this->entity->id)
            ->when(! $role->isDm(), fn ($query) => $query->where('source_field', 'body'))
            ->pluck('source_id')
            ->unique();

        return view('livewire.entities.show', [
            'role' => $role,
            'viewer' => $user,
            'ancestors' => $this->entity->ancestors()->filter(fn (Entity $ancestor) => $ancestor->isVisibleTo($user, $role))->values(),
            'children' => $this->entity->children()->visibleTo($user, $role)->orderBy('name')->get(),
            'backlinks' => Entity::query()->whereIn('id', $backlinkSourceIds)->visibleTo($user, $role)->orderBy('name')->get(),
            'sessions' => app(SessionsMentioning::class)->handle($this->entity, $role),
            'bodyHtml' => $renderer->render($this->entity->body, $wikiLinks),
            'dmNotesHtml' => $role->isDm() ? $renderer->render($this->entity->dm_notes, $wikiLinks) : null,
        ])->title($this->entity->name);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
