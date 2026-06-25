// app-users.js
import { html, render } from "../js/lit-html/lit-html.js";

const root = document.getElementById("app");

if (typeof WorkTableClient !== "function") {
  render(html`<div class="notification is-danger">WorkTableClient non disponibile</div>`, root);
  throw Error("WorkTableClient non disponibile");
}

const api = WorkTableClient(window.APP_CONFIG || {});

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
    state.error = e?.payload?.message ?? e?.message ?? "Errore caricamento utenti";
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
    state.saveError = e?.payload?.message ?? e?.message ?? "Errore salvataggio";
    mount();
  }
}

// ── Modal helpers ──────────────────────────────────────────────────────────

function openCreate() { state.modal = { mode: "create", user: {} }; state.saveError = null; mount(); }
function openEdit(u)   { state.modal = { mode: "edit",   user: { ...u } }; state.saveError = null; mount(); }
function openReset(u)  { state.modal = { mode: "reset",  user: { username: u.username } }; state.saveError = null; mount(); }
function closeModal()  { state.modal = null; state.saveError = null; mount(); }

function handleSubmit(e) {
  e.preventDefault();
  const data = Object.fromEntries(new FormData(e.target).entries());
  if (data.level) data.level = parseInt(data.level, 10);
  saveUser(data);
}

// ── Templates ──────────────────────────────────────────────────────────────

function levelLabel(l) { return parseInt(l) === 1 ? "Amministratore" : "Predefinito"; }

function ModalCreate() {
  return html`
    <div class="field"><label class="label">Username *</label>
      <input class="input" type="text" name="username" required autocomplete="off"></div>
    <div class="field"><label class="label">Password *</label>
      <input class="input" type="password" name="password" required autocomplete="new-password"></div>
    <div class="columns">
      <div class="column field"><label class="label">Nome</label>
        <input class="input" type="text" name="name"></div>
      <div class="column field"><label class="label">Cognome</label>
        <input class="input" type="text" name="surname"></div>
    </div>
    <div class="columns">
      <div class="column field"><label class="label">Gruppo</label>
        <input class="input" type="text" name="grp" value="default"></div>
      <div class="column field"><label class="label">Livello</label>
        <div class="select is-fullwidth"><select name="level">
          <option value="5">Predefinito</option>
          <option value="1">Amministratore</option>
        </select></div>
      </div>
    </div>`;
}

function ModalEdit(u) {
  return html`
    <input type="hidden" name="username" value="${u.username}">
    <div class="field"><label class="label">Username</label>
      <input class="input" type="text" value="${u.username}" disabled></div>
    <div class="columns">
      <div class="column field"><label class="label">Nome</label>
        <input class="input" type="text" name="name" value="${u.name ?? ""}"></div>
      <div class="column field"><label class="label">Cognome</label>
        <input class="input" type="text" name="surname" value="${u.surname ?? ""}"></div>
    </div>
    <div class="columns">
      <div class="column field"><label class="label">Gruppo</label>
        <input class="input" type="text" name="grp" value="${u.grp ?? "default"}"></div>
      <div class="column field"><label class="label">Livello</label>
        <div class="select is-fullwidth"><select name="level">
          <option value="5" ?selected=${parseInt(u.level) !== 1}>Predefinito</option>
          <option value="1" ?selected=${parseInt(u.level) === 1}>Amministratore</option>
        </select></div>
      </div>
    </div>`;
}

function ModalReset(u) {
  return html`
    <input type="hidden" name="username" value="${u.username}">
    <div class="field"><label class="label">Nuova password per <strong>${u.username}</strong></label>
      <input class="input" type="password" name="password" required autocomplete="new-password"></div>`;
}

function Modal() {
  if (!state.modal) return html``;
  const { mode, user } = state.modal;
  const titles = { create: "Nuovo utente", edit: "Modifica utente", reset: "Reset password" };
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
              ${state.saving ? "Salvataggio…" : "Salva"}
            </button>
            <button class="button" type="button" @click=${closeModal}>Annulla</button>
          </footer>
        </form>
      </div>
    </div>`;
}

function App() {
  return html`
    ${Modal()}
    <div class="level mb-3">
      <div class="level-left">
        <div class="level-item">
          <input class="input" type="text" placeholder="Cerca per username…"
            .value=${state.search}
            @input=${e => { state.search = e.target.value; state.page = 1; loadUsers(); }}>
        </div>
      </div>
      <div class="level-right">
        <div class="level-item">
          <button class="button is-primary" @click=${openCreate}>+ Nuovo utente</button>
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
          ? html`<tr><td colspan="6" class="has-text-centered has-text-grey">Nessun utente trovato</td></tr>`
          : state.users.map(u => html`
            <tr>
              <td><strong>${u.username}</strong></td>
              <td>${u.name ?? ""}</td>
              <td>${u.surname ?? ""}</td>
              <td><span class="tag">${u.grp ?? ""}</span></td>
              <td><span class="tag ${parseInt(u.level) === 1 ? "is-warning" : "is-light"}">${levelLabel(u.level)}</span></td>
              <td class="has-text-right">
                <div class="buttons is-right">
                  <button class="button is-small is-info is-light" @click=${() => openEdit(u)}>Modifica</button>
                  <button class="button is-small is-warning is-light" @click=${() => openReset(u)}>Reset pwd</button>
                </div>
              </td>
            </tr>`)}
      </tbody>
    </table>

    <nav class="pagination is-small" role="navigation">
      <button class="pagination-previous button" ?disabled=${state.page <= 1}
        @click=${() => { state.page--; loadUsers(); }}>Precedente</button>
      <button class="pagination-next button" ?disabled=${state.users.length < state.size}
        @click=${() => { state.page++; loadUsers(); }}>Successiva</button>
      <ul class="pagination-list">
        <li><span class="pagination-link is-current">Pagina ${state.page}</span></li>
      </ul>
    </nav>`;
}

function mount() { render(App(), root); }

loadUsers();
