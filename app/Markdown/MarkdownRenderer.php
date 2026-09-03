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

    /**
     * One line of Markdown for a place that is already a line: a quest objective, a
     * random table entry. Same parser and same escaping; the single wrapping paragraph
     * is unwrapped so the text sits inside a flex row instead of forcing a block.
     */
    public function renderInline(?string $markdown, ?WikiLinkRenderer $wikiLinks = null): string
    {
        $html = trim($this->render($markdown, $wikiLinks));

        if (str_starts_with($html, '<p>') && str_ends_with($html, '</p>') && substr_count($html, '<p>') === 1) {
            return trim(substr($html, 3, -4));
        }

        return $html;
    }
}
