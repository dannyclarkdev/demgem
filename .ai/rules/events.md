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
