<?php

namespace App\Actions\Campaigns;

/**
 * Either a document this install can build a campaign from, or the reasons it cannot.
 *
 * Never both. A file with one error is refused whole, because a partly-read campaign
 * is the thing the two-pass design exists to avoid.
 */
final class ReadResult
{
    /**
     * @param  list<string>  $errors
     * @param  array<string, mixed>  $document
     */
    private function __construct(
        public readonly array $errors,
        public readonly array $document,
        public readonly ImportReport $report,
    ) {}

    /**
     * @param  array<string, mixed>  $document
     */
    public static function ok(array $document, ImportReport $report): self
    {
        return new self([], $document, $report);
    }

    /**
     * @param  list<string>  $errors
     */
    public static function failed(array $errors): self
    {
        return new self($errors, [], new ImportReport);
    }

    public function succeeded(): bool
    {
        return $this->errors === [];
    }
}
