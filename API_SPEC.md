# Puja Fund REST API Spec (Phase 2)

This is the shared contract between the PHP REST API (`api/`) and the Flutter app
(`mobile_app/`). Both were built against this document — treat it as source of truth;
if either side deviates, fix the code to match this file rather than editing this file
to match the code.

Existing web app tables (`users`, `transactions`, `transfers`, `settings`) and helper
files (`db.php`, `year_helper.php`, `categories.php`, `lang.php`) are reused as-is.

## Auth

Token-based (the web app keeps using PHP sessions separately; this is only for API
clients). New table:

```sql
CREATE TABLE api_tokens (
  token VARCHAR(64) NOT NULL PRIMARY KEY,
  user_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- Token = 64-char random hex (`bin2hex(random_bytes(32))`), expires 30 days from issue.
- Every protected endpoint requires header: `Authorization: Bearer <token>`.
- Invalid/expired/missing token -> `401` with the standard error envelope.

## Envelope

All responses are `Content-Type: application/json`.

Success: `{"success": true, "data": <payload>}`
Error: `{"success": false, "error": "human-readable message"}`

HTTP status codes: 200 (ok), 201 (created), 400 (bad input), 401 (auth), 403
(forbidden — wrong role), 404 (not found), 500 (server error).

## Balance rule (must match the web app exactly)

Expenses are **never blocked** for insufficient balance. The fund balance is allowed to
go negative. When a transaction create/update of type `expense` results in a negative
pooled fund balance for that transaction's year, include a `warning` string field
alongside `data` in the response, e.g.:

```json
{"success": true, "data": {...}, "warning": "The fund balance for 2026 is now negative (৳-500.00)."}
```

The Flutter app shows a confirm dialog **before** submitting when the locally-known
fund balance would go negative (same UX as the web app's JS `confirm()`), then displays
the `warning` if the server still returns one (e.g. balance changed concurrently).

Pooled fund balance for a year = `SUM(collection.amount) - SUM(expense.amount) WHERE YEAR(date) = ?`.

Transfers remain a per-user check: a user can't transfer more than their own personal
running balance (`collections - expenses + transfers_in - transfers_out`, all-time, not
year-scoped) — this mirrors the existing web app behavior in `transactions.php` and is
unchanged.

## Endpoints

### `POST /api/auth/login.php`
Body: `{"email": "...", "password": "..."}`
201: `{"success": true, "data": {"token": "...", "expires_at": "2026-...", "user": {"id":1,"name":"...","email":"...","role":"manager"}}}`
401 on bad credentials.

### `POST /api/auth/logout.php` (auth)
Deletes the current token. 200: `{"success": true, "data": null}`

### `GET /api/me.php` (auth)
200: `{"success": true, "data": {"id":1,"name":"...","email":"...","role":"manager"}}`

### `GET /api/years.php` (auth)
200: `{"success": true, "data": {"active_year": 2026, "available_years": [2026, 2025]}}`

### `POST /api/settings.php` (auth, manager only)
Body: `{"active_year": 2027}`
200: `{"success": true, "data": {"active_year": 2027}}`

### `GET /api/dashboard.php?year=2026` (auth)
`year` optional, defaults to active year.
200 data: `{"year":2026,"total_collections":2000,"total_expenses":500,"balance":1500,"recent_transactions":[ {..transaction..} ]}`
(`recent_transactions`: manager sees all, member sees only their own — same as web.)

### `GET /api/transactions.php?year=&from=&to=&user_id=&type=` (auth)
Same filter semantics as `transactions.php`: `from`+`to` override `year`. Members only
ever see their own transactions in the list regardless of `user_id`; managers can pass
`user_id` to filter.
200 data: `{"transactions": [ {"id":1,"type":"collection","description":"...","amount":2000,"date":"2026-09-13","category":null,"added_by":1,"added_by_name":"...","created_at":"..."} ]}`

### `POST /api/transactions.php` (auth)
Body: `{"type":"collection|expense|transfer","description":"...","amount":100.5,"date":"2026-09-13","category":"decoration|null","transfer_user_id": null}`
- `type=transfer` -> creates a row in `transfers` with `status=pending` (identical to the
  web app's flow — it is NOT inserted into `transactions` directly; it becomes visible
  there only after a manager approves it via `POST /api/transfers.php`).
- `type=expense|collection` -> inserts into `transactions` for the current user.
201 data: `{"transaction": {...}}` (or `{"transfer": {...}}` for type=transfer), plus
top-level `warning` per the balance rule above when applicable.

### `PUT /api/transactions.php?id=123` (auth, owner or manager)
Body: same shape as POST minus `transfer_user_id` (transfers cannot be edited, matching
the web app — return 400 if the existing transaction's type is `transfer`).
200 data: `{"transaction": {...}}`, plus `warning` when applicable.

### `DELETE /api/transactions.php?id=123` (auth, manager only)
200: `{"success": true, "data": null}`

### `GET /api/transfers.php?year=` (auth, manager only)
200 data: `{"transfers": [ {"id":1,"from_user_id":2,"from_user_name":"...","to_user_id":3,"to_user_name":"...","amount":100,"description":"...","transfer_date":"...","status":"pending|completed|cancelled"} ]}`

### `POST /api/transfers.php?id=123&action=approve` (auth, manager only)
Approves a pending transfer (same side-effects as `approve_transfers.php`: inserts the
two `transactions` rows, marks transfer `completed`). 200 data: `{"transfer": {...}}`

### `POST /api/transfers.php?id=123&action=reject` (auth, manager only)
Marks `cancelled`. 200 data: `{"transfer": {...}}`

### `DELETE /api/transfers.php?id=123` (auth, manager only)
Deletes a completed transfer and its two linked transaction rows (same as
`transfers.php`'s delete). 200: `{"success": true, "data": null}`

### `GET /api/user-directory.php` (auth, any role)
Non-sensitive id/name list for populating a transfer-recipient picker — mirrors the web
app's `transactions.php`, where the plain `SELECT id, name FROM users ORDER BY name`
dropdown is visible to every logged-in user, not just managers.
200 data: `{"users": [ {"id":1,"name":"..."} ]}`

### `GET /api/users.php` (auth, manager only)
200 data: `{"users": [ {"id":1,"name":"...","email":"...","role":"manager","transaction_count":5,"total_collections":2000,"total_expenses":500} ]}` (stats scoped to `year` query param, default active year)

### `POST /api/users.php` (auth, manager only)
Body: `{"name":"...","email":"...","password":"...","role":"member|manager"}`
201 data: `{"user": {...}}`

### `PUT /api/users.php?id=123` (auth, manager only)
Body: `{"role":"manager|member"}` — cannot target own id (400).
200 data: `{"user": {...}}`

### `DELETE /api/users.php?id=123` (auth, manager only)
Cannot target own id (400). 200: `{"success": true, "data": null}`

### `GET /api/categories.php` (auth)
200 data: `{"categories": [ {"key":"prothima","en":"Prothima","bn":"প্রতিমা"} ] }`

### `GET /api/reports.php?from=&to=&user_id=&type=` (auth, manager only)
Same filters as `report.php`. 200 data:
`{"total_collection":2000,"total_expense":500,"balance":1500,"transactions":[...]}`

## Auth error example
```json
{"success": false, "error": "Invalid or expired token"}
```

## CORS
Add permissive dev headers on every `api/*.php` response (via a shared `api/bootstrap.php`
included first) so a Flutter *web* build can call it during development:
`Access-Control-Allow-Origin: *`, `Access-Control-Allow-Headers: Content-Type, Authorization`,
`Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS`, and short-circuit
`OPTIONS` requests with a bare `200`.
