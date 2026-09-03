---
title: "feat: Maps, and the pins on them"
type: feat
date: 2026-09-03
brainstorm: docs/brainstorms/2026-09-02-demgem-campaign-manager-brainstorm.md
follows: docs/plans/2026-09-03-feat-live-table-reverb-plan.md
---

# feat: Maps, and the pins on them

## Overview

The brainstorm's Maps table has four rows and three of them are P2. This builds all three.

| Feature | What it adds |
|---|---|
| A map is an entity | `EntityType::Map`. An image, a body, DM notes, tags, wiki links, backlinks, search, and the three-way visibility every other entity already has. |
| The viewer | Pan and zoom, written by hand in Alpine, on a phone, a tablet, and a laptop. |
| Markers | A pin at a point on the image, pointing at any entity, with a label copied at creation. |
| Two layers | `map_markers.player_visible`, the same eye toggle the tracker got in slice 5. A pin the party has not found is not on their map. |
| Nesting | A pin whose target is another map. The world pins the duchy; the duchy pins the city. One column does it. |

When this slice is done a GM uploads the map they already have, drops pins on it, and reveals them as the party finds the places. A player opens the same map and sees the half they have earned.

**On scope.** Phases 0 to 3 are a release: a GM has maps and a party can read them. Phases 4 and 5 are a second. Phase 5 is cuttable, and cutting it changes nothing else in the slice.

## Problem Statement

demgem holds a world in words and has nowhere to put a picture of it. `entities.parent_id` nests a tavern inside a district inside a city, and the entity page renders that chain as a breadcrumb, which is a map in words and reads like a filing cabinet. Every GM in the audience already has a map: a Wonderdraft export, a scan of something drawn on graph paper, a screenshot of somebody else's Inkarnate. Today it goes in the body of a note as one image nobody can zoom.

The competitors this project names — Kanka, World Anvil, LegendKeeper — all have maps with markers, and it is the first thing their users show each other. It is the largest single gap in the campaign bible, and it is the reason a GM bounces off demgem in the first ten minutes with a world already written somewhere else.

The player half is worse. A player who wants to know where they are has a wiki page with a parent link. The party's own sense of the world is the one thing the app has never given them, and the tracker proved in slice 5 that the party's screen is worth building for.

## Proposed Solution

**A map is an entity, not a new tree.** `EntityType::Map` joins the six. It inherits `Entity::visibleTo()`, `entity_viewers`, DM notes, tags, `[[The Duchy of Vell]]`, backlinks, search, the sidebar count, the export's entity section, and every leak test already written against all of them. The alternative is a `maps` table with a parallel `Map::visibleTo()`, which is a second place for a visibility bug to live in an app whose hardest tests are all leak tests. Slice 4 rejected a parallel `/player/...` tree for that exact reason and the reasoning has not changed.

**Markers are a child table, because a list gets one.** `.ai/rules/models.md` settled this in slice 4: a scalar gets a column and a list gets a table. Pins are many rows per map, individually placed, individually revealed. `quest_objectives` is the precedent, down to the column name.

**Coordinates are percentages, never pixels.** A pin at `x = 41.250, y = 68.900` is 41.25% across and 68.9% down, whatever the image is. A pixel coordinate breaks the day the GM replaces a 2000px export with a 6000px one, and it breaks on every screen that is not the one the pin was placed on.

**No map library.** The viewer is one image, a `transform`, and pointer events. Leaflet is a tile server's client and brings 145kB for a feature that is `scale()` and `translate()`. The project ships no third-party JavaScript but Echo, the Markdown autocomplete is hand-written already, and a map that is one image does not need a tile pyramid.

**A pin is visible when the GM revealed it and its target is visible.** Two gates, both already understood. The first is `player_visible`, the boolean and the eye toggle slice 5 built for combatants. The second is `Entity::visibleTo()` on the target. A GM who reveals the pin for a GM-only NPC has made a mistake, and the app should not turn it into a leak.

## Technical Approach

### No new dependency

Nothing to install. The viewer is Alpine, which Livewire already bundles; the image pipeline is Spatie Media Library, which entities already use; and the coordinates are two decimal columns.

### What slices 1 to 5 give us for free

| Piece | Reuse |
|---|---|
| `EntityType` | One case, six `match` arms, and the map has a label, a plural, a slug, an icon, a description, and a wiki-link priority. |
| `Entity::visibleTo()` | The map's own visibility, with nothing new to write and every existing test still guarding it. |
| `Entity` media | `addMediaCollection('image')->singleFile()` is the map image. `thumb` (320 crop) is the card on the index. The full-size original is the viewer's source. |
| `MarkdownRenderer` + `WikiLinkRenderer` | A map's body and DM notes render exactly like a location's. |
| `EntityPolicy` | The map inherits `view`, `update`, `delete`, and `viewDmFields`. Markers authorize through `update` on the map, the way combatants authorize through `update` on the encounter. |
| The autocomplete controller | Picking a pin's target is the endpoint the Markdown editor already calls. |
| `combatants.player_visible` | The same column name, the same default, the same eye icon, the same sentence in the UI. A GM learns it once. |
| `x-ui.*` kit | **Budget: one new component**, `x-ui.map-pin`, because a pin is drawn on two screens in three states and inlining it three times is how it drifts. |
| The slice 5 channel | Phase 5 only. One event, no new channel, no new callback. |

### The data model

One new table. No new columns on `entities`.

```
map_markers   id                  ulid, primary
              campaign_id         ulid, cascade
              entity_id           ulid, cascade      -- the map this pin is on
              target_entity_id    ulid, nullOnDelete -- what it points at, nullable
              label               string 120         -- copied at creation
              x                   decimal(6,3)       -- 0.000 to 100.000
              y                   decimal(6,3)
              player_visible      boolean, default false
              timestamps

              index (entity_id, player_visible)
              index (target_entity_id)
```

- **`entity_id` is the map, `target_entity_id` is the target.** Two foreign keys to `entities` on one row and no cycle between tables. `quest_objectives.entity_id` already means "the entity that owns this child row", so the name carries its meaning across.
- **`label` is copied, not read through the target.** `Combatant::name` settled this in slice 3: a deleted NPC still leaves a complete row. A pin whose target is deleted degrades to a label on the map instead of vanishing mid-session.
- **A pin with no target is a pin.** "Here be dragons" is a real annotation and it needs no entity behind it.
- **`decimal(6,3)`, cast to float.** Exact on PostgreSQL and on SQLite, and 0.001% is 0.06px on a 6000px map. A float column would be portable too; a decimal says the precision out loud.
- **No `position` column.** Pins have no order. They have coordinates.

### Nesting is a marker, not a parent column

The brainstorm sketches `maps.parent_map_id`. This slice does not build it, because a marker whose `target_entity_id` is another map already *is* the nesting, and a parent column would be a second source of truth for the same fact.

The reverse question, "which maps show this one", is the backlinks query the app already answers for prose, and the map page answers it the same way, in the same words: **Appears on**. Two maps may pin the same city and that is correct rather than a conflict, which a single parent column could not represent.

A cycle is possible and harmless: the viewer follows one link per click and never walks a chain, so there is nothing to guard.

### The viewer

`App\Livewire\Maps\Viewer`, nested, handed an entity id.

The Livewire component renders the image and the pins the viewer may see, and then does nothing at all while a person pans. Pan and zoom are Alpine state and CSS transforms; they never touch the server. The server round trips only when the GM places, moves, or reveals a pin.

```js
// resources/js/map.js, registered on alpine:init like markdownEditor.
Alpine.data('mapViewer', () => ({
    scale: 1, x: 0, y: 0,          // the transform
    // Pointer events, not mouse events: one code path for a mouse, a pen, and
    // a finger, which is what makes the tablet work without a second branch.
}))
```

- **Zoom clamps to 1x–8x**, and 1x is "fit the width". A GM cannot lose the map off the edge of the screen, because panning clamps to the image's own bounds at the current scale.
- **Pins scale inversely**, `scale(1 / mapScale)`, so a pin is the same size on screen at every zoom. A pin that grows with the map becomes a dinner plate at 8x.
- **Two fingers pinch.** `pointerdown` tracks a second pointer and the distance between them drives `scale`. There is no third-party gesture library and there does not need to be one.
- **The image is `loading="eager"` and sized.** A map is the point of the page, not a decoration below the fold.

### Placing a pin

GM only, and it is a mode rather than a form. The GM presses **Add a pin**, the cursor becomes a crosshair, and the next click on the image is the coordinate. Livewire receives `x` and `y` as percentages from the click handler, writes the row, and the pin appears where the click was.

```php
// The click handler hands over percentages, so the server never learns the
// screen size and the same number means the same place on every device.
$this->authorize('update', $this->map);

app(PlaceMarker::class)->handle($this->map, $x, $y, $label, $target);
```

Then the GM names it: an autocomplete on the existing endpoint picks a target entity and fills the label from it, or they type a label and leave the target empty. Dragging a placed pin moves it, with the same percentage arithmetic.

**Every write is an action** in `app/Actions/Maps/`, as the tracker's are, so the encounter page, the map page, and any future API get one answer: `PlaceMarker`, `MoveMarker`, `UpdateMarker`, `RemoveMarker`, `SetMarkerVisibility`.

### What a player sees

Two gates, and both run in the query:

```php
// app/Models/MapMarker.php
public function scopeVisibleToPlayers(Builder $query, User $user, CampaignRole $role): Builder
{
    if ($role->isDm()) {
        return $query;
    }

    return $query->where($query->qualifyColumn('player_visible'), true)
        ->where(function (Builder $target) use ($user, $role): void {
            // A pin with no target is only ever gated by the eye. A pin that points
            // at something is gated by that thing as well: a GM who reveals the pin
            // for a GM-only NPC made a mistake, not a decision.
            $target->whereNull($target->qualifyColumn('target_entity_id'))
                ->orWhereIn($target->qualifyColumn('target_entity_id'), /* Entity::visibleTo() ids */);
        });
}
```

The filter is in the query and never in the Blade, which is the rule `.ai/rules/table.md` recorded in slice 5. A hidden pin is not loaded, so it is not in the HTML, not in the snapshot, and not in the DOM for a curious player to find.

The map itself is gated once, by `Entity::visibleTo()`, before any of this runs.

### The one thing that is genuinely new

**A click on an image has to become a number the server can trust.** It cannot: a browser sends whatever it likes. So the action clamps `x` and `y` into 0–100 and rounds to three places, and a forged coordinate can at most put a pin somewhere silly on a map the forger can already edit. That is the whole threat model, and it is worth one sentence in the action rather than a validation ceremony.

## Decisions resolved

| Question | Decision |
|---|---|
| A `maps` table or an entity type | An entity type. It inherits visibility, search, wiki links, backlinks, tags, DM notes, and the export, and it adds no second place for a visibility bug to live. |
| Markers in a column or a table | A table. Slice 4's rule: a list gets a child table. |
| Pixels or percentages | Percentages, `decimal(6,3)`. A pixel breaks on the next screen and on the next image. |
| A map library | None. One image, a transform, and pointer events. Leaflet is a tile client and this is not a tile problem. |
| Nesting | A marker whose target is another map. No `parent_map_id`, no second source of truth, and "Appears on" is the backlinks query the app already runs. |
| Marker visibility | `player_visible`, the boolean and the eye from slice 5, **and** the target's own visibility. Two gates, both in the query. |
| A pin with no target | Allowed. "Here be dragons" is an annotation, not a broken row. |
| The label | Copied at creation, like `Combatant::name`. A deleted target leaves a readable pin. |
| Marker ordering | None. Pins have coordinates, not an order. |
| Where the writes live | Actions in `app/Actions/Maps/`, as the tracker's are. |
| New tables | One: `map_markers`. |
| New columns on `entities` | None. |
| New kit components | One, `x-ui.map-pin`, drawn on two screens in three states. |
| Live reveals | Phase 5, cuttable. One event on the slice 5 channel, no new channel and no new callback. |

## Implementation Phases

Each phase ends with a green suite. Phases 0 to 3 are a release; Phases 4 and 5 are a second.

### Phase 0: A map is an entity

Deliverables:
- `EntityType::Map`, with its six `match` arms and a wiki-link priority below Note.
- The `map` icon in `x-ui.icon`, and `maps` in the route pattern by way of `EntityType::slugs()`.
- The entity index, form, and page accept the new type. The only branch is the image: it renders full width instead of in the aside, and its cap is 10MB rather than 5MB.
- The image stays **optional**. A GM should be able to make the row tonight and find the file tomorrow, and the page says so rather than refusing to exist. Phase 6's "a map with no image" empty state is built here instead.
- The sidebar's World group counts maps like every other type.

Tests: `tests/Feature/Maps/MapEntityTest.php` — a GM creates a map with an image; a player sees a shared one and not a DM-only one; the wiki link `[[World map]]` resolves; the map appears in search and in the export.

Success: a GM uploads a map, and it behaves exactly like every other entity.

### Phase 1: The viewer

Deliverables:
- `resources/js/map.js` with the `mapViewer` Alpine component, registered on `alpine:init` beside `markdownEditor`.
- `App\Livewire\Maps\Viewer`, nested, rendering the image and nothing else yet.
- The entity page renders the viewer instead of the plain image when the type is Map.
- Zoom buttons, a reset, and a scale readout. All 44px, from the first commit rather than in the tablet pass.

Tests: the viewer's markup and its guards, in `tests/Feature/Maps/MapViewerTest.php`. Pan and zoom are CSS and are checked in a browser, which the phase says out loud rather than pretending a Pest test covers a pinch.

Success: a GM opens a 6000px map on a tablet and can read the street names.

### Phase 2: Markers

Deliverables:
- Migration `create_map_markers_table`.
- `App\Models\MapMarker`, `Entity::markers()`, and the factory.
- `app/Actions/Maps/`: `PlaceMarker`, `MoveMarker`, `UpdateMarker`, `RemoveMarker`.
- Place by clicking, name by autocomplete or by hand, drag to move, click to edit, delete.
- `x-ui.map-pin`.
- `map_markers` joins `ExportCampaign::NESTED_TABLES` as `entities[].markers`, in this commit.

Tests: `tests/Feature/Maps/MarkersTest.php` (place, move, rename, retarget, delete; a coordinate outside 0–100 is clamped and written clamped; a deleted target leaves the label and nulls the link; a player cannot place, move, or delete one), plus `ExportCoverageTest` unchanged and passing.

Success: a GM pins twelve places on the duchy map in two minutes.

### Phase 3: What a player sees

Deliverables:
- `map_markers.player_visible` and the eye toggle per pin, GM only, with the sentence the tracker uses.
- `MapMarker::scopeVisibleToPlayers()`, the two gates, in the query.
- The player's viewer: the same component, no editing controls, no crosshair.
- Reveal-all and hide-all for a GM who just finished a session.

Tests: `tests/Feature/Maps/MarkerVisibilityTest.php` — **a hidden pin's label and coordinates are absent from a player's HTML and Livewire snapshot**; **a revealed pin whose target is DM-only is absent as well**; a revealed pin with no target is present; the GM sees every pin and which are hidden; the player surface audit gains a map.

Success: the GM reveals the Salt Cathedral and it appears on the party's map.

### Phase 4: Nesting and backlinks

Deliverables:
- A pin whose target is a map renders differently and navigates into it.
- **Appears on**: the maps that pin this one, read as backlinks, respecting visibility.
- A breadcrumb of one hop, because there is no chain to walk.

Tests: `tests/Feature/Maps/NestedMapsTest.php` (the world pins the duchy and the duchy's page says so; a player sees no "appears on" entry for a map they cannot open; a cycle renders and does not hang).

Success: a GM clicks from the world to the duchy to the city without using the sidebar.

### Phase 5: The live layer (cuttable)

Deliverables: `MapChanged`, ids only, `ShouldRescue`, on the campaign presence channel. `Maps\Viewer` listens for itself. A GM reveals a pin mid-session and the party's open maps fill in.

Tests: `tests/Feature/Maps/MapBroadcastTest.php`, in the shape of `EncounterBroadcastTest`: each action dispatches once, the payload is ids only, and the listener re-renders under its own viewer.

Success: the party is looking at the map when the GM reveals the door.

### Phase 6: Polish

- The seeder gains the duchy map with eight pins, half revealed, and one that opens the city map.
- Empty states: no maps, a map with no image, a map with no pins, a player's map with nothing revealed yet.
- The tablet pass at 1024px and 768px, dark and light, with the five rules. Pinch, drag, and the pin popover on a real touch screen.
- Record the rules: percentages never pixels; a pin is gated twice; nesting is a marker.
- Pint, Larastan, the full suite, and `npm run build`.

## Alternative Approaches Considered

- **A `maps` table of its own.** The brainstorm's sketch, and the obvious shape. Rejected: it reimplements visibility, search, wiki links, backlinks, tags, and the export, and every one of those is a place a leak can happen twice. The entity type gets all six for nothing.
- **Leaflet, or OpenSeadragon.** Real pan and zoom, written by people who do it for a living. Rejected: both are tile clients, this is one image, and the project's entire front end is Alpine and 75kB of bundle. Revisit if a GM ever wants a 20000px map, which is what tiling is actually for.
- **Pixel coordinates.** Simpler arithmetic and no rounding question. Rejected: the first replaced image silently moves every pin, and there is no migration that can fix it afterwards.
- **`maps.parent_map_id` for nesting.** The brainstorm's sketch. Rejected: a marker already carries the link, and two sources of truth for one fact is how they disagree. It also cannot represent two maps pinning the same city, which is normal.
- **Three-way visibility on a marker.** Consistent with entities. Rejected: a pin is a pin. The eye is one switch a GM already learned on the tracker, and `entity_viewers` for pins would be a fourth place to get visibility wrong.
- **Drawing tools: regions, polygons, fog.** The thing everyone asks for next. Rejected for this slice, and fog of war is a stated non-goal: demgem is not a VTT.
- **Reading coordinates from the image's natural size on the server.** Removes the browser from the arithmetic. Rejected: the server would need to know the rendered size, which is the one thing only the browser knows.

## Acceptance Criteria

### Functional

- [ ] A GM creates a map, uploads an image, and opens it.
- [ ] The viewer pans and zooms with a mouse, a trackpad, and two fingers, and cannot lose the image off screen.
- [ ] A GM places a pin by clicking, names it from the autocomplete or by hand, drags it, and deletes it.
- [ ] A pin whose target entity is deleted keeps its label and loses its link.
- [ ] A GM reveals and hides a pin, and can reveal or hide every pin at once.
- [ ] A player opens a shared map and sees only the revealed pins.
- [ ] A pin that targets a map opens that map.
- [ ] A map page lists the maps it appears on.
- [ ] A map joins search, wiki links, backlinks, tags, and the JSON export.
- [ ] The GM reveals a pin mid-session and the party's open maps follow. *(Phase 5; drop this line if the phase is cut.)*

### Non-functional

- [ ] **A hidden pin's label and coordinates are absent from a player's HTML and Livewire snapshot.**
- [ ] **A revealed pin whose target a player may not see is absent as well.**
- [ ] A DM-only map is a 404 for a player, not a 403.
- [ ] Panning and zooming send no request to the server.
- [ ] The viewer costs a constant number of queries, whatever the map holds.
- [ ] Coordinates are stored as percentages, and a value outside 0–100 is clamped before it is written.
- [ ] `Model::shouldBeStrict()` is on, so every new screen eager-loads.
- [ ] The map page works at 1024px and 768px, dark and light, with 16px body text and 44px controls, and the viewer works by touch.

### Quality gates

- [ ] Pest suite green on SQLite locally and on PostgreSQL in CI.
- [ ] Larastan level 6 clean. Pint clean.
- [ ] At most one new `x-ui.*` component, with the reason written down.
- [ ] Every new query on `map_markers` goes through the visibility scope, and the new ability names its surfaces in a docblock.
- [ ] `npm run build` clean, and the bundle grows by less than 5kB.
- [ ] No new PHP or JavaScript dependency.

## Dependencies & Risks

| Risk | Mitigation |
|---|---|
| A hidden pin leaks to a player | Two gates, both in the query, and a leak test file of its own, as slice 5 did for combatants. |
| Hand-written pan and zoom is worse than a library's | It is 80 lines against a 145kB dependency for a single image. The tablet pass is a phase, not an afterthought, and Leaflet stays available if touch defeats us. |
| A pixel coordinate creeps in | Percentages are the column type and the action's contract. The rule gets written down in Phase 6. |
| A huge image makes the page unusable | The upload cap is 10MB, the card uses the existing 320px thumb, and the viewer loads the original once. A tiling story is a later slice and the plan says which one. |
| Making a map an entity bloats the entity page | The map branch is the viewer and the pin list. Quests already branch further, and the models rule was written for exactly this call. |
| The slice runs long | Two release boundaries: after Phase 3 and after Phase 4. Phase 5 is cuttable. |
| Pinch and drag fight the page's own scroll | `touch-action: none` on the image, and the viewer is a bounded box rather than the page. Checked on a real touch screen in Phase 6. |

## Future Considerations

- **Regions and polygons.** A shape a player can click, rather than a point. It needs a path editor and it is a slice.
- **Tiled maps.** For an image big enough that one download is rude. That is when Leaflet earns its place, and not before.
- **A pin's own note.** Today a pin points at an entity that holds the prose. A pin with a sentence of its own is cheap and probably right.
- **Distance and scale.** "Two days' march" needs a scale bar and a measuring tool, and both need the ruleset conversation.
- **Pushing a map to the table.** The handout feature from the live table's P2 row, which is the same channel and the same reveal.
- **Player pins.** A party marking where they think the thing is. It needs a second layer and a conversation about who may write on the map.

## References

### Internal

- Brainstorm: `docs/brainstorms/2026-09-02-demgem-campaign-manager-brainstorm.md`, the "Maps" table and the data model sketch
- Slice 4 plan: `docs/plans/2026-09-03-feat-player-view-export-docker-plan.md` — why a parallel player tree was rejected, and the five tablet rules
- Slice 5 plan: `docs/plans/2026-09-03-feat-live-table-reverb-plan.md` — `player_visible`, the eye toggle, and the channel Phase 5 uses
- Project rules: `.ai/rules/models.md` (a list gets a child table), `.ai/rules/table.md` (filter in the query, never in the Blade), `.ai/rules/migrations.md`, `.ai/rules/views.md`, `.ai/rules/tests.md`
- Patterns to copy: `app/Models/Combatant.php` (a copied label and a `player_visible` boolean), `app/Livewire/Quests/Objectives.php` (a nested editor for a child table), `resources/js/app.js` (an Alpine component registered on `alpine:init`), `app/Http/Controllers/AutocompleteController.php` (picking a target)

### External

- Spatie Media Library conversions: https://spatie.be/docs/laravel-medialibrary/v11/converting-images/defining-conversions
- Pointer events, including pinch: https://developer.mozilla.org/en-US/docs/Web/API/Pointer_events
- `touch-action`: https://developer.mozilla.org/en-US/docs/Web/CSS/touch-action
- Livewire 4 nested components: https://livewire.laravel.com/docs/4.x/components#nesting-components
