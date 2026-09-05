<?php

namespace App\Actions\Campaigns;

use App\Models\Campaign;
use RuntimeException;
use ZipArchive;

/**
 * A whole campaign as one file: the document, the bytes, and a readable copy.
 *
 * The JSON export streams, because a campaign of any size must start downloading at
 * once. An archive cannot, and this says so rather than pretending: ZipArchive needs
 * a real file to finalise its central directory. The mitigation is addFile(), which
 * points at media already on disk instead of reading them into memory, so the peak
 * cost of building one is the JSON document — exactly what it is today.
 *
 * Every entry name in here is generated. Nothing an uploaded archive contains ever
 * becomes a path, and this is the writing half of that rule.
 */
class BuildCampaignArchive
{
    public function __construct(private readonly ExportCampaign $exportCampaign) {}

    public const DOCUMENT = 'campaign.json';

    /**
     * Builds the archive and returns the path to it. The caller sends the file and
     * deletes it; nothing here keeps it.
     */
    public function handle(Campaign $campaign): string
    {
        $export = $this->exportCampaign->forArchive();

        // Encoding is what walks the lazy sections, and walking them is what records
        // which media the document referred to. The order matters.
        $json = json_encode($this->walk($export->handle($campaign)), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $path = tempnam(sys_get_temp_dir(), 'demgem-archive').'.zip';
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not open an archive to write.');
        }

        $zip->addFromString(self::DOCUMENT, $json);
        $zip->addFromString('README.md', $this->readme($campaign));

        foreach ($export->archiveFiles() as $entry => $file) {
            $zip->addFile($file, $entry);
        }

        $zip->close();

        return $path;
    }

    public function filename(Campaign $campaign): string
    {
        $name = str($campaign->name)->slug()->limit(60, '')->value();

        return 'demgem-'.($name !== '' ? $name : 'campaign').'-'.now()->format('Y-m-d').'.zip';
    }

    /**
     * The sections are LazyCollections so the plain export can stream. json_encode
     * walks them itself, but doing it here keeps the shape obvious and lets the
     * archive's document be pretty-printed without surprising anybody.
     *
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    private function walk(array $document): array
    {
        return array_map(
            fn (mixed $section) => is_iterable($section) && ! is_array($section) ? iterator_to_array($section) : $section,
            $document,
        );
    }

    /**
     * Four sentences for whoever opens this in three years.
     */
    private function readme(Campaign $campaign): string
    {
        return <<<MD
        # {$campaign->name}

        This is a demgem campaign archive. demgem is an open source campaign manager
        for tabletop roleplaying games: <https://github.com/sweatybreeze/demgem>.

        ## What is in here

        - `campaign.json` is the campaign itself, and the only file demgem reads back.
          Import it, or this whole zip, from the Campaigns page.
        - `media/` holds the images and attachments the document refers to.
        - `markdown/` is a readable copy of the same campaign, one file per page, with
          YAML front matter and `[[wiki links]]`. Open the folder as an Obsidian vault
          and the links work.

        ## What demgem does not read back

        The Markdown is a copy, not the source. Editing it changes nothing: demgem
        imports `campaign.json` and ignores the rest of the folder except the media it
        names.

        Member email addresses are never exported, so an import makes you the only
        member and you invite the others again. Dice rolls stay behind, because a roll
        records who made it and that person cannot be re-linked on another install.
        MD;
    }
}
