---
title: "feat: The next game, and the four ways a party says when"
type: feat
date: 2026-09-07
brainstorm: docs/brainstorms/2026-09-02-demgem-campaign-manager-brainstorm.md
follows: docs/plans/2026-09-04-feat-campaign-archive-plan.md
---

# feat: The next game, and the four ways a party says when

## Overview

The tagline is prep, play, recap, repeat. Nine slices built the first three verbs. Nothing in the app helps the fourth one happen.

| Feature | What it adds |
|---|---|
| RSVP | Yes, no, or maybe on a planned session, from the session page. The GM sees the count. |
| Attendance | Once a session is played, the GM ticks who was there, prefilled from the yeses. |
| A calendar feed | One tokenised `.ics` URL per user, for Google Calendar, Apple Calendar, and Outlook. Every campaign they belong to, filtered by what they may see. |
| Reminders | One email before each session, at a lead time the GM chooses per campaign, to every member who has not turned it off or said no. |
| A poll | A planned session with no date carries candidate times. Members tick the ones they can make, and the GM picks one. |

When this slice is done the GM plans session 12 with three candidate Thursdays, the party ticks two of them, the GM picks one, everybody's phone gets the event, four people say yes and one says maybe, an email goes out the day before, and afterwards the GM records that the maybe turned up.

**On scope.** Phases 0 and 1 are a release: a party can answer and a GM can record. Phases 2 and 3 are a second: the feed and the reminder, which are the two that leave the app. Phase 4 is a third: the poll. None of the three is cuttable, because all four features were asked for, but each is a release on its own and the order is the order of value.

## Problem Statement

**A session has a date and nobody is asked about it.** `game_sessions.scheduled_at` has existed since slice 2, the dashboard shows the next one, and the sessions index lists them. What it cannot say is whether anyone is coming. Every GM in the audience runs this on a Discord thread: "Thursday still good?", five replies over three days, and the one who did not reply is the one who does not turn up.

**The date lives in one place and the party lives in another.** A player's calendar is on their phone, and the session is on a web page they open on game night. A schedule that is not on the phone is a schedule that gets forgotten, and the reminder that fixes that today is a human being typing "tomorrow!" into the same Discord thread.

**Before the date exists there is nothing at all.** `SessionStatus::Planned` describes itself as "on the calendar, or waiting for a date", and the waiting half is a when2meet link somebody else hosts. The app knows who the members are, knows which sessions are dateless, and offers no help joining the two.

**And the app has never sent an email or run a schedule.** Mail is `log`, email verification is off, and `routes/console.php` holds the inspire command. The reminder is the first feature that leaves the app on its own, and it needs a process nobody has had to run yet. That is an operational cost for a self-hoster, and the plan has to pay it honestly rather than hide it.

## Proposed Solution

**RSVP and attendance are two columns on one row.** A session and a member have two facts between them: what the member said beforehand and whether they were there. Both are scalars per pair, and `.ai/rules/models.md` says a scalar gets a column. `session_rsvps` holds `rsvp` and `attended`, one row per member per session, keyed on `user_id` the way `dice_rolls` is.

**A poll lives on a dateless session, because that is what a dateless session is.** The status already says "waiting for a date". The GM adds candidate times to it, members tick the ones they can make, and picking a winner writes `scheduled_at` and deletes the options. The session page shows the poll until there is a date and the RSVP card after. There is no `polls` table and no poll screen, because a poll is not a thing a GM navigates to; it is the state a session is in before Thursday is chosen.

**The feed is per user, in UTC, and carries nothing a player could not already see.** `/calendar/{token}.ics` is unauthenticated, because calendar apps cannot log in, and it is safe for the same reason an invite link is: the token is 40 random characters and the page it opens holds only what its owner may see. Each campaign's sessions go through `GameSession::visibleTo()` under that member's role. The event carries the number, the title, the campaign name, and a link. Prep, secrets, and recaps never enter it, whatever the role, because a calendar entry is not a place to keep a secret and the file gets forwarded.

Times are written in UTC with a `Z` suffix. Every calendar app converts to its owner's zone, which is the per-user timezone feature the README promised for later, delivered without a column.

**A reminder is one email, once, and the date moving resets it.** `game_sessions.reminder_sent_at` is the whole idempotency story. The observer clears it whenever `scheduled_at` changes, so a session moved from Thursday to Saturday reminds everyone again on Friday and a session left alone never reminds twice. The scheduler asks every fifteen minutes for planned sessions inside their campaign's lead window, unsent, and still in the future. The last clause is what stops a scheduler that was down for a week from mailing the party about last Thursday.

**The scheduler is a service, shaped like the worker.** One process per container is what makes `docker compose logs scheduler` and `restart: unless-stopped` mean anything, and it is the reasoning `compose.yaml` already wrote down for Reverb. `schedule:work` rather than cron, because a container with cron in it needs a second process manager and the image has none.

## Technical Approach

### No new dependency

Nothing to install. The calendar format is RFC 5545, and what this feed needs of it is one `VCALENDAR`, one `VEVENT` per session, eight properties, a line fold at 75 octets, and four escape rules. That is a class of a hundred lines whose rules this project then owns, against a package for a problem that is smaller than the package. The mail is a Laravel `Mailable` with a Markdown template, which the framework already renders. The schedule is `Schedule::command()` in `routes/console.php`.

### What slices 1 to 9 give us for free

| Piece | Reuse |
|---|---|
| `GameSession::visibleTo()` | The gate on every RSVP list, every feed, and every reminder recipient list. |
| `GameSessionPolicy` and `roleFor()` | RSVP, attendance, and the poll are authorised against the session, so a player on a GM-only session cannot answer a question they cannot see. |
| `Campaign::timezone` | The time in the email and on the session page. The feed does not need it. |
| `CampaignMember` | The list of who can answer, who gets reminded, and the row that carries the reminder switch. |
| `Sessions\Show` | The card goes on the page that already exists, in the column beside the recap. |
| `Campaigns\Settings` | Two fields in a new section beside the timezone. |
| `Campaigns\Members` | The reminder switch, on the page that lists the people it concerns. |
| `Profile\Edit` | The feed URL, copy, and regenerate. |
| `ExportCampaign` and `ImportReport` | Three new nested sections and two new counted losses, in the shape the dice log set. |
| `WriteCampaignMarkdown` | A session's front matter gains `attended`. |
| The queue and the worker | The mail is `ShouldQueue`, so a reminder tick never waits on SMTP. |

### The data

```
session_rsvps
  id                 bigint
  campaign_id        ulid  -> campaigns, cascade
  game_session_id    ulid  -> game_sessions, cascade
  user_id            bigint -> users, cascade
  rsvp               string(10) nullable   yes | no | maybe
  attended           boolean nullable
  timestamps
  unique (game_session_id, user_id)
  index  (campaign_id, user_id)

session_date_options
  id                 ulid
  campaign_id        ulid  -> campaigns, cascade
  game_session_id    ulid  -> game_sessions, cascade
  starts_at          timestamp
  position           unsigned int
  timestamps
  unique (game_session_id, starts_at)

session_date_votes
  id                       bigint
  campaign_id              ulid  -> campaigns, cascade
  session_date_option_id   ulid  -> session_date_options, cascade
  user_id                  bigint -> users, cascade
  timestamps
  unique (session_date_option_id, user_id)

campaigns        + reminder_lead_hours   unsigned small int nullable   null = off; 24, 48, 168
campaigns        + session_length_minutes unsigned small int default 240
campaign_members + reminders_enabled     boolean default true
game_sessions    + reminder_sent_at      timestamp nullable
users            + calendar_token        string(40) unique nullable
```

`rsvp` is nullable because a GM can mark attendance for somebody who never answered, and `attended` is nullable because a session that has not been played has no attendance yet. A row with both null is deleted rather than kept.

A vote row existing means "I can make this". There is no `available` column and no third state, because a member who cannot make a time does not tick it and a member who has not looked has not looked. The GM's grid shows a tick or a blank per name per column and a count per column, which is what the when2meet page everyone is replacing shows.

`session_length_minutes` exists because a calendar event needs an end and a session has never had one. It is a campaign setting rather than a session field because a table plays for about as long every week, and a per-session end time is a P2 row nobody asked for.

`calendar_token` is nullable and minted on first request, so the profile page never shows a URL nobody asked for and an existing user gets one the first time they look.

### The RSVP card

`Sessions\Show` gains a nested component, `Sessions\Attendance`, with `InteractsWithCampaign` and its own `enterCampaign()`, because it writes and `.ai/rules/livewire.md` says a writing child re-checks membership itself.

Three states, decided in `render()`:

- **Dateless and planned:** the poll (Phase 4). Until then, a sentence: "No date yet."
- **Dated and planned:** every member the session is visible to, with their answer or a blank, and the viewer's own row as three buttons. The GM's header reads the count: "4 yes · 1 maybe · 2 not answered".
- **Played:** every member with a checkbox the GM can tick, prefilled from `attended` when set and from `rsvp === yes` when not, and read-only for everyone else. Cancelled sessions show the answers and nothing to press.

The member list is `CampaignMember` rows whose role passes `GameSession::isVisibleTo()`, left-joined to their `session_rsvps` row. A player on a `Dm`-visibility session is not in the list, because they cannot see the session, and the card does not run for them at all because `Sessions\Show` already 404s first.

One query for the members and one for the rows. `Model::shouldBeStrict()` is on.

### The feed

`App\Calendar\IcsCalendar` and `App\Calendar\IcsEvent`, plain classes in a new namespace beside `App\Markdown`, because the format has rules and the rules deserve a file with tests rather than a Blade with `\r\n` in it.

The writer owns:

- `CRLF` line endings, and folding at 75 octets with a leading space on the continuation, counted in bytes rather than characters because a title can hold an em dash.
- Escaping `\`, `;`, `,`, and newlines in text values, in that order.
- `UID` as `session-{ulid}@{host}`, stable across fetches so a calendar updates an event rather than duplicating it.
- `DTSTAMP`, `DTSTART`, and `DTEND` in UTC with the `Z` suffix. `DTEND` is `DTSTART` plus the campaign's session length.
- `STATUS:CONFIRMED` for planned and played, `STATUS:CANCELLED` for cancelled, so a calendar app strikes the event through rather than leaving it.
- `SUMMARY` as "Session 12: The Salt Cathedral · Vell" and `DESCRIPTION` as the link. Nothing else.

`CalendarFeedController` resolves the user from the token, walks their memberships, and for each campaign loads `GameSession::visibleTo($member->role)` with a date. It is `throttle:30,1`, returns `text/calendar; charset=utf-8`, and a wrong token is a 404 with no body, the same shape as a dead invite link.

The route sits outside the `auth` group and outside the campaign prefix. The token is the credential, and the name a calendar app sees is `demgem.ics`.

`Profile\Edit` shows the URL when a token exists, with a copy button, and **Get a calendar link** when it does not. **Reset the link** mints a new token and says the old one stopped working, because that is the one thing a user needs to know.

### The reminder

`App\Mail\SessionReminder`, `ShouldQueue`, a Markdown mail. It carries the campaign name, the session label, the time in the campaign's zone with the zone named, the RSVP link to the session page, and one line at the bottom: "Turn these off from the members page." It carries no prep, no recap, and nothing else from the session, because mail is forwarded and mail is searched.

`App\Actions\Sessions\SendSessionReminders` does the work and `demgem:send-reminders` calls it, so the tests run the action and the schedule runs the command. The action:

1. Selects planned sessions, not trashed, with `reminder_sent_at` null, `scheduled_at > now()`, and `scheduled_at <= now() + campaign.reminder_lead_hours`, joined to campaigns where the lead is not null. One query.
2. For each, sets `CurrentCampaign` the way the import command does, loads the members whose role passes `isVisibleTo()`, whose `reminders_enabled` is true, and whose RSVP is not `no`.
3. Queues one mail per member and writes `reminder_sent_at`. The write happens after the queueing so a crash between them sends twice rather than never, which is the right way round for a reminder.

The schedule: `Schedule::command('demgem:send-reminders')->everyFifteenMinutes()->withoutOverlapping()`.

`GameSessionObserver::saving()` clears `reminder_sent_at` when `scheduled_at` is dirty. That is the only place it is cleared.

The lead time is a select on campaign settings: Off, One day before, Two days before, A week before. Stored as hours. A new campaign is Off, because the mailer is `log` until a self-hoster changes it, and a reminder that goes to a log file is worse than no reminder because the GM believes it went.

### Docker and the README

`compose.yaml` gains:

```yaml
  scheduler:
    build: { context: ., dockerfile: Dockerfile }
    restart: unless-stopped
    env_file: [.env.docker]
    command: ["php", "artisan", "schedule:work"]
    healthcheck:
      test: ["CMD-SHELL", "pgrep -f 'artisan schedule:work' > /dev/null"]
    volumes: [storage:/app/storage]
    depends_on: { app: { condition: service_healthy } }
```

The README gains a row in the services table, a paragraph under a new "Reminders" heading saying that `MAIL_MAILER=log` means the reminder is written to the log and nobody receives it, and the four `MAIL_*` keys a self-hoster sets to change that. `.env.docker.example` gains the same keys with `log` as the value and a comment.

`php artisan dev` is unchanged. A developer who wants to see a reminder runs `php artisan demgem:send-reminders` by hand and reads `storage/logs/laravel.log`.

### The poll

`Sessions\Attendance` renders the poll when the session is planned and dateless. The GM adds a candidate time from a datetime input in the campaign's zone, which the form converts to UTC the way `Sessions\Form` already does. Options render as columns, members as rows, and a member's own row holds a checkbox per column.

`App\Actions\Sessions\AddDateOption`, `RemoveDateOption`, `ToggleDateVote`, and `PickDate`. `PickDate` writes `scheduled_at` through `UpdateSession` and deletes the options in the same transaction. The options cascade to the votes.

A session that gains a date any other way, through the edit form, keeps its options until the next `PickDate` or until somebody deletes them. That is a gap the plan chooses over a second observer: a GM who types a date over a live poll may have meant to, and deleting five members' answers on a form save is the kind of surprise that costs trust. The card shows "This session has a date now" above the old poll with a **Clear the poll** button, GM only.

### Export and import

| Table | Export | Import |
|---|---|---|
| `session_rsvps` | Nested as `sessions[].attendance`: member name, `rsvp`, `attended`. | Skipped and counted. The file names a person this install does not have. |
| `session_date_options` | Nested as `sessions[].date_options`: `starts_at`, `position`, and its votes as member names. | Options restored with fresh ids; votes skipped and counted. |
| `session_date_votes` | Inside the option, by name. | Skipped, in the same count as RSVPs. |
| `campaigns.reminder_lead_hours`, `session_length_minutes` | Columns on the campaign section. | Restored. |
| `campaign_members.reminders_enabled` | A column on the member. | Moot: the importer makes one member. |
| `game_sessions.reminder_sent_at` | A column on the session. | Restored, so a restored campaign does not re-remind a session whose reminder already went. |
| `users.calendar_token` | Not campaign-scoped, and a credential. Never exported. | — |

`ImportReport` gains `$answers`, counted across RSVPs and votes, with the label "N answers about dates and attendance will be left behind". It is loss five, and it is the dice log's loss with a different noun: the file cannot say who a person is on this install.

Adding keys does not bump the format version, by the rule slice 9 wrote down.

`WriteCampaignMarkdown::session()` adds `attended` to the front matter as a list of names when any row has `attended` true, so an Obsidian vault records who was there.

### Screens that change

| Screen | Change |
|---|---|
| Session page | The "Who's coming" card, in the column beside the recap. |
| Sessions index | Under each dated planned session, "4 yes · 1 maybe" and the viewer's own answer as a small badge. |
| Dashboard | The next-session card gets the same line. |
| Members | A switch at the top, above the list, for the viewer's own reminders: "Email me before each session". Not on other members' rows, because it is theirs to set. |
| Campaign settings | A "Scheduling" section: reminder lead time and session length, beside the timezone. |
| Profile | A "Calendar" section: the feed URL with copy, or the button to get one, and reset. |

No new `x-ui.*` component. The RSVP buttons are `x-ui.button` in a group, the poll grid is a table, the switch is the checkbox the kit has.

## Decisions resolved

| Question | Decision |
|---|---|
| RSVP and attendance as one table or two | One. Two scalars about the same pair, so two columns on one row. |
| Keyed on `campaign_members.id` or `user_id` | `user_id`, like `dice_rolls`. A member who leaves and returns keeps their history, and the export by name reads the same either way. |
| Who can answer | Every member the session is visible to, spectators included. A spectator who says yes is telling the GM something useful. |
| A "maybe" on attendance | No. Attendance is a boolean the GM sets afterwards; maybe is a word for beforehand. |
| Where the poll lives | On the dateless planned session. No `polls` table, no poll screen. |
| Vote states | One: a row means yes. No "if needed", because it is a second column nobody asked for and the GM's eye already reads the grid. |
| Picking a winner | Writes `scheduled_at` and deletes the options in one transaction. |
| Per-user timezone | Not added. The feed is UTC and calendar apps convert. The email and the page use the campaign's zone, as everything does today. |
| Session end time | `campaigns.session_length_minutes`, default 240. A campaign setting, not a session field. |
| Feed scope | One feed per user across every campaign, each filtered under that campaign's role. |
| Feed contents | Number, title, campaign name, link. Never prep, secrets, or recap, for any role. |
| Feed auth | A 40-character token in the URL, unauthenticated, throttled, regenerable. A wrong token is a 404. |
| Reminder lead | Per campaign: Off, 24, 48, or 168 hours. Off by default. |
| Reminder opt-out | Per member per campaign, `campaign_members.reminders_enabled`, set by that member only. |
| Reminder recipients | Members who can see the session, have reminders on, and did not say no. GMs included. |
| Reminder idempotency | `game_sessions.reminder_sent_at`, cleared by the observer when the date changes. |
| Reminder timing | Every fifteen minutes, sessions inside the window and still in the future. A session already past is never reminded. |
| Mail or Notification | A queued `Mailable`. One channel, no routing, no `notifications` table. |
| Scheduler | A `scheduler` compose service running `schedule:work`. Not cron, not a second process in the worker. |
| Mail in development | `log`, as today. The README says so and says what to set. |
| Broadcasts | None. Sessions are not on the live table, and an RSVP is not a thing anybody watches change. |
| New tables | Three: `session_rsvps`, `session_date_options`, `session_date_votes`. |
| New columns | Five, listed above. |
| New namespaces | One: `App\Calendar`. One class family: `App\Mail`. |
| New kit components | None. |

## Implementation Phases

Each phase ends with a green suite. Phases 0 and 1 are a release; 2 and 3 are a second; 4 is a third.

### Phase 0: RSVP

Deliverables:
- Migration `create_session_rsvps_table`.
- `App\Enums\Rsvp` with Yes, No, Maybe, a label, and a badge variant.
- `App\Models\SessionRsvp` with `BelongsToCampaign`, `GameSession::rsvps()`, and the factory.
- `GameSessionPolicy::respond()`: any member the session is visible to.
- `app/Actions/Sessions/RespondToSession.php`: writes the viewer's `rsvp`, deletes the row when both columns go null.
- `App\Livewire\Sessions\Attendance`, nested in `Sessions\Show`, with the dated-and-planned state.
- The headcount line on the sessions index and the dashboard's next-session card.
- `session_rsvps` joins `ExportCampaign::NESTED_TABLES` as `sessions[].attendance`, and `ImportReport` counts the loss, in this commit.

Tests: `tests/Feature/Sessions/RsvpTest.php` — a player says yes, changes to maybe, and clears; the GM sees "1 maybe"; a second player's answer does not overwrite the first; **a player cannot answer a GM-only session and its member list never includes them**; a cancelled session shows answers and refuses new ones; the export nests the answers by name; the importer skips them and the report says how many; `ExportCoverageTest` passes.

Success: a player opens Thursday's session and presses Yes.

### Phase 1: Attendance

Deliverables:
- `GameSessionPolicy::recordAttendance()`: GM roles.
- `app/Actions/Sessions/RecordAttendance.php`: sets `attended` for one member.
- The played state of the card: checkboxes for the GM, prefilled from `attended`, then from `rsvp === yes`; a read-only list for everyone else.
- `WriteCampaignMarkdown::session()` writes `attended` to the front matter.
- The seeder marks attendance on its played sessions.

Tests: `tests/Feature/Sessions/AttendanceTest.php` — the GM ticks two of four and unticks one; a checkbox is prefilled from a yes and not from a maybe; a player may not record; **a player's copy of the card carries no checkbox markup**; the Markdown front matter lists the names; a session with nobody marked has no `attended` key.

Success: after the game the GM ticks four names in ten seconds and the story-so-far has a record of who was there.

### Phase 2: The calendar feed

Deliverables:
- Migration adding `users.calendar_token` and `campaigns.session_length_minutes`.
- `App\Calendar\IcsCalendar` and `IcsEvent`: the writer, with folding and escaping.
- `App\Http\Controllers\CalendarFeedController` at `/calendar/{token}.ics`, `throttle:30,1`.
- `User::calendarToken()` mints on first call; `User::resetCalendarToken()`.
- The Calendar section on the profile page.
- Session length on campaign settings, beside the timezone.

Tests: `tests/Unit/Calendar/IcsCalendarTest.php` — a 200-character summary folds at 75 octets and unfolds to the original; a title with `;`, `,`, `\`, and a newline escapes and round-trips; an em dash does not split mid-character; times carry `Z`; the file ends in `END:VCALENDAR\r\n`. `tests/Feature/Sessions/CalendarFeedTest.php` — a player's feed holds the sessions they can see across two campaigns and **not a GM-only session and not any prep, secret, or recap text**; a GM's feed holds the GM-only session; cancelled carries `STATUS:CANCELLED`; `DTEND` is start plus the campaign's length; a wrong token is a 404; the reset makes the old URL a 404; the route is throttled; the `UID` is stable across two fetches.

Success: a player pastes one URL into Google Calendar and every session across both their campaigns appears in their own timezone.

### Phase 3: Reminders

Deliverables:
- Migration adding `campaigns.reminder_lead_hours`, `campaign_members.reminders_enabled`, and `game_sessions.reminder_sent_at`.
- `App\Mail\SessionReminder`, `ShouldQueue`, with a Markdown template in `resources/views/mail/`.
- `app/Actions/Sessions/SendSessionReminders.php` and `App\Console\Commands\SendSessionRemindersCommand` as `demgem:send-reminders`.
- The schedule in `routes/console.php`.
- `GameSessionObserver::saving()` clears `reminder_sent_at` when the date moves.
- Reminder lead on campaign settings. The reminder switch on the members page.
- The `scheduler` service in `compose.yaml`, the `MAIL_*` keys in `.env.docker.example`, and the README's "Reminders" section.
- `reminder_lead_hours`, `session_length_minutes`, `reminders_enabled`, and `reminder_sent_at` join the export as columns.

Tests: `tests/Feature/Sessions/ReminderTest.php`, with `Mail::fake()` and a frozen clock — a session 20 hours out on a 24-hour campaign mails three members and not the one who said no and not the one who turned reminders off; running the action again sends nothing; moving the date clears the stamp and the next run sends again; a session on an Off campaign sends nothing; **a player on a GM-only session is not mailed**; a session already in the past is stamped nothing and mailed nobody; the mail names the campaign, the session, and the time in the campaign's zone, and **contains no prep, secret, or recap text**; the command runs the action; the schedule lists the command every fifteen minutes.

Success: the day before session 12, five inboxes get one email each, and the GM did nothing.

### Phase 4: The poll

Deliverables:
- Migrations `create_session_date_options_table` and `create_session_date_votes_table`.
- `App\Models\SessionDateOption` and `SessionDateVote`, `GameSession::dateOptions()`, factories.
- `GameSessionPolicy::poll()`: GM roles add, remove, and pick; `respond()` covers voting.
- `app/Actions/Sessions/`: `AddDateOption`, `RemoveDateOption`, `ToggleDateVote`, `PickDate`, `ClearDateOptions`.
- The dateless state of the card: the grid, the add form, the pick button, and the "has a date now" notice.
- Options join the export nested as `sessions[].date_options` with votes by name; the importer restores options and counts votes into the same loss.

Tests: `tests/Feature/Sessions/DatePollTest.php` — the GM adds three times and removes one; a duplicate time is refused; two players tick different columns and the counts are per column; a player cannot add or pick; **a player cannot vote on a GM-only session**; picking writes the date in UTC from the campaign's zone and deletes every option and vote in one transaction; a failed pick leaves the poll intact; a session given a date through the edit form keeps its poll and shows the notice; clearing deletes it; the export nests options with votes by name and the round trip restores the options with fresh ids and no votes.

Success: three Thursdays, five ticks, one press, and session 12 has a date.

### Phase 5: Polish

- The seeder: the demo campaign's next session gets two yeses and a maybe, its last played session gets attendance, a dateless session gets a poll with two options and three votes, and the campaign's lead time is a day.
- Empty states: a dated session nobody has answered, a played session with nobody marked, a poll with no options, a poll with options and no votes, a profile with no token.
- The tablet pass at 1024px and 768px, dark and light: the poll grid scrolls sideways inside its own container and nothing else does.
- Record the rules: the reminder stamp and where it is cleared; the feed carries no prose; a poll is a dateless session.
- Pint, Larastan, the full suite, and `npm run build`.

## Alternative Approaches Considered

- **A `polls` table with its own screen.** Cleaner on a whiteboard. Rejected: it is a second thing to link, a second empty state, and a second place to look, for a question that is only ever asked about one session. The dateless session already is the poll.
- **RSVP on the `game_session_entities` pivot, since a PC is an entity.** Rejected: the person answering is a user, not their character, and spectators and co-GMs have no PC.
- **A per-campaign feed instead of a per-user one.** Rejected: a player in three campaigns would paste three URLs, and each would need its own token. One URL per person is what calendar apps expect and what people copy once.
- **A per-user timezone column, so the email reads in the recipient's zone.** Rejected for now: it is a column, a profile field, and a change to every timestamp on every page, for one email. The feed already solves the case that matters most. It stays on the README's "later" line.
- **`spatie/icalendar-generator`.** Well made. Rejected: no new dependency has been the rule for five slices, and the part of RFC 5545 this feed uses fits in one tested class.
- **Laravel Notifications instead of a Mailable.** The framework's default. Rejected: one channel, no per-user routing, and the `notifications` table would be a campaign-adjacent table with no campaign id, which is exactly what `ExportCoverageTest` cannot see. A Mailable is smaller and does the same thing.
- **Cron in the app container.** Rejected: the image runs one process, and adding cron means adding a supervisor. A service is what the worker and Reverb already are.
- **The GM sends reminders by hand.** Rejected in the brainstorm: the GM remembering is the problem.
- **Broadcasting RSVP changes to the live table.** Rejected: the table is for the four hours of play, and nobody watches an RSVP count change.
- **Clearing the poll when a date is typed into the edit form.** Rejected: five members' answers deleted by a form save is a surprise, and the notice with a button is the honest version.

## Acceptance Criteria

### Functional

- [x] A member says yes, no, or maybe on a dated planned session and changes their mind.
- [x] The GM sees the count on the session page, the sessions index, and the dashboard.
- [x] The GM marks attendance on a played session, prefilled from the yeses.
- [x] A user gets a calendar URL from their profile, and every session they can see in every campaign appears in their calendar app in their own timezone.
- [x] Resetting the calendar link makes the old URL stop working.
- [x] The GM sets a reminder lead time, and each member gets one email before the session.
- [x] A member turns reminders off for one campaign and gets none from it.
- [x] Moving a session's date sends a fresh reminder; leaving it alone never sends two.
- [x] The GM adds candidate times to a dateless session, members tick the ones they can make, and picking one gives the session its date.
- [x] RSVPs, attendance, options, and votes join the export; options survive the round trip; the report counts the answers that do not.

### Non-functional

- [x] **A player cannot answer, vote on, or be listed against a session they cannot see.**
- [x] **The calendar feed contains no prep, secret, live note, GM note, or unpublished recap, for any role.**
- [x] **The reminder email contains none of those either.**
- [x] **A player's copy of the card carries no attendance checkbox and no poll controls in its HTML or Livewire snapshot.**
- [x] A wrong calendar token is a 404 with an empty body, and the route is throttled.
- [x] A session already in the past is never reminded, however long the scheduler was down.
- [x] The reminder action runs in a constant number of queries per session, whatever the campaign holds.
- [x] The RSVP card costs two queries. *(Three once the poll is on the page: members, answers, options with votes.)*
- [x] `Model::shouldBeStrict()` is on, so every new screen eager-loads.
- [x] The Docker stack starts with the scheduler and without a mailer, and the README says what a log mailer means.
- [x] Every new screen works at 1024px and 768px, dark and light, with no sideways scroll outside the poll grid. *(One bug found and fixed: see below.)*

### Quality gates

- [x] Pest suite green on SQLite locally: 1,024 tests. PostgreSQL in CI is the pull request's job.
- [x] Larastan level 6 clean. Pint clean.
- [x] No new `x-ui.*` component.
- [x] Every new query on the three tables goes through the session's visibility, and every write goes through `GameSessionPolicy`.
- [x] `npm run build` clean. No new JavaScript.
- [x] No new PHP or JavaScript dependency.

## Dependencies & Risks

| Risk | Mitigation |
|---|---|
| The feed leaks GM prose | It is structural: `IcsEvent` has four text fields and none of them is prose. A test asserts the feed body against every DM field of a seeded session. |
| A token is guessed | 40 characters from `Str::random()`, a 404 that says nothing, and `throttle:30,1`. An invite link has lived on the same footing since slice 1. |
| The scheduler double-sends | `reminder_sent_at` and `withoutOverlapping()`. A crash between queueing and stamping sends twice, which is the direction chosen on purpose. |
| The scheduler is down for a week | `scheduled_at > now()` in the query. Last week's session is never reminded. |
| A self-hoster enables reminders with `MAIL_MAILER=log` | Off by default, and the README's "Reminders" section says what `log` means in the first sentence. |
| A date typed over a live poll deletes answers | It does not. The poll stays, with a notice and a GM-only clear button. |
| `session_length_minutes` is wrong for one session | It is a default, not a truth. A per-session end time is a P2 row and a column when somebody asks. |
| The poll grid at 768px | It scrolls inside its own container from the first commit, and the tablet pass checks the page does not. |
| The slice runs long | Three release boundaries: after Phase 1, after Phase 3, and after Phase 4. |

## What the browser pass found

**The poll grid pushed the whole page sideways at 768px.** The grid scrolls inside its own `overflow-x-auto` div, but the session page's layout was `grid` with no column definition below `xl`, and an auto column grows to the min-content of its children, which is the table's `min-w-max`. Every card on the page went to 870px wide. The grid is `grid-cols-[minmax(0,1fr)]` at every width now, and both columns are `min-w-0`.

**The poll did not belong in the 18rem side column.** At 1280px it showed one date of three with the other two behind a scrollbar, which defeats a grid whose point is names against dates side by side. `Sessions\Show` renders the card in the main column under the recap while the session is polling, and in the aside once it has a date and the card is a list. One component, two slots, chosen by `GameSession::isPolling()`.

**The Copy button threw an Alpine expression error.** `@js($calendarUrl)` inside an `x-on:click` attribute renders quotes that close the attribute. The button reads `$refs.feed.value` from the input beside it instead, which is also what the user sees.

**What held.** No sideways scroll on the session page, the sessions index, the dashboard, the members page, settings, or the profile at 1024px and 768px, dark and light, for the GM and for a player. A player's copy of the poll has a checkbox on their own row and a tick or a dot on everyone else's; their copy of a played session has badges and no boxes. The feed was fetched from the running app: 200, `text/calendar; charset=utf-8`, three events with folded description lines and stable UIDs. The reminder email rendered from the seeded campaign names the session, the campaign, and the time in the campaign's zone, and nothing else.

**What was not checked.** A real calendar app subscribing to the feed, and a real mailer delivering the reminder. The feed is RFC-shaped and the mail is a Markdown mailable, and the tests cover both, but neither has been seen in Google Calendar or an inbox from this slice.

## Future Considerations

- **A per-user timezone.** The column the README promised. The feed made it less urgent; the email is where it would show first.
- **Availability before a session exists.** "When are you free in October?" with no session yet. The poll would grow a campaign-level home, and that is the day the `polls` table earns itself.
- **A reminder for the poll.** "Three people have not answered" to the GM. The same command, a second query.
- **Attendance on the story-so-far.** The recaps page could name who was there. The front matter already carries it.
- **An XP or milestone log per session.** The brainstorm's other row in this table. It is a column on the session or a child table, and it is not this slice.
- **Discord.** A webhook that posts the reminder to a channel is the same action with a second sender, and the P2 row for it is next to this one.

## References

### Internal

- Slice 2 plan: `docs/plans/2026-09-02-feat-sessions-prep-play-recap-plan.md` — the session, its visibility, and its policy
- Slice 9 plan: `docs/plans/2026-09-04-feat-campaign-archive-plan.md` — the format policy and the loss report this slice extends
- Patterns to copy: `app/Livewire/Sessions/LiveNotes.php` (a nested writer), `app/Console/Commands/ImportCampaignCommand.php` (setting `CurrentCampaign` outside HTTP), `app/Actions/Campaigns/ImportReport.php` (a counted loss)
- Project rules: `.ai/rules/livewire.md`, `.ai/rules/models.md`, `.ai/rules/campaigns.md`, `.ai/rules/tests.md`, `.ai/rules/migrations.md`

### External

- RFC 5545, iCalendar: https://datatracker.ietf.org/doc/html/rfc5545
- Line folding, RFC 5545 §3.1: https://datatracker.ietf.org/doc/html/rfc5545#section-3.1
- Laravel task scheduling: https://laravel.com/docs/scheduling
- Laravel mail: https://laravel.com/docs/mail
