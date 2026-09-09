---
title: "feat: Entity templates and a way back to an earlier body"
type: feat
date: 2026-09-09
status: implemented
brainstorm: docs/brainstorms/2026-09-02-demgem-campaign-manager-brainstorm.md
follows: docs/plans/2026-09-08-feat-api-tokens-discord-plan.md
---

# feat: Entity templates and a way back to an earlier body

## Overview

Slice 14 makes recurring prep easier and saved prose recoverable. A GM writes an NPC outline once, chooses it when creating the next character, and fills in the details. When a later save replaces a useful body, the GM can read the earlier text and restore it. Changes made through the API have the same history as changes made through the form.

| Feature | What it adds |
|---|---|
| Entity templates | Named, campaign-owned starting bodies, each for one entity type. GM roles create, edit, delete, and use them. |
| Body history | Previous saved bodies, with the time they were replaced and who replaced them. GM roles inspect and restore them. |
| Portability | Templates and history join JSON and archive exports and import into a new campaign. |
| API access | Template reads and writes, creation from a template, and history reads and restores through the existing token and campaign gates. |

Implemented on 2026-09-09. Normal browser login works after clearing site data. Implementation, affected automated checks, and the browser pass are complete; the user accepted the restore confirmation after Brave's automation stalled on the native dialog.

## Problem Statement

The original brainstorm lists entity templates and body version history as P2 work. The current entity form starts with a blank body, and `UpdateEntity` replaces the stored text without retaining it. Slice 13 gives scripts the same write action, making recovery useful for both browser and API edits.

History has a different disclosure boundary from the current entity. A GM might remove a secret from the body and then reveal the entity. Showing that entity's earlier bodies to players would reveal the removed secret. Even the player who owns a PC must not receive its historical bodies.

## Scope and Decisions

| Question | Decision |
|---|---|
| Template ownership | One campaign. No account-wide library, cross-campaign references, or shipped template catalogue. |
| Template contents | A name, an entity type, and a Markdown body. No DM notes, visibility, assignments, tags, relationships, media, or type-specific fields. |
| Applying a template | A starting body on a new entity. It never changes an existing entity and never links created entities back to a live template. |
| Replacing an unsaved draft | An explicit **Use template** action. If the draft body is nonempty, confirm replacement first. Selecting an option alone changes nothing. |
| History contents | The previous `body` only, including an empty body. This is not whole-entity undo or a general audit log. |
| History access | GM roles only, even on a player-owned PC or an entity visible to everyone. |
| Retention | Keep every recorded body. No automatic expiry, pruning, or individual revision deletion in this slice. Explain this in the history UI and README. |
| Initial history | No backfill and no extra copy on creation. The current body is the starting state; the first change preserves it. Older changes cannot be reconstructed. |
| Restore | Save the selected body's exact text through `UpdateEntity`, preserving the body it replaces. Other entity fields remain as they are. |
| Comparison UI | Current body and selected earlier body, stacked at narrow widths. Escaped source text first; no diff engine or new dependency. |
| Automatic link rewrites | Existing rename housekeeping remains unversioned. Saved historical bodies are immutable and are never rewritten when a target is renamed. |
| Concurrent editing | Serialize body writes against a freshly loaded entity. Keep existing last-save-wins behavior; history preserves the intervening body. No collaborative editing or conflict-resolution UI. |
| API deletion | Preserve the existing rule: template deletion is available on the confirmed web action only. No DELETE endpoint. |

The first release deliberately limits templates to the body. An NPC outline and a location outline need reusable headings and prompts; copying visibility or player assignments adds a disclosure decision to what should be a starting-text choice.

## Repository Findings

Versions checked with `composer show --direct`: Laravel 13.30.1, Livewire 4.4.3, Sanctum 4.3.3, Pest 5.1.3, and Laravel Pest plugin 5.0.1. No dependency change is required.

| Existing piece | Integration |
|---|---|
| `app/Actions/Entities/CreateEntity.php` | Already creates the entity, tags, and viewers in a transaction. Template defaults resolve before this action receives the final body. |
| `app/Actions/Entities/UpdateEntity.php` | Shared by the form and API; already transactional and updates only supplied keys. Record the displaced body here. |
| `app/Livewire/Entities/Form.php` | Body limit is 100,000 characters. Add the template picker only in create mode and preserve ordinary validation. |
| `app/Policies/EntityPolicy.php` | `update` also permits a player editing their PC. History therefore needs distinct GM-only `viewHistory` and `restoreBody` abilities. |
| `app/Actions/Mentions/RewriteWikiLinks.php` | Uses `saveQuietly()` to rewrite current text after a rename. An observer-only history implementation would miss these writes and obscure their different semantics. |
| `app/Observers/EntityObserver.php` | Maintains mentions and rename behavior. Leave it responsible for those derived records; do not add a second history recorder here. |
| `app/Actions/Campaigns/ImportCampaign.php` | Writes remapped entities directly with observers running. Import history explicitly; do not manufacture revisions during reconstruction. |
| `app/Actions/Campaigns/ReadCampaignFile.php` | Validates the document before writes and caps decoded input at 25 MiB. New sections must remain optional for older exports. |
| `tests/Feature/Campaigns/RoundTripTest.php` | Compares exported documents across import. Extend the fixture and assertions without excluding the new content. |
| `routes/web.php` | Generic entity routes use `/{type}`. Register template routes before these and avoid model-named route parameters. |

## Technical Approach

### Data

Two new tables, with the existing ULID, `BelongsToCampaign`, typed casts, factory, and relationship conventions. Inspect the live schema with Boost before creating migrations; users use integer IDs while campaigns and entities use ULIDs.

```text
entity_templates
  id                   ulid primary
  campaign_id          ulid -> campaigns, cascade on delete
  type                 string, EntityType cast
  name                 string(120)
  body                 text nullable
  timestamps
  index (campaign_id, type, name)

entity_body_revisions
  id                   ulid primary
  campaign_id          ulid -> campaigns, cascade on delete
  entity_id            ulid -> entities, cascade on delete
  body                 text nullable
  replaced_by          nullable user foreign key, null on user deletion
  replaced_by_name     string nullable, name captured when the body was replaced
  recorded_at          timestamp, cast to datetime
  index (entity_id, recorded_at, id)
```

`recorded_at` means when the old body was displaced, not when its author originally wrote it. UI copy says **Replaced by … at …**, avoiding invented authorship. Capture the acting user's name at that point; never accept attribution from a browser or API payload. After import, the name remains a historical label and `replaced_by` is null, not a guessed local account. A deleted account does not erase campaign history, consistent with retaining member names in campaign exports.

Template names need not be unique: they are labels, and pickers identify records by ID. Validate nonblank names up to 120 characters, a supported type, and a nullable body up to the existing 100,000-character limit. Changing a template's type changes where it is offered, with no effect on entities already created from it.

Revisions do not soft-delete individually. Soft-deleting an entity retains its revisions but ordinary scoped lookups make them inaccessible and omit them from export. Hard deletion of the entity or campaign cascades. Neither new model participates in entity search, autocomplete, the mention index, or player-facing counts.

### Templates

Add `EntityTemplate`, its policy, and focused actions under `app/Actions/Entities/`: `CreateEntityTemplate`, `UpdateEntityTemplate`, and `DeleteEntityTemplate`. Use `app/Livewire/Entities/Templates.php` for the campaign's management screen, linked from campaign settings for GM roles. Reuse existing form, button, confirmation, and Markdown editing components.

The management page lists templates by type and name, with pagination and an empty state. GM roles can create and edit one and confirm deletion. It uses `InteractsWithCampaign`, enters the campaign during mount, and authorizes every mutation again. A template ID is resolved within the active campaign, never with an unscoped lookup.

In the entity create form, offer only templates matching the route's entity type. **Use template** fetches the selected template again under the campaign and policy, then copies its body into the draft. After that the draft is ordinary editable text. Template edits or deletion do not change that draft or prevent its later save. A template deleted before it is applied returns a clear unavailable message without clearing the draft. Changing the selection alone never overwrites text.

For API creation, accept optional `template_id` on `POST /entities`. Resolve it within the campaign and verify that its type matches the requested entity type. When `body` is omitted, use the template's body; when `body` is explicitly supplied, including null or empty text, it wins. Strip `template_id` before calling `CreateEntity`. A wrong-campaign ID is 404; a type mismatch is a named 422 validation error. `template_id` on an existing entity PATCH is prohibited.

### Recording and Restoring Bodies

Inside `UpdateEntity`'s existing transaction, reload the target under campaign context with `lockForUpdate()` before comparing or filling fields. Use that fresh model throughout the save and return it to the caller. If `body` is absent or its normalized value is unchanged, record nothing. Normalize empty text to null consistently with the existing form and API; do not trim or reformat nonempty Markdown.

When the body changes, insert its previous value into `entity_body_revisions`, with the actor and current time, before saving the new value. Use a focused `RecordEntityBodyRevision` action if it keeps the transaction readable; it must not commit independently. A failure in either write rolls back both. A player saving their own PC generates history as usual but gains no history access.

Two sequential writes using stale model instances must retain both displaced states because each action reloads before comparing. This guarantees recovery for form/API body saves. Automatic rename rewrites remain existing housekeeping and do not become an audit trail of every database write. Add a regression covering this distinction, including a rename that rewrites the renamed entity's own body. Preserve existing mention behavior without recursively invoking `UpdateEntity` from the rename rewriter.

Add `RestoreEntityBody`, shared by the page and API. Resolve the entity normally, authorize `restoreBody`, and resolve the revision through that entity's revisions relationship in the active campaign. Pass only its `body` into `UpdateEntity`. Revisions belonging to another entity or campaign return 404. The selected revision remains unchanged, and the current body being replaced becomes another revision. Restoring text already current is a successful no-op.

The history UI is an on-demand `Entities\History` component on the entity page, mounted only for GM roles. It still checks membership and authorization itself on every request. List 25 revision summaries per page ordered by `recorded_at` descending and ID descending; fetch a full body only when selected. Show the current and earlier source text with escaped output, an explicit empty-body state, and **Restore this body** with confirmation that other fields are unaffected. Refresh the current entity body after restoration.

Historical wiki-link text stays exactly as saved. A restored old target name may now be unresolved; normal mention synchronization handles it as any other save. No silent rewriting of historical snapshots. If a rendered preview is added, it must use `MarkdownRenderer` and the existing viewer-aware wiki-link renderer.

### API

All paths below are under `/api/v1/campaigns/{campaign}` with the existing authentication, throttling, campaign membership, and scoped lookup behavior. Controllers live under `app/Http/Controllers/Api/V1`, with matching API Resources. Use the same policies and actions as the page.

| Method and path | Contract |
|---|---|
| `GET /entity-templates` | GM-only list, optional type filter, 50 per page. Summary fields omit the body. |
| `GET /entity-templates/{templateId}` | GM-only template details, including body. |
| `POST /entity-templates` | GM role and write key; create a template. |
| `PATCH /entity-templates/{templateId}` | GM role and write key; update permitted template fields. |
| `POST /entities` | Existing endpoint gains the optional creation-only `template_id` default. |
| `GET /entities/{entityId}/body-revisions` | GM-only history summaries, 25 per page; no body in list items. |
| `GET /entities/{entityId}/body-revisions/{revisionId}` | GM-only selected revision, including body, replacement time, and actor label. |
| `POST /entities/{entityId}/body-revisions/{revisionId}/restore` | GM role and write key; return the updated ordinary entity resource. |

No revision data or counts are added to ordinary player entity resources. A non-member gets 404, a member without the required GM role gets 403, missing credentials get 401, and a read-only key gets 403 on every new write. Forbidden fields such as campaign IDs and revision attribution are explicitly prohibited. Template and history pages do not introduce a second token or permission model.

### Export, Import, and Retention

Register `entity_templates` as top-level `entity_templates` in `ExportCampaign::SECTION_TABLES`. Register `entity_body_revisions` as a separate top-level `entity_body_revisions` section too: retaining all bodies should not make eager-loading one entity load an unbounded history. Stream both sections, joining revisions only to non-deleted entities of the exported campaign. Keep revision order deterministic.

Templates export ID, type, name, body, and timestamps. Revisions export ID, entity ID, body, `replaced_by_name`, and `recorded_at`; omit the local `replaced_by` account ID. Neither section exports email addresses. These additions keep format version 1 under the existing additive-format rule.

`ReadCampaignFile` treats missing sections as empty, validates IDs, types, timestamps, body limits, and revision entity references before any write, and adds counts to `ImportReport`. Refuse dangling revision references with a clear error, following the reader's existing reference-validation behavior. Do not truncate historical body text silently: an oversized revision or template body must produce an explicit validation error. Imported entity and revision IDs are freshly assigned through `IdMap`; all writes follow the existing `forceFill` convention.

Import templates and revisions explicitly in the same transaction as their new campaign, after entities exist. Preserve revision content, replacement names, and `recorded_at`; set local actor IDs to null. Do not synthesize a revision for the imported current body. Import preview copy explains that historical names are retained without reconnecting accounts. Empty sections from old exports need no loss warning.

The archive contains these sections in `campaign.json`. Its Markdown vault continues to represent current entity pages; it does not create historical copies that would contaminate Obsidian search and backlinks. State in archive documentation that templates and history are restored from the JSON, while Markdown contains current pages.

Retention is unlimited in the database, but the existing importer still has a 25 MiB document limit. History makes that limit easier to reach. Keep it as an explicit limitation of this slice, correct any size-error advice that falsely suggests the CLI bypasses the same reader, and never silently omit revisions to make an export smaller. Streaming imports, configurable pruning, and exports that intentionally omit history require separate work. Test round trips within the supported size and explicit rejection above it.

## Implementation Phases

### Phase 0: Templates and Portability

- [x] Inspect schema; create the template migration, model, policy, factory, and action classes using Artisan with `--no-interaction`.
- [x] Add template export, reader validation, importer remapping, report counts, and round-trip fixture in the same commit as the table.
- [x] Build the GM management screen, settings link, and create-form picker with explicit draft replacement confirmation.
- [x] Add template API reads/writes and optional template defaults to entity creation.

Tests: `tests/Feature/Entities/TemplatesTest.php`, `TemplateVisibilityTest.php`, and `tests/Feature/Api/TemplatesApiTest.php`. Cover reusable starting text, draft preservation, immutable copies, type filtering/mismatch, null override semantics, deletion, cross-campaign IDs, revoked membership, and absent template prose in player HTML, snapshots, and JSON. Extend the existing export/import and `ExportCoverageTest` coverage.

### Phase 1: History at the Write Boundary

- [x] Create the revision migration, model, factory, and relation; add its export/import section and round-trip coverage in the same commit.
- [x] Update `UpdateEntity` to reload and lock, compare normalized bodies, record the displaced body, and save atomically.
- [x] Add GM-only history policies and the shared restore action.
- [x] Cover normal creation, existing entities, API writes, player PC edits, and the import and automatic-rename exceptions.

Tests: `tests/Feature/Entities/BodyHistoryTest.php`. Assert observable behavior for A → B → C, absent/unchanged body, empty body, metadata-only saves, restoration preserving C, repeated restore, rollback, and sequential edits through stale instances. Add a PostgreSQL integration case for overlapping body writes; SQLite tests alone do not demonstrate row-lock behavior. Verify that soft-deleted entities are inaccessible and omitted from export, force deletion cascades, and deleted actors leave history readable.

### Phase 2: History on Screen and Through the API

- [x] Add the paginated GM history component, selected-body comparison, restore confirmation, and entity-body refresh.
- [x] Add history API Resources, read endpoints, and write-protected restore endpoint.
- [x] Keep all history metadata and bodies out of player-facing resources, HTML, snapshots, search, and autocomplete.

Tests: `tests/Feature/Entities/BodyHistoryVisibilityTest.php` and `tests/Feature/Api/BodyHistoryApiTest.php`. Use a distinctive secret removed before revealing the entity. Assert it never reaches a player, including the PC owner, and test direct revision requests, another entity's revision ID, a foreign campaign ID, a revoked member, and a read-only key. Exercise malicious Markdown in the comparison view and verify escaped output. Use existing `asKey()` and `withoutKey()` helpers for API tests.

### Phase 3: Round Trip and Product Pass

- [x] Extend the demo seeder with useful templates and a small body history; keep any new factory/seeder support within existing directories.
- [x] Prove JSON and archive round trips preserve template bodies, revision bodies, ordering timestamps, empty values, and actor labels over two successive imports without extra revisions.
- [x] Assert new IDs separately; do not add revision content or `recorded_at` to the round-trip comparison's ignored fields.
- [x] Verify old exports import with empty new sections and invalid references or oversized documents fail before writes.
- [x] Update README status, API table, history retention/limitations, and archive contents after implementation.
- [x] Browser pass at 1024px and 768px, dark and light: template editing, draft replacement, empty history, pagination, long source lines, comparison, restore, and player absence. The user accepted the native restore confirmation.
- [x] Record settled implementation rules through Boost `record-rule`; do not turn unimplemented proposals into shared rules now.

## Acceptance Criteria

- [x] A GM creates, edits, deletes, and reuses a campaign's typed body templates.
- [x] Selecting a template does not replace draft text until the GM explicitly applies it, with confirmation for a nonempty draft.
- [x] Browser and API entity creation produce independent copies with identical default/override behavior.
- [x] Every changed body saved through the form or API retains the previous body atomically; unchanged text and unrelated edits add no revision.
- [x] A GM can inspect and restore an earlier body without reverting other fields or destroying the displaced text.
- [x] Players, including PC owners, receive no templates or history content or metadata through any new surface.
- [x] Every direct lookup and mutation enforces campaign membership, role, and parent entity boundaries.
- [x] Templates and revisions survive supported JSON/archive round trips with fresh IDs and preserved content, time, and actor labels.
- [x] Existing imports, wiki-link rewrites, entity editing, and visibility behavior remain covered and passing.
- [x] No new dependency, entity type, base directory, or UI kit component is required.

## Verification

Generate Pest tests with `php artisan make:test --pest` using names without the `Feature/` prefix. Start with the narrow files for each phase and rerun any test immediately after changing it. Reuse the existing entity creation, PC editing, API entity, wiki-link rename, reader, export coverage, and round-trip tests for regressions.

After PHP changes, run `vendor/bin/pint --dirty --format agent` and `composer analyse`. Run `npm run build` for the frontend changes. Validate PostgreSQL-specific locking and constraints on PostgreSQL, in addition to the normal SQLite feature tests. Once feature tests pass, ask the user to run `php artisan test --compact` for the complete suite, following the project instructions. Actual automated and browser results are recorded below.

## References

- Original scope: [campaign-manager brainstorm](../brainstorms/2026-09-02-demgem-campaign-manager-brainstorm.md), Knowledge Base P2 rows for templates and body history.
- Prior slice: [API tokens and Discord](2026-09-08-feat-api-tokens-discord-plan.md).
- Portability: [JSON importer](2026-09-04-feat-json-importer-plan.md) and [campaign archive](2026-09-04-feat-campaign-archive-plan.md).
- Shared rules: `.ai/rules/actions.md`, `api.md`, `campaigns.md`, `entities.md`, `feature-api.md`, `feature-campaigns.md`, `livewire.md`, `migrations.md`, `models.md`, `observers.md`, `routes.md`, `tests.md`, and `views.md`.
- Laravel 13 documentation consulted through Boost: [pessimistic locking](https://laravel.com/docs/13.x/queries#pessimistic-locking), [observers](https://laravel.com/docs/13.x/eloquent#observers), and [foreign-key constraints](https://laravel.com/docs/13.x/migrations#foreign-key-constraints).


## Implementation Results — 2026-09-09

- Added the two tables, factories, GM-only template management, create-form application, on-demand body history, restoration, and API endpoints. The demo seeder now includes two outlines and a displaced body.
- `ApplyEntityTemplate` shares the campaign/type/policy checks between the form and API. It returns starting text, and explicit API body overrides still win.
- `UpdateEntity` records the displaced body under a row lock in its existing transaction. A separate PostgreSQL process test observes the second writer waiting for that lock, then verifies that both displaced bodies survive.
- JSON and archives carry both new sections. Historical body whitespace, replacement dates, and actor labels survive two imports. Accounts are not relinked, and the Markdown vault stays current-only.
- Fixed request/import trimming of entity Markdown and corrected the oversized-import message. Both import entry points share the same reader and size limit.
- Fixed an existing export assertion that failed when a generated member name contained an apostrophe: it now checks decoded member names and deliberately exercises an apostrophe.
- Affected-suite run: SQLite, 378 passed and one PostgreSQL-only test skipped; PostgreSQL, 380 passed including the driver check. A subsequent focused test covers the unavailable message when the last selected template is deleted.
- Pint, Larastan level 6, and the production Vite build passed. The two migrations were applied to the local development database; PostgreSQL tests used a separate `demgem_slice14_test` database.
- Shared rules recorded with Boost for history transactions, portable sections, template application, and Markdown whitespace.

### Browser checks

After clearing `demgem.test` site data, normal Brave login succeeded with the existing demo credentials. Browser checks passed for template creation/editing, selection preserving a draft, explicit draft replacement, note creation from a template, the empty-history message, recording a changed body, and inspecting the previous body. Template management and history were visually checked at 1024px and 768px in light and dark modes; long source text wraps without horizontal page overflow.

The restore action opened its native confirmation, but browser automation timed out while accepting it. The user clicked OK; the earlier body was restored and the displaced body became a second revision, verified in the database and on a fresh page. A temporary 26-revision history showed 25 results on page one and the oldest body on page two, which remained inspectable.

Signed in as the seeded player, the revealed test note showed its current body without history controls or historical prose, and direct template access returned 403. Restored the original demo login, dark theme, and normal browser size afterward. Removed the temporary note, its revisions, and the template, and verified all three were absent from the database.

The complete application suite passed on SQLite (1,180 passed, one PostgreSQL-only test skipped) and PostgreSQL (1,181 passed). Final review caught duplicate template creation in the demo map and handout seed helpers. Removed those calls and added an assertion that the demo supplies exactly two templates; the focused seeder tests passed afterward, along with Pint and Larastan.
