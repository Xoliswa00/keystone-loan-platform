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

The account is fresh: `public_html` holds only the stock cPanel `index` + `.htaccess`. Manage domain/DNS in **Afrihost ClientZone**; NS are `ns.dns1.co.za` / `ns.dns2.co.za` (+ `ns.otherdns.com` / `.net`). Server IP: **`197.242.159.146`**.

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

### DNS — already pointed at the server

The zone is on Afrihost NS (`ns.dns1.co.za`, `ns.dns2.co.za`, `ns.otherdns.com/.net`) and the records are already live:

| Host | Type | Value | Meaning |
|---|---|---|---|
| `@`, `www`, `*` | A | **`197.242.159.146`** | the cPanel box (`keystof9z0m9`). Apex and `www` both already resolve here. |
| `@` | MX 10 | `mx7967617497.spe.ucebox.co.za` | **mail is external** — Afrihost's aserv/ucebox platform, *not* this cPanel account. See §4 mail. |
| `@` | TXT | `v=spf1 include:spf.aserv.co.za +a +mx -all` | SPF already covers aserv **and** the server's own IP (`+a`). |
| `_dmarc` | TXT | `v=DMARC1; p=none; adkim=s; aspf=s` | strict alignment — send From `@keystonecapitalpartners.co.za` through aserv so DKIM aligns. |

So there is **no registrar or nameserver change to make**. The "registered on behalf of a client" page you saw is just Afrihost's default for an account with nothing deployed yet.

- [ ] Confirm `keystonecapitalpartners.co.za` is the account's primary domain in cPanel (it will be, given the A records) — or add it as an addon/alias if not.
- [ ] Canonical host = **`www.`** (client advertises it). 301 apex → `www` via cPanel → *Domains* → Redirects, or a rule in `keystone/public/.htaccess`.
- [ ] After the app is deployed and the docroot is set (step D), run cPanel → *SSL/TLS Status* → **Run AutoSSL** for both `@` and `www` (no CAA record blocks it). A-record TTL is 7200s, so allow ~2h if anything is changed.

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
`keystonecapitalpartners.co.za` is the account's **primary** domain, and Afrihost locks the primary domain's docroot to `~/public_html`. Two ways to deal with it:

**Preferred — replace `public_html` with a symlink to the app's `public/`:**
```bash
cd /home/keystof9z0m9
rm -rf public_html                       # it only holds the stock index + .htaccess
ln -s keystone/public public_html
```
LiteSpeed follows the symlink; `keystone/public/.htaccess` (committed, Laravel's rewrite rules) then applies. If Afrihost's setup won't serve through a symlinked `public_html` (test: `curl -I https://www.keystonecapitalpartners.co.za/` → expect Laravel, not 403), fall back to:

**Fallback — add a subdomain/alias** `app.keystonecapitalpartners.co.za` (or re-add the domain as an alias) whose docroot *can* be set, point it at `/home/keystof9z0m9/keystone/public`, and 301 the primary domain to it.

- Do **not** copy `public/*` up and rewrite `index.php`'s paths — it breaks `storage:link` and every later `git pull`.
- After a symlink swap, re-check the PHP version stuck (step C) — `MultiPHP Manager` is vhost-level so it should, but verify with a `phpinfo()` probe you delete immediately.

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

# Mail is on Afrihost's aserv platform (per the MX/SRV records), NOT this
# cPanel account. Use the aserv submission host, not mail.keystone... (that
# A record just points back at the web box).
MAIL_MAILER=smtp
MAIL_HOST=envoy.aserv.co.za
MAIL_PORT=587                        # 465/ssl also offered; match what ClientZone shows for the mailbox
MAIL_USERNAME=no-reply@keystonecapitalpartners.co.za
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="no-reply@keystonecapitalpartners.co.za"
MAIL_FROM_NAME="Keystone Capital Partners"

# One-time admin bootstrap (used by AdminSeeder, then can be removed)
ADMIN_EMAIL=admin@keystonecapitalpartners.co.za
ADMIN_PASSWORD=<<long random>>
```

**Mail setup (external — don't use cPanel Email Accounts):**
- Create the `no-reply@keystonecapitalpartners.co.za` mailbox in **Afrihost ClientZone → Email**, not cPanel. Note the outgoing server / port it gives you and use those for `MAIL_HOST`/`MAIL_PORT`.
- cPanel → **Email Routing** for the domain → set to **Remote Mail Exchanger** (MX points to `ucebox`, so cPanel must not deliver locally).
- SPF (`include:spf.aserv.co.za +a +mx -all`) and DMARC already exist in the zone. DMARC is strict-alignment (`adkim=s; aspf=s`) — sending through the aserv mailbox above keeps DKIM/From aligned. Do **not** fall back to PHP `mail()` / cPanel `sendmail` (no aligned DKIM → poor deliverability; `p=none` means it won't hard-fail, but treat that as luck).
- Verify after deploy: generate an agreement, confirm it arrives and lands in inbox, not spam.

**Force HTTPS:** `APP_URL` is `https` + AutoSSL (§1) + the apex→`www` and http→https redirects. The app does not force the scheme itself.

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
