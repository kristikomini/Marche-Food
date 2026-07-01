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
