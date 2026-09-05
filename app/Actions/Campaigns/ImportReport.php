<?php

namespace App\Actions\Campaigns;

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

    public int $files = 0;

    /** @var list<string> */
    public array $memberNames = [];

    public int $selectedLists = 0;

    public int $diceRolls = 0;

    public int $truncated = 0;

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

        if ($this->files > 0) {
            $losses[] = [
                'label' => $this->files.' '.str('file')->plural($this->files).' cannot come across',
                'detail' => 'An export names its images rather than carrying them. demgem will not fetch a link out of an uploaded file, so upload them again after the import.',
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

        return $losses;
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
