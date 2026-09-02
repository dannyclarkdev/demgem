<?php

namespace App\Markdown\WikiLink;

use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Markdown\LinkResolver;
use App\Markdown\WikiLinkToken;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\User;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Util\Xml;

/**
 * Decides what a [[link]] becomes for one viewer:
 * a link, plain text (hidden target, or missing target for a player), or a create prompt (missing target, DM).
 */
final class WikiLinkRenderer implements NodeRendererInterface
{
    public function __construct(
        public readonly LinkResolver $resolver,
        private readonly Campaign $campaign,
        private readonly User $viewer,
        private readonly CampaignRole $role,
    ) {}

    public static function for(Campaign $campaign, User $viewer, CampaignRole $role): self
    {
        return new self(new LinkResolver($campaign->id), $campaign, $viewer, $role);
    }

    /**
     * @param  iterable<WikiLinkToken>  $tokens
     */
    public function preload(iterable $tokens): void
    {
        $this->resolver->preload($tokens);
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): HtmlElement|string
    {
        if (! $node instanceof WikiLink) {
            throw new \InvalidArgumentException('Incompatible node type: '.get_class($node));
        }

        return $this->present($node->token);
    }

    public function present(WikiLinkToken $token): HtmlElement|string
    {
        $entity = $this->resolver->resolve($token->name, $token->type);
        $text = Xml::escape($token->text());

        if ($entity !== null) {
            return $this->presentResolved($entity, $text);
        }

        if (! $this->role->isDm()) {
            return new HtmlElement('span', ['class' => 'wiki-link wiki-link--plain'], $text);
        }

        return $this->presentMissing($token, $text);
    }

    private function presentResolved(Entity $entity, string $text): HtmlElement
    {
        if (! $entity->isVisibleTo($this->viewer, $this->role)) {
            return new HtmlElement('span', ['class' => 'wiki-link wiki-link--plain'], $text);
        }

        return new HtmlElement('a', [
            'href' => $entity->url(),
            'class' => 'wiki-link',
            'data-entity-type' => $entity->type->value,
        ], $text);
    }

    private function presentMissing(WikiLinkToken $token, string $text): HtmlElement|string
    {
        if ($token->type !== null) {
            return new HtmlElement('a', [
                'href' => $this->createUrl($token->type, $token->name),
                'class' => 'wiki-link wiki-link--missing',
                'title' => "Create this {$token->type->label()}",
            ], $text);
        }

        $options = '';

        foreach (EntityType::cases() as $type) {
            $options .= new HtmlElement('a', ['href' => $this->createUrl($type, $token->name), 'class' => 'wiki-link__option'], Xml::escape($type->label()));
        }

        $escapedName = Xml::escape($token->name);

        return '<span class="wiki-link wiki-link--missing" x-data="{ open: false }" @click.outside="open = false">'
            .'<button type="button" class="wiki-link__missing-btn" @click="open = !open" title="No entity named &ldquo;'.$escapedName.'&rdquo; yet. Click to create it.">'.$text.'</button>'
            .'<span x-show="open" x-cloak class="wiki-link__menu"><span class="wiki-link__menu-title">Create &ldquo;'.$escapedName.'&rdquo; as</span>'.$options.'</span>'
            .'</span>';
    }

    private function createUrl(EntityType $type, string $name): string
    {
        return route('entities.create', [$this->campaign, $type->slug(), 'name' => $name]);
    }
}
