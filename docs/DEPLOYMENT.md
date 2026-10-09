# Deployment runbook: PHP + MySQL backend and admin

This moves jobs, applications and site settings into MySQL with an admin area at `/admin/`.
The public pages stay `.html` and keep the same URLs, so Google Ads landing pages and bookmarks don't change.

Set up the database once by importing `database/schema.sql` in phpMyAdmin (step 4). After that, later database changes are applied **automatically** when an admin opens the admin area, so you never import SQL again. (If you skip the import, the first admin visit creates the same tables and data by itself.)

---

## 1. Requirements

| Item | Needed |
| --- | --- |
| PHP | 7.4 or newer, with `pdo_mysql`, `openssl` or `sodium`, `mbstring`; `fileinfo` strongly recommended (CV type checks) |
| Database | MySQL 5.7+ or MariaDB 10.3+, utf8mb4 |
| Database user | Must be allowed to CREATE, ALTER, INDEX, REFERENCES, SELECT, INSERT, UPDATE, DELETE, DROP (DROP is only used by restore) |
| Web server | Apache with `.htaccess` enabled (`AllowOverride All`). On Nginx, block the folders listed in step 9 manually |
| Writable folders | `storage/` (CVs, backups, app key), `assets/data/` (public backup data), `uploads/` (logo) |

## 2. What's in the package

| Path | Purpose | Web access |
| --- | --- | --- |
| `*.html`, `assets/` | Public pages, scripts, styles | Public |
| `assets/data/*.json` | Saved copy of jobs and contact details, used if the API is unreachable. Rewritten automatically | Public |
| `api/` | `jobs.php`, `settings.php`, `form-token.php`, `apply.php` | Public |
| `sendmail.php` | Accepts posts from cached copies of the old form for 30 days | Public |
| `admin/` | Admin area | Login required |
| `config/` | `config.php` (your settings, not in git) | Blocked |
| `includes/`, `database/`, `cron/`, `docs/`, `PHPMailer-7.0.1/` | Code, migrations, fixtures | Blocked |
| `storage/` | `cvs/`, `backups/`, `app.key` | Blocked |
| `uploads/` | Uploaded logo images only | Images only, no PHP |

---

## 3. Before you start (5 minutes)

1. In cPanel File Manager, compress the current `public_html` and download the zip.
2. In the repo, tag the current live state so the old frontend can be restored exactly:
   ```bash
   git tag pre-php-migration <commit-that-is-live>
   git push origin pre-php-migration
   ```
3. Note the current form behaviour: submit one test application on the live site and confirm you receive it. You'll compare against this later.

## 4. One-time hosting setup

1. cPanel → **MySQL Databases**: create a database and a user, add the user to the database with **All Privileges**. Write down the full database name, user name and password (on cPanel both names usually start with your account name, e.g. `cpuser_hubjob`).
2. cPanel → **phpMyAdmin**: click the new database in the left column, open the **Import** tab, choose `database/schema.sql`, click **Import**. You should see 9 tables: `admins`, `applications`, `audit_log`, `auto_migrations`, `categories`, `jobs` (30 rows), `login_attempts`, `rate_limits`, `settings`. Importing it a second time is harmless.
3. Open `config/config.php` (already in the package; `config.sample.php` is the blank template) and replace the three `CHANGE_ME` values with the database name, user and password from step 1. Leave `host` as `localhost` unless your host says otherwise.
   - `app_url`: your public address, e.g. `https://www.hubjobplatform.com` (used in email links).
   - `app_key` is already filled with a random key generated for this site. Don't share it.
   - Optional `cv_storage_path` / `backup_storage_path`: absolute paths **outside** `public_html` if your host allows it (e.g. `/home/ACCOUNT/hubjob-private/cvs`).
4. **Keep `app_key` safe and never change it.** It decrypts the saved SMTP password and captcha secret. If it's lost, re-enter those two secrets in Admin → Settings.

There is no `.env` file: PHP hosting reads the settings from `config/config.php`, which the `config/.htaccess` file blocks from the web.

## 5. Upload the backend (public pages unchanged)

Upload these, **without** the HTML/JS/CSS changes and **without `sendmail.php` yet**:

- `config/` (with your `config.php`), `includes/`, `api/`, `admin/`, `database/`, `cron/`, `docs/`
- `storage/` (with its `.htaccess`), `uploads/` (with its `.htaccess`)
- `assets/data/` (the two JSON files)
- The root `.htaccess` (merge with your existing one if you have rules there)
- `PHPMailer-7.0.1/` is already on the server

At this point the live site still uses the old pages and the old `sendmail.php`, so applications keep arriving by email as before.

Folder permissions (cPanel → File Manager → Permissions): `storage/`, `storage/cvs/`, `storage/backups/` **750**; `assets/data/` and `uploads/` **755** (must be writable by PHP).

## 6. First admin visit: create your login

1. Open `https://YOUR-SITE/admin/setup.php`.
2. The page checks the database and shows "The database is ready". If you imported `schema.sql` there is nothing to apply; if you skipped the import, it creates the tables, settings, 8 categories and 30 jobs now.
   - If it shows a red error, it's almost always database permissions or wrong details in `config.php`. Fix it and reload; updates are retried on every load and never duplicate anything.
3. Create the **owner** account (name, email, password of 10+ characters).
4. Sign in at `/admin/`. `setup.php` stops working as soon as one admin exists.

## 7. Verify the data (sign-off)

1. **Admin → Job data check** (`/admin/verify.php`). All five checks must be green:
   - All 30 original jobs are in the database.
   - No duplicate reference codes.
   - All 8 categories exist.
   - Original listing text untouched.
   - Public backup file matches live jobs.
   Each job shows "Matches"; nothing is "Missing".
2. **Admin → Database updates**: every update "Success", server checks green (folders writable, encryption available, finfo available).
3. **Admin → Categories**: 8 categories with these job counts: Tech 4, Support 2, Operations 4, Marketing 4, Video & Motion 2, Sales 5, Design 4, Education 5.
4. **Admin → Jobs**: 30 active jobs, 6 featured (TECH-01, OPS-03, MKT-02, SLS-01, DES-02, EDU-01).

Re-running the updates is safe: seeds use `INSERT IGNORE` on unique keys (job ref code, category slug, setting key), so nothing is duplicated and admin edits are never overwritten.

## 8. Email setup and tests

1. **Admin → Settings → Email delivery**: SMTP server, port, encryption, username, password, "send from" address. Optionally a separate "Send new applications to" address.
   - The password is encrypted before it's saved and is never shown again. Leaving the field empty keeps it.
   - Alternatively, put the SMTP details in `config/config.php` under `smtp_override`; the Settings page then shows them as read-only.
2. Click **Send test notification**, then **Send test confirmation** (to your own address). Each is tested on its own.
3. Email checklist:
   - [ ] Correct credentials: both test emails arrive.
   - [ ] **Wrong password**: change the password to something wrong, submit a test application on the site. The applicant still sees "submitted", the application appears in Admin → Applications with email status "Failed" and the error message, and the dashboard lists it with a Resend button. Put the right password back and press Resend.
   - [ ] Port **587 with TLS** works; if your host blocks it, try **465 with SSL**.
   - [ ] **SPF and DKIM** are set for the sending domain (cPanel → Email Deliverability shows both as valid).
   - [ ] Test emails land in the inbox, not spam (check Gmail and Outlook).

## 9. Check the public API and the blocked folders

Open these in a browser:

| URL | Expected |
| --- | --- |
| `/api/jobs.php` | JSON with 30 jobs and 8 categories |
| `/api/settings.php` | JSON with contact details only. **No** `smtp_*`, `notification_email`, password or secret keys |
| `/api/form-token.php` | `{"token": "..."}` |
| `/assets/data/jobs-snapshot.json` | Same jobs as the API |
| `/config/config.php`, `/includes/db.php`, `/storage/`, `/storage/app.key`, `/database/fixtures/jobs.php`, `/docs/DEPLOYMENT.md` | **403 Forbidden** |
| `/uploads/` | 403 (no listing) |

## 10. Cut over the frontend

Upload together, in one go:

- All HTML pages: `index.html`, `jobs.html`, `about.html`, `faq.html`, `apply.html`, `contact.html`, **new** `privacy.html`
- `assets/js/` and `assets/css/`
- `sendmail.php` (the compatibility version)

If you use Cloudflare or another cache, purge it afterwards.

## 11. How links and forms behave during the switch

| Situation | What happens |
| --- | --- |
| Backend uploaded, setup not run yet | Old pages and old `sendmail.php` keep working. The admin area shows the setup page. |
| Setup run, frontend not switched yet | Old pages still work. Jobs are in the database but the old jobs page still shows its built-in list. |
| Visitor has the **old** apply/contact page cached and submits after the switch | It posts to `sendmail.php`, which accepts the old fields (age, nationality) for 30 days, saves the application (form version "legacy", nationality in the notes) and sends the emails. After 30 days it asks the visitor to reload the page. |
| Old links `jobs.html?work=remote` / `worldwide` / `ukeu` / `flexible` | Mapped to the new filters (work arrangement, applicant region, flexible schedule). |
| Old links `jobs.html?category=...` | Work, including after a category is renamed (old URL names are remembered). |
| Old links `apply.html?role=Content%20Writer` | The role is pre-filled; if it exactly matches a job title, the application is linked to that job. Otherwise it's kept as free text. |
| New links `apply.html?job=12&role=...` | The form shows "Applying for ..." and links the application to job 12. |
| API down (PHP error, database down) | Jobs and homepage use `assets/data/jobs-snapshot.json` with a notice; if that fails too, an error card with Retry, "Send us your CV anyway" and contact details. Contact details fall back to the values in the HTML. |
| Database down when someone applies | The form shows an error with the contact email, and nothing is lost silently. |

## 12. Test matrix (run after cutover)

Run on desktop and on a phone (or browser mobile view).

| Page | API working | API blocked (rename `api/` to `api_off/` temporarily) | After admin changes |
| --- | --- | --- | --- |
| Home | Featured jobs + 8 category tiles with counts | Same data from the saved copy; or the "browse all roles" card | Rename a category → new name on the tile |
| Jobs | 30 roles; each filter on its own (category, type, arrangement, region, flexible, pay band, search) | Amber "saved list" notice, roles still shown | Move a job to another category → appears there; set a job to Draft → disappears |
| Apply | Submit with CV → success, both emails; `?job=ID` shows the job box | Submit → clear error with the contact email | Change contact email in Settings → shown in footer and error messages |
| Contact | Submit without CV → success | Same as Apply | Change phone/address → updated on the page |
| About, FAQ, Privacy | Contact details from settings | Contact details from the HTML | Retention months change → Privacy page updates |

Also check:
- [ ] A second identical application (same email + job within 24 hours) is refused with a friendly message.
- [ ] Six quick submissions from one connection: the sixth is refused (limit 5 per hour).
- [ ] Uploading a `.exe` renamed to `.pdf` is refused; a 3MB PDF is refused.
- [ ] Admin → Applications: View opens a PDF in the browser; a DOCX downloads.
- [ ] Final literal search (see `docs/content-inventory.md`, "Re-audit"): every match carries a `data-setting` attribute or is on the static list.

## 13. Daily retention job

Applications are anonymised and their CVs deleted 12 months after the last update unless marked **Keep**.

- Recommended: cPanel → **Cron Jobs**, once a day:
  ```
  15 3 * * * /usr/local/bin/php /home/ACCOUNT/public_html/cron/purge.php >/dev/null 2>&1
  ```
- Without cron, the same check runs at most once a day when an admin signs in.
- Admin → Settings → Data retention has a "Run the retention check now" button.

## 14. Future updates

1. Upload the changed files, including any new file in `database/auto-migrations/`.
2. Open any admin page. Pending updates are applied automatically after a backup is taken; a green banner lists what was applied.
3. If an update fails, a red banner shows the error; the update is retried on every admin page load, and Admin → Database updates shows the details.

Rules for new migration files: name them `YYYY_MM_DD_NNNNNN_short_name.php`, only use the idempotent helpers (`ensureTable`, `ensureColumn`, `ensureIndex`, `ensureSetting`, `INSERT IGNORE`), and never edit a file that has already run.

`database/schema.sql` is only the starting point for a new, empty database. Don't re-import it to apply updates; new migration files do that. It marks migrations 000001–000004 as applied, so any later migration still runs automatically on top of it.

## 15. Backups

- Automatic: before every database update and before every restore. The newest 10 are kept in `storage/backups/`.
- Manual: Admin → Database updates → **Back up now**, then **Download**.
- Not included in the SQL backup: CV files (`storage/cvs/`) and the key (`storage/app.key`). Back these up with File Manager or your host's backup tool.

## 16. Rollback

**Triggers:** the job data check fails, application submissions fail, or the jobs page shows the error card in production.

**Frontend only (most cases, a few minutes):**
1. Restore the HTML, `assets/js`, `assets/css` and `sendmail.php` from the `pre-php-migration` tag (or the zip from step 3).
2. The backend can stay in place; nothing on the old pages uses it.
3. Applications received through the new form stay in Admin → Applications.

**Database (only if the data itself is wrong):**
1. Admin → Applications → **Export CSV** (keeps any applications that arrived since the backup).
2. Copy `storage/cvs/` to your computer.
3. Admin → Database updates → pick the backup → type `RESTORE` → **Restore**. A "pre-restore" backup of the current state is taken first, so a restore can itself be undone.
4. Restoring also restores admin accounts and the update history; you may need to sign in again.

**Full reset to the old site:** restore the zip from step 3 and leave the database untouched for later.

## 17. Security notes

- Roles: **owner** (settings, users, exports, deletion, database tools) and **recruiter** (jobs, categories, applications, CVs).
- Sessions end after 30 minutes without activity. Five failed sign-ins from one connection lock sign-in for 15 minutes.
- Every CV view or download, edit, deletion, setting change and sign-in is recorded and shown in the application history.
- Optional extra protection: add cPanel **Directory Privacy** (password) on the `admin` folder.
- Keep `debug` set to `false` in `config/config.php` on the live site.
