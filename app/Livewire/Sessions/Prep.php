<?php

namespace App\Livewire\Sessions;

use App\Actions\Sessions\CarrySecretForward;
use App\Actions\Sessions\ReorderScenes;
use App\Actions\Sessions\RevealSecret;
use App\Actions\Sessions\UpdateSession;
use App\Enums\PrepRole;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Markdown\MarkdownRenderer;
use App\Markdown\WikiLink\WikiLinkRenderer;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\Scene;
use App\Models\Secret;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * The GM-only prep screen. Every panel here is invisible to players, and the route
 * itself 404s for them, so no player ever mounts this component.
 */
class Prep extends Component
{
    use InteractsWithCampaign;

    public GameSession $session;

    public string $strong_start = '';

    public string $dm_notes = '';

    public string $strongStartPreview = '';

    public string $dmNotesPreview = '';

    public string $newSceneTitle = '';

    public ?string $editingSceneId = null;

    public string $sceneTitle = '';

    public string $sceneNotes = '';

    public string $sceneNotesPreview = '';

    public string $newSecretBody = '';

    public ?string $editingSecretId = null;

    public string $secretBody = '';

    /** Which bucket the entity picker is filling. Empty means the picker is closed. */
    public string $pickerRole = '';

    public string $pickerSearch = '';

    public function mount(Campaign $campaign, int $number): void
    {
        $this->enterCampaign($campaign);

        $session = GameSession::query()->where('number', $number)->first();

        abort_if($session === null, 404);

        $this->authorize('update', $session);

        $this->session = $session;
        $this->strong_start = $session->strong_start ?? '';
        $this->dm_notes = $session->dm_notes ?? '';
    }

    public function saveNotes(UpdateSession $updateSession): void
    {
        $this->authorize('update', $this->session);

        $validated = $this->validate([
            'strong_start' => ['nullable', 'string', 'max:100000'],
            'dm_notes' => ['nullable', 'string', 'max:100000'],
        ]);

        $updateSession->handle($this->session, $this->user(), [
            'strong_start' => filled($validated['strong_start']) ? $validated['strong_start'] : null,
            'dm_notes' => filled($validated['dm_notes']) ? $validated['dm_notes'] : null,
        ]);

        session()->flash('status', 'Prep saved.');
    }

    public function previewStrongStart(MarkdownRenderer $renderer): void
    {
        $this->strongStartPreview = $renderer->render($this->strong_start, $this->wikiLinks());
    }

    public function previewDmNotes(MarkdownRenderer $renderer): void
    {
        $this->dmNotesPreview = $renderer->render($this->dm_notes, $this->wikiLinks());
    }

    public function previewSceneNotes(MarkdownRenderer $renderer): void
    {
        $this->sceneNotesPreview = $renderer->render($this->sceneNotes, $this->wikiLinks());
    }

    public function addScene(): void
    {
        $this->authorize('update', $this->session);

        $this->validate(['newSceneTitle' => ['required', 'string', 'max:160']]);

        $scene = $this->session->scenes()->create([
            'campaign_id' => $this->session->campaign_id,
            'title' => trim($this->newSceneTitle),
            'position' => $this->nextPosition($this->session->scenes()->max('position')),
        ]);

        $this->newSceneTitle = '';
        $this->editScene($scene->id);
    }

    public function editScene(string $sceneId): void
    {
        $scene = $this->scene($sceneId);

        $this->editingSceneId = $scene->id;
        $this->sceneTitle = $scene->title;
        $this->sceneNotes = $scene->notes ?? '';
        $this->sceneNotesPreview = '';
    }

    public function cancelSceneEdit(): void
    {
        $this->editingSceneId = null;
        $this->sceneTitle = '';
        $this->sceneNotes = '';
        $this->sceneNotesPreview = '';
    }

    public function saveScene(): void
    {
        $this->authorize('update', $this->session);

        $scene = $this->scene((string) $this->editingSceneId);

        $validated = $this->validate([
            'sceneTitle' => ['required', 'string', 'max:160'],
            'sceneNotes' => ['nullable', 'string', 'max:100000'],
        ]);

        $scene->update([
            'title' => trim($validated['sceneTitle']),
            'notes' => filled($validated['sceneNotes']) ? $validated['sceneNotes'] : null,
        ]);

        $this->cancelSceneEdit();
    }

    public function removeScene(string $sceneId): void
    {
        $this->authorize('update', $this->session);

        $this->scene($sceneId)->delete();

        if ($this->editingSceneId === $sceneId) {
            $this->cancelSceneEdit();
        }
    }

    /**
     * Drag and drop. Livewire hands us the item id and its new zero-based position.
     */
    public function reorderScenes(string $id, int $position, ReorderScenes $reorderScenes): void
    {
        $this->authorize('update', $this->session);

        $reorderScenes->handle($this->session, $id, $position);
    }

    /**
     * The keyboard and tablet path to the same place.
     */
    public function moveScene(string $sceneId, int $offset, ReorderScenes $reorderScenes): void
    {
        $this->authorize('update', $this->session);

        $reorderScenes->move($this->session, $this->scene($sceneId), $offset);
    }

    public function addSecret(): void
    {
        $this->authorize('update', $this->session);

        $this->validate(['newSecretBody' => ['required', 'string', 'max:2000']]);

        $this->session->secrets()->create([
            'campaign_id' => $this->session->campaign_id,
            'body' => trim($this->newSecretBody),
            'position' => $this->nextPosition($this->session->secrets()->max('position')),
            'created_by' => $this->user()->id,
        ]);

        $this->newSecretBody = '';
    }

    public function editSecret(string $secretId): void
    {
        $secret = $this->secret($secretId);

        $this->editingSecretId = $secret->id;
        $this->secretBody = $secret->body;
    }

    public function cancelSecretEdit(): void
    {
        $this->editingSecretId = null;
        $this->secretBody = '';
    }

    public function saveSecret(): void
    {
        $this->authorize('update', $this->session);

        $secret = $this->secret((string) $this->editingSecretId);

        $validated = $this->validate(['secretBody' => ['required', 'string', 'max:2000']]);

        $secret->update(['body' => trim($validated['secretBody'])]);

        $this->cancelSecretEdit();
    }

    public function removeSecret(string $secretId): void
    {
        $this->authorize('update', $this->session);

        $this->secret($secretId)->delete();

        if ($this->editingSecretId === $secretId) {
            $this->cancelSecretEdit();
        }
    }

    public function revealSecret(string $secretId, RevealSecret $revealSecret): void
    {
        $this->authorize('update', $this->session);

        $revealSecret->handle($this->secret($secretId), $this->session);
    }

    public function unrevealSecret(string $secretId, RevealSecret $revealSecret): void
    {
        $this->authorize('update', $this->session);

        $revealSecret->undo($this->secret($secretId));
    }

    public function carrySecretForward(string $secretId, CarrySecretForward $carrySecretForward): void
    {
        $this->authorize('update', $this->session);

        $carrySecretForward->handle($this->secret($secretId), $this->session);
    }

    public function openPicker(string $role): void
    {
        $this->pickerRole = PrepRole::from($role)->value;
        $this->pickerSearch = '';
    }

    public function closePicker(): void
    {
        $this->pickerRole = '';
        $this->pickerSearch = '';
    }

    public function attachEntity(string $entityId): void
    {
        $this->authorize('update', $this->session);

        $role = PrepRole::from($this->pickerRole);
        $entity = Entity::query()->whereKey($entityId)->firstOrFail();

        // The same entity may sit in two buckets, so syncWithoutDetaching is wrong here:
        // it keys on the entity alone and would move the existing row to the new role.
        $alreadyPrepped = $this->session->prepped($role)->whereKey($entity->id)->exists();

        if (! $alreadyPrepped) {
            $this->session->entities()->attach($entity->id, [
                'role' => $role->value,
                'position' => $this->nextPosition($this->session->prepped($role)->max('game_session_entities.position')),
            ]);
        }

        $this->closePicker();
    }

    public function detachEntity(string $entityId, string $role): void
    {
        $this->authorize('update', $this->session);

        $this->session->entities()
            ->wherePivot('role', PrepRole::from($role)->value)
            ->detach($entityId);
    }

    public function render(MarkdownRenderer $renderer): View
    {
        $user = $this->user();
        $role = $this->role();
        $wikiLinks = WikiLinkRenderer::for($this->campaign, $user, $role);

        $scenes = $this->session->scenes()->get();
        $secrets = $this->session->secrets()->unrevealed()->get();
        $buckets = [];

        foreach (PrepRole::cases() as $prepRole) {
            $buckets[$prepRole->value] = $this->session->prepped($prepRole)->with('media')->get();
        }

        $party = Entity::query()
            ->where('is_pc', true)
            ->with('player')
            ->orderBy('name')
            ->get();

        return view('livewire.sessions.prep', [
            'role' => $role,
            'scenes' => $scenes,
            'sceneNotesHtml' => $scenes->mapWithKeys(fn (Scene $scene) => [
                $scene->id => $renderer->render($scene->notes, $wikiLinks),
            ]),
            'prepRoles' => PrepRole::cases(),
            'buckets' => $buckets,
            'party' => $party,
            'checklist' => $this->checklist($scenes, $buckets, $party),
            'secrets' => $secrets,
            'carriedSecrets' => Secret::query()->carriedInto($this->session)->orderBy('created_at')->get(),
            'revealedSecrets' => $this->session->revealedSecrets()->get(),
            'secretHtml' => $this->renderSecrets($renderer, $wikiLinks),
            'pickerResults' => $this->pickerResults($user),
            'autocompleteUrl' => route('entities.autocomplete', $this->campaign),
        ])->title($this->session->label().' prep');
    }

    /**
     * Which prep steps have something in them. Nothing is stored; it is all counted.
     *
     * @param  Collection<int, Scene>  $scenes
     * @param  array<string, Collection<int, Entity>>  $buckets
     * @param  Collection<int, Entity>  $party
     * @return list<array{label: string, done: bool, count: int|null, hint: string}>
     */
    private function checklist(Collection $scenes, array $buckets, Collection $party): array
    {
        $steps = [
            ['label' => 'Review the party', 'done' => $party->isNotEmpty(), 'count' => $party->count(), 'hint' => 'Who is at the table.'],
            ['label' => 'Write a strong start', 'done' => filled($this->session->strong_start), 'count' => null, 'hint' => 'The first thing that happens.'],
            ['label' => 'Outline scenes', 'done' => $scenes->isNotEmpty(), 'count' => $scenes->count(), 'hint' => 'What might happen.'],
        ];

        foreach (PrepRole::cases() as $prepRole) {
            $count = $buckets[$prepRole->value]->count();

            $steps[] = [
                'label' => 'Pick '.$prepRole->lowerPlural(),
                'done' => $count > 0,
                'count' => $count,
                'hint' => $prepRole->description(),
            ];
        }

        return $steps;
    }

    /**
     * @return Collection<int, Entity>
     */
    private function pickerResults(User $user): Collection
    {
        if ($this->pickerRole === '') {
            return new Collection;
        }

        $role = PrepRole::from($this->pickerRole);
        $search = mb_strtolower(trim($this->pickerSearch));
        $suggested = array_map(fn ($type) => $type->value, $role->suggestedTypes());
        $attached = $this->session->prepped($role)->pluck('entities.id');

        return Entity::query()
            ->visibleTo($user, $this->role())
            ->whereKeyNot($attached)
            ->when($search !== '', fn (Builder $q) => $q->whereRaw('lower(name) like ?', ['%'.$search.'%']))
            ->orderByRaw('case when type in ('.implode(',', array_fill(0, count($suggested), '?')).') then 0 else 1 end', $suggested)
            ->orderBy('name')
            ->limit(20)
            ->get();
    }

    /**
     * Append to the end of a list that may be empty.
     */
    private function nextPosition(mixed $currentMax): int
    {
        return $currentMax === null ? 0 : ((int) $currentMax) + 1;
    }

    private function scene(string $sceneId): Scene
    {
        return $this->session->scenes()->whereKey($sceneId)->firstOrFail();
    }

    /**
     * Secrets belong to the campaign, not to one session, so a carried-over secret is
     * reachable here before it is pinned to this session.
     */
    private function secret(string $secretId): Secret
    {
        return Secret::query()->whereKey($secretId)->firstOrFail();
    }

    /**
     * Secrets render their wiki links so a GM can jump to the NPC mid-sentence. They are
     * not indexed as mention sources: they move between sessions, and every move would
     * have to re-scope the rows.
     *
     * @return Collection<string, string>
     */
    private function renderSecrets(MarkdownRenderer $renderer, WikiLinkRenderer $wikiLinks): Collection
    {
        return Secret::query()
            ->where(function (Builder $query): void {
                $query->where('game_session_id', $this->session->id)
                    ->orWhere('revealed_in_session_id', $this->session->id)
                    ->orWhereIn('id', Secret::query()->carriedInto($this->session)->select('id'));
            })
            ->pluck('body', 'id')
            ->map(fn (string $body) => $renderer->render($body, $wikiLinks));
    }

    private function wikiLinks(): WikiLinkRenderer
    {
        return WikiLinkRenderer::for($this->campaign, $this->user(), $this->role());
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
