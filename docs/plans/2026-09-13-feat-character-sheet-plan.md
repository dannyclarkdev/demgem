---
title: "feat: The 5e character sheet, as a ruleset module"
type: feat
date: 2026-09-13
status: planned
brainstorm: docs/brainstorms/2026-09-02-demgem-campaign-manager-brainstorm.md
follows: docs/plans/2026-09-13-feat-family-trees-plan.md
---

# feat: The 5e character sheet, as a ruleset module

## Overview

The brainstorm's players table lists "Full 5e character sheet" under P3 with one note: "Ruleset module." The core of demgem is system-agnostic: a character has a class, a level, and a link to the sheet a player actually plays from. Slices 15 to 17 built the seam a ruleset adds its module through, `Ruleset::hasCompendium()` and `config/encounters.php`. This slice adds the second module through the same seam: a sheet with the numbers a table on the SRD 5.2.1 rules reads out loud.

| Feature | What it adds |
|---|---|
| The sheet | Six ability scores, saving throw and skill proficiencies, hit points and hit dice, spell slots, armour class and speed. One row per character, in its own table. |
| The maths | Modifiers, the proficiency bonus from the level, every save and skill bonus, passive perception, and the initiative bonus, computed on every read from the stored scores. Nothing derived is stored. |
| The seam | `Ruleset::hasCharacterSheet()`. The card, the write, the export section, and the API key all read it, so a campaign on the system-agnostic ruleset never sees the sheet. |
| Who writes it | The character's player, or a GM: `EntityPolicy::update()`'s rule, the same one the class and level already use. |
| The inventory | The party's pack, read from the ledger on the sheet. No second purse. |
| The round trip | The sheet travels nested under its character in the export, the reader, the importer, and the vault. |

When this slice is done Wren's player opens her page in The Drowned Duchy and reads a sheet: Dexterity 17, +3, Stealth +6 with expertise, 38 of 38 hit points, five d8 hit dice, and the party's pack under it. They take 12 damage, press "Long rest" at the end of the night, and the sheet is whole again. Halder's cleric sheet shows four first-level slots and two second, two of them spent.

**On scope.** Phase 0 is the seam, the table, the maths, and the policy. Phase 1 is the card: reading and editing. Phase 2 is the export, the API, the seeder, the rules, and the pass.

## Problem Statement

**A link to a sheet somewhere else is not a sheet.** `sheet_url` was the right call for a system-agnostic core: a table on any system has some site it plays from. A table on the SRD 5.2.1 rules is the one this app already carries a compendium and a fight budget for, and its players still tab out to read their own Stealth bonus.

**The numbers the table reads out loud are derived, and every sheet site stores them.** A modifier is the score; a save is the modifier and maybe the proficiency bonus; the bonus is the level. Storing any of those is the second source of truth that drifts, the ledger's rule. The sheet stores what a player decides and computes what the rules decide.

**A module must not leak into the core.** Six ability scores as columns on `entities` would put one ruleset's shape on every campaign's table, and the next ruleset would add six more. The compendium answered this with a seam and a config; the sheet is one table behind the same seam.

## Proposed Solution

**One table, one row per character, behind the seam.** `character_sheets` holds what a player decides: the six scores, which saves and skills carry proficiency and which skills expertise, the hit point maximum, current and temporary, the hit die size and how many are spent, the spell slots per level with how many are used, the spellcasting ability, the armour class, and the speed. The proficiency lists and the slots are JSON on the row, the calendar's recorded exception: one configuration read whole, replaced whole on save, never queried by row, never gated.

**The maths is a pure class.** `Support\Sheets\FifthEdition` knows the six abilities, the eighteen skills and which ability each uses, the modifier of a score, the proficiency bonus at a level, and the bonus of a save or a skill given the sheet. It is unit-tested and has no model in it. The level is the character's own `level`, so the sheet does not carry one.

**The card reads for whoever may read the character and writes for whoever may edit it.** `Characters\Sheet` is nested on the character's page when the campaign's ruleset has a sheet. A player reads the party's sheets and edits their own; a GM edits any. Editing is a form on the card, every field at once, validated against the bounds. Three quick presses beside it, because a table does these every hour: damage, healing, and a long rest.

**The inventory is the ledger.** The sheet's last section is the party's pack through `LedgerEntry::inventory()`, exactly as the ledger page shows it. The ledger is the party's, so the section says so.

## Technical Approach

### No new dependency

### What slices 1 to 26 give us for free

| Piece | Reuse |
|---|---|
| `Ruleset::hasCompendium()` | The seam's shape. `hasCharacterSheet()` sits beside it. |
| `EntityPolicy::update()` | Who writes a character's things. |
| `Entity::level`, `character_class` | The level the proficiency bonus comes from. |
| `LedgerEntry::inventory()` | The pack. |
| `StatBlock::ability_scores` | The six abbreviations and the score/modifier shape the compendium already prints. |
| `config('compendium.attribution')` | The ability and skill names are SRD terms, and the card carries the notice the compendium carries. |
| `ExportCampaign::NESTED_TABLES` | One-to-one with a character, so nested under it. |

### The data

```
character_sheets
  id                    ulid
  campaign_id           ulid -> campaigns, cascade
  entity_id             ulid -> entities, cascade, unique
  strength, dexterity, constitution, intelligence, wisdom, charisma   smallint, 1..30, default 10
  saving_throws         json list of ability keys with proficiency
  skills                json list of skill keys with proficiency
  expertise             json list of skill keys with expertise
  hp_max                smallint unsigned, 0..999
  hp_current            smallint unsigned, 0..999
  hp_temp               smallint unsigned, 0..999
  hit_die               smallint, one of 6, 8, 10, 12
  hit_dice_spent        smallint unsigned, 0..level
  spell_slots           json map, level 1..9 => {total, used}
  spellcasting_ability  string(3) nullable, an ability key
  armor_class           smallint nullable
  speed                 smallint nullable
  created_at, updated_at
```

Nothing derived is stored: no modifier, no proficiency bonus, no passive perception, no initiative bonus.

### Scope and decisions

| Question | Decision |
|---|---|
| Which rulesets | `Ruleset::hasCharacterSheet()`, true for SRD 5.2.1 alone. The card, the write, the export, and the API all read it. |
| Which characters | Any character on that ruleset. A GM may keep a sheet for an NPC. |
| Who reads | Whoever may read the character. The sheet has no gate of its own. |
| Who writes | A GM, or the character's player. Not the party. |
| The level | The character's own. A sheet with no level on its character computes at level 1. |
| Hit dice | One die size, `hit_die`, and the count is the level. Multiclassing is a follow-up. |
| Spell slots | Nine levels, total and used each, edited by hand. No spell list: a list of spells is a different slice. |
| Long rest | Hit points to the maximum, temporary hit points to zero, every slot's used to zero, hit dice spent reduced by half the level rounded down, the rule as the SRD states it. |
| Damage and healing | Damage comes off temporary hit points first, then current, never below zero. Healing adds to current, never above the maximum. |
| The attribution | The card prints the compendium's notice, because the ability and skill names are SRD terms. |
| The export | Nested under the character as `sheet`, null when there is none. The reader refuses a score outside the bounds rather than clamping it. |
| The API | `sheet` on the show route for a character that has one, on a ruleset with sheets. No API write this slice. |
| The vault | A "Character sheet" section on the character's page: the scores with modifiers, hit points, hit dice, and slots. |

### Actions

| Action | Does |
|---|---|
| `Characters\SaveSheet` | Creates or replaces the sheet from validated parts. |
| `Characters\AdjustHitPoints` | Damage or healing, by the two rules above. |
| `Characters\LongRest` | The long rest. |

### Screens

- **The character's page** gains a "Character sheet" card under the record row: abilities in a six-column grid with score and modifier, saves and skills in two columns with the proficiency mark and the bonus, hit points with the three numbers and the two quick inputs, hit dice, spell slots as pips, armour class, speed, initiative, passive perception, the party's pack, and for a writer the "Edit sheet" form and "Long rest".

### The round trip

- `character_sheets` joins `NESTED_TABLES` as `entities[].sheet`. The reader validates every number against the bounds. The importer writes it after the entity.
- No version bump.

## Verification

    php artisan test --compact tests/Unit/Sheets
    php artisan test --compact tests/Feature/Characters
    php artisan test --compact tests/Feature/Api
    php artisan test --compact --filter=RoundTrip
    php artisan test --compact --filter=DemoSeeder
    vendor/bin/pint --dirty --format agent
    vendor/bin/phpstan analyse

Then the full suite with `memory_limit=1G`, and a browser pass: as the player, open Wren, read the sheet, take damage, press "Long rest"; as the GM, edit Halder's slots; switch the campaign to the system-agnostic ruleset and find no card.

## Open Questions

1. **Should the sheet carry a spell list?** Recommendation: not now. The SRD's spells are a dataset the way its creatures are, and that is a compendium slice.
2. **Should the level move onto the sheet?** Recommendation: no. The level is a core fact every ruleset has.
3. **Should the API write the sheet?** Recommendation: a follow-up, once the form has been used for a while.

## References

- `.ai/rules/commands.md` — the licence follows the words; the notice renders wherever SRD terms appear.
- `.ai/rules/models.md` — a scalar gets a column, a list gets a child table, and the calendar's JSON exception.
- `.ai/rules/entities.md` — the character fields are not DM fields; the owning player edits their own PC.
- `.ai/rules/campaigns.md` — a new campaign-scoped table joins the export in the same commit.
