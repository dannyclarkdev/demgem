<?php

namespace App\Markdown;

use App\Enums\EntityType;

/**
 * A parsed [[type:Name|label]]. Type is null unless the prefix names a known entity type.
 */
final readonly class WikiLinkToken
{
    public function __construct(
        public string $raw,
        public string $name,
        public ?EntityType $type,
        public ?string $label,
    ) {}

    /**
     * Builds a token from regex groups. An unknown prefix stays part of the name,
     * so [[Chapter: The Fall]] is a name, not a type lookup.
     */
    public static function fromMatch(string $raw, ?string $prefix, string $name, ?string $label): self
    {
        $type = null;

        if ($prefix !== null && $prefix !== '') {
            $type = EntityType::tryFrom(strtolower($prefix)) ?? EntityType::fromSlug(strtolower($prefix));

            if ($type === null) {
                $name = $prefix.':'.$name;
            }
        }

        $label = $label !== null && trim($label) !== '' ? trim($label) : null;

        return new self($raw, trim($name), $type, $label);
    }

    public function isBlank(): bool
    {
        return $this->name === '';
    }

    public function text(): string
    {
        return $this->label ?? $this->name;
    }
}
