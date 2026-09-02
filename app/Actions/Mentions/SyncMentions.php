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
     * @param  array<string, string|null>  $fields  field name => markdown
     */
    public function handle(Model $source, string $campaignId, array $fields): void
    {
        DB::transaction(function () use ($source, $campaignId, $fields): void {
            Mention::withoutGlobalScopes()
                ->where('source_type', $source->getMorphClass())
                ->where('source_id', $source->getKey())
                ->delete();

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

            if ($rows !== []) {
                Mention::insert($rows);
            }
        });
    }
}
