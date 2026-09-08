---
title: "feat: Relationships, and the two gates on every one of them"
type: feat
date: 2026-09-08
brainstorm: docs/brainstorms/2026-09-02-demgem-campaign-manager-brainstorm.md
follows: docs/plans/2026-09-07-feat-scheduling-rsvp-reminders-plan.md
---

# feat: Relationships, and the two gates on every one of them

## Overview

The brainstorm's row reads "Entity relationships with labels — P2 — simple list first, graph view later." This is the simple list.

| Feature | What it adds |
|---|---|
| A relationship | One entity to another, with a label written from the first one's side: "employer of", "hunted by", "twin of". |
| The other side | An optional reverse label, so the duke's page says "employer of Mara" and Mara's says "works for the duke". Without one, the other side reads the label backwards. |
| The reveal | `player_visible`, the eye the tracker and the map already have. The party learns the twin exists when the GM says so. |
| Both pages | A **Relationships** card on the entity page, outgoing and incoming in one list, with the GM's controls inline. |
| The vault | A "Relationships" section in each entity's Markdown, as wiki links, so Obsidian's graph draws them. |

When this slice is done a GM writes "twin of" once and both siblings' pages know, the party sees it the night the GM decides, and the Obsidian export draws the line.

**On scope.** Phase 0 is a release: relationships exist, GMs manage them, and they are exported. Phase 1 adds the eye and the party's view. Phase 2 is the vault section, the seeder, and the pass. None is cuttable; they are small.

## Problem Statement

**The wiki knows who is mentioned, not who is what to whom.** `[[Mara Voss]]` in the duke's body makes a backlink, and a backlink says "these two pages mention each other" and nothing else. Every campaign manager this project measures itself against, Kanka, World Anvil, LegendKeeper, has a typed link between two entities, and it is the second thing a GM reaches for after the wiki link itself: the faction's leader, the NPC's employer, the item's owner, the location's ruler.

**Custom fields are the wrong tool for it.** A GM can write `employer: The Drowned Duke` in `custom_fields` today, and the duke's page will never know. A relationship is a fact about two rows and it has to live between them.

**And the hard part is the same as the map pin's.** A relationship is a link, and a link to something the party may not see is a leak with a label on it. Slice 6 settled the rule for pins: visible when the GM revealed it *and* the target passes `Entity::visibleTo()`. A relationship inherits that rule unchanged, and adds one thing: it has two ends, and the party may be standing at either.

## Proposed Solution

**A relationship is a row between two entities, with a label from one side.** `entity_relations`: `entity_id`, `target_entity_id`, `label`, `reverse_label`, `player_visible`, `position`. Directed, because "employer of" is not symmetrical, and the reverse label is optional because half of all relationships read fine backwards and the other half need a word.

**It is gated at both ends, in the query.** `EntityRelation::scopeVisibleTo()` for a non-DM requires `player_visible` and both `entity_id` and `target_entity_id` to pass `Entity::visibleTo()`. The pin gates its target; a relationship also gates its source, because the incoming list on Mara's page is built from rows whose source is somebody else, and that somebody may be GM-only. The gate is in the scope, so neither list can render a name the viewer may not see, whichever end they are standing at.

**The target cascades, unlike a pin's.** A pin with no target is still a pin, a point on a map with a label. A relationship with no target is nothing, so `target_entity_id` is not nullable and deleting either entity deletes the row.

**No `relations` entity type, no graph, no new kit component.** The card is a list and a form built from the pieces the map viewer's pin editor already uses.

## Technical Approach

### No new dependency

Nothing to install.

### What slices 1 to 10 give us for free

| Piece | Reuse |
|---|---|
| `Entity::visibleTo()` | Both gates. |
| `MapMarker` | The shape: ULID child table, `player_visible`, `scopeVisibleTo`, `SetMarkerVisibility`, the eye toggle, the export section, the importer's remap through `IdMap::newFor()`. |
| `Maps\Viewer::targetOptions()` | The picker: every entity the viewer may see, by name, with its type. |
| `Entities\Show` and `Sessions\Attendance` | A nested Livewire component that writes and re-checks membership itself. |
| `WriteCampaignMarkdown::entity()` | A section per entity, wiki links left as written. |

### The data

```
entity_relations
  id                 ulid
  campaign_id        ulid  -> campaigns, cascade
  entity_id          ulid  -> entities, cascade      the side the label is written from
  target_entity_id   ulid  -> entities, cascade      the other side
  label              string(60)
  reverse_label      string(60) nullable
  player_visible     boolean default false
  position           unsigned int default 0
  timestamps
  index (entity_id, player_visible)
  index (target_entity_id, player_visible)
```

No unique index on the pair: "employer of" and "hunted by" can both be true of the same two people.

### The card

`Entities\Relations`, nested in `Entities\Show` under the body, with `InteractsWithCampaign` and its own `enterCampaign()`. It renders one list:

- **Outgoing:** `label` and the target's name, linked.
- **Incoming:** the source's name, linked, and `reverse_label` when there is one; otherwise the label with the source named first, "The Drowned Duke · employer of".

Two queries, both through `scopeVisibleTo`, with `target` and `source` eager-loaded. A GM sees an eye per row, a remove button, and a form: the picker, the label, the reverse label, and a "show the party" checkbox. Players see the list.

### Export and import

`entity_relations` joins `NESTED_TABLES` as `entities[].relations`, exported from the source side with `target_entity_id`. The importer writes them after every entity exists, in the same pass as the markers, remapping both ids through `IdMap::newFor()`. A relation whose target is not in the file is dropped and counted as truncated, the way the reader already treats a dangling reference.

`WriteCampaignMarkdown::entity()` adds a "## Relationships" section listing `- employer of [[Mara Voss]]` for outgoing rows and `- works for [[The Drowned Duke]]` for incoming ones that have a reverse label. Obsidian draws the graph from the links.

## Decisions resolved

| Question | Decision |
|---|---|
| Directed or symmetric | Directed, with an optional reverse label. "Employer of" is not symmetric, and a symmetric table needs a rule for which side the label belongs to. |
| A relationship type enum | No. Free text, 60 characters. The brainstorm said labels, and every campaign's vocabulary is its own. |
| Nullable target | No. A relationship with nothing on the other end is nothing, so both ends cascade. |
| Visibility | `player_visible` plus both entities through `Entity::visibleTo()`, in the scope. The pin's rule with a second gate on the source. |
| Who manages them | GM roles, through `EntityPolicy::update` on the source entity. A player editing their own PC does not get to link it to the duke; that is a wiki fact rather than a sheet fact. |
| Where the card lives | Under the body on the entity page, both directions in one list. |
| Editing a row | Remove and add. A label is a word or two, and an edit form is a second form for a first release. |
| Broadcasts | None. |
| New tables | One: `entity_relations`. |
| New kit components | None. |

## Implementation Phases

### Phase 0: Relationships a GM can write

Deliverables:
- Migration `create_entity_relations_table`.
- `App\Models\EntityRelation`, `Entity::relations()` and `Entity::incomingRelations()`, factory.
- `app/Actions/Entities/`: `RelateEntities`, `RemoveRelation`.
- `App\Livewire\Entities\Relations`, nested in `Entities\Show`: the list and the GM form.
- `entity_relations` joins the export nested under entities, the reader validates it, the importer remaps both ends.

Tests: `tests/Feature/Entities/RelationsTest.php` — a GM relates the duke to Mara with a label and a reverse label, both pages list it, the duke's page says "employer of" and Mara's says "works for"; without a reverse label Mara's page names the duke first; a GM removes it; a player may not add or remove; a target outside the campaign is a 404; a label over 60 characters is refused; the export nests it and the round trip restores it with both ids remapped; a relation to an entity missing from the file is dropped and counted. `ExportCoverageTest` passes.

Success: the GM writes "twin of" once and both pages know.

### Phase 1: The eye

Deliverables:
- `SetRelationVisibility`, the eye per row, and "show the party" on the form.
- `EntityRelation::scopeVisibleTo()`, both gates, in every query the card runs.

Tests: `tests/Feature/Entities/RelationVisibilityTest.php` — **a hidden relationship's label and the other entity's name are absent from a player's HTML and Livewire snapshot, on both pages**; a revealed one is present on both; **a revealed relationship whose target is GM-only is absent from the source's page for a player**; **a revealed relationship whose source is GM-only is absent from the target's page for a player**; the GM sees all four and which is which; a player may not toggle.

Success: the party learns about the twin on the night the GM decides.

### Phase 2: The vault, the seeder, and the pass

- The Markdown section, with a test that the links are wiki links and hidden rows are still written because the vault is the GM's own export.
- The seeder relates the duke, Mara, and the signet, one hidden.
- Empty states: no relationships, a player with nothing revealed (the card does not render at all for them).
- The tablet pass at 1024px and 768px, dark and light.
- Record the rules. Pint, Larastan, the full suite, `npm run build`.

## Alternative Approaches Considered

- **A symmetric table with one label.** Rejected: "employer of" read from the other side is wrong, and the fix is a second label, which is this design.
- **A relationship type enum with fixed pairs (parent/child, owner/owned).** Rejected: the brainstorm asked for labels, a fantasy campaign's vocabulary is not enumerable, and a free label with an optional reverse covers every pair the enum would.
- **Storing relationships in `custom_fields`.** Rejected: one side never knows.
- **Rendering the party's list with an `@if` on visibility.** Rejected, as every slice since 5 has rejected it: the row would be in memory and one edit from the page.
- **A graph view.** The brainstorm's "later". The Obsidian section draws one for free in the meantime.

## Acceptance Criteria

### Functional

- [x] A GM relates two entities with a label and an optional reverse label, and both pages show it.
- [x] A GM removes a relationship.
- [x] A GM reveals a relationship to the party and hides it again.
- [x] Relationships join the export and survive the round trip with both ids remapped.
- [x] The Markdown export writes a Relationships section as wiki links.

### Non-functional

- [x] **A hidden relationship's label and the other entity's name never reach a player's HTML or snapshot, from either page.**
- [x] **A revealed relationship whose other end is GM-only never reaches a player, from either page.**
- [x] Both lists are filtered in the query, never in the template.
- [x] The card costs a constant number of queries. *(Two for the lists, plus the picker for a GM.)*
- [x] Both new screens work at 1024px and 768px, dark and light, with no sideways scroll.

### Quality gates

- [x] Pest suite green on SQLite locally: 1,035 tests. PostgreSQL in CI is the pull request's job.
- [x] Larastan level 6 clean. Pint clean, on the whole tree, before every commit.
- [x] No new `x-ui.*` component. No new dependency.

## Dependencies & Risks

| Risk | Mitigation |
|---|---|
| A hidden or half-hidden relationship leaks | Both gates in the scope, a leak test file with four cases, both pages. |
| A GM relates an entity to itself | Refused at write time. It is silly rather than dangerous, but it renders as nonsense. |
| The picker lists a thousand entities | It is the map viewer's picker, and the map viewer has the same list. A search box is the day it hurts. |

## What the browser pass found

Nothing broken in the layout. The card sits under the body at 1024px and 768px, dark and light, with no sideways scroll and no console errors; at 768px the form's two columns stack to one. The player's copy of Mara's page shows the two revealed rows and the hidden "secretly works for" is absent from the HTML; the duke's page, whose only relationships are hidden or point at hidden pages, renders no card for the player at all.

**The form and the eye were driven through the page's own DOM events rather than the browser tool's clicks.** The tool's synthetic click on a Livewire button on this page reached neither the server nor the DOM, on the eye and on Relate alike, while `element.click()` and `form.requestSubmit()` on the same elements did both. That is a fact about the tool, and the Livewire tests cover the same three calls. Through those events a GM created "hunting / hunted by" from the duke's page to Wren with the values Livewire had bound and revealed it. The player's copy of Wren's page still showed nothing, and that is right: the duke is a GM-only page, so the source gate held even though the GM had pressed the eye. The party-visible half of the proof is the seeded rows on Mara's page, which the player sees. What has not been seen is a human's click on this card, and that is the one thing left to check by hand.

## Future Considerations

- **A graph view.** The rows are the edges; a viewer is a canvas and a layout.
- **Editing a label in place.** When remove-and-add annoys somebody.
- **Relationships on the character sheet.** "Allies and enemies" is this table filtered to a PC.

## References

### Internal

- Slice 6 plan: `docs/plans/2026-09-03-feat-maps-markers-plan.md` — the pin's two gates
- Patterns to copy: `app/Models/MapMarker.php`, `app/Actions/Maps/SetMarkerVisibility.php`, `app/Livewire/Maps/Viewer.php` (the picker), `tests/Feature/Maps/MarkerVisibilityTest.php`
- Project rules: `.ai/rules/models.md`, `.ai/rules/entities.md`, `.ai/rules/campaigns.md`, `.ai/rules/tests.md`
