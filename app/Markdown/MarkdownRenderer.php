<?php

namespace App\Markdown;

use App\Markdown\WikiLink\WikiLinkExtension;
use App\Markdown\WikiLink\WikiLinkRenderer;
use Illuminate\Support\Str;

/**
 * The one place user Markdown becomes HTML. Raw HTML is stripped and unsafe links are blocked.
 */
class MarkdownRenderer
{
    public function __construct(private readonly WikiLinkScanner $scanner) {}

    /**
     * @return array<string, mixed>
     */
    public static function options(): array
    {
        return [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 50,
        ];
    }

    /**
     * Without a wiki link renderer, [[links]] stay as typed.
     */
    public function render(?string $markdown, ?WikiLinkRenderer $wikiLinks = null): string
    {
        if ($markdown === null || trim($markdown) === '') {
            return '';
        }

        $extensions = [];

        if ($wikiLinks !== null) {
            $wikiLinks->preload($this->scanner->scan($markdown));
            $extensions[] = new WikiLinkExtension($wikiLinks);
        }

        return (string) Str::markdown($markdown, self::options(), $extensions);
    }
}
