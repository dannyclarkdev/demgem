---
paths:
  - 'app/Support/Reckoning/**'
---

# Reckoning

## Reckoning is the in-game calendar; app/Calendar is the iCal feed
The world's calendar arithmetic lives in App\Support\Reckoning (Reckoning, GameDate, MoonPhase, MoonReading, Bounds), pure and unit tested under tests/Unit/Reckoning. App\Calendar is the RFC 5545 feed and its rules do not apply here; do not put in-game code there or name a route `calendar.feed`-adjacent by accident (`calendar.show`, `calendar.edit`, `timeline` are the in-game routes).

Everything reduces to a day number, days since 1/1/1, computed every time and never stored. Years start at 1; a date before year 1 is refused at write time, and negative years are a slice of their own. One leap rule only: every N years, month M gains a day. A moon's offset is days into its cycle at 1/1/1, with 0 meaning new. `format()` prints "month 13" for a month the calendar no longer has rather than failing, so a date written under an older shape still prints. Every number from a browser or a file goes through Bounds first, the way Segments and Coordinate do.
