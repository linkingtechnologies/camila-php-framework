# Design — user management

## Architecture

Endpoints are **Camila core handlers** registered in `camila/api/cf_handlers.inc.php` and loaded by `CamilaApiController`. The base path is the one configured for the app (`cf_api.php`).

All endpoints require the current user to be an **administrator** (`CamilaAuth::isAdmin()`); otherwise they return `403 Forbidden`.

The management SPA (`camila/admin/app-users.js`) is mounted via `camila/admin/dashboard_users.inc.php` and is accessible from the Camila dashboard.

---

## Two-table model

Camila supports a split-table configuration where authentication credentials live in a separate table from the user profile:

| Property | `userTable` (`CAMILA_TABLE_USERS`) | `authUserTable` (`CAMILA_AUTH_TABLE_USERS`) |
|---|---|---|
| Purpose | Full user profile | Minimal auth credentials |
| Columns | `id, username, password, name, surname, grp, level, visibility_type, token, preferences, session_id, …` | `username, password, grp, level, visibility_type, attrib_01…attrib_15, preferences, session_id` |
| `name` / `surname` | Present | **Not present** |

When `authUserTable === userTable` (single-table mode), all data lives in one place. When they differ, the auth table is kept minimal and `name`/`surname` exist only in `userTable`.

### Per-operation behaviour when tables differ

| Operation | `userTable` | `authUserTable` | Notes |
|---|---|---|---|
| `createUser` | INSERT (all fields) | INSERT (`username, password, grp, level, visibility_type`) | `name`/`surname` not written to auth table — it has no such columns |
| `updateUser` | UPDATE | — | `name`/`surname` updates are safe; auth table has no such columns |
| `updatePassword` | — | UPDATE | Auth table is authoritative for passwords; `userTable.password` becomes stale but is never checked for login |
| `DELETE /users/*` | DELETE | **not touched** | Auth table record becomes an orphan. In practice the user cannot log in because session lookup uses `userTable` (which no longer has the row), but the orphan remains |
| Login query | session read/write | credential check | `CamilaAuth::getAuthUserInfoSqlFromUsername()` reads from `authUserTable`; `name`/`surname` are not returned by the login query |

---

## Endpoints

Base path: `/<app>/cf_api.php`

---

### GET /users

**Auth:** private — admins only

**Query params:**

| Param | Type | Default | Description |
|---|---|---|---|
| `username` | string | — | Partial filter on username |
| `page` | int | 1 | Page number (1-based) |
| `size` | int | 50 | Rows per page |

**Response (200):**
```json
{
  "records": [
    { "id": 1, "username": "mario", "name": "Mario", "surname": "Rossi", "grp": "default", "level": 5, "has_token": 0 }
  ],
  "total": 1,
  "page": 1,
  "size": 50
}
```

`has_token` is `1` if an API token is set on the user, `0` otherwise. The actual token value is never returned.

> **Note:** the raw `token` column is excluded from `SELECT` in `getUsers()` by design. The token is write-only: it can be set via `PATCH /users/{username}` but never read back through the API.

---

### POST /users

**Auth:** private — admins only

**Body:**
```json
{
  "username": "mario",
  "password": "s3cr3t",
  "name": "Mario",
  "surname": "Rossi",
  "grp": "default",
  "level": 5
}
```

`username` and `password` are required. All other fields are optional.

**Response (200):**
```json
{ "status": "ok", "username": "mario" }
```

**Errors:**
- `400` — username or password missing
- `409` — username already exists
- `500` — DB error (logged to `var/log/cf-api-errors.log`)

---

### PATCH /users/{username}

**Auth:** private — admins only

**Body:** one or more of `name`, `surname`, `grp`, `level`, `token`. `username` and `password` cannot be changed through this endpoint.

`token` is write-only: once saved it is never returned by the API. Setting it to an empty string clears it.

**Response (200):**
```json
{ "status": "ok", "username": "mario" }
```

**Errors:**
- `400` — no updatable fields provided
- `404` — user not found

---

### DELETE /users/{username}

**Auth:** private — admins only

Deletes the user from `CAMILA_TABLE_USERS` only. When `authUserTable !== userTable` the auth table record is left in place (orphan). The user cannot log in anyway because the session lookup uses `userTable`, but the orphan is not cleaned up.

**Response (200):**
```json
{ "status": "ok", "username": "mario" }
```

**Errors:**
- `400` — username missing in path
- `404` — user not found
- `500` — DB error

---

### POST /users/{username}/reset-password

**Auth:** private — admins only

Updates the password in `authUserTable` only (via a dedicated auth DB connection). When `authUserTable !== userTable`, `userTable.password` is not updated and becomes stale, but it is never used for login verification.

**Body:**
```json
{ "password": "new_password" }
```

**Response (200):**
```json
{ "status": "ok", "username": "mario" }
```

**Errors:**
- `400` — username or password missing
- `404` — user not found

---

## SPA

The management SPA (`camila/admin/app-users.js`) is documented separately: [admin-users design](../../admin-users/design.md).

---

## Files

| File | Role |
|---|---|
| `camila/api/cf_handlers.inc.php` | Handler implementation: GET, POST, PATCH, DELETE, reset-password |
| `camila/auth.class.inc.php` | `CamilaAuth`: createUser, updateUser, updatePassword, getUsers — two-table logic lives here |
