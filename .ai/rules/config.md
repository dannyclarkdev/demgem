---
paths:
  - config/broadcasting.php
---

# Config

## Reverb needs two addresses, and only one of them is REVERB_HOST
`broadcasting.client` is where the **browser** connects. `connections.reverb.options` is where **this application publishes**. On one machine they are the same address, which is why the split is easy to miss.

In Docker they differ: the browser reaches a port published on the host (localhost:8080), and the app and the queue worker reach the container by name on the network's own port (reverb:8080). Point both at REVERB_HOST and the worker calls itself — cURL error 7 — and because every broadcast is ShouldRescue the failure is silent: the live table is simply dead and nothing says why. It cost an image rebuild to find, on 2026-09-03.

So options.host reads REVERB_PUBLISH_HOST first and falls back to REVERB_HOST. .env.docker.example sets the three REVERB_PUBLISH_* keys; a single-machine setup needs none of them. ReverbSettingsTest holds both directions.
