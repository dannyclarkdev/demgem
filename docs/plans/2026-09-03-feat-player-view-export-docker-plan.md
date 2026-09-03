---
title: "feat: The player view, the character record, JSON export, and Docker"
type: feat
date: 2026-09-03
brainstorm: docs/brainstorms/2026-09-02-demgem-campaign-manager-brainstorm.md
follows: docs/plans/2026-09-03-feat-quests-tracker-dice-tables-plan.md
---

# feat: The player view, the character record, JSON export, and Docker

## Overview

This is MVP slice 4, and it is the last one. Slice 1 built the world, slice 2 built the loop, and slice 3 built the tools the GM reaches for during play. All three built for the person running the game. This slice finishes the other half of the promise: the four people sitting opposite the GM, the data they all put in, and the machine somebody else wants to run it on.

| Feature | What it adds |
|---|---|
| The tablet pass | The two acceptance boxes slices 2 and 3 both left open. Prep, Run, the tracker, the drawer, and the quest page at 1024px and 768px, dark and light. |
| The character record | A class, a level, and a link to an external sheet on a character entity, editable by the player who owns the PC. A party roster on the dashboard and a party filter on the character index. |
| The story so far | One page of published recaps in order, the thing a player opens between games. |
| JSON export | A streamed, versioned export of a whole campaign, GM only. The open source promise, kept. |
| Docker Compose | app, postgres, redis, and a queue worker. A self-hoster runs `docker compose up -d` and registers an account. |
| Custom fields | The brainstorm's "custom key-value attributes" line, which no slice built. A JSON column, an editor row, and a definition list. |

When this slice is done, demgem is the product the brainstorm described: a GM preps, runs, and recaps a game; a player reads the story, tracks the quests, and keeps their character; anyone can run the whole thing on their own box and take their data out of it whenever they want.

**On scope.** There are two release boundaries. Phases 0 to 2 are the player-facing release. Phases 3 and 4 are the self-hosting release. Phase 5 is the custom fields, and it is cuttable: cut it and the MVP still ships, with the brainstorm line moved to P2 in the same commit that says so. Do not cut a phase in half.

**This slice adds no tables.** It adds four columns to `entities`, one Livewire page, one controller, and a Docker directory. That is worth saying up front, because three slices of schema growth make the next one feel inevitable, and this one is not.

## Problem Statement

demgem is a finished GM tool and an unfinished player one. A player who logs in today gets a dashboard, a list of entities the GM revealed, a session list, and a quest log. Nothing on any of those pages tells them what happened last time. The recap exists — slice 2 built it, and the GM publishes it on purpose — but it is one panel on one session page, and reading three months of story means opening eleven pages in order and remembering which ones they already read. The party's own characters are worse: a PC is a name, a Markdown body, and a picture. The two facts a player states at every table, their class and their level, live nowhere, and the character sheet they actually play from is a URL they paste into Discord every week.

The second gap is the promise. The README says demgem is open source and self-hostable, the brainstorm calls a JSON export "the open source promise: your data leaves with you", and neither is true yet. There is no export, so a campaign that goes into demgem stays in demgem. And there is no Docker image, so "self-hostable" means PHP 8.4, Node 20, PostgreSQL 17, seven commands, and a person who already knows Laravel. That is not a product anybody else can run.

The third gap is small, dull, and two slices old. Both slice 2 and slice 3 close with the same unticked box: the screens work at 1024px and 768px, in dark and light, with 16px body text and no hover-only controls. The brainstorm puts "tablet-friendly layout" in the MVP and gives the reason in five words: DMs run games from a tablet. Slice 3 wrote a whole drawer rather than a third column to protect that promise, then shipped without checking it.

## Proposed Solution

**The player view is not a new route tree.** It is the screens that already exist, finished. Every list in the app is already filtered by role — `Entity::visibleTo()`, `GameSession::visibleTo()`, the sidebar composer's GM-only branch — and a parallel `/player/...` tree would double the number of places a leak can happen, in an app whose hardest tests are all leak tests. So the dashboard grows a party roster, the character index grows a party filter, the character page grows a record, and one new reading surface joins them at `/story`. A player's home is the campaign dashboard, and it always was.

**The character record is three typed columns, not a child table and not free-form fields.** A class, a level, and a sheet link are one-to-one with the row, they are three of the four things every system in the world agrees a character has, and the fourth (the stat block) belongs to a ruleset module. A player who owns the PC may edit them, because `EntityPolicy::update()` already says so and nothing about a class or a level is GM business.

**The export streams and it is versioned.** `response()->streamJson()` with cursors underneath means a campaign of any size costs flat memory and starts downloading immediately, with no queue, no job, and no email. The envelope carries a format name and a version number so that the importer this slice does not build has something to read.

**Docker is one runtime container, not four.** FrankenPHP serves the app and terminates HTTP by itself, so the stack is app, postgres, redis, and a queue worker, with one Dockerfile and no nginx configuration for a self-hoster to debug at midnight.

## Technical Approach

### What slices 1 to 3 give us for free

Check these before writing anything new:

| Piece | Reuse |
|---|---|
| `Entity::visibleTo()` / `isVisibleTo()` | The party roster, the party filter, and every entity in the export go through it. No new visibility logic exists in this slice. |
| `GameSession::isRecapVisibleTo()` | Slice 2 already wrote the exact rule the story page needs, drafts and all. Call it; do not restate it. |
| `MarkdownRenderer` + `WikiLinkRenderer::for()` | The story page renders each recap exactly as `Sessions\Show` does, with the viewer and the role passed in. |
| `InteractsWithCampaign` | The story page is a page, so the trait plus `enterCampaign()` in `mount()`. No nested component in this slice needs it, because this slice adds none. |
| `EntityPolicy::update()` / `updateDmFields()` | The split this slice needs already exists: a player edits their own PC, and only a GM touches the DM fields. The three new columns join the first group, not the second. |
| `CampaignPolicy` | The export ability is a fifth line beside `useGmTools()`, in the same shape. |
| `Entities\Form` | The DM-field guard, the per-type guard (`isQuest()`), and the `prohibited` pattern all exist. The character fields copy the quest fields, one guard higher. |
| `Entities\Index` `#[Url]` filters | The party filter is a fifth one beside `search`, `tag`, `visibility`, and `questStatus`. |
| `SidebarComposer` | One nav entry and no new count. Story needs no badge; the session count already tells that story. |
| `x-ui.*` kit | **Budget: zero new components.** Card, badge, field, input, select, button, empty-state, page-header, and tabs cover every screen here. Anything new needs a reason written down. |
| `DemoCampaignSeeder` | Extend it again. A demo campaign with no published recaps makes the story page look broken rather than empty. |
| `.github/workflows/ci.yml` | The Postgres job stays as it is. The Docker job is a second job in the same file. |

### Phase 0 first: the tablet pass, and the plan debt

This is a design phase with no schema and no new tests, and it is first because it is two slices old and because everything after it adds screens that would inherit the same faults.

**The viewports.** 1024 x 768 and 768 x 1024, in dark and in light. Herd serves the app at `http://demgem.test`, and `DemoCampaignSeeder` fills it. Use the browser tools, resize, and look at every screen in this list:

- Run, with an active encounter, the tracker, the quest panel, live notes, and the drawer open on both tabs.
- Prep, with all four entity buckets full, scenes, and secrets.
- The quest page with objectives, a giver, rewards, and backlinks.
- Encounters index and page, tables index and page.
- Sessions index, session page, entity index, entity page, entity form, dashboard, members, search.

**The rules to hold**, from slice 2 and repeated by slice 3:

1. Body text is at least 16px. No 12px prose anywhere, including the dice log and the tracker.
2. No control is hover-only. A tablet has no hover, so a row's edit and delete controls are always present or they are in a menu that opens on tap.
3. Tap targets are at least 44px on their short side.
4. The page body never scrolls sideways. A wide table scrolls inside its own container.
5. Nothing important sits under the drawer button at the bottom right.

**The plan debt.** Slice 2's acceptance list has 28 unticked boxes and the work shipped; slice 3 has three. Tick a line only when a named test or a screen you just looked at proves it. A line that fails is a bug this slice fixes, not a box to leave open. Two of them are checks rather than tests:

- Slice 3, "a second GM device shows a round change within 15 seconds": two browser tabs on the same encounter, advance the turn in one, watch the other.
- Both slices, the tablet box: this phase.

### The data model

No new tables. Four nullable columns join `entities`:

| Column | Type | Notes |
|---|---|---|
| `character_class` | string 60, nullable | Named `character_class`, not `class`. `class` is a reserved word in enough dialects to be a nuisance and reads badly next to PHP's keyword in every model method that touches it. |
| `level` | unsigned small integer, nullable | Validated 1 to 100. 5e stops at 20; the core is system-agnostic and other systems do not. |
| `sheet_url` | string 2048, nullable | An external character sheet. See the safety note below; this is the only user-supplied URL in the app rendered as an `href` outside Markdown. |
| `custom_fields` | json, nullable | Phase 5. **Not** named `attributes`; see the decision below. |

No new index. The three character columns are read on a row the query already found by `(campaign_id, type)` or by primary key, and the party roster adds `where is_pc = true` to a list that a campaign keeps in the tens.

**The type-specific column count, answered.** Slice 3 recorded a signal: "`entities` grows a fourth and fifth type-specific column later. Watch the count. If a sixth appears, that is the signal to revisit the child-table question." A sixth, seventh, and eighth appear here, so the question gets its answer rather than another deferral.

The answer is that the count was the wrong measure and the shape is the right one. `quest_objectives` earned a child table because it is a **list**: many rows per entity, ordered, individually completable. `character_class`, `level`, and `sheet_url` are one-to-one with the row. A `character_records` table for them buys a tidy `entities` table and pays a join on every character read, a row lifecycle to create and destroy, and a second place for `Model::shouldBeStrict()` to catch a missing eager load. Record the rule in place of the count: **a list gets a child table, a scalar gets a column.** When a per-type field arrives that is not a scalar, that is the signal, whatever the count says.

### The character record

**Who carries it.** `type = character`, PC or not. A GM writes "Cultist, level 3" on an NPC as readily as a player writes "Bard, level 5" on their own, and `is_pc` already decides the only thing that differs: who may edit it.

**Who edits it.** The three columns are **not** DM fields. They sit in the base rules block of `Entities\Form`, guarded only by the type, so `EntityPolicy::update()` decides, and it already says "DM roles edit anything, a player edits their own PC". Visibility, DM notes, the PC flag, and the player assignment stay behind `updateDmFields()` where slice 1 put them.

**Validation**, added to `Entities\Form::save()` inside a `$this->entityType === EntityType::Character` guard, with `prohibited` on every other type exactly as `quest_status` does:

```php
'character_class' => ['nullable', 'string', 'max:60'],
'level' => ['nullable', 'integer', 'min:1', 'max:100'],
'sheet_url' => ['nullable', 'string', 'max:2048', 'url:http,https'],
```

**`sheet_url` is the one dangerous field in this slice.** Every other piece of user prose in demgem reaches the page through `MarkdownRenderer`, which strips raw HTML and blocks unsafe links. A sheet link does not: it is an `<a href>` written straight into a Blade template. `url:http,https` is what stops `javascript:alert(1)` from becoming a link the whole party can click, so it is a validation rule with a security job, and it gets a test that names the payload. Render it with `target="_blank"` and `rel="noopener noreferrer nofollow"`, and show the host rather than the raw URL so a 200-character D&D Beyond link does not blow the layout apart.

**Search.** `character_class` joins `toSearchableArray()`. "wizard" is a thing a GM types into the search box expecting to find the wizards. `level` does not; nobody searches for the number 5.

**Where it shows.**

- **The character page.** A record row under the header: class, level, a PC badge, the assigned player's name where one is set, and a "Character sheet" link button when there is a URL. A character with none of the four renders no row at all, not an empty one.
- **The character form.** A "Character" section holding the three fields, shown for characters only, above the visibility block.
- **The character index.** Class and level on the row, beside the name.

### The party, and the player's home

**The party filter.** `Entities\Index` gains `#[Url(as: 'pc')] public string $partyOnly = ''`, a chip on the character index only, composing onto `visibleTo()` the way the quest status filter does. `/characters?pc=1` is then a link a GM pastes to the party, which is the same reasoning slice 3 used to reject a `/quests` route.

**The party card.** The dashboard gains a "The party" card above "Quests in play": PCs the viewer may see, with image, name, class, and level, each linking to the character page. It is player-visible and it is the first card a player will look at, because it is the only one with their own name on it.

Query, beside the existing dashboard queries and eager-loaded the same way:

```php
'party' => Entity::query()
    ->ofType(EntityType::Character)
    ->visibleTo($user, $role)
    ->where('is_pc', true)
    ->with(['player', 'media'])
    ->orderBy('name')
    ->limit(8)
    ->get(),
```

A PC with `visibility = dm` stays out of a player's card by the scope, and a player's own PC is always in it by the `player_user_id` branch inside `visibleTo()`. Both already work; neither needs new code; both get a line in the player surface test.

### The story so far

**One page, at `/story`, named `story`.** A `#[Url]` tab on the sessions index was the cheaper option and it is the wrong one: the sessions index is a schedule, grouped by status and sorted newest first, and the story is prose, read oldest first, from the beginning. Those are two pages that happen to share a table.

`App\Livewire\Sessions\Story`, because a recap is a session surface and the folder already holds five.

**What it holds**, in session-number order, ascending:

- Every session whose recap the viewer may read, through `GameSession::isRecapVisibleTo($role)`. For a player that is published recaps on visible sessions, and slice 2 already wrote that rule.
- Each recap rendered through `MarkdownRenderer` with `WikiLinkRenderer::for($campaign, $user, $role)`, so a `[[Sunblade]]` in the story links to the item when the party may see it and renders as plain text when they may not.
- Each entry headed with the session label, the title, and the played date in the campaign timezone.

**What a GM additionally sees:** a session with an unpublished recap, marked "Draft, not published" with a link to the recap editor, and a played session with no recap at all, marked as a gap with a "Write the recap" button. The story page is then also the GM's list of homework, which is the reason a GM will open it twice.

**Pagination.** `WithPagination`, 20 sessions to a page, ascending. A campaign of 60 sessions with 400-word recaps is 24,000 words on one page otherwise. Ascending pagination reads oddly on an index and correctly on a story: page 1 is the beginning.

**One query.** `visibleTo()`, ordered, paginated, and nothing else eager-loaded, because a recap needs no relations. Assert the count.

### Custom fields (Phase 5, cuttable)

The brainstorm's "custom key-value attributes | MVP | JSON column" is the one MVP line no slice built.

**The column is `custom_fields`, never `attributes`.** `$model->attributes` is Eloquent's own internal property. From outside the class the magic getter hides the collision, and inside any model method, trait, or observer `$this->attributes` is the raw attribute array, silently. Naming a column `attributes` plants a trap that fires the first time somebody writes a helper method on the model. Record it as a rule.

**The shape is an ordered list of pairs, not an object.**

```json
[{"key": "Race", "value": "Tiefling"}, {"key": "Alignment", "value": "Chaotic good"}]
```

A GM types these in the order they think of them and expects that order back. A JSON object promises uniqueness nobody asked for and loses the order.

**Validation:** at most 20 pairs, key at most 40 characters, value at most 200, both trimmed. A pair with an empty key is dropped rather than rejected, because the editor's empty row is how a GM adds one. Control characters are stripped.

**They are plain text, not Markdown.** A key-value pair is a label and a value, the value renders escaped in a definition list, and a GM who wants prose has a body. This keeps the field out of `mentionableFields()` and out of the renderer entirely.

**Visibility follows the entity**, as objectives do. A secret stat goes in GM notes.

**Search:** the values join `toSearchableArray()` as one flattened string. Finding the tiefling by typing "tiefling" is the whole point of the feature.

**Who edits them:** `update`, so a player may set them on their own PC. Race and background are player facts.

**No reorder.** Add, edit, and remove only. `ReorderPositions` moves database rows and this is a JSON array; a drag handle here would be a second reorder implementation for a list a GM retypes in ten seconds.

### The JSON export

**Route and access.**

```php
Route::get('/export', CampaignExportController::class)
    ->middleware('throttle:5,1')
    ->name('campaigns.export');
```

Inside the campaign group, so `EnsureCampaignMember` runs first. A plain controller, not Livewire: this is a file download, and Livewire has no business in it. `CampaignPolicy::export()` returns `roleFor($user)?->isDm() ?? false`, a fifth ability in the same shape as `useGmTools()`. A player gets 403, a stranger gets 404 from the middleware.

**The envelope.**

```json
{
  "format": "demgem.campaign",
  "version": 1,
  "generated_at": "2026-09-03T18:00:00+00:00",
  "campaign": { "id": "...", "name": "...", "ruleset": "dnd5e_2024", "timezone": "Europe/London", "cover": { "url": "...", "file_name": "...", "mime_type": "...", "size": 12345 } },
  "members": [ { "user_id": 3, "name": "Tobin", "role": "player", "joined_at": "..." } ],
  "entities": [ ... ],
  "sessions": [ ... ],
  "encounters": [ ... ],
  "random_tables": [ ... ],
  "dice_rolls": [ ... ]
}
```

- **ULIDs are kept as they are.** They are the only thing that makes the relations in the file resolvable, and they are already opaque.
- **Timestamps are ISO 8601 in UTC.** The campaign timezone travels in the campaign object; the rows do not each carry it.
- Entities carry every column, their tags by name, their viewer user ids, their image metadata, and, for a quest, its objectives. Sessions carry every column, their scenes, their secrets, and their prep buckets as entity ids by role. Encounters carry their combatants; tables carry their entries.

**What is deliberately absent, and why:**

| Excluded | Reason |
|---|---|
| Email addresses | An export file sits in a Downloads folder and gets shared. The party's email addresses are not the GM's data to hand around. An importer re-links people by invite, which is how they joined in the first place. |
| Passwords, tokens, two-factor secrets, passkeys | Users are not exported as user records at all. Only membership rows. |
| Invites | They hold live tokens. An exported invite is a credential in a text file. |
| Soft-deleted rows | An export is the world as it stands, not its history. |
| `mentions` | Derived. The observers rebuild the whole index on save, so exporting it exports work, not data. |
| Media binaries | URLs and metadata only. A zip with the images is the Markdown export, which the brainstorm puts in P2. |

**Streaming, and why it matters here.**

```php
return response()->streamJson($sections, 200, [
    'Content-Disposition' => 'attachment; filename="'.$filename.'"',
]);
```

`streamJson` walks lazy values as it writes, so passing `->cursor()` or `->lazyById(500)` for each section keeps memory flat whatever the campaign holds and starts the download immediately. Build the sections in `app/Actions/Campaigns/ExportCampaign.php` — an action, not a new `app/Export/` folder, following the detour slice 2 took when `SessionsMentioning` went into `app/Actions/Sessions/` rather than opening `app/Queries/`.

Two traps come with streaming and both belong in the plan rather than in a debugging session:

1. **`Model::shouldBeStrict()` is on.** A lazy section that touches a relation it did not load throws mid-stream, after the response headers are sent, so the file arrives truncated and valid-looking. Eager-load inside every section query, and let `ExportTest` decode the whole body.
2. **A test reads a streamed response with `streamedContent()`**, not `getContent()`. Assert on the decoded array.

**The export must grow with the schema, and a test enforces it.** The failure mode for an export is silent: somebody adds a table in slice 5, nobody adds it to the export, and a year later a GM's data leaves without it. `ExportCoverageTest` reads the table list from the schema, keeps the ones that have a `campaign_id` column, and asserts each is either present in the export or named in a documented exclusion list inside `ExportCampaign`. A new campaign-scoped table then fails the suite until somebody makes a decision about it. Record the rule.

**Where it lives in the UI.** An "Export" card in campaign settings, above "Transfer ownership": one sentence about what the file holds, one about what it leaves out, and a download button. Settings is already GM-only, so the card needs no second guard, but the policy check in the controller is what actually enforces it.

### Docker

**One new base folder, `docker/`, and this plan is the request.** CLAUDE.md asks for approval before a new base folder. The alternative is four more files in the repository root, one of them a shell script, which is worse.

```
Dockerfile                  # multi-stage: composer deps, node build, runtime
compose.yaml
.dockerignore
.env.docker.example
docker/entrypoint.sh
```

The Dockerfile and compose file sit at the root where every tool looks for them; only the entrypoint goes in `docker/`.

**The runtime is FrankenPHP.** `dunglas/frankenphp:php8.4-alpine` in classic mode serves `public/` over HTTP with Caddy inside the same process, so the stack has no nginx service, no php-fpm socket, and no `fastcgi_pass` for a self-hoster to get wrong. It is a base image, not a Composer package, so no application dependency changes. The alternative is recorded below.

**The services:**

| Service | Image | Job |
|---|---|---|
| `app` | built from the Dockerfile | Serves the app on port 8000 by default, `APP_PORT` to change it. |
| `db` | `postgres:17-alpine` | Named volume `pgdata`. Healthcheck `pg_isready`. `app` waits for it. |
| `redis` | `redis:8-alpine` | Cache and queue. Healthcheck `redis-cli ping`. |
| `worker` | the same built image | `php artisan queue:work --tries=3 --max-time=3600`. |

Pin every tag at implementation time to the current stable, and write the pinned tag, not a floating one.

**Redis earns its place, and the worker earns its place, but only just.** Nothing in the app queues work today: media conversions are `nonQueued()` and no mail is sent. Both go in anyway, for one reason each. Redis: `CACHE_STORE=database` on a Postgres box means the cache competes with the campaign for the same connection pool, and P2 adds Reverb, which wants Redis regardless. The worker: the first S3 disk or the first email turns queued work on, and a self-hoster who has to add a service to their compose file a year later usually does not. Sessions stay on the database driver, because a Redis restart should not sign the whole table out mid-game.

**The entrypoint, in order:**

1. Refuse to start with no `APP_KEY`, printing the exact command that makes one. Generating a key on boot is worse than failing: an unpersisted key changes on every restart and signs everybody out.
2. Recreate `storage/framework/{cache,sessions,views}` and `storage/logs`. **A named volume mounted at `/app/storage` hides whatever the image put there**, so these directories exist in the image and vanish at runtime. This is the single most common Laravel-in-Docker failure and it is one `mkdir -p`.
3. `php artisan migrate --force`, unless `AUTO_MIGRATE=false`.
4. `php artisan storage:link`.
5. `config:cache`, `route:cache`, `view:cache`.
6. `exec` the container command.

**The first run**, documented in the README as four steps: copy `.env.docker.example` to `.env`, generate a key, `docker compose up -d`, open the app and register. The first account registered is a normal account; demgem has no instance admin and does not need one.

**CI builds it.** A second job in `.github/workflows/ci.yml`: build the image, `docker compose up -d`, poll `/up` until it answers or 60 seconds pass, then `docker compose down -v`. It runs on every pull request and costs about two minutes. An image that is not built by CI rots inside a month.

### Screens and routes

```php
// routes/web.php, inside the campaign group
Route::get('/story', SessionsStory::class)->name('story');
Route::get('/export', CampaignExportController::class)->middleware('throttle:5,1')->name('campaigns.export');
```

Register both above the `/{type}` block, as slices 2 and 3 did. Neither takes a parameter, so the "do not name a route parameter after a model" rule has nothing to catch here.

- **Sidebar, Play group.** "Story" with the `book-open` icon, between Sessions and Encounters, for every role. No count.
- **Dashboard.** "The party" card above "Quests in play".
- **Character index.** The party filter chip, plus class and level on each row.
- **Character page.** The record row and the sheet link.
- **Entity form.** The Character section, and in Phase 5 the custom fields repeater below the body for every type.
- **Campaign settings.** The Export card.

## Decisions resolved

| Question | Decision |
|---|---|
| A separate `/player` route tree | No. Every list is already role-filtered, and a second tree doubles the surface where a leak can happen. The dashboard is the player's home. |
| Where the recap archive lives | `/story`, its own page. A tab on the sessions index conflates a schedule with a story: different order, different rendering, different reader. |
| Story page order | Session number ascending, paginated at 20. Page 1 is the beginning of the story. |
| Drafts on the story page | A GM sees unpublished recaps marked as drafts and played sessions with no recap marked as gaps. It doubles as the GM's homework list. A player sees neither, through `isRecapVisibleTo()`. |
| Character record as a child table | No. Class, level, and sheet link are one-to-one with the row. A child table buys tidiness and pays a join on every character read. |
| The type-specific column count from slice 3 | Answered and replaced: a **list** gets a child table, a **scalar** gets a column. The count was the wrong measure. |
| `class` as a column name | No. `character_class`. `class` is reserved in enough dialects to be a nuisance and reads badly beside PHP's keyword. |
| Who may edit class, level, and sheet link | Anyone who passes `update`, which means a player on their own PC. They are not DM fields. |
| NPCs carry the record too | Yes. Only editing differs, and `is_pc` already decides that. |
| `sheet_url` validation | `url:http,https`, and it is a security rule, not a formatting one: this is the only user URL rendered as an `href` outside `MarkdownRenderer`. Rendered with `rel="noopener noreferrer nofollow"`. |
| Level range | 1 to 100. The core is system-agnostic; 5e's 20 belongs to a ruleset. |
| Party roster as a new route | No. A card on the dashboard plus `#[Url]` `pc` filter on `/characters`, matching the quest log decision from slice 3. |
| The JSON column name | `custom_fields`. **Never `attributes`**: it collides with Eloquent's internal property inside every model method. |
| Custom fields shape | An ordered list of `{key, value}` pairs. An object would lose the order and promise uniqueness nobody wants. |
| Custom fields as Markdown | No. Plain text, escaped, in a definition list. They stay out of `mentionableFields()` and out of the renderer. |
| Custom fields reordering | Not built. A JSON array with a drag handle is a second reorder implementation for a list a GM retypes in ten seconds. |
| Who may export | GM roles. A co-GM already sees everything in the file. A player gets 403. |
| Export delivery | A streamed download, synchronous. `streamJson` with cursors is flat memory and an instant start, so a queued job and an emailed link buy nothing. |
| Emails in the export | Excluded. An export file gets shared; an importer re-links people by invite. |
| Invites in the export | Excluded. They hold live tokens. |
| Soft-deleted rows in the export | Excluded. An export is the world as it stands. |
| `mentions` in the export | Excluded. Derived data the observers rebuild on save. |
| Images in the export | URLs and metadata only. The zip with binaries is the Markdown export, P2. |
| Export versioning | `format` and `version` in the envelope, version 1. The importer is P2 and needs something to read. |
| Import in this slice | No. Export is the promise; import is a feature, and a half-built importer is worse than none. |
| Keeping the export honest | `ExportCoverageTest` reads the schema and fails when a campaign-scoped table is neither exported nor documented as excluded. |
| Docker runtime | FrankenPHP, classic mode. One container serves HTTP and PHP, so there is no nginx config and no fpm socket to get wrong. |
| Services | `app`, `db`, `redis`, `worker`. Sessions stay on the database driver so a Redis restart does not sign the table out. |
| Redis and the worker with no queued work today | Both ship anyway. Redis keeps the cache off the campaign's connection pool and P2 wants it for Reverb; a worker added a year later usually is not. |
| `APP_KEY` on boot | The entrypoint refuses to start without one. A generated-on-boot key changes on restart and signs everybody out. |
| Migrations on boot | Yes, `migrate --force`, with `AUTO_MIGRATE=false` for operators who run them by hand. |
| A `docker/` base folder | Requested here, per CLAUDE.md. Only the entrypoint goes in it; the Dockerfile and compose file stay at the root where tools look. |
| Docker in CI | A second job: build, boot, poll `/up`, tear down. An image CI does not build rots inside a month. |
| New `x-ui.*` components | Zero. The kit covers every screen in this slice. |
| Custom fields in the MVP | In, as Phase 5, and cuttable. If it is cut, the brainstorm line moves to P2 in the same commit. |

## Implementation Phases

Each phase ends with a green suite. Do not start the next phase with a red one. Generate files with `php artisan make:… --no-interaction`, and use the `make:livewire` flag that produces a class plus a separate view, as all three earlier slices did.

Phases 0 to 2 are a shippable release. Phases 3 and 4 are a second one. Phase 5 is cuttable.

### Phase 0: The tablet pass and the plan debt

Deliverables:
- Blade and Tailwind fixes for every fault found at 1024 x 768 and 768 x 1024, dark and light, across the screens listed above.
- The two-device tracker check, done by hand with two browser tabs.
- Slice 2's 28 acceptance boxes and slice 3's three, ticked line by line against a named test or a screen. A line that fails becomes a fix in this phase.

Tests: none new. The suite stays green, which for a Blade-only phase is the whole assertion.

Success: a GM can run the Run screen from a 768px tablet in a dark room, and no plan in `docs/plans/` claims work that is not done.

### Phase 1: The character record and the party

Deliverables:
- Migration `add_character_columns_to_entities_table`: `character_class`, `level`, `sheet_url`.
- `Entity`: the three columns in `#[Fillable]` and the docblock, `'level' => 'integer'` in `casts()`, `isCharacter()`, `hasCharacterRecord()`, `sheetHost()`, and `character_class` in `toSearchableArray()`.
- `EntityFactory`: a `pc(?User $player = null)` state and a `withRecord()` state.
- `Entities\Form`: the Character section, the three fields, and the `prohibited` rules on every other type.
- `Entities\Show`: the record row and the sheet link button, rendered with `target="_blank"` and `rel="noopener noreferrer nofollow"`.
- `Entities\Index`: `#[Url(as: 'pc')] $partyOnly`, the chip, and class and level on character rows.
- `Campaigns\Show`: the party card and its query.

Tests: `tests/Feature/Entities/CharacterRecordTest.php` (a GM sets all three and they render; **a player edits their own PC's class and level and cannot touch another PC**; `javascript:` is refused and `https:` is accepted; the fields are `prohibited` on a location; a character with no record renders no row), `tests/Feature/Entities/PartyRosterTest.php` (the filter lists only PCs, composes onto `visibleTo()`, survives a refresh through the URL; a hidden PC is absent from a player's dashboard card; a player's own hidden PC is present).

Success: a player opens their character, sets Bard and 5, pastes their sheet link, and the party card on the dashboard shows all four PCs with their classes.

### Phase 2: The story so far

Deliverables:
- `app/Livewire/Sessions/Story.php` and its view. `InteractsWithCampaign`, `enterCampaign()` in `mount()`, `WithPagination`, 20 a page, ascending.
- Each entry rendered through `MarkdownRenderer` with `WikiLinkRenderer::for()`.
- GM-only draft and gap markers with links to the recap editor.
- The route, the sidebar entry, and an empty state that reads differently for a GM and a player.

Tests: `tests/Feature/Sessions/StoryTest.php` (published recaps in ascending order; **an unpublished recap's text is absent from a player's HTML and Livewire snapshot**; a recap on a `visibility = dm` session is absent for a player; a GM sees drafts and gaps; wiki links to hidden entities render as plain text; one query for the page), `tests/Feature/Players/PlayerSurfacesTest.php` (a player walks the dashboard, sessions index, session page, story, quest index, quest page, character page, entity index, members, and search, and none of the HTML holds a prep link, a run link, GM notes, a strong start, live notes, a secret, an encounter, a dice roll, a table, a settings link, or an export link).

Success: a player who missed two games reads what happened, in order, on one page, and the GM sees which recaps they still owe.

### Phase 3: The JSON export

Deliverables:
- `app/Actions/Campaigns/ExportCampaign.php`, building the sections with cursors and eager loads, and holding the documented exclusion list.
- `app/Http/Controllers/CampaignExportController.php`, invokable, authorising `export` and returning `response()->streamJson(...)` with the attachment header.
- `CampaignPolicy::export()`.
- The route with `throttle:5,1`.
- The Export card in campaign settings.

Tests: `tests/Feature/Campaigns/ExportTest.php` (the envelope carries the format and version; a campaign with entities, sessions, scenes, secrets, quests, objectives, encounters, combatants, tables, entries, and dice rolls round-trips every section; **no email address, password, token, two-factor secret, or invite token appears anywhere in the body**; soft-deleted rows are absent; a player gets 403 and a non-member 404; the filename carries the campaign name and the date; the body decodes from `streamedContent()`), `tests/Feature/Campaigns/ExportCoverageTest.php` (every table with a `campaign_id` column is exported or documented as excluded).

Success: a GM downloads their campaign, opens the file, finds their world in it, and finds nobody's email address.

### Phase 4: Docker

Deliverables:
- `Dockerfile`, multi-stage: Composer dependencies with `--no-dev`, the Vite build on Node 22, and the FrankenPHP runtime with `pdo_pgsql`, `gd`, `exif`, `intl`, and `zip`.
- `compose.yaml` with `app`, `db`, `redis`, and `worker`, pinned tags, healthchecks, and the `pgdata` and storage volumes.
- `docker/entrypoint.sh` with the six steps above, `mkdir -p` for the framework directories included.
- `.dockerignore` and `.env.docker.example`.
- The `docker` job in `.github/workflows/ci.yml`.
- README: a Docker section with the four first-run steps, the environment table, and a note that the first registered account is a normal account.

Tests: no Pest tests; CI is the test. The job fails if the image does not build or `/up` does not answer.

Success: a clone, four commands, and a browser tab produce a running demgem with an empty database and a working registration form.

### Phase 5: Custom fields (cuttable)

Deliverables:
- Migration `add_custom_fields_to_entities_table`.
- `Entity`: the cast, `customFields(): array`, and the flattened values in `toSearchableArray()`.
- `Entities\Form`: the repeater, the caps, the trimming, and the empty-key drop.
- `Entities\Show`: the definition list, escaped, below the body.

Tests: `tests/Feature/Entities/CustomFieldsTest.php` (pairs save and render in order; the 20-pair, 40-character, and 200-character caps hold; an empty key is dropped; `<script>` in a value renders escaped; search finds an entity by a value; a player sets fields on their own PC).

Success: a GM records Race, Alignment, and Patron on an NPC, and finds the NPC later by typing the patron's name.

### Phase 6: Release polish

- `DemoCampaignSeeder`: classes, levels, and sheet links on the four PCs, three published recaps and one draft so `/story` shows both states, and custom fields on two entities.
- README: the Status section gains slice 4 and closes the MVP; new sections for Docker and for the export; the contributor rules gain the sheet-link rule and the export rule.
- Record the new rules with `record-rule`: a list gets a child table and a scalar gets a column; `sheet_url` is the one non-Markdown user URL and needs `url:http,https`; a JSON column is never named `attributes`; a new campaign-scoped table joins the export in the same commit; a volume over `storage` hides the framework directories.
- Move the brainstorm's "custom key-value attributes" row to P2 if Phase 5 was cut.
- Run `vendor/bin/pint --dirty --format agent`, `composer analyse`, `npm run build`, and the full suite. Fix everything.

## Alternative Approaches Considered

- **A dedicated `/player/...` route tree.** A clean separation, and easy to reason about. Rejected: every list in the app is already role-filtered, the hardest tests in the codebase are leak tests, and a second tree doubles the number of places a leak can appear while duplicating six screens.
- **The recap archive as a tab on the sessions index.** Cheaper, and `x-ui.tabs` already exists. Rejected: a schedule sorts newest first and lists rows; a story sorts oldest first and renders prose. One page cannot be both without being bad at one.
- **A `character_records` child table.** Keeps `entities` free of type-specific columns. Rejected: one-to-one data, a join on every character read, and a row lifecycle, to avoid three nullable columns on a table that already carries five.
- **Class and level as custom fields instead of columns.** Tempting once Phase 5 exists, and it would have kept the column count flat. Rejected: a sheet link needs URL validation with a security job, a level needs to be a number, and "the two facts every character sheet in every system agrees on" deserve better than a string in a JSON blob.
- **A queued export job with an emailed download link.** The standard shape for a big export. Rejected: `streamJson` over cursors is flat memory whatever the size, so the queue would buy latency and an email dependency and nothing else.
- **Exporting the media binaries in a zip.** What a real migration needs. Deferred: the brainstorm already puts the Markdown-plus-zip export in P2, and that is where the binary story belongs, together.
- **Exporting email addresses so an importer can re-link accounts.** The obvious convenience. Rejected: the file leaves the instance and gets shared, and invites already exist as the way people join a campaign.
- **nginx plus php-fpm in Compose.** The shape most self-hosters have seen before. Rejected: two more services, an nginx config, and a socket path, all for a self-hoster to debug at midnight, when FrankenPHP does it in one container.
- **Laravel Octane with FrankenPHP in worker mode.** Faster, and the same base image. Rejected for this slice: Octane is a Composer dependency and a set of state-leak rules the codebase has never had to follow. Classic mode first; worker mode when there is a load problem to solve.
- **Shipping a published image to GHCR.** What a self-hoster actually wants: no build step. Deferred to the release that follows this one, because it needs a tag scheme and a publish workflow, and building in CI first proves the Dockerfile.
- **Skipping the tablet pass again and shipping the four features.** Rejected twice already, which is exactly the argument for doing it first.

## Acceptance Criteria

### Functional

- [x] Prep, Run, the tracker, the drawer, and the quest page work at 1024px and 768px, in dark and light, with 16px body text, 44px tap targets on the controls a GM uses mid-game, and no hover-only controls.
- [x] A second GM device shows a round change within 15 seconds without a manual refresh.
- [x] Every acceptance box in the slice 2 and slice 3 plans is ticked against a named test or a checked screen.
- [x] A GM sets a class, a level, and a sheet link on any character, and they render on the character page and the character index.
- [x] A player edits the class, level, and sheet link on their own PC, and cannot edit another player's PC.
- [x] A `javascript:` sheet link is refused with a message and writes nothing; an `https:` link renders with `rel="noopener noreferrer nofollow"`.
- [x] The three character fields are rejected on a location, a faction, an item, a quest, and a note.
- [x] `/characters?pc=1` lists the party only, and the filter survives a refresh through the URL.
- [x] The dashboard shows the party with images, classes, and levels, filtered by what the viewer may see.
- [x] `/story` shows every recap the viewer may read, oldest first, with wiki links resolved through the viewer's visibility.
- [x] A GM sees unpublished recaps marked as drafts and played sessions with no recap marked as gaps, each linking to the editor.
- [x] A GM downloads a JSON export holding the campaign, members, entities, sessions, encounters, tables, and dice rolls.
- [x] The export filename carries the campaign name and the date, and the browser downloads it rather than rendering it.
- [x] `docker compose up -d` produces a running app, a migrated database, and a working registration form, from a clean clone.
- [x] Restarting the stack keeps the database, the uploaded images, and every signed-in session.
- [x] A GM adds custom fields to an entity, sees them in order, and finds that entity by searching a value. *(Phase 5; drop this line if the phase is cut.)*

### Non-functional

- [x] **No unpublished recap text reaches a player's HTML, Livewire snapshot, or the story page's pagination.**
- [x] **A player's export request is refused with 403, and a non-member's with 404.**
- [x] **No email address, password hash, remember token, two-factor secret, or invite token appears anywhere in an export body.**
- [x] Soft-deleted entities and sessions are absent from the export.
- [x] A hidden PC is absent from a player's party card and party filter; a player's own hidden PC is present in both.
- [x] `Model::shouldBeStrict()` is on, so the story page, the party card, and every export section eager-load, and no test throws a lazy-load exception.
- [x] The story page costs a constant number of queries, whatever the page holds.
- [x] The export streams: the response is a streamed response, and a test reads it with `streamedContent()` and decodes it whole.
- [x] `ExportCoverageTest` fails when a campaign-scoped table is neither exported nor documented as excluded.
- [ ] The Docker image builds in CI and answers `/up` within 60 seconds of `docker compose up -d`.
- [x] The container refuses to start with no `APP_KEY` and prints the command that makes one.
- [x] A named volume over `storage` does not break the app: the framework directories are recreated on boot.
- [x] Custom field values render escaped, and `<script>` in a value is inert. *(Phase 5.)*

### Quality gates

- [ ] Pest suite green on SQLite locally and on Postgres in CI.
- [x] Larastan level 6 clean. Pint clean.
- [x] Zero new `x-ui.*` components, or a written reason for each one added.
- [x] Every new query on `entities` and `game_sessions` goes through `visibleTo()`, and `CampaignPolicy::export()` names its surfaces in a docblock as the other abilities do.
- [x] `npm run build` clean.

## Dependencies & Risks

| Risk | Mitigation |
|---|---|
| The tablet pass is subjective and could sprawl | The viewports, the five rules, and the screen list are named in Phase 0. Fix what breaks those rules; leave taste for later. |
| Ticking two plans' boxes turns into ticking them blind | A box is ticked against a named test or a screen just looked at. A line that fails is a fix in Phase 0, not a tick. |
| `sheet_url` becomes the app's first XSS | `url:http,https` at write time, `rel="noopener noreferrer nofollow"` at render time, and a test that names the `javascript:` payload. It is the only user URL outside `MarkdownRenderer`. |
| A draft recap leaks onto the story page | `isRecapVisibleTo()` is slice 2's rule and the only gate; the page adds no second one. Its own test file, snapshot included. |
| An export leaks credentials or email addresses | The exclusion list is documented in the action, and `ExportTest` asserts the absence of each class of secret by scanning the whole body. |
| A future table silently misses the export | `ExportCoverageTest` reads the schema and fails until somebody decides. Recorded as a rule. |
| A strict-mode lazy load throws mid-stream and truncates the file | Every section eager-loads, and the test decodes the entire body rather than asserting on a fragment. |
| A big campaign exhausts memory on export | `streamJson` over cursors, no `get()` anywhere in the action, and a 5-per-minute throttle on the route. |
| A volume over `storage` breaks a fresh container | The entrypoint recreates the framework directories. It is the most common Laravel Docker failure and it is one line. |
| Docker CI adds minutes to every pull request | One job: build, boot, poll `/up`, down. About two minutes, and an unbuilt image rots inside a month. |
| FrankenPHP is a runtime nobody on the project has run | It appears in exactly one place, the compose stack. Herd stays the development path and CI still runs the suite on bare PHP. |
| The slice runs long | Two release boundaries: after Phase 2 and after Phase 4. Phase 5 is cuttable by design. |
| `entities` keeps growing columns | The rule this slice records answers it: a list gets a child table, a scalar gets a column. The next non-scalar per-type field is the trigger. |

## Future Considerations

- **The importer.** The export is versioned for it. It needs conflict rules for ULIDs that already exist, an account re-link flow through invites, and a dry run. It is the natural first slice after the MVP.
- **Markdown export with front matter, in a zip.** P2 in the brainstorm, and the right home for the media binaries this slice leaves as URLs.
- **A published image on GHCR, with a tag scheme.** Turns four first-run commands into three and removes the build. It wants the release process this slice does not create.
- **Reverb.** Still the biggest P2 item: the shared dice log, the player tracker view, and a tracker that pushes instead of polling. Redis is in the compose stack partly for this.
- **Per-user timezones.** The campaign timezone is one field today, and the README already promises this as a later feature.
- **A player's own journal, and party inventory.** P2. Both are entity-shaped and both want the visibility model this slice leaves untouched.
- **RSVP, reminders, and an iCal feed.** P2, and the first real use of the queue worker this slice ships idle.
- **Character sheet embedding.** A sheet link is a link. Reading a level from D&D Beyond or Demiplane is a ruleset-module conversation, not a column.
- **An instance admin.** demgem has no superuser and does not need one yet. Multi-tenant hosting, quotas, and abuse handling all arrive together or not at all.

## References

### Internal

- Brainstorm: `docs/brainstorms/2026-09-02-demgem-campaign-manager-brainstorm.md`, the "Players", "Portability and integrations", and "Platform" tables
- Slice 1 plan: `docs/plans/2026-09-02-feat-campaigns-members-entities-foundation-plan.md` — the visibility model and the child-table exception
- Slice 2 plan: `docs/plans/2026-09-02-feat-sessions-prep-play-recap-plan.md` — the recap publishing rule, and 28 boxes to tick
- Slice 3 plan: `docs/plans/2026-09-03-feat-quests-tracker-dice-tables-plan.md` — the type-specific column signal this slice answers, and three boxes to tick
- Project rules: `.ai/rules/models.md`, `.ai/rules/migrations.md`, `.ai/rules/views.md` (`@disabled` inside `x-` tags), `.ai/rules/livewire.md`, `.ai/rules/routes.md`
- Reused without change: `app/Models/Entity.php` (`visibleTo`, `isVisibleTo`), `app/Models/GameSession.php` (`isRecapVisibleTo`, `needsRecap`), `app/Markdown/MarkdownRenderer.php`, `app/Livewire/Concerns/InteractsWithCampaign.php`, `app/Policies/EntityPolicy.php`
- Patterns to copy: `app/Livewire/Entities/Show.php` (renderer plus visibility-filtered relations), `app/Livewire/Entities/Index.php` (`#[Url]` filters), `app/Livewire/Sessions/Show.php` (recap rendering), `app/Policies/CampaignPolicy.php` (`useGmTools()`, the shape the export ability copies)
- The quest fields the character fields mirror: `app/Livewire/Entities/Form.php`, the `isQuest()` guard and the `prohibited` rules
- CI to extend: `.github/workflows/ci.yml`

### External

- Laravel 13 streamed JSON responses: https://laravel.com/docs/13.x/responses#streamed-json-responses
- Laravel 13 `url` validation with protocols: https://laravel.com/docs/13.x/validation#rule-url
- Laravel 13 chunking and lazy results: https://laravel.com/docs/13.x/eloquent#chunking-results
- Livewire 4 pagination: https://livewire.laravel.com/docs/4.x/pagination
- Livewire 4 URL query parameters: https://livewire.laravel.com/docs/4.x/url
- FrankenPHP with Laravel: https://frankenphp.dev/docs/laravel/
- Compose file reference: https://docs.docker.com/reference/compose-file/
