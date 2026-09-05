<?php

namespace App\Actions\Campaigns;

use App\Models\Entity;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Reads a campaign archive: the most dangerous input this app takes.
 *
 * The rule the whole class rests on is one sentence. **No string from an archive is
 * ever used as a path.** extractTo() is never called, an entry name is never joined
 * onto a directory, and every file this writes is named by Str::ulid(). An entry
 * called ../../../.env is therefore not dangerous — it is an entry nothing asks for,
 * because the only entries read are the ones campaign.json already named and this
 * class has already measured.
 *
 * The three risks that survive that rule get numbers rather than hopes:
 *
 * - Decompressed size. statName() reports it before a byte is read, and the sum of
 *   the entries this intends to read is capped. A bomb does not lie about its size;
 *   it just says forty gigabytes, and this declines.
 * - What a file is. Every extracted file is sniffed with finfo and checked against
 *   the same allow-list the upload form uses. The document's mime_type is not asked.
 * - Where it lands. Media goes in through Media Library's own addMedia(), so the
 *   disk, the naming and the conversions are the code that already handles uploads.
 *
 * A file that fails either check is dropped and counted, never fatal. The campaign is
 * the point; the picture is not.
 */
class ReadCampaignArchive
{
    public function __construct(private readonly ReadCampaignFile $readCampaignFile) {}

    /**
     * Total uncompressed bytes this will read from one archive.
     */
    public const MAX_UNPACKED_BYTES = 209_715_200;

    public const MAX_ENTRIES = 2_000;

    /**
     * The first bytes of every zip file, and how this tells a zip from a document
     * without believing a file name.
     */
    public const MAGIC = "PK\x03\x04";

    public static function looksLikeArchive(string $head): bool
    {
        return str_starts_with($head, self::MAGIC);
    }

    public function handle(string $path): ArchiveResult
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            return ArchiveResult::failed(['That file is not a zip archive this can open.']);
        }

        if ($zip->numFiles > self::MAX_ENTRIES) {
            $zip->close();

            return ArchiveResult::failed([
                'That archive holds '.$zip->numFiles.' files, and this reads at most '.self::MAX_ENTRIES.'.',
            ]);
        }

        $json = $zip->getFromName(BuildCampaignArchive::DOCUMENT);

        if ($json === false) {
            $zip->close();

            return ArchiveResult::failed([
                'That archive has no '.BuildCampaignArchive::DOCUMENT.' in it, so there is no campaign to read.',
            ]);
        }

        $read = $this->readCampaignFile->handle($json);

        if (! $read->succeeded()) {
            $zip->close();

            return ArchiveResult::failed($read->errors);
        }

        $wanted = $this->wanted($read->document);
        $budget = $this->budget($zip, $wanted);

        if ($budget === null) {
            $zip->close();

            return ArchiveResult::failed([
                'The files in that archive unpack to more than '.round(self::MAX_UNPACKED_BYTES / 1_048_576).'MB, which is more than this will read.',
            ]);
        }

        $restored = $this->extract($zip, $wanted, $read->report);

        $zip->close();

        return ArchiveResult::ok($read, $restored);
    }

    /**
     * The archive entries the document referred to, and nothing else. Every other
     * entry in the file is ignored, whatever it is called.
     *
     * @param  array<string, mixed>  $document
     * @return list<string>
     */
    private function wanted(array $document): array
    {
        $wanted = [];

        $add = function (mixed $reference) use (&$wanted): void {
            if (is_array($reference) && is_string($reference['archive_path'] ?? null)) {
                $wanted[] = $reference['archive_path'];
            }
        };

        $add($document['campaign']['cover'] ?? null);

        foreach ($document['entities'] as $entity) {
            $add($entity['image']);

            foreach ($entity['files'] as $file) {
                $add($file);
            }
        }

        return array_values(array_unique($wanted));
    }

    /**
     * The uncompressed total, read from the central directory before anything is
     * unpacked. Null when it is more than this will read.
     *
     * @param  list<string>  $wanted
     */
    private function budget(ZipArchive $zip, array $wanted): ?int
    {
        $total = 0;

        foreach ($wanted as $entry) {
            $stat = $zip->statName($entry);

            if ($stat === false) {
                continue;
            }

            $total += (int) $stat['size'];

            if ($total > self::MAX_UNPACKED_BYTES) {
                return null;
            }
        }

        return $total;
    }

    /**
     * @param  list<string>  $wanted
     * @return array<string, string> archive entry => a local file this app named
     */
    private function extract(ZipArchive $zip, array $wanted, ImportReport $report): array
    {
        $restored = [];

        foreach ($wanted as $entry) {
            $stream = $zip->getStream($entry);

            if ($stream === false) {
                continue;
            }

            // The destination is a name this application generated. Nothing from the
            // archive reaches the filesystem as a path, which is why a ../ entry name
            // cannot go anywhere at all.
            $destination = sys_get_temp_dir().'/demgem-media-'.Str::ulid();
            $out = fopen($destination, 'wb');

            if ($out === false) {
                fclose($stream);

                continue;
            }

            stream_copy_to_stream($stream, $out, self::MAX_UNPACKED_BYTES);

            fclose($stream);
            fclose($out);

            if ($this->acceptable($destination)) {
                $restored[$entry] = $destination;

                continue;
            }

            // It said it was a picture and it is not. Drop it, count it, carry on.
            @unlink($destination);
        }

        $report->filesRestored = count($restored);

        return $restored;
    }

    /**
     * What a file is, measured rather than believed. The allow-list is the one the
     * upload form uses, so an archive can put nothing on this disk that a GM could
     * not have uploaded through the browser.
     */
    private function acceptable(string $path): bool
    {
        $mime = (string) mime_content_type($path);

        return in_array($mime, Entity::FILE_MIME_TYPES, true);
    }
}
