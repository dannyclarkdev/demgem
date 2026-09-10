---
paths:
  - 'app/Livewire/Table/**'
---

# Table

## Filter what a player may see in the query, never in the Blade
Table\Fight renders one list for two audiences. The role decision happens in the query: a non-DM gets `->visibleToPlayers()`, so a hidden combatant is never loaded, never rendered, and never in anything the request produced.

Do not fetch every row and hide some with an `@if`. A template guard is one edit away from a leak, and the row is in memory the whole time.

The same reasoning as the broadcasts: a payload that never carries the data cannot leak it. Combatant::healthWord() is the second half of the rule — a player gets a word, and hit points, armour class and initiative never reach the page at all.

## Death saves are the one number a player reads, and the gate still decides
A player gets a word, never a number — except death saves. A dying character's rolls happen in the open and the table counts them out loud, so hiding them protects nothing, while "the ogre has 43 left" changes how a table plays.

The exception is to what a visible row CARRIES, never to the gate. Combatant::deathSavesVisibleToPlayers() asks player_visible first, so a hidden row's death saves reach nothing exactly as its hit points do not. healthWord() is untouched: a player still reads "Down", with the pips beside it.

A lair action reaches the party as a marker with the count and no words, the way a hidden combatant's turn reaches them without a name. The marker is not a combatants row: it has no hit points, cannot be targeted, and a fake row would reach RollInitiative, ApplyDamage and the export. Encounter::lairMarkerIndex() places it where it would fall if position and initiative were sorted together.
