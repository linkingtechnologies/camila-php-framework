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
    { "id": 1, "username": "mario", "name": "Mario", "surname": "Rossi", "grp": "default", "level": 5 }
  ],
  "total": 1,
  "page": 1,
  "size": 50
}
```

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

**Body:** one or more of `name`, `surname`, `grp`, `level`. `username` and `password` cannot be changed through this endpoint.

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

## SPA — app-users.js

lit-html application mounted in `#app` from the Camila dashboard.

### Features

| Feature | Description |
|---|---|
| Search | Live filter by username (GET with `username` param) |
| Pagination | Page navigation via `page`/`size` |
| Create user | Modal form (POST /users) |
| Edit user | Pre-filled modal form (PATCH /users/{username}) |
| Reset password | Dedicated modal (POST /users/{username}/reset-password) |
| Delete user | Confirm dialog + DELETE /users/{username} |

### Table badges

The **Group** and **Level** badges are not rendered when their value is null or empty string.

### Header

Fixed title with `ri-user-settings-line` icon (Remix Icons) and localised `users.title` text.

---

## i18n

UI strings are localised using the Camila lang system.

**Flow:**
1. Keys with prefix `camila.users.*` are defined in `camila/lang/{lang}.lang.php`
2. `dashboard_users.inc.php` reads translations via `camila_get_translation()` and injects them as `window.I18N` (JSON object) before the SPA loads
3. The SPA accesses strings via the `t(key, ...args)` helper with `%s` substitution for parameterised strings
4. The PHP variable for the i18n object is `$usersI18N` — **not** `$i18n`, which is reserved by Camila/TinyButStrong for the M2Translator instance

**Key prefix:** `camila.users.*`

**Lang cache:** after any edit to `camila/lang/{lang}.lang.php`, delete `app/<app>/var/tmp/{lang}.lang.php` to force regeneration.

**Defined keys:**

| Key | IT | EN |
|---|---|---|
| `camila.users.title` | Gestione utenti | User management |
| `camila.users.modal.create` | Nuovo utente | New user |
| `camila.users.modal.edit` | Modifica utente | Edit user |
| `camila.users.modal.reset` | Reset password | Reset password |
| `camila.users.new` | Nuovo utente | New user |
| `camila.users.saving` | Salvataggio... | Saving... |
| `camila.users.error.load` | Errore caricamento utenti | Error loading users |
| `camila.users.error.save` | Errore salvataggio | Error saving |
| `camila.users.error.delete` | Errore eliminazione utente | Error deleting user |
| `camila.users.empty` | Nessun utente trovato | No users found |
| `camila.users.search.placeholder` | Cerca per username... | Search by username... |
| `camila.users.reset.button` | Reset pwd | Reset pwd |
| `camila.users.delete.confirm` | Eliminare l'utente "%s"? L'operazione non e' reversibile. | Delete user "%s"? This action cannot be undone. |
| `camila.users.page` | Pagina | Page |
| `camila.users.prev` | Precedente | Previous |
| `camila.users.next` | Successiva | Next |
| `camila.users.level.admin` | Amministratore | Administrator |
| `camila.users.level.default` | Predefinito | Default |
| `camila.users.field.username` | Username | Username |
| `camila.users.field.password` | Password | Password |
| `camila.users.field.name` | Nome | First name |
| `camila.users.field.surname` | Cognome | Last name |
| `camila.users.field.group` | Gruppo | Group |
| `camila.users.field.level` | Livello | Level |
| `camila.users.field.new_password_for` | Nuova password per | New password for |

---

## Files

| File | Role |
|---|---|
| `camila/api/cf_handlers.inc.php` | Handler implementation: GET, POST, PATCH, DELETE, reset-password |
| `camila/auth.class.inc.php` | `CamilaAuth`: createUser, updateUser, updatePassword, getUsers — two-table logic lives here |
| `camila/admin/app-users.js` | lit-html SPA |
| `camila/admin/dashboard_users.inc.php` | SPA mount point, injects `window.APP_CONFIG` and `window.I18N` |
| `camila/lang/it.lang.php` | `camila.users.*` keys — Italian |
| `camila/lang/en.lang.php` | `camila.users.*` keys — English |
