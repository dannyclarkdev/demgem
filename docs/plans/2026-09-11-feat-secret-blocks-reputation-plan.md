---
title: "feat: What the party may not read, and how the factions feel about them"
type: feat
date: 2026-09-11
status: implemented
brainstorm: docs/brainstorms/2026-09-02-demgem-campaign-manager-brainstorm.md
follows: docs/plans/2026-09-11-feat-journals-party-ledger-plan.md
---

# feat: What the party may not read, and how the factions feel about them

## Overview

Two P2 rows are about what the party knows. One hides a paragraph inside a page they can read. The other tracks what a faction thinks of them, and lets the GM decide how much of that they have noticed.

| Feature | What it adds |
|---|---|
| Secret blocks | A `:::secret` fence inside any Markdown body. A GM reads it as a marked aside. A player never receives it: not on the page, not in the API, not in a backlink, not in a search hit. |
| Faction reputation | A log of changes to a faction's standing with the party, each with a reason and an eye. The true standing is the sum of all of them. What the party sees is the sum of the ones the GM revealed. |
| The round trip | Secret blocks are text and travel as they are. The reputation log joins the export, the import, and the vault. |

When this slice is done a GM writes the Abbess's page with one paragraph the party reads and one, fenced, that says she serves the Duke. The party's page is the first paragraph. The Tidewardens' page says the party stands at Friendly, +2, and the GM's says the same page reads +2 to the party and −1 in truth, because the favour Mara called in has not been noticed yet.

**On scope.** Phase 0 is the secret block: the fence, the renderer, and every reader a player has. Phase 1 is reputation: the table, the card, the badge. Phase 2 is the export, the seeder, the rules, and the pass.

## Problem Statement

**A page is one visibility, and a paragraph is not.** The Abbess is a page the party may read; the fact that she serves the Duke is not. Today that sentence goes in GM notes, away from the prose it belongs to, and the GM reads two boxes to remember one person. Kanka and World Anvil both give a GM a fence for this, because the alternative is a page written twice.

**A secret is a leak in five places, not one.** The page is the obvious one. The API returns the body. A `[[link]]` inside the secret makes a backlink on the target's page. The search finds the word. The player's own editor, on a PC the GM annotated, shows the raw text. A fence that hides the paragraph on the page and nowhere else is worse than no fence, because the GM will trust it.

**Reputation is a number the GM keeps in their head.** Every table has a version of "the guild does not like you any more", and every GM forgets what it was at. The number is the sum of a few remembered moments, which is a log with a reason on each row. Half the point is that the party has noticed some of those moments and not others.

## Proposed Solution

**A secret block is a fenced container, and a player's copy of the text never has it.** `:::secret` on its own line opens, `:::` on its own line closes. `SecretBlocks::strip()` is the one function that removes them, and every reader a player has calls it before the text goes anywhere: the renderer, the excerpt, the resource, the search filter, the mention scanner, and the form. A GM's renderer parses the fence with a CommonMark block extension and prints an aside marked "GM only". That is a structural design rather than a careful one: the player's request never holds the paragraph.

**The mention scanner indexes the two halves under two field names.** `body` is scanned with the secrets stripped, and the secret text alone is scanned as `body:secret`. `Entity::playerVisibleFields()` and the session backlink filter already list the fields a player may see, and neither lists a `:secret` field, so a link inside a fence never reaches a player's backlinks and nothing else changes.

**The search drops a hit a player could only have got from the fence.** Both Scout drivers search the stored column, and a fence lives in that column. So `Search` filters a non-DM's hits in PHP: a hit stays only when the term appears in the name, the stripped body, the rewards, the class, or the custom fields. Fifty rows at most, one string check each.

**A player's editor gets the stripped body, and their save keeps the fences.** A player edits their own PC and their own journal. The form loads `strip(body)` into their editor, and on save the fences the stored body held are appended back, each as its own block. The API's update does the same. A GM's annotations survive a player's edit; they move to the end of the page, which the plan records as the cost.

**Reputation is a log per faction, and the standing is a sum.** `reputation_changes` is a list: many rows per faction, each with a reason, a session, and an eye. The true standing is the sum of every row. The party's standing is the sum of the revealed rows. Both are computed on every read, and the GM's card shows both numbers side by side. The rows are gated on `player_visible` in the query, the clock's way, and the faction page itself is already gated by `Entity::visibleTo()`.

## Technical Approach

### No new dependency

`league/commonmark` 2.10 is installed and already carries the wiki link extension. The fence is a second extension on the same environment.

### What slices 1 to 19 give us for free

| Piece | Reuse |
|---|---|
| `WikiLinkRenderer` | Carries the viewer's role into the renderer. `revealsSecrets()` is one method on it. |
| `WikiLinkExtension` | The shape of a CommonMark extension in this app. |
| `SyncMentions` and `playerVisibleFields()` | The field-name gate on backlinks. A `:secret` field is one no list names. |
| `Clock`, `Decision`, `Clocks\Panel` | A gated GM table with an eye, and a nested component on an entity page. |
| `Entities\Index` | Where a faction's badge lands. |

### The data

```
reputation_changes
  id                ulid
  campaign_id       ulid -> campaigns, cascade
  entity_id         ulid -> entities (the faction), cascade
  game_session_id   ulid nullable -> game_sessions, nullOnDelete
  delta             smallint, -5..5, never 0
  reason            string(200) nullable
  player_visible    boolean default false
  created_by        user id nullable, nullOnDelete
  created_at, updated_at
  index (entity_id, created_at)
```

No column on `entities`. The standing is a sum, computed every time, and a stored total is the second source of truth that drifts.

`config/reputation.php` holds the bands: Hostile at −3 and below, Unfriendly at −2 and −1, Neutral at 0, Friendly at 1 and 2, Allied at 3 and above. `Standing::band()` reads them. The names are demgem's own and the config says so.

### Scope and decisions

| Question | Decision |
|---|---|
| Fence syntax | `:::secret` alone on a line opens; `:::` alone on a line closes. No nesting. An unclosed fence runs to the end of the text, which is the safe reading: a GM who forgot the close hid too much, not too little. |
| Where a fence works | Every Markdown field: a body, a recap, rewards, a decision, a table entry. One function strips them all. |
| A fence in GM notes or a strong start | Rendered as an aside for the GM. It changes nothing, because a player never reads those fields anyway. |
| The GM's aside | An `<aside class="secret-block">` with a "GM only" label and the same purple the DM badges use. |
| A link inside a fence | Indexed under `field:secret`. A GM's backlinks include it; a player's never do. |
| The search | A player's hit must match outside the fence. The check is in PHP over the hits, under both drivers. A GM's search is unchanged, and a GM's search still does not read GM notes, as before. |
| A player's editor | Shows the stripped body. Their save re-appends the stored fences. A GM's editor shows everything. |
| The API | `body`, `rewards`, and `recap` are stripped for a non-DM key. A non-DM's update re-appends the fences, as the form does. |
| The export and the vault | The GM's own. Raw text. The vault README already says the vault is a copy for the GM. |
| The delta's range | −5 to +5, never 0. A change of nothing is not a change. |
| Who reads the standing | Whoever reads the faction. The number a player reads is the sum of the revealed rows. The GM reads that number and the true one. |
| Who writes a change | GM roles. A player never does. |
| Is a change edited? | The reason is. The delta is not: a wrong delta is deleted and written again, the ledger's rule. |
| The badge on the factions index | The band of the standing the viewer may see, when there is at least one row they may see. A faction with no visible rows shows no badge rather than "Neutral". |
| The API | No reputation endpoint. Like a clock. |

### Actions

| Action | Does |
|---|---|
| `Factions\AdjustReputation` | Writes one row from a validated delta, an optional reason and session. Hidden. |
| `Factions\UpdateReputationReason` | The reason only. |
| `Factions\SetReputationVisibility` | The eye. |
| `Factions\DeleteReputationChange` | Deletes the row. |

### Screens

- **Every page with prose** gains nothing visible to a player and an aside for a GM.
- **The faction page** gains a "Standing" card: the band and the number, the GM's second number when it differs, the rows newest first with their reason and session, and for a GM a form (delta, reason, session), an eye, and a delete.
- **The factions index** shows the band as a badge on each card.

### The round trip

- `reputation_changes` is a new top-level section with `entity_id` and `game_session_id` remapped. The reader refuses a row whose faction the file does not carry.
- The vault: a faction's front matter gains `standing`, and its body gains a "Standing" section listing the rows.
- No version bump.

## Verification

    php artisan test --compact --filter=Secret
    php artisan test --compact --filter=Reputation
    php artisan test --compact tests/Feature/Mentions
    php artisan test --compact tests/Feature/SearchTest.php
    php artisan test --compact tests/Feature/Api
    php artisan test --compact --filter=RoundTrip
    vendor/bin/pint --dirty --format agent
    vendor/bin/phpstan analyse

Then the full suite with `memory_limit=1G`, and a browser pass: write a fence on the Abbess as the GM and read the aside; open the page as the player and read the first paragraph only; search for a word inside the fence as the player and find nothing; open the Tidewardens as both seats and read two standings.

## Open Questions

1. **Should a fence take a title, as in `:::secret The Duke`?** Recommendation: not now. A GM who wants a heading writes one inside the fence.
2. **Should reputation apply to a character as well as a faction?** An NPC has an opinion of the party too. Recommendation: not in this slice. The table takes an `entity_id`, so widening it later is a policy line and a form.
3. **Should the timeline or the story show a reputation change?** Recommendation: no. The session link on the row is the link back.

## References

- `.ai/rules/models.md` — the clock's two gates, and every table since that used them.
- `.ai/rules/observers.md` — the mention field map is derived from `mentionableFields()`; the `:secret` split lives in `SyncMentions`, not in the observers.
- `.ai/rules/entities.md` — `sheet_url` is the one user URL outside the renderer; a secret block is the one piece of prose the renderer must not see.
- `.ai/rules/api.md` — keys dropped, not nulled; here a paragraph is dropped from a key.
- `.ai/rules/table.md` — filter in the query, never in the Blade; the search filter is the one place this slice filters in PHP, and the plan says why.

## Implementation Results — 2026-09-11

Implemented in full. 21 new tests; the suite is 1370 tests, 1369 passing, 1 skipped, with Larastan clean and Pint clean.

### What shipped, against the plan

| Planned | Shipped |
|---|---|
| `SecretBlocks::strip()` as the one function | As planned, under `App\Markdown\Secrets`, with `only()` for the mention scanner and `merge()` for a player's save. An unclosed fence runs to the end of the text, and `merge()` closes one it puts back. |
| The GM's aside | A CommonMark block extension, `SecretBlockExtension`, registered only when `WikiLinkRenderer::revealsSecrets()`. With no renderer at all the text is stripped, which is the safe reading. |
| The six readers | The renderer, the recap excerpt, both API resources, the search, the mention scanner, and the form and API update for a non-DM. The mention split lives in `SyncMentions`, once, so the observers' maps stayed derived from `mentionableFields()`. |
| `reputation_changes` and the card | As planned. The delta is clamped to the configured range and refused at zero in the form and in the action. |
| The factions index badge | One grouped query over the page's factions, through the viewer's scope. |
| The round trip and the vault | `reputation` is a new top-level section with both links remapped. A faction's front matter gains `standing` and its body a section of the rows. |

### Deviations

| Planned | Shipped | Why |
|---|---|---|
| An "Unfriendly" badge in a warning colour | The danger colour | The badge component has no warning variant, and the bands are config, so a GM who wants one adds it there. |
| The standing card below the page's other cards | Directly under the body | The browser pass found it under Relationships and Body history, the same finding as the arc's lists in slice 18. |
| The card hidden from a GM until a row exists | Always shown to a GM | A GM needs the form to write the first row, and "The party has no read on them yet" is the honest first state. |

### A flake seen on the way

`CustomFieldsTest` failed once in a batch of 263 and passed on every rerun, alone and in the batch. It asserts `assertDontSee('Coll')` on a search page, a bare four-letter word, which is the class of assertion `.ai/rules/tests.md` warns about. It is not this slice's, and it is left as found.

### Browser checks

Driven end to end on the seeded world at 1400px as the GM and 834px as the player:

- The Abbess's page shows the GM a purple "GM only" aside between two paragraphs, and the player the two paragraphs with nothing between them.
- The player's search for "crypt door", words inside the fence, finds nothing.
- The Tidewardens' page shows the GM "+2 Friendly" as the party sees it beside "0 Neutral" in truth, with three rows and the eye on each; the player reads "+2 Friendly" and the two revealed rows.
