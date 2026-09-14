---
date: 2026-09-14
topic: github-readme
---

# A GitHub landing page for demgem

## What we are building

The README becomes a landing page of about 150 lines. The 35 KB of content it holds today, a slice-by-slice log and the operator manual, moves into `docs/guide/`, one page per topic, with the text kept as it is apart from a title and a short lead. Nothing is deleted.

## Why

A visitor to the GitHub page today reads twenty-eight paragraphs that each begin "Slice N is done" before they reach the setup. There is no image, no badge, and no summary of what the app does. The README is a changelog, and the project needs a front door.

## The README, in order

1. A centered header: an SVG wordmark in `docs/readme/`, the one-sentence pitch, and a badge row. The badges are the CI status from `.github/workflows/ci.yml`, the license, PHP 8.4, Laravel 13, Livewire 4, and "Self-host with Docker". All come from shields.io. No external account is needed.
2. A hero screenshot of the run screen with the initiative tracker, full width.
3. "What it does", a two-column HTML table of about twelve cells. Each cell has a small icon, a bold title, and two lines: sessions, the live table, the player screen, maps, handouts and clocks, the compendium and fights, the 5e sheet, the world calendar, quests and arcs, the export to an Obsidian vault, the API, and Discord with scheduling.
4. Three screenshots in a row: the dashboard, a map, and `/table`.
5. "Run it in three commands", the Docker block, and a one-line pointer to the local setup page.
6. "Documentation", a table of links into `docs/guide/`.
7. "Built with", "Contributing" in three lines with a link to the rules page, "Content licensing" as one paragraph with the SRD notice, and "License".

Content licensing stays in the README because `database/srd/ATTRIBUTION.md` asks for the notice to be visible.

## The guide pages

| File | Holds |
| --- | --- |
| `docs/guide/changelog.md` | The slice log, slices 1 to 28, unchanged. |
| `docs/guide/local-setup.md` | Requirements, the install block, the queue and Reverb note, the demo seeder. |
| `docs/guide/docker.md` | The compose stack, the service table, and the operator notes. |
| `docs/guide/live-table.md` | The three services, the Reverb address table, proxies, and the queue note. |
| `docs/guide/reminders-and-discord.md` | Reminder emails, the mailer, the scheduler, the Discord webhook, the calendar feed. |
| `docs/guide/export-and-import.md` | The archive, the JSON, the importer, and what stays behind. |
| `docs/guide/templates-and-history.md` | Entity templates and body history. |
| `docs/guide/api.md` | The key, the endpoint tables, and the API rules. |
| `docs/guide/contributing.md` | The rules for contributors, the commands table, the environment table, and timezones. |

## Screenshots

Four PNG files under `docs/readme/`, captured at 1440 px wide from a local run of the demo world, signed in as the demo GM: the run screen with the tracker, the campaign dashboard, a map with pins, and `/table`. The demo seeder already builds the fight, the maps, and the pins, so no manual setup is needed.

## Out of scope

No code changes. No new dependencies. No committed test. A shell loop checks that every relative link in the README and the guide pages resolves to a file before the commit.
