<?php

namespace App\Calendar;

use Carbon\CarbonInterface;

/**
 * One event, and the only fields the feed will ever write. There is no field for
 * prose on purpose: a calendar entry gets forwarded, synced, and searched, and
 * nothing a GM keeps behind the screen belongs in one.
 */
final readonly class IcsEvent
{
    public function __construct(
        public string $uid,
        public string $summary,
        public string $description,
        public CarbonInterface $startsAt,
        public CarbonInterface $endsAt,
        public string $status,
        public CarbonInterface $stampedAt,
    ) {}
}
