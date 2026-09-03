---
paths:
  - 'app/Models/**'
  - app/Models/MapMarker.php
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
