# The live table

How the websocket channel works, what it needs from the network, and how to run without it.

Three services make it work: `app` serves the page, `worker` picks the broadcast off the queue, and `reverb` pushes it to every open browser. Stop any of them and the tracker falls back to a sixty-second poll, which is a worse table but never a broken one.

Before the first start, put three strings in `.env.docker`:

```sh
REVERB_APP_ID=$(openssl rand -hex 8)
REVERB_APP_KEY=$(openssl rand -hex 16)
REVERB_APP_SECRET=$(openssl rand -hex 16)
```

Everyone at the table needs to reach the websocket server, so `REVERB_HOST` and `REVERB_PORT` must be the address **their browser** uses, not the container name:

| Where you run it | `REVERB_HOST` | `REVERB_PORT` | `REVERB_SCHEME` |
|---|---|---|---|
| Your own laptop | `localhost` | `8080` | `http` |
| A box on the LAN | its LAN address | `8080` | `http` |
| A server, behind a proxy | your domain | `443` | `https` |

The page reads these at runtime and the bundle never sees them, so one built image serves any host. Publish port 8080 to the network the table is on, or put a proxy in front.

The app and the worker need a *second* address: they publish to the websocket server rather than connecting to it as a browser does, and inside Docker that is `reverb:8080` on the compose network. `.env.docker.example` sets `REVERB_PUBLISH_HOST`, `REVERB_PUBLISH_PORT`, and `REVERB_PUBLISH_SCHEME` for you. Leave them alone unless you move the service; on a single machine they are unnecessary and fall back to `REVERB_HOST`.

**Behind a proxy.** Forward `/app` and `/apps` to the `reverb` container on port 8080, with the websocket upgrade headers, and set `REVERB_HOST` to your domain with `REVERB_SCHEME=https` and `REVERB_PORT=443`. Everything else stays on the `app` container.

**Running without it.** Set `BROADCAST_CONNECTION=null` and stop the `reverb` service. Every screen keeps working on its poll.

**A sluggish table is a queue question, not a socket one.** Broadcasts are queued, so the wait is the worker picking the job up. Redis, which this stack uses, blocks on pop and pays nothing; the database queue driver adds a second or three.
