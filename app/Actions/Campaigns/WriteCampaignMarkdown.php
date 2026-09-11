<?php

namespace App\Actions\Campaigns;

use App\Models\Campaign;
use App\Models\Decision;
use App\Models\Entity;
use App\Models\EntityRelation;
use App\Models\GameSession;
use App\Models\LedgerEntry;
use App\Models\QuestObjective;
use App\Models\RandomTable;
use App\Models\RandomTableEntry;
use App\Models\ReputationChange;
use App\Models\Scene;
use App\Models\Secret;
use App\Models\SessionRsvp;
use App\Support\Reckoning\Reckoning;
use App\Support\Reputation\Standing;
use Illuminate\Support\Collection;

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
     * The world's reckoning, for printing dates. Set per handle() call, because the
     * writer is resolved from the container and a campaign's calendar is its own.
     */
    private ?Reckoning $reckoning = null;

    /**
     * @return array<string, string> archive entry => file contents
     */
    public function handle(Campaign $campaign): array
    {
        $files = [];

        $this->reckoning = $campaign->calendar?->reckoning();

        $entities = Entity::withoutGlobalScopes()
            ->where('campaign_id', $campaign->id)
            ->whereNull('deleted_at')
            ->with(['tags', 'parent', 'arc', 'player', 'objectives', 'relations.target', 'incomingRelations.source', 'reputationChanges.gameSession'])
            ->orderBy('name')
            ->get();

        foreach ($entities as $entity) {
            $files['markdown/'.$entity->type->slug().'/'.$entity->slug.'.md'] = $this->entity($entity);
        }

        $sessions = GameSession::withoutGlobalScopes()
            ->where('campaign_id', $campaign->id)
            ->whereNull('deleted_at')
            ->with(['scenes', 'secrets', 'rsvps.user', 'arc'])
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

        $decisions = Decision::withoutGlobalScopes()
            ->where('campaign_id', $campaign->id)
            ->with('gameSession')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        // One page for the whole log rather than a file per row: a decision is a
        // sentence or two, and the log reads as a list. Hidden rows are written too,
        // as everywhere in the vault, and each says so.
        if ($decisions->isNotEmpty()) {
            $files['markdown/decisions.md'] = $this->decisions($decisions);
        }

        $ledger = LedgerEntry::withoutGlobalScopes()
            ->where('campaign_id', $campaign->id)
            ->with('gameSession')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        if ($ledger->isNotEmpty()) {
            $files['markdown/ledger.md'] = $this->ledger($ledger, $campaign->currency);
        }

        return $files;
    }

    /**
     * The purse, the pack, and every movement, oldest first: the same three things the
     * page shows, in the order a reader wants them.
     *
     * @param  Collection<int, LedgerEntry>  $ledger
     */
    private function ledger(Collection $ledger, string $currency): string
    {
        $matter = ['name' => 'Ledger', 'type' => 'ledger', 'currency' => $currency, 'demgem' => 'ledger'];

        $balance = (float) $ledger->filter(fn (LedgerEntry $row) => $row->isCoin())->sum('amount');

        $pack = collect(LedgerEntry::inventory($ledger))
            ->map(fn (array $line) => '- '.$line['quantity'].' × '.$line['name'])
            ->implode("\n");

        $rows = $ledger->map(function (LedgerEntry $row) use ($currency): string {
            $what = $row->isCoin()
                ? $row->signedAmount().' '.$currency
                : (($row->quantity ?? 0) < 0 ? '−' : '+').abs($row->quantity ?? 0).' '.$row->item_name;
            $where = $row->gameSession !== null ? ' *('.$row->gameSession->label().')*' : '';
            $note = filled($row->note) ? ' — '.$row->note : '';

            return '- '.$what.$note.$where;
        })->implode("\n");

        return $this->frontMatter($matter, [])."\n".$this->body([
            '## In the purse'."\n\n".number_format($balance, 2).' '.$currency,
            $pack !== '' ? "## In the pack\n\n".$pack : null,
            "## Movements\n\n".$rows,
        ]);
    }

    /**
     * @param  Collection<int, Decision>  $decisions
     */
    private function decisions(Collection $decisions): string
    {
        $matter = ['name' => 'Decisions', 'type' => 'decisions', 'demgem' => 'decisions'];

        $rows = $decisions->map(function (Decision $decision): string {
            $where = $decision->gameSession !== null ? ' *('.$decision->gameSession->label().')*' : '';
            $eye = $decision->player_visible ? '' : ' *(GM only)*';
            $line = '- '.$decision->choice.$where.$eye;

            if ($decision->hasConsequence()) {
                $line .= "\n  - ".$decision->consequence;
            }

            return $line;
        })->implode("\n");

        return $this->frontMatter($matter, [])."\n".$this->body([$rows]);
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

        if ($entity->arc !== null) {
            $matter['arc'] = $entity->arc->name;
        }

        if ($entity->isJournal() && $entity->player !== null) {
            $matter['author'] = $entity->player->name;
        }

        if ($entity->isFaction() && $entity->reputationChanges->isNotEmpty()) {
            $matter['standing'] = (new Standing((int) $entity->reputationChanges->sum('delta')))->signed();
        }

        if (filled($entity->character_class)) {
            $matter['class'] = (string) $entity->character_class;
        }

        if ($entity->level !== null) {
            $matter['level'] = (string) $entity->level;
        }

        if ($entity->happens_on !== null && $this->reckoning !== null) {
            $matter['happens_on'] = $this->reckoning->format($entity->happens_on);
        }

        $body = [$entity->body];

        if ($entity->objectives->isNotEmpty()) {
            $body[] = "## Objectives\n\n".$entity->objectives
                ->map(fn (QuestObjective $objective) => '- ['.($objective->completed_at !== null ? 'x' : ' ').'] '.$objective->body)
                ->implode("\n");
        }

        // Both directions as wiki links, so Obsidian's graph draws the line. Hidden
        // rows are written too: the vault is the GM's own export, and the front
        // matter already says which pages are GM-only.
        $relationships = $entity->relations
            ->map(fn (EntityRelation $relation) => '- '.$relation->label.' [['.$relation->target->name.']]')
            ->concat($entity->incomingRelations->map(fn (EntityRelation $relation) => $relation->reverse_label !== null
                ? '- '.$relation->reverse_label.' [['.$relation->source->name.']]'
                : '- [['.$relation->source->name.']] · '.$relation->label))
            ->implode("\n");

        if ($relationships !== '') {
            $body[] = "## Relationships\n\n".$relationships;
        }

        // The moments, oldest first. Hidden rows are written too, and each says so.
        if ($entity->isFaction() && $entity->reputationChanges->isNotEmpty()) {
            $body[] = "## Standing with the party\n\n".$entity->reputationChanges
                ->sortBy('created_at')
                ->map(fn (ReputationChange $change) => '- '.$change->signedDelta()
                    .(filled($change->reason) ? ' '.$change->reason : '')
                    .($change->gameSession !== null ? ' *('.$change->gameSession->label().')*' : '')
                    .($change->player_visible ? '' : ' *(GM only)*'))
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

        if ($session->in_game_start !== null && $this->reckoning !== null) {
            $matter['in_game_start'] = $this->reckoning->format($session->in_game_start);
        }

        if ($session->in_game_end !== null && $this->reckoning !== null) {
            $matter['in_game_end'] = $this->reckoning->format($session->in_game_end);
        }

        if ($session->arc !== null) {
            $matter['arc'] = $session->arc->name;
        }

        if ($session->xp_awarded !== null) {
            $matter['xp_awarded'] = (string) $session->xp_awarded;
        }

        if (filled($session->milestone)) {
            $matter['milestone'] = (string) $session->milestone;
        }

        $attended = $session->rsvps
            ->filter(fn (SessionRsvp $row) => $row->wasThere())
            ->map(fn (SessionRsvp $row) => $row->user->name)
            ->sort()
            ->values()
            ->all();

        if ($attended !== []) {
            $matter['attended'] = $attended;
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
     * @param  array<string, string|list<string>>  $matter
     * @param  list<string>  $tags
     */
    private function frontMatter(array $matter, array $tags): string
    {
        $lines = ['---'];

        foreach ($matter as $key => $value) {
            $lines[] = $key.': '.(is_array($value) ? $this->quoteList($value) : $this->quote($value));
        }

        if ($tags !== []) {
            $lines[] = 'tags: '.$this->quoteList($tags);
        }

        $lines[] = '---';

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  list<string>  $values
     */
    private function quoteList(array $values): string
    {
        return '['.implode(', ', array_map(fn (string $value) => $this->quote($value), $values)).']';
    }

    private function quote(string $value): string
    {
        return '"'.str_replace(['\\', '"', "\n"], ['\\\\', '\"', ' '], $value).'"';
    }
}
