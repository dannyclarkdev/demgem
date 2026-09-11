<?php

namespace App\Markdown\Secrets;

use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Parser\Block\AbstractBlockContinueParser;
use League\CommonMark\Parser\Block\BlockContinue;
use League\CommonMark\Parser\Block\BlockContinueParserInterface;
use League\CommonMark\Parser\Cursor;

/**
 * A container: everything up to the closing `:::` line is parsed as ordinary
 * Markdown inside the block. An unclosed fence runs to the end of the text, which
 * matches SecretBlocks::strip() reading it the same way.
 */
final class SecretBlockParser extends AbstractBlockContinueParser
{
    private readonly SecretBlock $block;

    public function __construct()
    {
        $this->block = new SecretBlock;
    }

    public function getBlock(): AbstractBlock
    {
        return $this->block;
    }

    public function isContainer(): bool
    {
        return true;
    }

    public function canContain(AbstractBlock $childBlock): bool
    {
        return true;
    }

    public function tryContinue(Cursor $cursor, BlockContinueParserInterface $activeBlockParser): BlockContinue
    {
        if (! $cursor->isIndented() && $cursor->match('/^[ \t]{0,3}:::[ \t]*$/') !== null) {
            return BlockContinue::finished();
        }

        return BlockContinue::at($cursor);
    }
}
