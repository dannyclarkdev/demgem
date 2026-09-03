---
paths:
  - 'app/Models/**'
---

# Models

## Never syncWithoutDetaching a pivot that carries a role
`game_session_entities` lets one entity sit in two prep buckets, keyed by (game_session_id, entity_id, role). `syncWithoutDetaching([$id => [...]])` keys on the entity alone, so it silently moves the existing row to the new role instead of adding one.

Check the bucket first, then `attach()`. The unique index is the backstop.
