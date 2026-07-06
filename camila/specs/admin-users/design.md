# Design — admin-users SPA

**PHP bootstrap:** `camila/admin/dashboard_users.inc.php`  
**SPA file:** `camila/admin/app-users.js`

See [user-management API](../api/user-management/design.md) for the backend endpoints.  
See [SPA architecture guide](../framework/spa-architecture.md) for the general framework patterns.

---

## 1. Structure

Single-page admin tool for managing Camila framework users. The page is a toolbar + search bar + table + pagination. All mutations happen in modal overlays.

```
[Search: username…                    ] [+ New user]
┌──────────────┬──────┬─────────┬─────────┬───────────┬──────────────────────────┐
│ Username     │ Nome │ Cognome │ Gruppo  │ Livello   │                          │
├──────────────┼──────┼─────────┼─────────┼───────────┼──────────────────────────┤
│ mario        │ Mario│ Rossi   │ default │ Default   │ [Edit] [Reset pwd] [Del] │
│ admin        │      │         │         │ Admin     │ [Edit] [Reset pwd] [Del] │
└──────────────┴──────┴─────────┴─────────┴───────────┴──────────────────────────┘
[← Previous                                                             Next →  ]
```

> Column headers are hardcoded in Italian (`Nome`, `Cognome`, `Gruppo`, `Livello`) — not wired to `I18N`.

---

## 2. State shape

```js
state = {
  users:     Array,           // current page returned by API
  page:      Number,          // 1-based page index
  size:      Number,          // page size, default 20
  search:    String,          // username filter (sent as query param)
  loading:   Boolean,
  error:     String | null,
  modal:     null | { mode: 'create' | 'edit' | 'reset', user: Object },
  saving:    Boolean,
  saveError: String | null,
}
```

---

## 3. Modals

Three modes share the same outer `Modal()` shell (title bar, form, footer with Save/Cancel):

| Mode | Content function | Fields |
|---|---|---|
| `create` | `ModalCreate()` | username\*, password\*, name, surname, group, level |
| `edit` | `ModalEdit(user)` | name, surname, group, level (username shown disabled) |
| `reset` | `ModalReset(user)` | new password\* |

The username field in `edit` mode is a disabled `<input>` for display only; the actual value is carried by a hidden `<input name="username">`.

### Level → group auto-set (create modal only)

Selecting `level = 1` (Administrator) clears the `grp` field to `""` — administrators belong to no group. Any other level defaults `grp` to `"default"`. Implemented as a `@change` handler on the level `<select>`:

```js
function onCreateLevelChange(e) {
  const grp = e.target.closest('form').querySelector('input[name="grp"]');
  if (grp) grp.value = e.target.value === '1' ? '' : 'default';
}
```

The group field remains editable — this is a convenience default only.

---

## 4. Level display

| `level` | Label | Tag style |
|---|---|---|
| `1` | Administrator | `tag is-warning` |
| other / empty | Default | `tag is-light` |

The Group and Level badges are not rendered when their value is null or empty string.

---

## 5. Pagination

The API is called with `page` (1-based) and `size` query params. The **Next** button is disabled when the returned array length is less than `size` (last page detected client-side). **Previous** is disabled on page 1. Changing the search string resets `state.page` to 1.

---

## 6. i18n

UI strings are injected by `dashboard_users.inc.php` as `window.I18N` and accessed via the `t(key, ...args)` helper. Key prefix: `camila.users.*`.

The PHP variable must be named `$usersI18N` — **not** `$i18n`, which is reserved by Camila/TinyButStrong for the M2Translator instance.

After editing `camila/lang/{lang}.lang.php`, delete `app/<app>/var/tmp/{lang}.lang.php` to force cache regeneration.

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

## 7. Files

| File | Role |
|---|---|
| `camila/admin/app-users.js` | Full SPA — state, templates, API calls (~270 lines) |
| `camila/admin/dashboard_users.inc.php` | PHP bootstrap: injects `APP_CONFIG`, `I18N`, loads module |
| `camila/lang/it.lang.php` | `camila.users.*` keys — Italian |
| `camila/lang/en.lang.php` | `camila.users.*` keys — English |
| `app/<app>/cf_api.php` | Thin wrapper → `camila/api/cf_api_controller.inc.php` |
