<?php

namespace App\Actions\Campaigns;

/**
 * A read archive: the document it held, and where its media landed on this disk.
 *
 * The keys of $restored are archive entry names and the values are files this app
 * named. Nothing downstream ever turns a key into a path.
 */
final class ArchiveResult
{
    /**
     * @param  list<string>  $errors
     * @param  array<string, string>  $restored
     */
    private function __construct(
        public readonly array $errors,
        public readonly ?ReadResult $read,
        public readonly array $restored,
    ) {}

    /**
     * @param  array<string, string>  $restored
     */
    public static function ok(ReadResult $read, array $restored): self
    {
        return new self([], $read, $restored);
    }

    /**
     * @param  list<string>  $errors
     */
    public static function failed(array $errors): self
    {
        return new self($errors, null, []);
    }

    public function succeeded(): bool
    {
        return $this->errors === [] && $this->read !== null;
    }
}
