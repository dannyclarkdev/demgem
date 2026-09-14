# Contributing

The rules the codebase holds itself to, the commands, the environment keys, and timezones.

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

## Timezones

A campaign has one timezone, set in campaign settings. Session times are stored in UTC and shown in that zone, and reminder emails use it. The calendar feed sends UTC and every calendar app converts, so a player in another zone sees the session at their own local time there. Per-user timezones inside the app are a later feature.
