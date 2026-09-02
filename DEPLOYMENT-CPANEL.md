# Deploying Keystone to cPanel shared hosting

Laravel **10** · runs on PHP **8.2 or 8.3** · MySQL **8.0** · queue `sync` · assets and `vendor/` are **committed**, so no Composer or npm run on the server.

**Host: Afrihost**, cPanel on a **LiteSpeed** server. From the account's current `public_html/.htaccess`:

| Fact | Value |
|---|---|
| cPanel user | `keystof9z0m9` |
| Home directory | `/home/keystof9z0m9` |
| Default PHP | `ea-php83` (**8.3**) — fine for Laravel 10 |
| Web server | LiteSpeed (`lsapi_module`) — reads `.htaccess`, but see §9 |
| PHP error log | `/home/keystof9z0m9/logs/php.error.log` |

The account is fresh: `public_html` holds only the stock cPanel `index` + `.htaccess`. Manage domain/DNS in **Afrihost ClientZone**; NS are `ns1.afrihost.co.za` / `ns2.afrihost.co.za`.

> **Confirm the plan has SSH before starting.** Afrihost "Shared Hosting – Home" has **no SSH and no cPanel Terminal**, which blocks every `artisan` step (migrate, seed, `storage:link`, `config:cache`). You need **Shared – Pro/Advanced** (SSH on request) or Cloud/VPS. No-SSH fallbacks are in §8.

---

## 0. Hosting account must provide

| Requirement | Notes |
|---|---|
| PHP **8.2 or 8.3** — set per-domain in **cPanel → MultiPHP Manager** (vhost-level, survives the docroot change; don't rely on the `.htaccess` `AddHandler`). Server default is already `ea-php83`. **Not 8.4** — it changes `str_getcsv()` escaping, which the bank-statement/NuPay CSV importers use. `composer.lock`'s platform pin is 8.1 but that's build-time only; `vendor/` is committed so runtime PHP just needs ≥8.1. |
| PHP extensions | `bcmath, ctype, curl, dom, fileinfo, gd, intl, mbstring, mysqlnd, openssl, pdo_mysql, tokenizer, xml, zip` (CI baseline is `mbstring, dom, fileinfo, mysql, gd, zip` — enable the rest in *Select PHP Version → Extensions* for `ea-php83`). |
| **SSH access** | Needed for every `artisan` command. Confirm the plan tier includes it (see banner above). |
| MySQL database + user | `utf8mb4`, user granted **ALL PRIVILEGES** on the DB. cPanel → *MySQL Databases*. |
| Cron jobs | One entry, see §5. Without it, arrears escalation / payment reminders / IFRS 9 provisioning / GL reconciliation never run. |
| Ability to set the domain document root to a subfolder | Point the domain at `.../public`. See §2 step C. |
| Outbound SMTP | An `@keystonecapitalpartners.co.za` mailbox (cPanel → *Email Accounts*) — signed agreements and investor OTP codes are emailed. |

---

## 1. Before you touch the server

- [ ] `main` is green in CI.
- [ ] You have the production `.env` values ready (see §4). `.env` is **git-ignored** — it never arrives via `git pull`.
- [ ] Decide the app directory, e.g. `~/keystone` (repo lives here; **not** inside `public_html`).

### DNS — the domain is registered but not yet pointed here

`keystonecapitalpartners.co.za` currently resolves to the registrar's "registered on behalf of a client" parking page. Nothing deploys until DNS points at the cPanel server:

- [ ] In cPanel, add `keystonecapitalpartners.co.za` (WHM/"Addon Domains" or "Domains") so the account will answer for it.
- [ ] At the registrar: either set the nameservers to the host's, **or** point an `A` record for `@` and `www` at the cPanel server IP (from cPanel → *Server Information* / the welcome email).
- [ ] Decide canonical host. The client advertises **`www.`**, so make `www.keystonecapitalpartners.co.za` canonical and 301 the apex → `www` (cPanel → *Domains* → Redirects, or an `.htaccess` rule).
- [ ] Wait for propagation (`dig www.keystonecapitalpartners.co.za` returns the cPanel IP), **then** run cPanel → *SSL/TLS Status* → **Run AutoSSL** so `https` works before first load.

---

## 2. First deploy (one time)

### A. Get the code
```bash
cd /home/keystof9z0m9
git clone https://github.com/Xoliswa00/keystone-loan-platform.git keystone
cd /home/keystof9z0m9/keystone
git checkout main
```
`vendor/` and `public/build/` come with the clone — **do not** run `composer install` or `npm install`.

> If `vendor/bin/*` scripts are needed later (they are not for a deploy) and error with "permission denied", run `chmod +x vendor/bin/*` — the executable bit is lost on Windows commits.

### B. Environment
```bash
cp .env.example .env
nano .env            # fill in per §4
php artisan key:generate
```

### C. Set the PHP version for the domain
cPanel → **MultiPHP Manager** → select `keystonecapitalpartners.co.za` → set to **`ea-php83`** (or 8.2). This is vhost-level and keeps working after the docroot change in step D. Don't hand-edit the `AddHandler` line in `public_html/.htaccess`.

### D. Point the web root at `public/`
- cPanel → **Domains** → set the Document Root for `keystonecapitalpartners.co.za` (and `www.`) to `/home/keystof9z0m9/keystone/public`.
- If cPanel refuses a root outside `public_html`: symlink it instead —
  ```bash
  rm -f /home/keystof9z0m9/public_html/index.html
  ln -s /home/keystof9z0m9/keystone/public /home/keystof9z0m9/public_html/app
  # then set the domain's docroot to public_html/app
  ```
- Do **not** use the "copy `public/*` up and edit `index.php` paths" hack — it breaks `storage:link` and every later `git pull`.
- `keystone/public/.htaccess` is committed (Laravel's rewrite rules). LiteSpeed reads it; `AllowOverride All` is the Afrihost default. Leave the stock `public_html/.htaccess` alone.

### E. Database
- cPanel → **MySQL Databases** → create DB + user (names auto-prefix to `keystof9z0m9_`), add the user to the DB with **ALL PRIVILEGES**.
- Into `.env`: `DB_DATABASE=keystof9z0m9_keystone`, `DB_USERNAME=keystof9z0m9_keystone`, `DB_PASSWORD=...`, `DB_HOST=127.0.0.1`.

### F. Migrate
```bash
php artisan migrate --force
```
Fresh-database migrations in this project have historically been fragile (column-type ordering). If it fails partway: read the error, fix forward, **do not** `migrate:fresh` once real data exists.

### G. Seed the baseline data — **required**, the app is non-functional without it
```bash
php artisan db:seed --force
```
`DatabaseSeeder` loads, in this order (order matters — FKs + observers):
`CompaniesSeeder → ChartOfAccountsAndGlMappingsSeeder → BranchesSeeder → RulesSeeder → LoanProductsSeeder → NcrPurposeCodesSeeder → LendingSettingsSeeder → FinancialPeriodsSeeder → AdminSeeder → LoanFeeRulesSeeder`.

`AdminSeeder` reads `ADMIN_EMAIL` / `ADMIN_PASSWORD` from `.env` and skips with a warning if they're unset — **set a strong `ADMIN_PASSWORD` before this step** (not the `admin123` from dev).

### H. Storage symlink
```bash
php artisan storage:link
```
Creates `public/storage → storage/app/public`. The company-logo upload writes to the `public` disk; without this the logo 404s. (This is Laravel 10 — the `local` disk is still `storage/app`, there is **no** L11 `storage/app/private` split. Don't "fix" disk paths for that.)

### I. Writable paths
```bash
chmod -R 755 storage bootstrap/cache
```
Afrihost PHP (LSAPI) runs as the account user `keystof9z0m9`, so 755 is enough — no shared web-user group to worry about.

### J. Caches
```bash
php artisan config:cache
php artisan view:cache
```
**Do NOT run `php artisan route:cache`** — `routes/web.php` and `routes/api.php` contain closure routes (`/`, `/dashboard`, notification marks, statement/agreement endpoints). `route:cache` aborts on closures and would 500 the whole site. Leave routes uncached, or convert those closures to controllers first.

### K. Cron — see §5.

### L. Restart PHP + smoke test
LiteSpeed caches the PHP process; after the first deploy force a fresh worker:
```bash
/usr/local/lsws/bin/lswsctrl restart 2>/dev/null || killall lsphp 2>/dev/null || true
```
(or cPanel → *Select PHP Version* → toggle and save, which recycles LSPHP). Then run §6.

---

## 3. Repeat deploy (every release)

```bash
cd ~/keystone
php artisan down                     # optional maintenance window
git fetch origin
git checkout main
git pull --ff-only origin main
# vendor/ and public/build/ update with the pull — nothing to rebuild

php artisan migrate --force
php artisan config:cache
php artisan view:cache
php artisan queue:restart            # harmless on sync; needed if you ever move to a worker
php artisan up
```
If `git pull` reports local changes to `vendor/bin/*` file modes, `git checkout -- vendor/bin` then re-pull, or `git config core.fileMode false` on the server once.

---

## 4. Production `.env` (differences from `.env.example`)

```dotenv
APP_NAME="Keystone Capital Partners"
APP_ENV=production
APP_DEBUG=false                      # never true in prod — leaks stack traces via Ignition
APP_URL=https://www.keystonecapitalpartners.co.za   # canonical host — asset URLs + emailed links

LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=keystof9z0m9_keystone
DB_USERNAME=keystof9z0m9_keystone
DB_PASSWORD=...

CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync                # keep sync on shared hosting; a DB/Redis queue needs a worker

MAIL_MAILER=smtp
MAIL_HOST=mail.keystonecapitalpartners.co.za
MAIL_PORT=587
MAIL_USERNAME=no-reply@keystonecapitalpartners.co.za
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="no-reply@keystonecapitalpartners.co.za"
MAIL_FROM_NAME="Keystone Capital Partners"

# One-time admin bootstrap (used by AdminSeeder, then can be removed)
ADMIN_EMAIL=admin@keystonecapitalpartners.co.za
ADMIN_PASSWORD=<<long random>>
```
Force HTTPS: `APP_URL` is `https` + cPanel AutoSSL (§1) + the apex→`www` and http→https redirects; the app does not force the scheme itself. Create the `no-reply@` mailbox in cPanel → *Email Accounts* first, and add an SPF record (cPanel usually auto-adds one) so agreement/OTP mail isn't spam-filed.

---

## 5. Cron

cPanel → **Cron Jobs** → add, every minute:

```
* * * * * /opt/cpanel/ea-php83/root/usr/bin/php /home/keystof9z0m9/keystone/artisan schedule:run >> /home/keystof9z0m9/keystone/storage/logs/schedule.log 2>&1
```
Confirm the binary path first: `which ea-php83` or `ls /opt/cpanel/ea-php8*/root/usr/bin/php`. Plain `php` in cron on Afrihost may resolve to a different version than the domain's.

This one entry drives all scheduled work: `keystone:send-payment-reminders` (08:00), `keystone:escalate-arrears` (09:00), `keystone:provision-monthly` (28th 22:00), `keystone:reconcile-gl` (weekdays 07:00), `keystone:accrue-facility-interest` (28th 22:30), `queue:retry all` (Sun 02:00).

---

## 6. Post-deploy smoke test

- [ ] `https://www.keystonecapitalpartners.co.za/` welcome page renders with CSS (asset URLs resolve → `public/build` + `storage:link` OK); apex and `http://` both 301 to it.
- [ ] Log in as the seeded admin → redirected to `/admin/dashboard`.
- [ ] `/admin/system/logs` loads for admin/it_admin (403 for other roles — the gate this platform explicitly enforces).
- [ ] Create a test loan application end to end → approve → disburse → check a GL batch posted.
- [ ] Company Settings → upload a logo → it displays (public disk + symlink).
- [ ] Trigger one email path (generate an agreement) → it arrives (SMTP creds OK).
- [ ] `php artisan schedule:list` shows the six commands; wait for the cron log to get a first line.
- [ ] Logs clean: `storage/logs/laravel.log` **and** `/home/keystof9z0m9/logs/php.error.log` (fatals that never reach Laravel land here). `APP_DEBUG=false` confirmed — force a 404, expect the generic page, no stack trace.

---

## 7. Rollback

```bash
cd /home/keystof9z0m9/keystone
php artisan down
git checkout <previous-good-tag-or-sha>
php artisan migrate:rollback --step=1     # only if the bad release added migrations AND they're reversible
php artisan config:cache && php artisan view:cache
php artisan up
```
Prefer rolling **forward** with a fix for anything data-related — several migrations in this repo are not cleanly reversible.

---

## 8. No SSH? (Afrihost "Home" plan)

If the plan genuinely has no SSH / Terminal, every `artisan` step still has to run somehow. Options, best first:

1. **Upgrade the plan** to one with SSH (Pro/Advanced or Cloud). This is a support-ticket change, not a migration — cleanest by far.
2. **Temporary web runner.** Drop a one-off, IP-restricted, token-gated script in `public/` that runs the exact command list (`migrate --force`, `db:seed --force`, `storage:link`, `config:cache`, `view:cache`) via `Artisan::call()`, hit it once from your browser, then **delete it in the same session**. Never leave it deployed.
3. **cPanel "Cron Jobs" as a one-shot.** Add each command with a near-future time, let it run once, then remove it — append `>> /home/keystof9z0m9/deploy.log 2>&1` and read the file for output. Covers `migrate --force`, `db:seed --force`, `storage:link`, `config:cache`, `view:cache`.

`route:cache` is *still* off-limits regardless of method (closure routes).

---

## 9. Afrihost / LiteSpeed specifics

- **LiteSpeed serves stale PHP after a deploy** until the LSPHP workers recycle. After `git pull` + `config:cache`, run `killall lsphp` (harmless — they respawn) or toggle the PHP version in *Select PHP Version* and save. Symptom if skipped: old code / "class not found" for a few minutes.
- **`.htaccess` works** under LiteSpeed (it's Apache-compatible), so Laravel's committed `public/.htaccess` is fine. The `# php -- BEGIN cPanel-generated handler` block in `public_html/.htaccess` is set by MultiPHP Manager — don't hand-edit it; changing PHP version there is what step 2C does properly.
- **Request timeout** on shared LiteSpeed is short (~100s). Fine for `sync` queue mail (agreements/OTP send inline in well under that), but if a future job gets heavy, move it to a real queue + a cron `queue:work --stop-when-empty` runner rather than lengthening request time.
- **`opcache`** is on. `php artisan config:cache` + the LSPHP recycle above is enough; no separate opcache reset needed.
- **MySQL host** is `127.0.0.1` (or `localhost`), not a remote host. Afrihost does not expose MySQL externally, so run migrations from the server, not from your machine.
- **`DB_HOST=localhost` vs `127.0.0.1`**: on Afrihost `localhost` uses a unix socket and is slightly faster; `127.0.0.1` forces TCP. Either works — if you get "could not find driver" it's a PHP extension problem (enable `mysqlnd`/`pdo_mysql` for `ea-php83`), not the host value.
