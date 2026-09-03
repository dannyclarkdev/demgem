---
paths:
  - 'database/migrations/**'
---

# Migrations

## Game sessions live in game_sessions, never sessions
SESSION_DRIVER=database, and the users table migration already creates a `sessions` table for HTTP sessions. The game session model is `App\Models\GameSession` on `game_sessions`, with children `scenes`, `secrets`, and the `game_session_entities` pivot. UI copy and route segments still say "session".

`game_sessions` is unique on (campaign_id, number) and soft-deletes, so a trashed session keeps its number. `CreateSession::nextNumber()` therefore counts trashed rows, and the form's unique rule sees them too.

## encounters.active_combatant_id carries no foreign key
combatants.encounter_id already points at encounters, so a constraint back to combatants would be circular and would need a deferred constraint to create in either order. The column is a plain indexed ULID.

RemoveCombatant clears it when it deletes the active row, and NextTurn treats an id that no longer resolves as "start from the top". That cleanup is application code because the database will not do it.

It is stored as an id rather than a position index because ReorderPositions rewrites every position on every drag, and an index would silently point at somebody else.

## Never name a column attributes, and keep searchable JSON in a text column
`$model->attributes` is Eloquent's own internal property. A column with that name is shadowed inside every model method, trait, and observer. The key-value column is `custom_fields`.

It is `text` holding JSON, not a `json` column: Scout's DatabaseEngine searches with `column ilike ?`, and PostgreSQL has no ilike for json. A json column works on SQLite locally and fails in production, which is the same class of difference that bit this project with `nulls last`.
