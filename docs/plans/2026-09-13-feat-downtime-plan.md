---
title: "feat: Downtime, what each character did between sessions"
type: feat
date: 2026-09-13
status: planned
brainstorm: docs/brainstorms/2026-09-02-demgem-campaign-manager-brainstorm.md
follows: docs/plans/2026-09-13-feat-player-screen-plan.md
---

# feat: Downtime, what each character did between sessions

## Overview

The brainstorm's players table has one P3 row, "Downtime tracking", with no notes. What a table means by it is settled enough: between two sessions each character did something, it took some days in the world, and somebody wants it written down where the next session can find it.

| Feature | What it adds |
|---|---|
| The downtime log | A table of what a character did, how many days it cost, the session it happened around, and where in the world's calendar it started. |
| Who writes it | A player writes their own PC's downtime. A GM writes anyone's. Everyone who may see the character reads it. |
| Three places | The character's page, the session's page, and `/downtime`, one component in each, the decision log's way. |
| The sum | Days are summed on every read, per character and for the campaign. Nothing is stored. |
| The round trip | A new top-level section in the export, the reader, the importer, and a section on the character's vault page. |

When this slice is done a player opens Wren Ashgrove's page after session 2 and writes "Trained with the Tidewardens, 5 days, from 22 Saltrise 312", and the page says Wren has spent 5 days of downtime and the training ran to 26 Saltrise. The GM opens session 2 and reads what all four characters did before session 3.

**On scope.** Phase 0 is the table, the model, the policy, and the actions. Phase 1 is the component and its three homes. Phase 2 is the export, the vault, the seeder, the rules, and the pass.

## Problem Statement

**Downtime is where a campaign's between-session play goes to be forgotten.** A player says "I spend the week forging the papers" at the end of a session, and by the next one nobody remembers whether it was a week or a fortnight, or whether it was this player or that one. The recap is the GM's and covers the session, not the gap after it.

**Days are a cost, and a cost wants a ledger.** Slice 19 gave coin a ledger because a purse in someone's head drifts. Downtime days are the same shape: a number per event, summed. A character who has spent forty days of downtime while another spent four is a fact the GM needs when the party splits.

**The calendar exists and nothing a player writes is on it.** Slice 12 gave the world months and a today. A session carries its days. A player's own activity has no day, so "the forgery was ready before the Duke's feast" is a sentence nobody can check.

## Proposed Solution

**One table, gated by the character's own gate.** `downtime_activities` is a list: many rows per character, each with a name, a day count, an optional note, an optional session, and an optional start date in the world. A row is visible to whoever may see the character, through `Entity::visibleTo()` in the query. There is no eye of its own: a character the party may see did what they did, and a GM-only character's rows are gone with the character. The session link is loaded separately through `GameSession::visibleTo()`, the clock's way.

**A player writes their own PC's rows, and a GM writes anyone's.** That is `EntityPolicy::update()`'s rule, and the downtime policy reads the same two facts: the role, and `entities.player_user_id`. Editing and deleting follow the same rule, not the author: a GM who wrote a row on Wren's page has written it for Wren's player to correct.

**`Downtime\Log` is one component in three places.** On a character's page it lists that character's rows and the form writes under them. On a session's page it lists the rows around that session, with a character picker. At `/downtime` it is the whole campaign, newest first, with a character picker and a session picker. The same shape as `Decisions\Log`, and for the same reason: three forks would drift.

**Days are summed on every read.** The character's page shows the total, the index shows a total per character. The sum is `sum(days)` over the rows the viewer may see, never a column.

**A start date is optional, and it is checked against the calendar.** With a calendar, the form offers the world's date picker, the same one the session form uses, and a row with a date prints "from 22 Saltrise 312 to 26 Saltrise 312" through `Reckoning::add()`. Without a calendar the picker is absent and a row prints its days. A date is three integer columns read as one `GameDate`, the models rule.

## Technical Approach

### No new dependency

### What slices 1 to 24 give us for free

| Piece | Reuse |
|---|---|
| `Decisions\Log` and its view | The nested component in three places, the inline edit, the session link gated separately. |
| `LedgerEntryPolicy` | The role fallback through `CurrentCampaign`. |
| `EntityPolicy::update()` | The rule for who writes a character's things. |
| `x-ui.game-date`, `Sessions\Form::gameDate()` | The date picker and its validation against the calendar. |
| `GameDateCast`, `Bounds`, `Reckoning::add()` | Storage, limits, arithmetic. |
| `ExportCampaign::SECTION_TABLES`, `RoundTripTest` | The round trip joins the comparison on its own. |
| `WriteCampaignMarkdown::entity()` | The character's page in the vault gains a section. |

### The data

```
downtime_activities
  id                ulid
  campaign_id       ulid -> campaigns, cascade
  entity_id         ulid -> entities (the character), cascade
  game_session_id   ulid nullable -> game_sessions, nullOnDelete
  activity          string(120)
  days              smallint unsigned, 0..999
  notes             text nullable, Markdown
  starts_year, starts_month, starts_day   integer nullable, as a group
  created_by        user id nullable, nullOnDelete
  created_at, updated_at
  index (entity_id, created_at)
```

No column on `entities`. The total is a sum, computed every time.

### Scope and decisions

| Question | Decision |
|---|---|
| Which characters | Any character. A player may write on a PC that is theirs. A GM may write on any character, PC or not, because an NPC's month matters to a GM too. |
| Who reads a row | Whoever may see the character. `scopeVisibleTo(user, role)` is a `whereIn` over `Entity::visibleTo()`. No eye. |
| Who edits or deletes | A GM, or the character's player. Not the author. |
| Zero days | Allowed. "Sold the signet" costs an afternoon. |
| The date | Optional. Checked against the calendar at write time, the session form's way. A row keeps its date when the GM later reshapes the calendar, and it prints "month 13" rather than failing, the reckoning rule. |
| The end date | Computed: `add(start, days - 1)` when days is above zero, else the start. Never stored. |
| No calendar | No picker, no date on the row. The days still count. |
| The card on a character's page | Shown when the character is a PC or already has rows. An NPC's page does not carry an empty card. |
| The card on a session's page | Always, under the decisions. |
| `/downtime` | Every member. The whole campaign's rows, with a total per character at the top. In the sidebar under Ledger. |
| Markdown | `notes` renders through `MarkdownRenderer`, like a decision. Not a mention source. |
| The API | None. Like a clock. |
| The vault | A "Downtime" section on the character's page, oldest first, with days, session, and date. |

### Actions

| Action | Does |
|---|---|
| `Downtime\RecordDowntime` | One row from validated parts. |
| `Downtime\UpdateDowntime` | The same parts, on a row. |
| `Downtime\DeleteDowntime` | Deletes the row. |

### Screens

- **The character's page** gains a "Downtime" card under the body: the total, the rows newest first, and for a writer the form and the controls.
- **The session's page** gains a "Downtime" card under the decisions: the rows around this session, with a character picker in the form.
- **`/downtime`** is a page header, a totals table, and the log.

### The round trip

- `downtime` is a new top-level section with `entity_id` and `game_session_id` remapped, the date as the triple every date in the document uses, and the reader refusing a row whose character the file does not carry.
- No version bump.

## Verification

    php artisan test --compact tests/Feature/Downtime
    php artisan test --compact --filter=RoundTrip
    php artisan test --compact --filter=DemoSeeder
    vendor/bin/pint --dirty --format agent
    vendor/bin/phpstan analyse

Then the full suite with `memory_limit=1G`, and a browser pass: as the player, write a row on Wren's page with a date and read the total and the range; as the GM, read it on session 2's page and on `/downtime`; as the player, open Halder's page and find no form.

## Open Questions

1. **Should downtime carry a cost in coin as well?** The ledger already does that, and a ledger row can name the session. Recommendation: no. Two purses is how they disagree.
2. **Should the timeline show a dated downtime row?** Recommendation: not now. The timeline lists events and sessions; a player's week is a different grain.
3. **Should a GM be able to hide a row from the party?** Recommendation: no. The character's own gate is the gate. A GM who wants a secret week writes it in GM notes.

## References

- `.ai/rules/decisions.md` — one component in three places, never forked.
- `.ai/rules/models.md` — a list gets a child table; a date is three columns read as one `GameDate`; the ledger is summed on every read.
- `.ai/rules/livewire.md` — nested and it writes, so it enters the campaign itself.
- `.ai/rules/campaigns.md` — a new table joins the export in the same commit.
- `.ai/rules/reckoning.md` — every number from a browser or a file goes through `Bounds`.
