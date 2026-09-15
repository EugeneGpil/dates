# Running Dates locally

Everything runs in Docker. Nothing — PHP, Node, Postgres — needs to be installed on the host.

## First run

```sh
make setup
```

It copies `.env.example` to `.env`, builds the images, waits for Postgres, writes `back/.env`
and `front/.env.local`, installs both dependency trees, builds the PWA and runs the migrations.

Then:

| what | where |
|---|---|
| API | http://localhost:8001 — `GET /api/health` is the probe |
| PWA (built, served by nginx) | http://localhost:8084 |
| PWA (dev server, hot reload) | http://localhost:9201 — `make node`, then `npm run dev` |
| Postgres | `localhost:5433`, database `dates`, user `dates` |

The ports are offset from `shopping_list`'s (8000/8080/5432/9200) so both projects can be up at
once. They live in `.env`; change them there, not in `docker-compose.yml`.

## Day to day

```sh
make up                      # start everything
make down                    # stop everything
make logs                    # tail all logs
make php                     # shell into the PHP container
make node                    # shell into the Node container
make migrate                 # run migrations
make test                    # the backend suite
make artisan CMD='route:list'
make composer CMD='require vendor/package'
```

The frontend's own commands run inside the Node container:

```sh
docker compose exec node npm run dev     # dev server on 9201
docker compose exec node npm run build   # rebuild what nginx serves on 8084
docker compose exec node npm run lint
docker compose exec node npm run test
```

**A front change is only visible on 8084 after a rebuild.** The dev server on 9201 is the one
that reloads by itself.

## Services

`nginx`, `php`, `postgres` and `node` are the app. Two more are running for the notifications:

- `scheduler` — `php artisan schedule:work`, which is what will dispatch `dates:notify`. No host
  crontab, and the same environment anywhere `docker compose up` runs.
- `queue` — `php artisan queue:work`, on the database connection. The sends are queued per user
  so that one unreachable FCM call cannot stall everyone behind it.

`capacitor` is behind a compose profile and is a one-shot builder, not a service — see the
`android-*` targets in the `Makefile`.
