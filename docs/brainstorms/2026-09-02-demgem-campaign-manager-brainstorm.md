---
date: 2026-09-02
topic: demgem-campaign-manager
---

# demgem: Open Source Campaign Manager for DMs and GMs

## What We're Building

demgem is an open source Laravel application that helps a Dungeon Master or Game Master run a tabletop RPG campaign. It is session-first. The core loop is prep, play, recap, repeat. A campaign wiki, quest log, live table tools, and player views support that loop.

The core is game-system agnostic. Rulesets plug in as modules. The first ruleset is D&D 5e (2024 rules) built on the SRD 5.2, which is licensed CC-BY-4.0. demgem will be self-hostable and will also run as a hosted service operated by the project author.

## Who It Is For

- The DM or GM who preps in Notion, Obsidian, or Google Docs, tracks combat in a separate app, and posts recaps in Discord. demgem replaces that stack.
- Players who want to read the lore they know, check the quest log, and see when the next session is.
- Later: the co-GM, the spectator, and an AI assistant acting through the API.

## Positioning

Existing tools cluster in two groups.

| Group | Examples | Focus |
|---|---|---|
| World wikis | World Anvil, Kanka, LegendKeeper | The encyclopedia. Sessions are an afterthought. |
| Virtual tabletops | Foundry, Roll20, Owlbear Rodeo | The battle map. Prep and lore live elsewhere. |
| Single tools | Improved Initiative, Shieldmaiden, Kobold Fight Club | One job. No campaign memory. |

The gap is the loop a DM lives in every week. demgem owns prep, play, and recap. The wiki serves the session, not the other way around. The Lazy DM prep model (strong start, scenes, secrets and clues, locations, NPCs, monsters, treasure) is the backbone of the prep screen.

## Decisions Made

These came out of the first conversation on 2026-09-02.

| Decision | Choice | Rationale |
|---|---|---|
| First ruleset | D&D 5e 2024 rules, via SRD 5.2 (CC-BY-4.0) | Largest audience. Author's own table. Open license. |
| Core design | Game-system agnostic core with ruleset modules | Author wants demgem to serve other systems. Design the seams now, build one module. |
| Hosting | Self-host and a hosted service run by the author | Same schema. Campaign is the tenant boundary. |
| UI | Fully custom Livewire, Alpine, and Tailwind | Author rejects Filament. Product identity matters. No paid UI dependency, so contributors can build. |
| Product shape | Session-first | See Positioning. |

## Feature Inventory

Phase tags: **MVP** ships in the first usable release. **P2** follows once the loop works. **P3** is later.

### Campaign bible

| Feature | Phase | Notes |
|---|---|---|
| Campaigns, many per user, with game system and cover image | MVP | |
| Entities: character (PC and NPC), location, faction, item, note, quest | MVP | One table, type column. See Architecture. |
| Entities: creature, event, deity, race, organization chart | P2 | |
| Nested locations | MVP | parent_id on entities. |
| Markdown body with wiki links `[[Name]]` and `@` autocomplete | MVP | |
| Backlinks ("mentioned in") | MVP | Rebuilt on save. |
| Private DM notes field on every entity | MVP | Never shown to players. |
| Visibility per entity: DM only, all players, selected players | MVP | |
| Secret blocks inside the body, hidden from players | P2 | `:::secret` fence. |
| Tags | MVP | |
| Custom key-value attributes | MVP | JSON column. |
| Full-text search across the campaign | MVP | Laravel Scout, database driver first. |
| One image per entity | MVP | Spatie media library. |
| Image galleries and file attachments | P2 | |
| Entity relationships with labels | P2 | Simple list first, graph view later. |
| Entity templates | P2 | |
| Body version history | P2 | |
| Custom calendar: months, weekdays, moons, leap rules, current in-game date | P2 | |
| Timeline of events tied to calendar dates | P2 | |
| Family trees | P3 | |

### Session loop

| Feature | Phase | Notes |
|---|---|---|
| Sessions with number, title, scheduled time, status | MVP | planned, played, cancelled |
| Prep screen with Lazy DM steps | MVP | Each step links entities. |
| Scenes as an ordered list with notes and linked entities | MVP | |
| Secrets and clues with reveal tracking | MVP | Mark revealed in a session. |
| Unrevealed secrets carry forward to the next session | MVP | Small feature, big DM value. |
| Live notes with autosave during play | MVP | |
| Player-visible recap and private post-session notes | MVP | |
| Session in-game date range | P2 | Needs calendar. |
| RSVP and attendance | P2 | |
| Reminder emails and iCal feed | P2 | |
| XP or milestone log per session | P2 | |
| Availability polling for the next date | P2 | |

### Live table tools

| Feature | Phase | Notes |
|---|---|---|
| Initiative tracker: combatants, initiative, HP, AC, conditions, rounds, turn | MVP | System-light. Works for any game. |
| Concentration, legendary and lair actions, death saves | P2 | 5e ruleset adds these. |
| Player view of the tracker | P2 | Reverb broadcast. |
| Dice roller with formulas, advantage, history | MVP | |
| Shared dice log visible to players | P2 | Reverb. |
| Custom random tables, weighted, nested | MVP | |
| Built-in generators: names, NPC, tavern, weather, loot, rumor | P2 | Ship as random tables. |
| Encounter builder with difficulty math | P2 | Needs ruleset. |
| Compendium: monsters, spells, items, conditions from the SRD | P2 | Ruleset module. |
| Homebrew stat blocks | P2 | JSON stat block, Markdown fallback. |
| Progress clocks and countdowns | P2 | |
| Handouts with reveal | P2 | |
| Player screen: push an image or map to a second display | P3 | |
| Ambient audio | Non-goal | Link out. |

### Players

| Feature | Phase | Notes |
|---|---|---|
| Invite by link or email | MVP | |
| Roles: owner, co-GM, player, spectator | MVP | |
| Player campaign view: visible lore, quest log, recaps, schedule | MVP | |
| Simple character record: name, class, level, link to external sheet | MVP | |
| Players can edit their own PC entity | MVP | |
| Player journals and shared notes | P2 | |
| Party inventory and gold ledger | P2 | |
| Full 5e character sheet | P3 | Ruleset module. |
| Downtime tracking | P3 | |

### Quests and plot

| Feature | Phase | Notes |
|---|---|---|
| Quests with status, giver, objectives, rewards, linked entities | MVP | available, active, completed, failed |
| Story arcs that group quests and sessions | P2 | |
| Faction reputation trackers | P2 | |
| Decision and consequence log | P2 | |

### Maps

| Feature | Phase | Notes |
|---|---|---|
| Upload image maps, pin entities as markers | P2 | |
| Nested maps from world to building | P2 | |
| Separate DM and player marker layers | P2 | |
| Token movement, fog of war | Non-goal | demgem is not a VTT. |

### Portability and integrations

| Feature | Phase | Notes |
|---|---|---|
| JSON export of a whole campaign | MVP | Open source promise: your data leaves with you. |
| Markdown export with front matter, in a zip | P2 | Obsidian can open it. |
| Import from an Obsidian vault | P2 | |
| Import from Kanka and World Anvil | P3 | |
| REST API with Sanctum tokens | P2 | |
| Webhooks | P2 | |
| Discord login | P2 | Socialite. |
| Discord webhook for recaps and reminders | P2 | No bot needed. |
| Discord bot | P3 | |
| MCP server so an AI assistant can read lore and log notes | P3 | |
| Optional AI helpers, bring your own key | P3 | Provider-agnostic. |

### Platform

| Feature | Phase | Notes |
|---|---|---|
| Docker Compose for self-hosters | MVP | app, postgres, redis |
| Multi-user on one instance | MVP | |
| Dark mode, tablet-friendly layout | MVP | DMs run games from a tablet. |
| Hosted service with plans and billing | P2 or P3 | Cashier. Build the product first. |
| Per-campaign storage quotas | P2 | Needed before hosted launch. |
| Localization | P2 | |
| PWA with offline read | P3 | |

## Approaches Considered

### Approach A: Session-first campaign runner (chosen)

The session prep, play, and recap screens are the home page. The wiki, quests, and tools attach to sessions.

**Pros:** Clear differentiation. Matches how DMs work week to week. Small MVP.
**Cons:** The wiki must still be good, or DMs keep World Anvil open beside it.
**Best when:** The author dogfoods it at a weekly table.

### Approach B: World wiki first

Entities, relations, and maps are the product. Sessions are one entity type.

**Pros:** Proven model. Easy to explain.
**Cons:** Crowded market. Kanka already does this in Laravel. Hard to win on features.

### Approach C: Table tools first

Combat tracker, dice, and generators. Add campaign memory later.

**Pros:** Fast to build. Fun to demo.
**Cons:** Many free options. Low retention. Campaign memory bolted on later is awkward.

## Recommended Stack

| Layer | Choice | Notes |
|---|---|---|
| Framework | Laravel 13, PHP 8.4 | Scaffolded 2026-09-02. |
| UI | Livewire (current major), Alpine, Tailwind 4, Vite 8 | Fully custom components. No Filament. No paid UI kit. |
| Database | PostgreSQL | Postgres only at first. SQLite support is an open question. |
| Cache and queues | Redis, Horizon | |
| Real-time | Laravel Reverb | P2. MVP tracker uses Livewire polling. |
| Search | Laravel Scout | Database driver first. Meilisearch optional later. |
| Auth | Fortify, Socialite (Discord, Google), 2FA | |
| Media | Spatie media library, S3-compatible storage | S3 from day one because the author hosts. |
| Markdown | league/commonmark with custom extensions | Wiki links, secret blocks, mentions. |
| Testing and quality | Pest, Pint, Larastan | |
| Packaging | Docker Compose, one image with FrankenPHP or Octane | |
| License | AGPL-3.0 recommended | Keeps hosted forks open. Author hosts, so this protects the project. MIT is the alternative. |

## Architecture Notes

### Data model sketch

```
users
campaigns            owner_id, name, slug, ruleset, settings json, in_game_date
campaign_members     campaign_id, user_id, role (owner|co_gm|player|spectator)

entities             campaign_id, type, name, slug, parent_id, body (md),
                     dm_notes (md), visibility (dm|players|selected),
                     attributes json, stat_block json null, created_by
entity_viewers       entity_id, user_id            -- for visibility = selected
entity_links         from_entity_id, to_entity_id, label, is_mutual
mentions             source_type, source_id, entity_id   -- backlinks
tags, taggables

sessions             campaign_id, number, title, scheduled_at, status,
                     strong_start (md), live_notes (md), recap (md),
                     dm_notes (md), in_game_start, in_game_end
session_scenes       session_id, position, title, notes (md)
session_entities     session_id, entity_id, role (npc|location|monster|treasure)
secrets              campaign_id, session_id null, text, revealed_at,
                     revealed_session_id null
session_attendance   session_id, user_id, status

quests               campaign_id, title, status, giver_entity_id, body (md),
                     rewards, visibility, arc_id null
quest_objectives     quest_id, position, text, completed_at

encounters           campaign_id, session_id null, name, status, round
combatants           encounter_id, entity_id null, name, initiative, hp,
                     max_hp, ac, conditions json, position, player_visible

random_tables        campaign_id null (global), name, dice
random_table_entries table_id, weight, text, nested_table_id null
dice_rolls           campaign_id, user_id, formula, result json

maps                 campaign_id, entity_id null, parent_map_id null, image
map_markers          map_id, entity_id, x, y, visibility
calendars            campaign_id, months json, weekdays json, moons json
calendar_events      calendar_id, entity_id null, date, title

compendium_entries   ruleset, type, slug, name, data json, source, license
media                spatie
```

Every campaign-scoped table carries campaign_id. A global scope plus policies enforce isolation. There is no tenant-per-database. A user plus a campaign is the tenancy model. Do not add an organization layer until a real need appears.

### Visibility model

- Entity level: `visibility` enum. `selected` uses the entity_viewers pivot.
- Field level: `dm_notes` on every entity is always private.
- Block level (P2): a `:::secret` fence in Markdown. The renderer strips it for players.
- Sessions: `recap` is player-visible when the session is played. `live_notes` and `dm_notes` are private.

### Ruleset contract

Rulesets are code, not tables. A `Ruleset` interface in the core, with a `Generic` implementation that returns nothing special and an `Srd5e2024` implementation as the first real module.

```php
interface Ruleset
{
    public function key(): string;                       // 'generic', 'srd-5e-2024'
    public function statBlockSchema(): ?array;           // JSON schema for entities.stat_block
    public function compendiumTypes(): array;            // ['monster', 'spell', 'item', 'condition']
    public function encounterDifficulty(Party $party, Collection $monsters): ?Difficulty;
    public function combatantFields(): array;            // extra tracker columns
    public function characterSummaryFields(): array;     // class, level, ancestry
}
```

Keep rulesets inside the monolith under `app/Rulesets/`. Extract to packages only when a second external ruleset exists.

### Content licensing

- SRD 5.2 (2024 rules) is CC-BY-4.0. Ship the attribution text in the app and the README.
- Do not import non-SRD content. No 5e.tools dumps. No scraped D&D Beyond data.
- Open5e publishes SRD data under CC-BY. Check its 5.2 coverage before you write an importer.
- Pathfinder 2e content is under the ORC license and can be a P3 module.

### Markdown pipeline

- Store Markdown. Render with league/commonmark.
- `[[Entity Name]]` and `[[Entity Name|label]]` resolve to entities on save. Unresolved links render as "create this entity" prompts.
- `@` in the editor opens an autocomplete that inserts a wiki link.
- Save rebuilds the mentions table for backlinks.
- MVP editor: a textarea with a toolbar and live preview. WYSIWYG via Tiptap with a Markdown serializer is an open question for P2.

### Hosted service notes

- Media on S3-compatible storage. Image conversions run in the queue.
- Rate limits on the API and on uploads.
- Storage quota per campaign before public launch.
- Cashier for plans. Free tier for self-hosters is the code itself.

## Roadmap

### MVP (v0.1)

1. Auth with Fortify. Campaigns. Members and invite links. Roles.
2. Entities: six types, Markdown body, wiki links, backlinks, DM notes, visibility, tags, attributes, one image, search.
3. Sessions: schedule, Lazy DM prep, scenes, secrets with reveal and carry-forward, live notes, recap.
4. Quests with objectives.
5. Initiative tracker with polling. Dice roller. Random tables.
6. Player campaign view.
7. JSON export. Docker Compose.

### P2 (v0.2 to v0.4)

Reverb real-time for tracker and dice. 5e 2024 SRD compendium, stat blocks, encounter builder. Maps and markers. Calendar and timeline. Entity relationships. Markdown export and Obsidian import. REST API and webhooks. Discord login and webhook. Handouts. Clocks. RSVP, attendance, XP log, availability polling. Body history. Localization. Storage quotas.

### P3

5e character sheets. Player screen. Party inventory. Story arcs and faction reputation. MCP server. Optional AI helpers. Kanka and World Anvil import. Discord bot. Hosted billing. PWA.

## Non-Goals

- Virtual tabletop features: tokens, fog of war, dynamic lighting, video.
- Character sheet builders for every system.
- Audio and music.
- A content marketplace.
- Native mobile apps. Responsive web only.

## Open Questions

- SQLite support for tiny self-host installs, or PostgreSQL only?
- Editor UX: raw Markdown with preview for MVP, or WYSIWYG from the start?
- Can players create and edit entities in the MVP, or only read and edit their own PC?
- Real-time in the MVP, or P2 as recommended?
- Is "demgem" the final name?
- AGPL-3.0 or MIT?

## Next Steps

1. Install Laravel Boost, as the scaffold's CLAUDE.md asks. It replaces AGENTS.md with tailored guidelines.
2. `git init`, first commit of the scaffold plus this document.
3. Add Livewire, Fortify, Pest, Pint, Larastan.
4. Run `/workflows:plan` for the first MVP slice: campaigns, members, and entities with Markdown and wiki links.
