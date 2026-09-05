<?php

namespace App\Actions\Campaigns;

use Illuminate\Support\Str;
use RuntimeException;

/**
 * The file's ids to this install's ids.
 *
 * Every row in an imported campaign gets a fresh ULID, always, even when nothing
 * would have collided. Reuse works right up until a GM restores their own export
 * into the install it came from, which is the most likely restore there is.
 *
 * newFor() throws rather than returning null. A reference that reached the writer
 * unresolved is a hole in the reader, and a null written into a foreign key is a
 * quiet wrong answer where an exception is a loud one.
 */
final class IdMap
{
    /** @var array<string, string> */
    private array $map = [];

    /**
     * Mints a new id for a row and remembers it. Calling it twice for the same old
     * id returns the same new one, so an order-of-writes mistake cannot fork a row.
     */
    public function remember(string $oldId): string
    {
        return $this->map[$oldId] ??= (string) Str::ulid();
    }

    public function newFor(string $oldId): string
    {
        return $this->map[$oldId] ?? throw new RuntimeException("The import found no id for {$oldId}.");
    }

    public function newForNullable(?string $oldId): ?string
    {
        return $oldId === null ? null : $this->newFor($oldId);
    }

    public function has(string $oldId): bool
    {
        return isset($this->map[$oldId]);
    }

    public function count(): int
    {
        return count($this->map);
    }
}
