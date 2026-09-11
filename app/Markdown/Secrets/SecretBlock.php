<?php

namespace App\Markdown\Secrets;

use League\CommonMark\Node\Block\AbstractBlock;

/**
 * A `:::secret` fence, as a block. Only a GM's renderer ever builds one: a player's
 * text is stripped of the fence before the parser sees it.
 */
final class SecretBlock extends AbstractBlock {}
