# demgem

An open source campaign manager for Dungeon Masters and Game Masters. Session-first: prep, play, recap, repeat. A campaign wiki with wiki links, per-entity visibility, and player views supports that loop.

Built with Laravel 13, Livewire 4, Alpine, and Tailwind 4. PostgreSQL in production. Fully custom UI.

## Status

Slice 1 is done: accounts, campaigns, members with roles, invite links, entities (characters, locations, factions, items, quests, notes) with Markdown, `[[wiki links]]`, backlinks, GM-only notes, visibility, tags, nesting, images, and search.

Slice 2 is done: sessions with a number, title, date, and status; a prep screen with a strong start, ordered scenes, secrets and clues, and four entity buckets; a run screen with autosaving live notes and one-click secret reveals; and a recap the GM publishes on purpose. Unrevealed secrets carry into the next session.

Slice 3 is done: quests with a status, a giver, rewards, and an ordered objective checklist that records the session each step was finished in; an initiative tracker with hit points, conditions, rounds, and a turn marker that survives a refresh; a dice roller with keep-highest, keep-lowest, and advantage; and weighted random tables that can nest one inside another. The tracker sits on the run screen, and dice and tables live in a drawer beside it.

Slice 5 is done: **the live table**. A GM advances the turn and every screen at the table changes at once, over one authorised websocket channel per campaign. `/table` is the player's screen: the turn order, the round, whose turn it is, and each combatant the GM chose to show, with health as a word rather than a number. Dice are shared, so a player rolls at their end of the table and everyone sees it, and a GM can still roll behind the screen. The strip at the top of both screens says who has the campaign open.

Slice 4 is done, and the MVP with it: a character record with a class, a level, and a link to the sheet a player actually plays from, editable by that player; the party on the dashboard and behind a filter on the character index; **The story so far**, every recap in order, with drafts and missing recaps shown to the GM only; key-value fields on any entity, searchable; a streamed JSON export of a whole campaign; and a Docker stack a self-hoster can run with one command. See `docs/plans/`.

Slice 6 is done: **maps**. A map is an entity, so it has a body, GM notes, tags, wiki links, and visibility like everything else, plus an image the viewer pans and zooms on a phone, a tablet, or a laptop. A GM drops pins that point at any entity, reveals each one as the party finds the place, and pins one map inside another so the world leads to the duchy and the duchy to the city. A player opens the same map and sees the half they have earned, and a reveal lands on their screen without a refresh.

Slice 7 is done: **handouts and clocks**. A handout is an entity with a gallery of up to ten files, images and PDFs, and **Show the party** is one press that puts it on every open table screen. A progress clock is a named dial cut into 4, 6, 8, or 12 segments that the GM fills, or empties as a countdown, and a revealed clock ticks on `/table` while the party watches.

Slices 8 and 9 are done: **the round trip**. A campaign leaves as one archive, a zip holding the JSON, every image and attachment, and the whole campaign as Markdown with front matter that Obsidian opens as a vault. The importer takes the archive or the bare JSON, validates the whole file before writing a row, remaps every id, restores the media, and tells the GM what could not come across before they commit. `php artisan demgem:import` does the same for the JSON from a terminal.

Slice 10 is done: **scheduling**. A member says whether they are coming, the GM records who was there, a dateless session is a poll with candidate times the party votes on, one reminder email goes out before each session at a lead time the GM chooses, and every user has a private calendar feed that carries nothing but the session names.

Slice 11 is done: **relationships**. A typed link between two entities with a label written from one side and an optional reverse label for the other, revealed to the party when the GM says so and gated at both ends, and drawn as wiki links in the Obsidian vault so its graph shows them.

Slice 12 is done: **the world's own calendar**. A GM names the months, sets the week, hangs a moon or two, adds a leap rule and an era, and says what day it is; every member reads today on the dashboard and on a month grid with the moons on every day. An event is an entity with a day. A session carries the days the party spent in the world. The timeline lists every dated event and session the viewer may see, in world order, with a marker for today. The calendar and every date travel in the export and the Markdown front matter.

Slice 13 is done: **a key, an API, and the party's channel**. A user mints a named API key from their profile, read-only or read and write, shown once and revoked with a click. `/api/v1` serves the campaigns that key belongs to, the entities and sessions its role may see, search, and the same writes a GM makes on screen, gated by the same scopes and policies as every page. A campaign can hold one Discord webhook, and when a recap is published or a reminder goes out the channel gets one line and a link, never the prose.

Slice 15 adds **the compendium**. A campaign on the SRD 5.2.1 ruleset gets all 330 creatures of the System Reference Document as a searchable reference: filter by creature type and challenge band, read one stat block as the book prints it, and put it in the fight. Adding a creature brings its hit points, armour class and initiative bonus with it, optionally rolling each copy's hit dice so four goblins are four different totals. An NPC can name what it fights as, so a session's Monsters bucket fills the turn order with numbers. The data is shipped, global and read-only; the API serves it beside the rest; and a campaign export carries the reference rather than the licensed text.

Slice 14 adds **entity templates and body history**. GMs keep named starting bodies per entity type in campaign settings, copy one into a new page, and edit the copy freely. Earlier bodies are kept whenever a form or API save replaces the text. GMs can inspect and restore them from the entity page; restoring preserves the displaced body too. Templates and history travel in campaign JSON and archives.

Slice 16 adds **what a fight is worth, and the four rules the tracker used to make you keep on paper**. The turn order says what the creatures in it cost against what the party can afford, in five bands from Trivial to Deadly. A combatant holds one named effect, and damage prints the concentration DC rather than rolling it. A character on nought collects death saves, three of either ending the question, and the party's own screen carries the pips because the whole table is counting them out loud anyway. A creature from the book arrives with its legendary actions counted and gets them back when the turn marker reaches it. A lair action is the GM's own words on a count they choose, sitting in the turn order as a marker the party sees without the text. **Duplicate this fight** builds the whole thing again, at full health with nobody's initiative rolled.

The budget numbers are demgem's own, and the read-out says so. The SRD prices every creature and that data ships with the app, but it publishes no encounter building budget. `config/encounters.php` states one rule instead and derives the rest from the ladder the dataset itself carries: a character of level N affords a quarter, a half and three quarters of the XP of a CR N creature. A fight holding rows the compendium cannot price says so, and calls its band a floor.

Slice 17 adds **the GM's own monsters**. A campaign writes its own creatures into the same compendium as the shipped ones: the numbers the tracker copies, six ability scores, and traits, actions, bonus actions, reactions and legendary actions as lists you can write in Markdown. Every field but the name is optional. **Copy to my campaign** takes any shipped creature and hands you an editable copy, which is the fastest way to a homebrew ogre. Your creatures come first in the book, in the tracker's picker, and on any NPC that names what it fights as, and they carry XP so the encounter budget prices them. The compendium is now open to every campaign, not only one on a ruleset with a shipped book: a system-agnostic table writes its own creatures and reads a compendium holding exactly those.

Slice 18 adds **story arcs, and the log of what the party earned and chose**. An arc is an entity, a chapter of the campaign: a quest and a session may each be filed under one, and the arc's page lists its quests by status and its sessions in order, each list gated by the viewer's own role. A session carries what the party earned that night, XP or a milestone or both, and the story page totals it over the sessions the reader may see. The decision log is what the party chose and what it cost them, written at the table on the run screen or on the session page, the consequence filled in when the world answers, and revealed to the party a row at a time. All three travel in the export, the import, and the Markdown vault.

Your creatures travel in your export with the prose you wrote. A shipped creature never does — it is named in the file and nothing more, exactly as before. The CC BY notice follows the words rather than the table: a creature you wrote carries none, and a copy of a shipped one keeps the source and licence it came from, so the credit travels with the text.

## Local setup

Requirements: PHP 8.4, Composer, Node 20+, PostgreSQL 17+.

```sh
composer install
cp .env.example .env
php artisan key:generate
# Point DB_* at your Postgres, then:
php artisan migrate
php artisan demgem:import-srd   # the SRD compendium; skip it for system-agnostic campaigns
php artisan storage:link
npm install && npm run build
```

For the live table locally, run a queue worker and Reverb beside the app:

```sh
php artisan queue:work
php artisan reverb:start
```

`php artisan dev` runs both for you, along with Vite. Without them, screens fall back to their sixty-second poll. Reminder emails also need `php artisan schedule:work`, and go to the log until `MAIL_MAILER` is a real mailer.

Optional demo world with a GM and a player:

```sh
php artisan db:seed --class=DemoCampaignSeeder
```

It creates `dev@demgem.test` and `tobin@demgem.test`, both with the password `password`.

## Run it with Docker

Requirements: Docker 24 or newer with Compose v2. Nothing else: no PHP, no Node, no PostgreSQL.

```sh
cp .env.docker.example .env.docker
docker compose run --rm --no-deps app php artisan key:generate --show
# Paste the whole base64:... string into APP_KEY in .env.docker, then:
docker compose up -d
```

Open <http://localhost:8000> and register. The first account is an ordinary account: demgem has no instance administrator and does not need one.

| Service | What it does |
|---|---|
| `app` | FrankenPHP, serving the app on port 8000. Runs the migrations on boot. |
| `worker` | `queue:work`. It carries the live table's broadcasts and the reminder emails, so the table is only as quick as this container. |
| `scheduler` | `schedule:work`. Every fifteen minutes it queues the reminder emails that are due. |
| `reverb` | The websocket server, on port 8080. Every open browser holds a connection to it. |
| `db` | PostgreSQL 17, in the `pgdata` volume. |
| `redis` | Cache and queue. Sessions stay in PostgreSQL, so a Redis restart keeps everyone signed in. |

- `APP_PORT=8099 docker compose up -d` publishes on another port.
- Change `DB_PASSWORD` in `.env.docker` and `POSTGRES_PASSWORD` in `compose.yaml` together before anyone else can reach the instance.
- `AUTO_MIGRATE=false` stops the migration on boot. Run `docker compose exec app php artisan migrate --force` yourself.
- Uploaded images live in the `storage` volume. Use `MEDIA_DISK=s3` with the `AWS_*` keys for object storage.
- `SERVER_NAME=demgem.example.com` in `.env.docker` gets automatic HTTPS from Caddy. A bare `:8000` serves plain HTTP for a proxy in front.
- The container refuses to start with an empty `APP_KEY`, or with `BROADCAST_CONNECTION=reverb` and no Reverb credentials. It says how to fix either.

## The live table

Three services make it work: `app` serves the page, `worker` picks the broadcast off the queue, and `reverb` pushes it to every open browser. Stop any of them and the tracker falls back to a sixty-second poll, which is a worse table but never a broken one.

Before the first start, put three strings in `.env.docker`:

```sh
REVERB_APP_ID=$(openssl rand -hex 8)
REVERB_APP_KEY=$(openssl rand -hex 16)
REVERB_APP_SECRET=$(openssl rand -hex 16)
```

Everyone at the table needs to reach the websocket server, so `REVERB_HOST` and `REVERB_PORT` must be the address **their browser** uses, not the container name:

| Where you run it | `REVERB_HOST` | `REVERB_PORT` | `REVERB_SCHEME` |
|---|---|---|---|
| Your own laptop | `localhost` | `8080` | `http` |
| A box on the LAN | its LAN address | `8080` | `http` |
| A server, behind a proxy | your domain | `443` | `https` |

The page reads these at runtime and the bundle never sees them, so one built image serves any host. Publish port 8080 to the network the table is on, or put a proxy in front.

The app and the worker need a *second* address: they publish to the websocket server rather than connecting to it as a browser does, and inside Docker that is `reverb:8080` on the compose network. `.env.docker.example` sets `REVERB_PUBLISH_HOST`, `REVERB_PUBLISH_PORT`, and `REVERB_PUBLISH_SCHEME` for you. Leave them alone unless you move the service; on a single machine they are unnecessary and fall back to `REVERB_HOST`.

**Behind a proxy.** Forward `/app` and `/apps` to the `reverb` container on port 8080, with the websocket upgrade headers, and set `REVERB_HOST` to your domain with `REVERB_SCHEME=https` and `REVERB_PORT=443`. Everything else stays on the `app` container.

**Running without it.** Set `BROADCAST_CONNECTION=null` and stop the `reverb` service. Every screen keeps working on its poll.

**A sluggish table is a queue question, not a socket one.** Broadcasts are queued, so the wait is the worker picking the job up. Redis, which this stack uses, blocks on pop and pays nothing; the database queue driver adds a second or three.

## Reminders

A GM turns on a reminder email in campaign settings: a day, two days, or a week before each session with a date. Every member who wants one gets one, in the campaign's timezone, with a link back to the session to say whether they are coming. A member who said no is not reminded, and every member has their own switch on the members page.

**With `MAIL_MAILER=log`, which is the default, a reminder is written to the log and nobody receives it.** Set a real mailer before a GM turns reminders on. In `.env.docker`:

```sh
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=you
MAIL_PASSWORD=secret
MAIL_FROM_ADDRESS=demgem@example.com
```

The compose stack runs the scheduler for you. Outside Docker, run `php artisan schedule:work` beside the queue worker, or add `php artisan schedule:run` to cron every minute. To see what would go out right now, run `php artisan demgem:send-reminders` by hand.

**Discord.** A GM pastes a channel's webhook URL into campaign settings and sends a test message. From then on the channel gets one line when a recap is published and one when a reminder goes out: the campaign, the session, and a link. Never the recap itself. The URL is stored encrypted and never exported, and the server only ever posts to `discord.com`; any other address is refused when it is pasted.

Every session you can see is also available as a calendar feed. Get the link from your profile and subscribe to it in Google Calendar, Apple Calendar, or Outlook; it covers every campaign you belong to, and the times land in your own timezone. The feed carries the session's number, title, and campaign, and never its prep or recap.

## Take your data with you

A GM downloads the whole campaign from campaign settings, two ways:

- **The archive**, a zip. Inside it is `campaign.json`, every image and attachment beside it, and a Markdown folder with one file per page, foldered by type, with front matter and the wiki links left exactly as written. Obsidian opens that folder as a vault.
- **The JSON alone**, for anything that only wants the data.

Both carry every entity with its GM notes, every session with its prep, secrets, and recaps, plus quests, encounters, tables, maps, handouts, clocks, and the dice log. They leave out email addresses, invite links, and deleted things. `ExportCoverageTest` reads the schema and fails when a new campaign table is neither exported nor documented as excluded, so the export cannot quietly fall behind.

Either file imports back into any demgem, as a new campaign, from `/campaigns/import`. The JSON also imports from a terminal:

```sh
php artisan demgem:import path/to/campaign.json --user=you@example.com
```

The importer validates the whole file before it writes a row, remaps every id, and reports what it could not carry before the GM commits. It never fetches a URL found in the file and never uses a string from the archive as a path, so an untrusted file cannot reach the network or the disk. Four things stay behind on purpose: the members, because the file carries no email addresses, so the GM invites the party again; the viewer lists on entities shown to selected players, which import as GM-only rather than guess wider; the dice log, because the file cannot say who rolled; and the answers about sessions, who said yes and who turned up, for the same reason.

## Templates and body history

Open **Entity templates** from campaign settings to create a reusable Markdown outline for a character, location, quest, or any other entity type. On a new page, choose an outline and press **Use template**. Replacing an unsaved body requires confirmation. Only the body is copied; the page keeps its own name, visibility, tags, and other fields. Editing or deleting a template never changes existing pages.

**Body history** on an entity page lets a GM inspect and restore earlier text. Every changed body saved through the form or API preserves the body it replaces, including an empty body. History starts with the first body change after this feature is installed; earlier edits cannot be recovered. Automatic wiki-link replacements following a rename do not add revisions, and old snapshots retain their original link text. Restoring an old link may therefore leave it unresolved until edited.

History is GM-only, including on a player's own character: an earlier body may hold a secret removed before the page was revealed. Revision labels say who replaced the body and when, rather than claiming who originally wrote it. A restore changes only the body. This is not an undo for GM notes, media, visibility, or other fields.

Bodies are kept without expiry or individual deletion. Deleting an entity hides its history and leaves it out of exports; permanently deleting the entity or campaign removes it. Templates and history are carried in `campaign.json` inside an archive. The Markdown vault contains current pages only. Imported history keeps the replacement time and name, without linking that name to a local account.

The importer reads documents up to **25 MiB**, in both the browser and the Artisan command. Keeping all history can eventually exceed that limit. Exports still include every revision; they never silently drop history to fit. Larger imports and configurable retention are future work.

## The API

Every screen's data, in JSON, for a script or an assistant. Get a key from your profile: it reads what you can read in every campaign you belong to, and writes what you can write if you ticked **Can write** when you made it. Send it as a bearer token.

```sh
curl -H "Authorization: Bearer $DEMGEM_KEY" https://demgem.example/api/v1/me
```

| Method and path | What it does |
|---|---|
| `GET /api/v1/me` | You, and the campaigns you belong to with your role in each. |
| `GET /api/v1/campaigns` | The same campaigns, with each calendar's current date. |
| `GET /api/v1/campaigns/{id}` | One campaign. |
| `GET /api/v1/campaigns/{id}/entities` | Every entity you may see. Filter with `type=locations`, `tag=harbor`, or `q=bell`. Fifty a page. |
| `GET /api/v1/campaigns/{id}/entities/{entityId}` | One entity with its parent, children, and relationships, each through its own visibility gate. |
| `GET /api/v1/campaigns/{id}/search?q=` | Full-text search over what you may see. |
| `GET /api/v1/campaigns/{id}/sessions` | Every session you may see. A player gets the schedule and the published recap; a GM gets the prep too. |
| `GET /api/v1/campaigns/{id}/sessions/{number}` | One session, with scenes, secrets, and prepped entities for GM roles. |
| `POST /api/v1/campaigns/{id}/entities` | Create an entity. GM roles, write key. |
| `PATCH /api/v1/campaigns/{id}/entities/{entityId}` | Change one. GM roles on anything; a player on their own PC's body and record. |
| `PATCH /api/v1/campaigns/{id}/sessions/{number}` | Change a session's title, status, and notes. GM roles, write key. |
| `POST /api/v1/campaigns/{id}/sessions/{number}/publish-recap` | Publish the recap, and save a new one on the way if you send `recap`. |

Templates and history use the same campaign prefix, `/api/v1/campaigns/{id}`:

| Method and path | What it does |
|---|---|
| `GET /entity-templates` | GM-only summaries, 50 per page. Optional `type=character` filter uses the singular entity type. |
| `GET /entity-templates/{templateId}` | GM-only template with its body. |
| `POST /entity-templates` | Create with `name`, singular `type`, and optional `body`. GM role, write key. |
| `PATCH /entity-templates/{templateId}` | Change the template's name, type, or body. GM role, write key. |
| `GET /entities/{entityId}/body-revisions` | GM-only summaries, 25 per page, newest first. |
| `GET /entities/{entityId}/body-revisions/{revisionId}` | One previous body with `recorded_at` and `replaced_by_name`. GM-only. |
| `POST /entities/{entityId}/body-revisions/{revisionId}/restore` | Restore the body and return the updated entity. No payload. GM role, write key. |

`POST /entities` also accepts `template_id` for a matching entity type. Omit `body` to use the template's text; an explicitly supplied body, including null, takes precedence. A template is resolved within the campaign even when the body is overridden. `PATCH /entities/{entityId}` cannot apply a template. Markdown body whitespace is preserved.

The API creates and changes; it never deletes. A field your key may not set comes back as a 422 that names it, not a silent drop. Sixty requests a minute per key. A campaign you are not a member of is a 404, the same as on the web.

## Commands

| Command | What it does |
|---|---|
| `composer test` | Pest suite, SQLite in memory |
| `composer lint` | Pint |
| `composer analyse` | Larastan, level 6 |
| `npm run dev` | Vite with hot reload |
| `npm run build` | Production assets |

## Environment

| Key | Notes |
|---|---|
| `DB_CONNECTION=pgsql` | PostgreSQL. The local suite runs on SQLite in memory; CI runs the same suite on Postgres. |
| `SCOUT_DRIVER=database` | Search uses `ILIKE` on name and body. Swap for Meilisearch later. |
| `MEDIA_DISK=public` | Entity images and campaign covers. Use `s3` with the `AWS_*` keys in production. |

## Rules for contributors

- **Every campaign-scoped query runs inside a campaign context.** HTTP routes under `/campaigns/{campaign}` get it from `EnsureCampaignMember`. Livewire pages use `InteractsWithCampaign`. Jobs and commands set `CurrentCampaign` themselves. Never call `Entity::find()` from code that has no campaign.
- **Every list of entities goes through `Entity::visibleTo()`.** Index, search, autocomplete, backlinks, tag counts, children, breadcrumbs, sidebar counts. A new query on `entities` gets a visibility test.
- **GM notes never reach a player.** Not in HTML, not in a Livewire snapshot, not in search, not in a preview.
- **A broadcast carries ids and nothing else.** Every listener is a Livewire component that re-renders on the server under its own viewer's role, so there is no payload to filter and none to leak. A new event that carries data needs a very good reason and a test that names the payload.
- **Never bake a deploy-specific value into the Vite bundle.** The layout renders the websocket settings and the bundle reads them at runtime, so one built image serves any host.
- **Every list of sessions goes through `GameSession::visibleTo()`.** Index, dashboard cards, sidebar count, and the "Appears in sessions" panel on an entity.
- **A session's prep is GM-only.** Strong start, scenes, secrets, live notes, GM notes, and an unpublished recap. Only a published recap on a visible session reaches a player.
- **The API is the screens in JSON.** Every list goes through the same scope the page uses; every resource under `App\Http\Resources\Api` reads the viewer's role from `CurrentCampaign` and leaves a GM-only key out rather than nulling it; every write calls the action the form calls, behind the same policy. A new endpoint gets the leak tests its screen has, asserted on the JSON.
- **The server posts to Discord and to nothing else.** `DiscordWebhook` is the one place the host rule is spelled. A URL the server will request is validated there before it is stored, and a second destination is a change to that class with its own allow-list, never a field that takes any URL.
- **Markdown renders through `MarkdownRenderer` only.** Raw HTML is stripped and unsafe links are blocked there.
- **`entities.sheet_url` is the one user URL rendered as an `href` outside the renderer.** It is validated with `url:http,https` at write time and rendered with `rel="noopener noreferrer nofollow"`. A second such field needs the same two things.
- **A new campaign-scoped table joins the export in the same commit that creates it.** Give it a section in `ExportCampaign`, nest it in one, or write down why it stays behind. `ExportCoverageTest` reads the schema and fails until you do.
- **A list gets a child table; a scalar gets a column.** `quest_objectives` earned its table by being a list. Class, level, and sheet link are one-to-one with the row, so they are columns.
- **Never name a JSON column `attributes`.** It shadows Eloquent's own property inside every model method. The key-value column is `custom_fields`, and it is `text` rather than `json` because Scout's database engine runs `ilike` against it and PostgreSQL has no `ilike` for `json`.
- **A nested Livewire component re-checks membership itself.** `InteractsWithCampaign` does that per component, not per page, so a child that writes needs the trait too.
- **The game session table is `game_sessions`.** `sessions` belongs to the database session driver.
- Tests are Pest feature tests. Run the narrowest set that covers your change, then the suite.

## Timezones

A campaign has one timezone, set in campaign settings. Session times are stored in UTC and shown in that zone, and reminder emails use it. The calendar feed sends UTC and every calendar app converts, so a player in another zone sees the session at their own local time there. Per-user timezones inside the app are a later feature.

## Content licensing

demgem's code is MIT. The creature data in `database/srd/` is not: it is System Reference Document 5.2.1 material, published by Wizards of the Coast LLC under [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/legalcode).

> This work includes material taken from the System Reference Document 5.2.1 ("SRD 5.2.1") by Wizards of the Coast LLC and is licensed under the Creative Commons Attribution 4.0 International License, available at https://creativecommons.org/licenses/by/4.0/legalcode.

That notice renders on every compendium screen and on every stat block the API returns. `database/srd/ATTRIBUTION.md` says where it has to appear; `database/srd/README.md` records the provenance and the checksum that pins the dataset.

CC BY 4.0 licenses the text and grants no trademark rights. Dungeons & Dragons, D&D and their logos are trademarks of Wizards of the Coast LLC; demgem uses none of them, and nothing here implies endorsement. The ruleset is named "SRD 5.2.1 (2024 rules)" for that reason.

Only SRD content is in the dataset. A campaign export carries a stat block as a `{ruleset, slug}` reference and never its prose, so an export redistributes nothing.

`php artisan demgem:import-srd` loads the compendium. `php artisan db:seed` and the Docker entrypoint both run it, and it is idempotent.

## License

MIT. See [LICENSE](LICENSE).
