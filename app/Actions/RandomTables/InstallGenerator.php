<?php

namespace App\Actions\RandomTables;

use App\Models\Campaign;
use App\Models\RandomTable;
use App\Models\User;
use App\Support\Generators\GeneratorSet;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InstallGenerator
{
    /**
     * One set into one campaign: every table, every entry, the nested slugs resolved
     * to the new ids, and every table stamped with the set's key. One transaction,
     * so a set is in whole or not at all.
     *
     * A table whose name the campaign already uses is named "<name> (generator)",
     * because random_tables is unique on (campaign_id, name) and a GM who wrote their
     * own "Weather" should keep it. The set still nests into the renamed table by id.
     *
     * @return Collection<int, RandomTable> the tables made, in the set's order
     */
    public function handle(Campaign $campaign, User $actor, GeneratorSet $set): Collection
    {
        if (self::isInstalled($campaign, $set)) {
            throw new InvalidArgumentException("The {$set->name} set is already in this campaign.");
        }

        return DB::transaction(function () use ($campaign, $actor, $set): Collection {
            $taken = RandomTable::withoutGlobalScopes()
                ->where('campaign_id', $campaign->id)
                ->pluck('name')
                ->map(fn (string $name) => mb_strtolower($name))
                ->flip()
                ->all();

            /** @var array<string, RandomTable> $bySlug */
            $bySlug = [];

            foreach ($set->tables as $table) {
                $name = isset($taken[mb_strtolower($table['name'])]) ? $table['name'].' (generator)' : $table['name'];
                $taken[mb_strtolower($name)] = true;

                $bySlug[$table['slug']] = RandomTable::create([
                    'campaign_id' => $campaign->id,
                    'name' => $name,
                    'description' => $table['description'],
                    'generator_key' => $set->key,
                    'created_by' => $actor->id,
                ]);
            }

            foreach ($set->tables as $table) {
                foreach ($table['entries'] as $position => $entry) {
                    $bySlug[$table['slug']]->entries()->create([
                        'campaign_id' => $campaign->id,
                        'position' => $position,
                        'weight' => $entry['weight'],
                        'body' => $entry['body'],
                        'nested_table_id' => $entry['nested'] === null ? null : $bySlug[$entry['nested']]->id,
                    ]);
                }
            }

            return collect(array_values($bySlug));
        });
    }

    /**
     * A set is in while any table carries its key. A GM who pruned a set is a GM
     * who is using it, so one deleted table does not make the set offer itself again.
     */
    public static function isInstalled(Campaign $campaign, GeneratorSet $set): bool
    {
        return RandomTable::withoutGlobalScopes()
            ->where('campaign_id', $campaign->id)
            ->where('generator_key', $set->key)
            ->exists();
    }
}
