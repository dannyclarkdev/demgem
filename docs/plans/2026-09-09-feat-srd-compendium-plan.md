---
title: "feat: The compendium, and the numbers it puts in the turn order"
type: feat
date: 2026-09-09
status: planned
brainstorm: docs/brainstorms/2026-09-02-demgem-campaign-manager-brainstorm.md
follows: docs/plans/2026-09-09-feat-entity-templates-body-history-plan.md
---

# feat: The compendium, and the numbers it puts in the turn order

## Overview

Slice 15 gives a campaign a book to look things up in, and a way to get a monster from that book into the fight without typing its hit points. The dataset is the System Reference Document 5.2.1, which Wizards of the Coast publishes under CC BY 4.0. It ships with the app, it is read-only, and it belongs to no campaign.

| Feature | What it adds |
|---|---|
| Stat blocks | The SRD 5.2.1 creatures as rows in a global table, shipped as a checksummed data file and loaded by one command. |
| The compendium | A GM-only screen per campaign: search by name, filter by creature type and challenge rating, read one stat block whole. |
| Into the fight | Pick a stat block, choose a quantity, and the turn order gets rows with hit points, armour class and an initiative bonus already on them. |
| An entity's stat block | A monster NPC may name the stat block it fights as, so the Monsters prep bucket adds it with numbers. |
| Attribution | The licence notice travels with the data: on the screen, in the API document, and in the repository. |

## Problem Statement

`AddCombatants` has carried the reason for this slice in its docblock since slice 3: "there are no stat blocks until the compendium lands, so HP and AC are typed once on the add form and applied to every copy." A GM running a published adventure types four numbers per monster group while five people wait. The combatants migration made the same bet, keeping `hp` signed "so the systems that track negatives have room when a ruleset lands".

The campaign already declares a ruleset. `App\Enums\Ruleset` has two cases and nothing reads the value: `Srd5e2024` changes no behaviour anywhere in the application. Slice 15 is what that column was for.

There is a second problem, and it is not a feature. `Ruleset::Srd5e2024->label()` currently returns `'D&D 5e (2024)'`. CC BY 4.0 licenses the text of the SRD and grants no trademark rights whatsoever. The word mark is not ours to put in a dropdown, and it must go before any SRD content ships beside it.

## Scope and Decisions

| Question | Decision |
|---|---|
| Which document | SRD 5.2.1, the current English release, published 2025-05-01 under CC BY 4.0. Not SRD 5.1, and not both. |
| Which content | Creature stat blocks only. No spells, no magic items, no classes, no conditions list, no rules text. |
| Ownership | Global. `stat_blocks` carries no `campaign_id`, is never edited through the app, and is the same set of rows for every campaign on the install. |
| Custom stat blocks | Out. A GM's own monster is a later slice, and it is the slice that makes the table campaign-scoped and puts it in the export. |
| Who may read it | GM roles only, and only in a campaign whose ruleset has a compendium. A player never reaches the screen or the endpoint. |
| The gate's home | `CampaignPolicy::viewCompendium()`, checked in `mount()` and in the controller. Never an `@if` in a Blade, and never a hidden nav link as the only guard. |
| What reaches a combatant | The name, the hit points, the armour class, the initiative bonus, and a `stat_block_id`. No prose. |
| Why no prose | A combatant is exported. Copying licensed prose onto a campaign row would put CC BY text into every GM's JSON with no notice attached to it. Numbers and a creature's name are not the licensed part. |
| Encounter difficulty | Out. The XP budget needs party levels and a thresholds table, and it is worth its own slice. "Builder" here means compendium to turn order. |
| Saved encounters | Out. Assembling a fight once and reusing it is the same later slice as the budget. |
| Rolled hit points | In, as an optional toggle, defaulting to the average. It uses the existing injected `Random\Randomizer`, so a test can bind a seeded `Mt19937` and assert exact totals. First thing to cut if the slice runs long. |
| Search | A plain `lower(name) like ?` query, as `Entities\Index` does. Not Scout: this is a fixed local dataset browsed with filters, and a Scout index would add a rebuild step to the loader for nothing. |
| Addressing | Slug in the URL, both on the web and in the API. An SRD slug is stable shipped data; the entity rule about ids exists because a user renames things, and nobody renames a bugbear. |
| Writing | Nothing writes a stat block except `demgem:import-srd`. No form, no endpoint, no `abilities:write` route. |
| Deleting | The loader never deletes. It upserts on `(ruleset, slug)` and reports rows the new file no longer names. Removing reference data under a live campaign's combatants is a surprise. |

## Content Licensing

This is the part of the slice that is not code, and it does not get skipped.

CC BY 4.0 is irrevocable, so the licence itself is settled and safe. What it asks for is attribution, and what it withholds is the trademark. Three rules follow, and each has a place in the repository.

1. **The notice is copied, never paraphrased.** `database/srd/ATTRIBUTION.md` holds the notice exactly as the SRD 5.2.1 document prints it, alongside `database/srd/LICENSE-CC-BY-4.0.txt`. The expected shape is "This work includes material taken from the System Reference Document 5.2 ... licensed under the Creative Commons Attribution 4.0 International License", but the text that ships is the text from page one of the document, transcribed by the implementer.

2. **The notice travels with the content.** It renders in a footer on the compendium index and on every stat block page, and it is a `license` and an `attribution` key on every stat block document the API returns. A script that pulls the data therefore receives the attribution it will itself owe.

3. **The trademark is not ours.** `Ruleset::Srd5e2024->label()` becomes `'SRD 5.2.1 (2024 rules)'`. No user-visible string names Dungeons & Dragons or D&D, and nothing implies that Wizards of the Coast endorses demgem. A test asserts this over the enum labels and the compendium views. The existing "D&D Beyond" mention at `app/Models/Entity.php:300` is a code comment describing a sheet host, and it stays.

A fourth rule is about provenance, and it is the one most likely to be got wrong quietly. Only the SRD is licensed. A community dataset that mixes SRD creatures with monsters from published books carries no licence at all for the second half, and a single beholder in a JSON file puts the whole compendium in the wrong. `database/srd/README.md` records where the file came from, which document version it represents, and its SHA-256, and a test fails when the file on disk stops matching the checksum in `config/compendium.php`. Swapping the dataset is then a deliberate commit rather than an accident.

The campaign export is unaffected on purpose. It carries a stat block as `{ruleset, slug}`, never its prose, so a GM's export redistributes nothing and needs no notice of its own.

## Repository Findings

Versions from `composer show --direct`: Laravel 13.30.1, Livewire 4.4.3, Scout 11.6.1, Sanctum 4.3.3, Pest 5.1.3. No dependency change is required, and none is proposed.

| Existing piece | Integration |
|---|---|
| `app/Enums/Ruleset.php` | Two cases, read nowhere. Add `hasCompendium(): bool` so the next ruleset is one case and not a scattered comparison. Fix the label here. |
| `app/Actions/Encounters/AddCombatants.php` | `create()` already takes name, quantity, hp, ac and initiative bonus and numbers copies. Add `fromStatBlock()` beside `fromEntities()`, dispatching `EncounterChanged` once. Replace the docblock paragraph that says stat blocks do not exist. |
| `app/Models/Combatant.php` | Stats are copied onto the row so a deleted source still renders. `stat_block_id` follows `entity_id`: nullable, indexed, `nullOnDelete`, and the row survives without it. |
| `app/Livewire/Encounters/Tracker.php` | Nothing is live-bound and every edit is an explicit action. The compendium picker keeps that rule: choosing a stat block sets a property, and a separate Add button writes. One eager-loaded query per render is asserted by an existing test; do not add a per-row lookup. |
| `app/Livewire/Entities/Index.php` | `whereRaw('lower(name) like ?')` is the search pattern the compendium index copies. |
| `app/Actions/Campaigns/ExportCampaign.php` | `EXCLUDED_TABLES` needs no entry: `ExportCoverageTest` keeps only tables with a `campaign_id`, and `stat_blocks` has none. Say so in a comment so nobody adds one. |
| `app/Actions/Campaigns/ImportCampaign.php` | Every id is remapped through `IdMap`, and `newFor()` throws on an unknown one. A global stat block id must therefore never enter `IdMap`. Resolve `{ruleset, slug}` against the local table, leave null when absent, and count it in `ImportReport`. |
| `routes/web.php` | Do not name a parameter after a model. Use `{statBlockSlug}`. |
| `routes/api.php` | The compendium sits inside the `/campaigns/{campaign}` group, because the ruleset and the role both need a campaign to be read from. Read-only, so no `abilities:write` block. |
| `app/Support/Dice/DiceRoller` | Takes an injected `Random\Randomizer`. Rolled hit points go through it, and `RollDice::roll()` is the unthrottled path a GM's one click already uses for a dozen combatants. |
| `docker/entrypoint.sh` | Runs migrations on boot. The loader command joins it, so a self-hoster gets the compendium without a second command. |

## Technical Approach

### Data

`stat_blocks`, global, ULID primary key, unique on `(ruleset, slug)`:

- Identity: `ruleset`, `slug`, `name`, `source`, `license`.
- Shape: `size`, `creature_type`, `subtype`, `alignment`.
- Numbers: `ac`, `ac_note`, `hp`, `hit_dice`, `initiative_bonus`, `cr`, `cr_value`, `xp`.
- Lists: `ability_scores`, `speed`, `saving_throws`, `skills`, `senses`, `languages`, `resistances`, `immunities`, `vulnerabilities`, `condition_immunities`.
- Prose: `traits`, `actions`, `bonus_actions`, `reactions`, `legendary_actions`, each a JSON list of `{name, text}`.

Three column decisions carry a reason.

`ability_scores` is one JSON column and not six, which is the exception the calendar rule already records: one configuration read whole, replaced whole, never queried by row, never gated. It also sidesteps naming a column `int`, which Postgres would make an argument about. The rule against a column named `attributes` applies here as everywhere; nothing in this table is called that.

`cr` is a string and `cr_value` is a `decimal(6,3)` beside it. A challenge rating of `1/4` is a label, and sorting or filtering needs a number. Storing only the number loses the fraction the GM reads; storing only the label sorts 10 before 2.

Every JSON column here is a `json` column, not `text`. The rule about `custom_fields` exists because Scout's database engine searches with `ilike` and Postgres has no `ilike` for json. The compendium is not searchable through Scout and no query touches these columns with `like`, so the reason does not apply. If a later slice searches trait text, that column moves to `text` in the same commit.

`combatants.stat_block_id` and `entities.stat_block_id` are both nullable `foreignUlid`, `constrained()->nullOnDelete()`. Unlike `encounters.active_combatant_id`, this constraint is not circular and a real foreign key is correct.

### The dataset and its provenance

`database/srd/srd-5.2.1-creatures.json` holds the rows. `database/srd/README.md` records the upstream, the document version, the date, and the SHA-256. `config/compendium.php` holds `dataset_path`, `dataset_sha256` and `attribution`.

`php artisan demgem:import-srd` reads the file, checks the checksum, and upserts on `(ruleset, slug)` inside a transaction. It prints created, updated, and no-longer-named counts, and it deletes nothing. `--check` validates and reports without writing. `DatabaseSeeder` calls it, and `docker/entrypoint.sh` runs it after `migrate`.

### The compendium on screen

`App\Livewire\Compendium\Index` and `App\Livewire\Compendium\Show`, both using `InteractsWithCampaign` and calling `enterCampaign()` in their own `mount()`, because the hydrate hook runs per component.

Both call `Gate::authorize('viewCompendium', $campaign)` in `mount()`. That policy is GM roles and `Ruleset::hasCompendium()`, and it uses the `EntityPolicy::roleFor()` fallback so a nested component resolves the role without `CurrentCampaign`.

The index searches on name, filters on creature type and challenge rating, paginates, and orders by `cr_value` then `name`. The show page renders one stat block with the licence footer, and offers "Add to the current fight" when the campaign has an active encounter.

### Into the fight

`AddCombatants::fromStatBlock(Encounter $encounter, StatBlock $statBlock, int $quantity = 1, bool $rollHitPoints = false)`. It reuses `create()` for numbering and positions, so "Goblin 1" through "Goblin 4" keeps working, and it dispatches `EncounterChanged` once for the group.

`player_visible` stays false. The existing rule is the right one and this slice does not touch it: a monster the GM adds waits for the eye toggle, because an ambusher that reaches the party's screens before it reaches the fiction is the last time they trust the feature.

With `$rollHitPoints`, each copy rolls its own `hit_dice` through `DiceRoller` and gets its own `hp` and `max_hp`. Without it, every copy takes the average from the dataset, which is today's behaviour with a number the GM did not type.

The tracker gains a picker beside the manual add form. The manual form stays exactly as it is: a generic campaign has no compendium, and a GM improvising a bandit still wants three boxes.

### Entities and the prep bucket

`entities.stat_block_id` is a scalar, one-to-one with the row, so it is a column and not a child table — the rule the models file settles. It is a GM field, inside the `canEditDmFields` block, and it is offered only when the ruleset has a compendium.

`AddCombatants::fromEntities()` then reads `$entity->statBlock` and copies the numbers, so one click on a session's Monsters bucket fills the turn order properly. An entity with no stat block behaves as it does today.

### API

Two read-only routes inside the existing campaign group:

    GET /api/v1/campaigns/{campaign}/compendium/stat-blocks
    GET /api/v1/campaigns/{campaign}/compendium/stat-blocks/{slug}

`StatBlockController` authorises through the same policy, so a player's key is a 403 and a generic campaign is a 404. `App\Http\Resources\Api\V1\StatBlockResource` carries `source`, `license` and `attribution` on every document. There is no write route, no store, no update, and no delete.

### Export and import

`ExportCampaign` emits a stat block reference as `{"ruleset": "...", "slug": "..."}` on both entities and combatants, and never its prose. Adding a key does not bump `VERSION`, which is the settled rule from slice 9.

`ImportCampaign` resolves each reference against the local `stat_blocks` table by `(ruleset, slug)`. A hit sets the column, a miss leaves it null and increments a new `ImportReport` counter, and nothing goes near `IdMap`. This is the same shape as RSVPs exported by member name: the file cannot say what only this install knows.

## Implementation Phases

### Phase 0: The licence and the label

1. Add `database/srd/ATTRIBUTION.md`, `database/srd/LICENSE-CC-BY-4.0.txt`, and `database/srd/README.md` with the provenance fields filled in.
2. Change `Ruleset::Srd5e2024->label()` to `'SRD 5.2.1 (2024 rules)'`. Add `Ruleset::hasCompendium()`.
3. Update the README's Content licensing section from a promise to a statement, with the notice and the code/data licence split.
4. Add the trademark test over enum labels and views.

This phase is independently correct and independently mergeable. It fixes a live trademark problem whether or not the rest of the slice lands.

### Phase 1: The table and the dataset

1. `create_stat_blocks_table` migration.
2. `App\Models\StatBlock`, read-only, no `BelongsToCampaign`, with a `scopeForRuleset` and the challenge-rating ordering.
3. `config/compendium.php`, the data file, and `demgem:import-srd` with `--check`.
4. `DatabaseSeeder` and `docker/entrypoint.sh` wiring.
5. Tests: the checksum guard, the licence-and-source assertion over every row, and idempotency across two runs.

### Phase 2: The compendium on screen

1. `CampaignPolicy::viewCompendium()` with both gates.
2. Routes with `{statBlockSlug}`, registered inside the campaign group.
3. `Compendium\Index` and `Compendium\Show`, with the licence footer.
4. Nav entry, shown only when the policy allows it.
5. Tests: a player is refused, a generic campaign is refused, search and both filters return what they should, and one render is one query.

### Phase 3: From the compendium into the fight

1. `combatants.stat_block_id` and `entities.stat_block_id` migrations.
2. `AddCombatants::fromStatBlock()`, and the docblock correction.
3. The tracker picker, and the optional rolled hit points with a seeded randomizer test.
4. The entity form field, GM-only, ruleset-gated.
5. `fromEntities()` reading the entity's stat block.
6. Tests: quantities number correctly, `player_visible` stays false, a deleted stat block leaves the combatant whole, and a player's `/table` render carries no trait prose.

### Phase 4: The API, the round trip, and the product pass

1. The two endpoints, the resource, and the controller.
2. Export and import by `{ruleset, slug}`, with the report counter.
3. `RoundTripTest` extended with a stat block reference, including the missing-dataset case.
4. `tests/Feature/Api` coverage with `asKey()` and `withoutKey()`.
5. README updates and the browser pass at two widths.

## Acceptance Criteria

- A GM in an SRD campaign opens the compendium, searches "goblin", filters to challenge rating 1 or less, and reads the whole stat block.
- The same GM adds four goblins to the fight in one action, and each row carries hit points, armour class and an initiative bonus without a number being typed.
- With rolled hit points on, the four goblins have four different totals.
- A player who visits the compendium URL gets a 403, and a player's key gets a 403 from both endpoints.
- A GM in a generic campaign sees no compendium nav entry and gets a 404 from the routes.
- No user-visible string contains "D&D" or "Dungeons & Dragons".
- The attribution notice renders on the compendium index, on every stat block page, and in every API stat block document.
- `demgem:import-srd` run twice creates the same row count, and changes no ids.
- Editing the data file without updating `config/compendium.php` fails the suite.
- A campaign export contains no stat block prose, and re-importing it on an install with the dataset restores the links; on an install without it, the import succeeds, the links are null, and the report says how many.
- `ExportCoverageTest` still passes with no new entry.

## Verification

    php artisan test --compact --filter=Compendium
    php artisan test --compact --filter=StatBlock
    php artisan test --compact --filter=Encounter
    php artisan test --compact --filter=RoundTrip
    vendor/bin/pint --dirty --format agent
    vendor/bin/phpstan analyse

Then the full suite, and a browser pass at a laptop width and a tablet width: the index, the filters, one stat block, the add to a fight, and the same fight on `/table` as a player.

## Open Questions

1. **Where does the JSON come from?** Preference order: generate it from the official SRD 5.2.1 document; failing that, take a third-party dataset that names SRD 5.2.1 as its only source and CC BY 4.0 as its licence. Do not take a 5e.tools-shaped dump: those mix SRD and non-SRD content, and the non-SRD half is unlicensed. Whichever is chosen, `database/srd/README.md` records it and the checksum test pins it.
2. **Does the shipped file need localisation?** The SRD has German, Spanish, French and Italian releases. The `ruleset` and `slug` pair already leaves room for a language dimension, but localisation is a P2 item of its own and this slice ships English.
3. **Should `initiative_bonus` be stored or derived from dexterity?** Stored, on the argument that the dataset states it and derivation is a rules decision. Worth one look at the data before the migration is written.

## References

- `docs/brainstorms/2026-09-02-demgem-campaign-manager-brainstorm.md` — the P2 list this slice takes its item from.
- `.ai/rules/models.md` — a list gets a child table, a scalar gets a column; the calendar's JSON exception.
- `.ai/rules/migrations.md` — never name a column `attributes`; searchable JSON lives in text.
- `.ai/rules/campaigns.md` — the export coverage test, and how an import resolves what a file cannot name.
- `.ai/rules/table.md` — filter what a player may see in the query, never in a Blade.
- `.ai/rules/routes.md` — do not name a route parameter after a model.
- `.ai/rules/api.md` — the API is the screens in JSON.
- `.ai/rules/tests.md` — never assert a bare number against output carrying a ULID.
- https://www.dndbeyond.com/srd — the SRD releases and their licence.
