---
title: "feat: Family trees, drawn from typed relationships"
type: feat
date: 2026-09-13
status: implemented
brainstorm: docs/brainstorms/2026-09-02-demgem-campaign-manager-brainstorm.md
follows: docs/plans/2026-09-13-feat-downtime-plan.md
---

# feat: Family trees, drawn from typed relationships

## Overview

The brainstorm's world table lists family trees under P3. Slice 11 built relationships: a typed link between two entities with a label from one side and an optional reverse label, gated at both ends. A family is a set of those links with four meanings the app can read. This slice gives a relationship an optional kinship and draws what the kinships say.

| Feature | What it adds |
|---|---|
| Kinship on a relationship | One nullable column, `kinship`: parent, child, sibling, or spouse, read from the source's side. The labels fill in from it when the GM leaves them blank. |
| The tree | A "Family" card on a character's page: grandparents, parents, siblings, spouses, children, and grandchildren, walked from the typed rows in both directions. |
| The gate | The relations card's own scope, `EntityRelation::visibleTo()`, at both ends. No new gate. |
| The round trip | `kinship` on the relation rows in the export, the reader, the importer, and the API resource. |

When this slice is done a GM opens Mara Voss and reads that Abbess Corvane is her mother, and the abbess's page reads Mara as her daughter without a second row being written. The GM opens Wren and reads a sister the party has not been told about; the player opens Wren and reads no family at all.

**On scope.** Phase 0 is the column, the enum, and the form. Phase 1 is the tree. Phase 2 is the round trip, the API, the seeder, the rules, and the pass.

## Problem Statement

**"Daughter of" is a label the app cannot read.** A GM writes "mother of" on the abbess and "daughter of" back, and the two pages each show one line. Nothing joins them to the abbess's own mother, so a three-generation house is six lines on six pages and a diagram in the GM's notebook.

**A family is the one relationship graph every table draws.** Kanka and World Anvil both draw one. The relations card already holds the rows; what it lacks is the four words that make a row a branch.

**A tree that leaks is worse than no tree.** A family tree walks two hops from a page, and the second hop is where a careless implementation reaches a GM-only aunt through a revealed cousin. The relations scope already refuses a row whose either end the party may not see, and the tree has to walk through that scope and nothing else.

## Proposed Solution

**A kinship is one nullable column on the relation, read from the source's side.** `Kinship::Parent` means the source is a parent of the target; `Child`, the reverse; `Sibling` and `Spouse` read the same from both ends. `Kinship::reverse()` gives the other side's word, and the form fills a blank label and a blank reverse label from the two. A GM who types their own words keeps them: the labels are what the row says, and the kinship is what the app knows.

**The tree is one query and a walk.** The Relations component already renders the card on every entity page. For a character, it loads every kinship row the viewer may see in the campaign, one query through `EntityRelation::visibleTo()` with both ends eager-loaded, and walks up two generations and down two in memory. A campaign's kinship rows are a handful, and one query for the whole graph is what makes the second hop as safe as the first: a row that the scope refused is not in the graph to walk through.

**Drawn as generations, not as a diagram.** Six rows of name chips, each labelled: grandparents, parents, siblings, spouses, children, grandchildren, with only the rows that have someone in them. A GM's page marks a chip whose row the party has not been told, the relations card's way.

## Technical Approach

### No new dependency

### What slices 1 to 25 give us for free

| Piece | Reuse |
|---|---|
| `EntityRelation::scopeVisibleTo()` | Both gates, and the tree walks through it. |
| `Entities\Relations` | The card, the form, the controls, and the per-page lookup. The tree is one more section of the same component. |
| `RelateEntities` | Takes the kinship as one more argument. |
| `EntityResource` | Gains the key on both relation lists. |
| `RoundTripTest` | Compares `kinship` in full without a change. |

### The data

```
entity_relations
  kinship   string(8) nullable   parent | child | sibling | spouse
```

### Scope and decisions

| Question | Decision |
|---|---|
| Which types | Any entity may carry a kinship row, because the column is on the relation. The tree is drawn on characters only. |
| The labels | Filled from the kinship when blank. A GM's own words win. |
| The generations | Two up, two down. Siblings and spouses of the page only. A wider walk is a follow-up. |
| A sibling through shared parents | Not inferred. The tree draws what was typed. |
| The card | Shown when at least one kinship row the viewer may see touches the character. |
| The API | `kinship` on both relation lists of the show route. The API does not write relations today and this slice does not change that. |
| The vault | Unchanged. The label already carries the word. |
| Changing a kinship | Remove the row and relate again, as with a label. |

### Screens

- **The relations card** gains a "Kinship" select on the form, and a "Family" card above the list on a character's page.

### The round trip

- `kinship` on each relation row. The reader accepts an absent key and refuses a value it does not know. No version bump.

## Verification

    php artisan test --compact tests/Feature/Entities/FamilyTreeTest.php
    php artisan test --compact tests/Feature/Entities/RelationsTest.php tests/Feature/Entities/RelationVisibilityTest.php
    php artisan test --compact tests/Feature/Api
    php artisan test --compact --filter=RoundTrip
    php artisan test --compact --filter=DemoSeeder
    vendor/bin/pint --dirty --format agent
    vendor/bin/phpstan analyse

Then the full suite with `memory_limit=1G`, and a browser pass: as the GM, relate Abbess Corvane as parent of Mara Voss and read the family on both pages; open Wren and read the hidden sister; as the player, open Wren and find no family card, and Mara with the abbess.

## Open Questions

1. **Should the tree draw an SVG with lines?** Recommendation: not now. Generations as rows read on a phone; a diagram does not.
2. **Should a kinship row show on the timeline or a calendar?** Recommendation: no. Birth dates are a field, not this slice.

## References

- `.ai/rules/entities.md` — relationships are a GM's to write; the target picker and the per-page lookup.
- `.ai/rules/models.md` — a relationship is gated at both ends, in the scope.
- `.ai/rules/api.md` — an added key on a resource.

## Implementation Results — 2026-09-13

Implemented in full. 8 new tests; the suite is 1442 tests, 1441 passing, 1 skipped, with Larastan clean and Pint clean.

### What shipped, against the plan

| Planned | Shipped |
|---|---|
| `entity_relations.kinship` | As planned, cast to `Kinship`, with `label()`, `reverse()`, and `plural()` on the enum. |
| The labels from the kinship | `required_without:kinship` on the label; a blank label and a blank reverse label take the two words, and typed ones win. |
| The tree as one query and a walk | `Relations::family()`: every kinship row the viewer may see, both ends eager-loaded, walked two up and two down in memory. The leak test reads a hidden spouse and a GM-only sister from the player's seat and finds neither in the markup or the snapshot. |
| The card | A "Family" card above "Relationships" on a character's page, generations as rows of name chips, with a "Hidden" mark for a GM. |
| The round trip and the API | `kinship` on every relation row; the reader accepts an absent key and refuses a value it does not know; both API relation lists carry it, the incoming one from the page's side. |
| The demo world | Abbess Corvane is Mara's mother, revealed; Iselle Ashgrove is a new GM-only page, Wren's sister, on a hidden row. |

### Deviations

| Planned | Shipped | Why |
|---|---|---|
| `RelationsTest` unchanged | One expected array gained `kinship: null` | The test compares the exported relation row in full, and an added key is the export policy. |

### Browser checks

Driven end to end on the seeded world at 1400px as the GM and 1024px as the player:

- On Mara Voss the GM picks Abbess Corvane, "Child of", and "Show the party", with both labels blank. The row reads "child of Abbess Corvane" and the Family card reads "Parents: Abbess Corvane".
- The abbess's page reads "parent of Mara Voss" and "Children: Mara Voss" without a second row.
- The player opens Mara and reads the same Family card, and no picker.
