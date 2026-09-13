# Upgrading an Existing Live Site (Phase 1 + Phase 2)

This guide is for updating a website that's **already live** with an older version of
Puja Fund. If you're installing fresh, use `DEPLOYMENT.md` instead — this file only
covers the *upgrade* path, so existing data is never touched.

## What's in this upgrade

- **Yearly system**: a manager-only Settings page to set the "active year"; everything
  else defaults to it, new entries are dated into it, old years stay browsable.
- **Balance fix**: adding an expense used to wrongly check your own personal
  contributions instead of the shared fund total. Fixed — expenses now check (and can
  be confirmed against) the real pooled fund balance, and are allowed to go negative
  with a confirmation instead of being silently blocked.
- **Mobile-responsive polish** on the website.
- **A REST API** (`api/`) and a **Flutter mobile app** (`mobile_app/`) — optional; the
  website works standalone without them.

## 1. Back up first

Always back up before touching a live database or file set.

```bash
# On the server (adjust credentials/db name to your live setup)
mysqldump -u your_db_user -p your_db_name > backup_before_upgrade_$(date +%Y%m%d).sql
```

Also download/zip a copy of the current live PHP files somewhere safe.

## 2. Upload the changed files

Upload everything from this repo **except**:

- `db.php` — **do not overwrite this on the live server.** The live `db.php` holds your
  real production database credentials; the one in this repo is a local placeholder
  (`localhost` / `root` / no password / `puja_fund`). Overwriting it will break the
  live site until you put the real credentials back.
- `mobile_app/` — this is a separate Flutter project, not part of the website. See
  section 5 below for how to build and ship it.
- `.claude/` — local tooling config, not part of the app.

Everything else (the modified `.php` files, `assets/`, `year_helper.php`,
`settings.php`, `api/`, the `.sql` migration files, etc.) should be uploaded, replacing
the old copies.

## 3. Run the database migrations

Two small, additive migration files are included — they only add new tables/rows, they
never touch or delete existing data. Run them against your live database, in this
order:

```bash
mysql -u your_db_user -p your_db_name < SETTINGS_UPDATE.sql
mysql -u your_db_user -p your_db_name < API_UPDATE.sql
```

(Or paste their contents into phpMyAdmin / your host's SQL runner if you don't have
shell access.)

- `SETTINGS_UPDATE.sql` adds the `settings` table and seeds `active_year` to the
  current calendar year.
- `API_UPDATE.sql` adds the `api_tokens` table, needed only if you're also deploying
  the REST API for the mobile app. Safe to run even if you're not using the API yet.

If you're not deploying the mobile app/API at all, you can skip `API_UPDATE.sql` — the
website itself doesn't need the `api_tokens` table.

## 4. Verify the website

1. Log in as a manager. Confirm the dashboard loads with the correct total balance.
2. Open **Settings** (new nav link, manager-only) — confirm it shows the current year
   as active and lets you switch/start years.
3. Add a test expense larger than the fund balance — confirm you get a confirmation
   dialog (not a hard block), and the balance can go negative if you confirm.
4. Check the site on a phone-width browser to confirm the mobile layout looks right.
5. Delete your test expense afterward if you don't want it in real records.

Do **not** re-run `installation.php` on a site that already has a manager account — it
already detects an existing install and won't create tables it thinks exist, but it's
meant for fresh installs only. If it's still present on your live server, consider
deleting it after confirming everything works (it's a security best practice for any
installer script left on a production server).

## 5. Deploying the REST API (optional, needed for the mobile app)

The API lives entirely under `api/` and reuses your existing `db.php`, so once you've
uploaded `api/` and run `API_UPDATE.sql`, it's live at
`https://your-domain.com/api/...`. Quick check:

```bash
curl -X POST https://your-domain.com/api/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"email":"your-manager-email","password":"your-password"}'
```

You should get back `{"success":true,"data":{"token":"...", ...}}`.

## 6. Building the Flutter app for real use

The app was built and tested locally against `http://<your-computer-ip>:8899`. For
real use, point it at your live domain instead, using `--dart-define`:

```bash
cd mobile_app
flutter pub get

# Debug build, to test on your own device against the live API:
flutter run --dart-define=API_BASE_URL=https://your-domain.com

# Release APK, to share with other Android users:
flutter build apk --release --dart-define=API_BASE_URL=https://your-domain.com
# Output: mobile_app/build/app/outputs/flutter-apk/app-release.apk

# iOS (requires a Mac + Xcode + an Apple Developer account to distribute):
flutter build ios --release --dart-define=API_BASE_URL=https://your-domain.com
```

Your live domain must be served over **HTTPS** for a release build shipped to real
users — plain HTTP is fine for local testing only, but Android/iOS restrict
cleartext HTTP traffic by default in release builds.

### Building from a different computer

Since this is now pushed to GitHub, setting up on another machine is:

```bash
git clone https://github.com/bijoyknath999/Puja-Fund.git
cd Puja-Fund/mobile_app
flutter pub get
flutter run --dart-define=API_BASE_URL=https://your-domain.com
```

Requires Flutter installed (`flutter doctor` should report no blocking issues) and, for
iOS builds, Xcode with CocoaPods.

## 7. Rolling back

If something goes wrong: restore the file backup, restore the database from the dump
taken in step 1 (`mysql -u user -p db_name < backup_before_upgrade_YYYYMMDD.sql`), and
put the original `db.php` back. Since the migrations are additive-only, a rollback of
just the PHP files (keeping the new `settings`/`api_tokens` tables in place) is also
safe — the old code simply won't reference those tables.
