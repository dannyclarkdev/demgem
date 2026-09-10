---
title: "feat: The GM's own monsters, in the book beside the shipped ones"
type: feat
date: 2026-09-09
status: implemented
brainstorm: docs/brainstorms/2026-09-02-demgem-campaign-manager-brainstorm.md
follows: docs/plans/2026-09-09-feat-encounter-builder-fight-rules-plan.md
---

# feat: The GM's own monsters, in the book beside the shipped ones

## Overview

Slice 15 gave a campaign a book to look creatures up in. Slice 16 said what a fight built from that book is worth. Slice 17 lets a GM put their own creatures in the same book, so the compendium, the tracker picker, the budget and the export all read them without learning a second shape.

| Feature | What it adds |
|---|---|
| A campaign's own creatures | `stat_blocks` gains a nullable `campaign_id`. Null is the shipped reference; set is one campaign's own. |
| Start from a creature | Copy any shipped stat block into the campaign and edit the copy. The fastest way to a homebrew ogre is an ogre. |
| The editor | The numbers as fields, six ability scores, and the five sections as lists of named entries. Every field optional. |
| The compendium opens to everybody | A system-agnostic campaign gets the screen too. What changes is what is in it. |
| The round trip | A campaign's own creatures export in full, prose included, and import as campaign rows with every reference remapped. |
| The API | The same reads, and the same writes the form makes, behind `abilities:write`. |

## The Rule This Slice Overturns

`.ai/rules/commands.md` currently says, in as many words:

> stat_blocks has no campaign_id on purpose: one install, one copy, every campaign reads the same rows. That keeps it out of the export, so ExportCoverageTest needs no entry for it — do not add one.

This slice does exactly the thing that rule forbids, so the rule is rewritten in the same commit rather than quietly broken. The part that was right stays right: **the shipped rows are still global, still read-only, still written by one command, and still never exported.** What changes is that the table now also holds rows that are none of those things, and the two kinds are told apart by one column.

The alternative was a second table, `custom_stat_blocks`. It was rejected because `combatants.stat_block_id` and `entities.stat_block_id` already point at `stat_blocks` with `nullOnDelete`, and a second table would make both columns polymorphic or double them. Every screen, the picker, the budget and the API would then carry the fork. One nullable column carries it instead.

## Scope and Decisions

| Question | Decision |
|---|---|
| Ownership | `stat_blocks.campaign_id`, nullable, `cascadeOnDelete`. Null is shipped reference data. Set is one campaign's own, and no other campaign can read it. |
| Who may write one | A GM of that campaign. A shipped row has no writer but `demgem:import-srd`, and no policy path reaches one. |
| Who may read the compendium | Every GM of every campaign, which is a change. `CampaignPolicy::viewCompendium()` drops its ruleset condition. |
| What is in the compendium | The campaign's own creatures always; the shipped set as well when `Ruleset::hasCompendium()`. A system-agnostic campaign sees only what it wrote. |
| Slug collisions | Suffixed at creation. A campaign's own "Goblin Warrior" becomes `goblin-warrior-2` when the shipped set already has `goblin-warrior` in that ruleset, so every row stays reachable by a slug that means one thing. |
| Uniqueness | Two partial indexes, not one. `(ruleset, slug) where campaign_id is null` is the loader's upsert key; `(campaign_id, slug)` keeps one campaign's own slugs distinct. A single index over all three columns would not work: Postgres treats NULLs as distinct, so it would let the loader write a duplicate shipped row. |
| A Markdown-only stat block | Out, and deliberately against the brainstorm's "Markdown fallback". Every field is already optional and every section entry is already Markdown, so a GM who wants prose writes prose in a trait. A second shape would be one the tracker cannot read numbers off and the budget cannot price. |
| Challenge rating and XP | Both fields, and neither derived. A GM's own creature is worth what they say it is worth, and the budget reads `xp` exactly as it does for a shipped row. |
| Deleting | A GM may delete their own. `combatants.stat_block_id` and `entities.stat_block_id` are `nullOnDelete`, so a fight in progress keeps its numbers and loses only the link back. |
| Editing a creature already in a fight | Allowed, and it changes nothing about the fight. `AddCombatants` copies the numbers at the moment of adding; that was slice 3's decision and it is what makes this safe. |
| The export | A campaign's own creatures are a top-level section. The prose travels, because it is the GM's own writing. |
| API writes | In. `.ai/rules/api.md` says the API is the screens in JSON and every write calls the action the form calls, so a writable resource on screen is a writable resource in JSON. |

## Licensing

Slice 15's rules do not bend here; they gain a second half.

- **Shipped prose still never leaves the install.** `ExportCampaign` writes `{ruleset, slug}` for a shipped row and no text, exactly as `.ai/rules/actions-campaigns.md` requires.
- **A campaign's own prose travels in full.** It is the GM's writing, in their own campaign's export, and holding it back would make the export lossy for no reason.
- **The attribution notice is for shipped rows only.** A GM's own creature is not SRD material and must not render the CC BY notice, on the screen or in the API document, because the notice would be a false claim about who wrote it. `CompendiumLicensingTest` grows a case for that.
- **Copying a shipped creature copies its text into the campaign.** That is a GM taking CC BY material and putting it in their own campaign, which the licence allows. The copy records `source` and `license` from the row it came from, so the attribution follows the words rather than being lost in the copy.

## Data Model

### stat_blocks

| Column | Type | Why |
|---|---|---|
| `campaign_id` | `foreignUlid`, nullable, `cascadeOnDelete` | Null is shipped. A campaign's own creatures go when the campaign goes. |

Index changes:

- Drop `unique(['ruleset', 'slug'])`.
- Add `unique (ruleset, slug) where campaign_id is null`, raw, because the schema builder writes no partial index.
- Add `unique (campaign_id, slug)`.
- Add `index (campaign_id, cr_value)` beside the two existing ruleset indexes, because the compendium's own-creatures query orders by challenge rating.

The partial index is Postgres syntax. `.ai/rules/actions.md` records what this project has already been bitten by twice on database differences, so the migration checks the driver and the test suite must run against Postgres for it to mean anything.

## Actions

| Action | Does |
|---|---|
| `CreateStatBlock` | Builds a campaign's own creature from a validated array. Slugs the name, suffixes on collision, sets `campaign_id`, and writes `source` to the campaign's own name. |
| `UpdateStatBlock` | The same fields on an existing campaign row. Refuses a shipped row rather than trusting the caller. |
| `CopyStatBlock` | A shipped creature into the campaign: every column, a fresh slug, `campaign_id` set, and `source` and `license` kept so the attribution follows the text. |
| `DeleteStatBlock` | A campaign's own only. The two `nullOnDelete` columns do the rest. |

`ImportSrdCommand` gains one line and one guarantee: its queries are scoped to `whereNull('campaign_id')` so a GM's own creature can never be updated, reported as orphaned, or counted by the loader.

## Screens

- **The compendium index** grows a "Yours" filter and a **New creature** button, and marks a campaign's own rows with a badge. The shipped rows are unchanged.
- **The stat block page** grows Edit, Duplicate and Delete for a campaign's own row, and **Copy to my campaign** for a shipped one.
- **The editor** is one Livewire form: the numbers, six ability scores, and five repeatable section lists. It is the largest new view in the slice and the one to cut down first if the slice runs long — the fields are all optional, so a smaller first version is a coherent one.

## Verification

    php artisan test --compact --filter=StatBlock
    php artisan test --compact --filter=Compendium
    php artisan test --compact --filter=RoundTrip
    php artisan test --compact --filter=Budget
    vendor/bin/pint --dirty --format agent
    vendor/bin/phpstan analyse

Then the full suite, and a browser pass at a laptop width and a tablet width: create a creature, copy a shipped one and edit it, put both in a fight and watch the budget price them, delete one that is in a fight, and read the index as a system-agnostic campaign.

## Open Questions

1. **Does a copied creature keep the original's slug lineage?** Recording `copied_from_slug` would let a GM see what they started from and would let a later slice offer "what changed since the book". It is one nullable column and no query reads it yet. Recommendation: leave it out. A column nothing reads is the thing slice 15 already deleted once, when `ac_note` came out.
2. **Should a campaign's own creature be searchable from the global search?** The compendium is not in Scout and slice 15 argued why. A GM's own creatures are campaign content, which is what Scout indexes. Recommendation: out of this slice, and worth its own look, because indexing them means the search screen learns a result type.
3. **What does the tracker picker show when both halves have a match?** Recommendation: the campaign's own first, then the shipped ones, with the badge that the index uses. A GM who wrote their own goblin meant that one.

## References

- `.ai/rules/commands.md` — the rule this slice overturns, rewritten in the same commit.
- `.ai/rules/actions-campaigns.md` — a global row exports as a natural key, never through IdMap; the campaign's own rows are the other case.
- `.ai/rules/campaigns.md` — a new campaign-scoped table joins the export in the same commit; an added key does not bump the version.
- `.ai/rules/encounters.md` — only a row with a `stat_block_id` can be priced, which is now also true of a GM's own creature.
- `.ai/rules/api.md` — the API is the screens in JSON, same scopes, same actions.
- `.ai/rules/migrations.md` — never name a column `attributes`; searchable JSON lives in text.
- `.ai/rules/actions.md` — portable SQL, because this project has been bitten twice.
- `docs/plans/2026-09-09-feat-srd-compendium-plan.md` — where custom stat blocks were first deferred, and to this slice by name.

## Implementation Results — 2026-09-09

Implemented in full. 27 new tests; the suite is 1298 tests, 1297 passing, 1 skipped, with Larastan clean and Pint clean.

### The rule that was overturned, and what survived it

`.ai/rules/commands.md` is rewritten rather than broken quietly. Both halves of slice 15's decision survive as conditions on `campaign_id` rather than as facts about the table: a null row is still global, still read-only, still written by one command, still absent from every export. Every query in `ImportSrdCommand` is now scoped with `shipped()`, without which the loader would update a GM's creature whose slug matched, or report it as one the dataset no longer names.

`.ai/rules/actions-campaigns.md` needed the same treatment for the other direction. A `stat_block` reference is now one of two things, and the column says which: `{ruleset, slug}` for a shipped row, resolved against this install; `stat_block_id` for a campaign's own, remapped through IdMap like every other campaign id.

### Deviations from the plan

| Planned | Shipped | Why |
|---|---|---|
| A rename re-slugs | The slug is set once and never moves | Writing the plan's API section made the contradiction visible: `.ai/rules/api.md` says an entity is addressed by id precisely because a slug moves on rename, and everything here is addressed by slug. Making the slug permanent keeps one address for the life of the row and costs only a URL that still says `harbour-thug` after a rename. |
| Open question 1, `copied_from_slug` | Left out, as recommended | A column nothing reads is what slice 15 already deleted once, when `ac_note` came out. |
| Nothing about the ability grid | Two cards across at a tablet width | The browser pass at 834px found three cards across leaves each about 130px wide, where a number input's spinner takes the whole field and the score reads as blank. |

### A shipped defect found on the way

Copying an Ogre showed its Actions ending with an entry reading `## Oni`. A creature's last section runs to the next group's heading, so that heading was read as a final entry — on 176 of the 330 creatures, since slice 15, on every page and in every API document.

`parseSection()` now stops at a heading rather than reading past one. All 176 were the last entry in their section, so cleaning the shipped file is exactly what the corrected parser produces: the diff is 704 deletions and no other change. The checksum moved with the data in the same commit and `database/srd/README.md` records the correction. It is its own commit, because it is a slice 15 bug rather than part of this slice.

A creature copied before that fix keeps the stray entry, which is correct: a copy is a snapshot, and the GM can delete the line in the editor.

### Browser checks

Driven end to end on the seeded world at 1400px and at 834px:

- The index shows the campaign's own creature first, badged **Yours**, above the CR 0 shipped rows, with **New creature** and **Only mine**.
- A GM's own creature shows Edit, Duplicate and Delete, and no SRD notice.
- A shipped creature shows only **Copy to my campaign**, and copying one lands on the editor with every field filled and the slug suffixed to `ogre-2`.
- A rename saved and the URL kept `ogre-2`, which is the slug decision working.
- The copy keeps the CC BY notice, because it kept the licence it was copied from.
