# ROADMAP.md
## Marche International Food — Deployment Readiness & Full-Platform Plan

**Status date:** 2026-07-01
**Deployment target:** Hetzner Cloud VPS + Coolify (Docker + Traefik + PostgreSQL 18), per `DEPLOY.md`.

This document has three parts:

- **Part A — Gap analysis: deployment readiness** (what stands between the app and a safe production launch on Hetzner + Coolify)
- **Part B — Gap analysis: full-fledged platform** (what the product needs to feel complete for the client)
- **Part C — Phased implementation plan** (ordered, testable work packages)

Severity: **Critical** (blocks launch) · **High** · **Medium** · **Low**.
Each item notes how it can be **verified** in this environment (unit tests, migrations, artisan, lint) vs. what needs a real HTTP/browser environment.

---

## Part A — Deployment readiness (Hetzner + Coolify)

The codebase is already deployment-aware (multi-stage Dockerfile, `start.sh` runs migrate + scheduler + queue worker, `/up` health route, `trustProxies('*')`, HTTPS forced in prod, sessions encrypted). The following gaps remain.

| ID | Sev | Area | Gap | Fix summary | Verify |
|----|-----|------|-----|-------------|--------|
| **D-A1** | High | Backups | `db:backup` writes to `storage/backups/` **inside the container** — wiped on every rebuild. No off-site copy. | Make backup path configurable; document a Hetzner Volume mount at `storage/backups`; add optional S3/rclone off-site hook. | Unit-test path + pruning logic |
| **D-A2** | High | Security | No HTTP security headers (HSTS, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`). Only `forceScheme(https)`. | Add a `SecurityHeaders` middleware appended to the `web` group. | Lint + unit assertion on header array |
| **D-A3** | High | Security | `SESSION_SECURE_COOKIE` is read by `config/session.php` but never set → cookies may be sent over HTTP. | Set `SESSION_SECURE_COOKIE=true` in production env + `.env.example` guidance. | Config assertion |
| **D-A4** | Medium | Resilience | Scheduler & queue worker are bare `&` background processes in `start.sh`; if either dies, it is not restarted, and a crash is invisible. | Document running them as **separate Coolify services/processes** (recommended), or add a lightweight supervisor loop. | Review only (infra) |
| **D-A5** | Medium | Health | `/up` is a static health route; Coolify/Traefik can mark the app "healthy" even if the DB is down. | Add a DB-aware health check (`/health`) returning 200 only when the DB responds; wire Coolify healthcheck. | artisan route:list + logic script |
| **D-A6** | Medium | Config | `.env.example` still ships skeleton values (`APP_NAME=Laravel`, unused `REDIS_*`, `AWS_*`, `MEMCACHED_*`) that contradict `DEPLOY.md`. | Align `.env.example` to the documented production set; set `APP_NAME="Marche Food"`; add `SESSION_SECURE_COOKIE`, `APP_URL` guidance. | Diff review |
| **D-A7** | Medium | CI/CD | No automated pipeline: tests, linting and asset build are manual. A broken commit can reach `main`. | Add GitHub Actions: `composer install`, `php artisan test`, `pint --test`, `npm ci && npm run build`. | Workflow lint |
| **D-A8** | Low | Tooling | `composer.json` declares `php: ^8.3` but the locked deps require ≥ 8.4.1 (platform_check) and Docker uses 8.4. Confusing for new devs. | Bump the declared constraint to `^8.4` (or document clearly). | composer validate |
| **D-A9** | Low | Deploy safety | `migrate --force` runs on **every** container boot; with >1 replica this races. Single-instance today, but undocumented. | Document single-instance assumption / release-command pattern. | Review only |
| **D-A10** | Low | Observability | No error tracking (Sentry) or uptime monitoring. | Optional: add Sentry DSN hook; Coolify uptime alert. | N/A |

---

## Part B — Full-fledged platform gaps

The domain core is strong. To satisfy "a full platform," the following product-level gaps matter most. Grouped by theme.

### B.1 Correctness & data integrity
| ID | Sev | Gap |
|----|-----|-----|
| ~~P-B1~~ | — | ~~Semi-finished (semilavorato) balance is not enforced.~~ **Resolved on review:** `ProduzioneController::lockAndCheckBalance()` already locks and re-checks `lotti_semilavorati` balances with the same pessimistic-lock pattern as purchase lots. Confirmed by the balance simulation (see change-log). No further work needed. |
| ~~P-B2~~ | — | ~~**Hard deletes** everywhere — an accidental admin delete is unrecoverable.~~ **Resolved 2026-07-06:** the 7 operational document models use `SoftDeletes`; admin **Cestino** (`/cestino`) restores or permanently deletes; `destroy()` guards preserve the "can't remove referenced data" invariant; all raw balance/report/search/audit queries exclude trashed parents. See `CHANGELOG-2026-07-06.md`. |
| **P-B3** | Medium | **No optimistic locking** — two users editing the same document → silent last-write-wins. |
| **P-B4** | Low | Some CHECK constraints are PostgreSQL-only; SQLite (tests) does not enforce them. Application-level parity exists for the XOR rule but not all. |

### B.2 Compliance & reporting (the client's likely "full platform" expectations)
| ID | Sev | Gap |
|----|-----|-----|
| **P-B5** | High | **Reporting/analytics is thin.** Only live dashboard KPIs. No date-range management reports (purchases/sales/production volumes), per-supplier / per-customer reports, or expiry/stock reports, and no export to PDF/Excel. |
| **P-B6** | High | **Recall is read-only.** No workflow to *issue* a recall, record status, and log customer notifications — important for HACCP audits. |
| ~~P-B7~~ | — | ~~**Audit trail is captured but invisible.**~~ **Resolved 2026-07-06:** upgraded from `created_by`/`updated_by` to a true append-only `audit_logs` change history (every create/update/delete/restore with before→after field values), shown at `/audit`. `Auditable` trait + `AuditLog` + `AuditService::changeLog()`. |
| ~~P-B8~~ | — | ~~**No lot labels / QR codes.**~~ **Resolved:** production lots (`/produzioni/{id}/etichetta`) and, since 2026-07-06, **purchase and sale lots** (`/acquisti\|vendite/{id}/etichette`) print QR labels that open the traceability view. |
| **P-B9** | Medium | **No DDT/invoice PDF** for acquisti/vendite (only the production report is a PDF). |
| **P-B10** | Medium | **No stock/inventory view** — lot balances are computed only inside the production form, not shown as an inventory report. |
| ~~P-B16~~ | — | **Added 2026-07-06 — allergen tracking (Reg. UE 1169/2011).** 14 EU allergens per raw material (contiene / tracce), derived onto each production lot recursively through semilavorati, shown in traceability and printed on the QR label + HACCP PDF. Foundation for full FIC consumer labels. `AllergenService`. |

### B.3 UX, accessibility, quality
| ID | Sev | Gap |
|----|-----|-----|
| **P-B11** | Medium | **Accessibility** below WCAG AA: missing ARIA labels, image alt text, color-only expiry indicators, modal focus management. |
| **P-B12** | Medium | **Mobile refinement** — wide inline tables and 4-column form grids overflow on phones. |
| **P-B13** | Medium (improving) | **Thin automated test coverage.** 2026-07-06 added feature tests for soft-delete/Cestino, QR labels, allergens, recall workflow, user management, and the imballaggi delete guard (suite now ~91 tests). Schede CRUD remains the main untested controller. |
| **P-B14** | Low | No global search, no in-app notifications, alert windows/recipients are hard-coded, single language (Italian — acceptable for this client). |

### B.4 Access & account security
| ID | Sev | Gap |
|----|-----|-----|
| **P-B15** | Low | No 2FA for admins, no email verification. Acceptable for a small internal tool but worth offering. |

---

## Part C — Phased implementation plan

Ordering principle: **make it safe to deploy first (Phase 1), then correct (Phase 2), then complete (Phases 3–4).** Each phase ends with the test toolkit available here (PHP 8.4 wasm runtime: unit tests, migrations, artisan, logic scripts, lint) plus feature tests to be run in the client's real environment.

> **Testing note for this environment:** the sandbox runs PHP 8.4 via a WebAssembly runtime that executes migrations, artisan commands, unit tests, and standalone logic scripts, but the PHPUnit-wrapped **HTTP feature tests overflow the wasm asyncify stack** and cannot run here. Feature-level changes are therefore validated by (a) PHP lint, (b) `artisan route:list`/`config` checks, (c) unit tests on extracted logic, and (d) seeded-DB logic scripts. The full feature suite must be run once in a native PHP 8.4 environment (or CI, per D-A7) before go-live.

### Phase 1 — Deployment hardening (Critical/High, pre-launch)
Target gaps: D-A1, D-A2, D-A3, D-A5, D-A6, D-A7, D-A8.

1. **Security headers middleware** (D-A2) — new `app/Http/Middleware/SecurityHeaders.php`, appended to `web`. Sets HSTS (prod only), `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`. Unit test asserts headers.
2. **Secure cookies** (D-A3) — `.env.example` + `DEPLOY.md`: `SESSION_SECURE_COOKIE=true` in prod.
3. **DB-aware health check** (D-A5) — `/health` route + tiny controller that runs `SELECT 1`; returns 503 on failure. Keep `/up` for liveness, `/health` for readiness. Wire Coolify.
4. **Durable backups** (D-A1) — `db:backup` reads `BACKUP_PATH` (default `storage/backups`) and `BACKUP_RETENTION_DAYS`; unit-test the retention/pruning. `DEPLOY.md`: mount a Hetzner Volume at the path; document optional off-site.
5. **`.env.example` alignment** (D-A6) — `APP_NAME="Marche Food"`, add `SESSION_SECURE_COOKIE`, remove/comment unused skeleton vars, match `DEPLOY.md`.
6. **CI pipeline** (D-A7) — `.github/workflows/ci.yml`: PHP 8.4 + Postgres service, `composer install`, `php artisan test`, `./vendor/bin/pint --test`, `npm ci && npm run build`.
7. **PHP constraint** (D-A8) — bump `composer.json` to `"php": "^8.4"`.

### Phase 2 — Correctness & integrity (High/Medium)
Target gaps: P-B1, P-B13 (partial), P-B2 (optional).

1. **Semilavorato balance enforcement** (P-B1) — extend `lockAndCheckBalance()` to lock and check `lotti_semilavorati` remaining quantity (produced − consumed − shipped). Unit-test the balance math.
2. **Test expansion** (P-B13) — add feature tests for import rollback, traceability legs, balance rejection, recall, imballaggi, users/last-admin guard. (Authored here; executed in CI.)
3. *(Optional)* **Soft deletes** (P-B2) for operational records with an admin "cestino"/restore.

### Phase 3 — Compliance & reporting (High/Medium — the "full platform") ✅ IMPLEMENTED (2026-07-01)
Target gaps: P-B5, P-B6, P-B7, P-B9, P-B10.

1. ✅ **Reporting module** — `/report` with date-range management reports (purchases, sales, production volumes; per-supplier/customer; expiry), exportable to **CSV and PDF** (dompdf). `ReportService` + `ReportController`. *(Excel export deferred as a nice-to-have.)*
2. ✅ **Stock/inventory view** (P-B10) — `/magazzino` backed by a reusable `InventoryService` (purchase-lot + semilavorato balances) with CSV export.
3. ✅ **Audit-log viewer** (P-B7) — admin `/audit` reading `created_by`/`updated_by` (+ timestamps) across the 7 operational tables via `AuditService`.
4. ✅ **Recall workflow** (P-B6) — `recall` is now stateful: aperto → in_corso → chiuso, with an auto-populated per-customer notification log (`recalls` + `recall_notifiche` tables).
5. ✅ **DDT/invoice PDF** (P-B9) — dompdf templates + routes for acquisti (`/acquisti/{id}/pdf`) & vendite (`/vendite/{id}/pdf`).

See `CHANGELOG-2026-07-01.md` § "Phase 3" for the full record and verification.

### Phase 4 — Product completeness (Medium/Low) ✅ IMPLEMENTED (2026-07-01)
Target gaps: P-B8, P-B11, P-B12, P-B3, P-B14, P-B15.

1. ✅ **Lot labels + QR** (P-B8) — `/produzioni/{id}/etichetta` prints labels whose QR opens `/tracciabilita?q=<lotto>` (client-side QR via vendored `public/vendor/qrcode-generator.js`, `?copie=N` for sheets).
2. ✅ **Accessibility** (P-B11) — layout wins (skip-link, logo alt, nav landmark, `#main-content`, search `aria-label`, decorative-icon `aria-hidden`) **plus** `aria-label`s added to 43 icon-only action buttons across 18 index pages. A fine-grained per-page audit (contrast tuning, every form field id) can continue but the major screen-reader gaps are closed.
3. ✅ **Optimistic locking** (P-B3) — `updated_at` conflict guard on acquisti/vendite/produzioni edits (`Controller::assertNotStale`); forms send the loaded `updated_at`; a global toast surfaces conflicts.
4. ✅ **Nice-to-haves** (P-B14/P-B15): **global search** (`/cerca` + topbar box), **configurable alerts** (`config/haccp.php`), **in-app notifications** (topbar bell + `/notifiche` alerts center), **mobile refinement** (global responsive CSS stacks form/stat grids and scrolls tables), and **admin 2FA** (P-B15) — full TOTP (RFC 6238) with authenticator enrollment (QR), recovery codes, and a two-step login challenge.

See `CHANGELOG-2026-07-01.md` §§ "Phase 4" and "Deferred items" for details and verification.

---

## What this session implements

Per the agreed workflow (plan → build automatically), this session implements **Phase 1 in full** (security headers, DB-aware health check, durable-backup config, `.env.example` alignment, PHP constraint, CI pipeline) plus **Phase 2 item 2 (test expansion)** — including an end-to-end **balance simulation** that also confirmed Phase 2 item 1 (semilavorato balance) was already implemented. These are the highest-value, lowest-risk, deployment-critical items that can be reliably verified with the available toolkit. Phases 3–4 are specified above as the continuing backlog. Every change is linted, unit-tested and/or migration/simulation-verified; see `CHANGELOG-2026-07-01.md` and the updated docs for the record.
