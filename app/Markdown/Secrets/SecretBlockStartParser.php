<?php

namespace App\Markdown\Secrets;

use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\MarkdownParserStateInterface;

final class SecretBlockStartParser implements BlockStartParserInterface
{
    public function tryStart(Cursor $cursor, MarkdownParserStateInterface $parserState): ?BlockStart
    {
        if ($cursor->isIndented()) {
            return BlockStart::none();
        }

        $match = $cursor->match('/^[ \t]{0,3}:::secret[ \t]*$/');

        if ($match === null) {
            return BlockStart::none();
        }

        return BlockStart::of(new SecretBlockParser)->at($cursor);
    }
}
