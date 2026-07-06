# BLUEPRINT.md
## Marche International Food S.R.L. — Sistema di Tracciabilità HACCP

A single-source overview of **what this application is**, the technologies it is
built on, the architectural patterns it follows, its data model, and every
feature worth knowing about. Read this first; the other docs go deeper on
specific areas (see the map at the end).

---

## 1. What it is

A web-based **food-traceability and HACCP compliance system** for *Marche
International Food S.R.L.*, an Italian food business. It replaces spreadsheets
with a structured, auditable record of everything that moves through the plant:

- **who** supplied **what** raw material, in which **lot**, with which expiry;
- **how** those lots were combined in **production runs** (recipes, HACCP flows);
- **where** the finished lots were **sold** (which customer, which document);
- and therefore, for any lot, a complete **bidirectional trace** — forward
  (supplier lot → production → sale → customer) and backward (sold product →
  production → raw-material lots → suppliers).

That trace is the legal heart of the system: EU Reg. 178/2002 requires
one-step-back / one-step-forward traceability, and Reg. 852/2004 requires HACCP
records. On top of that, the app now derives an **allergen declaration**
(Reg. 1169/2011) for each production lot, can **recall** a lot and log customer
notifications, and keeps deletes **recoverable** for audit safety.

**Users** fall into two roles: `operator` (create/edit operational documents,
read master data) and `admin` (everything, plus master-data CRUD, deletes, user
management, import, audit, and the trash bin). Language is Italian throughout.

---

## 2. Technology stack

| Layer | Technology |
|---|---|
| **Language / runtime** | PHP 8.4 (dev machine runs 8.5; Docker image `php:8.4-apache`) |
| **Backend framework** | Laravel 13 |
| **Frontend** | Vue 3 + **Inertia.js v3** — a server-driven SPA with **no separate REST/JSON API** |
| **UI toolkit** | PrimeVue (Aura theme) — DataTable, Button, MultiSelect, useConfirm, toasts, etc. |
| **Build** | Vite (Rolldown) |
| **Database** | PostgreSQL (16+ dev, 18 prod); **SQLite in-memory** for the test suite |
| **PDF** | `barryvdh/laravel-dompdf` (production report, DDT/invoice, HACCP sheet) |
| **QR / barcode** | Vendored browser scripts (no build step): `public/vendor/qrcode-generator.js` (labels, 2FA), `public/vendor/html5-qrcode.min.js` (kiosk camera) |
| **Email** | Symfony Mailgun mailer (expiry alerts) |
| **AI** | Anthropic Claude Vision — supplier-certificate field extraction |
| **Container / deploy** | Docker (Apache + mod_php) on Hetzner + Coolify (Traefik, PostgreSQL 18) |

---

## 3. Architecture

### The Inertia monolith

There is **no API layer**. Controllers return `Inertia::render('PageName', [...props])`;
Inertia ships those props to a Vue page component and swaps the page client-side.
Writes are plain Laravel form POST/PUT/DELETE (via Inertia's `router` /
`useForm`), validated server-side, redirecting back with a flash message. This
keeps one language of truth (Laravel routing + validation + Eloquent) and avoids
API/DTO duplication.

**Request flow (production lot registration — the core HACCP write):**

1. `GET /produzioni/create` → `ProduzioneController@create` loads active schede,
   materials, and **available lots with live balances** → renders `Produzioni/Form.vue`.
2. Operator picks a scheda, adds ingredient lines (each sourced from a purchase
   lot **or** a semi-finished lot), quantities, packaging, detergents.
3. `POST /produzioni` → validate → **inside a DB transaction**: pessimistically
   lock the consumed lots, re-check balances, insert the `produzione` and its
   `produzioni_materie_prime` / imballaggi / detergenti rows.
4. Redirect to the list with a success toast.

### Layers & where logic lives

- **Controllers** (`app/Http/Controllers`) — thin: validate, orchestrate, render.
- **Services** (`app/Services`) — the reusable domain logic, unit/feature-tested:
  `InventoryService` (lot balances/stock), `ReportService` (management aggregates),
  `SearchService` (global search), `AuditService` (who-changed-what feed),
  `NotificationService` (in-app alerts), `AllergenService` (FIC allergen matrix),
  `TotpService` (2FA), `CertificateExtractionService` (AI).
- **Models** (`app/Models`) — Eloquent, with a couple of cross-cutting concerns
  (`App\Concerns\Auditable`, Laravel `SoftDeletes`).
- **Middleware** — `admin` (role gate), `SecurityHeaders`, `HandleInertiaRequests`
  (shares `auth.user`, `flash`, notification badge on every page).
- **Vue pages** (`resources/js/Pages`) — one folder per module; `AppLayout.vue`
  is the shell (sidebar nav, topbar search + notification bell, toasts).
- **Blade** is used only for non-Inertia HTML: PDFs (`resources/views/pdf/*`),
  print/label sheets (`resources/views/labels/*`), the SPA root, and emails.

### Directory landmarks

```
app/Http/Controllers   thin controllers (one per module)
app/Services           domain logic (tested in isolation)
app/Models             Eloquent models + relations
app/Concerns/Auditable created_by/updated_by trait
database/migrations    schema history (source of truth)
routes/web.php         every route, grouped guest / auth / admin
resources/js/Pages      Vue pages (Inertia)
resources/js/Layouts    AppLayout shell
resources/views/pdf     dompdf documents
resources/views/labels  QR label sheets
tests/Feature|Unit      PHPUnit suite (SQLite in-memory)
docs/                   this documentation set
```

---

## 4. Data model (essentials)

Three functional "screens" plus master data. Full ERD and per-table detail in
`DATABASE.md`.

**Master data (anagrafica):** `fornitori` (suppliers, typed:
alimentare / imballaggio_primario / detergente_secondario / conto_terzi),
`clienti`, `prodotti`, `materie_prime` (+ allergen columns), `unita_misura`,
`destinazione_ingredienti`.

**Screen 1 — Alimenti (food documents):**
`acquisti` → `acquisti_righe` (purchase lots: lotto, scadenza, qty),
`vendite` → `vendite_righe` (sales, optionally linked to a purchase lot or a
production lot), `bolle_reso` (return slips), `note_credito` (credit notes).

**Screen 2 — Imballaggi (packaging):** `lotti_imballaggi_primari` (MOCA
food-contact packaging) and `lotti_detergenti` (certified detergents).

**Screen 3 — Produzione:** `schede_produzione` (versioned HACCP production
sheets) with `ricette` / `ricette_marinature` (recipes) and
`schede_produzione_flussi` (HACCP flow steps); `produzioni` (runs) with
`produzioni_materie_prime` (the traceability join: each consumed line points to a
purchase lot **or** a `lotti_semilavorati` semi-finished lot, plus its
`materia_prima`), `produzioni_imballaggi_primari`, `produzioni_detergenti`;
`lotti_semilavorati` (internal semi-finished lots that a later run can consume).

**Cross-cutting:** `recalls` + `recall_notifiche`; `app_notifications` +
`notification_reads`; 2FA columns on `users`; audit columns
(`created_by`/`updated_by`) on the 7 operational tables; and, since 2026-07-06,
`deleted_at` (soft-delete) on those same 7 tables and `allergeni` /
`allergeni_tracce` on `materie_prime`.

**The traceability spine** is `produzioni_materie_prime`: it ties every
production run to the exact lots it consumed, which is what makes both trace
directions and the recall "who got this lot" query possible.

---

## 5. Patterns & conventions

- **Inertia, no API.** Server renders pages with props; writes are form
  submissions with server-side validation and flash redirects.
- **Thin controllers, fat services.** Anything reusable or testable in isolation
  lives in `app/Services`.
- **Derived, not stored.** Lot balances, the stock report, and the allergen
  matrix are **computed at read time** from live data (there is no materialised
  stock column and no stored production-allergen column). This guarantees they
  can never drift from the underlying records.
- **Balance enforcement under lock.** Production writes run in a transaction that
  `lockForUpdate()`s the consumed lots and re-checks balances, so two concurrent
  submissions can't over-draw the same lot.
- **Optimistic locking on edits.** Edit forms submit the `updated_at` they loaded;
  `Controller::assertNotStale()` rejects the save if the record changed meanwhile
  (surfaced as a "Conflitto di modifica" toast).
- **Auditable trait.** `created_by`/`updated_by` are stamped automatically on the
  7 operational models; surfaced read-only in the **Log Attività** page.
- **Soft-delete with guards.** Operational documents soft-delete (recoverable via
  the Cestino) instead of hard-deleting; `destroy()` guards refuse to trash a
  record still referenced by an active downstream document. Because raw
  `DB::table()` queries bypass the soft-delete scope, every raw
  balance/report/search/audit query explicitly filters trashed parents.
- **Driver-aware search.** `ILIKE` on PostgreSQL, case-insensitive `LIKE` on
  SQLite (tests) — chosen at runtime from the connection driver.
- **Vendored browser assets.** QR generation and camera scanning use small
  self-hosted scripts (no CDN, no bundler step), which suits the offline-ish
  factory context and a strict deploy.
- **Role gating in routes.** `routes/web.php` groups routes as guest / auth /
  admin; shared reads are `auth`, mutations and tooling are `admin`.

---

## 6. Feature catalogue

### Master data (anagrafica)
Suppliers, customers, products, raw materials (with **allergen declarations**),
units, ingredient destinations. Admin-only CRUD; everyone can read.

### Screen 1 — Alimenti
Purchases (DDT/invoice with lots, expiry, conto-terzi flag), sales (optionally
linked to the source purchase or production lot), return slips, credit notes.
Line-level **diff-sync** on edit (preserves IDs, refuses to delete lines already
referenced downstream). CSV export + printable/PDF documents.

### Screen 2 — Imballaggi
MOCA food-contact packaging lots and certified detergent lots, with supplier,
DDT, quantities, in/out dates.

### Screen 3 — Produzione
Versioned HACCP production **schede** (with recipes and HACCP flow steps) and
production **runs** that consume purchase or semi-finished lots under balance +
recipe enforcement, produce optional **semilavorati** for later runs, and record
packaging/detergents. HACCP PDF and print views per run.

### Traceability
`/tracciabilita` — forward and backward lot search across purchases, productions
and sales, now annotated with each production lot's **derived allergens**.

### Recall (stateful, HACCP audit)
Open a recall on a lot → auto-populate the affected customers from the sales of
that lot → mark each notification done (auto-advances `aperto → in_corso`) →
close (records `data_chiusura`). Tables `recalls` + `recall_notifiche`.

### Allergen tracking (Reg. UE 1169/2011)
14 EU allergens per raw material — *contiene* and *può contenere (tracce)*.
`AllergenService` derives each production lot's declaration as the **union** of
its ingredients, **recursing through semi-finished ingredients** (an allergen in
"contains" is dropped from "may contain"). Shown as chips in the materie-prime
list + traceability and printed on the production QR label and HACCP PDF.

### Reporting & inventory
`/report` — date-range management report (purchase/sale/production volumes with
conto-terzi excluded, per-supplier / per-customer, expiry list) with PDF + CSV
export. `/magazzino` — live stock: purchase-lot and semilavorato balances
(received − consumed − sold), with CSV export.

### QR lot labels
Printable QR label sheets whose codes open the traceability view, for production
lots (`/produzioni/{id}/etichetta`) and — since 2026-07-06 — purchase and sale
lots (`/acquisti|vendite/{id}/etichette`). The production label also prints the
derived allergen declaration.

### Soft-delete & Cestino (data safety)
Deletes are recoverable: trashed documents move to **/cestino** (admin), where
they can be restored (reappearing everywhere, including inventory/traceability)
or permanently removed. Delete guards preserve the "can't remove referenced
data" invariant that the DB foreign keys enforced on the old hard delete.

### Audit trail
`/audit` (admin) — a "who created/modified what" feed over the 7 operational
tables, built from the Auditable columns (`AuditService`).

### In-app notifications
`NotificationService` derives alerts (expired/expiring lots, HACCP certs, open
recalls) from live conditions, dedups by key, re-surfaces on change, and prunes
when the condition clears. Topbar bell + `/notifiche`, per-user dismissals,
regenerated hourly.

### Kiosk mode
`/produzioni/kiosk` — a full-screen tablet flow for the factory floor (scan/type
a lot, keypad kg, submit) that posts through the same controller, so **all
balance/recipe enforcement still applies**.

### Two-factor authentication (admin)
TOTP (RFC 6238) with recovery codes and a two-step login; secret and codes stored
encrypted; admin-only.

### AI certificate extraction
On the supplier form, upload a HACCP/MOCA certificate → Claude Vision extracts
`haccp_scadenza` + `moca_numero` to auto-fill the form; degrades gracefully when
unconfigured.

### Import & global search
CSV bulk import of historical purchases/sales (admin), and a cross-entity global
search box in the topbar (`/cerca`).

---

## 7. Security model

- **Authentication**: session-based; `guest` routes for login/reset/2FA-challenge,
  everything else behind `auth`. Login and password-reset are rate-limited
  (`throttle:10,1` / `throttle:5,1`).
- **Authorisation**: two roles; the `admin` middleware gates master-data CRUD,
  deletes, users, import, audit, cestino, flows, and 2FA enrollment.
- **Hardening**: `SecurityHeaders` middleware (nosniff / SAMEORIGIN /
  Referrer-Policy, HSTS in prod), forced HTTPS + secure/encrypted sessions in
  production, DB-aware `/health` readiness probe alongside `/up`.
- **Data at rest**: 2FA secret/recovery codes encrypted; sessions encrypted.

---

## 8. Testing

`php artisan test` runs the full **PHPUnit** suite on **SQLite in-memory**
(`RefreshDatabase`) — feature tests exercise real routes/middleware, unit tests
cover extracted logic. ~91 tests. Coverage includes access control, auth, 2FA
TOTP vectors, dashboard, purchases/productions CRUD, balance & semilavorato
enforcement, traceability, import, inventory, certificate parsing, security
headers, backup pruning, and (2026-07-06) soft-delete/Cestino, QR labels,
allergen propagation, recall workflow, user management, and the packaging delete
guard.

> One **pre-existing** failure is the accepted green baseline:
> `AccessControlTest::test_operator_cannot_delete_admin_records` — Laravel's
> route-model-binding returns 404 before the `admin` middleware can redirect an
> unauthorised operator. It is a middleware-ordering quirk, not a regression.

The repo is **not** Laravel Pint-clean (linting is manual — roadmap D-A7); new
code matches the surrounding file style rather than triggering a repo-wide
reformat.

---

## 9. Deployment

Multi-stage Docker build (Composer without dev deps, Vite assets compiled into
`public/build`). On boot, `docker/start.sh` runs `migrate --force`, starts the
Laravel scheduler and a queue worker, then Apache. Target infra is Hetzner +
Coolify (Traefik + PostgreSQL 18). Required prod env: `APP_ENV=production`,
`APP_KEY`, `APP_DEBUG=false`, `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`,
DB credentials, mail + `ANTHROPIC_API_KEY` if those features are used. Full
detail in `DEPLOY.md`.

> After pulling the 2026-07-06 changes, run `php artisan migrate` (dev) — two new
> migrations add `deleted_at` and the allergen columns. Production migrates on boot.

---

## 10. Documentation map

| Doc | What it covers |
|---|---|
| **BLUEPRINT.md** (this file) | Whole-system overview |
| `ARCHITECTURE.md` | Stack, layers, request flow, services/components |
| `DATABASE.md` | Full ERD + per-table detail |
| `MODULES.md` | Business-logic breakdown, module map |
| `API.md` | Every route, auth, request/response format |
| `WORKFLOWS.md` | Domain rules: traceability, balances, semilavorati, conto terzi, recall, soft-delete guards, allergen derivation |
| `INTEGRATIONS.md` | AI certificate extraction, CSV import, env vars |
| `INDEXING.md` | Index strategy |
| `DEPLOY.md` | Hetzner + Coolify deploy, health checks, backups, hardening |
| `ROADMAP.md` | Gap analysis + phased plan (with resolved items) |
| `GAPS.md` | The original 21 technical/security/domain gaps (all resolved) |
| `fornitori.md` | Deep dive on the Fornitori module |
| `CHANGELOG-2026-07-01.md` / `CHANGELOG-2026-07-06.md` | Dated change logs |
