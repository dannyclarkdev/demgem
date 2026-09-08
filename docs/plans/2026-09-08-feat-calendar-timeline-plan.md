---
title: "feat: The world's own calendar, and the days the party spent in it"
type: feat
date: 2026-09-08
brainstorm: docs/brainstorms/2026-09-02-demgem-campaign-manager-brainstorm.md
follows: docs/plans/2026-09-08-feat-entity-relations-plan.md
---

# feat: The world's own calendar, and the days the party spent in it

## Overview

The brainstorm has four rows that wait on one thing. "Custom calendar: months, weekdays, moons, leap rules, current in-game date" is that thing. "Timeline of events tied to calendar dates", "Session in-game date range", and the `event` entity type all follow from it. This slice builds the calendar and the three rows that lean on it.

| Feature | What it adds |
|---|---|
| The calendar | One per campaign: named months with a day count each, weekdays, moons with a cycle, a leap rule, an era suffix, and the current in-game date. |
| The reckoning | Pure date math with no database: the day number of any date, the weekday, the phase of every moon, and a printed form such as "Tideday, 3 Harvestmoon 1042 AR". |
| The calendar screen | The current month as a grid, today marked, moon phases on each day, and the events and sessions that fall on it. A GM advances the day or sets it. |
| Events | A new entity type with a date. It has a body, a visibility, wiki links, and everything else an entity has. |
| Sessions in the world | An in-game start and end on a session, shown on the session, the story so far, and the timeline. |
| The timeline | Every dated event and every dated session the viewer may see, in world order, with a marker for today. |
| The round trip | The calendar and every date in the export, the import, and the Markdown front matter. |

When this slice is done a GM tells the party "it is the third of Harvestmoon, the Drowned Moon is full", the dashboard says so, the session's recap carries the date, and the timeline shows the fire at the harbor two weeks before it.

**On scope.** Phase 0 is the calendar itself: the math, the settings, the screen, and the dashboard card. Phase 1 puts dates on events and sessions and draws the timeline. Phase 2 is the export, the seeder, the rules, and the pass.

## Problem Statement

**Every session happens on a date the app cannot write down.** `scheduled_at` is the date the players met. The date the characters lived through has nowhere to go but the recap body, and a date in prose cannot be sorted, cannot mark a day on a grid, and cannot say how long ago the harbor burned.

**A fantasy campaign's year is not the Earth's.** Kanka, World Anvil, and Fantasy Calendar all let a GM name the months and set their length, because the players ask "what day is it" every session and "the 14th of March" breaks the world. Moons matter for the same reason: werewolves, tides, and rituals.

**The hard part is the arithmetic, and it must be right once.** A weekday is a day number modulo a week length. A moon phase is a day number modulo a cycle. A day number depends on every month's length and every leap year before it. All of that belongs in one pure class with a unit test per rule, not in a Blade.

## Proposed Solution

**A calendar is one row per campaign, and its lists are JSON.** `calendars` holds `months`, `weekdays`, and `moons` as JSON arrays, plus the leap rule, the era, and the current date as scalars. The models rule says a list gets a child table. This slice makes a recorded exception: these lists are one configuration read whole on every use, replaced whole on every save, never queried by row, and never gated. Three child tables would cost three joins per page for nothing.

**The math is a value object, `Reckoning`, under `App\Support\Reckoning`.** `app/Calendar` is the iCal feed, and its rules are about RFC 5545. The in-game calendar gets its own namespace so the two never share a rule file. `Reckoning` takes the arrays and answers every question about a `GameDate`. It is unit tested with no database.

**A date is three small integers, stored as three columns and read as one object.** `GameDate` is `year`, `month`, `day`. A session carries `in_game_start_*` and `in_game_end_*`; an event carries `happens_*`. An Eloquent cast maps the three columns to one `GameDate`, the documented Laravel pattern for a value object over several columns. The triple survives a calendar edit: "3 Harvestmoon 1042" stays the third day of the third month when the GM renames the month. A packed day number would not.

**Events are entities.** `EntityType::Event` with three date columns. Visibility, wiki links, tags, the image, the export, and the vault all come free. The timeline reads events through `Entity::visibleTo()` and sessions through `GameSession::scopeVisibleTo()`, in the query, and merges the two lists in PHP.

**Years start at 1.** A date before year 1 is refused at write time. Counting backwards is a future slice.

## Technical Approach

### No new dependency

Nothing to install.

### What slices 1 to 11 give us for free

| Piece | Reuse |
|---|---|
| `Campaigns\Settings` | The shape of a GM-only form that writes a campaign-level row. |
| `Entity::visibleTo()` and `GameSession::scopeVisibleTo()` | The two gates on the timeline and the calendar grid. |
| `EntityType` and `Entities\Form` | The per-type fields pattern: `isQuest()`, prohibited rules for the wrong type. |
| `Sessions\Story` | A chronological page with visibility settled before the view. |
| `Clocks\Segments` and `Maps\Coordinate` | Clamp-style bounds that the reader reuses on import. |
| `ExportCampaign::NESTED_TABLES` | Where `calendars` joins as `campaign.calendar`. |
| `WriteCampaignMarkdown::session()` | The front matter that gains an in-game date. |

### The data

```
calendars
  id             ulid
  campaign_id    ulid -> campaigns, cascade, unique
  name           string(60)                 "The Tide Reckoning"
  era            string(12) nullable        "AR", printed after the year
  months         json   list<{name: string(30), days: 1..999}>, 1..24 entries
  weekdays       json   list<string(30)>, 0..14 entries; empty means no weeks
  moons          json   list<{name: string(30), cycle: 1..999 with two decimals, offset: 0..998}>, 0..6 entries
  leap_every     unsigned smallint nullable   every N years, 2..1000
  leap_month     unsigned tinyint nullable    1-based, the month that gains a day
  current_year   unsigned int
  current_month  unsigned tinyint
  current_day    unsigned smallint
  timestamps

game_sessions
  in_game_start_year, in_game_start_month, in_game_start_day   nullable ints
  in_game_end_year,   in_game_end_month,   in_game_end_day     nullable ints

entities
  happens_year, happens_month, happens_day   nullable ints
```

Both triples are nullable as a group: all three set or all three null, enforced at write time.

### The reckoning

`App\Support\Reckoning\Reckoning` is `final readonly`, built from the calendar's arrays.

- `isLeapYear(int $year)`: `leap_every` is set and `year % leap_every === 0`.
- `daysInMonth(int $month, int $year)`: the month's days, plus one in a leap year for `leap_month`.
- `dayNumber(GameDate)`: days since 1/1/1, so 1/1/1 is 0.
- `dateAt(int $dayNumber)`: the inverse.
- `add(GameDate, int $days)`.
- `weekdayOf(GameDate)`: `weekdays[dayNumber % count]`, or null with no weekdays.
- `phases(GameDate)`: one `MoonPhase` per moon, from `(dayNumber + offset) mod cycle / cycle`, where 0 is new.
- `format(GameDate)`: "Tideday, 3 Harvestmoon 1042 AR"; the weekday and era only when they exist.
- `isValid(GameDate)`: year at least 1, month within the list, day within the month.
- `static earth()`: twelve months, seven weekdays, one moon at 29.53 days, a leap day every four years in month 2. The form's starting values.

`App\Support\Reckoning\GameDate` is `final readonly` with `year`, `month`, `day`, `compare()`, `equals()`, `toArray()`, `fromArray()`. `App\Support\Reckoning\MoonPhase` is an enum of eight phases with a label and a symbol. `App\Support\Reckoning\Bounds` holds the limits above and clamps, for the reader.

`App\Casts\GameDateCast` takes a column prefix. `get()` returns a `GameDate` when all three columns are set, else null. `set()` writes the three columns from a `GameDate` or null.

### The screens

**`calendar.edit`**, GM only, `/campaigns/{campaign}/calendar/edit`. Name, era, the month rows with add and remove, weekdays as one comma-separated field, the moon rows, the leap rule, and the current date. Creating starts from `Reckoning::earth()`. `SaveCalendar` validates every bound and that the current date is valid in the calendar being saved. A month a date already uses can still be removed; `format()` prints "month 13" rather than failing, and the timeline still sorts.

**`calendar.show`**, every member, `/campaigns/{campaign}/calendar`. Without a calendar: an empty state, and for a GM a button to create one. With one: a page header naming today, a moon line, month controls (previous, next, today), and the month grid. With weekdays the grid has one column per weekday and starts on the right column; without, the days flow seven to a row. Each cell has the day, the moon symbols, and the events and sessions that start on it, from two gated queries for the month. A GM has "Advance a day" and a "Set the date" form. `AdvanceDate` and `SetCurrentDate` are the actions.

**`timeline`**, every member, `/campaigns/{campaign}/timeline`. Dated events through `visibleTo`, dated sessions through `scopeVisibleTo`, merged, sorted by the triple, grouped by year, with a "Today" marker in place. An entity row links to its page; a session row shows its number, title, and range. Empty state when nothing is dated.

**The dashboard** gets an "In the world" card: today's formatted date and the moons, linking to the calendar. Only when a calendar exists.

**The session form** gets an in-game start and end, only when a calendar exists. The session page and the story so far print the range under the title.

**The event form and page** get a date field and a printed date. `x-ui.game-date` is one new kit component: a day input, a month select, and a year input over one array property, with the field wrapper's label and error.

### Export and import

`calendars` joins `NESTED_TABLES` as `campaign.calendar`. The campaign section carries `calendar: {name, era, months, weekdays, moons, leap_every, leap_month, current: {year, month, day}}` or null. Sessions carry `in_game_start` and `in_game_end` as `{year, month, day}` or null; entities carry `happens_on` the same way. The reader runs every number through `Bounds` and drops a date that fails, counting it. No `VERSION` bump: keys are added, none change meaning.

The Markdown front matter gains `in_game_start` and `in_game_end` on a session and `happens_on` on an event, as the formatted string, because the vault is for reading and the JSON is the round trip.

## Decisions resolved

| Question | Decision |
|---|---|
| Lists as JSON or child tables | JSON, as a recorded exception to the models rule: read whole, saved whole, never queried by row, never gated. |
| One calendar per campaign | Yes. `campaign_id` is unique. A world has one reckoning; a second calendar is a future slice. |
| Where the math lives | `App\Support\Reckoning`, unit tested. `app/Calendar` stays the iCal feed. |
| How a date is stored | Three integer columns per date, read as a `GameDate` through a cast. Robust to calendar edits, sortable in SQL. |
| Years before 1 | Refused. The arithmetic for negative years is a slice of its own. |
| Moon phase origin | `offset` is days since the last new moon at 1/1/1. Zero means a new moon on the first day. |
| Leap rule | One rule: every N years, month M has one more day. Enough for Earth and for most invented worlds. |
| Events | A new `EntityType::Event`, dated by three columns, undated allowed. |
| A session's date | A start and an optional end. End before start is refused. |
| Who edits and advances | GM roles, through `CampaignPolicy::update`. Every member reads. |
| Visibility | The calendar and the date are world facts, visible to every member. Events and sessions on the grid and the timeline go through their own gates, in the query. |
| Pagination on the timeline | None. A timeline is read whole. |
| New tables | One: `calendars`. |
| New kit components | One: `x-ui.game-date`. |
| Broadcasts | None. |

## Implementation Phases

### Phase 0: A calendar a GM can define, and a day the party can read

Deliverables:
- `App\Support\Reckoning\{Reckoning, GameDate, MoonPhase, Bounds}`.
- Migration `create_calendars_table`, `App\Models\Calendar`, `Campaign::calendar()`, factory.
- `app/Actions/Calendars/`: `SaveCalendar`, `AdvanceDate`, `SetCurrentDate`.
- `App\Livewire\Calendars\Edit` and `App\Livewire\Calendars\Show`, routes, nav link, the dashboard card.
- `calendars` joins the export as `campaign.calendar`, the reader validates it, the importer writes it.

Tests: `tests/Unit/Reckoning/ReckoningTest.php` — day numbers across month ends and leap years; `dateAt` inverts `dayNumber` for a thousand days; the weekday cycles through leap days; a moon at 8 days is new on day 0 and full on day 4; the formatted string with and without weekdays and an era; `earth()` puts 29 February in a leap year. `tests/Feature/Calendars/CalendarTest.php` — a GM creates a calendar and the dashboard names today; a player may not; a current date outside the calendar is refused; a GM advances the day across a month end and a year end; a GM sets the date; the grid shows the right number of cells and starts on the right weekday; the export nests the calendar and the round trip restores it.

Success: the dashboard says it is the third of Harvestmoon, and the calendar shows the moon.

### Phase 1: Dates on events and sessions, and the timeline

Deliverables:
- `GameDateCast`, the six session columns and the three entity columns, `EntityType::Event` with its icon.
- `x-ui.game-date`, the fields on the session form and the event form, the printed date on the session page, the story, and the event page.
- `App\Livewire\Timeline\Index` and its route, the events and sessions on the calendar grid.
- The dates in the export, the reader, the importer, and the Markdown front matter.

Tests: `tests/Feature/Calendars/TimelineTest.php` — **a GM-only event's name is absent from a player's timeline HTML and snapshot**; a hidden session is absent; a player sees a revealed event and a visible session in world order across years; the "Today" marker sits between the right rows; an undated event is absent. `tests/Feature/Sessions/InGameDateTest.php` — a GM sets a range on a session and the session page and story print it; an end before the start is refused; a partial triple is refused; a date outside the calendar is refused; the range survives the round trip and appears in the front matter. `tests/Feature/Entities/EventTest.php` — an event is created with a date, listed under Events, printed on its page, and exported with `happens_on`.

Success: the timeline shows the harbor fire two weeks before the spring tide.

### Phase 2: The seeder, the rules, and the pass

- The seeder gives the Drowned Duchy "The Tide Reckoning": ten months, six weekdays, two moons, a leap day, in-game dates on sessions 1 to 3, and three events, one GM-only.
- Empty states: no calendar, a month with nothing on it, a timeline with nothing dated.
- The tablet pass at 1024px and 768px, dark and light.
- Record the rules. Pint, Larastan, the full suite, `npm run build`.

## Alternative Approaches Considered

- **Three child tables for months, weekdays, and moons.** Rejected: three joins on every page for lists that are only ever read whole.
- **One packed day-number column per date.** Rejected: a calendar edit would move every date; the triple keeps its meaning.
- **Dates as a free-text field.** Rejected: nothing sorts, nothing lands on a grid.
- **Events as a separate `events` table.** Rejected: an event wants a body, a visibility, wiki links, and an export, which is an entity.
- **A calendar per session or per entity.** Rejected: a world has one reckoning.

## Acceptance Criteria

### Functional

- [x] A GM defines a calendar with months, weekdays, moons, a leap rule, an era, and the current date.
- [x] A GM advances the date by a day and sets it outright; every member sees today on the dashboard and the calendar.
- [x] The calendar screen shows the month grid, the weekdays, the moon phases, and the day's events and sessions.
- [x] An event is an entity type with an optional date.
- [x] A session carries an in-game start and end, printed on the session, the story, and the timeline.
- [x] The timeline lists dated events and sessions in world order with a marker for today.
- [x] The calendar and every date survive the round trip and appear in the Markdown front matter.

### Non-functional

- [x] **A GM-only event's name never reaches a player's timeline or grid HTML or snapshot.**
- [x] **A DM-only session never reaches a player's timeline or grid.**
- [x] Every list on the timeline and the grid is filtered in the query, never in the template.
- [x] The reckoning is pure and unit tested with no database.
- [x] The calendar, timeline, and edit screens work at 1024px and 768px, dark and light, with no sideways scroll.

### Quality gates

- [x] Pest suite green on SQLite locally: 1,081 tests. PostgreSQL in CI is the pull request's job.
- [x] Larastan level 6 clean. Pint clean before every commit.
- [x] One new `x-ui.*` component. No new dependency.

## Dependencies & Risks

| Risk | Mitigation |
|---|---|
| A wrong day number | One pure class, a unit test per rule, and an inversion test over a thousand days. |
| A calendar edit strands a date | The triple keeps its meaning; `format()` prints "month 13" rather than failing. |
| The grid leaks a hidden event | Both queries go through the existing gates; a leak test on the grid and the timeline. |
| A float moon cycle drifts | `fmod` on the day number, never an accumulated sum. |

## What the browser pass found

Nothing broken in the layout. The calendar, the timeline, the edit form, the event page, the session page, and the dashboard were checked at 1024px and 768px, dark and light, as the GM of the seeded world. No page scrolls sideways at either width and the console is clean. At 768px the month grid scrolls inside its own card, as the tablet rule asks, and the "Move the day" card stacks its buttons over the date form. The moon symbols are emoji and render on the grid, in the header, and on the dashboard card in both themes.

**The buttons were driven through the page's own DOM events, as slice 11's were.** The tool's synthetic click on a Livewire button reached nothing; `element.click()` on the same buttons did. Through those events "Advance a day" moved the header from Duskday, 4 Highwater to Sunday, 5 Highwater and the row in the database with it, "A day back" returned it, and "Next month" turned the grid to Netmend. The Livewire tests cover the same calls. What has not been seen is a human's click on this screen.

The demo world on the local install predates this slice, so the calendar, the three events, and the session dates were added to it by hand for the pass with the same values the seeder now writes.

## Future Considerations

- **Years before 1.** Negative day numbers and floor division through the leap count.
- **A second calendar.** A campaign with two reckonings and a conversion between them.
- **Event ranges.** An event with an end date, drawn as a bar on the timeline.
- **A date on every entity.** "Born on", "founded on". The cast makes it a column and a field.
- **Session in-game date on the run screen.** Advance the day from the table.

## References

### Internal

- Slice 10 plan: `docs/plans/2026-09-07-feat-scheduling-rsvp-reminders-plan.md` — the iCal feed, and why `app/Calendar` is taken
- Patterns to copy: `app/Livewire/Campaigns/Settings.php`, `app/Livewire/Sessions/Story.php`, `app/Actions/Clocks/Segments.php`, `app/Support/Dice/DiceFormula.php`, `tests/Unit/Dice/DiceFormulaTest.php`
- Project rules: `.ai/rules/models.md`, `.ai/rules/campaigns.md`, `.ai/rules/routes.md`, `.ai/rules/livewire.md`, `.ai/rules/views.md`, `.ai/rules/tests.md`
