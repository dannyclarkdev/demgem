---
title: "feat: The player screen, for the television at the end of the table"
type: feat
date: 2026-09-13
status: implemented
brainstorm: docs/brainstorms/2026-09-02-demgem-campaign-manager-brainstorm.md
follows: docs/plans/2026-09-11-feat-storage-quotas-plan.md
---

# feat: The player screen, for the television at the end of the table

## Overview

The brainstorm's GM table has one P3 row about a second display: "push an image or map to a second display". Slice 5 built `/table`, the page a player keeps open on their own device. This slice builds the page a GM puts on the television: what the whole party may see, nothing else, chosen from the Run screen and changed on every screen at once.

| Feature | What it adds |
|---|---|
| `/screen` | One route, one page, no sidebar. It shows the fight as the party sees it, a handout, a map with the pins the party found, or the revealed clocks. Every member may open it. |
| The GM's choice | A card on the Run screen: put a handout or a map on the screen, show the fight, show the clocks, or clear it. One press. |
| The live part | A new event, `ScreenChanged`, on the campaign's presence channel, with the four events the table already has. The screen re-renders on each and polls as a backstop. |
| The round trip | The two columns travel in the campaign section of the export, and the importer remaps the one that is an id. |

When this slice is done a GM running session 3 presses "On the screen" beside the duke's letter, and the television at the end of the table shows the letter, full size, with its name under it. The GM presses "The fight" and the television shows the turn order with health as a word. A player who opens `/screen` on a tablet sees exactly the same page.

**On scope.** Phase 0 is the state: the two columns, the action, the event. Phase 1 is the screen. Phase 2 is the card on the Run screen. Phase 3 is the export, the seeder, the rules, and the pass.

## Problem Statement

**`/table` is a page for one person's hands, not a wall.** It carries the dice tray, the party list, and the last recap, in the type size of a form. A television across a room needs one thing at a time, large, with no controls on it. A GM who plugs a laptop into a screen today shows the party the Run screen, which is the one page they must never see.

**A handout has a gallery, and a gallery is not a display.** "Show the party" puts the letter on every player's own device inside a card of ten. At a physical table the players are looking at one wall, and the GM wants the letter on it, alone.

**The turn order the party may read already exists, and nobody can see it from six feet.** `Table\Fight` renders the party's view under a query gate. The television needs that same query and a different sheet of Blade.

## Proposed Solution

**The screen is two columns on the campaign, and both are scalars.** `campaigns.screen_focus` says what kind of thing is up: the fight, the clocks, a handout, or a map. `campaigns.screen_entity_id` names the handout or map. A campaign has one screen, one focus, one page on it, so by the models rule these are columns and not a table. The importer remaps the id and the export carries both.

**What the screen shows is decided in the query, under the party's gate, whoever opened it.** The GM opens `/screen` on the laptop that feeds the television, so the GM is the viewer, and the page must still show what the party may see. Every read on the screen goes through a gate that takes no user and no role: combatants through `Combatant::visibleToPlayers()`, clocks through `Clock::visibleTo(Player)`, the handout and the map through a new `Entity::visibleToParty()`, and the pins through a new `MapMarker::visibleToParty()`. That last gate is the strictest one in the app: `Players` only, no `Selected`, no "my own character". A television is not one player. When a GM takes a handout back, the column still names it and the screen shows nothing, because the gate said so.

**The choice reuses "Show the party".** Putting a hidden handout on the screen goes through `RevealHandout::show()` first, so it is revealed on every player's device the same moment it lands on the wall. A map is offered only when the party may already see it and it has an image.

**The event carries nothing.** `ScreenChanged` broadcasts the campaign id on the campaign's presence channel and no payload at all. The screen re-renders and reads its own state. It also listens to `encounter.changed`, `clock.changed`, `handout.revealed`, and `map.changed`, because each of those changes what the wall should say.

**Focus falls back on its own.** A focus of the fight with no fight running shows the idle state: the campaign's name and the party. A focus of a handout whose row is gone, or hidden, shows the same. A null focus shows the fight while one is active, then the idle state. The revealed clocks sit in a strip at the bottom whenever the focus is not the clocks and at least one is revealed.

## Technical Approach

### No new dependency

Reverb, Echo, Livewire, and Alpine are all installed. The map viewer's pan and zoom is `resources/js/map.js`, and the screen reuses it with `canEdit: false`.

### What slices 1 to 23 give us for free

| Piece | Reuse |
|---|---|
| `Table\Show` and `Table\Fight` | The listener shape, the sixty-second poll, and the query gates. |
| `RevealHandout` | The reveal, through `UpdateEntity` so the observers run. |
| `Combatant::healthWord()`, `Encounter::lairMarkerIndex()` | The party's reading of a row and where the lair marker sits. |
| `mapViewer()` and `x-ui.map-pin` | The image, the transform, and the pins. |
| `x-ui.clock` | The dial. |
| `EncounterChanged`, `MapChanged`, `ClockChanged`, `HandoutRevealed` | The four events the screen listens to beside its own. |
| `DeleteEntity` | Already nulls an arc's columns on soft delete. It nulls the screen's the same way. |

### The data

```
campaigns
  screen_focus       string(16) nullable   fight | clocks | handout | map
  screen_entity_id   ulid nullable -> entities, nullOnDelete, indexed
```

`ScreenFocus` is a backed enum with four cases. `Campaign::screen()` returns a `ScreenState`: the focus as stored, the entity read through the party gate, and the focus resolved to null when the focus needs an entity and the gate returned none.

### Scope and decisions

| Question | Decision |
|---|---|
| Who may open `/screen` | Every member, as `/table`. A non-member gets 404. |
| Whose gate | The party's, always. The viewer's role never widens what the screen shows. A GM who opens it reads exactly what a player reads. |
| `Selected` | Never on the screen. A handout shared with two players is not on the wall. |
| A hidden handout put on the screen | Revealed first, through `RevealHandout`. Taking it back later hides it from the screen through the gate, and the columns are left alone. |
| A map put on the screen | Only a map the party may see, with an image. The pins are the ones the party found. No reveal on the way, because a map's visibility is a form decision. |
| The fight | The active encounter, as `Table\Show` finds it. No "You" badge, no numbers, no names for hidden turns. Death saves show, by the table rule. |
| The clocks | The revealed ones, in a strip when something else is up, large when they are the focus. |
| Deleting the page on the screen | `DeleteEntity` nulls both columns. Soft delete never fires `nullOnDelete`. |
| The layout | A new `layouts::screen`: no sidebar, no header, no search. The theme script and the Echo settings stay, because the page is live. |
| The poll | Sixty seconds, `wire:poll.visible`, the table's backstop. |
| The payload | `ScreenChanged` carries no keys. The listener reads the row. |
| Who may set the screen | `useGmTools`, the existing gate for the tracker and the tables. |
| The API | None. Like a clock. |
| The vault | Nothing. The screen is state, not lore. |

### Actions

| Action | Does |
|---|---|
| `Table\SetScreen::show(Campaign, Entity)` | A handout or a map. Reveals a hidden handout. Writes both columns. Dispatches the event. |
| `Table\SetScreen::focus(Campaign, ?ScreenFocus)` | The fight, the clocks, or null. Clears the entity. Dispatches the event. |

### Screens

- **`/screen`** is `Table\Screen`, on the new layout. The campaign's name in the corner, the focus in the middle, the clock strip at the bottom. Type sizes are for a wall.
- **The Run screen** gains an "On the screen" card, `Table\ScreenControls`: what is up now, four buttons, every handout with "On the screen", every party-visible map with an image with "On the screen", and a link that opens the screen in a new tab.
- **`/table`** gains a link to the screen in its header.

### The round trip

- The campaign section gains `screen_focus` and `screen_entity_id`. The reader accepts absent keys from an older file, refuses a focus it does not know, and refuses an entity the file does not carry. The importer writes both after the entities, the way `active_combatant_id` is written after the combatants.
- `RoundTripTest` drops `screen_entity_id` with the other remapped ids and compares `screen_focus` in full.
- No version bump.

## Verification

    php artisan test --compact tests/Feature/Table
    php artisan test --compact --filter=Screen
    php artisan test --compact --filter=RoundTrip
    php artisan test --compact --filter=DemoSeeder
    vendor/bin/pint --dirty --format agent
    vendor/bin/phpstan analyse

Then the full suite with `memory_limit=1G`, and a browser pass: open the Run screen for session 3 as the GM and `/screen` in a second tab; put the duke's letter up and read it on the screen; show the fight and read the party's turn order with no numbers; open `/screen` as the player and read the same page; take the letter back and watch the screen fall to the idle state.

## Open Questions

1. **Should the screen carry the dice log?** A wall that shows every roll is a nice thing at some tables. Recommendation: not now. The focus list is four things, and a roll is a moment rather than a state.
2. **Should a handout with several files page through them on the screen?** Recommendation: the first file, full size, this slice. A second press for the next file is a small follow-up.
3. **Should the screen have a "session" line?** Recommendation: no. The screen is the campaign's, and a session's name is often a spoiler.

## References

- `.ai/rules/table.md` — the gate is in the query, never the Blade. The screen adds the strictest form of that gate.
- `.ai/rules/events.md` — broadcast the fact, never the data, and always `ShouldRescue`.
- `.ai/rules/models.md` — a handout is revealed by its visibility column and nothing else; the screen reads that column and adds no second switch.
- `.ai/rules/routes.md` — `/screen` is singular and sits above the `{type}` routes, like `/table`.
- `.ai/rules/campaigns.md` — an added key does not bump the export version.

## Implementation Results — 2026-09-13

Implemented in full. 28 new tests; the suite is 1422 tests, 1421 passing, 1 skipped, with Larastan clean and Pint clean.

### What shipped, against the plan

| Planned | Shipped |
|---|---|
| Two columns on the campaign | As planned: `screen_focus` and `screen_entity_id`, with `ScreenFocus` as a backed enum and `Campaign::screen()` returning a `ScreenState` resolved through the party's gate. |
| The party's gate, whoever opened it | `Entity::visibleToParty()` and `MapMarker::visibleToParty()`, both user-free. `Table\Screen` has no `isDm()` branch at all, and the snapshot test holds that a hidden handout the columns name never reaches the payload. |
| `SetScreen` | As planned. `show()` reveals a hidden handout through `RevealHandout` and refuses a map the party may not see; `focus()` refuses a focus that needs a page. |
| `ScreenChanged` | As planned, with an empty payload. |
| `/screen` on its own layout | `layouts::screen`: no sidebar, no header, no search. The four focuses, the idle state, and the clock strip. |
| The card on the Run screen | `Table\ScreenControls`, at the top of the aside beside the secrets. Every handout, every party-visible map with a picture, the two focus buttons, clear, and a link that opens the screen in a new tab. |
| The round trip | Both keys in the campaign section; the reader accepts absent keys and refuses an unknown focus or a page the file does not carry; the importer writes both after the entities. |
| The demo world | The duke's letter is on the screen on first run, and the seeder test opens `/screen` and `/table`. |

### Deviations

| Planned | Shipped | Why |
|---|---|---|
| No poll on the controls card | A sixty-second poll, like every panel | The browser pass took a handout back from the panel beside the card, and with no socket the card kept saying the letter was up. The poll is the same backstop the panels keep. |
| The handout at 78vh, the map at 82vh | 62vh and 68vh | At 1024×768 the clock strip fell below the fold. The strip is the part a table glances at, so it has to be on the screen. |

### Not done

- The `public/storage` link was missing on this machine, so the first screenshot showed a broken image. `php artisan storage:link` fixed it. That is the environment, not the slice, and nothing in the repo changed for it.

### Browser checks

Driven end to end on the seeded world at 1400px as the GM and 1024px as the player, with `BROADCAST_CONNECTION=null`, so every change was read on the next load rather than pushed:

- The Run screen for session 3 shows the "On the screen" card with the two handouts, the two maps, "The fight", "The clocks", and the link.
- "On the screen" beside the duke's letter puts the letter on `/screen`, full size with its name under it and the two revealed clocks in the strip. The player's `/screen` shows the same page.
- "The fight" puts the turn order up: round, whose turn, four rows the party may see, health as words, the hidden thralls absent, no numbers anywhere, read from the GM's own session.
- The Duchy of Vell on the screen shows the four pins the party found and neither of the two hidden ones, which the GM's own map page shows in purple.
- "Take it back" on the letter while it is up drops the screen to the fight on the next load, with the columns still naming the letter.
