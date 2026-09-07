---
paths:
  - 'app/Calendar/**'
  - app/Http/Controllers/CalendarFeedController.php
  - app/Mail/**
---

# Calendar and mail

## The feed and the reminder carry names and links, never prose
`IcsEvent` has four text fields and none of them is prose, and `SessionReminder` passes the session label, the title, the time and two URLs to its template. That is structural on purpose: a calendar entry gets forwarded, synced and searched, and mail is forwarded and searched, so a strong start or an unpublished recap must not be able to reach either. A test asserts each against every DM field of a seeded session. Do not add a "description" that renders the session body.

## The feed is unauthenticated, in UTC, and filtered per campaign role
`/calendar/{token}.ics` sits outside the auth group because a calendar app cannot log in; the 40-character token is the credential, a wrong one is a bare 404 and the route is `throttle:30,1`. Each campaign's sessions go through `GameSession::visibleTo()` under that membership's role. Times are written with a `Z` and every calendar app converts, which is the per-user timezone this project does not store.

`IcsCalendar::fold()` counts octets, not characters, and never splits a multibyte character: a title with an em dash folded through the middle is not UTF-8 any more. Escape order is backslash first, then `;` `,` and newlines.

## User::calendarToken() reads the row, not the instance
The authenticated user can be an instance built before `calendar_token` existed on it, and strict mode throws on a missing attribute. The method queries the column and mints a token only when the row has none.
