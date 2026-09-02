# demgem

An open source campaign manager for Dungeon Masters and Game Masters. Session-first: prep, play, recap, repeat. A campaign wiki with wiki links, per-entity visibility, and player views supports that loop.

Built with Laravel 13, Livewire 4, Alpine, and Tailwind 4. PostgreSQL in production. Fully custom UI.

## Status

Slice 1 is done: accounts, campaigns, members with roles, invite links, entities (characters, locations, factions, items, quests, notes) with Markdown, `[[wiki links]]`, backlinks, GM-only notes, visibility, tags, nesting, images, and search. Sessions, quests with objectives, the initiative tracker, and dice come next. See `docs/plans/`.

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

Optional demo world with a GM and a player:

```sh
php artisan db:seed --class=DemoCampaignSeeder
```

It creates `dev@demgem.test` and `tobin@demgem.test`, both with the password `password`.

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
| `DB_CONNECTION=pgsql` | PostgreSQL. Tests use SQLite; add a Postgres CI job before you rely on Postgres-only features. |
| `SCOUT_DRIVER=database` | Search uses `ILIKE` on name and body. Swap for Meilisearch later. |
| `MEDIA_DISK=public` | Entity images and campaign covers. Use `s3` with the `AWS_*` keys in production. |

## Rules for contributors

- **Every campaign-scoped query runs inside a campaign context.** HTTP routes under `/campaigns/{campaign}` get it from `EnsureCampaignMember`. Livewire pages use `InteractsWithCampaign`. Jobs and commands set `CurrentCampaign` themselves. Never call `Entity::find()` from code that has no campaign.
- **Every list of entities goes through `Entity::visibleTo()`.** Index, search, autocomplete, backlinks, tag counts, children, breadcrumbs, sidebar counts. A new query on `entities` gets a visibility test.
- **GM notes never reach a player.** Not in HTML, not in a Livewire snapshot, not in search, not in a preview.
- **Markdown renders through `MarkdownRenderer` only.** Raw HTML is stripped and unsafe links are blocked there.
- Tests are Pest feature tests. Run the narrowest set that covers your change, then the suite.

## Content licensing

The first ruleset module will use the D&D System Reference Document 5.2, licensed CC-BY-4.0 by Wizards of the Coast. Attribution text ships with that module. No non-SRD content is imported.

## License

MIT. See [LICENSE](LICENSE).
