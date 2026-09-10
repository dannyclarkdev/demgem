---
title: "feat: What a fight is worth, and the four rules the tracker learns"
type: feat
date: 2026-09-09
status: planned
brainstorm: docs/brainstorms/2026-09-02-demgem-campaign-manager-brainstorm.md
follows: docs/plans/2026-09-09-feat-srd-compendium-plan.md
---

# feat: What a fight is worth, and the four rules the tracker learns

## Overview

Slice 15 got a monster from the book into the turn order. Slice 16 answers the question a GM asks next, which is whether that monster will kill anybody, and then teaches the tracker the four rules a 5e fight actually turns on.

| Feature | What it adds |
|---|---|
| The budget | A fight is worth the XP of the creatures in it. The party's own levels say what it can afford, and the encounter says which of three bands it lands in. |
| Concentration | A combatant holds one named effect. Damage prints the save DC. One click drops it. |
| Death saves | A combatant at zero collects successes and failures. Three of either ends the question. |
| Legendary actions | A count that refills on the creature's own turn, spent from the tracker between other people's turns. |
| Lair actions | One GM-written reminder that sits in the turn order at a count the GM picks. |

## Problem Statement

Two of these are promises already written down.

`docs/plans/2026-09-09-feat-srd-compendium-plan.md` deferred the budget in its own scope table: "Out. The XP budget needs party levels and a thresholds table, and it is worth its own slice." Both halves now exist. `entities.level` and `entities.is_pc` have carried the party since slice 4, and `stat_blocks.xp` and `stat_blocks.cr_value` arrived with the dataset in slice 15.

`app/Models/Combatant.php` carries the other one: "Conditions are free text with a suggested list in the UI. The tracker is system-light by design, and a fixed condition list is a ruleset decision." The campaign now declares its ruleset and `Ruleset::hasCompendium()` already gates on it. Concentration, death saves and legendary actions are the three pieces of state a 5e table tracks on paper today because the tracker will not hold them.

The third problem is one this slice creates and must handle honestly. The XP values per creature are SRD 5.2.1 content, published under CC BY 4.0 and already shipped. **The encounter-building budget table is not in the SRD.** It belongs to a book demgem has no licence to copy. See Content Licensing.

## Scope and Decisions

| Question | Decision |
|---|---|
| Homebrew stat blocks | Out, and moved to slice 17. A GM's own creature makes `stat_blocks` campaign-scoped and lands in the export, the importer, the API and a form. That is a slice, not a corner of this one. |
| Saved and reusable fights | In, as **Duplicate this fight** and nothing more. A GM builds the fight on the prep screen and copies it when the party takes the long way round. A separate encounter library is not worth a table yet. |
| Per-campaign budget numbers | Out. The ladder lives in `config/encounters.php` for the whole install. A per-campaign override is a settings screen and a JSON column for a number most GMs never touch. |
| Which characters count | Combatants in this fight whose entity `is_pc` and has a `level`. When the fight holds no PC rows, the campaign's party is the fallback, so a GM reads a budget while building. A level-less PC counts as level 1 and the screen says so. |
| A group-size multiplier | None. The 2024 rules dropped it, demgem authors its own ladder, and a multiplier nobody can point at a source for is a number that argues with the GM. |
| Where the difficulty is decided | `App\Support\Encounters\Budget`, a plain class with no database access, the way `App\Support\Reckoning` holds the calendar's arithmetic. The action and the Livewire component both read it. |
| Who sees the budget | GM roles only. A player never learns that the fight in front of them is rated High. It is not in the player query, not in the payload, and not on `/table`. |
| Ruleset gate | The budget reads `Ruleset::hasCompendium()`, because XP per creature is what it is built from. The four rules do **not**: concentration, death saves and a legendary count are useful on a system-agnostic table and cost nothing when unused. |
| Death saves on `/table` | Shown, for a row the party can already see. This is a deliberate exception to `.ai/rules/table.md`, argued below. |
| Concentration DC | Computed, never quoted: `max(10, intdiv($damage, 2))`. A number is a fact; the paragraph around it in the book is not ours. |
| Who rolls the save | Nobody. The tracker prints the DC and the GM rolls, from the drawer that is already there. Automating a save means owning the creature's saving throw modifiers, and `stat_blocks.ability_scores` holds those as display strings. |
| Legendary count source | Parsed from the dataset. Every one of the 30 creatures with legendary actions prints `Legendary Action Uses: 3`, and 27 of them add `(4 in Lair)`. The loader reads the first integer; the GM can edit the number on the row. |
| Lair actions | GM-written text on the encounter plus an initiative count. Not from the dataset. The 2024 document does not print lair actions the way the tracker would need them, and a half-parsed rule is worse than a reminder. |
| Where the lair marker lives | The turn order, rendered between combatants at its count. It is not a `combatants` row: it has no hit points, cannot be damaged, cannot be targeted, and a fake row would reach `RollInitiative`, `ApplyDamage` and the export. |

## Content Licensing

Slice 15 set the rule and this slice is the first test of it.

The XP printed on a creature is SRD content, already shipped, already attributed. **What a party can afford is not.** demgem therefore publishes its own ladder and says so plainly, in three places: `config/encounters.php`, the read-out on the screen, and `README.md`.

The ladder is derived, not invented. The SRD's own data gives a challenge rating an XP value, and that mapping ships with the dataset. demgem's rule is one sentence:

> A character of level N can face a Low fight worth the XP of a CR N/4 creature, a Moderate fight worth CR N/2, and a High fight worth CR N. A party's budget is the sum over its characters.

`database/srd/build-dataset.php` writes the CR-to-XP ladder into `config/encounters.php` beside the dataset checksum, and a test asserts the config still agrees with the shipped rows. Nothing in the read-out claims a published book agrees with it, and the screen names the rule so a GM who disagrees knows exactly what to ignore.

## Death Saves and the Table Rule

`.ai/rules/table.md` says a player gets a word, never a number, and that the numbers never reach the page at all. Death saves are the one place that rule is wrong, and the reason is what happens at a real table.

Hit points are the GM's information. A player who knows the ogre has 43 left plays differently, which is the whole argument for `healthWord()`. Death saves are the opposite: a dying character's rolls happen in the open, everyone counts them out loud, and the tension of the third one is the point of the mechanic. Hiding them from the party's screen would not protect anything, because the party already knows.

So the exception is narrow and it is written into the scope:

- Death save counts render on `/table` **only** for a row that already passes `visibleToPlayers()`. The gate does not move; only what a passing row carries changes.
- A hidden combatant's death saves reach nothing, exactly as its hit points do not.
- `Combatant::healthWord()` is untouched. A player still reads "Down", and the pips sit beside that word rather than replacing it.
- The leak test covers both: a visible PC's pips are asserted present, and a hidden monster's are asserted absent along with everything else about it.

`.ai/rules/table.md` gets a paragraph recording this, because the next person to read the rule needs to find the exception attached to it.

## Data Model

### combatants

| Column | Type | Why |
|---|---|---|
| `concentrating_on` | `string`, nullable | The effect's name. Null is "not concentrating", so there is no second boolean to disagree with it. |
| `death_save_successes` | `unsignedTinyInteger`, default 0 | Counts, not a list. Nothing is ordered and nothing is individually undone except the last one. |
| `death_save_failures` | `unsignedTinyInteger`, default 0 | As above. |
| `legendary_actions_max` | `unsignedTinyInteger`, nullable | Null means this creature has none, which is almost every row. |
| `legendary_actions_left` | `unsignedTinyInteger`, nullable | Null with the max, so the pair is written together or not at all. |

Five scalars, one-to-one with the row, so no child table. `.ai/rules/models.md` puts the signal at the shape rather than the count: none of these is a list.

### encounters

| Column | Type | Why |
|---|---|---|
| `lair_action_note` | `text`, nullable | The GM's own words. Rendered as Markdown through the existing renderer, GM-only. |
| `lair_initiative` | `integer`, nullable | Plain integer to match `combatants.initiative`, which is signed. Null means no lair action in this fight. |

No column stores the difficulty. It is a function of rows that change on every add, and a stored copy would be wrong between the add and the recompute.

## Actions

| Action | Does |
|---|---|
| `SetConcentration` | Writes or clears `concentrating_on`. Dispatches `EncounterChanged`. |
| `RecordDeathSave` | One success or one failure, clamped to 0..3. Refuses on a row that is not down, because a save on a standing combatant is a misclick. |
| `SpendLegendaryAction` | Decrements to a floor of zero. |
| `DuplicateEncounter` | Copies the fight and its combatants in one transaction: names, hit points, armour class, initiative bonus, `stat_block_id`, `entity_id`, `player_visible`, positions. Not initiative, not the round, not the active row, not conditions, not death saves. A copy is the fight before it started. |

Two existing actions change:

- **`ApplyDamage`** clears concentration when the row drops to zero, adds a death save failure when damage lands on a row already at zero, and clears both counts when healing lifts a row above zero. The DC is returned for the screen to print, never stored.
- **`NextTurn`** refills `legendary_actions_left` to `legendary_actions_max` when the row it makes active has a max, and skips the lair marker rather than making it active.

## Verification

    php artisan test --compact --filter=Budget
    php artisan test --compact --filter=Encounter
    php artisan test --compact --filter=Combatant
    php artisan test --compact --filter=Table
    vendor/bin/pint --dirty --format agent
    vendor/bin/phpstan analyse

Then the full suite, and a browser pass at a laptop width and a tablet width: the budget read-out as monsters are added and removed, a concentration save prompt after damage, a character taken to zero and through three failures on both the GM screen and `/table`, a legendary count refilling across a round, the lair marker in the turn order, and Duplicate this fight.

## Open Questions

1. **Should the lair variant count be stored too?** 27 of the 30 creatures with legendary actions print `(4 in Lair)` beside the count. Storing both means a second column and a switch nothing else in the app knows how to set, because demgem has no concept of a lair. Recommendation: store the plain count, and let the GM raise the number on the row when the fight is in the lair. Revisit if lair actions grow past a note.
2. **Does a level-less PC belong in the budget at all?** Counting them as level 1 keeps a mixed party honest but understates it. The alternative is to exclude them and say the budget covers four of five characters. Counting them is the recommendation, because a budget that silently shrinks is worse than one that is conservative and labelled.
3. **Should the lair marker broadcast to `/table`?** A lair action is a thing the party experiences, so the count belongs in their turn order, but the GM's note does not. Recommendation: the marker shows with no text, the same way a hidden combatant's name does not travel.
4. **Is Duplicate this fight enough of a builder?** If the browser pass says a GM wants the same three fights across four sessions, the encounter library is slice 18 and this slice records the signal rather than growing.

## References

- `docs/plans/2026-09-09-feat-srd-compendium-plan.md` — the slice that deferred the budget, and the licensing rules this one inherits.
- `.ai/rules/table.md` — a player gets a word; the death save exception is argued above and recorded there.
- `.ai/rules/models.md` — a list gets a child table, a scalar gets a column.
- `.ai/rules/migrations.md` — why `encounters.active_combatant_id` carries no foreign key, which the lair marker must not undo.
- `.ai/rules/actions.md` — sort nulls last with a case expression, never `nulls last`.
- `.ai/rules/events.md` — every event carries ids and nothing else; each screen re-renders under its own viewer's role.
- `.ai/rules/actions-campaigns.md` — the export writes `{ruleset, slug}`, never an id and never the prose.
- `.ai/rules/tests.md` — never assert a bare number against output carrying a ULID, which the death save pips will tempt.
