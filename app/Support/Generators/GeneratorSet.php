<?php

namespace App\Support\Generators;

/**
 * One shipped set, read from database/generators/<key>.json and checked for shape.
 *
 * @phpstan-type Entry array{body: string, weight: int, nested: string|null}
 * @phpstan-type Table array{slug: string, name: string, description: string|null, entries: list<Entry>}
 */
final class GeneratorSet
{
    /**
     * @param  list<Table>  $tables
     */
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly string $description,
        public readonly array $tables,
    ) {}

    public function tableCount(): int
    {
        return count($this->tables);
    }

    public function entryCount(): int
    {
        return array_sum(array_map(fn (array $table) => count($table['entries']), $this->tables));
    }
}
