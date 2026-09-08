---
title: "feat: A key to the campaign, and the channel the party already reads"
type: feat
date: 2026-09-08
brainstorm: docs/brainstorms/2026-09-02-demgem-campaign-manager-brainstorm.md
follows: docs/plans/2026-09-08-feat-calendar-timeline-plan.md
---

# feat: A key to the campaign, and the channel the party already reads

## Overview

Twelve slices built a campaign a GM runs from a browser. Nothing outside the browser can read it, and the one place the party actually talks is a Discord channel the app has never heard of.

| Feature | What it adds |
|---|---|
| Personal API keys | A named key from the profile page, shown once, revoked with a click. Read-only, or read and write. |
| A REST API | `/api/v1`: the campaigns a key holder belongs to, the entities and sessions they may see, search, and the same writes a GM makes on screen. JSON, versioned, gated by the same scopes and policies as every page. |
| A Discord webhook | One URL per campaign. When a recap is published and when a reminder goes out, the channel gets one line and a link. |

When this slice is done a GM mints a key, hands it to a script or an assistant, and that thing reads the lore and writes a note without ever seeing a GM-only page it should not. And when the recap goes up, the party's channel says so before anybody has to.

**On scope.** Phases 0 and 1 are a release: a key and a read API. Phase 2 is a second: writes. Phase 3 is a third: Discord. Generic outgoing webhooks are **not in this slice**; see "Decisions resolved".

## Problem Statement

**The brainstorm wrote a persona the app cannot serve.** "An AI assistant acting through the API" is listed under who this is for, and the P3 rows for an MCP server and optional AI helpers both stand on an API that does not exist. Every fact in the campaign is reachable through one Livewire page or another and through nothing else.

**The party is somewhere else.** Slice 10's plan said it plainly: the reminder that works today is a human typing "tomorrow!" into a Discord thread. The email helps, but the thread is where the table lives, and the recap is published to a page nobody is looking at until they are told.

**And anything that leaves the app carries the risks the last four slices catalogued.** A key is a credential. An API is a second surface for every visibility rule. A webhook URL is a server-side request to an address a user typed. Each of those has a rule already written for its sibling: the calendar token, the export, the archive reader. This slice's job is to apply them rather than invent new ones.

## Proposed Solution

**A key is the user, with the campaign role deciding everything.** A token belongs to a user, not a campaign. What a key holder may read is what that user may read, campaign by campaign, through `Entity::visibleTo()` and `GameSession::visibleTo()` and the policies. There is no second permission model. The one flag a key carries is whether it may write at all, because a GM handing a key to a script that only needs to read should be able to say so.

**The API is the screens, in JSON.** Every list goes through the same scope the page uses. Every resource hides the same fields the page hides. Every write goes through the same action the Livewire component calls, authorised by the same policy. Nothing under `app/Http/Controllers/Api` knows a visibility rule; it asks the models and policies that already do.

**The API creates and changes; it never deletes.** Deleting is a screen with a confirmation, and a key in a script has no confirm button. A campaign, an entity, and a session all stay deletable from the page and nowhere else.

**A Discord post is names and links, never prose.** The rule `.ai/rules/calendar.md` wrote for the feed and the mail holds for the channel: a Discord channel's membership is not the campaign's membership, and a published recap is for the party, not for whoever else is in the server. The post says which session, that the recap is up, and where.

**The server only ever talks to Discord.** The webhook URL is validated against `discord.com/api/webhooks/` and nothing else, in one class the rule and the job both use. That removes the SSRF class the importer refused in slice 8 without a blocklist, because an allow-list of one host is not a check that can be wrong on a network the project cannot see. It is also why generic webhooks are not in this slice.

## Technical Approach

### One new dependency: Laravel Sanctum

The brainstorm names it, `install:api` installs it, and a token in this app is exactly what Sanctum's personal access token is: a hashed secret in a table, named, with abilities, with `last_used_at`. Writing that by hand would be the calendar token with three more columns and a guard, and the guard is the part worth not owning.

Sanctum's SPA cookie mode is not used. `auth:sanctum` accepts a bearer token or the existing session, and the API is the only consumer.

### What slices 1 to 12 give us for free

| Piece | Reuse |
|---|---|
| `Entity::visibleTo()`, `GameSession::visibleTo()`, `EntityRelation::visibleTo()` | Every index, every search, every list of relations. |
| `EntityPolicy`, `GameSessionPolicy`, `CampaignPolicy` | Every write. `roleFor()` already falls back to the database when `CurrentCampaign` is unset. |
| `EnsureCampaignMember` | The same middleware on the API group. It reads `$request->user()`, which the Sanctum guard supplies, and 404s a non-member. |
| `CreateEntity`, `UpdateEntity`, `UpdateSession` | The write endpoints call these and nothing else. |
| `Entity::search()` | The search endpoint is `Livewire\Search` without a view. |
| `SendSessionReminders` | Gains one call after the mail loop. |
| `Sessions\Show::publishRecap()` | Moves into a `PublishRecap` action so the page and the API share the side effect. |
| `Campaigns\Settings`, `Profile\Edit` | One card each. |
| `bootstrap/app.php` | Already renders JSON for `api/*`. |
| The queue | The Discord post is a queued job; a GM's click never waits on Discord. |

### The data

```
personal_access_tokens              Sanctum's own migration, unchanged
  tokenable_type / tokenable_id
  name                              string
  token                             string(64) unique, sha256 of the secret
  abilities                         text, JSON list: ["read"] or ["read", "write"]
  last_used_at, expires_at          nullable
  timestamps

campaigns + discord_webhook_url     text nullable, encrypted cast
```

`discord_webhook_url` is encrypted at rest because it is a credential: anybody holding it can post to the channel. It is never exported, for the same reason `campaign_invites` is on the excluded list. It is a column rather than a table, so `ExportCoverageTest` has nothing to say about it; the campaign section simply does not name it.

No `webhooks` table. One URL per campaign is a scalar, and `.ai/rules/models.md` says a scalar gets a column.

### The key

`Profile\Edit` gains an "API keys" card: a name field, a "Can write" checkbox, and a **Create key** button. On create, the plaintext secret is shown once inside a panel with a copy button and the sentence "Copy it now. It is not shown again." The list below shows each key's name, whether it can write, when it was created, and when it was last used, with a **Revoke** button per row.

Abilities are `read` for every key and `write` for those with the box ticked. Sanctum's `abilities:write` middleware sits on every write route. The names are deliberately those two words: an ability is a fact about the key, not a role.

### The API

`routes/api.php`, prefix `/api/v1`, middleware `auth:sanctum` and `throttle:api`. Campaign routes add `EnsureCampaignMember` and `scopeBindings()` the way `web.php` does. Every response body is an Eloquent API Resource under `App\Http\Resources\Api\V1`.

| Method and path | Who | What |
|---|---|---|
| `GET /me` | any key | The user and their memberships: campaign id, name, role. |
| `GET /campaigns` | any key | The campaigns this user belongs to. |
| `GET /campaigns/{campaign}` | member | The campaign, its calendar's current date, and the viewer's role. |
| `GET /campaigns/{campaign}/entities` | member | `visibleTo()`. Filters: `type`, `tag`, `q` (name contains). Paginated, 50 per page. |
| `POST /campaigns/{campaign}/entities` | DM, write | `CreateEntity`. |
| `GET /campaigns/{campaign}/entities/{entityId}` | member | The entity, tags, custom fields, parent, relations through their scope, image URL. `dm_notes` for DM roles only. |
| `PATCH /campaigns/{campaign}/entities/{entityId}` | policy update, write | `UpdateEntity`. A non-DM key may send only `body` and the character record on its own PC; `updateDmFields` gates the rest. |
| `GET /campaigns/{campaign}/search?q=` | member | Scout, `visibleTo()`, 50 results. |
| `GET /campaigns/{campaign}/sessions` | member | `visibleTo()`, by number. |
| `GET /campaigns/{campaign}/sessions/{number}` | member | Schedule and recap when published. Strong start, scenes, secrets, live notes, GM notes for DM roles only. |
| `PATCH /campaigns/{campaign}/sessions/{number}` | DM, write | `UpdateSession`: title, status, strong start, live notes, recap, GM notes. Never `recap_published_at`. |
| `POST /campaigns/{campaign}/sessions/{number}/publish-recap` | DM, write | `PublishRecap`. The one endpoint with a side effect, so it is a verb. |

Entities are addressed by id, not slug, on the API: a slug changes when a name does, and a script holding one would break on a rename. The response carries both.

The field gates live in the resources, decided from `CurrentCampaign::role()`, which `EnsureCampaignMember` has set. The resource for an entity a DM asked for includes `dm_notes`; the same resource for a player does not have the key at all, rather than carrying it as null, so a diff of the two documents shows the gate.

Validation is inline in the controllers with the same rules `Entities\Form` and `Sessions\Form` use, because the API has four write endpoints and a Form Request per endpoint is a folder for a dozen rules. Errors are Laravel's 422 JSON.

### Discord

`App\Discord\DiscordWebhook` is a value object: it takes a string, accepts only `https://discord.com/api/webhooks/{id}/{token}` (and `discordapp.com`, which Discord still honours), and refuses everything else. `App\Rules\DiscordWebhookUrl` wraps it for the settings form. `App\Jobs\PostToDiscord` takes a campaign and a line of content, resolves the URL, and posts `{"content": "..."}` with the HTTP client, ten-second timeout, three tries with backoff. A failure is logged and the job is done; nothing about a Discord outage reaches a GM's screen.

Two posts:

- **Recap published.** `PublishRecap` writes the stamp through `UpdateSession` and dispatches the job when the campaign has a URL and the recap was not already published. "**The Drowned Duchy** · Session 12: The Salt Cathedral · The recap is up: {url}".
- **Reminder.** `SendSessionReminders` dispatches one post per due session after the mail loop, inside the same `reminder_sent_at` stamp, so the channel and the inbox agree. "**The Drowned Duchy** · Session 12: The Salt Cathedral is tomorrow at 19:00 BST · Say whether you're coming: {url}".

Campaign settings gains a "Discord" card: the URL field with a hint on where Discord shows it, and a **Send a test message** button that posts "demgem is connected to this channel." so a GM knows the URL works before Thursday.

### Screens that change

| Screen | Change |
|---|---|
| Profile | The API keys card. |
| Campaign settings | The Discord card. |
| Session page | None visible. `publishRecap()` calls the action. |

## Decisions resolved

- **Tokens are per user, not per campaign.** A key is the person; the role is the permission. A per-campaign key would need its own role column and a second `roleFor()`, and the leak tests would have to run twice.
- **Two abilities, `read` and `write`.** Not a matrix. A key that can write can write everything its user can; a key that cannot, cannot. Finer grain is a P3 question with no asker.
- **No DELETE endpoints.** Above.
- **No generic webhooks.** A URL the server posts to is SSRF unless the host is fixed. Discord's host is fixed. A generic webhook needs a resolver that refuses private ranges, a DNS rebinding story, and a way to test it in CI, and that is a slice.
- **Discord only, not Slack.** The brainstorm's row is Discord, and the party is on Discord. Slack is the same job with a different payload key and a second host in the allow-list, the day somebody asks.
- **No prose in a Discord post.** The rule from the feed and the mail, for the same reason.
- **`discord_webhook_url` is encrypted and never exported.** It is a credential.
- **Entities by id on the API.** Slugs rename.
- **Sanctum, not a hand-rolled token.** The guard is the part worth not owning.

## Implementation Phases

### Phase 0: The key

- [ ] `install:api`, Sanctum, `HasApiTokens` on `User`, the migration.
- [ ] The API keys card on the profile: create with a name and a write flag, shown once, list, revoke.
- [ ] `GET /api/v1/me`.

Tests: `tests/Feature/Api/TokensTest.php`: a key is created and shown once; a revoked key gets 401; `/me` lists memberships with roles; a key without `write` gets 403 on a write route.

### Phase 1: Reading

- [ ] Campaign, entity, and session resources with the role gates.
- [ ] Campaigns index and show, entities index, show, and search, sessions index and show.
- [ ] `EnsureCampaignMember` and `scopeBindings()` on the group; a non-member's request is a 404.

Tests: `CampaignsApiTest`, `EntitiesApiTest`, `SessionsApiTest`: every leak case the screens are tested for, asserted on the JSON. A player's key never receives `dm_notes`, a GM-only entity, a hidden session, a strong start, a scene, a secret, a live note, or an unpublished recap.

### Phase 2: Writing

- [ ] `POST` and `PATCH` entities, `PATCH` sessions, `POST publish-recap`, all behind `abilities:write`.
- [ ] `PublishRecap` action; `Sessions\Show` calls it.

Tests: a DM key creates an entity and the slug is generated; a player key edits its own PC's body and cannot change its visibility; a player key cannot create; a read key cannot write; the publish endpoint stamps the session.

### Phase 3: Discord

- [ ] `discord_webhook_url`, encrypted, on campaigns; the settings card; the URL rule; the test button.
- [ ] `PostToDiscord` job; `PublishRecap` and `SendSessionReminders` dispatch it.

Tests: `tests/Feature/Discord/DiscordTest.php`: a URL on another host is refused, including `http://169.254.169.254/`; a published recap posts once with the label and the link and none of the session's prose; a re-publish does not post again; a reminder posts to the channel; a failed post is logged and the reminder is still stamped; the test button posts.

### Phase 4: Polish

- [ ] README: an "API" section with a curl example and the endpoint table, a "Discord" section under Reminders, the status paragraph.
- [ ] Browser pass on the profile and the settings page at 1024px and 768px, dark and light.

## Acceptance Criteria

### Functional

- [ ] A user creates a named key, sees the secret once, and revokes it.
- [ ] With the key, a script lists the user's campaigns and reads every entity and session that user can see on screen.
- [ ] With a write key, a GM creates and edits entities and sessions and publishes a recap.
- [ ] A GM pastes a Discord webhook URL, sends a test message, and the channel receives it.
- [ ] Publishing a recap posts one line to the channel. A reminder posts one line to the channel.

### Non-functional

- [ ] **A player's key receives exactly what the player's screen shows: never `dm_notes`, a GM-only entity, a hidden session, a strong start, a scene, a secret, a live note, or an unpublished recap.**
- [ ] **A non-member's key gets a 404 for every campaign route, and a revoked key gets a 401 everywhere.**
- [ ] **A key without `write` gets a 403 from every write route.**
- [ ] **The server posts to `discord.com` and to nothing else. A URL on any other host is refused at validation.**
- [ ] **A Discord post carries no prose from the session.**
- [ ] The API creates and changes and never deletes.
- [ ] A Discord outage never surfaces on a GM's screen.
- [ ] `discord_webhook_url` never appears in an export or an archive.
- [ ] The API is `throttle:api`.

### Quality gates

- [ ] Pest suite green on SQLite locally. PostgreSQL in CI is the pull request's job.
- [ ] Larastan level 6 clean. Pint clean.
- [ ] No new `x-ui.*` component.
- [ ] One new PHP dependency, Sanctum, and no new JavaScript.

## Dependencies & Risks

| Risk | Mitigation |
|---|---|
| The API leaks a field the screen hides | The resources read the role from `CurrentCampaign` and drop the key rather than nulling it. One test per hidden field, on the JSON. |
| A key is leaked | Sanctum stores a hash. Revoke is one click. `last_used_at` on the profile says whether it has been used. |
| The webhook URL is an SSRF vector | `DiscordWebhook` accepts one host and one path prefix. The test tries the metadata address. |
| Discord is down during a reminder | The job retries three times and then logs. The mail already went, and the stamp is written either way. |
| A recap posts twice | `PublishRecap` posts only when `recap_published_at` was null before the write. |

## References

### Internal

- Slice 10 plan: `docs/plans/2026-09-07-feat-scheduling-rsvp-reminders-plan.md`: the reminder pass this slice extends, and the names-and-links rule
- Slice 8 plan: `docs/plans/2026-09-04-feat-json-importer-plan.md`: the SSRF refusal this slice keeps
- Patterns to copy: `app/Actions/Sessions/SendSessionReminders.php`, `app/Calendar/IcsCalendar.php`, `app/Http/Controllers/CalendarFeedController.php`
- Project rules: `.ai/rules/calendar.md`, `.ai/rules/campaigns.md`, `.ai/rules/livewire.md`, `.ai/rules/tests.md`

### External

- Laravel Sanctum, API tokens: https://laravel.com/docs/sanctum#api-token-authentication
- Discord webhooks: https://discord.com/developers/docs/resources/webhook#execute-webhook
