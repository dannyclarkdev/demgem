---
paths:
  - 'app/Models/**'
---

# Models

## Never syncWithoutDetaching a pivot that carries a role
`game_session_entities` lets one entity sit in two prep buckets, keyed by (game_session_id, entity_id, role). `syncWithoutDetaching([$id => [...]])` keys on the entity alone, so it silently moves the existing row to the new role instead of adding one.

Check the bucket first, then `attach()`. The unique index is the backstop.

## A list gets a child table, a scalar gets a column
Slice 3 left a signal to revisit the child-table question once `entities` carried six type-specific columns. Slice 4 answered it: the count was the wrong measure, the shape is the right one.

quest_objectives earned its own table by being a list: many rows per entity, ordered, individually completable. character_class, level, and sheet_url are one-to-one with the row, so a child table would buy tidiness and pay a join on every character read plus a row lifecycle.

The next per-type field that is not a scalar is the signal, whatever the column count says.
