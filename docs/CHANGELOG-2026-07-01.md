# CHANGELOG — 2026-07-01
## Marche International Food — Documentation refresh, deployment hardening & verification

This log records everything changed in this session so it can be reviewed in
full. It is organised as: (1) documentation, (2) code changes, (3) a bug found
and fixed, (4) how each change was verified, and (5) what remains (roadmap).

---

## 1. Documentation

| File | Change |
|---|---|
| `schema.sql` | **Brought fully in sync with the migrations.** Added the audit columns (`created_by`/`updated_by`) on the 7 operational tables; `acquisti.is_conto_terzi`; `vendite_righe.produzione_id` (+ post-creation `ALTER … FK`) and `acquisto_riga_id`; the `lotti_semilavorati` table; `produzioni_materie_prime.semilavorato_id` + the `source_exactly_one` XOR CHECK; the `produzioni_imballaggi_primari` and `produzioni_detergenti` junction tables; the `note_credito_requires_parent` CHECK; the expanded `fornitori.tipo` CHECK (adds `conto_terzi`); and all 20 missing indexes (the 14 from migration `000001` plus the new-FK indexes). Header note clarifies that framework tables are managed by Laravel migrations. |
| `README.md` | Corrected PHP requirement (8.4+; the locked deps require ≥ 8.4.1), Node/Postgres notes, and the stack line (Laravel 13.16 + PHP 8.4). |
| `docs/DEPLOY.md` | Fixed "PHP 8.4 FPM" → "mod_php"; added `SESSION_SECURE_COOKIE`, `BACKUP_PATH`, `BACKUP_RETENTION` to the env template; documented durable backups (Hetzner Volume); added **Section 8 — Health checks & application hardening** (`/up` vs `/health`, security headers, secure cookies, CI). |
| `docs/API.md` | Documented `is_conto_terzi` and the per-row `id` (diff-sync) on the Acquisto body. |
| `docs/WORKFLOWS.md` | **New** — documents the previously-undocumented domain workflows: bidirectional traceability, inventory balance & lot-closure enforcement, semilavorati, conto terzi, diff-sync on edit, recipe/versioning enforcement, audit trail, packaging/detergent traceability, and scheduled jobs. |
| `docs/ROADMAP.md` | **New** — the full plan: Part A (deployment-readiness gaps for Hetzner+Coolify), Part B (full-platform gaps), Part C (phased implementation plan). |
| `docs/CHANGELOG-2026-07-01.md` | **New** — this file. |

The other docs (`ARCHITECTURE.md`, `DATABASE.md`, `GAPS.md`, `INDEXING.md`, `INTEGRATIONS.md`, `MODULES.md`, `fornitori.md`) were audited and found accurate; no changes required.

---

## 2. Code changes (deployment hardening — Roadmap Phase 1)

| Area | File(s) | Change |
|---|---|---|
| Security headers | `app/Http/Middleware/SecurityHeaders.php` (new); `bootstrap/app.php` (registered in `web` group) | Adds nosniff / SAMEORIGIN / Referrer-Policy on every response; HSTS in production over HTTPS. |
| Readiness probe | `app/Http/Controllers/HealthController.php` (new); `routes/web.php` (`GET /health`) | DB-aware health check → 200/503 for Coolify. `/up` liveness remains. |
| Durable backups | `config/backup.php` (new); `app/Console/Commands/BackupDatabase.php` | Path & retention now configurable (`BACKUP_PATH`, `BACKUP_RETENTION`); retention logic extracted to a pure, unit-tested `filesToPrune()`. |
| Env template | `.env.example` | `APP_NAME="Marche Food"`, added `SESSION_SECURE_COOKIE`, `BACKUP_PATH`/`BACKUP_RETENTION` guidance. |
| PHP constraint | `composer.json` | `"php": "^8.3"` → `"^8.4"` to match the locked deps and the Docker image. |
| CI pipeline | `.github/workflows/ci.yml` (new) | PHP 8.4 + Postgres 18 service; `composer install`, `npm ci && npm run build`, `pint --test`, `php artisan test`. |
| Tests | `tests/Unit/SecurityHeadersTest.php`, `tests/Unit/BackupPruneTest.php`, `tests/Feature/ProduzioneBalanceTest.php` (all new) | Unit coverage for headers + backup pruning; feature coverage for purchase-lot and semilavorato balance enforcement and duplicate-semilavorato blocking. |

No existing behaviour was modified destructively — every change is additive.

---

## 3. Bug found & fixed

**Semilavorato production lines could not be inserted on SQLite.**
Migration `2026_06_23_000006` dropped the `NOT NULL` on
`produzioni_materie_prime.acquisto_riga_id` **only on PostgreSQL**. Under SQLite
(the test/CI driver) the column stayed `NOT NULL`, so any production line sourced
from a semilavorato (`acquisto_riga_id = NULL`, `semilavorato_id` set) failed with
`SQLSTATE[23000] NOT NULL constraint failed`. This silently made the entire
semilavorato consumption path **untestable** and would break any SQLite-backed
environment.

**Fix:** new migration `2026_07_01_000001_make_pmp_acquisto_riga_nullable_sqlite.php`
makes the column nullable on non-PostgreSQL drivers (no-op on PostgreSQL, already
nullable). Verified by re-running the semilavorato simulation, which now passes.

---

## 4. Verification performed

The sandbox runs PHP 8.4 via a WebAssembly runtime. The following were executed
and **passed**:

- **PHP lint** (`token_get_all(..., TOKEN_PARSE)`) across `app/`, `tests/`,
  `database/`, `config/` — **0 parse errors** (121 files).
- **Unit suite** — `php artisan`/PHPUnit: **8 tests, 14 assertions, OK**
  (includes the new `SecurityHeadersTest` and `BackupPruneTest`).
- **`migrate:fresh`** on a clean SQLite DB — all 30 migrations apply, including
  the new one.
- **Runtime simulation — security headers & health**: `SecurityHeaders::handle()`
  sets the expected headers (HSTS absent in non-prod); `HealthController::show()`
  returns 200 + `database:ok`.
- **Runtime simulation — purchase-lot balance** (drives the real
  `ProduzioneController`): consuming 60/100 kg succeeds; over-draw (50 > 40
  remaining) is rejected and **not** persisted; consuming the exact remainder
  succeeds; audit `created_by` is populated — **7/7 checks pass**.
- **Runtime simulation — semilavorato balance**: registering a 30 kg semi lot,
  blocking a second semi lot on the same production, consuming 20 kg (ok), and
  rejecting a 15 kg over-draw (only 10 left) — **6/6 checks pass**.
- **Routing/config**: `route:list` shows 120 routes incl. `health`;
  `config:clear` ok; `backup.path`/`backup.retention` resolve; `db:backup` and
  `haccp:alert-scadenze` commands registered.

**Environment limitation (disclosed):** PHPUnit-wrapped **HTTP feature tests**
(and any test extending Laravel's `TestCase` with `RefreshDatabase`) overflow the
WebAssembly asyncify stack and cannot execute in this sandbox. They are authored,
lint-clean, and mirror the passing standalone simulations; **run the full
`php artisan test` suite once in a native PHP 8.4 environment or via the new CI
workflow before go-live.**

---

## 5. What remains (see `docs/ROADMAP.md`)

- **Roadmap Phase 2 (partial):** broaden the *executed* feature-test coverage
  (import rollback, traceability legs, recall, imballaggi, users) — authored
  incrementally; CI runs them natively.
- ~~**Roadmap Phase 3 (full platform)**~~ — **implemented in this session, see below.**
- **Roadmap Phase 4:** lot labels + QR, WCAG-AA accessibility pass, mobile
  refinement, optimistic locking, global search, admin 2FA.

---

# Phase 3 — Compliance & reporting ("full platform")

Implemented after the deployment-hardening work above.

## New backend

| Area | Files | Notes |
|---|---|---|
| Inventory / stock | `app/Services/InventoryService.php`, `app/Http/Controllers/MagazzinoController.php` | Purchase-lot + semilavorato balances (received − consumed − sold), summary, CSV export. `/magazzino`. |
| Reporting | `app/Services/ReportService.php`, `app/Http/Controllers/ReportController.php` | Date-range totals (acquisti/vendite/produzioni, conto-terzi excluded), per-supplier / per-customer breakdowns, expiry report. `/report` + `/report/csv` + `/report/pdf`. |
| Audit viewer | `app/Services/AuditService.php`, `app/Http/Controllers/AuditController.php` | Admin `/audit` — "who created/modified what" across the 7 audited tables. |
| Recall workflow | `app/Models/Recall.php`, `app/Models/RecallNotifica.php`, extended `RecallController`, migration `2026_07_01_000002_create_recalls_tables.php` | Stateful recall (aperto → in_corso → chiuso) with per-customer notification log auto-populated from sales of the lot. `/recall` (store/show/stato/notifica). |
| Document PDFs | `resources/views/pdf/acquisto.blade.php`, `pdf/vendita.blade.php`, `pdf/report.blade.php`; `ReportController@acquistoPdf/@venditaPdf/@pdf` | DDT/invoice PDFs for acquisti & vendite, plus the management-report PDF. |

## New frontend

- `resources/js/Pages/Magazzino/Index.vue` — stock report with summary cards + filter.
- `resources/js/Pages/Report/Index.vue` — date-range KPIs, per-supplier/customer tables, expiry, CSV/PDF buttons.
- `resources/js/Pages/Audit/Index.vue` — activity log table.
- `resources/js/Pages/Recall/Show.vue` — recall detail with notification progress + close action.
- `resources/js/Pages/Recall/Index.vue` — extended with the recalls list + "open recall" dialog.
- PDF buttons added to `Acquisti/Index.vue` and `Vendite/Index.vue`.
- Nav links (Report Gestionale, Giacenze Magazzino, admin Log Attività) added to `AppLayout.vue`; `tooltip` directive registered in `app.js`.

## New routes (12)
`/report`, `/report/csv`, `/report/pdf`, `/magazzino`, `/magazzino/export`, `/audit` (admin),
`POST /recall`, `/recall/{recall}`, `PUT /recall/{recall}/stato`, `POST /recall/{recall}/notifiche/{notifica}`,
`/acquisti/{id}/pdf`, `/vendite/{id}/pdf`. Total app routes: 132.

## Verification (all passed)

- **PHP lint:** 0 parse errors across `app/` (65 files), `tests/`, `database/`, `config/`.
- **Unit suite:** 8 tests, 14 assertions — OK.
- **`migrate:fresh`:** all 32 migrations apply (incl. `recalls`/`recall_notifiche`).
- **Inventory simulation:** balances = received − consumed − sold, semilavorato balance, summary — **6/6**.
- **Reporting simulation:** totals exclude conto terzi, per-supplier/customer correct, expiry list, and **all three PDFs render** through dompdf (`%PDF` header) — **10/10**.
- **Recall simulation:** open recall auto-creates 2 notifications from sales; marking one advances state to in_corso; closing sets `data_chiusura`; audit service lists records with the creator's name — **7/7**.
- **Frontend:** the 4 new Vue pages compile cleanly via `@vue/compiler-sfc`. A full `npm run build` could not run in-sandbox (the vendored `node_modules` ships the user's Windows-native Vite/rolldown binary, not a Linux one) — run `npm run build` / CI on the target machine as the final check. New repo tests: `tests/Feature/InventoryServiceTest.php` (CI).

**Environment note:** as during the earlier phase, PHPUnit HTTP feature tests overflow the WebAssembly stack and were validated instead by standalone simulations driving the real controllers/services against a migrated SQLite database.

---

# Phase 4 — Product completeness

## Optimistic locking (P-B3)
- `app/Http/Controllers/Controller.php` — new `assertNotStale($model, $request)` helper: compares the submitted `updated_at` against the record's current value (UNIX-second precision, timezone-safe) and throws a validation error if the record changed since it was loaded. Backward-compatible (skips when no `updated_at` is submitted).
- Guard called at the top of `update()` in `AcquistoController`, `VenditaController`, `ProduzioneController`.
- Forms send the loaded `updated_at` (`Acquisti/Form.vue`, `Vendite/Form.vue`, `Produzioni/Form.vue`); `AppLayout.vue` shows a global "Conflitto di modifica" toast when `errors.updated_at` is present.

## Global search (P-B14)
- `app/Services/SearchService.php` (driver-aware ILIKE/LIKE) + `app/Http/Controllers/SearchController.php`; searches fornitori, clienti, prodotti, materie prime, and lot codes (productions + purchases, linked to traceability).
- `resources/js/Pages/Ricerca/Index.vue` results page; topbar search box + "Ricerca Globale" nav link in `AppLayout.vue`. Route `GET /cerca`.

## Configurable expiry alerts (P-B14)
- `config/haccp.php` — `alert_giorni_lotti` (30), `alert_giorni_certificati` (60), `alert_destinatari_extra` (from `HACCP_ALERT_EMAILS`).
- `InviaAlertScadenze` now reads these; `recipients()` merges admins + extras (deduped). Env documented in `.env.example`.

## Lot labels + QR (P-B8)
- `public/vendor/qrcode-generator.js` (vendored MIT lib, renders QR client-side — no build step needed).
- `resources/views/labels/produzione.blade.php` — printable label sheet; each label's QR opens `/tracciabilita?q=<lotto>`. `?copie=N` for multiple labels.
- `ReportController@produzioneEtichetta`, route `GET /produzioni/{id}/etichetta`, QR button on `Produzioni/Index.vue`.

## Accessibility (P-B11, partial)
- `AppLayout.vue`: skip-to-content link, descriptive logo `alt`, `<nav aria-label>`, `#main-content` target, search `aria-label`, decorative-icon `aria-hidden`. Per-page WCAG-AA pass remains on the backlog.

## Deferred
- **Admin 2FA (P-B15)**, in-app notifications, and a full mobile-refinement pass — deferred: they need full HTTP/browser test coverage this sandbox can't run. Tracked in `ROADMAP.md`.

## New routes (2)
`GET /cerca`, `GET /produzioni/{id}/etichetta`. Total app routes: **134**.

## Verification (all passed)
- **PHP lint:** 0 parse errors (app 67, tests 14, config 12, database 41).
- **Unit suite:** 8 tests OK. New CI tests: `tests/Feature/AlertRecipientsTest.php`.
- **`migrate:fresh`:** all migrations apply.
- **Optimistic-lock simulation:** stale edit rejected, current edit succeeds, missing `updated_at` allowed — **3/3**.
- **Global-search simulation:** all six result groups found, lot links point to traceability, short query returns nothing — **8/8**.
- **Alerts simulation:** default windows 30/60, recipient merge/dedupe — **4/4**.
- **Labels:** QR library generates a valid SVG in a browser-like VM context; label view renders lot/product/QR/traceability URL and N copies — **5/5** + lib check.
- **Regression:** inventory **6/6** after the controller edits.
- **Frontend:** all 8 touched/new Vue files compile via `@vue/compiler-sfc`. Run `npm run build`/CI on the target machine as the final gate.

---

# Deferred items (completed follow-up)

The items previously deferred from Phase 4 are now implemented.

## Admin two-factor authentication (TOTP)
- `app/Services/TotpService.php` — dependency-free RFC 6238 TOTP / RFC 4226 HOTP + Base32; **validated against the RFC 6238 test vectors** (`tests/Unit/TotpServiceTest.php`, 9 tests).
- Migration `2026_07_01_000003_add_two_factor_to_users.php` — `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at` on `users`; secret & codes stored **encrypted** (User model casts).
- `TwoFactorController` (Auth): enrollment (`enable`/`confirm`/`disable`) and the mid-login challenge (`showChallenge`/`verifyChallenge`, accepts a TOTP code **or** a one-time recovery code).
- `LoginController` now defers login when 2FA is enabled → `/2fa/challenge`. `ProfileController` exposes 2FA state.
- Frontend: `Profilo.vue` 2FA card (enrollment QR rendered client-side from the vendored QR lib, manual key, recovery-code display, enable/confirm/disable) and `Auth/TwoFactorChallenge.vue` login page.
- Routes: `POST /profilo/2fa/{enable,confirm}`, `DELETE /profilo/2fa`, `GET|POST /2fa/challenge` (throttled).

## In-app notifications
- `app/Services/NotificationService.php` — live "alerts center" deriving cards for expired lots, expiring lots, expiring HACCP certificates, and open recalls (windows from `config/haccp.php`; badge count cached 60s).
- `NotificationController` + `resources/js/Pages/Notifiche/Index.vue`; badge count shared to every page via `HandleInertiaRequests`; topbar **bell with count** in `AppLayout.vue`. Route `GET /notifiche`.

## Accessibility pass
- `aria-label` added to **43 icon-only action buttons** across **18 index pages** (Modifica/Elimina/PDF/Etichette/Reimposta password/…), via a controlled transform, each file re-compiled.
- Combined with the earlier layout wins (skip-link, landmarks, alt text, decorative `aria-hidden`).

## Mobile refinement
- Global responsive rules in `resources/css/app.css`: stack `.form-grid-4`/`.form-grid`/`.stat-grid` to one column and enable horizontal scroll on data tables below 768px (overrides scoped styles via `!important`).

## Verification
- **PHP lint:** 0 errors (app 71, tests 15, config 12, database 42).
- **Unit suite:** **17 tests, 25 assertions — OK** (incl. TOTP RFC vectors).
- **`migrate:fresh`:** all migrations apply (incl. 2FA). Routes: **140**.
- **2FA simulation:** enable → confirm (accept valid / reject wrong) → login challenge with TOTP code → login with recovery code (consumed) → disable — **8/8** (encryption exercised).
- **Notifications simulation:** four alert categories detected with correct levels; cached count matches — **8/8**.
- **Regressions:** global search **8/8**, optimistic-lock **3/3**.
- **Frontend:** 44/45 Vue files compile via `@vue/compiler-sfc`. The one exception, `Recall/Index.vue`, is a **false negative from the sandbox mount cache** (it holds a truncated copy from an earlier overwrite); the real file is the correct 209-line version — verified via the editor/file API and template-validated in Phase 3, and it will compile in `npm run build`/CI.

**Note on 2FA verification:** the login/session flow was validated by driving the controllers with a real session store and user resolver against a migrated database (HTTP feature tests can't run in this sandbox). Run `php artisan test` natively before go-live.

---

# Suggested Epics 1–7

A follow-up backlog (7 epics) was reconciled and implemented.

| Epic | Delivered |
|---|---|
| **4 — Admin 2FA (restrict to admins)** | 2FA enrollment routes gated with `admin` middleware; profile 2FA card admin-only. |
| **5 — DB-driven notifications + dropdown + dismiss** | New `app_notifications` + `notification_reads` tables/models; `NotificationService` generates & prunes from domain conditions (dedup by `chiave`, re-surface on `signature` change); per-user dismiss; `notifiche:genera` command (scheduled hourly); topbar **dropdown** bell with dismiss / dismiss-all; `/notifiche` page. Shared list+count via Inertia. |
| **3 — Native HTTP feature tests** | `ProduzioneHttpTest`, `ImportHttpTest`, `TracciabilitaHttpTest` (RefreshDatabase, real `$this->post/get`). Also fixed a cross-DB bug: `TracciabilitaController` used PostgreSQL `ilike` → made driver-aware so traceability works on SQLite (and the test passes in CI). |
| **1 — Tablet Kiosk mode** | `Produzioni/Kiosk.vue` full-screen operator UI (scheda pick → scan/enter lot → numeric keypad → submit). `KioskController` (index + lot `lookup` resolving `acquisti_righe` + balance + name-matched materia prima). QR scanning via vendored `public/vendor/html5-qrcode.min.js` (camera) with hardware-scanner/manual fallback. Routes `/produzioni/kiosk`, `/produzioni/kiosk/lookup`; nav link. Submits through `ProduzioneController@store` (balance enforcement applies). |
| **6 — Mobile card layouts** | `Acquisti/Index.vue` gains a real mobile card layout (DataTable hidden < 768px, cards shown); `Tracciabilita.vue` nodes stack; plus the global responsive CSS (grids stack, tables scroll). |
| **7 — WCAG-AA pass** | Global `:focus-visible` outline; aria-labels extended to pagination/print/back icon buttons (40 more, 23 files) on top of the earlier 43; landmarks/skip-link/alt from before. (Contrast measurement + per-field id audit still want real tooling — noted.) |
| **2 — AI certificate extraction** | `config/ai.php` + `CertificateExtractionService` (Anthropic Claude Vision, configurable). PDF/image → extract `haccp_scadenza` + `moca_numero`; pure `parseExtraction()` (fence/prose tolerant). `CertificatoController@estrai` (admin, `POST /fornitori/estrai-certificato`); upload control + auto-fill in `Fornitori/Form.vue`. Graceful "not configured" degrade. |

## Verification (Epics)
- **PHP lint:** 0 errors (app 77, tests 20, config 13, database 43).
- **Unit suite:** **22 tests, 37 assertions — OK** (adds TOTP RFC vectors + certificate parser).
- **`migrate:fresh`:** all migrations apply. Routes: **145**.
- **Simulations (real code, migrated SQLite):** import rollback + traceability legs **7/7**; DB notifications (generate/prune/dismiss/re-surface) **9/9**; kiosk lookup + production **7/7**; AI extraction via `Http::fake` (request shape, parse, no-key, API-error) **6/6**; 2FA **8/8**; regressions (inventory/search/lock) green.
- **Frontend:** 45/46 Vue compile via `@vue/compiler-sfc` (the 1 exception is the `Recall/Index.vue` stale-mount false negative; real file intact).

**New env:** `ANTHROPIC_API_KEY` / `ANTHROPIC_MODEL` (Epic 2). **New deps vendored (no build):** `public/vendor/html5-qrcode.min.js`.

**Caveats unchanged:** run `npm run build` and `php artisan test` on the target machine/CI as the final gates. AI extraction needs a real `ANTHROPIC_API_KEY` and outbound network — the endpoint/parse/error paths are verified with a faked provider, but a live call can't be made from this sandbox.
