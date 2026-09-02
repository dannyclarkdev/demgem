<?php

namespace App\Markdown\WikiLink;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;

final class WikiLinkExtension implements ExtensionInterface
{
    public function __construct(private readonly WikiLinkRenderer $renderer) {}

    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment
            ->addInlineParser(new WikiLinkParser, 60)
            ->addRenderer(WikiLink::class, $this->renderer);
    }
}
