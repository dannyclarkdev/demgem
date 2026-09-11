---
title: "feat: The generators, six table sets a GM adds with one press"
type: feat
date: 2026-09-11
status: planned
brainstorm: docs/brainstorms/2026-09-02-demgem-campaign-manager-brainstorm.md
follows: docs/plans/2026-09-11-feat-secret-blocks-reputation-plan.md
---

# feat: The generators, six table sets a GM adds with one press

## Overview

The brainstorm's last P2 row for the table is "built-in generators: names, NPC, tavern, weather, loot, rumor", with the note "ship as random tables". Slice 3 built the tables and the nesting. This slice ships the content.

| Feature | What it adds |
|---|---|
| Six generator sets | Names, someone the party meets, a tavern, the weather, loot, and a rumour. Each is a few weighted tables that nest into one another, written for this app and shipped with it. |
| One press to add a set | The tables index lists the sets. A GM adds one, or all of them, and gets ordinary tables in the campaign: editable, rollable from the drawer, exported, and deleted like any other. |
| A marker | `random_tables.generator_key` says which set a table came from, so the index can say which sets are in and the round trip can carry the fact. |

When this slice is done a new campaign's tables page offers six sets. The GM presses "Add all", opens the Run screen, rolls "Someone the party meets", and reads "A ferryman with one oar. Wants their debt forgotten. Hides a letter they cannot read. Talks to the ground." The GM edits the ferryman out and writes a lamplighter in, because the tables are theirs now.

**On scope.** Phase 0 is the content and the installer. Phase 1 is the index, the round trip, and the demo. Phase 2 is the rules, the README, and the pass.

## Problem Statement

**An empty tables page teaches nothing.** The feature is nesting, and a GM who has never seen a nested table writes a flat list of five rumours and never learns what the second column is for. Six sets that nest three deep show the shape by example.

**The first session needs a name in three seconds.** Every GM improvises an NPC on the spot and reaches for a name generator in another tab. The tab is the competitor. A table set that answers "who is this" in one roll is the reason to keep demgem open.

**The sketch put global tables in the same table, and that was a trap.** The slice 3 migration recorded why: a nullable `campaign_id` on `random_tables` would be filtered out silently by the campaign scope. This slice takes the other road. A set is copied into the campaign, and nothing global exists at runtime.

## Proposed Solution

**A set is a JSON file under `database/generators`, and adding it copies it.** Each file names the set, its tables, and their entries, with nesting written as a slug within the set. `Generators` reads the directory once per request and validates each file's shape. `InstallGenerator` creates the tables of one set in the campaign in one transaction, resolves the nested slugs to the new ids, and stamps every table with the set's key. Installed tables are ordinary rows the GM may edit or delete, and the roller, the drawer, and the export never learn the word "generator".

**A set is in when a table carries its key.** `generator_key` is a nullable column on `random_tables`. The index lists the sets with an "Add" button for the ones with no table carrying their key, and "Added" for the rest. A GM who deletes every table of a set can add it again. A GM who deletes one table of a set sees "Added" still, and the plan records that as the right reading: a set the GM has pruned is a set the GM is using.

**Names are a chain, and so is an NPC.** An entry may nest one table, and a chain runs five deep. So a first name nests the family names, a trade nests what they want, which nests what they hide, which nests how they carry it. The roller already prints a chain as lines.

## Technical Approach

### No new dependency

Nothing to install.

### What slices 1 to 20 give us for free

| Piece | Reuse |
|---|---|
| `RandomTable`, `RandomTableEntry`, `RollRandomTable` | The whole runtime. Nothing changes. |
| `CreateRandomTable` | The row an installer writes. |
| `RandomTables\Index` | Where the sets are offered. |
| `ExportCampaign` random tables section | Where `generator_key` joins as an added key. |
| `database/srd` and `ImportSrdCommand` | The precedent for shipped data under `database/` with a README that says where it came from. |

### The data

```
random_tables
  generator_key    string(40) nullable, indexed   the set this table was copied from

database/generators/<key>.json
  {
    "key": "npc",
    "name": "Someone the party meets",
    "description": "...",
    "tables": [
      {
        "slug": "npc-who",
        "name": "Someone the party meets",
        "description": "...",
        "entries": [
          { "body": "A ferryman with one oar.", "weight": 1, "nested": "npc-wants" }
        ]
      }
    ]
  }
```

### Scope and decisions

| Question | Decision |
|---|---|
| Global tables or copies? | Copies. The slice 3 migration recorded the trap in a nullable `campaign_id`, and a copy is the GM's to edit, which is what a generator is for. |
| Where the content lives | `database/generators/*.json`, one file per set, with a README that says the content is demgem's own. |
| Who may add a set | GM roles, the same as creating a table. |
| Adding a set twice | Refused while a table carries its key. The button reads "Added". |
| A name collision | A GM who already has a table named "Weather" gets the set's table named "Weather (generator)". The set's other tables still nest into it by id, so the chain holds. |
| Editing an installed table | Allowed and expected. The key stays; the set stays "Added". |
| The export | `generator_key` is an added key on the random tables section. The importer carries it, so a set counts as added on the other side too. |
| The API | No change. Tables have no endpoint. |
| The demo | The seeder adds every set, beside the two tables it already writes. |
| The content's licence | Written for demgem, under the repository's licence. No published table is transcribed. |

### Actions

| Action | Does |
|---|---|
| `RandomTables\InstallGenerator` | One set into one campaign, in one transaction, with the nested slugs resolved and every table stamped. Refuses a set already in. |

### Screens

- **The tables index** gains a "Generators" card above the list: each set with its name, its description, its table count, and Add or Added. An "Add all" button when any set is out.
- **Nothing else.** A generator is a table.

### The round trip

- `generator_key` on each exported table. The reader accepts the key or its absence; the importer writes it.
- No version bump.

## Verification

    php artisan test --compact --filter=Generator
    php artisan test --compact tests/Feature/RandomTables
    php artisan test --compact --filter=RoundTrip
    vendor/bin/pint --dirty --format agent
    vendor/bin/phpstan analyse

Then the full suite with `memory_limit=1G`, and a browser pass: add one set from the index, roll it from the drawer on the Run screen and read a four-line NPC, add the rest, delete a table from a set and confirm the chain degrades to text as slice 3 promised.

## Open Questions

1. **Should a new campaign get the sets automatically?** Recommendation: not now. A GM importing a vault, or writing their own world, may not want thirty tables they did not ask for, and one press is cheap.
2. **Should a set be updatable when a later release improves it?** Recommendation: no. Once copied, a table is the GM's. A better set ships as a new key.
3. **Should the generators have a system-specific set, such as 5e loot?** Recommendation: no. The core is system-agnostic, and loot here is objects rather than rules.

## References

- `database/migrations/*_create_random_tables_table.php` — the trap this slice does not walk into.
- `.ai/rules/campaigns.md` — an added key does not bump the version.
- `.ai/rules/commands.md` — the precedent for shipped data under `database/` and the notice that says where it came from.
- `docs/plans/2026-09-03-feat-quests-tracker-dice-tables-plan.md` — where nesting was designed.
