<?php

namespace App\Enums;

/**
 * What a relationship means when it is family, read from the source's side: the
 * source is a parent of the target, a child of it, a sibling, or a spouse.
 *
 * Four cases, because four is what a tree needs. A cousin is a sibling's child, an
 * in-law is a spouse's parent, and both are walked rather than typed.
 */
enum Kinship: string
{
    case Parent = 'parent';
    case Child = 'child';
    case Sibling = 'sibling';
    case Spouse = 'spouse';

    /**
     * The word from the source's side: "parent of".
     */
    public function label(): string
    {
        return $this->value.' of';
    }

    /**
     * The same row read from the target's side.
     */
    public function reverse(): self
    {
        return match ($this) {
            self::Parent => self::Child,
            self::Child => self::Parent,
            self::Sibling, self::Spouse => $this,
        };
    }

    /**
     * The heading a generation gets on the tree.
     */
    public function plural(): string
    {
        return match ($this) {
            self::Parent => 'Parents',
            self::Child => 'Children',
            self::Sibling => 'Siblings',
            self::Spouse => 'Spouses',
        };
    }
}
