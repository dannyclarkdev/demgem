<?php

namespace App\Livewire\Entities;

use App\Actions\Entities\DeleteEntity;
use App\Actions\Sessions\SessionsMentioning;
use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Markdown\MarkdownRenderer;
use App\Markdown\WikiLink\WikiLinkRenderer;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\MapMarker;
use App\Models\Mention;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
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

        if ($this->entity->isQuest()) {
            $this->entity->load('giver');
        }

        $backlinkSourceIds = Mention::query()
            ->where('target_entity_id', $this->entity->id)
            ->where('source_type', $this->entity->getMorphClass())
            ->where('source_id', '!=', $this->entity->id)
            ->when(! $role->isDm(), fn ($query) => $query->whereIn('source_field', Entity::playerVisibleFields()))
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
            'rewardsHtml' => $this->entity->isQuest() ? $renderer->render($this->entity->rewards, $wikiLinks) : '',
            'questStatus' => $this->entity->questStatus(),
            'giver' => $this->visibleGiver($user, $role),
            'pinnedOn' => $this->mapsPinningThis($user, $role),
            // A handout's attachments. Eager-loaded, because strict mode is on and the
            // gallery is the point of the page rather than an extra on it.
            'files' => $this->entity->isHandout() ? $this->entity->files() : collect(),
        ])->title($this->entity->name);
    }

    /**
     * The maps that pin this thing. "Appears on" is the backlinks question the app
     * already answers for prose, asked of pictures.
     *
     * Both of a pin's gates apply. A pin the party has not found does not tell them
     * that the thing is on that map, and neither does a pin on a map they cannot open.
     *
     * @return Collection<int, Entity>
     */
    private function mapsPinningThis(User $user, CampaignRole $role): Collection
    {
        $mapIds = MapMarker::query()
            ->visibleTo($user, $role)
            ->where('target_entity_id', $this->entity->id)
            ->pluck('entity_id')
            ->unique();

        return Entity::query()
            ->whereIn('id', $mapIds)
            ->visibleTo($user, $role)
            ->orderBy('name')
            ->get();
    }

    /**
     * The giver is a separate entity with its own visibility, so a quest the party can
     * read may be given by an NPC they have never met. A hidden giver renders as
     * nothing at all: the absence of a row is the only safe empty state, because
     * "hidden" tells them there is somebody.
     */
    private function visibleGiver(User $user, CampaignRole $role): ?Entity
    {
        $giver = $this->entity->isQuest() ? $this->entity->giver : null;

        return $giver !== null && $giver->isVisibleTo($user, $role) ? $giver : null;
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
