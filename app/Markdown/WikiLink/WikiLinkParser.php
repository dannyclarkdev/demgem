<?php

namespace App\Markdown\WikiLink;

use App\Markdown\WikiLinkToken;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;

final class WikiLinkParser implements InlineParserInterface
{
    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::regex('\[\[(?:([A-Za-z]+):)?([^\]|\n]+?)(?:\|([^\]\n]+?))?\]\]');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $cursor = $inlineContext->getCursor();
        $raw = $inlineContext->getFullMatch();
        $cursor->advanceBy($inlineContext->getFullMatchLength());

        [$prefix, $name, $label] = array_pad($inlineContext->getSubMatches(), 3, null);
        $token = WikiLinkToken::fromMatch($raw, $prefix, (string) $name, $label);

        if ($token->isBlank()) {
            $inlineContext->getContainer()->appendChild(new Text($raw));

            return true;
        }

        $inlineContext->getContainer()->appendChild(new WikiLink($token));

        return true;
    }
}
