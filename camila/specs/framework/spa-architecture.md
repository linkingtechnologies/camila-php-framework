# SPA Architecture Guide

The framework provides a lightweight SPA pattern based on [lit-html](https://lit.dev/docs/v1/lit-html/introduction/), [WorkTableClient](../api/worktable-client.md), and [Bulma CSS](https://bulma.io/).

No build step. No bundler. Each SPA is a plain ES module (`type="module"`) bootstrapped by a PHP entry point.

---

## 1. File structure

| File | Role |
|---|---|
| `app-<feature>.js` | SPA entry point — imports lit-html, initialises the API client, defines state and templates, mounts the app |
| `dashboard_<feature>.inc.php` | PHP bootstrap — injects `APP_CONFIG` and `I18N`, loads the JS module |
| `views/<feature>/index.js` | Optional — used by larger SPAs to separate logic from the entry point |

Core admin SPAs live in `camila/admin/`. Plugin feature SPAs live in the plugin directory.

**Reference implementation:** `camila/admin/app-users.js` — see [user-management design](../api/user-management/design.md).

---

## 2. PHP bootstrap

`dashboard_<feature>.inc.php` does three things:

```php
// 1. Build the API config
$config = [
    'baseUrl'           => $scheme . '://' . $host . '/app/' . CAMILA_APP_DIR . '/cf_api.php',
    'apiKeyHeaderName'  => 'Authorization',
    'apiKeyHeaderValue' => 'PHPSESSID',
];

// 2. Inject config + translations into global JS variables
$refrCode  = "<script src='../../camila/js/worktable-client.js'></script>";
$refrCode .= "<script>window.APP_CONFIG = " . json_encode($config, JSON_UNESCAPED_SLASHES) . "</script>";
$refrCode .= "<script>window.I18N = " . json_encode($i18n, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "</script>";
$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, $refrCode));

// 3. Mount the root element and load the ES module
echo '<div id="app"></div>';
$_CAMILA['page']->camila_add_js("<script type='module' src='app-feature.js'></script>");
```

**Conventions:**
- `window.APP_CONFIG.baseUrl` always points to `cf_api.php`
- `window.I18N` is a flat `key → string` object, populated via `camila_get_translation()`
- The i18n PHP variable must be named `$usersI18N` or similar — **not** `$i18n`, which is reserved by Camila/TinyButStrong for the M2Translator instance
- The module script uses `type="module"`, which defers it automatically
- Add a `<script nomodule>` block with a friendly unsupported-browser message

---

## 3. Entry point (`app-<feature>.js`)

```js
import { html, render } from "../js/lit-html/lit-html.js";

const root = document.getElementById("app");

// Guard against missing dependency
if (typeof WorkTableClient !== "function") {
  render(html`<div class="notification is-danger">WorkTableClient not available</div>`, root);
  throw Error("WorkTableClient not available");
}

// Initialise API client from PHP-injected config
const api = WorkTableClient(window.APP_CONFIG || {});

// i18n helper — %s placeholders replaced left to right
const t = (key, ...args) => {
  let s = window.I18N?.[key] ?? key;
  args.forEach(a => { s = s.replace('%s', a); });
  return s;
};
```

---

## 4. State management

State is a plain JS object. No reactive library, no proxy. Every mutation is followed by a `mount()` call to re-render the whole tree. lit-html diffs the DOM, so full re-renders are cheap.

```js
const state = {
  items:   [],
  loading: false,
  error:   null,
  modal:   null,   // null | { mode: 'create' | 'edit', data: {} }
  saving:  false,
};

function mount() { render(App(), root); }
```

---

## 5. API calls

`WorkTableClient` (initialised in the entry point) handles all HTTP. See [worktable-client](../api/worktable-client.md) for the full reference.

**Custom endpoint pattern:**

```js
async function loadItems() {
  state.loading = true;
  state.error   = null;
  mount();
  try {
    const res = await api.call("GET", "/some-endpoint", null, { page: state.page });
    state.items = res.records ?? [];
  } catch (e) {
    state.error = e?.payload?.message ?? e?.message ?? t("error.load");
  }
  state.loading = false;
  mount();
}
```

**Table-backed CRUD:**

```js
await api.table("volontari").list({ size: 200 });
await api.table("volontari").create({ nome: "Mario" });
await api.table("volontari").update(id, { servizio: "A" });
await api.table("volontari").remove(id);
```

Always use `filters: [api.filter(field, "eq", value)]` — the shorthand `{ filter: { field: value } }` is silently ignored.

---

## 6. Templates (lit-html)

Templates are functions that return `html` tagged template literals.

```js
function ItemRow(item) {
  return html`
    <tr>
      <td><strong>${item.name}</strong></td>
      <td class="has-text-right">
        <div class="buttons is-right">
          <button class="button is-small is-info is-light" @click=${() => openEdit(item)}>
            ${t("edit")}
          </button>
          <button class="button is-small is-danger is-light" @click=${() => deleteItem(item.id)}>
            ${t("delete")}
          </button>
        </div>
      </td>
    </tr>`;
}

function App() {
  return html`
    ${state.loading ? html`<progress class="progress is-small is-primary"></progress>` : ""}
    ${state.error   ? html`<article class="message is-danger"><div class="message-body">${state.error}</div></article>` : ""}
    <table class="table is-fullwidth is-striped is-hoverable">
      <tbody>${state.items.map(ItemRow)}</tbody>
    </table>`;
}
```

**lit-html attribute syntax:**

| Syntax | Use |
|---|---|
| `@click=${fn}` | Event listener |
| `?disabled=${bool}` | Boolean attribute (present/absent) |
| `.value=${expr}` | JS property (not HTML attribute) |
| `${expr}` | Text content or nested template |

---

## 7. Modals

Modal state lives in `state.modal = null | { mode, data }`. A single `Modal()` function renders the correct inner form based on `mode`.

```js
function Modal() {
  if (!state.modal) return html``;
  const { mode, data } = state.modal;
  return html`
    <div class="modal is-active">
      <div class="modal-background" @click=${closeModal}></div>
      <div class="modal-card">
        <header class="modal-card-head">
          <p class="modal-card-title">${t(`modal.${mode}`)}</p>
          <button class="delete" type="button" @click=${closeModal}></button>
        </header>
        <form @submit=${handleSubmit}>
          <section class="modal-card-body" style="font-size:0.85rem">
            ${mode === "create" ? ModalCreate()   : ""}
            ${mode === "edit"   ? ModalEdit(data) : ""}
            ${state.saveError ? html`
              <article class="message is-danger mt-3">
                <div class="message-body">${state.saveError}</div>
              </article>` : ""}
          </section>
          <footer class="modal-card-foot">
            <button class="button is-primary is-small" type="submit" ?disabled=${state.saving}>
              ${state.saving ? t("saving") : t("save")}
            </button>
            <button class="button is-small" type="button" @click=${closeModal}>
              ${t("cancel")}
            </button>
          </footer>
        </form>
      </div>
    </div>`;
}
```

Read form data from `FormData` — works with all `name`-bearing inputs including hidden fields:

```js
function handleSubmit(e) {
  e.preventDefault();
  const data = Object.fromEntries(new FormData(e.target).entries());
  saveItem(data);
}
```

---

## 8. Bulma CSS conventions

| Element | Classes |
|---|---|
| Primary action button | `button is-primary is-small` |
| Secondary / cancel button | `button is-small` |
| Action buttons in table rows | `button is-small is-info is-light` / `is-warning is-light` / `is-danger is-light` |
| Text input | `input is-small` |
| Select wrapper | `<div class="select is-small is-fullwidth"><select ...>` |
| Full-width table | `table is-fullwidth is-striped is-hoverable` |
| Error / success banner | `message is-danger` / `message is-success` with `message-body` inside |
| Loading bar | `progress is-small is-primary` |
| Status badge | `<span class="tag is-warning">` / `tag is-light` |
| Toolbar layout | `level mb-3` with `level-left` / `level-right` / `level-item` |

**Rule:** always add `is-small` to every form control inside a modal.

---

## 9. i18n

All user-visible strings come from `window.I18N`, injected by PHP via `camila_get_translation()`.

The `t(key, ...args)` helper replaces `%s` placeholders left to right:

```js
t("delete.confirm", username)   // "Delete user %s?" → "Delete user mario?"
t("error.load")                 // "Error loading data"
```

Key naming convention: `<feature>.<element>.<verb>` — e.g. `users.modal.create`, `users.field.username`.  
Core Camila keys are prefixed with `camila.` — e.g. `camila.users.title`.

After editing a lang file (`camila/lang/{lang}.lang.php`), delete `app/<app>/var/tmp/{lang}.lang.php` to force regeneration.

---

## 10. Conditional field logic

When one field's value should respond to another field's change, use a `@change` handler and find the sibling via `closest('form').querySelector(...)`. lit-html patches real DOM, so DOM traversal works normally:

```js
function onLevelChange(e) {
  const grp = e.target.closest('form').querySelector('input[name="grp"]');
  if (grp) grp.value = e.target.value === '1' ? '' : 'default';
}
// In the template:
// <select name="level" @change=${onLevelChange}>
```

---

## 11. Pagination

Server-side pagination via `page` (1-based) and `size` query params. Detect last page client-side: if returned array length < `size`, the Next button is disabled. Reset `state.page = 1` whenever the search filter changes.

```js
html`
  <nav class="pagination is-small" role="navigation">
    <button class="pagination-previous button" ?disabled=${state.page <= 1}
      @click=${() => { state.page--; loadItems(); }}>
      ${t("prev")}
    </button>
    <button class="pagination-next button" ?disabled=${state.items.length < state.size}
      @click=${() => { state.page++; loadItems(); }}>
      ${t("next")}
    </button>
  </nav>`
```
