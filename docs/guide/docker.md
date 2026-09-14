# Run it with Docker

The compose stack for a self-hoster: one command, no PHP, no Node, no PostgreSQL on the host.

Requirements: Docker 24 or newer with Compose v2. Nothing else: no PHP, no Node, no PostgreSQL.

```sh
cp .env.docker.example .env.docker
docker compose run --rm --no-deps app php artisan key:generate --show
# Paste the whole base64:... string into APP_KEY in .env.docker, then:
docker compose up -d
```

Open <http://localhost:8000> and register. The first account is an ordinary account: demgem has no instance administrator and does not need one. It is also the only account the register page takes on its own; after it, every account arrives through an invite link, unless `DEMGEM_REGISTRATION=open`.

| Service | What it does |
|---|---|
| `app` | FrankenPHP, serving the app on port 8000. Runs the migrations on boot. |
| `worker` | `queue:work`. It carries the live table's broadcasts and the reminder emails, so the table is only as quick as this container. |
| `scheduler` | `schedule:work`. Every fifteen minutes it queues the reminder emails that are due. |
| `reverb` | The websocket server, on port 8080. Every open browser holds a connection to it. |
| `db` | PostgreSQL 17, in the `pgdata` volume. |
| `redis` | Cache and queue. Sessions stay in PostgreSQL, so a Redis restart keeps everyone signed in. |

- `APP_PORT=8099 docker compose up -d` publishes on another port.
- Change `DB_PASSWORD` in `.env.docker` and `POSTGRES_PASSWORD` in `compose.yaml` together before anyone else can reach the instance.
- `AUTO_MIGRATE=false` stops the migration on boot. Run `docker compose exec app php artisan migrate --force` yourself.
- Uploaded images live in the `storage` volume. Use `MEDIA_DISK=s3` with the `AWS_*` keys for object storage.
- `SERVER_NAME=demgem.example.com` in `.env.docker` gets automatic HTTPS from Caddy. A bare `:8000` serves plain HTTP for a proxy in front.
- The container refuses to start with an empty `APP_KEY`, or with `BROADCAST_CONNECTION=reverb` and no Reverb credentials. It says how to fix either.
