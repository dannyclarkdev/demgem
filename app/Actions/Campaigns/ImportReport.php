<?php

namespace App\Actions\Campaigns;

use App\Support\Storage\CampaignStorage;

/**
 * What came across, what did not, and why.
 *
 * Rendered twice: on the confirm screen before anything is written, and on the
 * campaign afterwards. The "not carried" half is the point of the screen. A GM who
 * finds their images missing three weeks later has been told something they did not
 * read; a GM told before they press the button has made a decision.
 */
final class ImportReport
{
    /** @var array<string, int> */
    public array $counts = [];

    /**
     * How many files the document refers to, and how many of them this install
     * actually has. A JSON import restores none of them; an archive normally
     * restores all of them.
     */
    public int $files = 0;

    public int $filesRestored = 0;

    /**
     * Files the archive carried that the campaign's ceiling has no room for. Unpacked,
     * measured, and left behind before the GM commits, so the count is exact.
     */
    public int $filesOverQuota = 0;

    /** @var list<string> */
    public array $memberNames = [];

    public int $selectedLists = 0;

    public int $diceRolls = 0;

    /** RSVPs, attendance marks, and poll votes. Each names a person the file cannot re-link. */
    public int $answers = 0;

    public int $truncated = 0;

    /**
     * References to creatures this install has no dataset for. The campaign imports;
     * the links are simply not made, and the combatants keep their own numbers.
     */
    public int $statBlocks = 0;

    public function count(string $section, int $rows): void
    {
        $this->counts[$section] = ($this->counts[$section] ?? 0) + $rows;
    }

    /**
     * The four things an import cannot carry, in the words the screen uses. Only the
     * ones that actually apply to this file: a campaign with no images should not be
     * told about images.
     *
     * @return list<array{label: string, detail: string}>
     */
    public function losses(): array
    {
        $losses = [];

        $missing = max(0, $this->files - $this->filesRestored);

        if ($missing > 0) {
            $losses[] = [
                'label' => $this->filesRestored > 0
                    ? $missing.' of '.$this->files.' files cannot come across'
                    : $this->files.' '.str('file')->plural($this->files).' cannot come across',
                'detail' => $this->filesRestored > 0
                    ? 'Those entries are missing from the archive, or they are not the kind of file they say they are. Everything else came with it.'
                    : 'A JSON export names its images rather than carrying them, and demgem will not fetch a link out of an uploaded file. Export the archive instead, or upload them again after the import.',
            ];
        }

        if ($this->filesOverQuota > 0) {
            $losses[] = [
                'label' => $this->filesOverQuota.' '.str('file')->plural($this->filesOverQuota).' would put the campaign over its storage limit',
                'detail' => 'This install allows '.CampaignStorage::format(CampaignStorage::limitBytes()).' of files per campaign. The files that fit come across in the order the archive lists them; the rest stay behind, and you can upload them again after deleting something.',
            ];
        }

        if (($this->counts['entity_body_revisions'] ?? 0) > 0) {
            $losses[] = [
                'label' => 'History keeps names, without reconnecting accounts',
                'detail' => 'Earlier bodies and their replacement dates come across. Historical names remain labels; they are not linked to users on this install.',
            ];
        }

        if ($this->memberNames !== []) {
            $losses[] = [
                'label' => count($this->memberNames).' '.str('member')->plural(count($this->memberNames)).' cannot be re-linked',
                'detail' => 'An export carries names and roles, never email addresses. You will be the only member; invite the rest as you did the first time. From the file: '.implode(', ', $this->memberNames).'.',
            ];
        }

        if ($this->selectedLists > 0) {
            $losses[] = [
                'label' => $this->selectedLists.' '.str('page')->plural($this->selectedLists).' shared with named players will arrive GM-only',
                'detail' => 'Those lists name people this install does not know. Nothing is ever made more visible than the file says, so they come in hidden and you can share them again.',
            ];
        }

        if ($this->diceRolls > 0) {
            $losses[] = [
                'label' => $this->diceRolls.' dice '.str('roll')->plural($this->diceRolls).' will be left behind',
                'detail' => 'A roll records who made it, and those people cannot be re-linked. Attributing every roll to you would be a lie in the record, so the log stays behind.',
            ];
        }

        if ($this->answers > 0) {
            $losses[] = [
                'label' => $this->answers.' '.str('answer')->plural($this->answers).' about dates and attendance will be left behind',
                'detail' => 'Who said yes, who turned up, and who could make which Thursday all name people this install does not have. The sessions come across; the answers about them do not.',
            ];
        }

        if ($this->statBlocks > 0) {
            $losses[] = [
                'label' => $this->statBlocks.' '.str('link')->plural($this->statBlocks).' to the compendium cannot be made',
                'detail' => 'The file names creatures this install has no dataset for. An export carries the reference, never the licensed text, so the pages and the fights come across with their own numbers and the links are simply not made.',
            ];
        }

        return $losses;
    }

    /**
     * The one line on the confirm screen that is good news.
     */
    public function gains(): ?string
    {
        if ($this->filesRestored === 0) {
            return null;
        }

        return $this->filesRestored.' '.str('file')->plural($this->filesRestored).' will come across with it, pictures and all.';
    }

    public function hasLosses(): bool
    {
        return $this->losses() !== [];
    }

    public function total(): int
    {
        return array_sum($this->counts);
    }
}
