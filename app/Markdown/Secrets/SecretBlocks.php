<?php

namespace App\Markdown\Secrets;

/**
 * The `:::secret` fence, as text.
 *
 * `:::secret` alone on a line opens a block and `:::` alone on a line closes it. An
 * unclosed fence runs to the end of the text: a GM who forgot the close hid too much
 * rather than too little, which is the safe reading.
 *
 * This is the one place the fence is understood as text. Every reader a player has
 * calls strip() before the text goes anywhere: the renderer, the excerpt, the
 * resource, the search filter, the mention scanner, and the form. The GM's renderer
 * is the only thing that parses the fence into a block, and it does that through
 * the CommonMark extension beside this class.
 */
final class SecretBlocks
{
    /**
     * A fence with its contents, the contents in group 1. The close is optional, so
     * the pattern also eats a fence left open. Line-anchored and tolerant of trailing
     * spaces on the fence lines, like the parser.
     */
    private const PATTERN = '/^[ \t]{0,3}:::secret[ \t]*(?:\R|\z)(.*?)(?:^[ \t]{0,3}:::[ \t]*(?:\R|\z)|\z)/msu';

    public static function contains(?string $markdown): bool
    {
        return $markdown !== null && preg_match('/^[ \t]{0,3}:::secret[ \t]*$/mu', $markdown) === 1;
    }

    /**
     * The text with every fence and its contents removed. Null stays null, and text
     * with no fence comes back untouched, so callers need not check first.
     */
    public static function strip(?string $markdown): ?string
    {
        if ($markdown === null || ! self::contains($markdown)) {
            return $markdown;
        }

        // Each fence becomes one line break, so the paragraphs either side of it stay
        // apart; a run of breaks where a block was collapses to one paragraph break.
        $stripped = (string) preg_replace(self::PATTERN, "\n", $markdown);

        return trim((string) preg_replace('/(\R[ \t]*){3,}/u', "\n\n", $stripped));
    }

    /**
     * Only the contents of the fences, joined as paragraphs, for the mention scanner:
     * a link inside a fence is indexed under a field name no player's list carries.
     */
    public static function only(?string $markdown): ?string
    {
        if ($markdown === null || ! self::contains($markdown)) {
            return null;
        }

        preg_match_all(self::PATTERN, $markdown, $matches);

        $inner = array_filter(array_map(fn (string $block) => trim($block), $matches[1]), fn (string $block) => $block !== '');

        return $inner === [] ? null : implode("\n\n", $inner);
    }

    /**
     * The fences a player never saw, put back after the text they edited.
     *
     * A player's editor holds strip($stored). When they save, the fences $stored held
     * are appended to their text, each as its own block, so a GM's annotation survives
     * a player's edit. It moves to the end of the page; that is the recorded cost.
     */
    public static function merge(?string $edited, ?string $stored): ?string
    {
        if ($stored === null || ! self::contains($stored)) {
            return $edited;
        }

        preg_match_all(self::PATTERN, $stored, $matches);

        $fences = array_map(fn (string $fence) => rtrim($fence), $matches[0]);
        $fences = array_map(fn (string $fence) => str_ends_with(trim($fence), ':::') && ! str_ends_with(trim($fence), ':::secret') ? $fence : $fence."\n:::", $fences);

        $base = trim((string) $edited);

        return ($base === '' ? '' : $base."\n\n").implode("\n\n", $fences);
    }
}
