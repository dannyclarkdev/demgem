<?php

namespace App\Markdown\Secrets;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;

final class SecretBlockExtension implements ExtensionInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment
            ->addBlockStartParser(new SecretBlockStartParser, 80)
            ->addRenderer(SecretBlock::class, new SecretBlockRenderer);
    }
}
