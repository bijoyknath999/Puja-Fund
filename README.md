# Puja Fund

A community fund tracker for a Puja committee: a PHP + MySQL web app, a JSON REST
API, and a companion Flutter app for managers and members — all sharing one
database. Bilingual (English/Bengali) throughout.

## What's here

- **Web app** (repo root, e.g. `index.php`, `transactions.php`, `users.php`, ...) —
  the primary interface. Session-based login, Bootstrap 5 UI.
- **REST API** (`api/`) — token-based JSON API used by the mobile app. See
  [API_SPEC.md](API_SPEC.md) for the full endpoint contract.
- **Mobile app** (`mobile_app/`) — Flutter app for Android/iOS, for managers and
  members. See [mobile_app/README.md](mobile_app/README.md) (Flutter's default) and
  the "Mobile app" section below for the parts specific to this project.

## Features

- **Yearly fund tracking**: a manager sets the "active year" (Settings page); every
  page defaults to it, new entries are dated into it, and past years stay browsable
  without losing data.
- **Pooled fund balance**: collections and expenses are tracked against one shared
  fund total per year, not per member. Adding an expense that would take the fund
  negative asks for confirmation instead of being blocked outright.
- **Transfers**: a member requests a transfer to another member; a manager
  approves/rejects it before it becomes a real transaction.
- **Roles**: `manager` (full access — users, reports, settings, approvals) and
  `member` (their own transactions + transfer requests).
- **Bilingual**: English and Bengali (বাংলা) on both the web app and the mobile app,
  switchable at any time.
- **Mobile-responsive** web UI, plus the native Flutter app for phones/tablets.

## Setup (fresh install)

1. Upload all files (except `mobile_app/`, which is a separate project — see below)
   to your web server.
2. Create a MySQL database and import the schema:
   ```bash
   mysql -u your_user -p your_db < db_schema.sql
   ```
3. Edit `db.php` with your real database credentials.
4. Visit the site in a browser — with no users yet, it redirects to
   `installation.php` to create the first manager account. **Delete
   `installation.php` after that succeeds** (it's a setup wizard, not meant to stay
   on a live server).
5. Log in and start using it. Optionally deploy `api/` too if you plan to use the
   mobile app (it reuses `db.php`, no separate config needed).

### Security notes

- Use a strong database password and a strong manager password.
- Serve over HTTPS in production — the mobile app requires it for release builds.
- Keep regular database backups (`mysqldump`).
- Delete `installation.php` once setup is done.

## Mobile app

Lives in `mobile_app/`, built with Flutter (Provider for state, a thin `ApiClient`
wrapper around the REST API). The API address is a plain constant in
`mobile_app/lib/services/api_client.dart` (`_defaultBaseUrl`) — edit that string to
point at your server, then build:

```bash
cd mobile_app
flutter pub get
flutter run                       # debug, on a connected device/emulator
flutter build apk --release       # Android release APK
flutter build ios --release       # iOS (needs Xcode + an Apple Developer account)
```

## Tech stack

- **Web**: PHP (MySQLi, prepared statements throughout), Bootstrap 5, vanilla JS.
- **API**: plain PHP under `api/`, bearer-token auth (`api_tokens` table), JSON
  envelope responses — see [API_SPEC.md](API_SPEC.md).
- **Mobile**: Flutter, Provider, `http`, `shared_preferences`.
- **Database**: MySQL — `users`, `transactions`, `transfers`, `settings`,
  `api_tokens`.

## License

Open source, MIT License.
