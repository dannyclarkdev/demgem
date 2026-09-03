# demgem

An open source campaign manager for Dungeon Masters and Game Masters. Session-first: prep, play, recap, repeat. A campaign wiki with wiki links, per-entity visibility, and player views supports that loop.

Built with Laravel 13, Livewire 4, Alpine, and Tailwind 4. PostgreSQL in production. Fully custom UI.

## Status

Slice 1 is done: accounts, campaigns, members with roles, invite links, entities (characters, locations, factions, items, quests, notes) with Markdown, `[[wiki links]]`, backlinks, GM-only notes, visibility, tags, nesting, images, and search.

Slice 2 is done: sessions with a number, title, date, and status; a prep screen with a strong start, ordered scenes, secrets and clues, and four entity buckets; a run screen with autosaving live notes and one-click secret reveals; and a recap the GM publishes on purpose. Unrevealed secrets carry into the next session.

Slice 3 is done: quests with a status, a giver, rewards, and an ordered objective checklist that records the session each step was finished in; an initiative tracker with hit points, conditions, rounds, and a turn marker that survives a refresh; a dice roller with keep-highest, keep-lowest, and advantage; and weighted random tables that can nest one inside another. The tracker sits on the run screen, and dice and tables live in a drawer beside it.

Slice 5 is done: **the live table**. A GM advances the turn and every screen at the table changes at once, over one authorised websocket channel per campaign. `/table` is the player's screen: the turn order, the round, whose turn it is, and each combatant the GM chose to show, with health as a word rather than a number. Dice are shared, so a player rolls at their end of the table and everyone sees it, and a GM can still roll behind the screen. The strip at the top of both screens says who has the campaign open.

Slice 4 is done, and the MVP with it: a character record with a class, a level, and a link to the sheet a player actually plays from, editable by that player; the party on the dashboard and behind a filter on the character index; **The story so far**, every recap in order, with drafts and missing recaps shown to the GM only; key-value fields on any entity, searchable; a streamed JSON export of a whole campaign; and a Docker stack a self-hoster can run with one command. See `docs/plans/`.

## Local setup

Requirements: PHP 8.4, Composer, Node 20+, PostgreSQL 17+.

```sh
composer install
cp .env.example .env
php artisan key:generate
# Point DB_* at your Postgres, then:
php artisan migrate
php artisan storage:link
npm install && npm run build
```

For the live table locally, run a queue worker and Reverb beside the app:

```sh
php artisan queue:work
php artisan reverb:start
```

`php artisan dev` runs both for you, along with Vite. Without them, screens fall back to their sixty-second poll.

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
| `worker` | `queue:work`. It carries the live table's broadcasts, so the table is only as quick as this container. |
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

## Take your data with you

A GM downloads the whole campaign as JSON from campaign settings: every entity with its GM notes, every session with its prep, secrets, and recaps, plus quests, encounters, tables, and the dice log.

The file leaves out email addresses, invite links, and deleted things, and it carries images as links rather than files. `ExportCoverageTest` reads the schema and fails when a new campaign table is neither exported nor documented as excluded, so the export cannot quietly fall behind.

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
- **Markdown renders through `MarkdownRenderer` only.** Raw HTML is stripped and unsafe links are blocked there.
- **`entities.sheet_url` is the one user URL rendered as an `href` outside the renderer.** It is validated with `url:http,https` at write time and rendered with `rel="noopener noreferrer nofollow"`. A second such field needs the same two things.
- **A new campaign-scoped table joins the export in the same commit that creates it.** Give it a section in `ExportCampaign`, nest it in one, or write down why it stays behind. `ExportCoverageTest` reads the schema and fails until you do.
- **A list gets a child table; a scalar gets a column.** `quest_objectives` earned its table by being a list. Class, level, and sheet link are one-to-one with the row, so they are columns.
- **Never name a JSON column `attributes`.** It shadows Eloquent's own property inside every model method. The key-value column is `custom_fields`, and it is `text` rather than `json` because Scout's database engine runs `ilike` against it and PostgreSQL has no `ilike` for `json`.
- **A nested Livewire component re-checks membership itself.** `InteractsWithCampaign` does that per component, not per page, so a child that writes needs the trait too.
- **The game session table is `game_sessions`.** `sessions` belongs to the database session driver.
- Tests are Pest feature tests. Run the narrowest set that covers your change, then the suite.

## Timezones

A campaign has one timezone, set in campaign settings. Session times are stored in UTC and shown in that zone. Per-user timezones are a later feature.

## Content licensing

The first ruleset module will use the D&D System Reference Document 5.2, licensed CC-BY-4.0 by Wizards of the Coast. Attribution text ships with that module. No non-SRD content is imported.

## License

MIT. See [LICENSE](LICENSE).
