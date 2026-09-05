<?php

namespace App\Actions\Campaigns;

use App\Actions\Entities\SyncTags;
use App\Models\Campaign;
use App\Models\Clock;
use App\Models\Combatant;
use App\Models\Encounter;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\RandomTable;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\HasMedia;
use Throwable;

/**
 * Builds a campaign from a document ReadCampaignFile has already checked.
 *
 * It creates a campaign. It never updates one. Merging into an existing campaign
 * needs a conflict rule for every row in the graph, and the wrong rule silently
 * overwrites a year of notes; creating is total, which makes the transaction boundary
 * obvious and the failure mode boring. One transaction, one campaign, or nothing.
 *
 * Every id is fresh. Reuse would work right up until a GM restores their own export
 * into the install it came from, which is the most likely restore there is.
 *
 * Observers are left running on purpose. The tempting optimisation is to silence them
 * and rebuild the mention index once at the end, but the index converges without it:
 * an entity written before the page it links to leaves an unresolved mention, and
 * ResolveMentionsFor points that row at the target the moment the target is created.
 * Silencing them would buy a little speed and cost the guarantee.
 */
class ImportCampaign
{
    public function __construct(
        private readonly CreateCampaign $createCampaign,
        private readonly SyncTags $syncTags,
    ) {}

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, string>  $restored  archive entry => a local file, from an archive import
     */
    public function handle(array $document, User $importer, array $restored = []): Campaign
    {
        // Outside the transaction on purpose: the media pass below needs it, and it
        // needs to run after the rows have committed.
        $ids = new IdMap;

        $campaign = DB::transaction(function () use ($document, $importer, $ids): Campaign {
            /** @var array<string, mixed> $attributes */
            $attributes = $document['campaign'];

            $campaign = $this->createCampaign->handle($importer, [
                'name' => $attributes['name'],
                'description' => $attributes['description'],
                'ruleset' => $attributes['ruleset']->value,
                'timezone' => $attributes['timezone'],
            ]);

            // Scout indexes on save, and a bulk import is the one time that is worth
            // deferring: one index at the end instead of a write per entity.
            Entity::withoutSyncingToSearch(function () use ($document, $importer, $campaign, $ids): void {
                $this->entities($document, $campaign, $importer, $ids);
                $this->sessions($document, $campaign, $importer, $ids);
                $this->linkEntities($document, $ids);
                $this->children($document, $ids);
                $this->prepped($document, $ids);
                $this->encounters($document, $campaign, $importer, $ids);
                $this->randomTables($document, $campaign, $importer, $ids);
                $this->clocks($document, $campaign, $ids);
            });

            // One index at the end rather than a write per entity. Chunked, so a
            // campaign of any size costs the same memory here as it does in the export.
            Entity::withoutGlobalScopes()
                ->where('campaign_id', $campaign->id)
                ->chunk(200, function ($entities): void {
                    $entities->each(fn (Entity $entity) => $entity->searchable());
                });

            return $campaign;
        });

        $this->attachMedia($campaign, $document, $ids, $restored);

        return $campaign;
    }

    /**
     * The pictures, after the rows have committed.
     *
     * Deliberately outside the transaction, and this is a correction to the plan
     * rather than an oversight. Files are not transactional in any database: attaching
     * them inside would move bytes onto the disk that a rollback could not take back,
     * so the transaction would only look total. Outside it, a rollback leaves no
     * files at all, and a picture that fails to attach costs a picture rather than
     * the whole campaign.
     *
     * @param  array<string, mixed>  $document
     * @param  array<string, string>  $restored
     */
    private function attachMedia(Campaign $campaign, array $document, IdMap $ids, array $restored): void
    {
        if ($restored === []) {
            return;
        }

        $this->attach($campaign, $document['campaign']['cover'] ?? null, 'cover', $restored);

        foreach ($document['entities'] as $row) {
            $entity = Entity::withoutGlobalScopes()->find($ids->newFor($row['id']));

            if ($entity === null) {
                continue;
            }

            $this->attach($entity, $row['image'], 'image', $restored);

            foreach ($row['files'] as $file) {
                $this->attach($entity, $file, 'files', $restored);
            }
        }
    }

    /**
     * archive_path is a key into the map the archive reader built, never a path. The
     * file it names was written by this application, under a name this application
     * chose, and checked before it got here.
     *
     * @param  array{archive_path: string|null, file_name: string|null}|null  $reference
     * @param  array<string, string>  $restored
     */
    private function attach(HasMedia $model, ?array $reference, string $collection, array $restored): void
    {
        $entry = $reference['archive_path'] ?? null;
        $file = $entry === null ? null : ($restored[$entry] ?? null);

        if ($file === null || ! is_file($file)) {
            return;
        }

        try {
            $model->addMedia($file)
                ->usingFileName($this->safeFileName($reference['file_name'] ?? null, $file))
                ->toMediaCollection($collection);
        } catch (Throwable) {
            // One picture, not the campaign. The report has already counted it.
            @unlink($file);
        }
    }

    /**
     * A file name from the document is a label, not a location. Basename first, then
     * a slug, so nothing that arrives can steer where Media Library writes.
     */
    private function safeFileName(?string $name, string $file): string
    {
        $base = $name === null ? '' : basename($name);
        $extension = strtolower((string) pathinfo($base, PATHINFO_EXTENSION));
        $stem = str(pathinfo($base, PATHINFO_FILENAME))->slug()->limit(60, '')->value();

        if ($stem === '') {
            $stem = 'file';
        }

        $extension = preg_replace('/[^a-z0-9]/', '', $extension) ?? '';

        return $extension !== '' ? $stem.'.'.$extension : $stem;
    }

    /**
     * forceFill, not create.
     *
     * `id` is not in any model's Fillable list, and create() drops what it cannot
     * assign, so every remapped id would be silently replaced by a fresh one and the
     * whole IdMap would point at rows that do not exist.
     *
     * @template TModel of Model
     *
     * @param  TModel  $model
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    private function write(Model $model, array $attributes): Model
    {
        $model->forceFill($attributes)->save();

        return $model;
    }

    /**
     * Pass one: every page, with its own references left null. Filling them needs ids
     * that do not exist yet, and two plain passes beat a topological sort of a graph
     * that may be a forest.
     *
     * player_user_id is dropped rather than remapped. It names somebody on another
     * install, and a PC with no player is a PC the GM assigns when its player joins.
     *
     * @param  array<string, mixed>  $document
     */
    private function entities(array $document, Campaign $campaign, User $importer, IdMap $ids): void
    {
        foreach ($document['entities'] as $row) {
            $this->write(new Entity, [
                'id' => $ids->remember($row['id']),
                'campaign_id' => $campaign->id,
                'type' => $row['type'],
                'name' => $row['name'],
                'slug' => $row['slug'] ?? str($row['name'])->slug()->limit(140, '')->value(),
                'body' => $row['body'],
                'dm_notes' => $row['dm_notes'],
                'rewards' => $row['rewards'],
                'visibility' => $row['visibility'],
                'is_pc' => $row['is_pc'],
                'player_user_id' => null,
                'character_class' => $row['character_class'],
                'level' => $row['level'],
                'sheet_url' => $row['sheet_url'],
                'quest_status' => $row['quest_status'],
                'created_by' => $importer->id,
                'updated_by' => $importer->id,
            ]);
        }
    }

    /**
     * Pass two, plus the tags. Tags are their own rows keyed by name, so SyncTags
     * makes them here exactly as the form does.
     *
     * @param  array<string, mixed>  $document
     */
    private function linkEntities(array $document, IdMap $ids): void
    {
        foreach ($document['entities'] as $row) {
            $entity = Entity::withoutGlobalScopes()->findOrFail($ids->newFor($row['id']));

            $entity->forceFill([
                'parent_id' => $ids->newForNullable($row['parent_id']),
                'giver_entity_id' => $ids->newForNullable($row['giver_entity_id']),
            ])->save();

            if ($row['tags'] !== []) {
                $this->syncTags->handle($entity, $row['tags']);
            }
        }
    }

    /**
     * The rows that hang off a page: objectives, which may record the night they were
     * finished, and map pins, which may point at another page.
     *
     * @param  array<string, mixed>  $document
     */
    private function children(array $document, IdMap $ids): void
    {
        foreach ($document['entities'] as $row) {
            $entity = Entity::withoutGlobalScopes()->findOrFail($ids->newFor($row['id']));

            foreach ($row['objectives'] as $objective) {
                $entity->objectives()->create([
                    'campaign_id' => $entity->campaign_id,
                    'position' => $objective['position'],
                    'body' => $objective['body'],
                    'completed_at' => $objective['completed_at'],
                    'completed_in_session_id' => $ids->newForNullable($objective['completed_in_session_id']),
                ]);
            }

            foreach ($row['markers'] as $marker) {
                $entity->markers()->create([
                    'campaign_id' => $entity->campaign_id,
                    'target_entity_id' => $ids->newForNullable($marker['target_entity_id']),
                    'label' => $marker['label'],
                    'x' => $marker['x'],
                    'y' => $marker['y'],
                    'player_visible' => $marker['player_visible'],
                ]);
            }
        }
    }

    /**
     * Sessions, then the rows inside them. Secrets record which session they came out
     * in, so every session id has to exist before any secret is written.
     *
     * @param  array<string, mixed>  $document
     */
    private function sessions(array $document, Campaign $campaign, User $importer, IdMap $ids): void
    {
        foreach ($document['sessions'] as $row) {
            $this->write(new GameSession, [
                'id' => $ids->remember($row['id']),
                'campaign_id' => $campaign->id,
                'number' => $row['number'],
                'title' => $row['title'],
                'scheduled_at' => $row['scheduled_at'],
                'status' => $row['status'],
                'visibility' => $row['visibility'],
                'strong_start' => $row['strong_start'],
                'live_notes' => $row['live_notes'],
                'recap' => $row['recap'],
                'recap_published_at' => $row['recap_published_at'],
                'dm_notes' => $row['dm_notes'],
                'created_by' => $importer->id,
                'updated_by' => $importer->id,
            ]);
        }

        foreach ($document['sessions'] as $row) {
            $session = GameSession::withoutGlobalScopes()->findOrFail($ids->newFor($row['id']));

            foreach ($row['scenes'] as $scene) {
                $session->scenes()->create([
                    'campaign_id' => $campaign->id,
                    'position' => $scene['position'],
                    'title' => $scene['title'],
                    'notes' => $scene['notes'],
                ]);
            }

            foreach ($row['secrets'] as $secret) {
                $session->secrets()->create([
                    'campaign_id' => $campaign->id,
                    'position' => $secret['position'],
                    'body' => $secret['body'],
                    'revealed_at' => $secret['revealed_at'],
                    'revealed_in_session_id' => $ids->newForNullable($secret['revealed_in_session_id']),
                    'created_by' => $importer->id,
                ]);
            }
        }
    }

    /**
     * The prep buckets. Written after both entities and sessions exist, because the
     * pivot is the one row that needs an id from each.
     *
     * @param  array<string, mixed>  $document
     */
    private function prepped(array $document, IdMap $ids): void
    {
        foreach ($document['sessions'] as $row) {
            $session = GameSession::withoutGlobalScopes()->findOrFail($ids->newFor($row['id']));

            foreach ($row['prepped'] as $entry) {
                if ($entry['entity_id'] === null) {
                    continue;
                }

                $session->entities()->attach($ids->newFor($entry['entity_id']), [
                    'role' => $entry['role']->value,
                    'position' => $entry['position'],
                ]);
            }
        }
    }

    /**
     * Encounters, their combatants, and then whose turn it is. active_combatant_id
     * carries no foreign key and is filled last, because it points at a row this same
     * loop is still writing.
     *
     * @param  array<string, mixed>  $document
     */
    private function encounters(array $document, Campaign $campaign, User $importer, IdMap $ids): void
    {
        foreach ($document['encounters'] as $row) {
            $encounter = $this->write(new Encounter, [
                'id' => $ids->remember($row['id']),
                'campaign_id' => $campaign->id,
                'game_session_id' => $ids->newForNullable($row['game_session_id']),
                'name' => $row['name'],
                'status' => $row['status'],
                'round' => $row['round'],
                'created_by' => $importer->id,
            ]);

            foreach ($row['combatants'] as $combatant) {
                $this->write(new Combatant, [
                    ...($combatant['id'] === null ? [] : ['id' => $ids->remember($combatant['id'])]),
                    'campaign_id' => $campaign->id,
                    'encounter_id' => $encounter->id,
                    'entity_id' => $ids->newForNullable($combatant['entity_id']),
                    'name' => $combatant['name'],
                    'initiative' => $combatant['initiative'],
                    'initiative_bonus' => $combatant['initiative_bonus'],
                    'hp' => $combatant['hp'],
                    'max_hp' => $combatant['max_hp'],
                    'ac' => $combatant['ac'],
                    'conditions' => $combatant['conditions'],
                    'position' => $combatant['position'],
                    'player_visible' => $combatant['player_visible'],
                ]);
            }

            if ($row['active_combatant_id'] !== null) {
                $encounter->forceFill(['active_combatant_id' => $ids->newFor($row['active_combatant_id'])])->save();
            }
        }
    }

    /**
     * Tables, then their rows. A row may nest another table, so every table id exists
     * before the first entry is written.
     *
     * @param  array<string, mixed>  $document
     */
    private function randomTables(array $document, Campaign $campaign, User $importer, IdMap $ids): void
    {
        foreach ($document['random_tables'] as $row) {
            $this->write(new RandomTable, [
                'id' => $ids->remember($row['id']),
                'campaign_id' => $campaign->id,
                'name' => $row['name'],
                'description' => $row['description'],
                'created_by' => $importer->id,
            ]);
        }

        foreach ($document['random_tables'] as $row) {
            $table = RandomTable::withoutGlobalScopes()->findOrFail($ids->newFor($row['id']));

            foreach ($row['entries'] as $entry) {
                $table->entries()->create([
                    'campaign_id' => $campaign->id,
                    'position' => $entry['position'],
                    'weight' => $entry['weight'],
                    'body' => $entry['body'],
                    'nested_table_id' => $ids->newForNullable($entry['nested_table_id']),
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function clocks(array $document, Campaign $campaign, IdMap $ids): void
    {
        foreach ($document['clocks'] as $row) {
            $this->write(new Clock, [
                'id' => $ids->remember($row['id']),
                'campaign_id' => $campaign->id,
                'entity_id' => $ids->newForNullable($row['entity_id']),
                'name' => $row['name'],
                'segments' => $row['segments'],
                'filled' => $row['filled'],
                'player_visible' => $row['player_visible'],
                'position' => $row['position'],
            ]);
        }
    }
}
