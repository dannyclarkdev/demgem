---
paths:
  - 'app/Livewire/Table/**'
---

# Table

## Filter what a player may see in the query, never in the Blade
Table\Fight renders one list for two audiences. The role decision happens in the query: a non-DM gets `->visibleToPlayers()`, so a hidden combatant is never loaded, never rendered, and never in anything the request produced.

Do not fetch every row and hide some with an `@if`. A template guard is one edit away from a leak, and the row is in memory the whole time.

The same reasoning as the broadcasts: a payload that never carries the data cannot leak it. Combatant::healthWord() is the second half of the rule — a player gets a word, and hit points, armour class and initiative never reach the page at all.
