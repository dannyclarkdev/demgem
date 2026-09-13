---
paths:
  - 'app/Livewire/Downtime/**'
---

# Downtime

## Downtime is gated by the character, written by the character's player, and one component in three places
downtime_activities has no visibility column. DowntimeActivity::scopeVisibleTo(user, role) is a whereIn over Entity::visibleTo(), so a row is read by whoever may read the character and a GM-only NPC's month never loads for a player. DowntimeActivityPolicy follows the character, not the author: a GM or entities.player_user_id may create, edit, and delete, the rule EntityPolicy::update() already spells. Downtime\Log renders on the character page (scoped to the character, form writes under it), the session page (scoped to the session, character picker), and /downtime (both pickers); do not fork it. The character picker's list is also the validation rule, so a posted id it never offered is an error rather than a write. The start date goes through the same calendar check as the session form and the end date is computed, never stored.
