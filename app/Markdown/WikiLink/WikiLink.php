<?php

namespace App\Markdown\WikiLink;

use App\Markdown\WikiLinkToken;
use League\CommonMark\Node\Inline\AbstractInline;

final class WikiLink extends AbstractInline
{
    public function __construct(public readonly WikiLinkToken $token)
    {
        parent::__construct();
    }
}
