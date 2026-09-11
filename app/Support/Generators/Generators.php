<?php

namespace App\Support\Generators;

use Illuminate\Support\Collection;
use RuntimeException;

/**
 * The shipped table sets. Reads database/generators once and checks each file's
 * shape, so a typo in a JSON file fails loudly here rather than as a half-installed
 * set in somebody's campaign.
 *
 * @phpstan-import-type Table from GeneratorSet
 * @phpstan-import-type Entry from GeneratorSet
 */
final class Generators
{
    public const MAX_KEY_LENGTH = 40;

    public const MAX_BODY_LENGTH = 300;

    /** @var Collection<string, GeneratorSet>|null */
    private ?Collection $sets = null;

    public function __construct(private readonly string $directory) {}

    /**
     * @return Collection<string, GeneratorSet> keyed by set key, in the order the files sort
     */
    public function all(): Collection
    {
        if ($this->sets !== null) {
            return $this->sets;
        }

        $files = glob($this->directory.'/*.json') ?: [];
        sort($files);

        /** @var Collection<string, GeneratorSet> $sets */
        $sets = new Collection;

        foreach ($files as $file) {
            $set = $this->read($file);
            $sets[$set->key] = $set;
        }

        return $this->sets = $sets;
    }

    public function find(string $key): ?GeneratorSet
    {
        return $this->all()->get($key);
    }

    private function read(string $file): GeneratorSet
    {
        $decoded = json_decode((string) file_get_contents($file), true);

        if (! is_array($decoded)) {
            throw new RuntimeException("Generator file {$file} is not valid JSON.");
        }

        $key = $this->string($decoded, 'key', $file, self::MAX_KEY_LENGTH);

        if (preg_match('/^[a-z0-9-]+$/', $key) !== 1) {
            throw new RuntimeException("Generator file {$file} has a key that is not a slug.");
        }

        $tables = [];
        $slugs = [];

        foreach ($this->list($decoded, 'tables', $file) as $index => $table) {
            if (! is_array($table)) {
                throw new RuntimeException("Generator {$key}: table ".($index + 1).' is not an object.');
            }

            $slug = $this->string($table, 'slug', $file, 80);

            if (isset($slugs[$slug])) {
                throw new RuntimeException("Generator {$key}: two tables share the slug {$slug}.");
            }

            $slugs[$slug] = true;

            $entries = [];

            foreach ($this->list($table, 'entries', $file) as $entry) {
                if (! is_array($entry)) {
                    throw new RuntimeException("Generator {$key}: an entry of {$slug} is not an object.");
                }

                $entries[] = [
                    'body' => $this->string($entry, 'body', $file, self::MAX_BODY_LENGTH),
                    'weight' => max(1, (int) ($entry['weight'] ?? 1)),
                    'nested' => isset($entry['nested']) && is_string($entry['nested']) && $entry['nested'] !== '' ? $entry['nested'] : null,
                ];
            }

            if ($entries === []) {
                throw new RuntimeException("Generator {$key}: the table {$slug} has no entries.");
            }

            $tables[] = [
                'slug' => $slug,
                'name' => $this->string($table, 'name', $file, 120),
                'description' => isset($table['description']) && is_string($table['description']) && trim($table['description']) !== ''
                    ? mb_substr(trim($table['description']), 0, 240)
                    : null,
                'entries' => $entries,
            ];
        }

        if ($tables === []) {
            throw new RuntimeException("Generator {$key} has no tables.");
        }

        // Every nested slug names a table in the same set, or the chain would break
        // the moment it was rolled.
        foreach ($tables as $table) {
            foreach ($table['entries'] as $entry) {
                if ($entry['nested'] !== null && ! isset($slugs[$entry['nested']])) {
                    throw new RuntimeException("Generator {$key}: {$table['slug']} nests {$entry['nested']}, which the set does not have.");
                }
            }
        }

        return new GeneratorSet(
            $key,
            $this->string($decoded, 'name', $file, 120),
            $this->string($decoded, 'description', $file, 240),
            $tables,
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function string(array $row, string $key, string $file, int $max): string
    {
        $value = $row[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException("Generator file {$file} is missing \"{$key}\".");
        }

        if (mb_strlen(trim($value)) > $max) {
            throw new RuntimeException("Generator file {$file}: \"{$key}\" is longer than {$max} characters.");
        }

        return trim($value);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<mixed>
     */
    private function list(array $row, string $key, string $file): array
    {
        $value = $row[$key] ?? null;

        if (! is_array($value) || ! array_is_list($value)) {
            throw new RuntimeException("Generator file {$file}: \"{$key}\" is not a list.");
        }

        return $value;
    }
}
