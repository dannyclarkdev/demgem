---
title: "feat: Handouts, and the clocks that tick behind them"
type: feat
date: 2026-09-03
brainstorm: docs/brainstorms/2026-09-02-demgem-campaign-manager-brainstorm.md
follows: docs/plans/2026-09-03-feat-maps-markers-plan.md
---

# feat: Handouts, and the clocks that tick behind them

## Overview

Two rows from the brainstorm's Live table tools table, both P2, and both the same verb: the GM decides when the party learns something.

| Feature | What it adds |
|---|---|
| Handouts | `EntityType::Handout`. Prose, and now files: the scan of the letter, the ship's manifest as a PDF, the front and the back of the map fragment. |
| Reveal | The visibility switch entities already carry. Hidden is hidden; **Show the party** is one press, and the handout lands on the open table screens. |
| A gallery | A `files` collection, ten files, images and PDF. The first attachment a campaign has ever had that is not one portrait. |
| Progress clocks | A named dial cut into 4, 6, 8, or 12 segments. The GM fills a segment when the world moves. |
| Countdowns | The same dial, emptied instead of filled. One shape, two habits, no second column. |
| The party's half | A clock the GM reveals appears on `/table` and ticks there while they watch. |

When this slice is done the GM drops the letter on the table mid-scene, and the party watches the alarm clock fill one wedge at a time without knowing what the last wedge does.

**On scope.** Phases 0 and 1 are a release: clocks work, at the table and on the GM's screen. Phases 2 and 3 are a second: handouts, with files and a reveal. Phase 4 ties the two features to the world, and it is cuttable. Cutting it changes nothing else in the slice.

## Problem Statement

**The GM has no way to hand the party a thing.** Every piece of prose in demgem is a page a player navigates to. A handout is the opposite: it arrives. Today a GM who wants the party to read the duke's letter pastes it into live notes, which players cannot see, or makes a Note and flips its visibility, which puts it in a wiki index nobody is looking at during a fight. The scan of the letter has nowhere to go at all, because an entity carries exactly one image and that image is the portrait beside the prose.

**Secrets solved the GM's half of this and stopped.** `Secret` is a clue with `revealed_at` and `revealed_in_session_id`, and it is GM-only revealed or not, on purpose: revealing a secret means the GM said it out loud. A handout is the case where saying it out loud is not enough, and the party needs to hold the thing.

**Nothing in the app represents a process.** A quest is done or not. An objective is ticked or not. A campaign is made of things that are neither: the cult is three steps from the ritual, the city guard is half convinced, the ship sinks in four more failures. Every GM tracks these on paper because demgem models only the endpoints. Progress clocks are the standard answer — Blades in the Dark made them, and Daggerheart, Fate, and half the tables running 5e now use them anyway — and they are a name, a count, and a total.

**The table screen is thin between fights.** Slice 5 gave players a live page and slice 6 gave them a map. Outside a fight the page is dice, the party, and last week's recap. A revealed handout and a ticking clock are the two things a party actually watches when nobody is rolling.

## Proposed Solution

**A handout is an entity type, and the reveal is the visibility it already has.** `EntityType::Handout` joins the seven. It inherits `Entity::visibleTo()`, `entity_viewers`, DM notes, tags, `[[The duke's letter]]`, backlinks, search, the sidebar count, the session prep buckets, the export, and every leak test written against all of them. Slice 6 made this call for maps and the reasoning has not moved.

**There is no `revealed_at` and no `player_visible` on a handout.** Revealed means `visibility` is not `Dm`. A second column that also means "revealed" is a second source of truth for one fact, and the two disagree the first time somebody edits the entity form instead of pressing the button. **Show the party** writes the same column the form writes.

This is the opposite call from the one the tracker and the map made, and the difference is the reason: a combatant and a pin have no visibility of their own, so `player_visible` gave them one. An entity has three-way visibility already, and `entity_viewers` on top of it. Adding a fourth mechanism to the table that carries the other three is how a leak gets written.

**Files are a media collection, not a table.** Spatie Media Library is already installed and already holds every entity image. A `files` collection with `singleFile()` left off is the whole feature. Ten files, 10MB each, images and PDF.

**A clock is a row, because it is nothing else.** A name, a total, a count, and an eye. It is not an entity: it has no body, no slug, no wiki link, no visibility beyond one switch, and nobody navigates to one. Making it an entity would buy a search index for the string "The ritual" and pay for it with a nullable column on `entities` for every row in the campaign.

**A countdown is a clock the GM empties.** The controls are plus and minus. "Four sessions until the ritual" starts at 4 filled and comes down; "the guard's suspicion" starts at 0 and goes up. One table, one component, no `direction` column, and the name carries the meaning the way it does on paper.

## Technical Approach

### No new dependency

Nothing to install. The gallery is Media Library, which entities already use. The dial is an inline SVG. The lightbox is `x-ui.modal` with an Alpine `x-show`, both already in the kit. The broadcasts ride the presence channel slice 5 opened.

### What slices 1 to 6 give us for free

| Piece | Reuse |
|---|---|
| `EntityType` | One case, six `match` arms, and a handout has a label, a plural, a slug, an icon, a description, and a wiki-link priority. |
| `Entity::visibleTo()` | The whole reveal. Nothing new to write, and every existing leak test still guards it. |
| `Entity` media | `addMediaCollection('files')` beside the `image` collection that already works. |
| `MarkdownRenderer` + `WikiLinkRenderer` | A handout's body and DM notes render exactly like a note's. |
| `EntityPolicy` | `view`, `update`, `delete`, `viewDmFields`, for the handout, with nothing to write. A clock gets a `ClockPolicy` of its own, copied from `RandomTablePolicy` down to the `roleFor()` fallback `.ai/rules/livewire.md` asks for. |
| Session prep | `PrepRole::suggestedTypes()` sorts the picker and **never limits** what a GM may attach, so a handout drops into tonight's Treasure bucket with no change at all. |
| `ReorderPositions` | Clocks are a short ordered list. The one reorder path already handles four of them. |
| `MapChanged` | The shape both new events copy: two ULIDs, `ShouldRescue`, `broadcastAs`, and a listener that reads nothing from the payload. |
| `Table\Fight` | One component, two audiences, the role decided in the query. `Clocks\Panel` is the same trick. |
| `x-ui.progress` | Not reused. A progress bar is a fraction of a job; a clock is a countable number of wedges, and the GM presses one. |
| `x-ui.modal` | The handout lightbox. No new component for it. |
| `x-ui.*` kit | **Budget: one new component**, `x-ui.clock`, because the dial is drawn on three screens in two states. |

### The data model

One new table, one new media collection, and no new columns on `entities`.

```
clocks    id              ulid, primary
          campaign_id     ulid, cascade
          entity_id       ulid, nullOnDelete   -- what it is about, nullable
          name            string 120
          segments        smallint, default 6  -- 2 to 12
          filled          smallint, default 0  -- 0 to segments
          player_visible  boolean, default false
          position        integer, default 0
          timestamps

          index (campaign_id, player_visible)
          index (entity_id)
```

- **`segments` and `filled` are counts, not a percentage.** A clock's whole point is that the wedges are countable: "two more" is the sentence a GM says. A percentage would round three of eight to 38 and lose the noun.
- **No `direction` column.** See above. The plus and the minus both work whichever way the GM reads the dial.
- **No `completed_at`.** Complete is `filled >= segments`, and a derived fact does not get a column. `Secret::isRevealed()` earns its timestamp because a secret's reveal is a moment in a session's record; a clock fills and empties again all night.
- **`entity_id` is nullable and means "about".** "The Duke's suspicion" points at the duke. Most clocks point at nothing, and a clock with no link is a clock.
- **`position`, through `ReorderPositions`.** Clocks are few and ordered by the GM's sense of what matters tonight.

The handout adds no column. It adds a collection:

```php
// app/Models/Entity.php
$this->addMediaCollection('files')
    ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'])
    ->useDisk(config('media-library.disk_name'));
```

### The trap in the media conversion

`registerMediaConversions()` today declares one `thumb` conversion and restricts it to nothing, so it applies to **every** collection. Add a collection that accepts PDF and that conversion starts trying to rasterise PDFs, which needs Imagick with Ghostscript behind it. On a machine that has it the conversion is slow and nonQueued; on a machine that does not, Media Library skips it and the page asks for a URL that was never written.

So the conversions get scoped, in this slice, before the collection exists:

```php
$this->addMediaConversion('thumb')
    ->nonQueued()
    ->fit(Fit::Crop, 320, 320)
    ->performOnCollections('image');

// The gallery tile. Images only, and a PDF renders as an icon and a filename
// rather than as a broken img whose src depends on whether Ghostscript is installed.
$this->addMediaConversion('tile')
    ->nonQueued()
    ->fit(Fit::Contain, 480, 480)
    ->performOnCollections('files');
```

`Media::hasGeneratedConversion('tile')` is what the Blade asks, so the answer is a fact about the file rather than a guess from its mime type.

### Revealing a handout

One action, and it writes the same column the form writes:

```php
// app/Actions/Handouts/RevealHandout.php
public function handle(Entity $handout, User $user, Visibility $visibility): void
{
    // Through UpdateEntity, so the observers, the mention sync, and the audit
    // trail all run exactly as they do when a GM edits the form. This action adds
    // the broadcast and nothing else; it is a shortcut for the GM, not a second
    // way to change who may see an entity.
    $this->updateEntity->handle($handout, $user, ['visibility' => $visibility]);

    HandoutRevealed::dispatch($handout->campaign_id, $handout->id);
}
```

**Show the party** sets `Visibility::Players`. **Take it back** sets `Visibility::Dm`, dispatches the same event, and the table screens re-render without it. A GM who wants only two players to see it uses the form's Selected option, which has worked since slice 1.

### What a player sees

A revealed handout reaches `/table` through the filter every other list uses:

```php
Entity::query()
    ->ofType(EntityType::Handout)
    ->visibleTo($user, $role)
    ->with('media')
    ->latest('updated_at')
    ->limit(self::TABLE_HANDOUTS)
    ->get();
```

A clock is gated by its own switch, in the query:

```php
// app/Models/Clock.php
public function scopeVisibleTo(Builder $query, CampaignRole $role): Builder
{
    return $role->isDm() ? $query : $query->where($query->qualifyColumn('player_visible'), true);
}
```

**The link is gated separately from the row, and this is the one place a clock differs from a pin.** A pin *is* a pointer, so a pin whose target a player may not see is not shown at all. A clock merely mentions one: the GM who revealed "The Duke's suspicion" meant the party to read those words, and hiding the whole dial because the duke's page is GM-only would delete the thing the GM just did on purpose.

So the row stays and the link does not, and the decision is still in the query rather than in a Blade:

```php
// The linked entity comes back null when this viewer may not see it, so its name
// never reaches the page and there is nothing for an @if to get wrong.
->with(['entity' => fn ($q) => $q->visibleTo($user, $role)])
```

### The events

Two, both in the shape `MapChanged` set:

```php
class ClockChanged implements ShouldBroadcast, ShouldRescue    // campaignId, clockId
class HandoutRevealed implements ShouldBroadcast, ShouldRescue // campaignId, entityId
```

Ids only. Every listener is a Livewire component that re-renders on the server under its own viewer's role, so `Clock::visibleTo()` and `Entity::visibleTo()` decide per screen what comes back. **A clock hidden and a clock revealed broadcast the same two ids**, so the broadcast itself says nothing about what changed, which is the security design rather than a courtesy.

`ClockChanged` names a fact that covers a tick, a rename, a reveal, and a delete, because the listener re-reads the list either way and the payload it would need to tell them apart is the payload that could leak.

### The panel

`App\Livewire\Clocks\Panel`, nested, handed the campaign and an optional entity id.

- On the Run screen: every clock, the controls, the eye, the drag handle.
- On `/table`: the revealed ones, no controls.
- On an entity page: the clocks about that entity, controls if the viewer is a GM.

One component, and the role decides the query, not the template. It carries its own `wire:poll.visible.60s`, because a nested component does not re-render when its parent polls and the parent's poll is what covers a dropped socket for everything else on the page.

### Ticking

```php
// app/Actions/Clocks/TickClock.php
// The delta arrives from a browser, so it is clamped rather than trusted, exactly
// as a pin's coordinate is. A forged value can at most set a clock the forger can
// already edit to a number inside its own range.
$clock->update(['filled' => max(0, min($clock->segments, $clock->filled + $delta))]);
```

The GM clicks a wedge to set the fill to that wedge, clicks the last filled wedge to unfill it, and has plus and minus buttons for a touch screen and a keyboard. All three go through `TickClock`, which takes an absolute value or a delta and clamps either.

### The dial

`x-ui.clock` is an inline SVG: one circle per segment drawn as an arc, filled or not, with a 44px minimum hit area per wedge on touch and a `<button>` behind each. It takes `:clock`, `:interactive`, and a size, and it is the same drawing on all three screens so a GM learns one shape.

No library. A clock is `2 * pi * r / segments` and a `stroke-dasharray`.

## Decisions resolved

| Question | Decision |
|---|---|
| A `handouts` table or an entity type | An entity type. It inherits visibility, search, wiki links, backlinks, tags, DM notes, prep buckets, and the export. |
| How a handout is revealed | `Entity::visibility`, the column that already exists. No `revealed_at`, no `player_visible`, no second source of truth. |
| Files | A `files` media collection: ten files, 10MB each, images and PDF. No new table. |
| The `thumb` conversion | Scoped to the `image` collection, and `files` gets its own `tile`. A PDF is an icon, not a broken image. |
| A clock as an entity | No. It has no body, no slug, no wiki link, and nobody navigates to one. |
| Clock as a fraction or as counts | Counts. "Two more" is the sentence a GM says. |
| Countdowns | The same table. Plus and minus, and the name says which way the GM reads it. No `direction` column. |
| A completed clock | Derived from `filled >= segments`. No column, because a clock empties again. |
| Clock visibility | One switch, `player_visible`, in the query. |
| A clock whose linked entity is hidden | The clock shows, the link does not. The relation is loaded through `Entity::visibleTo()`, so the name never reaches the page. This differs from a map pin on purpose: a pin is the link. |
| Where the writes live | Actions in `app/Actions/Clocks/` and `app/Actions/Handouts/`. `RevealHandout` goes through `UpdateEntity`. |
| Events | Two, ids only, `ShouldRescue`, on the campaign presence channel: `ClockChanged`, `HandoutRevealed`. |
| New tables | One: `clocks`. |
| New columns on `entities` | None. |
| New kit components | One, `x-ui.clock`. The lightbox is `x-ui.modal`. |
| New icons | Two: `paperclip`, `download`. |

## Implementation Phases

Each phase ends with a green suite. Phases 0 and 1 are a release; Phases 2 and 3 are a second.

### Phase 0: Clocks a GM can turn

Deliverables:
- Migration `create_clocks_table`.
- `App\Models\Clock`, `Campaign::clocks()`, `Entity::clocks()`, and the factory.
- `app/Actions/Clocks/`: `CreateClock`, `UpdateClock`, `TickClock`, `SetClockVisibility`, `ReorderClocks`, `DeleteClock`.
- `x-ui.clock`, the SVG dial, interactive and not.
- `App\Policies\ClockPolicy`, copied from `RandomTablePolicy`, with the `roleFor()` fallback.
- `App\Livewire\Clocks\Panel`, and `App\Livewire\Clocks\Index` at `/clocks`, GM only. The route sits above `/{type}`, and `clocks` is not an entity slug, so nothing can claim it.
- The Run screen renders the panel.
- `clocks` joins `ExportCampaign::SECTION_TABLES` as its own section, in this commit.

Tests: `tests/Feature/Clocks/ClocksTest.php` — a GM creates a clock, ticks it up and down, renames it, reorders two, and deletes one; a tick past `segments` clamps and writes the clamped value; a tick below zero clamps; a player may not create, tick, or delete; `ExportCoverageTest` passes with the new table.

Success: a GM makes "The ritual, 6" during prep and fills a wedge mid-scene without leaving the Run screen.

### Phase 1: Clocks at the table

Deliverables:
- `clocks.player_visible` and the eye toggle per clock, GM only, with the sentence the tracker and the map already use.
- `Clock::scopeVisibleTo()`, in the query.
- The panel on `/table`, read-only, with its own sixty-second poll.
- `ClockChanged`, dispatched by every clock action, and the panel's listener.

Tests: `tests/Feature/Clocks/ClockVisibilityTest.php` — **a hidden clock's name and counts are absent from a player's HTML and Livewire snapshot**; a revealed one is present; the GM sees both and which is which; the player surface audit gains the clock panel. `tests/Feature/Clocks/ClockBroadcastTest.php`, in the shape of `EncounterBroadcastTest`: each action dispatches once, the payload is two ids, and revealing and hiding dispatch identically.

Success: the GM fills a wedge and the party's open table screens fill the same wedge.

### Phase 2: Handouts

Deliverables:
- `EntityType::Handout`, its six `match` arms, and a wiki-link priority between Note and Map.
- The `paperclip` and `download` icons, and `handouts` in the route pattern by way of `EntityType::slugs()`.
- The `files` media collection, and the `thumb` conversion scoped before it lands.
- The entity form accepts up to ten files for a handout: add, remove, and reorder is out of scope — a handout's files are a set, not a list.
- The handout page renders the gallery: image tiles that open `x-ui.modal` full size, and PDFs as a row with a filename, a size, and a download link.
- `files` joins the export's entity payload beside `image`.

Tests: `tests/Feature/Handouts/HandoutEntityTest.php` — a GM creates a handout with three files; a PDF and an image both survive a round trip; the eleventh file is refused; a file over the cap is refused; a player sees a shared handout and not a DM-only one; `[[The duke's letter]]` resolves; a handout appears in search and in the export with its files.

Success: a GM uploads the scan of the letter and the transcription in one form.

### Phase 3: The reveal

Deliverables:
- `app/Actions/Handouts/RevealHandout.php`, through `UpdateEntity`.
- **Show the party** and **Take it back** on the handout page and in a Run screen list of tonight's handouts.
- The Handouts card on `/table`: the last ten revealed, newest first, each opening the lightbox.
- `HandoutRevealed`, and the listener on `Table\Show`.

Tests: `tests/Feature/Handouts/HandoutRevealTest.php` — revealing writes `visibility` and nothing else; **an unrevealed handout's name, body, and file URLs are absent from a player's table screen HTML and snapshot**; taking it back removes it; a player may not reveal; the event carries two ids and fires once per press.

Success: the GM presses one button mid-scene and the letter is on every player's screen.

### Phase 4: Ties to the world (cuttable)

Deliverables:
- A clock's optional entity link, set from the clock form with the picker the map pin uses.
- The entity page renders the clocks about it, GM controls included, players' gate respected.
- A handout links to the session it came out in through the prep pivot that already exists, so the session page lists tonight's handouts with no new column.

Tests: `tests/Feature/Clocks/ClockEntityLinkTest.php` — the duke's page shows his clock; **a player who may not see the duke sees the clock and not his name**; deleting the duke leaves the clock with a null link.

Success: a GM opens the cult's page and sees how close the ritual is without going anywhere else.

### Phase 5: Polish

- The seeder gains three clocks, one revealed and half full, and two handouts, one revealed with an image and one hidden with a PDF.
- Empty states: no clocks, no handouts, a player's table with neither revealed, a handout with no files.
- The tablet pass at 1024px and 768px, dark and light, with the five rules. A wedge is a 44px target and the lightbox closes on Escape and on a tap outside.
- Record the rules: the reveal is the visibility column; a clock shows while its link hides; scope a conversion to its collection.
- Pint, Larastan, the full suite, and `npm run build`.

## Alternative Approaches Considered

- **A `handouts` table of its own.** The obvious shape, and the one Kanka uses. Rejected for the reason slice 6 rejected a `maps` table: it reimplements visibility, search, wiki links, backlinks, tags, and the export, and every one of those is a second place a leak can happen.
- **`player_visible` on a handout, to match the tracker and the map.** Consistent on the surface. Rejected: an entity already has three-way visibility and a viewers table, so this would be a fourth mechanism on a row that has three, and the first person to use the form instead of the button would desynchronise them.
- **A handout as a `Secret` with a file.** Tempting, because a secret already records reveal and session. Rejected: a secret is GM-only prose whose reveal means the GM said it out loud, and it is deliberately never indexed as a mention source because it moves between sessions. A handout is player-facing, permanent, and linkable. They share a verb and nothing else.
- **A clock as an entity type.** It would inherit the reveal for free, which is the argument that won for maps and handouts. Rejected: a clock has no body, no slug, no prose, and nothing to search, so it would inherit an index of the string "The ritual" and pay with a nullable column on every entity row. The type is for things a GM writes about; a clock is a number a GM turns.
- **A `direction` column, so a countdown counts down.** Rejected: the controls are plus and minus either way, and the name on the dial is what a table actually reads. A column would need a second set of labels and a second empty state to say nothing new.
- **Reusing `x-ui.progress` for the dial.** Free, and already drawn. Rejected: a bar is a fraction of one job and a clock is a countable number of pieces the GM presses individually. Rendering four of eight as a 50% bar deletes the interaction and the noun.
- **Hiding a clock whose linked entity is hidden**, as a map pin does. Rejected, with the reasoning written into the rules: a pin is the link and a clock merely mentions one, so the pin has nothing left when the target goes and the clock has everything left.
- **A file per row in a `handout_files` table.** More control, and an explicit order. Rejected: Media Library is installed, holds every image already, and gives ordering, conversions, disks, and the export shape for nothing. Revisit if a handout ever needs per-file visibility, which is a real feature and a different slice.
- **Pushing a handout to a second display.** The brainstorm's "player screen" row, and P3. Out of scope, and the reveal built here is what it will use.

## Acceptance Criteria

### Functional

- [x] A GM creates a clock, names it, sets 4, 6, 8, or 12 segments, and fills a wedge by clicking it.
- [x] Plus and minus tick a clock, and neither can pass zero or the total.
- [x] A GM reorders clocks, renames one, and deletes one. *(The arrows step a row, not a stored number: a delete leaves a hole until the next drag.)*
- [x] A GM reveals a clock and the party sees it on `/table`; hiding it takes it away.
- [ ] A clock ticks on the party's screen while they watch, with no refresh. *(The listener and the payload are tested; the two-browser check is not done.)*
- [x] A GM creates a handout with prose and up to ten files, images and PDF.
- [x] The handout page shows images as tiles that open full size, and PDFs as named, downloadable rows. *(The branch is tested; the lightbox itself is a browser check.)*
- [x] **Show the party** reveals a handout, and it appears on every open table screen. *(Tested to the listener; the second browser is not.)*
- [x] **Take it back** hides it again.
- [x] A handout joins search, wiki links, backlinks, tags, prep buckets, and the JSON export, with its files.
- [x] A clock links to an entity, and that entity's page shows it.

### Non-functional

- [x] **A hidden clock's name and counts are absent from a player's HTML and Livewire snapshot.**
- [x] **An unrevealed handout's name, body, and file URLs are absent from a player's table screen HTML and snapshot.**
- [x] **A revealed clock whose linked entity a player may not see renders without that entity's name.**
- [x] A DM-only handout is a 404 for a player, not a 403.
- [x] Revealing and hiding broadcast the same payload, so the event says nothing about what changed.
- [x] `filled` is clamped to 0 and `segments` before it is written, whatever the browser sent.
- [x] The clock panel costs a constant number of queries, whatever the campaign holds. *(Two: one for the rows, one for the links.)*
- [x] `Model::shouldBeStrict()` is on, so every new screen eager-loads.
- [x] A PDF renders as an icon and a filename on a machine with no Ghostscript, not as a broken image.
- [ ] Both new screens work at 1024px and 768px, dark and light, with 44px targets and no sideways scroll. *(Not checked: the browser session is not logged in.)*

### Quality gates

- [x] Pest suite green on SQLite locally. PostgreSQL in CI is the pull request's job.
- [x] Larastan level 6 clean. Pint clean.
- [x] At most one new `x-ui.*` component, with the reason written down: `x-ui.clock`. *(Two new icons as well: `paperclip` and `download`.)*
- [x] Every new query on `clocks` goes through `scopeVisibleTo`, and every write goes through `ClockPolicy`. *(A test reads the query log and asserts every clocks query names `player_visible`.)*
- [x] `RevealHandout` writes visibility through `UpdateEntity` and through no other path.
- [x] `npm run build` clean. The bundle did not grow: no new JavaScript.
- [x] No new PHP or JavaScript dependency.

## Dependencies & Risks

| Risk | Mitigation |
|---|---|
| A hidden clock or an unrevealed handout leaks to a player | Both gates in the query, both with a leak test file of their own, as slices 5 and 6 did for combatants and pins. |
| Two ways to reveal a handout drift apart | There is one column. `RevealHandout` calls `UpdateEntity`, which is what the form calls, and a test asserts the action writes nothing else. |
| The `thumb` conversion meets a PDF | The conversions are scoped to their collections in the same commit that adds `files`, and the Blade asks `hasGeneratedConversion()` rather than guessing from the mime type. |
| Ten 10MB files per handout fills a disk | The same cap the map image already uses, and per-campaign storage quotas are their own P2 row. The plan does not pretend to solve it here. |
| The clock dial is fiddly on a touch screen | A 44px hit area per wedge from the first commit, plus and minus buttons beside it, and a real touch screen in Phase 5. |
| A clock's link leaks the entity it points at | The relation is loaded through `Entity::visibleTo()`, so a hidden entity is null rather than filtered in a template. It has a named test. |
| The slice runs long | Two release boundaries: after Phase 1 and after Phase 3. Phase 4 is cuttable. |
| The table screen grows a fourth card and gets crowded | The handouts card replaces the empty space between fights, and the clocks panel sits under the fight. The 768px pass is a phase, not an afterthought. |

## Future Considerations

- **A handout pushed to a second display.** The P3 player-screen row. It is this reveal and a different layout.
- **Per-file visibility.** The front of the letter now, the back when they turn it over. That is when `handout_files` earns its table.
- **A clock that fills itself.** Tied to a quest's objectives, or ticked by a failed roll. It needs the ruleset conversation.
- **A clock's history.** "Filled in session 6" is the `revealed_in_session_id` pattern, and it is worth having the day a campaign wants a timeline.
- **Clocks on the map.** A siege clock pinned to the city. Two features that already exist and one nullable column, once somebody wants it.
- **Image galleries on every entity.** The `files` collection is registered on `Entity`, so this is a form and a page, not a migration. This slice renders it for handouts only and says so on purpose.

## References

### Internal

- Brainstorm: `docs/brainstorms/2026-09-02-demgem-campaign-manager-brainstorm.md`, the "Live table tools" table, rows "Progress clocks and countdowns" and "Handouts with reveal"
- Slice 5 plan: `docs/plans/2026-09-03-feat-live-table-reverb-plan.md` — the presence channel, `player_visible`, and the eye toggle
- Slice 6 plan: `docs/plans/2026-09-03-feat-maps-markers-plan.md` — why an entity type beats a table of its own, and the two-gate rule this slice deliberately differs from
- Project rules: `.ai/rules/models.md` (a list gets a child table; the pin's two gates), `.ai/rules/events.md` (broadcast the fact, always `ShouldRescue`), `.ai/rules/table.md` (filter in the query, never in the Blade), `.ai/rules/livewire.md` (a nested component re-checks membership), `.ai/rules/migrations.md`, `.ai/rules/views.md`, `.ai/rules/tests.md`
- Patterns to copy: `app/Models/Secret.php` (a reveal that records a session, and why a handout does not), `app/Models/MapMarker.php` (a `player_visible` scope), `app/Livewire/Table/Fight.php` (one component, two audiences), `app/Actions/Maps/Coordinate.php` (clamping what a browser sent), `app/Actions/Support/ReorderPositions.php`, `app/Actions/Campaigns/ExportCampaign.php`

### External

- Media Library, multiple files in a collection: https://spatie.be/docs/laravel-medialibrary/v11/working-with-media-collections/simple-media-collections
- Media Library, conversions per collection: https://spatie.be/docs/laravel-medialibrary/v11/converting-images/defining-conversions
- SVG `stroke-dasharray` for arcs: https://developer.mozilla.org/en-US/docs/Web/SVG/Attribute/stroke-dasharray
- Livewire 4 nested components: https://livewire.laravel.com/docs/4.x/components#nesting-components
