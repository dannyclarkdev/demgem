<?php

namespace App\Calendar;

use Carbon\CarbonInterface;

/**
 * Writes the part of RFC 5545 a session feed needs: one VCALENDAR, one VEVENT per
 * session, CRLF endings, a fold at 75 octets, and four escapes. Hand-written so
 * that the rules live in one tested class rather than in a dependency that
 * covers a format ten times this size.
 */
class IcsCalendar
{
    /** @var list<IcsEvent> */
    private array $events = [];

    public function __construct(private readonly string $name) {}

    public function add(IcsEvent $event): static
    {
        $this->events[] = $event;

        return $this;
    }

    public function render(): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//demgem//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.self::escape($this->name),
        ];

        foreach ($this->events as $event) {
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:'.self::escape($event->uid);
            $lines[] = 'DTSTAMP:'.self::stamp($event->stampedAt);
            $lines[] = 'DTSTART:'.self::stamp($event->startsAt);
            $lines[] = 'DTEND:'.self::stamp($event->endsAt);
            $lines[] = 'SUMMARY:'.self::escape($event->summary);
            $lines[] = 'DESCRIPTION:'.self::escape($event->description);
            $lines[] = 'URL:'.$event->description;
            $lines[] = 'STATUS:'.$event->status;
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode('', array_map(fn (string $line) => self::fold($line)."\r\n", $lines));
    }

    /**
     * UTC, always, with the Z that says so. The reader's calendar app converts to its
     * owner's zone, which is the per-user timezone this project does not store.
     */
    public static function stamp(CarbonInterface $time): string
    {
        return $time->copy()->setTimezone('UTC')->format('Ymd\THis\Z');
    }

    /**
     * Backslash first, then the three separators, then newlines. The order matters:
     * escaping the backslash last would double the ones the other three just added.
     */
    public static function escape(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\n"],
            ['\\\\', '\;', '\,', '\n', '\n'],
            $value,
        );
    }

    /**
     * RFC 5545 §3.1: no content line longer than 75 octets, continuation lines start
     * with a single space. Counted in bytes, and never inside a multibyte character,
     * because a title can hold an em dash and a fold through the middle of one is
     * not UTF-8 any more.
     */
    public static function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $out = '';
        $current = '';
        $limit = 75;

        foreach (mb_str_split($line) as $character) {
            if (strlen($current) + strlen($character) > $limit) {
                $out .= $current."\r\n ";
                $current = '';
                $limit = 74;
            }

            $current .= $character;
        }

        return $out.$current;
    }
}
