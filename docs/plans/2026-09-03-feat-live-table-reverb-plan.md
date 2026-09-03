---
title: "feat: The live table, with Reverb"
type: feat
date: 2026-09-03
brainstorm: docs/brainstorms/2026-09-02-demgem-campaign-manager-brainstorm.md
follows: docs/plans/2026-09-03-feat-player-view-export-docker-plan.md
---

# feat: The live table, with Reverb

## Overview

The first slice after the MVP, and the one the MVP kept deferring. Three brainstorm rows say "P2, needs Reverb" — the shared dice log, the player view of the tracker, and a tracker that pushes instead of polling — and slice 3 shaped its columns for all three without building any of them.

| Feature | What it adds |
|---|---|
| Broadcasting | Reverb, Echo, and one authorised channel per campaign. Everything else in the slice hangs off it. |
| The tracker pushes | A GM advances the turn and every open screen at the table changes at once. The fifteen-second poll becomes a sixty-second backstop. |
| The player table view | `/table`: the turn order, whose turn it is, the round, and the state of every combatant the GM chose to show. |
| The shared dice log | Players roll and everyone sees it. A GM rolls behind the screen when they want to. |
| Who is at the table | The GM sees which players have the campaign open. |

When this slice is done, the party stops asking "whose turn is it?" and stops reading dice results out loud. demgem is on the table, not just behind the screen.

**On scope.** This is wide, like slice 3. Phases 0 to 2 are a release on their own: the party can watch the fight. Phases 3 and 4 are a second: they can roll in it. Cut at the end of Phase 2 if the slice runs long, and do not cut a phase in half.

## Problem Statement

demgem is a GM's application with a reading room attached. The MVP gave players the lore, the quest log, the schedule, and the story, and every one of those is something they read between games. At the table, where the app is supposed to earn its place, a player has nothing. They ask whose turn it is. They read their own d20 out loud and hope somebody writes it down. They watch the GM look at a screen that is turned away from them.

The GM's side has the same gap in a smaller form. The tracker polls every fifteen seconds, which slice 3 shipped knowing it was the wrong answer: a poll is a compromise between staleness and load, and it loses both ways once a second device is at the table. The plan said so at the time, in the risk table, and deferred the fix to this slice.

The columns for all of it are already in place. `dice_rolls.user_id` has been recording who rolled since slice 3, and nothing has ever read it, because a log only one person can see does not need to know. That column has been waiting for this slice.

## Proposed Solution

**Broadcast the fact, never the data.** Every event in this slice carries ids and nothing else: which campaign, which encounter. A listening Livewire component re-renders on the server under its own viewer's role, so `Entity::visibleTo()`, `GameSession::visibleTo()`, and the new combatant rule all apply per viewer, exactly as they do on a normal request. No payload means no payload to leak. This is the whole security design, and it is structural rather than careful.

**One presence channel per campaign.** Private channels would work, and a presence channel is a private channel that also answers "who is here", which is a question a table asks out loud every week. The members page already shows every member and their role to everyone, so the roster a presence channel exposes is not new information. One channel, one authorisation callback, membership answered once.

**The GM chooses what the party can see.** `combatants.player_visible` is the switch slice 3 named and did not build. The party's own characters default to visible; anything the GM adds is hidden until they say otherwise, because the surprise round is a real thing.

**Health is a word for players, not a number.** A player sees Unhurt, Hurt, Badly hurt, or Down, derived from the fraction the GM is tracking. Exact hit points are a GM's working number, and "the ogre has 43 left" changes how a table plays.

## Technical Approach

### The dependencies this slice needs

CLAUDE.md asks for approval before changing dependencies. This plan is the request. Four packages, all first-party:

| Package | Why |
|---|---|
| `laravel/reverb` | The websocket server. Laravel's own, self-hostable, no third-party account, which matters for a self-hosted app. |
| `laravel-echo` (npm) | The client. Reverb speaks the Pusher protocol and Echo is how Laravel apps consume it. |
| `pusher-js` (npm) | Echo's transport for the Pusher protocol. |

`php artisan install:broadcasting --reverb` installs and wires all three. Pusher Channels and Ably would need an account and would send a self-hoster's table traffic through somebody else's service, which is the opposite of what this project is for.

### What slices 1 to 4 give us for free

| Piece | Reuse |
|---|---|
| `Campaign::roleFor()` | The channel authorisation callback is three lines: resolve the campaign, read the role, return the roster entry or false. |
| `InteractsWithCampaign` | Every listening component already re-checks membership on each round trip. A broadcast that triggers a re-render goes through the same hook. |
| The nested-component rule | `Tracker`, `Dice\Tray`, and the new player components each listen for themselves. A page-level listener would re-render the wrong thing. |
| `EncounterPolicy` / `CampaignPolicy` | The new abilities join them: `viewTable`, `rollDice`. Both copy `roleFor()` in full, as slice 3's rule requires. |
| The slice 4 queue worker | It has been idle since the day it shipped. Queued broadcasts are the work it was put there for. |
| `Combatant` and `DiceRoll` | Two nullable columns join tables that already exist. No new table in this slice either. |
| `x-ui.*` kit | **Budget: one new component**, `x-ui.presence-dot`, and only if Phase 4 survives. Everything else reuses the kit. |

### The channel, and the one authorisation callback

`routes/channels.php` does not exist yet, and `bootstrap/app.php` does not register it. Both are Phase 0.

```php
// routes/channels.php
Broadcast::channel('campaign.{campaignId}', function (User $user, string $campaignId): array|false {
    $campaign = Campaign::query()->find($campaignId);
    $role = $campaign?->roleFor($user);

    if ($role === null) {
        return false;
    }

    // The roster a presence channel shares. The members page already shows every
    // member and role to everyone in the campaign, so this adds nothing new.
    return ['id' => $user->id, 'name' => $user->name, 'role' => $role->value];
});
```

A guest never reaches the callback: Laravel denies an unauthenticated subscription before it runs. A member of another campaign gets `false`. Both get a test.

### Broadcast the fact, never the data

Two events, both carrying ids only:

```php
// app/Events/EncounterChanged.php
class EncounterChanged implements ShouldBroadcast, ShouldRescue
{
    public function __construct(public readonly string $campaignId, public readonly string $encounterId) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel('campaign.'.$this->campaignId)];
    }

    public function broadcastAs(): string
    {
        return 'encounter.changed';
    }
}
```

`app/Events/DiceRolled.php` is the same shape with `campaignId` alone.

- **`ShouldRescue` is not optional.** A GM clicking "next turn" must not see an error because a websocket server is down. The rescue helper reports the failure and lets the request finish; the fifteen-second-turned-sixty-second poll picks the change up anyway.
- **`ShouldBroadcast`, not `ShouldBroadcastNow`.** Queued, so the request does not wait on an HTTP call to Reverb. This makes a running queue worker a requirement for live updates: `php artisan dev` runs one locally, the slice 4 compose file already runs one, and the README says so.

  **Measured, two browsers on one encounter, 2026-09-03:** 3.1 seconds with the database queue and a default worker, 1.1 seconds with the same worker at `--sleep=0`. The wait is the worker's poll, not the socket, and Redis, which compose already uses, does not pay it. A sluggish table is a queue question first.
- **The listener re-renders; it does not read the payload.** In Livewire:

```php
#[On('echo-presence:campaign.{campaign.id},.encounter.changed')]
public function refresh(): void
{
    // Deliberately empty. The re-render is the point, and it runs under this
    // viewer's own role, so every visibility rule applies exactly as it always does.
}
```

**Where the events are dispatched.** In the actions, not the components, because the Run screen, the encounter page, and any future API all go through the same actions: `NextTurn`, `ApplyDamage`, `SetConditions`, `AddCombatants`, `RemoveCombatant`, `SortByInitiative`, `RollInitiative`, and the reorder path dispatch `EncounterChanged`. `RollDice` dispatches `DiceRolled`.

**`->toOthers()` is not used.** The person who clicked already has the fresh render from their own round trip, and Livewire ignores a re-render it did not ask for; excluding them would only make the code lie about who receives it. Keep the broadcast simple and let each client's own request win the race.

### What a player sees of a fight

One new column: `combatants.player_visible`, boolean, default false.

- `AddCombatants` sets it to **true** for a combatant added from an entity with `is_pc`, and false for everything else. The party is on the screen; the thing in the dark is not until the GM says so.
- The tracker gains an eye toggle per row, GM only.
- The player table view lists visible combatants in turn order with: name, conditions, whether it is their turn, and health as a word.

```php
// app/Support/HealthWord.php  (or a method on Combatant)
public function healthWord(): ?string   // null when the GM tracks no hit points
{
    // Unhurt, Hurt, Badly hurt, Down, from hp/max_hp. Any system with hit points
    // reads the same way, which is what "system-light" has meant since slice 3.
}
```

Exact hit points, armour class, initiative bonuses, and every combatant with `player_visible = false` are absent from a player's HTML and Livewire snapshot. That is the leak test of this slice, and it gets its own file.

### The player's screen

**`/table`, named `table`.** A player's live screen: the fight when there is one, the shared dice log, and who else is here. It is the one page a player keeps open during a game, and it is the only place in the app that a player would keep open at all.

- With no active encounter it shows the party, the session's published recap link, and the dice log, so the page is not empty between fights.
- The GM has no need for it — the Run screen is their version — but the route is open to every member so a co-GM on a second device can watch the same thing.

`App\Livewire\Table\Show`, with two nested components: `Table\Fight` and `Dice\Log`. Both use `InteractsWithCampaign` and both listen for themselves.

### The shared dice log

One new column: `dice_rolls.private`, boolean, default false.

- **Players may roll.** `CampaignPolicy::rollDice()` returns true for owner, co-GM, and player, and false for a spectator, who is read-only by definition.
- **A GM may roll behind the screen.** A "behind the screen" toggle on the tray sets `private = true`. A private roll appears only to the user who rolled it, and only GM roles can set it. A player's roll is never private: a private player roll is just a roll they did not make.
- **The log is one component, two audiences.** `Dice\Log` shows the campaign's rolls, filtered: `where('private', false)->orWhere('user_id', $viewer->id)`. It renders on the Run screen drawer and on `/table`.
- **Throttled.** `RollDice` refuses more than 30 rolls a minute per user, through the cache. A player with a stuck key must not fill the table's log.

### The tracker stops polling

`wire:poll.visible.15s` becomes `wire:poll.visible.60s` plus the broadcast listener.

The poll stays because a websocket connection drops, a laptop sleeps, and a GM who missed a round change would rather wait sixty seconds than refresh. The query-count test from slice 3 still applies and its number does not change.

### Who is at the table (Phase 4, cuttable)

The presence channel already carries the roster. `Table\Presence` and a small strip on the Run screen show the names of connected members, so a GM knows whether everyone is looking at the same thing. `here`, `joining`, and `leaving` are three more Livewire listeners on the same channel.

Cut this phase and nothing else in the slice changes: the channel is a presence channel either way, because that decision is about authorisation, not about the strip.

### The one Docker wrinkle worth planning

Vite bakes `VITE_*` values into the bundle at build time. A self-hoster's Reverb host is not known at build time, so an image built with `VITE_REVERB_HOST=localhost` is wrong on every machine but the one that built it.

**The layout renders the settings; the bundle reads them.**

```blade
{{-- layouts/app.blade.php --}}
<script>window.demgem = @json(['reverb' => config('broadcasting.client')]);</script>
```

```js
// resources/js/app.js
window.Echo = new Echo({ broadcaster: 'reverb', ...window.demgem.reverb });
```

One image then serves any host, which is what a self-hosted product needs. Record it as a rule: **never bake a deploy-specific value into the Vite bundle.**

Compose gains a `reverb` service running `php artisan reverb:start --host=0.0.0.0 --port=8080`, published on `${REVERB_PORT:-8080}`, with the app's `REVERB_*` variables pointing at it. The CI docker job waits for it to answer as well.

## Decisions resolved

| Question | Decision |
|---|---|
| Reverb, Pusher, or Ably | Reverb. Self-hosted, first-party, no account, no table traffic through a third party. |
| What a broadcast carries | Ids only. The listener re-renders under its own viewer's role, so there is no payload to leak. |
| Private channel or presence channel | Presence, one per campaign. It is a private channel that also answers "who is here", and the roster it shares is already on the members page. |
| A separate GM channel | Not needed. With no payload, a player learning that "something changed" learns nothing they could not already see. |
| Queued or sync broadcasts | Queued, with `ShouldRescue`. A GM's click must never fail because Reverb is down, and it must never wait on it either. |
| Polling after this slice | Kept at sixty seconds as a backstop. Sockets drop and laptops sleep. |
| Where events are dispatched | In the actions, so every surface and any future API broadcasts the same way. |
| `->toOthers()` | Not used. The clicker already has a fresh render, and excluding them would make the code lie about who receives it. |
| What a player sees of a combatant | Name, turn order, conditions, and a health word. Never hit points, armour class, or initiative bonus. |
| Which combatants a player sees | Those with `player_visible`. PCs default to true when added from the party; everything else defaults to false, because the surprise round is real. |
| Health as words | Unhurt, Hurt, Badly hurt, Down, from the fraction. Any system with hit points reads the same, and an exact number changes how a table plays. |
| Who may roll dice | Owner, co-GM, and player. Not a spectator, who is read-only. |
| Private rolls | GM roles only, through a "behind the screen" toggle. A private player roll is a roll they did not make. |
| Roll throttling | 30 a minute per user, in `RollDice` through the cache. |
| The player's screen | One new route, `/table`. It is the only page a player keeps open during a game, and the Run screen is the GM's version of it. |
| Presence strip | Phase 4, cuttable. The channel is a presence channel regardless. |
| Reverb settings in the bundle | Never. The layout renders them and the bundle reads them, so one image serves any host. |
| New tables | None. Two boolean columns on two existing tables. |
| New kit components | One, `x-ui.presence-dot`, and only if Phase 4 survives. |

## Implementation Phases

Each phase ends with a green suite. Phases 0 to 2 are a release; Phases 3 and 4 are a second.

### Phase 0: Broadcasting, and the channel

Deliverables:
- `composer require laravel/reverb`, `php artisan install:broadcasting --reverb`, and the npm packages. **Ask before running it**: this plan requests the dependency change, and the answer belongs in the commit message.
- `routes/channels.php` with the one callback, registered in `bootstrap/app.php` through `withRouting(channels: ...)`.
- The runtime Echo configuration: `config/broadcasting.php` gains a `client` block, the layout renders it, `resources/js/app.js` builds `window.Echo` from it.
- `.env.example` and `.env.docker.example` gain the `REVERB_*` keys.

Tests: `tests/Feature/Broadcasting/ChannelAuthTest.php` — a member is authorised and gets their name and role back; a member of another campaign is refused; a guest is refused; a campaign that does not exist is refused.

Success: `php artisan reverb:start` runs, a browser connects, and `/broadcasting/auth` answers correctly for all four cases.

### Phase 1: The tracker pushes

Deliverables:
- `app/Events/EncounterChanged.php`.
- Dispatches in the eight encounter actions.
- `Tracker` listens and re-renders; the poll drops to sixty seconds.
- `Encounters\Show` and the Run screen need no change: the nested tracker owns its own listener.

Tests: `tests/Feature/Broadcasting/EncounterBroadcastTest.php` (each action dispatches once, with `Event::fake()`; the payload is ids only; **the serialised event contains no combatant name, hit points, or condition**), plus the slice 3 tracker tests unchanged, including the query count.

Success: two GM devices, one clicks "next turn", the other changes without waiting.

### Phase 2: The player table view

Deliverables:
- Migration `add_player_visible_to_combatants_table`.
- `AddCombatants` defaults it true for PC entities.
- `Combatant::healthWord()`, `Combatant::isVisibleToPlayers()`.
- `EncounterPolicy::viewTable()` for members, with `roleFor()` copied in full.
- The eye toggle in the tracker, GM only.
- `App\Livewire\Table\Show` and `Table\Fight`, the `/table` route, and the sidebar entry for every role.

Tests: `tests/Feature/Table/PlayerTableTest.php` (turn order, the current turn, the round; a player's own PC is marked), `tests/Feature/Table/CombatantVisibilityTest.php` (**a hidden combatant's name is absent from a player's HTML and snapshot; hit points, armour class, and initiative bonus never appear for a player; the GM sees all of it**), and the player surface audit from slice 4 gains `/table`.

Success: the GM reveals four goblins mid-fight and the party's screens fill in.

### Phase 3: The shared dice log

Deliverables:
- Migration `add_private_to_dice_rolls_table`.
- `CampaignPolicy::rollDice()`, and `Dice\Tray` no longer gated by `useGmTools`.
- The throttle in `RollDice`, and `app/Events/DiceRolled.php`.
- `App\Livewire\Dice\Log`, nested, on `/table` and in the Run screen drawer.
- The "behind the screen" toggle, GM only.

Tests: `tests/Feature/Dice/SharedLogTest.php` (a player's roll reaches the GM's log; **a private roll is absent from every other viewer's HTML and snapshot**; a spectator cannot roll and gets no tray; the throttle refuses the thirty-first roll in a minute and writes nothing).

Success: a player rolls a d20 at their end of the table and the GM sees it without being told.

### Phase 4: Who is at the table (cuttable)

Deliverables: `Table\Presence`, the strip on both screens, `x-ui.presence-dot`.

Tests: presence membership comes from the channel callback, which Phase 0 already tests. This phase is UI.

Success: the GM can see that three of four players have the table open.

### Phase 5: Docker, CI, and the runtime configuration

Deliverables:
- A `reverb` service in `compose.yaml`, its port, its healthcheck, and the app's `REVERB_*` variables.
- The CI docker job waits for Reverb as well as the app.
- README: a section on the live table, what it needs, and how to run it behind a proxy.

Tests: CI is the test, as in slice 4.

Success: `docker compose up -d` gives a stack where two browsers see the same fight.

### Phase 6: Polish

- The seeder gains a fight in progress and a handful of rolls, so `/table` shows something on the first run.
- Empty states: no fight, no rolls, nobody else here.
- The tablet pass, at 1024px and 768px, on `/table` and on the Run screen with the log open. Both viewports, both themes, the same five rules as slice 4's Phase 0.
- Record the rules: broadcast the fact never the data; never bake a deploy value into the Vite bundle; `ShouldRescue` on every broadcast a user's click depends on.
- Pint, Larastan, the full suite, and `npm run build`.

## Alternative Approaches Considered

- **Broadcasting the data and filtering per channel.** The obvious shape, and it is how most tutorials do it. Rejected: it needs a channel per role, a payload per audience, and a decision on every field. The signal-and-re-render pattern makes the leak structurally impossible instead, at the cost of one extra round trip per client.
- **Keeping the poll and shortening it to three seconds.** No new dependency, no websocket server. Rejected: it is a worse tracker and a worse dice log, and it multiplies the query count by the number of people at the table, which is the one thing slice 3 measured and held.
- **Pusher Channels or Ably.** No server to run. Rejected: an account, a bill, and a self-hoster's table traffic through a third party.
- **A private channel plus a separate presence channel.** Tidier separation. Rejected: two channels, two callbacks, two subscriptions, for a roster the members page already shows.
- **Exact hit points for players.** Simpler code, no health words. Rejected: it is the GM's working number, and a party that can read it plays differently. The GM can still say the number out loud, which is the point.
- **`player_visible` defaulting to true.** Fewer clicks in the common case. Rejected: the first fight where a hidden ambusher appears on the party's screens before it appears in the fiction is the last time the GM trusts the feature.
- **A player dice tray on the campaign dashboard instead of `/table`.** One less route. Rejected: rolling and watching the fight are the same moment, and the dashboard is a between-games page.

## Acceptance Criteria

### Functional

- [x] A member subscribes to their campaign's channel; a non-member and a guest are refused.
- [x] A GM advances the turn and every other open tracker changes without a refresh and without a poll.
- [x] The tracker still recovers within sixty seconds when the socket is down.
- [x] A GM toggles a combatant between hidden and shown, and the party's screens follow.
- [x] `/table` shows the turn order, the round, whose turn it is, and each shown combatant's health as a word.
- [x] A player sees their own character marked on `/table`.
- [x] `/table` is useful with no fight running: the party and the latest recap. *(The dice log joins it in P3.)*
- [ ] A player rolls dice and the roll appears in the GM's log and every other player's log.
- [ ] A GM rolls behind the screen and nobody else sees it.
- [ ] A spectator can open `/table` and cannot roll.
- [ ] The thirty-first roll in a minute is refused and writes nothing.
- [ ] The GM can see which members have the campaign open. *(Phase 4; drop this line if the phase is cut.)*
- [ ] `docker compose up -d` gives a stack where two browsers see the same fight.

### Non-functional

- [x] **No broadcast payload carries a combatant's name, hit points, conditions, or a dice result.** Asserted on the serialised event.
- [x] **A hidden combatant is absent from a player's HTML and Livewire snapshot**, and so are hit points, armour class, and initiative bonuses for every combatant.
- [ ] **A private roll is absent from every other viewer's HTML and snapshot.**
- [x] A member removed or demoted mid-fight stops receiving updates on their next round trip, and cannot re-subscribe.
- [x] Every broadcast implements `ShouldRescue`, so a GM's click never fails because Reverb is down.
- [x] The tracker's query count per render does not change from slice 3.
- [x] `/table` costs a constant number of queries, whatever the fight holds.
- [x] `Model::shouldBeStrict()` is on, so every new screen eager-loads.
- [x] No `VITE_` value decides where the browser connects: the layout renders the settings at runtime.
- [ ] `/table` and the Run screen with the log open work at 1024px and 768px, in dark and light, with 16px body text and 44px tap targets on the controls a GM uses mid-game.

### Quality gates

- [ ] Pest suite green on SQLite locally and on PostgreSQL in CI.
- [ ] Larastan level 6 clean. Pint clean.
- [ ] The Docker job builds, boots, and reaches Reverb.
- [ ] At most one new `x-ui.*` component, with the reason written down.
- [ ] Every new query on `combatants` and `dice_rolls` goes through a policy, and each new ability names its surfaces in a docblock.
- [ ] `npm run build` clean.

## Dependencies & Risks

| Risk | Mitigation |
|---|---|
| A dependency change without approval | This plan is the request. Four first-party packages, named above, and nothing runs until the answer is yes. |
| A broadcast leaks GM-only data | Ids only, and the re-render runs under each viewer's role. A test asserts the serialised payload holds no name, no hit points, no result. |
| A hidden combatant appears on a player's screen | `player_visible` defaults to false for everything the GM adds, and the visibility test is its own file. |
| Reverb is down and the app breaks | `ShouldRescue` on every event, and the sixty-second poll stays as the backstop. |
| No queue worker, so nothing broadcasts | Queued broadcasts need a worker. `php artisan dev` runs one locally, compose runs one already, and the README says so plainly. |
| The image bakes one host into the bundle | The layout renders the settings and the bundle reads them. Recorded as a rule. |
| A player floods the shared log | Thirty rolls a minute per user, refused in the action so every surface shares the answer, exactly as the dice limits do. |
| The slice runs long | Two release boundaries: after Phase 2 and after Phase 4. Phase 4 is cuttable. |
| Websockets make tests flaky | Nothing in the suite opens a socket. `Event::fake()` proves dispatch; the channel callback is tested through `/broadcasting/auth`. |
| Presence exposes who is online to a player | The members page already lists every member and role. Being online is the only new fact, and it is one the table can see anyway. |

## Future Considerations

- **The player's own hit points.** A player knowing their character's exact hit points is reasonable and needs the character sheet conversation, not this slice.
- **Handouts pushed to the table.** The same channel, a new event, and the image reveal the brainstorm puts in P2.
- **The player screen on a second display.** P3 in the brainstorm, and it becomes easy once `/table` exists.
- **Reverb behind a proxy, with TLS.** The README will cover the simple case. A guide for nginx or Traefik in front is a docs task.
- **Scaling Reverb.** One process holds every connection. A campaign is a handful of people, and a self-hoster will not notice for years; the Redis scaling option is documented if they do.
- **Rolls against a target, and a roll a GM requests.** "Everyone roll perception" is the natural next feature once rolls are shared.

## References

### Internal

- Brainstorm: `docs/brainstorms/2026-09-02-demgem-campaign-manager-brainstorm.md`, the "Live table tools" and "Players" tables
- Slice 3 plan: `docs/plans/2026-09-03-feat-quests-tracker-dice-tables-plan.md` — the polling decision, `combatants.player_visible`, and `dice_rolls.user_id`, all shaped for this slice
- Slice 4 plan: `docs/plans/2026-09-03-feat-player-view-export-docker-plan.md` — the Docker stack this one extends, and the tablet rules
- Project rules: `.ai/rules/livewire.md` (nested components re-check membership), `.ai/rules/models.md`, `.ai/rules/migrations.md`, `.ai/rules/css.md`
- Patterns to copy: `app/Livewire/Encounters/Tracker.php` (the nested component that will listen), `app/Actions/Encounters/NextTurn.php` (where the first dispatch goes), `app/Policies/GameSessionPolicy.php` (`roleFor()` and the surface docblock)

### External

- Laravel 13 broadcasting: https://laravel.com/docs/13.x/broadcasting
- Laravel Reverb: https://laravel.com/docs/13.x/reverb
- Livewire 4 with Echo, including the dynamic channel syntax: https://livewire.laravel.com/docs/4.x/events#real-time-events-using-laravel-echo
- `ShouldRescue`: https://laravel.com/docs/13.x/broadcasting#rescuing-broadcasts
- Presence channels: https://laravel.com/docs/13.x/broadcasting#presence-channels
