---
title: "feat: The players' own pages, and the party's purse"
type: feat
date: 2026-09-11
status: implemented
brainstorm: docs/brainstorms/2026-09-02-demgem-campaign-manager-brainstorm.md
follows: docs/plans/2026-09-11-feat-story-arcs-rewards-decisions-plan.md
---

# feat: The players' own pages, and the party's purse

## Overview

Eighteen slices in, a player can edit one thing: their own character. Two P2 rows in the brainstorm give the players a pen. This slice builds both.

| Feature | What it adds |
|---|---|
| Journals | A new entity type a player may create. The author owns it, shares it with the party or keeps it between them and the GM, and gets wiki links, backlinks, search, the export, and the vault for free. |
| The party ledger | One table of coin and item movements any member may write to. A running balance, an inventory summed from the rows, and the session each movement happened in. |
| A currency | One label per campaign, "gp" by default, set in campaign settings. The ledger prints it and nothing else asks. |
| The round trip | Journals travel as entities. The ledger and the currency join the export, the import, and the Markdown vault. |

When this slice is done a player writes "We met the abbess. I do not trust her." after the session, shares it with the party, and the GM reads it. The next night the party finds forty gold in a drowned chest, a player types it into the ledger from their phone, and the balance at the top says 212 gp before the GM has opened a laptop.

**On scope.** Phase 0 is the journal: the type, the policy, the form's author branch, the index. Phase 1 is the ledger: the currency, the table, the page. Phase 2 is the export, the seeder, the rules, and the pass.

## Problem Statement

**The players remember things the GM does not write down.** A player's notes live in a phone app or a paper notebook, and the party's shared knowledge lives in nobody's. Every campaign manager in the field gives players a journal, because the alternative is "what was that priest's name" at the top of every session.

**Nobody knows how much gold the party has.** The ledger is the most-asked question at a table after "whose turn is it", and the answer is a number one player keeps in a spreadsheet. It should be a number every member can read and any member can move.

**A player has no reason to open the app between sessions.** Every page is the GM's. A journal and a ledger are the two pages a player owns, and owning a page is what brings someone back.

## Proposed Solution

**A journal is an entity, and its author is its player.** `EntityType::Journal` gets everything an entity has. What makes it a player's is `player_user_id`, which the slice 4 gate already honours: `Entity::visibleTo()` shows a row to its player whatever the visibility says. So a journal with visibility Dm is "me and the GM" and one with Players is "the party", and no new gate is written. `EntityPolicy::create()` learns the type: a GM makes anything, a member makes a journal. `delete()` lets the author delete their own. The form shows the author a two-way visibility switch outside the DM card, because `updateDmFields()` stays a GM ability.

**The ledger is one table of movements.** `ledger_entries` holds a row per event: coin in or out, or an item picked up or spent. A balance is a sum, an inventory is a group-by, and both are computed every time and never stored. That is the shape the models rule gives a list. Every member may write a row; the author or a GM may delete one; nobody edits one, because a ledger is corrected by a second row and a deleted mistake, like a bank statement.

**Nothing in the ledger is gated.** The party's purse is the party's, and a player who could not read it would have no reason to write to it. The one link on a row, the session it happened in, goes through `GameSession::visibleTo()` the clock's way, so a movement recorded during a GM-only session keeps its words and loses the link.

## Technical Approach

### No new dependency

Nothing to install.

### What slices 1 to 18 give us for free

| Piece | Reuse |
|---|---|
| `Entity::visibleTo()` and `player_user_id` | The whole visibility model of a journal. A player already sees their own PC through it. |
| `EntityPolicy::update()` | Already lets `player_user_id` edit the row. |
| `EntityType::Arc` | The shape of a type added late, and the sidebar and the vault pick it up. |
| `Decision` and `Decisions\Log` | A campaign table with a session link gated separately, a policy with an author, and a list with an inline form. |
| `Campaigns\Settings` | Where the currency lands. |
| `ExportCampaign::SECTION_TABLES` | Where `ledger_entries` joins. |

### The data

```
campaigns
  currency          string(12) default 'gp'

entities
  (no new column; a journal uses player_user_id as its author)

ledger_entries
  id                ulid
  campaign_id       ulid -> campaigns, cascade
  game_session_id   ulid nullable -> game_sessions, nullOnDelete
  kind              string(8)   'coin' | 'item'
  amount            decimal(12,2) nullable   signed; coin only
  item_name         string(120) nullable     item only
  quantity          integer nullable         signed; item only
  entity_id         ulid nullable -> entities, nullOnDelete   the Item page, when there is one
  note              string(500) nullable
  created_by        user id nullable, nullOnDelete
  created_at, updated_at
  index (campaign_id, created_at)
```

### Scope and decisions

| Question | Decision |
|---|---|
| Is a journal an entity? | Yes. It has a body, a visibility, wiki links, and an author. `Event`, `Map`, and `Arc` took the same path. |
| Who is the author? | `player_user_id`. It is what the gate already reads. A GM who writes a journal is its author too. The author never changes. |
| What visibility may an author set? | Dm ("just me and the GM") or Players ("the party"). Selected stays a GM decision from the DM card. |
| Who may delete a journal? | Its author, or a GM. |
| DM notes on a journal? | A GM may write them, as on any entity. The author never sees them. |
| Journal order | Newest first on the index, by `created_at`. A journal is a diary, not an encyclopedia. |
| Is a journal on the timeline? | No. It has no in-world date. A later slice may give it one. |
| Is the ledger gated? | No. Every member reads all of it. The session link is gated on its own. |
| Who writes the ledger? | Every member. Who deletes: the author or a GM. Nobody edits: a wrong row is deleted and written again. |
| Coin or item, one table or two? | One. The page is one list in time order, and "found 40 gold and a signet" is two rows of the same event. A `kind` column tells them apart. |
| Units | `amount` is a signed decimal in the campaign's one currency. Silver and copper are the GM's arithmetic; a system-agnostic core carries one unit and prints its label. |
| The inventory | Item rows grouped by the Item page when linked, else by name, quantities summed, and only totals above zero shown. Computed on every read. |
| Linking an item | Optional. A row names the item; it may also point at an Item page, picked from the ones the writer may see. |
| The currency | `campaigns.currency`, twelve characters, "gp" by default, in settings. In the export. Never a second one. |
| The API | The entity endpoints gain journals for free, and a key may create one where the form would allow it. The ledger has no endpoint, like a clock and a decision. |

### Actions

| Action | Does |
|---|---|
| `Ledger\RecordLedgerEntry` | Writes one coin or item row from validated input, with the author and an optional session and Item page of the same campaign. |
| `Ledger\DeleteLedgerEntry` | Deletes the row. |

`CreateEntity` sets `player_user_id` to the actor on a journal. `EntityPolicy::create()` takes the type as a third argument and every caller passes it.

### Screens

- **The journals index** is `Entities\Index` for the new type: newest first, each card with its author and date, and a **New journal** button every member sees.
- **The journal form** for the author: name, body, tags, and a two-way visibility switch. The DM card stays a GM's. A GM editing a player's journal gets the DM card as well.
- **The journal page** carries "By {author}" and the date under the title.
- **`/ledger`** is every member's: the balance and the currency at the top, the inventory beside it, an inline form (coin or item, amount or name and quantity, an optional note, an optional session and Item page), and the movements newest first. A delete for the author or a GM.
- **The sidebar** gains "Ledger" under Play for every member, and "Journals" arrives under World through `EntityType::cases()`.
- **Settings** gains the currency field.

### The round trip

- Journals export as entities with `player_user_id`, which the importer drops as it does for every person column. An imported journal is authorless, GM-only, and the report counts it as a dropped person link. That is the rule "nothing is made more visible than the file says", and it is the right loss: the file cannot say who a person is on this install.
- `campaign.currency` is an added key. No version bump.
- `ledger_entries` is a new top-level section with `game_session_id` and `entity_id` remapped.
- The vault gains `journals/` and one `ledger.md` with the balance, the inventory, and the movements.

## Verification

    php artisan test --compact --filter=Journal
    php artisan test --compact --filter=Ledger
    php artisan test --compact --filter=RoundTrip
    php artisan test --compact --filter=ExportCoverage
    php artisan test --compact --filter=Markdown
    php artisan test --compact tests/Feature/Api
    vendor/bin/pint --dirty --format agent
    vendor/bin/phpstan analyse

Then the full suite with `memory_limit=1G`, and a browser pass at a laptop width and a tablet width: write a journal as the player, share it, read it as the GM, keep one private and confirm a second player cannot see it, record coin and an item on the ledger from the player's seat, read the balance from both seats, and delete a row.

## Open Questions

1. **Should a journal carry an in-world date and sit on the timeline?** It is the events pattern again, and it would make the timeline the party's diary. Recommendation: not in this slice. The timeline's leak test would grow a third list.
2. **Should the ledger have a starting balance?** A campaign that starts mid-story has gold already. Recommendation: no column. The first row is "Starting purse, 150 gp", which is what a GM would write in a notebook.
3. **Should a coin row split into gold, silver, and copper?** Recommendation: no. One unit, one label. A 5e table writes 0.5 for five silver, and a table on another system never asks.

## References

- `.ai/rules/models.md` — the gate, the clock's two halves, and the decision that followed them.
- `.ai/rules/entities.md` — the character fields sit outside the DM card so the owning player may edit them; the journal's visibility switch follows.
- `.ai/rules/campaigns.md` — a new campaign-scoped table joins the export in the same commit; an added key does not bump the version; people are never re-linked.
- `.ai/rules/api.md` — the API is the screens in JSON.
- `docs/plans/2026-09-11-feat-story-arcs-rewards-decisions-plan.md` — the last type added, and the decision log this ledger is shaped after.

## Implementation Results — 2026-09-11

Implemented in full. 16 new tests; the suite is 1349 tests, 1348 passing, 1 skipped, with Larastan clean and Pint clean.

### What shipped, against the plan

| Planned | Shipped |
|---|---|
| `EntityType::Journal`, author in `player_user_id` | As planned. `EntityPolicy::create()` takes the type; every caller passes it. The form strips the DM card's `player_user_id` and `is_pc` on a journal so a GM's edit keeps the author. |
| The author's visibility switch | As planned, outside the DM card, limited to Dm and Players in the form and in `EntityController::rules()`. Selected from an author is a 422. |
| The journals index | Newest first, with the author and the date on each card, and a **New journal** button for every member. |
| `campaigns.currency` | As planned, in settings and in the export. The model carries the same default the column does, because an instance a factory just made has not read the row back. |
| `ledger_entries`, one table, two kinds | As planned. `LedgerEntry::inventory()` returns a plain list rather than a Collection: the shape is the contract, and a Collection's value type is not covariant, so Larastan could not hold it. |
| `/ledger` | As planned: the purse, the pack, the form, the movements newest first, and a delete for the author or a GM. |
| The round trip | `ledger` is a new top-level section with both links remapped; `campaign.currency` is an added key. A journal's author is dropped with every other person column, as the plan said. |
| The vault | `journals/` with `author` in the front matter, and one `ledger.md` with the purse, the pack, and the movements. |

### Deviations

| Planned | Shipped | Why |
|---|---|---|
| Nothing about a private journal's look for its author | A badge, "You and the GM" or "The party", on the index and the page for the author | The browser pass from the player's seat showed two journals with no way to tell the private one apart. |
| A forged Item page id refused | Dropped silently, the row written without the link | The picker never offers a page the writer may not see, so an id for one is a forged request, and a forged request is downgraded silently, the dice log's rule. |

### Open questions, answered

1. No in-world date on a journal, and not on the timeline. Out, as recommended.
2. No starting balance column. The demo's first row is "Starting purse, 150 gp".
3. One unit. A 5e table writes 0.5 for five silver.

### Browser checks

Driven end to end on the seeded world at 834px as the player and 1400px as the GM:

- The ledger reads 110.00 gp in the purse and a signet and four torches in the pack from both seats. The player's rows carry a delete; the GM's starting purse does not, from the player's seat.
- The journals index offers the player **New journal**, lists both entries newest first with the author, and marks the private one.
- The journal form gives the player name, body, tags, details, an image, and the one switch, "Just me and the GM" or "The whole party".
- The private journal page reads "By Tobin Ashgrove" with the badge, and the GM opens the same page.
