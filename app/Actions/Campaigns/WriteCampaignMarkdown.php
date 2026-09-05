<?php

namespace App\Actions\Campaigns;

use App\Models\Campaign;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\QuestObjective;
use App\Models\RandomTable;
use App\Models\RandomTableEntry;
use App\Models\Scene;
use App\Models\Secret;

/**
 * The campaign as a folder of Markdown, for Obsidian and for reading.
 *
 * It is one-way on purpose. Nothing in demgem reads this back: parsing a vault means
 * guessing which files are ours, what to do with one a person renamed, and how to
 * resolve a [[link]] whose target moved. That is a plan, not a phase, and the
 * README in the archive says so.
 *
 * The wiki links are left exactly as they were written. [[The Salt Cathedral]] means
 * the same thing in demgem and in Obsidian, which was true by accident of taste and
 * is worth something now.
 */
class WriteCampaignMarkdown
{
    /**
     * @return array<string, string> archive entry => file contents
     */
    public function handle(Campaign $campaign): array
    {
        $files = [];

        $entities = Entity::withoutGlobalScopes()
            ->where('campaign_id', $campaign->id)
            ->whereNull('deleted_at')
            ->with(['tags', 'parent', 'objectives'])
            ->orderBy('name')
            ->get();

        foreach ($entities as $entity) {
            $files['markdown/'.$entity->type->slug().'/'.$entity->slug.'.md'] = $this->entity($entity);
        }

        $sessions = GameSession::withoutGlobalScopes()
            ->where('campaign_id', $campaign->id)
            ->whereNull('deleted_at')
            ->with(['scenes', 'secrets'])
            ->orderBy('number')
            ->get();

        foreach ($sessions as $session) {
            // Numbered, so a folder listing is in play order rather than alphabetical.
            $slug = str((string) $session->title)->slug()->limit(60, '')->value();

            $files[sprintf('markdown/sessions/%02d-%s.md', $session->number, $slug !== '' ? $slug : 'session')] = $this->session($session);
        }

        $tables = RandomTable::withoutGlobalScopes()
            ->where('campaign_id', $campaign->id)
            ->with('entries')
            ->orderBy('name')
            ->get();

        foreach ($tables as $table) {
            $slug = str($table->name)->slug()->limit(60, '')->value();

            $files['markdown/tables/'.($slug !== '' ? $slug : 'table').'.md'] = $this->table($table);
        }

        return $files;
    }

    private function entity(Entity $entity): string
    {
        $matter = [
            'name' => $entity->name,
            'type' => $entity->type->value,
            'visibility' => $entity->visibility->value,
            'demgem' => 'entity',
        ];

        if ($entity->parent !== null) {
            $matter['parent'] = $entity->parent->name;
        }

        if ($entity->quest_status !== null) {
            $matter['status'] = $entity->quest_status->value;
        }

        if (filled($entity->character_class)) {
            $matter['class'] = (string) $entity->character_class;
        }

        if ($entity->level !== null) {
            $matter['level'] = (string) $entity->level;
        }

        $body = [$entity->body];

        if ($entity->objectives->isNotEmpty()) {
            $body[] = "## Objectives\n\n".$entity->objectives
                ->map(fn (QuestObjective $objective) => '- ['.($objective->completed_at !== null ? 'x' : ' ').'] '.$objective->body)
                ->implode("\n");
        }

        $body[] = $this->section('Rewards', $entity->rewards);
        $body[] = $this->section('GM notes', $entity->dm_notes);

        return $this->frontMatter($matter, $entity->tags->pluck('name')->all())."\n".$this->body($body);
    }

    private function session(GameSession $session): string
    {
        $matter = [
            'name' => $session->title ?? 'Session '.$session->number,
            'type' => 'session',
            'number' => (string) $session->number,
            'status' => $session->status->value,
            'visibility' => $session->visibility->value,
            'demgem' => 'session',
        ];

        if ($session->scheduled_at !== null) {
            $matter['scheduled'] = $session->scheduled_at->toIso8601String();
        }

        $body = [
            $this->section('Recap', $session->recap),
            $this->section('Strong start', $session->strong_start),
        ];

        if ($session->scenes->isNotEmpty()) {
            $body[] = "## Scenes\n\n".$session->scenes
                ->map(fn (Scene $scene) => '### '.$scene->title.(filled($scene->notes) ? "\n\n".$scene->notes : ''))
                ->implode("\n\n");
        }

        if ($session->secrets->isNotEmpty()) {
            $body[] = "## Secrets and clues\n\n".$session->secrets
                ->map(fn (Secret $secret) => '- '.($secret->revealed_at !== null ? '~~'.$secret->body.'~~' : $secret->body))
                ->implode("\n");
        }

        $body[] = $this->section('Live notes', $session->live_notes);
        $body[] = $this->section('GM notes', $session->dm_notes);

        return $this->frontMatter($matter, [])."\n".$this->body($body);
    }

    private function table(RandomTable $table): string
    {
        $matter = ['name' => $table->name, 'type' => 'table', 'demgem' => 'table'];

        $rows = $table->entries
            ->map(fn (RandomTableEntry $entry) => '- '.$entry->body.($entry->weight > 1 ? ' *(weight '.$entry->weight.')*' : ''))
            ->implode("\n");

        return $this->frontMatter($matter, [])."\n".$this->body([$table->description, $rows]);
    }

    private function section(string $heading, ?string $prose): ?string
    {
        return filled($prose) ? '## '.$heading."\n\n".$prose : null;
    }

    /**
     * @param  list<string|null>  $parts
     */
    private function body(array $parts): string
    {
        return implode("\n\n", array_filter($parts, fn (?string $part) => filled($part)))."\n";
    }

    /**
     * Every value is quoted, always.
     *
     * That is the whole rule, and it is a rule rather than a judgement so there is
     * nothing to get wrong on a name holding a colon, a `#`, a quote or a leading
     * dash. YAML accepts a double-quoted scalar everywhere a bare one is allowed, so
     * quoting unconditionally costs nothing and removes the entire class of question.
     *
     * @param  array<string, string>  $matter
     * @param  list<string>  $tags
     */
    private function frontMatter(array $matter, array $tags): string
    {
        $lines = ['---'];

        foreach ($matter as $key => $value) {
            $lines[] = $key.': '.$this->quote($value);
        }

        if ($tags !== []) {
            $lines[] = 'tags: ['.implode(', ', array_map(fn (string $tag) => $this->quote($tag), $tags)).']';
        }

        $lines[] = '---';

        return implode("\n", $lines)."\n";
    }

    private function quote(string $value): string
    {
        return '"'.str_replace(['\\', '"', "\n"], ['\\\\', '\"', ' '], $value).'"';
    }
}
