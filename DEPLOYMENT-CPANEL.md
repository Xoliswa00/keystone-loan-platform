# Deploying Keystone to cPanel shared hosting

Laravel **10** · PHP target **8.1** (composer platform is pinned to 8.1) · MySQL **8.0** · queue `sync` · assets and `vendor/` are **committed**, so no Composer or npm run on the server.

---

## 0. cPanel account must provide

| Requirement | Notes |
|---|---|
| PHP **8.1 or 8.2** via MultiPHP Manager | Not 8.0, not 8.4. `composer.lock` platform is pinned to 8.1; 8.2 runs the code fine, 8.4 changes `str_getcsv()`/other defaults. |
| PHP extensions | `bcmath, ctype, curl, dom, fileinfo, gd, intl, mbstring, mysqlnd, openssl, pdo_mysql, tokenizer, xml, zip` (CI baseline is `mbstring, dom, fileinfo, mysql, gd, zip` — add the rest). |
| SSH access | Needed for `artisan` commands. cPanel "Terminal" is fine. |
| MySQL database + user | `utf8mb4`, user granted **ALL PRIVILEGES** on the DB. |
| Cron jobs | One entry, see §5. Without it, arrears escalation / payment reminders / IFRS 9 provisioning / GL reconciliation never run. |
| Ability to set the domain document root to a subfolder | Point the domain/subdomain at `.../public`. See §2 step C. |
| Outbound SMTP | Real mail credentials — signed agreements and investor OTP codes are emailed. |

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
cd ~
git clone https://github.com/Xoliswa00/keystone-loan-platform.git keystone
cd ~/keystone
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

### C. Point the web root at `public/`
- cPanel → **Domains** (or Subdomains) → set the Document Root for the site to `~/keystone/public`.
- If cPanel refuses a root outside `public_html`: create the subdomain with root `public_html/keystone` and instead **symlink** it:
  ```bash
  rm -rf ~/public_html/keystone
  ln -s ~/keystone/public ~/public_html/keystone
  ```
- Do **not** use the "copy `public/*` up and edit `index.php` paths" hack — it breaks `storage:link` and every later `git pull`.
- Confirm `~/keystone/public/.htaccess` exists (it's committed) and the host has `AllowOverride All` / mod_rewrite (cPanel default).

### D. Database
- cPanel → **MySQL Databases** → create DB + user, add user to DB with ALL PRIVILEGES.
- Put the DB name / user / password into `.env` (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_HOST=127.0.0.1` or `localhost`).

### E. Migrate
```bash
php artisan migrate --force
```
Fresh-database migrations in this project have historically been fragile (column-type ordering). If it fails partway: read the error, fix forward, **do not** `migrate:fresh` once real data exists.

### F. Seed the baseline data — **required**, the app is non-functional without it
```bash
php artisan db:seed --force
```
`DatabaseSeeder` loads, in this order (order matters — FKs + observers):
`CompaniesSeeder → ChartOfAccountsAndGlMappingsSeeder → BranchesSeeder → RulesSeeder → LoanProductsSeeder → NcrPurposeCodesSeeder → LendingSettingsSeeder → FinancialPeriodsSeeder → AdminSeeder → LoanFeeRulesSeeder`.

`AdminSeeder` reads `ADMIN_EMAIL` / `ADMIN_PASSWORD` from `.env` and skips with a warning if they're unset — **set a strong `ADMIN_PASSWORD` before this step** (not the `admin123` from dev).

### G. Storage symlink
```bash
php artisan storage:link
```
Creates `public/storage → storage/app/public`. The company-logo upload writes to the `public` disk; without this the logo 404s. (This is Laravel 10 — the `local` disk is still `storage/app`, there is **no** L11 `storage/app/private` split. Don't "fix" disk paths for that.)

### H. Writable paths
```bash
chmod -R 755 storage bootstrap/cache
```
On most cPanel setups PHP runs as your account user, so 755 is enough. If the host uses a shared web user, use `775` and set the group.

### I. Caches
```bash
php artisan config:cache
php artisan view:cache
```
**Do NOT run `php artisan route:cache`** — `routes/web.php` and `routes/api.php` contain closure routes (`/`, `/dashboard`, notification marks, statement/agreement endpoints). `route:cache` aborts on closures and would 500 the whole site. Leave routes uncached, or convert those closures to controllers first.

### J. Cron — see §5.

### K. Smoke test — see §6.

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
DB_DATABASE=cpaneluser_keystone
DB_USERNAME=cpaneluser_keystone
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
* * * * * /usr/local/bin/php ~/keystone/artisan schedule:run >> ~/keystone/storage/logs/schedule.log 2>&1
```
(Use the PHP 8.1/8.2 binary path from MultiPHP — often `/usr/local/bin/ea-php81`.)

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
- [ ] `storage/logs/laravel.log` has no unexpected errors; `APP_DEBUG=false` confirmed (force a 404 → generic page, no stack trace).

---

## 7. Rollback

```bash
cd ~/keystone
php artisan down
git checkout <previous-good-tag-or-sha>
php artisan migrate:rollback --step=1     # only if the bad release added migrations AND they're reversible
php artisan config:cache && php artisan view:cache
php artisan up
```
Prefer rolling **forward** with a fix for anything data-related — several migrations in this repo are not cleanly reversible.
