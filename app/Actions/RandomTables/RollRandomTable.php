<?php

namespace App\Actions\RandomTables;

use App\Models\RandomTable;
use App\Models\RandomTableEntry;
use Random\Randomizer;

class RollRandomTable
{
    public const MAX_DEPTH = 5;

    public function __construct(private readonly Randomizer $randomizer) {}

    /**
     * Rolls a table, following any nested table an entry points at.
     *
     * The result is a chain rather than one row, because "a rumour about a person named
     * X" is two rolls and the GM wants both. Nothing is persisted: dice_rolls is for
     * dice, and a table result is prose the GM either uses or discards. The component
     * keeps the last few, and anything worth keeping goes into the live notes.
     *
     * @param  list<string>  $visited  Table ids already rolled in this chain.
     * @return list<array{table: string, roll: int|null, entry: RandomTableEntry|null, note: string|null}>
     */
    public function handle(RandomTable $table, array $visited = []): array
    {
        if (in_array($table->id, $visited, true)) {
            return [[
                'table' => $table->name,
                'roll' => null,
                'entry' => null,
                'note' => "Stopped: {$table->name} nests back into itself.",
            ]];
        }

        if (count($visited) >= self::MAX_DEPTH) {
            return [[
                'table' => $table->name,
                'roll' => null,
                'entry' => null,
                'note' => 'Stopped after '.self::MAX_DEPTH.' nested tables.',
            ]];
        }

        $entries = $table->entries()->with('nestedTable')->get();
        $total = (int) $entries->sum('weight');

        if ($entries->isEmpty() || $total <= 0) {
            return [[
                'table' => $table->name,
                'roll' => null,
                'entry' => null,
                'note' => "{$table->name} has nothing in it yet.",
            ]];
        }

        $roll = $this->randomizer->getInt(1, $total);
        $cursor = 0;
        $chosen = $entries->last();

        foreach ($entries as $entry) {
            $cursor += max(1, $entry->weight);

            if ($roll <= $cursor) {
                $chosen = $entry;

                break;
            }
        }

        $results = [[
            'table' => $table->name,
            'roll' => $roll,
            'entry' => $chosen,
            'note' => null,
        ]];

        if ($chosen->nestedTable !== null) {
            return [...$results, ...$this->handle($chosen->nestedTable, [...$visited, $table->id])];
        }

        return $results;
    }
}
