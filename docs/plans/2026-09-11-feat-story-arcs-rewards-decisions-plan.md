---
title: "feat: Story arcs, and the log of what the party earned and chose"
type: feat
date: 2026-09-11
status: implemented
brainstorm: docs/brainstorms/2026-09-02-demgem-campaign-manager-brainstorm.md
follows: docs/plans/2026-09-09-feat-homebrew-stat-blocks-plan.md
---

# feat: Story arcs, and the log of what the party earned and chose

## Overview

Three P2 rows in the brainstorm are about the campaign's memory, and all three lean on sessions and quests, which slices 2 and 3 built. This slice builds the three together.

| Feature | What it adds |
|---|---|
| Story arcs | A new entity type. A quest and a session may each belong to one arc. The arc page lists its quests by status and its sessions in order. |
| The reward log | Two columns on a session: the XP the party earned, and the milestone it reached. The story page carries a running total. |
| The decision log | A table of the choices the party made and what came of them, tied to the session where it happened, revealed to the party when the GM says so. |
| The round trip | Every column and the new table in the export, the import, and the Markdown vault. |
| The API | The session and entity resources gain the new keys, and the same writes the forms make. |

When this slice is done a GM opens "The Drowned Duke" and reads the three quests under it, the four sessions the party spent on it, and the moment they let the smuggler go. The story page says the party has earned 1,450 XP over those sessions and reached two milestones. A player reads the same page and sees only the choices the GM revealed.

**On scope.** Phase 0 is the arc: the type, the two columns, the page. Phase 1 is the reward log. Phase 2 is the decision log. Phase 3 is the export, the seeder, the rules, and the pass.

## Problem Statement

**A quest log is flat, and a campaign is not.** Twelve quests in four states are a list. "The Drowned Duke", "The Salt Road", and "What the Tide Took" are what a GM actually thinks in. Kanka and World Anvil call the grouping a story arc or a chapter. Without one, the quests index and the sessions index cannot say which chapter the party is in.

**The party's progress has nowhere to be written down.** A GM hands out XP at the end of a session or declares a milestone, and the number lives in a chat message. The next session the players ask "how much did we have". The session already carries the recap, and the recap is where the number belongs.

**The choices the party made are the campaign's real plot, and they are lost in prose.** "We let the smuggler go" is a sentence in a recap. Three sessions later the harbor guild turns on the party and the GM wants to point back to the line. A log with two halves, the choice and what it cost, is what a GM keeps in a notebook now.

## Proposed Solution

**An arc is an entity.** `EntityType::Arc` gets a body, a visibility, wiki links, tags, an image, the export, and the vault for free, exactly as `Event` and `Map` did. What an arc adds is two nullable columns elsewhere: `entities.arc_id` on a quest, and `game_sessions.arc_id` on a session. Both are scalars, one-to-one with the row, so both are columns by the models rule.

**The reward is two scalars on the session.** `game_sessions.xp_awarded` and `game_sessions.milestone`. A campaign runs on XP or on milestones, and a session may carry either or both. The log is a query over sessions in order, not a table. A player sees a session's reward when they see the session.

**A decision is a row in its own table.** `decisions` is a list per campaign: many rows, each with its own visibility, individually edited, and the consequence written in later. That is the shape the models rule says gets a table. It is gated the clock's way: `player_visible` on the row decides whether the party sees it, and the link to the session is loaded through `GameSession::visibleTo()` separately, so a revealed decision from a GM-only session shows its words and drops the link.

**One Livewire component serves two places.** `Decisions\Log` is the routed page at `/decisions`, and the same component nested on the session page and the run screen with a session passed in. Scoped to a session it lists that session's decisions and hides the session select. The rule in `.ai/rules/livewire.md` applies: it uses `InteractsWithCampaign` and calls `enterCampaign()` in its own `mount()`.

## Technical Approach

### No new dependency

Nothing to install.

### What slices 1 to 17 give us for free

| Piece | Reuse |
|---|---|
| `EntityType::Event` | The shape of a type added late: label, plural, slug, icon, description, priority, and the sidebar picks it up. |
| `entities.giver_entity_id` | The shape of an entity-to-entity reference on one type only: prohibited on the wrong type, remapped in the export, validated to the same campaign. Every file it touches is a file `arc_id` touches. |
| `game_sessions.in_game_start` | The shape of a scalar added to a session: form, page, story, resource, controller, export, reader, importer, front matter. |
| `Clock` and `Clocks\Index` | A GM-written table with `player_visible`, a `scopeVisibleTo()`, a policy, four actions, and an inline list page. |
| `Entity::visibleTo()` and `GameSession::scopeVisibleTo()` | The two gates on the arc page, the story total, and the decision's session link. |
| `ExportCampaign::SECTION_TABLES` | Where `decisions` joins, and the round-trip test picks it up on its own. |

### The data

```
entities
  arc_id           ulid nullable -> entities, nullOnDelete, indexed
                   Set on a quest only. Prohibited on every other type, including an arc.

game_sessions
  arc_id           ulid nullable -> entities, nullOnDelete, indexed
  xp_awarded       unsigned int nullable, 0..1000000
  milestone        string(120) nullable

decisions
  id               ulid
  campaign_id      ulid -> campaigns, cascade
  game_session_id  ulid nullable -> game_sessions, nullOnDelete
  choice           text, 1..2000     "Let the smuggler go with the ledger."
  consequence      text nullable, max 2000   "The harbor guild no longer trusts the party."
  player_visible   boolean default false
  created_by       user id nullable, nullOnDelete
  created_at, updated_at
  index (campaign_id, created_at)
```

An arc's `arc_id` is prohibited so that no chain forms. Nesting arcs is a future slice, and `parent_id` is there for it if it comes.

### Scope and decisions

| Question | Decision |
|---|---|
| Is an arc an entity or its own table? | An entity. It has a body, a visibility, and wiki links, which is everything an entity is. `Event` and `Map` took the same path. |
| One arc per quest, or many? | One. `arc_id` is a column. A quest that belongs to two chapters is rare, and a pivot costs a join on every quest read. |
| Can an event belong to an arc? | Not in this slice. The brainstorm names quests and sessions. |
| Does an arc have a status? | No column. The arc page shows its quests grouped by status and its sessions in order, which says whether it is alive. A status column is one more thing to export, import, and validate for a fact the page already shows. |
| Who sees an arc's quest list? | Every quest goes through `Entity::visibleTo()` and every session through `GameSession::scopeVisibleTo()`, in the query. The arc page never asks in the Blade. |
| Does a quest page show its arc? | Yes, as "Part of", and only when the arc passes `Entity::visibleTo()` for the viewer. A player may read a quest whose arc is still GM-only. |
| XP and milestone, one or both? | Both columns, both nullable. A table that levels by milestone leaves XP blank. |
| Where is the reward log? | The story page. Each recap carries its award, and the header carries the total over the sessions the viewer may see. A player's total never counts a GM-only session. |
| Who sees a session's reward? | Anyone who sees the session. The number is not a DM field. `dmOnlyFields()` is unchanged. |
| Is a decision a mention source? | No, like a secret. The text renders through `MarkdownRenderer` with wiki links, so a `[[Name]]` works when read, but a rename does not rewrite it and it does not appear in backlinks. Recorded as a known limit. |
| Who writes a decision? | GM roles. A player reads. Same policy shape as a clock. |
| Is a decision in the API? | Not in this slice. Clocks and encounters are not either. The session and entity resources gain their new keys, because those resources already exist. |
| Is a decision on the run screen? | Yes, the same nested component. The moment to write "they let him go" is the moment it happens. |
| Deleting an arc | `nullOnDelete` on both columns. The quests and sessions stay, with no arc. |
| Deleting a session | `decisions.game_session_id` is `nullOnDelete`. The choice was made whether or not the session row survives. A soft-deleted session keeps the link and the log shows it by number. |

### Actions

| Action | Does |
|---|---|
| `Decisions\RecordDecision` | Writes a row from a validated choice, an optional consequence, and an optional session of the same campaign. `player_visible` false. |
| `Decisions\UpdateDecision` | The same three fields on an existing row. |
| `Decisions\SetDecisionVisibility` | The reveal. One column, one write. |
| `Decisions\DeleteDecision` | Deletes the row. |

`CreateEntity` and `UpdateEntity` gain `arc_id` beside `giver_entity_id`, and drop it to null on a non-quest. `CreateSession` and `UpdateSession` gain `arc_id`, `xp_awarded`, and `milestone`.

### Screens

- **The arc page** is `Entities\Show` for the new type: the body as any entity, then "Quests in this arc" grouped by status with the objective progress bar the quests index draws, then "Sessions in this arc" in number order with the recap excerpt. A GM sees the empty groups with a hint. A player sees only what the two scopes return.
- **The quest form** gains an arc select, arcs of this campaign by name, shown for a quest only. The session form gains the same select and two fields: XP awarded and milestone.
- **The quest page and the session page** each show "Part of {arc}" under the title when the arc passes the viewer's gate.
- **The story page** gains a header strip, "1,450 XP over 4 sessions · 2 milestones", and a line per recap with that session's award. The strip is hidden when no visible session carries either.
- **`/decisions`** is the log, every member. Grouped under the session that made it, oldest first, then the ones with no session. A GM gets an inline form at the top, edit in place, a reveal toggle, and delete. A player sees revealed rows and the session link only when the session passes.
- **The session page and the run screen** embed the same component scoped to that session.
- **The sidebar** gains "Decisions" under Play for every member, and "Arcs" arrives under World through `EntityType::cases()`.

### The round trip

- `entities` rows gain `arc_id`, remapped through IdMap, and the reader refuses a file whose `arc_id` names no row or names a row that is not an arc.
- `sessions` rows gain `arc_id`, `xp_awarded`, and `milestone`.
- `decisions` is a new top-level section after `sessions`, with `game_session_id` remapped. `ExportCoverageTest` needs the table in `SECTION_TABLES` or it fails, which is the point of it.
- The Markdown vault: a quest's front matter gains `arc`, a session's front matter gains `arc`, `xp_awarded`, and `milestone`, and a `Decisions.md` page lists every decision under its session, with the consequence beneath the choice.
- No version bump. Added keys do not move `VERSION`.

### The API

- `EntityResource` gains `arc_id` on a quest. `EntityController` accepts it on a quest and marks it `prohibited` on every other type, and validates that it names an arc of this campaign.
- `SessionResource` gains `arc_id`, `xp_awarded`, and `milestone`. `SessionController::update()` accepts all three.

## Verification

    php artisan test --compact --filter=Arc
    php artisan test --compact --filter=Reward
    php artisan test --compact --filter=Decision
    php artisan test --compact --filter=RoundTrip
    php artisan test --compact --filter=ExportCoverage
    php artisan test --compact --filter=Markdown
    php artisan test --compact tests/Feature/Api
    vendor/bin/pint --dirty --format agent
    vendor/bin/phpstan analyse

Then the full suite with `memory_limit=1G`, and a browser pass at a laptop width and a tablet width: make an arc, put two quests and a session in it, read the arc page as a GM and as a player, award XP on a session and read the story total from both seats, record a decision on the run screen, reveal it, and read `/decisions` as a player.

## Open Questions

1. **Should the timeline show decisions?** A decision made in a session with an in-game date could sit on the timeline beside the session. Recommendation: not in this slice. The timeline reads two scopes and merges them, and a third list is its own change with its own leak test.
2. **Should the arc page show a running XP total for its sessions?** It is one sum over rows the page already loaded. Recommendation: yes if it costs one line, out if it needs a second query.
3. **Should a decision carry the entities it is about?** A pivot to entities would let an NPC's page list the choices that involved them. Recommendation: out. Wiki links in the text carry the names for the reader, and a pivot is a fourth gated list with its own leak test.

## References

- `.ai/rules/models.md` — a list gets a child table, a scalar gets a column; the clock's two gates.
- `.ai/rules/livewire.md` — a nested component that writes re-checks membership itself.
- `.ai/rules/table.md` — filter in the query, never in the Blade.
- `.ai/rules/campaigns.md` — a new campaign-scoped table joins the export in the same commit; an added key does not bump the version.
- `.ai/rules/feature-campaigns.md` — the round trip is driven by `SECTION_TABLES`.
- `.ai/rules/api.md` — the API is the screens in JSON; prohibited fields name themselves in the 422.
- `.ai/rules/routes.md` — no route parameter named after a model.
- `docs/plans/2026-09-08-feat-calendar-timeline-plan.md` — the last type added late, and the shape of a session scalar's touchpoints.

## Implementation Results — 2026-09-11

Implemented in full. 30 new tests; the suite is 1331 tests, 1330 passing, 1 skipped, with Larastan clean and Pint clean.

### What shipped, against the plan

| Planned | Shipped |
|---|---|
| `EntityType::Arc`, `entities.arc_id`, `game_sessions.arc_id` | As planned. `DeleteEntity` nulls both columns itself, because a soft delete never fires `nullOnDelete`. |
| The arc page's two lists | As planned, through the two scopes, grouped by status in PHP. The browser pass moved them to directly under the body: they are the point of the page, and they sat below Relationships and Body history on the first render. |
| `xp_awarded` and `milestone` on a session | As planned. `GameSession::rewardLine()` is the one place "450 XP · Reached the Drowned Court" is spelled; the session page, the story page and the vault all go through it. |
| The story page strip and per-recap line | As planned. Three counts in one query, cloned, over `visibleTo()`. |
| `decisions` and `Decisions\Log` in three places | As planned, with one addition: `Decisions\Index` is the routed page and holds nothing but the header, so the log stays one component. |
| The round trip | `decisions` is a new top-level section; `arc_id` joins the dropped-id list in `RoundTripTest` and `ArcsRewardsRoundTripTest` holds the link itself, the way `HomebrewRoundTripTest` does for a creature. `created_at` travels on a decision because the order of the log is the order they were made in. |
| The vault | Front matter as planned, plus one `decisions.md` page for the whole log rather than a file per row. |
| The API | As planned. A decision has no endpoint, like a clock. |

### Deviations

| Planned | Shipped | Why |
|---|---|---|
| The demo arc named "The Drowned Duke" | "The Duke Beneath" | The demo already has a character of that name, so the arc's slug would have been suffixed, and the seeder test could not name the page. The villain and the chapter reading differently is better anyway. |
| One empty state for the log | A one-line notice when the log is scoped to a session | The full empty state, with its icon and two sentences, was the tallest thing in the run screen's narrow column. |
| A session link on every row | Hidden when the log is scoped | On the session page every row would have linked to the page it was on. |

### Open questions, answered

1. The timeline does not show decisions. Out, as recommended.
2. The arc page has no XP total. It would have needed the sessions' columns loaded for a sum the story page already shows, so it stayed out.
3. A decision carries no entity pivot. Out, as recommended; wiki links in the text carry the names.

### Browser checks

Driven end to end on the seeded world at 1400px as the GM and at 834px as the player:

- The arc page lists Seal the Undercity under Active with its progress bar, and the three sessions in order with their status badges.
- The story page reads 1,450 XP over 2 sessions and 1 milestone for the GM, with the reward line under each recap's date.
- `/decisions` shows the GM three rows with edit, eye and delete, and the player the two revealed ones with no controls and no trace of the third.
- The session page shows the player "Part of The Duke Beneath", the reward line, and the one decision made that night.
- The run screen carries the log under Clocks with the form ready to write in.

The first screenshots showed a decision's session and its time run together. That was the CSS build, not the Blade: the utility the meta line uses was not in the compiled stylesheet until `npm run build` ran. A change that adds a utility class no view has used before needs a build before a browser pass means anything.
