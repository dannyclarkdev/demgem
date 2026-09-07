<?php

use App\Calendar\IcsCalendar;
use App\Calendar\IcsEvent;
use Illuminate\Support\Carbon;

function anEvent(string $summary = 'Session 1: The cellar', string $description = 'https://demgem.test/s/1'): IcsEvent
{
    return new IcsEvent(
        uid: 'session-01abc@demgem.test',
        summary: $summary,
        description: $description,
        startsAt: Carbon::parse('2026-09-10 19:00:00', 'Europe/London'),
        endsAt: Carbon::parse('2026-09-10 23:00:00', 'Europe/London'),
        status: 'CONFIRMED',
        stampedAt: Carbon::parse('2026-09-07 12:00:00', 'UTC'),
    );
}

function unfold(string $ics): string
{
    return str_replace("\r\n ", '', $ics);
}

it('folds any line past 75 octets and unfolds back to the original', function () {
    $summary = str_repeat('The road to the Salt Cathedral and back ', 6);

    $ics = (new IcsCalendar('demgem'))->add(anEvent($summary))->render();

    foreach (explode("\r\n", $ics) as $line) {
        expect(strlen($line))->toBeLessThanOrEqual(75);
    }

    expect(unfold($ics))->toContain('SUMMARY:'.$summary);
});

it('never splits a multibyte character when it folds', function () {
    $summary = str_repeat('—', 60);

    $ics = (new IcsCalendar('demgem'))->add(anEvent($summary))->render();

    foreach (explode("\r\n", $ics) as $line) {
        expect(mb_check_encoding($line, 'UTF-8'))->toBeTrue()
            ->and(strlen($line))->toBeLessThanOrEqual(75);
    }

    expect(unfold($ics))->toContain('SUMMARY:'.$summary);
});

it('escapes the four characters the format reserves', function () {
    $ics = (new IcsCalendar('demgem'))
        ->add(anEvent("Vell; the duchy, again \\ twice\nsecond line"))
        ->render();

    expect(unfold($ics))->toContain('SUMMARY:Vell\; the duchy\, again \\\\ twice\nsecond line');
});

it('writes every time in UTC with a Z', function () {
    $ics = (new IcsCalendar('demgem'))->add(anEvent())->render();

    expect($ics)->toContain("DTSTART:20260910T180000Z\r\n")
        ->and($ics)->toContain("DTEND:20260910T220000Z\r\n")
        ->and($ics)->toContain("DTSTAMP:20260907T120000Z\r\n");
});

it('is one calendar with CRLF endings and nothing after the last line', function () {
    $ics = (new IcsCalendar('demgem'))->add(anEvent())->render();

    expect($ics)->toStartWith("BEGIN:VCALENDAR\r\nVERSION:2.0\r\n")
        ->and($ics)->toEndWith("END:VCALENDAR\r\n")
        ->and($ics)->toContain("BEGIN:VEVENT\r\nUID:session-01abc@demgem.test\r\n")
        ->and($ics)->toContain("STATUS:CONFIRMED\r\n")
        ->and(substr_count($ics, "\n"))->toBe(substr_count($ics, "\r\n"));
});
