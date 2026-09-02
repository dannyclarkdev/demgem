<?php

namespace App\Markdown;

/**
 * Finds [[wiki links]] in Markdown without rendering it.
 */
final class WikiLinkScanner
{
    public const PATTERN = '/\[\[(?:([A-Za-z]+):)?([^\]|\n]+?)(?:\|([^\]\n]+?))?\]\]/u';

    /**
     * @return list<WikiLinkToken>
     */
    public function scan(?string $markdown): array
    {
        if ($markdown === null || $markdown === '') {
            return [];
        }

        preg_match_all(self::PATTERN, $markdown, $matches, PREG_SET_ORDER);

        $tokens = array_map(
            fn (array $match) => WikiLinkToken::fromMatch($match[0], $match[1], $match[2], $match[3] ?? null),
            $matches,
        );

        return array_values(array_filter($tokens, fn (WikiLinkToken $token) => ! $token->isBlank()));
    }
}
