---
paths:
  - 'database/migrations/**'
---

# Migrations

## Game sessions live in game_sessions, never sessions
SESSION_DRIVER=database, and the users table migration already creates a `sessions` table for HTTP sessions. The game session model is `App\Models\GameSession` on `game_sessions`, with children `scenes`, `secrets`, and the `game_session_entities` pivot. UI copy and route segments still say "session".

`game_sessions` is unique on (campaign_id, number) and soft-deletes, so a trashed session keeps its number. `CreateSession::nextNumber()` therefore counts trashed rows, and the form's unique rule sees them too.
