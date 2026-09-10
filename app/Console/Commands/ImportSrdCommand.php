<?php

namespace App\Console\Commands;

use App\Models\StatBlock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of stat_blocks.
 *
 * It is idempotent on (ruleset, slug), so running it twice changes no ids and a
 * combatant that points at a stat block keeps pointing at the same one. It never
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

        $existing = StatBlock::query()
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

                if ($existing->has($slug)) {
                    StatBlock::query()->whereKey($existing->get($slug))->update($attributes);
                    $updated++;

                    continue;
                }

                StatBlock::query()->create($attributes + ['slug' => $slug]);
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
}
