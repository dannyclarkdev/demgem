---
paths:
  - 'app/Models/**'
  - app/Models/MapMarker.php
  - app/Models/Clock.php
  - app/Models/Entity.php
---

# Models

## Never syncWithoutDetaching a pivot that carries a role
`game_session_entities` lets one entity sit in two prep buckets, keyed by (game_session_id, entity_id, role). `syncWithoutDetaching([$id => [...]])` keys on the entity alone, so it silently moves the existing row to the new role instead of adding one.

Check the bucket first, then `attach()`. The unique index is the backstop.

## A list gets a child table, a scalar gets a column
Slice 3 left a signal to revisit the child-table question once `entities` carried six type-specific columns. Slice 4 answered it: the count was the wrong measure, the shape is the right one.

quest_objectives earned its own table by being a list: many rows per entity, ordered, individually completable. character_class, level, and sheet_url are one-to-one with the row, so a child table would buy tidiness and pay a join on every character read plus a row lifecycle.

The next per-type field that is not a scalar is the signal, whatever the column count says.

## A map pin is a percentage, and it is gated twice
Coordinates are percentages of the image, never pixels. `decimal(6,3)`, clamped to 0–100 by `Coordinate::clamp()` before anything is written, because they arrive from a browser. A pixel coordinate breaks the day the GM replaces a 2000px export with a 6000px one and no migration can fix it afterwards.

A pin is visible to a player only when **both** gates open: `player_visible` is true, and the target entity passes `Entity::visibleTo()`. The second gate is the one nobody thinks of — a GM who reveals the pin for a GM-only NPC has made a mistake, and this turns the mistake into nothing rather than into a leak. A pin with no target passes it, because there is nothing behind it to protect. Both live in `scopeVisibleTo`, never in a Blade.

Nesting is a marker whose target is a map. There is deliberately no `maps.parent_map_id`: the marker already carries the link, and "Appears on" is the backlinks query. Two maps may pin the same city, which a parent column could not represent.

## A handout is revealed by its visibility column, and nothing else
EntityType::Handout carries no revealed_at and no player_visible. Revealed means `visibility` is not Dm, and RevealHandout writes that column through UpdateEntity, which is the path the form takes.

Do not add a second switch. This is deliberately the opposite call from combatants and map markers: those rows have no visibility of their own, so player_visible gave them one. An entity already has three-way visibility plus entity_viewers, and a fourth mechanism on the same row is how two answers come to disagree the first time somebody edits the form instead of pressing the button.

"Show the party" means Players. Selected stays a form decision.

## A clock shows while the thing it is about stays hidden
Clock::scopeVisibleTo() gates the row on player_visible. The entity link is gated separately, by a second query that loads only the entities this viewer passes Entity::visibleTo() for, keyed by id and handed to the Blade.

This is deliberately NOT the map pin's rule. A pin is nothing but a link, so a pin whose target is hidden has nothing left to show and does not show. A clock only mentions one: a GM who revealed "The Duke's suspicion" meant the party to read those words, so the dial stays and the duke's name goes.

Both halves are decided in queries. Do not filter the link with an @if.

Do not use a constrained eager load for this. Larastan cannot type the closure with() takes, and the fix it would need is an inline @var, which the project forbids. Two queries, both constant, is the answer.

## Every media conversion names its collection, before fit()
registerMediaConversions() once declared `thumb` with no collection, so it ran on every collection. The day the `files` collection started accepting PDFs, that unscoped crop would have sent them to Imagick and Ghostscript: slow where those exist, and silently absent where they do not, leaving the page asking for a URL nobody generated.

Two rules follow.

1. Every conversion calls performOnCollections(). `thumb` is the portrait, `tile` is the handout gallery.
2. performOnCollections() comes BEFORE fit(). fit() forwards to the image driver and returns it, so chained the other way the call lands on the driver and the conversion stays global. Larastan catches this one.

The Blade asks $media->hasGeneratedConversion('tile'), never the mime type: whether there is a tile is a fact about the file, and it stays true on a machine with no Ghostscript.

## A session's answers are one row, and a poll is a dateless session
`session_rsvps` holds `rsvp` and `attended` as two nullable columns on one row per member per session: two scalars about one pair, so no second table. `RespondToSession` deletes a row with nothing left in it, so "never answered" and "cleared" look the same. `SessionRsvp::wasThere()` reads the GM's mark first and the member's own yes until there is one, and the card, the headcount and the Markdown front matter all go through it, so there is one answer to "who was there".

There is no `polls` table. `GameSession::isPolling()` is `Planned` with no `scheduled_at`, and `session_date_options` hang off the session. `PickDate` writes the date and deletes the options in one transaction; a date typed into the edit form keeps the poll and the card shows a notice with a GM-only clear button, because deleting five members' answers on a form save is a surprise.

## reminder_sent_at is cleared in one place
`game_sessions.reminder_sent_at` is the whole idempotency story for reminder emails. `SendSessionReminders` stamps it after queueing (a crash between the two sends twice rather than never, which is the right way round), and `GameSessionObserver::saving()` clears it when `scheduled_at` is dirty. Nothing else touches it. A session already in the past is never reminded however long the scheduler was down: the query says `scheduled_at > now()`.

## A relationship is gated at both ends, in the scope
`entity_relations` is directed: the label is written from `entity_id`'s side, `reverse_label` is the optional word for the other side, and `EntityRelation::readsFromTargetAs()` is the one place the fallback ("The Drowned Duke · employer of") is spelled. Both ends cascade, unlike a pin's target: a relationship with nothing on the other end is nothing.

`scopeVisibleTo()` for a non-DM requires `player_visible` **and** both `entity_id` and `target_entity_id` to pass `Entity::visibleTo()`. The pin gates its target only; a relationship also gates its source because the incoming list on the target's page is built from rows whose source is somebody else, and that somebody may be GM-only. Both lists on the card go through the scope, never an `@if`. The leak test checks every case from both pages.
