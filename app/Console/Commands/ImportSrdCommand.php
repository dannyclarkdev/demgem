<?php

namespace App\Console\Commands;

use App\Models\StatBlock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of stat_blocks.
 *
 * It is idempotent on (ruleset, slug) among the shipped rows, so running it twice
 * changes no ids and a combatant that points at a stat block keeps pointing at the same
 * one. Every query here is scoped with shipped(): a campaign's own creatures share the
 * table since slice 17 and are none of this command's business. It never
 * deletes: removing reference data under a live campaign's fight is a surprise, so a
 * row the new file no longer names is reported and left alone.
 *
 * The checksum is checked before a row is read. The dataset is licensed material with
 * a recorded provenance (database/srd/README.md), and a file that no longer matches
 * the configuration is a file nobody has vouched for.
 */
class ImportSrdCommand extends Command
{
    protected $signature = 'demgem:import-srd
        {--check : Validate the dataset and report without writing}';

    protected $description = 'Load the shipped SRD creature dataset into the compendium';

    public function handle(): int
    {
        $path = (string) config('compendium.dataset');

        if (! is_file($path) || ! is_readable($path)) {
            $this->components->error("No readable dataset at {$path}.");

            return self::FAILURE;
        }

        $expected = (string) config('compendium.dataset_sha256');
        $actual = hash_file('sha256', $path);

        if ($expected !== $actual) {
            $this->components->error('The dataset does not match its checksum.');
            $this->components->bulletList([
                "expected: {$expected}",
                "on disk:  {$actual}",
                'Update compendium.dataset_sha256 in the same commit as the data, and say where the file came from in database/srd/README.md.',
            ]);

            return self::FAILURE;
        }

        $document = json_decode((string) file_get_contents($path), true);

        if (! is_array($document)) {
            $this->components->error('The dataset is not a JSON document.');

            return self::FAILURE;
        }

        /** @var array<int, array<string, mixed>>|mixed $creatures */
        $creatures = $document['creatures'] ?? null;

        if (! is_array($creatures)) {
            $this->components->error('The dataset names no creatures list.');

            return self::FAILURE;
        }

        $ruleset = (string) ($document['ruleset'] ?? '');
        $source = (string) ($document['source'] ?? '');
        $license = (string) ($document['license'] ?? '');

        if ($ruleset === '' || $source === '' || $license === '') {
            $this->components->error('The dataset must name its ruleset, source and license.');

            return self::FAILURE;
        }

        $this->components->info("{$source}, {$license}: ".count($creatures).' creatures.');

        if ($this->option('check')) {
            $this->components->info('Checked only. Nothing was written.');

            return self::SUCCESS;
        }

        // shipped(), not just the ruleset. Since slice 17 a campaign's own creatures
        // live in this table too, and without this the loader would update one whose
        // slug matched, or report it as a row the dataset no longer names.
        $existing = StatBlock::query()
            ->shipped()
            ->forRuleset($ruleset)
            ->pluck('id', 'slug');

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($creatures, $ruleset, $source, $license, $existing, &$created, &$updated): void {
            foreach ($creatures as $creature) {
                $slug = (string) ($creature['slug'] ?? '');

                if ($slug === '') {
                    continue;
                }

                $attributes = $creature;
                unset($attributes['slug']);

                $attributes['ruleset'] = $ruleset;
                $attributes['source'] = $source;
                $attributes['license'] = $license;
                $attributes['legendary_action_uses'] = $this->legendaryActionUses($creature);

                if ($existing->has($slug)) {
                    StatBlock::query()->shipped()->whereKey($existing->get($slug))->update($attributes);
                    $updated++;

                    continue;
                }

                StatBlock::query()->create($attributes + ['slug' => $slug, 'campaign_id' => null]);
                $created++;
            }
        });

        $named = array_filter(array_column($creatures, 'slug'));
        $orphaned = $existing->keys()->diff($named);

        $this->components->twoColumnDetail('created', (string) $created);
        $this->components->twoColumnDetail('updated', (string) $updated);

        if ($orphaned->isNotEmpty()) {
            $this->components->warn($orphaned->count().' row(s) the dataset no longer names were left in place:');
            $this->components->bulletList($orphaned->all());
        }

        return self::SUCCESS;
    }

    /**
     * How many legendary actions a creature gets in a round.
     *
     * The dataset already ships the number inside the prose it prints above the list:
     * "Legendary Action Uses: 3 (4 in Lair)". Reading it here rather than adding a
     * field to the data file means the file and its checksum do not move, and the
     * loader stays the only place that knows the document's shape.
     *
     * The lair number beside it is not stored. demgem has no concept of a lair, and a
     * GM raises the count on the row when the fight is in one.
     *
     * Every one of the thirty creatures with legendary actions states this, so a null
     * here means the parse missed rather than that the creature gets none. The tracker
     * treats that the same way it treats a hand-typed row: the GM types the number.
     *
     * @param  array<string, mixed>  $creature
     */
    private function legendaryActionUses(array $creature): ?int
    {
        $actions = $creature['legendary_actions'] ?? null;

        if (! is_array($actions)) {
            return null;
        }

        foreach ($actions as $action) {
            if (! is_array($action) || ! is_string($action['text'] ?? null)) {
                continue;
            }

            if (preg_match('/Legendary Action Uses:\s*(\d+)/i', $action['text'], $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return null;
    }
}
