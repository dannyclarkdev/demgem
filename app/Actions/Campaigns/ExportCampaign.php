<?php

namespace App\Actions\Campaigns;

use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\Clock;
use App\Models\Combatant;
use App\Models\DiceRoll;
use App\Models\Encounter;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\MapMarker;
use App\Models\QuestObjective;
use App\Models\RandomTable;
use App\Models\RandomTableEntry;
use App\Models\Scene;
use App\Models\Secret;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A whole campaign as JSON: the open source promise, kept.
 *
 * Every section is a LazyCollection so response()->streamJson() walks it row by row.
 * Nothing here calls get(): a campaign with a year of Thursdays in it must cost the
 * same memory as an empty one, and the download must start immediately.
 *
 * Strict mode is on outside production, so every section eager-loads what it renders.
 * A lazy load here throws after the headers are sent, which arrives as a truncated
 * file that still looks like JSON.
 */
class ExportCampaign
{
    public const FORMAT = 'demgem.campaign';

    public const VERSION = 1;

    /**
     * Campaign-scoped tables that are exported inside another section rather than as
     * one of their own.
     *
     * @var array<string, string>
     */
    public const NESTED_TABLES = [
        'entity_tag' => 'entities[].tags',
        'entity_viewers' => 'entities[].viewer_user_ids',
        'quest_objectives' => 'entities[].objectives',
        'map_markers' => 'entities[].markers',
        'scenes' => 'sessions[].scenes',
        'secrets' => 'sessions[].secrets',
        'game_session_entities' => 'sessions[].prepped',
        'combatants' => 'encounters[].combatants',
        'random_table_entries' => 'random_tables[].entries',
        'tags' => 'entities[].tags, by name',
    ];

    /**
     * Campaign-scoped tables this export leaves out on purpose, with the reason.
     *
     * ExportCoverageTest reads the schema and fails when a table appears in neither
     * this list nor the section map, so a new table cannot quietly miss the export.
     *
     * @var array<string, string>
     */
    public const EXCLUDED_TABLES = [
        'campaign_invites' => 'Live tokens. An exported invite is a credential in a text file.',
        'mentions' => 'Derived. The observers rebuild the whole index on save.',
    ];

    /**
     * Table to top-level section name.
     *
     * @var array<string, string>
     */
    public const SECTION_TABLES = [
        'campaign_members' => 'members',
        'clocks' => 'clocks',
        'entities' => 'entities',
        'game_sessions' => 'sessions',
        'encounters' => 'encounters',
        'random_tables' => 'random_tables',
        'dice_rolls' => 'dice_rolls',
    ];

    /**
     * @return array<string, mixed>
     */
    public function handle(Campaign $campaign): array
    {
        return [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'generated_at' => now()->toIso8601String(),
            'campaign' => $this->campaign($campaign),
            'members' => $this->members($campaign),
            'entities' => $this->entities($campaign),
            'sessions' => $this->sessions($campaign),
            'encounters' => $this->encounters($campaign),
            'random_tables' => $this->randomTables($campaign),
            'dice_rolls' => $this->diceRolls($campaign),
            'clocks' => $this->clocks($campaign),
        ];
    }

    public function filename(Campaign $campaign): string
    {
        $name = str($campaign->name)->slug()->limit(60, '')->value();

        return 'demgem-'.($name !== '' ? $name : 'campaign').'-'.now()->format('Y-m-d').'.json';
    }

    /**
     * @return array<string, mixed>
     */
    private function campaign(Campaign $campaign): array
    {
        return [
            'id' => $campaign->id,
            'name' => $campaign->name,
            'description' => $campaign->description,
            'ruleset' => $campaign->ruleset->value,
            'timezone' => $campaign->timezone,
            'created_at' => $campaign->created_at?->toIso8601String(),
            'updated_at' => $campaign->updated_at?->toIso8601String(),
            'cover' => $this->media($campaign->getFirstMedia('cover')),
        ];
    }

    /**
     * Names and roles, never email addresses. An export file gets shared, and the
     * party's addresses are not the GM's to hand around; an importer re-links people
     * by invite, which is how they joined in the first place.
     *
     * @return iterable<int, array<string, mixed>> A LazyCollection: it streams row by row.
     */
    private function members(Campaign $campaign): iterable
    {
        return CampaignMember::query()
            ->where('campaign_id', $campaign->id)
            ->with('user')
            ->orderBy('created_at')
            ->cursor()
            ->map(fn (CampaignMember $member) => [
                'user_id' => $member->user_id,
                'name' => $member->user->name,
                'role' => $member->role->value,
                'joined_at' => $member->created_at?->toIso8601String(),
            ]);
    }

    /**
     * @return iterable<int, array<string, mixed>> A LazyCollection: it streams row by row.
     */
    private function entities(Campaign $campaign): iterable
    {
        return Entity::query()
            ->withoutGlobalScopes()
            ->where('campaign_id', $campaign->id)
            ->whereNull('deleted_at')
            ->with(['tags', 'viewers', 'media', 'objectives', 'markers'])
            ->orderBy('created_at')
            ->cursor()
            ->map(fn (Entity $entity) => [
                'id' => $entity->id,
                'type' => $entity->type->value,
                'name' => $entity->name,
                'slug' => $entity->slug,
                'body' => $entity->body,
                'dm_notes' => $entity->dm_notes,
                'rewards' => $entity->rewards,
                'visibility' => $entity->visibility->value,
                'parent_id' => $entity->parent_id,
                'is_pc' => $entity->is_pc,
                'player_user_id' => $entity->player_user_id,
                'character_class' => $entity->character_class,
                'level' => $entity->level,
                'sheet_url' => $entity->sheet_url,
                'quest_status' => $entity->quest_status?->value,
                'giver_entity_id' => $entity->giver_entity_id,
                'tags' => $entity->tags->pluck('name')->values()->all(),
                'viewer_user_ids' => $entity->viewers->pluck('id')->values()->all(),
                'objectives' => $entity->objectives
                    ->map(fn (QuestObjective $objective) => [
                        'id' => $objective->id,
                        'position' => $objective->position,
                        'body' => $objective->body,
                        'completed_at' => $objective->completed_at?->toIso8601String(),
                        'completed_in_session_id' => $objective->completed_in_session_id,
                    ])->values()->all(),
                'markers' => $entity->markers
                    ->map(fn (MapMarker $marker) => [
                        'id' => $marker->id,
                        'target_entity_id' => $marker->target_entity_id,
                        'label' => $marker->label,
                        'x' => $marker->x,
                        'y' => $marker->y,
                        'player_visible' => $marker->player_visible,
                    ])->values()->all(),
                'image' => $this->media($entity->getFirstMedia('image')),
                // A handout's attachments, in the same shape as the single image: the
                // facts about each file and a URL, never the bytes. The zip with the
                // binaries in it is still the Markdown export.
                'files' => $entity->getMedia('files')
                    ->map(fn (Media $file) => $this->media($file))
                    ->values()->all(),
                'created_at' => $entity->created_at?->toIso8601String(),
                'updated_at' => $entity->updated_at?->toIso8601String(),
            ]);
    }

    /**
     * @return iterable<int, array<string, mixed>> A LazyCollection: it streams row by row.
     */
    private function sessions(Campaign $campaign): iterable
    {
        return GameSession::query()
            ->withoutGlobalScopes()
            ->where('campaign_id', $campaign->id)
            ->whereNull('deleted_at')
            ->with(['scenes', 'secrets', 'entities'])
            ->orderBy('number')
            ->cursor()
            ->map(fn (GameSession $session) => [
                'id' => $session->id,
                'number' => $session->number,
                'title' => $session->title,
                'scheduled_at' => $session->scheduled_at?->toIso8601String(),
                'status' => $session->status->value,
                'visibility' => $session->visibility->value,
                'strong_start' => $session->strong_start,
                'live_notes' => $session->live_notes,
                'recap' => $session->recap,
                'recap_published_at' => $session->recap_published_at?->toIso8601String(),
                'dm_notes' => $session->dm_notes,
                'scenes' => $session->scenes
                    ->map(fn (Scene $scene) => [
                        'id' => $scene->id,
                        'position' => $scene->position,
                        'title' => $scene->title,
                        'notes' => $scene->notes,
                    ])->values()->all(),
                'secrets' => $session->secrets
                    ->map(fn (Secret $secret) => [
                        'id' => $secret->id,
                        'position' => $secret->position,
                        'body' => $secret->body,
                        'revealed_at' => $secret->revealed_at?->toIso8601String(),
                        'revealed_in_session_id' => $secret->revealed_in_session_id,
                    ])->values()->all(),
                'prepped' => $session->entities
                    ->map(fn (Entity $entity) => [
                        'entity_id' => $entity->id,
                        'role' => $entity->pivot?->getAttribute('role'),
                        'position' => $entity->pivot?->getAttribute('position'),
                    ])->values()->all(),
                'created_at' => $session->created_at?->toIso8601String(),
                'updated_at' => $session->updated_at?->toIso8601String(),
            ]);
    }

    /**
     * @return iterable<int, array<string, mixed>> A LazyCollection: it streams row by row.
     */
    private function encounters(Campaign $campaign): iterable
    {
        return Encounter::query()
            ->withoutGlobalScopes()
            ->where('campaign_id', $campaign->id)
            ->with('combatants')
            ->orderBy('created_at')
            ->cursor()
            ->map(fn (Encounter $encounter) => [
                'id' => $encounter->id,
                'game_session_id' => $encounter->game_session_id,
                'name' => $encounter->name,
                'status' => $encounter->status->value,
                'round' => $encounter->round,
                'active_combatant_id' => $encounter->active_combatant_id,
                'combatants' => $encounter->combatants
                    ->map(fn (Combatant $combatant) => [
                        'id' => $combatant->id,
                        'entity_id' => $combatant->entity_id,
                        'name' => $combatant->name,
                        'initiative' => $combatant->initiative,
                        'initiative_bonus' => $combatant->initiative_bonus,
                        'hp' => $combatant->hp,
                        'max_hp' => $combatant->max_hp,
                        'ac' => $combatant->ac,
                        'conditions' => $combatant->conditions,
                        'position' => $combatant->position,
                        'player_visible' => $combatant->player_visible,
                    ])->values()->all(),
                'created_at' => $encounter->created_at?->toIso8601String(),
            ]);
    }

    /**
     * @return iterable<int, array<string, mixed>> A LazyCollection: it streams row by row.
     */
    private function randomTables(Campaign $campaign): iterable
    {
        return RandomTable::query()
            ->withoutGlobalScopes()
            ->where('campaign_id', $campaign->id)
            ->with('entries')
            ->orderBy('name')
            ->cursor()
            ->map(fn (RandomTable $table) => [
                'id' => $table->id,
                'name' => $table->name,
                'description' => $table->description,
                'entries' => $table->entries
                    ->map(fn (RandomTableEntry $entry) => [
                        'id' => $entry->id,
                        'position' => $entry->position,
                        'weight' => $entry->weight,
                        'body' => $entry->body,
                        'nested_table_id' => $entry->nested_table_id,
                    ])->values()->all(),
                'created_at' => $table->created_at?->toIso8601String(),
            ]);
    }

    /**
     * @return iterable<int, array<string, mixed>> A LazyCollection: it streams row by row.
     */
    private function diceRolls(Campaign $campaign): iterable
    {
        return DiceRoll::query()
            ->withoutGlobalScopes()
            ->where('campaign_id', $campaign->id)
            ->orderBy('created_at')
            ->cursor()
            ->map(fn (DiceRoll $roll) => [
                'id' => $roll->id,
                'game_session_id' => $roll->game_session_id,
                'user_id' => $roll->user_id,
                'formula' => $roll->formula,
                'label' => $roll->label,
                'total' => $roll->total,
                'detail' => $roll->detail,
                'private' => $roll->private,
                'rolled_at' => $roll->created_at?->toIso8601String(),
            ]);
    }

    /**
     * @return iterable<int, array<string, mixed>> A LazyCollection: it streams row by row.
     */
    private function clocks(Campaign $campaign): iterable
    {
        return Clock::query()
            ->withoutGlobalScopes()
            ->where('campaign_id', $campaign->id)
            ->orderBy('position')
            ->cursor()
            ->map(fn (Clock $clock) => [
                'id' => $clock->id,
                'entity_id' => $clock->entity_id,
                'name' => $clock->name,
                'segments' => $clock->segments,
                'filled' => $clock->filled,
                'player_visible' => $clock->player_visible,
                'position' => $clock->position,
                'created_at' => $clock->created_at?->toIso8601String(),
                'updated_at' => $clock->updated_at?->toIso8601String(),
            ]);
    }

    /**
     * A URL and the facts about the file, not the file. The zip with the binaries in
     * it is the Markdown export, which is P2.
     *
     * @return array<string, mixed>|null
     */
    private function media(?Media $media): ?array
    {
        if ($media === null) {
            return null;
        }

        return [
            'url' => $media->getUrl(),
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
        ];
    }
}
