---
paths:
  - 'app/Events/**'
---

# Events

## The live table's latency is the queue connection, not the socket
Broadcasts are queued (ShouldBroadcast), so a change reaches the other screens only as fast as a worker picks the job up. Measured on 2026-09-03 with two browsers on one encounter:

- QUEUE_CONNECTION=database with the default worker: 3.1 seconds.
- The same worker with --sleep=0: 1.1 seconds.
- Redis, which compose uses, blocks on pop and does not pay that wait at all.

So a sluggish table is a queue question first. Do not reach for ShouldBroadcastNow to fix it: that makes a GM's click wait on an HTTP call to Reverb, which is the thing ShouldRescue exists to survive.

## Broadcast the fact, never the data — and always ShouldRescue
Every event in this app carries ids and nothing else: EncounterChanged carries two ULIDs, DiceRolled carries one. Each listener is a Livewire component that re-renders on the server under its own viewer's role, so Entity::visibleTo(), Combatant::visibleToPlayers() and DiceRoll::visibleTo() apply per screen exactly as on a normal request.

That is the whole security design, and it is structural rather than careful: there is no payload to filter and none to leak. A new event that carries data needs a very good reason and a test asserting the serialised payload.

Every event also implements ShouldRescue. A GM clicking "next turn" and a player tapping d20 must never see an error because a websocket server is down; the sixty-second poll on Tracker, Table\Fight, Table\Show and Dice\Log is the backstop. The cost of ShouldRescue is that a broadcast failure is silent — see .ai/rules/config.md for the one that cost a rebuild to find.
