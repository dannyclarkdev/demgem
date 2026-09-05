<?php

namespace App\Livewire\Campaigns;

use App\Actions\Campaigns\ArchiveResult;
use App\Actions\Campaigns\ImportReport;
use App\Actions\Campaigns\ReadResult;

/**
 * One shape for the two things a GM can upload.
 *
 * The screen should not branch on which reader ran: a zip and a document produce the
 * same four answers, and the only difference a GM sees is how many files came with it.
 */
final class UploadReading
{
    /**
     * @param  list<string>  $errors
     * @param  array<string, mixed>  $document
     * @param  array<string, string>  $restored
     */
    private function __construct(
        public readonly array $errors,
        public readonly array $document,
        public readonly array $restored,
        public readonly ImportReport $report,
    ) {}

    public static function fromDocument(ReadResult $result): self
    {
        return new self($result->errors, $result->document, [], $result->report);
    }

    public static function fromArchive(ArchiveResult $result): self
    {
        if ($result->read === null) {
            return new self($result->errors, [], [], new ImportReport);
        }

        return new self($result->errors, $result->read->document, $result->restored, $result->read->report);
    }

    public function succeeded(): bool
    {
        return $this->errors === [];
    }
}
