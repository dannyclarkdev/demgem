<?php

namespace App\Support\Table;

use App\Enums\ScreenFocus;
use App\Models\Entity;

/**
 * The screen, resolved: what is up and, when it is a page, the page itself.
 *
 * The focus here is never one that needs a page it does not have. Campaign::screen()
 * reads the page through the party's gate, and a focus of a handout the party may
 * not see comes out of it as null, so the Blade has one question to ask and the
 * answer was decided in a query.
 */
final readonly class ScreenState
{
    public function __construct(
        public ?ScreenFocus $focus,
        public ?Entity $entity,
    ) {}

    public static function nothing(): self
    {
        return new self(null, null);
    }
}
