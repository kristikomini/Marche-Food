# API.md
## Marche International Food S.R.L. — Route & Request Reference

> **Note:** This application does not expose a REST JSON API. All routes are served by Laravel and return Inertia.js responses — either a full HTML page (first visit) or a JSON payload that Inertia uses for client-side navigation. There is no `api.php` route file and no token-based authentication.

---

## 1. Conventions

### Authentication
All routes except `GET /login` and `POST /login` require a valid Laravel session cookie (`laravel_session`). Requests without it receive a `302` redirect to `/login`.

### Authorization
Two tiers of access enforcement:

| Middleware | Applied To | Effect on Failure |
|---|---|---|
| `auth` | All routes except login | 302 → `/login` |
| `admin` (`EnsureAdmin`) | DELETE verbs, schede CRUD, flussi, import, user management | 302 → `/` with `error` flash (JSON 403 if `X-Inertia` + `Accept: application/json`) |

### Request Format
- **GET** requests: query-string parameters only.
- **POST/PUT/DELETE** requests: `application/x-www-form-urlencoded` or `multipart/form-data` (file uploads). Laravel's form method spoofing (`_method=PUT` / `_method=DELETE`) is used from Vue forms via Inertia.
- **CSRF**: Every state-changing request requires the `X-XSRF-TOKEN` header (set automatically by Inertia) or the `_token` form field.

### Response Format
- On success: `302` redirect with a `success` flash message.
- On validation failure: `422` with `errors` object (handled by Inertia and displayed in forms).
- On authorization failure: `302` redirect (or `403` for JSON requests).
- Inertia page responses carry a shared `auth.user` prop (`{id, name, role}`) and a `flash` prop (`{success, error}`).

### Pagination
List endpoints return Laravel's default paginator with 25 rows per page (`LengthAwarePaginator`). Pagination state is preserved in query strings via `withQueryString()`.

---

## 2. Endpoint Map

### Auth

| Method | Path | Auth | Body / Query | Description |
|---|---|---|---|---|
| `GET` | `/login` | Guest only | — | Render Login.vue |
| `POST` | `/login` | Guest only | `email`, `password`, `remember` | Authenticate user, regenerate session. `throttle:10,1` |
| `POST` | `/logout` | `auth` | — | Invalidate session and redirect to `/login` |
| `GET` | `/forgot-password` | Guest only | — | Render ForgotPassword.vue |
| `POST` | `/forgot-password` | Guest only | `email` | Send password-reset email. `throttle:5,1` |
| `GET` | `/reset-password/{token}` | Guest only | — | Render ResetPassword.vue |
| `POST` | `/reset-password` | Guest only | `token`, `email`, `password`, `password_confirmation` | Validate token and set new password |

---

### Dashboard

| Method | Path | Auth | Query | Description |
|---|---|---|---|---|
| `GET` | `/` | `auth` | — | KPI stats, last 5 acquisti, last 5 produzioni, lots expiring in 30 days |

---

### Tracciabilità

| Method | Path | Auth | Query | Description |
|---|---|---|---|---|
| `GET` | `/tracciabilita` | `auth` | — | Render empty traceability search page |
| `GET` | `/tracciabilita/search` | `auth` | `q` (min 2 chars) | Forward + reverse lot search across `acquisti_righe`, `produzioni`, and `vendite_righe` |

---

### Recall (stateful workflow)

| Method | Path | Auth | Body / Query | Description |
|---|---|---|---|---|
| `GET` | `/recall` | `auth` | `q` | Search tool (productions + customer sales of a lot) **and** list of registered recalls |
| `POST` | `/recall` | `auth` | `lotto`, `prodotto`, `motivo` | Open a recall; auto-populates `recall_notifiche` from sales of the lot |
| `GET` | `/recall/{id}` | `auth` | — | Recall detail with per-customer notification list and progress |
| `PUT` | `/recall/{id}/stato` | `auth` | `stato` (aperto\|in_corso\|chiuso) | Change recall state (sets `data_chiusura` when chiuso) |
| `POST` | `/recall/{id}/notifiche/{notifica}` | `auth` | `notificato` (bool) | Mark a customer notification done/undone (auto-advances to in_corso) |

### Reportistica & Magazzino

| Method | Path | Auth | Query | Description |
|---|---|---|---|---|
| `GET` | `/report` | `auth` | `da`, `a` | Management report: totals (acquisti/vendite/produzioni, conto-terzi excluded), per-supplier / per-customer, expiry list |
| `GET` | `/report/csv` | `auth` | `da`, `a` | Management report as CSV |
| `GET` | `/report/pdf` | `auth` | `da`, `a` | Management report as PDF (dompdf) |
| `GET` | `/magazzino` | `auth` | `solo_giacenza` (bool) | Stock report: purchase-lot + semilavorato balances |
| `GET` | `/magazzino/export` | `auth` | `solo_giacenza` | Stock report as CSV |
| `GET` | `/audit` | `admin` | — | Audit log: who created/modified operational records |

### Document PDFs

| Method | Path | Auth | Description |
|---|---|---|---|
| `GET` | `/acquisti/{id}/pdf` | `auth` | DDT/purchase document as PDF (dompdf) |
| `GET` | `/vendite/{id}/pdf` | `auth` | Sales document (DDT/invoice) as PDF (dompdf) |
| `GET` | `/produzioni/{id}/pdf` | `auth` | HACCP production report as PDF (dompdf) |

---

### Anagrafica — Fornitori

| Method | Path | Auth | Body / Query | Description |
|---|---|---|---|---|
| `GET` | `/fornitori` | `auth` | — | Paginated supplier list |
| `GET` | `/fornitori/create` | `admin` | — | Create form |
| `POST` | `/fornitori` | `admin` | See validation below | Create supplier |
| `GET` | `/fornitori/{id}/edit` | `admin` | — | Edit form |
| `PUT` | `/fornitori/{id}` | `admin` | See validation below | Update supplier |
| `DELETE` | `/fornitori/{id}` | `admin` | — | Delete supplier |

**Fornitore validation fields:** `ragione_sociale` (required, max 200), `tipo` (required, enum: `alimentare\|imballaggio_primario\|detergente_secondario\|conto_terzi`), `codice` (nullable, unique), `piva`, `indirizzo`, `email`, `telefono`, `haccp_certificato` (bool), `haccp_scadenza` (date), `certificazioni_note`, `moca_certificato` (bool), `moca_numero`, `attivo` (bool), `note`.

---

### Anagrafica — Clienti

| Method | Path | Auth | Body / Query | Description |
|---|---|---|---|---|
| `GET` | `/clienti` | `auth` | — | Paginated customer list |
| `GET` | `/clienti/create` | `admin` | — | Create form |
| `POST` | `/clienti` | `admin` | See below | Create customer |
| `GET` | `/clienti/{id}/edit` | `admin` | — | Edit form |
| `PUT` | `/clienti/{id}` | `admin` | See below | Update customer |
| `DELETE` | `/clienti/{id}` | `admin` | — | Delete customer |

**Cliente validation fields:** `codice_cliente` (required, unique), `ragione_sociale` (required), `piva`, `indirizzo`, `email`, `telefono`, `attivo` (bool), `note`.

---

### Anagrafica — Prodotti

| Method | Path | Auth | Body / Query | Description |
|---|---|---|---|---|
| `GET` | `/prodotti` | `auth` | — | Product catalogue list |
| `GET` | `/prodotti/create` | `admin` | — | Create form |
| `POST` | `/prodotti` | `admin` | See below | Create product |
| `GET` | `/prodotti/{id}/edit` | `admin` | — | Edit form |
| `PUT` | `/prodotti/{id}` | `admin` | — | Update product |
| `DELETE` | `/prodotti/{id}` | `admin` | — | Delete product |

**Prodotto validation fields:** `codice_prodotto` (required, unique), `nome` (required), `pezzatura_valore` (nullable, numeric), `pezzatura_um`, `um_id` (nullable, FK), `attivo` (bool), `note`.

---

### Anagrafica — Materie Prime

| Method | Path | Auth | Body / Query | Description |
|---|---|---|---|---|
| `GET` | `/materie-prime` | `auth` | — | Raw materials list |
| `GET` | `/materie-prime/create` | `admin` | — | Create form |
| `POST` | `/materie-prime` | `admin` | `codice`, `nome`, `um_id` | Create raw material |
| `GET` | `/materie-prime/{id}/edit` | `admin` | — | Edit form |
| `PUT` | `/materie-prime/{id}` | `admin` | — | Update raw material |
| `DELETE` | `/materie-prime/{id}` | `admin` | — | Delete raw material |

---

### Anagrafica — Destinazione Ingredienti

| Method | Path | Auth | Body | Description |
|---|---|---|---|---|
| `GET` | `/destinazione-ingredienti` | `auth` | — | Ingredient → product mapping matrix |
| `POST` | `/destinazione-ingredienti` | `admin` | `prodotto_id`, `materia_prima_id` | Add mapping |
| `DELETE` | `/destinazione-ingredienti/{id}` | `admin` | — | Remove mapping |

---

### Screen 1 — Acquisti (Food Purchases)

| Method | Path | Auth | Body / Query | Description |
|---|---|---|---|---|
| `GET` | `/acquisti` | `auth` | `search`, `fornitore_id`, `da`, `a` | Paginated list with filters |
| `GET` | `/acquisti/create` | `auth` | — | Create form (food suppliers only) |
| `POST` | `/acquisti` | `auth` | See below | Create purchase document + lines |
| `GET` | `/acquisti/{id}/edit` | `auth` | — | Edit form with existing lines |
| `PUT` | `/acquisti/{id}` | `auth` | See below | Replace document header + all lines |
| `DELETE` | `/acquisti/{id}` | `admin` | — | Delete document (cascades lines) |
| `GET` | `/acquisti/{id}/print` | `auth` | — | Print view (DDT-style layout) |
| `GET` | `/acquisti/export` | `auth` | — | Download all acquisti_righe as UTF-8 BOM CSV (semicolon-delimited) |

**Acquisto body:** `fornitore_id`, `numero_documento`, `data_documento`, `tipo_documento` (DDT\|Fattura\|Bolla), `is_conto_terzi` (boolean; esclude il documento da magazzino e KPI finanziari), `note`, `righe[]` (array, min 1): each riga has `id` (nullable, per il diff-sync in edit), `nome_prodotto`, `quantita_kg` (required, >0), `quantita_pz`, `um`, `lotto`, `lotto_esterno`, `scadenza`, `data_in`, `data_out`, `nota_credito_ref`.

---

### Screen 1 — Vendite (Sales)

| Method | Path | Auth | Body / Query | Description |
|---|---|---|---|---|
| `GET` | `/vendite` | `auth` | `search`, `cliente_id`, `da`, `a`, `tipo_documento` | Paginated list with filters |
| `GET` | `/vendite/create` | `auth` | — | Create form |
| `POST` | `/vendite` | `auth` | See below | Create sale document + lines |
| `GET` | `/vendite/{id}/edit` | `auth` | — | Edit form |
| `PUT` | `/vendite/{id}` | `auth` | See below | Replace document + lines |
| `DELETE` | `/vendite/{id}` | `admin` | — | Delete document (cascades lines) |
| `GET` | `/vendite/export` | `auth` | — | Download all vendite_righe as UTF-8 BOM CSV |

**Vendita body:** `cliente_id`, `numero_documento`, `data_documento`, `tipo_documento` (DDT\|FI\|NC), `note`, `righe[]`: each riga has `nome_prodotto`, `quantita_kg`, `quantita_pz`, `pezzatura_gr`, `um`, `lotto`, `lotto_esterno`, `scadenza`.

---

### Screen 1 — Bolle Reso (Return Notes)

| Method | Path | Auth | Body | Description |
|---|---|---|---|---|
| `GET` | `/bolle-reso` | `auth` | — | Paginated return notes list |
| `GET` | `/bolle-reso/create` | `auth` | — | Create form |
| `POST` | `/bolle-reso` | `auth` | `vendita_riga_id`, `numero_bolla`, `quantita_kg`, `quantita_pz`, `data_reso`, `note` | Create return note |
| `GET` | `/bolle-reso/{id}/edit` | `auth` | — | Edit form |
| `PUT` | `/bolle-reso/{id}` | `auth` | Same as store | Update return note |
| `DELETE` | `/bolle-reso/{id}` | `admin` | — | Delete return note |

---

### Screen 1 — Note Credito (Credit Notes)

| Method | Path | Auth | Body | Description |
|---|---|---|---|---|
| `GET` | `/note-credito` | `auth` | — | Paginated credit notes list |
| `GET` | `/note-credito/create` | `auth` | — | Create form |
| `POST` | `/note-credito` | `auth` | `numero_documento`, `data_documento`, `importo`, `vendita_id\|bolla_reso_id`, `note` | Create credit note |
| `GET` | `/note-credito/{id}/edit` | `auth` | — | Edit form |
| `PUT` | `/note-credito/{id}` | `auth` | Same as store | Update credit note |
| `DELETE` | `/note-credito/{id}` | `admin` | — | Delete credit note |

---

### Screen 2 — Imballaggi (Packaging Lots)

| Method | Path | Auth | Body / Query | Description |
|---|---|---|---|---|
| `GET` | `/imballaggi` | `auth` | `search_p`, `search_d`, `tab`, `page_p`, `page_d` | Tabbed index: primary lots + detergent lots |
| `GET` | `/imballaggi/primari/create` | `auth` | — | Primary packaging form |
| `POST` | `/imballaggi/primari` | `auth` | See below | Create primary packaging lot |
| `GET` | `/imballaggi/primari/{id}/edit` | `auth` | — | Edit form |
| `PUT` | `/imballaggi/primari/{id}` | `auth` | See below | Update lot |
| `DELETE` | `/imballaggi/primari/{id}` | `admin` | — | Delete lot |
| `GET` | `/imballaggi/detergenti/create` | `auth` | — | Detergent lot form |
| `POST` | `/imballaggi/detergenti` | `auth` | See below | Create detergent lot |
| `GET` | `/imballaggi/detergenti/{id}/edit` | `auth` | — | Edit form |
| `PUT` | `/imballaggi/detergenti/{id}` | `auth` | See below | Update lot |
| `DELETE` | `/imballaggi/detergenti/{id}` | `admin` | — | Delete lot |

**Primario body:** `fornitore_id`, `componente`, `codice_articolo`, `um`, `quantita`, `lotto`, `numero_ddt`, `data_in`, `data_out`, `note`.
**Detergente body:** same as primario plus `scadenza`.

---

### Screen 3 — Schede Produzione (HACCP Production Sheets)

| Method | Path | Auth | Body | Description |
|---|---|---|---|---|
| `GET` | `/schede` | `auth` | `search`, `solo_attive` | Paginated list of production sheets |
| `GET` | `/schede/create` | `admin` | — | Create form (prodotti, materie, flussi) |
| `POST` | `/schede` | `admin` | See below | Create scheda with ricette + flussi |
| `GET` | `/schede/{id}/edit` | `admin` | — | Edit form |
| `PUT` | `/schede/{id}` | `admin` | See below | Replace scheda, ricette, flussi |
| `DELETE` | `/schede/{id}` | `admin` | — | Delete scheda (cascades ricette + flussi) |
| `GET` | `/schede/{id}/print` | `auth` | — | Printable HACCP sheet |

**Scheda body:** `prodotto_id`, `modello`, `revisione`, `data_revisione`, `ha_marinatura` (bool), `attiva` (bool), `note`, `ricette[]` (each: `materia_prima_id`, `percentuale`, `grammi_per_kg`, `um`), `ricette_marinature[]` (each: `materia_prima_id`, `litri_grammi`, `um`), `scheda_flussi[]` (each: `flusso_id`, `valore_controllo`, `tempo_minuti`).

---

### Screen 3 — Produzioni (Production Runs)

| Method | Path | Auth | Body | Description |
|---|---|---|---|---|
| `GET` | `/produzioni` | `auth` | `search`, `da`, `a` | Paginated production list |
| `GET` | `/produzioni/create` | `auth` | — | Create form (schede attive + acquisti_righe) |
| `POST` | `/produzioni` | `auth` | See below | Register production run + ingredient lot linkages |
| `GET` | `/produzioni/{id}/edit` | `auth` | — | Edit form |
| `PUT` | `/produzioni/{id}` | `auth` | See below | Update run; replaces all `produzioni_materie_prime` |
| `DELETE` | `/produzioni/{id}` | `admin` | — | Delete run (cascades ingredient linkages) |
| `GET` | `/produzioni/{id}/print` | `auth` | — | Printable production record (Inertia Vue page) |
| `GET` | `/produzioni/{id}/pdf` | `auth` | — | Download HACCP production report as PDF (dompdf) |
| `GET` | `/produzioni/export` | `auth` | — | Download all production runs as UTF-8 BOM CSV |
| `POST` | `/produzioni/{id}/semilavorato` | `auth` | See below | Register a semi-finished lot output from this production run |

**Produzione body:** `scheda_id`, `lotto_produzione` (unique), `data_produzione`, `quantita_prodotta_kg`, `operatore`, `note`, `materie_prime[]` (each: `materia_prima_id`, `acquisto_riga_id` or `semilavorato_id`, `quantita_kg`), `imballaggi[]` (each: `lotto_imballaggio_id`, `quantita_usata`, `note`), `detergenti[]` (each: `lotto_detergente_id`, `quantita_usata`, `note`).

**Semilavorato body:** `lotto` (required, unique in `lotti_semilavorati`), `nome_prodotto` (required), `quantita_kg` (required, >0), `note`. A production can only have one semilavorato; a 422 is returned if one already exists for this production.

---

### Screen 3 — Flussi di Lavorazione (Workflow Steps)

| Method | Path | Auth | Body | Description |
|---|---|---|---|---|
| `GET` | `/flussi` | `admin` | — | Full ordered list of workflow steps |
| `POST` | `/flussi` | `admin` | `numero`, `nome`, `controllo`, `misura` | Add workflow step |
| `PUT` | `/flussi/{id}` | `admin` | Same as store | Update workflow step |
| `DELETE` | `/flussi/{id}` | `admin` | — | Delete workflow step |

---

### Import (CSV Bulk Load)

| Method | Path | Auth | Body | Description |
|---|---|---|---|---|
| `GET` | `/import` | `admin` | — | Import dashboard page |
| `POST` | `/import/acquisti` | `admin` | `file` (CSV, max 10 MB) | Bulk import purchase documents from semicolon-delimited CSV |
| `POST` | `/import/vendite` | `admin` | `file` (CSV, max 10 MB) | Bulk import sale documents from semicolon-delimited CSV |
| `GET` | `/import/template-acquisti` | `admin` | — | Download CSV template for acquisti import |
| `GET` | `/import/template-vendite` | `admin` | — | Download CSV template for vendite import |

---

### User Management

| Method | Path | Auth | Body | Description |
|---|---|---|---|---|
| `GET` | `/utenti` | `admin` | — | Full user list |
| `POST` | `/utenti` | `admin` | `name`, `email`, `password`, `password_confirmation`, `role` | Create user |
| `PUT` | `/utenti/{id}` | `admin` | `name`, `email`, `role` | Update user name/email/role |
| `DELETE` | `/utenti/{id}` | `admin` | — | Delete user (cannot self-delete) |
| `POST` | `/utenti/{id}/reset-password` | `admin` | `password`, `password_confirmation` | Force-reset another user's password |

---

### Profile

| Method | Path | Auth | Body | Description |
|---|---|---|---|---|
| `GET` | `/profilo` | `auth` | — | Current user profile page |
| `PUT` | `/profilo/password` | `auth` | `current_password`, `password`, `password_confirmation` | Self-service password change |

---

## 3. Rate Limits

Application-level throttle middleware is applied on sensitive write endpoints. All limits are per IP per minute.

| Route | App Throttle | Notes |
|---|---|---|
| `POST /login` | `throttle:10,1` | 10 attempts/minute; 429 after limit |
| `POST /forgot-password` | `throttle:5,1` | 5 attempts/minute; prevents email flooding |
| `POST /import/*` | None (10 MB file cap only) | Admin-only; brute-force not applicable |
| All other routes | None | |

Infrastructure-level rate limiting (Traefik/Coolify/Hetzner firewall) can add an additional layer but is not required for current threat model.
