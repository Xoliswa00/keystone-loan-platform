# Keystone → Xquisite monitoring

Keystone is a **reporter** to the central hub — the **Xquisite Creations Suite**
(`https://xquisite.brightfinance-x.co.za`) — so every app's health and errors
are visible in one place. Keystone does not run its own hub; it just points a
few env vars and two scheduled jobs at that one. Ingest contract:
`Xquisite/suite/docs/MONITORING_INGEST.md`.

## What gets sent where

| Signal | From | To (path under `MONITORING_URL`) | Cadence |
|---|---|---|---|
| **Heartbeat** (is this site alive, DB reachable) | `App\Jobs\ReportHealthStatus` | `POST /api/health-report` | every 5 min (scheduler, sync) |
| **Server errors** (`error` and above) | `keystone:report-errors` | `POST /ingest/logs` | every 5 min (scheduler) |
| **Browser JS errors** | `resources/views/partials/monitoring-beacon.blade.php` | `POST /js-error` | on `window.onerror` / unhandled rejection |
| Pull health check (hub polls us) | `routes/web.php` `/api/health` | — | hub-initiated, every 5 min |

All four are gated by `MONITORING_ENABLED`. Nothing depends on a queue worker —
the scheduler runs the heartbeat and the forwarder synchronously.

## Configuration

```dotenv
MONITORING_ENABLED=true
MONITORING_URL=https://xquisite.brightfinance-x.co.za   # BASE url, no path
MONITORING_TOKEN=<the api_token from the instance's row on the hub>
MONITORING_SLUG=keystone                                # this instance's slug on the hub
```

`MONITORING_URL` is the **base** URL. Each sender appends its own path
(`/api/health-report`, `/ingest/logs`, `/js-error`) — do not put a path in the
env var. `MONITORING_SLUG` is used to build the per-row dedup fingerprint
(`<slug>-<id>`); the hub still derives `system_logs.source` from the token, not
from this value.

### Getting a token

On the hub: **`/admin/monitoring` → Add Instance**. Set `name` (`Keystone`),
`slug` (`keystone` — this becomes `source` on every forwarded log row, and goes
in `MONITORING_SLUG` here), `url` (`https://<this site>/api/health`), and
generate `api_token` (`Str::random(48)`) for `MONITORING_TOKEN`.

## How the error forwarder works

`keystone:report-errors` (see `app/Console/Commands/ReportErrorsToXquisite.php`):

1. Reads `system_logs` rows where `level` ∈ {error, critical, alert, emergency}
   and `forwarded_at IS NULL`, oldest first, up to `--limit` (default 100).
2. Scrubs SA ID numbers, emails, `key=value` secrets and bearer tokens from the
   message.
3. `POST`s them as one batch to `{MONITORING_URL}/ingest/logs` with the bearer
   token. Each event carries `fingerprint = "keystone-<row id>"` so the hub can
   de-duplicate — a re-sent batch after a failed run never doubles up.
4. On a 2xx it stamps `forwarded_at` on the batch. On any failure it logs a
   **warning** (which is below the forward threshold, so it can't feed back into
   itself) and retries on the next run.

Only `error`+ is forwarded, on purpose: the hub is for triage, not a copy of
every log line. The full trace stays in `storage/logs/laravel.log` on this box.

If the hub is unreachable, forward failures are only visible locally
(`storage/logs/xquisite-forward.log` + the local `system_logs`) — by design, you
can't tell the hub that the hub is down. The heartbeat is what tells the hub
this instance went quiet.

## First deploy

After `git pull` + `composer install --no-dev -o` + `php artisan migrate --force`:

```bash
# .env — set ALL FOUR. MONITORING_URL is the hub base ONLY (scheme + host),
# no /api/... and no trailing path. If this box previously reported health via
# a full URL, strip the path off the value.
#   MONITORING_ENABLED=true
#   MONITORING_URL=https://xquisite.brightfinance-x.co.za
#   MONITORING_TOKEN=<exact api_token from the hub's instance row>
#   MONITORING_SLUG=keystone
php artisan config:clear && php artisan config:cache

php artisan monitoring:ping                      # <-- MUST print "OK" before trusting the scheduler
php artisan keystone:report-errors --backfill    # mark existing error history as sent — do NOT skip
php artisan schedule:list                        # confirm keystone:report-errors + the heartbeat
```

`monitoring:ping` actively authenticates against the hub and tells you exactly
what's wrong — `401` = wrong/inactive token, `404` = `MONITORING_URL` has a path
or the hub route isn't live, connection error = DNS/TLS. Don't move on until it
says `OK`.

Skipping `--backfill` floods the hub (and its critical-error email alert) with
months of old errors on the first run.

The minute cron (`* * * * * php artisan schedule:run`) already exists for the
other `keystone:*` jobs — nothing new to add. **Do not** run `php artisan
route:cache` or `optimize` on this box — `routes/web.php` has closure routes and
it 500s the whole site (see DEPLOYMENT-CPANEL.md). `config:cache` / `view:cache`
are fine.

The `MonitoredInstance` / `/admin/monitoring` checks run on the **hub** server,
not here — this box only has the reporter half.

## Enforcement

`php artisan monitoring:verify` checks the heartbeat job, the `/api/health`
route, the JS beacon (and that it's `@include`d somewhere), the
`config/services.php` `monitoring` block, all four `MONITORING_*` vars in
`services.php` + `.env.example`, that `MONITORING_URL` (if set) has no path, the
forwarder command, and both schedule entries. It runs from `.githooks/pre-commit`
and `.githooks/pre-push` (wired up by `composer install` via `git config
core.hooksPath .githooks`), so the integration can't be silently removed.

`monitoring:verify` is a static/code check. `monitoring:ping` is the runtime
one — it proves the deployed `MONITORING_URL` + `MONITORING_TOKEN` actually
authenticate against the hub. Run `ping` after every deploy that touches the
`MONITORING_*` env.

If you deliberately change any of this, update
`app/Console/Commands/VerifyMonitoringSetup.php` in the same commit and
re-register / re-token the instance on the hub if needed.

## Note on the CSRF-exempt hub endpoints

`/ingest/logs`, `/api/health-report` and `/api/health-status` on the hub are
CSRF-exempt and carry no session — they are authenticated by the **bearer
token**, not a login. That is intentional; don't "fix" it by adding `auth`
middleware.
