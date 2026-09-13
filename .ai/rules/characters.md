---
paths:
  - 'app/Livewire/Characters/**'
---

# Characters

## The 5e sheet is a ruleset module: its own table, behind hasCharacterSheet(), storing only what the player decides
character_sheets is one row per character in its own table, never columns on entities, so one ruleset's shape stays off the core table. Ruleset::hasCharacterSheet() is the seam: Characters\Sheet aborts 404 without it, Entities\Show does not mount the card, and the API sets the relation only on such a campaign. Only what a player decides is stored (scores, proficiency lists, hit points, die, slots); modifiers, proficiency bonus, save and skill bonuses, passive Perception and initiative are methods on CharacterSheet that read App\Support\Sheets\FifthEdition, the pure maths class with the unit tests. Never store a derived number. The three lists and the slots are JSON on the row, the calendar's exception. Who writes is EntityPolicy::update(): a GM or the character's player; there is no sheet policy. The ability and skill names are SRD terms, so the card prints config('compendium.attribution').
