---
title: "feat: The importer, and the four things it cannot carry"
type: feat
date: 2026-09-04
brainstorm: docs/brainstorms/2026-09-02-demgem-campaign-manager-brainstorm.md
follows: docs/plans/2026-09-03-feat-handouts-clocks-plan.md
---

# feat: The importer, and the four things it cannot carry

## Overview

Slice 4 shipped the export and called it "the open source promise, kept". It is half kept. Data that leaves and cannot come back is a door, not a gate.

| Feature | What it adds |
|---|---|
| Read a campaign file | `demgem.campaign` version 1, validated whole before a single row is written. |
| Rebuild it | Every id remapped, every cross-reference remapped with it, in one transaction. |
| Say what did not come | A report the GM reads before they commit, and again afterwards. |
| A screen | `/campaigns/import`, an upload, and the report. |
| A command | `php artisan demgem:import`, for a self-hoster with a 40MB file and no patience for a browser. |

When this slice is done a GM exports a campaign from one demgem, imports it into another, and gets their world back with an honest list of what stayed behind.

**On scope.** Phases 0 to 2 are a release: a GM can restore a campaign through the browser. Phase 3 is a second and it is small. Phase 4 is the round trip and the polish, and it is not cuttable — the round-trip test is the only thing that proves the two halves agree.

## Problem Statement

**The export has been complete and unusable for four slices.** `ExportCampaign` streams every campaign-scoped table, `ExportCoverageTest` reads the schema and fails when a new table is not accounted for, and slices 5, 6 and 7 each added to it without being asked twice. The file is good. Nothing reads it.

That matters three ways.

- **A self-hoster cannot move.** The whole pitch of an MIT-licensed, Docker-composed campaign manager is that the GM owns the install. Owning an install you cannot migrate off is owning a hostage.
- **A backup that cannot be restored is not a backup.** The export button currently produces a file whose only use is reading it by hand.
- **The format has no consumer, so nothing keeps it honest.** `version: 1` has never been read by anything. A format with no reader drifts, and the drift is invisible until somebody needs it.

**And the hard half is not the parsing.** It is that a campaign file is a graph. Eleven columns point at other rows: `parent_id`, `giver_entity_id`, `target_entity_id`, `entity_id`, `game_session_id`, `revealed_in_session_id`, `completed_in_session_id`, `active_combatant_id`, `nested_table_id`, `encounter_id`, `player_user_id`. Ten of them point inside the file. One points at a person who is not in it.

## Proposed Solution

**An import creates a campaign. It never updates one.** This is the decision the whole slice rests on, and it is a refusal: there is no merge, no "import into this campaign", and no conflict resolution. Merging two campaigns is a different feature with a different hard problem — what happens when both sides changed the same NPC — and it is the one that loses data. Creating is total: it either builds a whole campaign or it builds nothing.

That makes the transaction boundary obvious and the failure mode boring.

**Every id is remapped, always.** A fresh ULID for every row, and a map from the file's id to the new one, applied to every reference as the row is written. Never reuse an id from the file, even when nothing collides: reuse works until a GM imports their own export back into the install it came from, and then it corrupts the campaign it was meant to restore.

**The file is validated whole, then written whole.** Two passes. The first reads, checks, counts, and resolves every reference against the file's own contents without touching the database. The second writes, inside one transaction. A file that fails the first pass writes nothing and says why; a file that passes cannot fail the second for anything but a database error.

**Nothing is ever made more visible than the file says.** Every ambiguity resolves towards hidden. This is the rule that decides three of the four losses below, and it is not negotiable in review.

## The four things it cannot carry

These are the slice, honestly. Each is a decision, not an oversight, and the GM sees all four in the report before they press the button.

### 1. The files

The export carries a URL and the facts about each image, never the bytes. **The importer does not fetch those URLs**, and the reason is security rather than effort: fetching a URL out of an uploaded file is a request the server makes to wherever an attacker wrote, from inside the network the server lives in. That is server-side request forgery, and no amount of scheme checking makes it a good idea in a self-hosted app whose network the project cannot see.

So an imported campaign has no images, and the report counts them: "12 files could not come across." The Markdown-and-zip export in the brainstorm's P2 row is the one that carries bytes, and it is the slice that fixes this.

### 2. The people

The export carries names and roles, and deliberately no email addresses: an export file gets shared, and the party's addresses are not the GM's to hand around. So there is nothing to match a member against.

**The importer makes exactly one member: the importing user, as Owner.** The report lists the names from the file so the GM knows who to invite, and the invite flow is the one they used the first time.

### 3. The Selected lists

`Visibility::Selected` means "these named players", and the names are user ids from another install. They cannot be re-linked for the same reason the members cannot.

**A Selected entity imports as `Dm`**, and its viewer list is dropped. That is the hidden direction, which is the rule. Importing it as `Players` would take a thing the GM showed to two people and show it to the table, which is exactly the mistake the app has spent three slices refusing to make. The report says how many.

### 4. The dice log

`dice_rolls.user_id` is not nullable, and the file has no way to say who rolled. Attributing every historic roll to the importing user would be a fabrication written into the database, and a dice log whose every entry names the wrong person is worse than no dice log.

**The importer skips the dice rolls** and counts them in the report. A restored campaign loses session noise, not the campaign.

## Technical Approach

### No new dependency

`json_decode` reads the file, with a size cap. A streaming JSON parser would let the importer scale past memory, and it is a dependency for a problem nobody has: the seeded demo campaign is 40KB, and a campaign with a year of Thursdays in it is not close to the cap.

**The cap is 25MB**, checked before decode, with the reason in the validation message. `json_decode` on a 25MB file peaks around ten times that in PHP arrays, which is inside the default memory limit and nowhere near it for a real campaign.

### The shape

```
app/Actions/Campaigns/
    ImportCampaign.php      -- the writer. One transaction, one campaign.
    ReadCampaignFile.php    -- the reader. Parses, validates, and reports.
    ImportReport.php        -- what came, what did not, and why.
    IdMap.php               -- old id to new id, and the lookup that fails loudly.
```

`ReadCampaignFile` never touches the database. That is what makes the preview honest and the test suite fast: most of the interesting cases are malformed files, and none of them need a campaign.

### The reader

```php
// A file is a claim. Every string is truncated to its column, every enum is checked
// against its cases, and every reference must resolve inside this same file.
public function handle(string $json): ReadResult
```

- **Format and version first.** `format` must be `demgem.campaign` and `version` must be `1`. Anything else is a clean refusal naming what it found, because a version 2 file in a version 1 importer is the one case where guessing is worse than stopping.
- **Every enum through `tryFrom`.** An unknown `visibility`, `type`, `status`, `role` or `ruleset` fails the file rather than defaulting, because a default here is a guess about what a GM meant.
- **Every reference resolved against the file.** A `parent_id` that names no entity in the file is an error, not a null. The exception is the person columns, which are expected not to resolve and are documented as such.
- **Every string truncated, not rejected.** A 200-character name in a 120-character column is a file from a future version or a hand-edited one; truncating keeps the campaign and loses a few words. This is the one place the reader is lenient, and it counts what it truncated.
- **Cycles are checked.** `parent_id` chains and `nested_table_id` chains are walked once. `Entity::ancestors()` has a 20-level guard, which means a cycle imported today is a bug a GM meets later; the reader refuses it now.

### The writer

```php
DB::transaction(function () {
    // Order matters exactly as much as the graph says it does. Entities before the
    // things that point at them, sessions before the rows that record a reveal.
});
```

- **Campaign first**, through `CreateCampaign`, so the importer gets the owner membership for free and the one path that makes a campaign stays one path.
- **Entities in two passes.** Write every entity with its self-references null, then fill `parent_id` and `giver_entity_id` once every id exists. A single pass would need a topological sort of a graph that may be a forest, and two passes is the same work written plainly.
- **Observers stay on.** The plan first said to silence them and rebuild the mention index once at the end, on the reasoning that an entity written before the page it links to would resolve against a campaign that does not hold it yet. Reading `EntityObserver` says otherwise: that entity leaves an unresolved mention row, and `ResolveMentionsFor` points the row at its target the moment the target is created. The index converges on its own, so silencing the observers would buy a little speed and cost the guarantee. It also cannot be done cheaply: `withoutEvents` swaps the global dispatcher, which would take `HasUlids` with it.
- **Scout quiet, then one index.** `Entity::withoutSyncingToSearch()` around the writes, and `Entity::query()->...->searchable()` at the end.
- **`created_by` and `updated_by` are the importing user.** These are this install's audit columns, and the row genuinely entered this install by that user's hand. The file's own authorship is gone with the people.

### The report

One value object, rendered twice: on the confirm screen before anything is written, and on the campaign afterwards.

```
Counts:      12 entities, 3 sessions, 2 encounters, 5 clocks, 2 handouts
Not carried: 12 files, 4 members, 3 selected-visibility lists, 41 dice rolls
Adjusted:    2 names truncated to 120 characters
```

The "not carried" list is the point of the whole screen. A GM who imports a backup and finds their images missing three weeks later has been told something they did not read; a GM who is told before they press the button has made a decision.

### What the round trip proves

`tests/Feature/Campaigns/RoundTripTest.php` is the centrepiece: seed a campaign, export it, import it, and compare the two campaigns section by section, driven by `ExportCampaign::SECTION_TABLES` so a new table joins the comparison automatically.

The comparison is on the export of both, not on the rows: export the original, import it, export the copy, and the two documents must be equal after ids, timestamps, and the four documented losses are removed. That way the test states the promise in the same language the feature does, and it fails the day the export grows a field the importer ignores.

## Decisions resolved

| Question | Decision |
|---|---|
| Import into an existing campaign | Never. An import creates a campaign. Merging is a different feature and the dangerous one. |
| Reuse the file's ids | Never. Fresh ULIDs and a remap, because reuse breaks exactly when a GM restores into the install the file came from. |
| Validate then write, or write and roll back | Validate whole, then write whole. A rejection must cost nothing and explain itself. |
| Files | Not fetched. Fetching a URL from an uploaded file is SSRF. Counted in the report; the zip export is the fix. |
| Members | One: the importing user, as Owner. The file's names are listed so the GM can invite them. |
| `Visibility::Selected` | Imports as `Dm`, list dropped. Ambiguity resolves towards hidden, always. |
| Dice rolls | Skipped. `user_id` is not nullable and inventing a roller writes a lie into the database. |
| `created_by` / `updated_by` | The importing user. They are this install's audit columns. |
| Unknown enum value | Refuse the file. A default is a guess about what a GM meant. |
| Over-long string | Truncate and count it. This is the one lenient rule. |
| A reference that resolves to nothing | Refuse the file, except the person columns, which are expected to. |
| Version mismatch | Refuse, naming the version found. |
| File size | 25MB cap, checked before decode. |
| A streaming parser | No. `json_decode` and a cap; no dependency for a problem nobody has. |
| New tables | None. |
| New kit components | None expected. |

## Implementation Phases

Each phase ends with a green suite. Phases 0 to 2 are a release.

### Phase 0: The reader

Deliverables:
- `ReadCampaignFile`, `ImportReport`, `IdMap`, and a `ReadResult` carrying either a report or a list of errors.
- Format, version, size, enum, reference, cycle and truncation rules, all without a database.

Tests: `tests/Feature/Campaigns/ReadCampaignFileTest.php` — a good file reads and reports; a wrong `format`, a wrong `version`, an over-size file, an unknown enum, a dangling `parent_id`, and a `parent_id` cycle each fail with a message naming the problem; an over-long name truncates and is counted.

Success: a GM's file is understood, and a bad one is refused in a sentence they can act on.

### Phase 1: The writer

Deliverables:
- `ImportCampaign`, one transaction, the two-pass entity write, Scout deferred to one chunked index at the end, and `forceFill` rather than `create` because `id` is in no model's fillable list.
- The four losses applied and counted.

Tests: `tests/Feature/Campaigns/ImportCampaignTest.php` — a campaign is built with every section; ids are new, and every cross-reference points inside the new campaign; the importer is the only member and is Owner; Selected became Dm with no viewers; no dice rolls; no media; a database failure mid-write leaves no campaign.

Success: a file becomes a campaign, and nothing in it points anywhere it should not.

### Phase 2: The screen

Deliverables:
- `App\Livewire\Campaigns\Import` at `/campaigns/import`, an upload, the report, a confirm, and a redirect to the new campaign.
- The "not carried" block, worded so a GM understands each of the four before they commit.
- The campaigns index gains an Import button beside Create.

Tests: `tests/Feature/Campaigns/ImportScreenTest.php` — an upload previews without writing; the confirm writes and redirects; a bad file shows its error and writes nothing; a guest gets the login page.

Success: a GM restores a backup without reading any documentation.

### Phase 3: The command

Deliverables:
- `php artisan demgem:import {file} --user=email`, over the same two actions.
- The report printed as a table, and a non-zero exit on refusal.

Tests: `tests/Feature/Campaigns/ImportCommandTest.php` — the command imports and prints the counts; a missing file, an unknown user and a bad document each exit non-zero with a message.

Success: a self-hoster moves a 40MB campaign between installs over ssh.

### Phase 4: The round trip and the polish

- `RoundTripTest`: export, import, export, compare, driven by `SECTION_TABLES`.
- Empty states and the error wording pass.
- The tablet pass at 1024px and 768px, dark and light.
- Record the rules: an import creates and never updates; ids are always remapped; ambiguity resolves towards hidden; do not fetch a URL from an uploaded file.
- Pint, Larastan, the full suite, and `npm run build`.

Success: the export and the importer agree, and a test says so in the same words the plan does.

## Alternative Approaches Considered

- **Merge into an existing campaign.** The obvious "restore" shape, and what a GM might expect from a backup. Rejected: it needs a conflict rule for every row in the graph, and the wrong rule silently overwrites a year of notes. Creating a second campaign and deleting the first is a GM's decision to make, with both in front of them.
- **Reuse the exported ids.** Cheaper, and it makes references trivial. Rejected: it works until the first restore into the install the file came from, which is the most likely restore there is.
- **Fetch the media URLs.** It would make the round trip complete, which is genuinely tempting. Rejected on security: it is an outbound request to an address inside an untrusted file. Revisit when the export carries bytes in a zip, which is where this belongs anyway.
- **Import the dice log against the importing user.** Keeps a section that would otherwise vanish. Rejected: a log that names the wrong person for every roll is worse than an absent one, and the alternative is a migration and an "unknown roller" rendering in three places.
- **Import Selected as Players.** Keeps the entity visible to the party, which is closer to what the GM had. Rejected: it widens visibility, which is the one direction this app never moves without being asked.
- **A streaming JSON parser.** Correct for a file bigger than memory. Rejected: a dependency for a size nobody has, and the cap says so out loud.
- **Write first, validate by rolling back.** Fewer passes, and the database does the checking. Rejected: the error messages become database errors, and a GM cannot act on a foreign key violation.

## Acceptance Criteria

### Functional

- [ ] A GM uploads a `demgem.campaign` version 1 file and sees a report before anything is written.
- [ ] Confirming builds a campaign with every section the file holds, and redirects to it.
- [ ] Every id in the new campaign is new, and every reference points inside it.
- [ ] The importing user is the only member, as Owner, and the file's other members are listed for inviting.
- [ ] A Selected entity arrives as GM-only, with no viewers, and is counted.
- [ ] The dice log and the media are skipped and counted.
- [ ] A wrong format, a wrong version, an unknown enum, a dangling reference, a cycle, and an over-size file are each refused with a message naming the problem.
- [ ] Nothing is written when a file is refused.
- [ ] `php artisan demgem:import` does the same from a terminal.
- [ ] Export, import, export: the two documents match once ids, timestamps and the four losses are removed.

### Non-functional

- [ ] **An import never makes anything more visible than the file says.**
- [ ] **The importer makes no outbound HTTP request, whatever the file contains.**
- [ ] A failed write leaves no campaign, no entities, and no tags.
- [ ] Wiki links resolve after the import, including a page written before the page it links to.
- [ ] Imported entities are searchable.
- [ ] `Model::shouldBeStrict()` is on.
- [ ] A player cannot import into somebody else's campaign, because an import has no campaign to point at.
- [ ] The import screen works at 1024px and 768px, dark and light, with no sideways scroll.

### Quality gates

- [ ] Pest suite green on SQLite locally and on PostgreSQL in CI.
- [ ] Larastan level 6 clean. Pint clean.
- [ ] No new PHP or JavaScript dependency.
- [ ] `RoundTripTest` is driven by `ExportCampaign::SECTION_TABLES`, so a new section joins it without being remembered.
- [ ] `npm run build` clean.

## Dependencies & Risks

| Risk | Mitigation |
|---|---|
| An import widens visibility | One rule, stated in the plan and recorded in `.ai/rules`: ambiguity resolves towards hidden. Selected becomes Dm, and a test names it. |
| A crafted file makes the server fetch a URL | The importer never fetches. There is no code path that takes a URL from the file. |
| A crafted file exhausts memory | 25MB cap checked before decode, and the message says why. |
| A half-written campaign survives a failure | One transaction, and a test that fails a write mid-import and asserts nothing remains. |
| The export grows a field the importer ignores | `RoundTripTest` compares documents rather than rows, so a new field appears as a difference. |
| Ids collide on a restore into the source install | Every id is remapped. There is no path that reuses one. |
| The four losses surprise a GM | They are on the confirm screen before the button, not in a changelog. |
| The slice runs long | Phase 3 is small and separable; Phase 0 is testable with no database at all. |

## Future Considerations

- **The zip export, with the bytes in it.** The brainstorm's Markdown export row, and the thing that closes loss 1.
- **Invite links in the report.** The importer knows the names it could not link; offering to create invites there is a small, obvious next step.
- **A nullable `dice_rolls.user_id`.** With an "unknown roller" rendering, the log could survive an import. It is a migration and three rendering branches.
- **Version 2 and a migration path.** The moment the format changes, the importer needs to upgrade a version 1 document rather than refuse it.
- **Import from Obsidian, Kanka, World Anvil.** All three are this reader with a different front half.

## References

### Internal

- Brainstorm: `docs/brainstorms/2026-09-02-demgem-campaign-manager-brainstorm.md`, the "Portability and integrations" table
- Slice 4 plan: `docs/plans/2026-09-03-feat-player-view-export-docker-plan.md` — the export, its versioning, and why members carry no email
- Slice 7 plan: `docs/plans/2026-09-03-feat-handouts-clocks-plan.md` — the visibility rules an import must not undo
- Patterns to copy: `app/Actions/Campaigns/ExportCampaign.php` (the section map this mirrors), `app/Actions/Campaigns/CreateCampaign.php`, `tests/Feature/Campaigns/ExportCoverageTest.php` (a test that reads the schema rather than trusting anybody)
- Project rules: `.ai/rules/models.md`, `.ai/rules/migrations.md`, `.ai/rules/tests.md`

### External

- Laravel database transactions: https://laravel.com/docs/13.x/database#database-transactions
- Scout, pausing indexing in bulk: https://laravel.com/docs/13.x/scout#pausing-indexing
- OWASP on server-side request forgery: https://owasp.org/www-community/attacks/Server_Side_Request_Forgery
