// app-users.js
import { html, render } from "../js/lit-html/lit-html.js";

const root = document.getElementById("app");

if (typeof WorkTableClient !== "function") {
  render(html`<div class="notification is-danger">WorkTableClient non disponibile</div>`, root);
  throw Error("WorkTableClient non disponibile");
}

const api = WorkTableClient(window.APP_CONFIG || {});

const t = (key, ...args) => {
  let s = window.I18N?.[key] ?? key;
  args.forEach(a => { s = s.replace('%s', a); });
  return s;
};

// ── State ──────────────────────────────────────────────────────────────────

const state = {
  users:     [],
  page:      1,
  size:      20,
  search:    "",
  loading:   false,
  error:     null,
  modal:     null,   // null | { mode: 'create'|'edit'|'reset', user?: {} }
  saving:    false,
  saveError: null,
};

// ── API ────────────────────────────────────────────────────────────────────

async function loadUsers() {
  state.loading = true;
  state.error   = null;
  mount();
  try {
    const params = { page: state.page, size: state.size };
    if (state.search.trim()) params.username = state.search.trim();
    const res = await api.call("GET", "/users", null, params);
    state.users = res.records ?? [];
  } catch (e) {
    state.error = e?.payload?.message ?? e?.message ?? t("users.error.load");
  }
  state.loading = false;
  mount();
}

async function saveUser(data) {
  state.saving    = true;
  state.saveError = null;
  mount();
  try {
    if (state.modal.mode === "create") {
      await api.call("POST", "/users", data);
    } else if (state.modal.mode === "edit") {
      const { username, ...fields } = data;
      await api.call("PATCH", `/users/${username}`, fields);
    } else if (state.modal.mode === "reset") {
      await api.call("POST", `/users/${data.username}/reset-password`, { password: data.password });
    }
    state.modal  = null;
    state.saving = false;
    await loadUsers();
  } catch (e) {
    state.saving    = false;
    state.saveError = e?.payload?.message ?? e?.message ?? t("users.error.save");
    mount();
  }
}

// ── Modal helpers ──────────────────────────────────────────────────────────

function openCreate() { state.modal = { mode: "create", user: {} }; state.saveError = null; mount(); }
function openEdit(u)   { state.modal = { mode: "edit",   user: { ...u } }; state.saveError = null; mount(); }
function openReset(u)  { state.modal = { mode: "reset",  user: { username: u.username } }; state.saveError = null; mount(); }
function closeModal()  { state.modal = null; state.saveError = null; mount(); }

async function deleteUser(username) {
  if (!confirm(t("users.delete.confirm", username))) return;
  try {
    await api.call("DELETE", `/users/${username}`);
    await loadUsers();
  } catch (e) {
    state.error = e?.payload?.message ?? e?.message ?? t("users.error.delete");
    mount();
  }
}

function handleSubmit(e) {
  e.preventDefault();
  const data = Object.fromEntries(new FormData(e.target).entries());
  if (data.level) data.level = parseInt(data.level, 10);
  saveUser(data);
}

// ── Templates ──────────────────────────────────────────────────────────────

function levelLabel(l) { return parseInt(l) === 1 ? t("users.level.admin") : t("users.level.default"); }

function ModalCreate() {
  return html`
    <div class="field"><label class="label">${t("users.field.username")} *</label>
      <input class="input" type="text" name="username" required autocomplete="off"></div>
    <div class="field"><label class="label">${t("users.field.password")} *</label>
      <input class="input" type="password" name="password" required autocomplete="new-password"></div>
    <div class="columns">
      <div class="column field"><label class="label">${t("users.field.name")}</label>
        <input class="input" type="text" name="name"></div>
      <div class="column field"><label class="label">${t("users.field.surname")}</label>
        <input class="input" type="text" name="surname"></div>
    </div>
    <div class="columns">
      <div class="column field"><label class="label">${t("users.field.group")}</label>
        <input class="input" type="text" name="grp" value="default"></div>
      <div class="column field"><label class="label">${t("users.field.level")}</label>
        <div class="select is-fullwidth"><select name="level">
          <option value="5">${t("users.level.default")}</option>
          <option value="1">${t("users.level.admin")}</option>
        </select></div>
      </div>
    </div>`;
}

function ModalEdit(u) {
  return html`
    <input type="hidden" name="username" value="${u.username}">
    <div class="field"><label class="label">${t("users.field.username")}</label>
      <input class="input" type="text" value="${u.username}" disabled></div>
    <div class="columns">
      <div class="column field"><label class="label">${t("users.field.name")}</label>
        <input class="input" type="text" name="name" value="${u.name ?? ""}"></div>
      <div class="column field"><label class="label">${t("users.field.surname")}</label>
        <input class="input" type="text" name="surname" value="${u.surname ?? ""}"></div>
    </div>
    <div class="columns">
      <div class="column field"><label class="label">${t("users.field.group")}</label>
        <input class="input" type="text" name="grp" value="${u.grp ?? "default"}"></div>
      <div class="column field"><label class="label">${t("users.field.level")}</label>
        <div class="select is-fullwidth"><select name="level">
          <option value="5" ?selected=${parseInt(u.level) !== 1}>${t("users.level.default")}</option>
          <option value="1" ?selected=${parseInt(u.level) === 1}>${t("users.level.admin")}</option>
        </select></div>
      </div>
    </div>`;
}

function ModalReset(u) {
  return html`
    <input type="hidden" name="username" value="${u.username}">
    <div class="field"><label class="label">${t("users.field.new_password_for")} <strong>${u.username}</strong></label>
      <input class="input" type="password" name="password" required autocomplete="new-password"></div>`;
}

function Modal() {
  if (!state.modal) return html``;
  const { mode, user } = state.modal;
  const titles = { create: t("users.modal.create"), edit: t("users.modal.edit"), reset: t("users.modal.reset") };
  return html`
    <div class="modal is-active">
      <div class="modal-background" @click=${closeModal}></div>
      <div class="modal-card">
        <header class="modal-card-head">
          <p class="modal-card-title">${titles[mode]}</p>
          <button class="delete" type="button" @click=${closeModal}></button>
        </header>
        <form @submit=${handleSubmit}>
          <section class="modal-card-body">
            ${mode === "create" ? ModalCreate() : ""}
            ${mode === "edit"   ? ModalEdit(user) : ""}
            ${mode === "reset"  ? ModalReset(user) : ""}
            ${state.saveError ? html`
              <article class="message is-danger mt-3">
                <div class="message-body">${state.saveError}</div>
              </article>` : ""}
          </section>
          <footer class="modal-card-foot">
            <button class="button is-primary" type="submit" ?disabled=${state.saving}>
              ${state.saving ? t("users.saving") : t("save")}
            </button>
            <button class="button" type="button" @click=${closeModal}>${t("cancel")}</button>
          </footer>
        </form>
      </div>
    </div>`;
}

function App() {
  return html`
    ${Modal()}
    <div class="box mb-4">
      <h3 class="title is-4 mb-0">
        <span class="icon is-medium" style="vertical-align: middle; margin-right: 0.4rem;">
          <i class="ri-user-settings-line ri-lg"></i>
        </span>
        ${t("users.title")}
      </h3>
    </div>
    <div class="level mb-3">
      <div class="level-left">
        <div class="level-item">
          <input class="input" type="text" placeholder="${t("users.search.placeholder")}"
            .value=${state.search}
            @input=${e => { state.search = e.target.value; state.page = 1; loadUsers(); }}>
        </div>
      </div>
      <div class="level-right">
        <div class="level-item">
          <button class="button is-primary" @click=${openCreate}>
            <span class="icon"><i class="ri-add-line"></i></span>
            <span>${t("users.new")}</span>
          </button>
        </div>
      </div>
    </div>

    ${state.error ? html`
      <article class="message is-danger"><div class="message-body">${state.error}</div></article>` : ""}

    ${state.loading ? html`<progress class="progress is-small is-primary"></progress>` : ""}

    <table class="table is-fullwidth is-striped is-hoverable">
      <thead><tr>
        <th>Username</th><th>Nome</th><th>Cognome</th><th>Gruppo</th><th>Livello</th><th></th>
      </tr></thead>
      <tbody>
        ${state.users.length === 0 && !state.loading
          ? html`<tr><td colspan="6" class="has-text-centered has-text-grey">${t("users.empty")}</td></tr>`
          : state.users.map(u => html`
            <tr>
              <td><strong>${u.username}</strong></td>
              <td>${u.name ?? ""}</td>
              <td>${u.surname ?? ""}</td>
              <td>${u.grp ? html`<span class="tag">${u.grp}</span>` : ""}</td>
              <td>${u.level != null && u.level !== "" ? html`<span class="tag ${parseInt(u.level) === 1 ? "is-warning" : "is-light"}">${levelLabel(u.level)}</span>` : ""}</td>
              <td class="has-text-right">
                <div class="buttons is-right">
                  <button class="button is-small is-info is-light" @click=${() => openEdit(u)}>${t("edit")}</button>
                  <button class="button is-small is-warning is-light" @click=${() => openReset(u)}>${t("users.reset.button")}</button>
                  <button class="button is-small is-danger is-light" @click=${() => deleteUser(u.username)}>${t("delete")}</button>
                </div>
              </td>
            </tr>`)}
      </tbody>
    </table>

    <nav class="pagination is-small" role="navigation">
      <button class="pagination-previous button" ?disabled=${state.page <= 1}
        @click=${() => { state.page--; loadUsers(); }}>${t("users.prev")}</button>
      <button class="pagination-next button" ?disabled=${state.users.length < state.size}
        @click=${() => { state.page++; loadUsers(); }}>${t("users.next")}</button>
      <ul class="pagination-list">
        <li><span class="pagination-link is-current">${t("users.page")} ${state.page}</span></li>
      </ul>
    </nav>`;
}

function mount() { render(App(), root); }

loadUsers();
