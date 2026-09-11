<?php

namespace App\Markdown\Secrets;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

/**
 * The GM's aside: the fenced prose with a "GM only" label, in the purple the DM
 * badges use, so it reads as what it is on a page the party also opens.
 */
final class SecretBlockRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): HtmlElement
    {
        if (! $node instanceof SecretBlock) {
            throw new \InvalidArgumentException('Incompatible node type: '.get_class($node));
        }

        $label = new HtmlElement('span', ['class' => 'secret-block__label'], 'GM only');
        $inner = $childRenderer->renderNodes($node->children());

        return new HtmlElement('aside', ['class' => 'secret-block'], $label.$childRenderer->getBlockSeparator().$inner);
    }
}
