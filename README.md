<p align="center">
  <img src="docs/readme/wordmark.svg" width="560" alt="demgem">
</p>

<p align="center">
  <strong>An open source campaign manager for Dungeon Masters and Game Masters.</strong><br>
  Session-first: prep, play, recap, repeat. The wiki serves the session, not the other way around.
</p>

<p align="center">
  <a href="https://github.com/dannyclarkdev/demgem/actions/workflows/ci.yml"><img src="https://github.com/dannyclarkdev/demgem/actions/workflows/ci.yml/badge.svg" alt="CI"></a>
  <a href="LICENSE"><img src="https://img.shields.io/github/license/dannyclarkdev/demgem?color=2ea44f" alt="MIT license"></a>
  <img src="https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white" alt="PHP 8.4">
  <img src="https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white" alt="Laravel 13">
  <img src="https://img.shields.io/badge/Livewire-4-FB70A9?logo=livewire&logoColor=white" alt="Livewire 4">
  <a href="docs/guide/docker.md"><img src="https://img.shields.io/badge/Self--host-Docker-2496ED?logo=docker&logoColor=white" alt="Self-host with Docker"></a>
</p>

<p align="center">
  <a href="#run-it-in-three-commands">Run it</a> ·
  <a href="#what-it-does">What it does</a> ·
  <a href="#documentation">Documentation</a> ·
  <a href="docs/guide/api.md">API</a> ·
  <a href="docs/guide/changelog.md">Changelog</a>
</p>

<br>

<p align="center">
  <img src="docs/readme/run.png" alt="The run screen: a strong start, the initiative tracker with hit points and conditions, the encounter budget, and the card that puts a handout or a map on the party's screen." width="100%">
</p>

<p align="center"><sub>The run screen. Everything the GM needs at the table, on one page, with the fight in the middle of it.</sub></p>

<br>

## Why demgem

Most tools cluster in two groups. World wikis keep the encyclopedia and treat the session as an afterthought. Virtual tabletops keep the battle map and leave the prep somewhere else. demgem owns the loop a GM lives in every week: **prep** a session from a strong start and a handful of scenes, **play** it with a tracker that every screen at the table follows, **recap** it on purpose, and carry the loose ends into the next one.

The core is system agnostic. Rulesets plug in as modules, and the first is the SRD 5.2.1 ruleset, with 330 creatures, an encounter budget, and a character sheet. A campaign on no ruleset keeps every other feature and writes its own creatures.

## What it does

<table>
  <tr>
    <td width="50%" valign="top">
      <h3>🎬 Sessions, prepped and run</h3>
      A strong start, ordered scenes, secrets and clues, and four buckets of NPCs, locations, monsters, and treasure. The run screen carries live notes that autosave, one-press secret reveals, and a recap the GM publishes on purpose. Unrevealed secrets carry into the next session.
    </td>
    <td width="50%" valign="top">
      <h3>⚡ The live table</h3>
      The GM advances the turn and every screen at the table changes at once, over one authorised websocket channel per campaign. Players see the turn order, the round, and health as a word. Dice are shared, and a GM can still roll behind the screen.
    </td>
  </tr>
  <tr>
    <td valign="top">
      <h3>🖥️ The player screen</h3>
      <code>/screen</code> is one page for the television at the end of the table: no sidebar, no controls, only what the whole party may see. The GM puts a fight, a handout, a map, or the clocks on it from the run screen.
    </td>
    <td valign="top">
      <h3>⚔️ Fights that price themselves</h3>
      An initiative tracker with hit points, conditions, concentration, death saves, legendary and lair actions, and a budget from Trivial to Deadly. Add a creature from the compendium and its numbers come with it. Duplicate a whole fight in one press.
    </td>
  </tr>
  <tr>
    <td valign="top">
      <h3>📖 A compendium, shipped and homebrew</h3>
      All 330 SRD creatures, filterable by type and challenge, plus your own written in Markdown. <strong>Copy to my campaign</strong> turns any shipped creature into an editable one.
    </td>
    <td valign="top">
      <h3>📜 A 5e character sheet</h3>
      Six scores, proficiencies with expertise, hit dice, spell slots, and every modifier computed on read and never stored. The player edits it, takes damage on it, and presses <strong>Long rest</strong>.
    </td>
  </tr>
  <tr>
    <td valign="top">
      <h3>🗺️ Maps, handouts, and clocks</h3>
      A map is an entity with an image the viewer pans and zooms. Pins point at any entity, reveal one at a time, and nest one map inside another. A handout holds up to ten files. A progress clock is a dial the GM fills while the party watches.
    </td>
    <td valign="top">
      <h3>🌙 The world's own calendar</h3>
      Name the months, set the week, hang a moon or two, add a leap rule and an era. Every member reads today on the dashboard and on a month grid with the moons on every day. Events and sessions land on a timeline in world order.
    </td>
  </tr>
  <tr>
    <td valign="top">
      <h3>🧭 A wiki that gates itself</h3>
      Characters, locations, factions, items, quests, arcs, journals, and notes with Markdown, <code>[[wiki links]]</code>, backlinks, tags, nesting, images, and search. GM notes and <code>:::secret</code> fences never reach a player: not on the page, not in search, not in the API.
    </td>
    <td valign="top">
      <h3>🧾 Quests, arcs, and the party's log</h3>
      Quests with an objective checklist that records the session each step was finished in. Story arcs that file quests and sessions into chapters. A decision log, a ledger of coin and items, downtime per character, faction reputation, family trees, and relationships between any two pages.
    </td>
  </tr>
  <tr>
    <td valign="top">
      <h3>📦 Take your data with you</h3>
      One archive holds the JSON, every image and attachment, and the whole campaign as Markdown with front matter that Obsidian opens as a vault. Either file imports back into any demgem, validated before a row is written.
    </td>
    <td valign="top">
      <h3>🔌 An API, Discord, and scheduling</h3>
      <code>/api/v1</code> serves every screen's data in JSON behind the same policies as every page. A Discord webhook gets one line when a recap is published. Members RSVP, vote on candidate times, and get one reminder email per session.
    </td>
  </tr>
</table>

<br>

<table>
  <tr>
    <td width="33%"><img src="docs/readme/dashboard.png" alt="The campaign dashboard: the day in the world with two moons, the next session, the latest recap, the party, and the quests in play."></td>
    <td width="33%"><img src="docs/readme/calendar.png" alt="The world calendar: a month grid with the phase of both moons on every day and the sessions on the days the party spent there."></td>
    <td width="33%"><img src="docs/readme/table.png" alt="The player's table screen: the round, whose turn it is, health as a fraction for the party and hidden for the creatures the GM has not shown."></td>
  </tr>
  <tr>
    <td align="center"><sub>The dashboard</sub></td>
    <td align="center"><sub>The calendar</sub></td>
    <td align="center"><sub>The table, as a player sees it</sub></td>
  </tr>
</table>

## Run it in three commands

Docker 24 or newer with Compose v2. Nothing else on the host: no PHP, no Node, no PostgreSQL.

```sh
cp .env.docker.example .env.docker
docker compose run --rm --no-deps app php artisan key:generate --show   # paste the key into APP_KEY in .env.docker
docker compose up -d
```

Open <http://localhost:8000> and register. The first account is an ordinary account, and after it every account arrives through an invite link. The stack runs the app, a queue worker, the scheduler, the Reverb websocket server, PostgreSQL, and Redis. The [Docker guide](docs/guide/docker.md) covers ports, HTTPS, object storage, and running without the websocket.

For a development machine with PHP 8.4, Node 20, and PostgreSQL, see [Local setup](docs/guide/local-setup.md). A demo world with a GM and a player is one seeder away.

## Documentation

| Guide | What it covers |
| --- | --- |
| [Local setup](docs/guide/local-setup.md) | Requirements, the install, the queue and Reverb beside the app, the demo seeder. |
| [Run it with Docker](docs/guide/docker.md) | The compose stack, every service, and the operator switches. |
| [The live table](docs/guide/live-table.md) | The websocket channel, the Reverb addresses, proxies, and the poll it falls back to. |
| [Reminders, Discord, and the calendar feed](docs/guide/reminders-and-discord.md) | Reminder emails, the mailer, the scheduler, the webhook, and the per-user feed. |
| [Take your data with you](docs/guide/export-and-import.md) | The archive, the JSON, the Obsidian vault, and the importer. |
| [Templates and body history](docs/guide/templates-and-history.md) | Reusable outlines per entity type, and every replaced body kept. |
| [The API](docs/guide/api.md) | Keys, every endpoint, and the rules the API holds itself to. |
| [Contributing](docs/guide/contributing.md) | The rules of the codebase, the commands, the environment keys, and timezones. |
| [Changelog](docs/guide/changelog.md) | What each of the 28 slices added, with a link to the plan behind it. |

## Built with

[Laravel 13](https://laravel.com), [Livewire 4](https://livewire.laravel.com), [Alpine](https://alpinejs.dev), and [Tailwind 4](https://tailwindcss.com), on PHP 8.4. [Reverb](https://reverb.laravel.com) carries the live table. PostgreSQL in production, SQLite in the test suite. The UI is custom from the ground up, in a serif and an ember.

## Contributing

Issues and pull requests are welcome. Read [Contributing](docs/guide/contributing.md) first: it holds the rules every change is checked against, and most of them are about what a player must never see. Tests are Pest feature tests, and CI runs Pint, Larastan, and the suite on PostgreSQL, then builds and boots the Docker image.

## Content licensing

demgem's code is MIT. The creature data in `database/srd/` is not: it is System Reference Document 5.2.1 material, published by Wizards of the Coast LLC under [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/legalcode).

> This work includes material taken from the System Reference Document 5.2.1 ("SRD 5.2.1") by Wizards of the Coast LLC and is licensed under the Creative Commons Attribution 4.0 International License, available at https://creativecommons.org/licenses/by/4.0/legalcode.

That notice renders on every compendium screen and on every stat block the API returns. CC BY 4.0 licenses the text and grants no trademark rights. Dungeons & Dragons, D&D and their logos are trademarks of Wizards of the Coast LLC; demgem uses none of them, and nothing here implies endorsement. Only SRD content is in the dataset, and a campaign export carries a stat block as a reference and never its prose, so an export redistributes nothing. `database/srd/ATTRIBUTION.md` says where the notice has to appear, and `database/srd/README.md` records the provenance and the checksum that pins the dataset.

## License

MIT. See [LICENSE](LICENSE).
