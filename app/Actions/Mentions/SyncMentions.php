<?php

namespace App\Actions\Mentions;

use App\Markdown\LinkResolver;
use App\Markdown\WikiLinkScanner;
use App\Models\Mention;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncMentions
{
    public function __construct(private readonly WikiLinkScanner $scanner) {}

    /**
     * Rebuilds the outbound mention rows of one source from its Markdown fields.
     *
     * Live notes autosave every couple of seconds while a GM types, and most of that
     * typing is prose, not links. So build the rows first and return before writing
     * anything when they match what is already stored.
     *
     * @param  array<string, string|null>  $fields  field name => markdown
     */
    public function handle(Model $source, string $campaignId, array $fields): void
    {
        $rows = $this->rows($source, $campaignId, $fields);

        if ($this->matchesStoredRows($source, $rows)) {
            return;
        }

        DB::transaction(function () use ($source, $rows): void {
            Mention::withoutGlobalScopes()
                ->where('source_type', $source->getMorphClass())
                ->where('source_id', $source->getKey())
                ->delete();

            if ($rows !== []) {
                Mention::insert($rows);
            }
        });
    }

    /**
     * @param  array<string, string|null>  $fields
     * @return list<array<string, mixed>>
     */
    private function rows(Model $source, string $campaignId, array $fields): array
    {
        $resolver = new LinkResolver($campaignId);
        $rows = [];
        $seen = [];

        foreach ($fields as $field => $markdown) {
            $tokens = $this->scanner->scan($markdown);
            $resolver->preload($tokens);

            foreach ($tokens as $token) {
                $key = $field.'|'.($token->type !== null ? $token->type->value : '').'|'.mb_strtolower($token->name);

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;

                $rows[] = [
                    'campaign_id' => $campaignId,
                    'source_type' => $source->getMorphClass(),
                    'source_id' => $source->getKey(),
                    'source_field' => $field,
                    'target_entity_id' => $resolver->resolve($token->name, $token->type)?->id,
                    'target_name' => Str::limit($token->name, 120, ''),
                    'target_type' => $token->type?->value,
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function matchesStoredRows(Model $source, array $rows): bool
    {
        $stored = Mention::withoutGlobalScopes()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->get(['source_field', 'target_entity_id', 'target_name', 'target_type'])
            ->map(fn (Mention $mention) => implode('|', [
                $mention->source_field,
                $mention->target_entity_id ?? '',
                $mention->target_name,
                $mention->target_type ?? '',
            ]))
            ->sort()
            ->values()
            ->all();

        $built = collect($rows)
            ->map(fn (array $row) => implode('|', [
                $row['source_field'],
                $row['target_entity_id'] ?? '',
                $row['target_name'],
                $row['target_type'] ?? '',
            ]))
            ->sort()
            ->values()
            ->all();

        return $stored === $built;
    }
}
