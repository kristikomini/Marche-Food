# CHANGELOG — 2026-07-06
## Marche International Food — Soft-delete/recovery, QR lot labels, allergen tracking, test expansion

This log records everything changed in this session. It is organised as:
(1) features, (2) code changes, (3) a pre-existing bug fixed in passing,
(4) how each change was verified, (5) documentation, and (6) what remains.

Starting point: `php artisan test` = **68 tests, 67 passing** (1 pre-existing
failure). End state: **91 tests, 90 passing** (same 1 pre-existing failure, no
new regressions at any phase).

---

## 1. Features (in four tested phases)

| Phase | Feature | Summary |
|---|---|---|
| **1** | Soft-delete + restore (Cestino) | Accidental admin deletes are now recoverable instead of lost. |
| **2** | QR lot labels for purchases/sales | Printable QR labels extended from productions to `acquisti`/`vendite` lots. |
| **3** | Allergen tracking (Reg. UE 1169/2011) | 14 EU allergens per raw material, derived recursively onto production lots. |
| **4** | Test expansion | Feature tests for Cestino, labels, allergens, recall, users, imballaggi guard. |

---

## 2. Code changes

### Phase 1 — Soft-delete, restore & delete guards
| Area | File(s) | Change |
|---|---|---|
| Migration | `database/migrations/2026_07_06_000001_add_soft_deletes_to_operational_tables.php` (new) | `deleted_at` on `acquisti`, `vendite`, `produzioni`, `bolle_reso`, `note_credito`, `lotti_imballaggi_primari`, `lotti_detergenti`. |
| Models | `Acquisto`, `Vendita`, `Produzione`, `BollaReso`, `NotaCredito`, `LottoImballaggioPrimario`, `LottoDetergente` | Added `use SoftDeletes;` (alongside the existing `Auditable`). |
| Relation | `app/Models/ProduzioneMateriaPrima.php` | Added the missing `produzione()` belongsTo (needed by the guards). |
| Trash UI | `app/Http/Controllers/CestinoController.php` (new); `resources/js/Pages/Cestino/Index.vue` (new); `routes/web.php`; `AppLayout.vue` nav | `/cestino` (admin): list trashed docs, restore, permanent-delete (force-delete wrapped for FK-safety). |
| Delete guards | `AcquistoController`, `VenditaController`, `ProduzioneController`, `BollaResoController`, `ImballaggioController` `destroy()` | Refuse to trash a document still referenced by an **active** downstream document; messages surfaced via `flash.error` toast. |
| Raw-query soft-delete filters | `InventoryService`, `ReportService`, `SearchService`, `AuditService`, `ProduzioneController` (lot availability + `lockAndCheckBalance`) | Join the parent document and `whereNull('<parent>.deleted_at')` so trashed docs never leak into balances/reports/search/audit. |

### Phase 2 — QR lot labels (purchases / sales)
| Area | File(s) | Change |
|---|---|---|
| View | `resources/views/labels/lotti.blade.php` (new) | Multi-lot QR label sheet (reuses `public/vendor/qrcode-generator.js`); lines without a lot code are skipped. |
| Controller | `app/Http/Controllers/ReportController.php` | `acquistoEtichette()` / `venditaEtichette()` + shared `lottoLabels()` builder; `copie` 1–60. |
| Routes | `routes/web.php` | `GET /acquisti/{id}/etichette`, `GET /vendite/{id}/etichette`. |
| UI | `Pages/Acquisti/Index.vue`, `Pages/Vendite/Index.vue` | QR button in the row actions (desktop + mobile card). |

### Phase 3 — Allergen tracking (Reg. UE 1169/2011)
| Area | File(s) | Change |
|---|---|---|
| Migration | `database/migrations/2026_07_06_000002_add_allergeni_to_materie_prime.php` (new) | `allergeni` + `allergeni_tracce` JSON columns. |
| Service | `app/Services/AllergenService.php` (new) | The 14 EU allergens; `forProduzione()` unions ingredient allergens **recursively** through semilavorati (cycle-guarded); `forProduzioneLabels()` for display. |
| Model | `app/Models/MateriaPrima.php` | Fillable + array casts for the two columns. |
| Controller | `app/Http/Controllers/MateriaPrimaController.php` | Validate against the 14-code whitelist; pass options/labels to the form + index. |
| UI | `Pages/MateriePrime/Form.vue`, `Pages/MateriePrime/Index.vue`, `Pages/Tracciabilita.vue` | MultiSelects on the form, chips in the list + traceability. |
| Labels/PDF | `labels/produzione.blade.php`, `pdf/produzione.blade.php`, `ReportController`, `TracciabilitaController` | Derived allergens printed on the production QR label + HACCP PDF and shown per production lot in traceability. |

### Phase 4 — Tests
`tests/Feature/CestinoTest.php`, `EtichetteTest.php`, `AllergenTest.php`,
`RecallTest.php`, `UtenteTest.php`, `ImballaggioTest.php` (all new); three
delete assertions in `AcquistiTest`, `ProduzioniTest`, `ProduzioneHttpTest`
changed from `assertDatabaseMissing` to `assertSoftDeleted` (intended
behaviour change).

No existing behaviour was modified destructively — deletes changed from hard to soft (recoverable) with guards preserving the prior "can't delete referenced data" rule.

---

## 3. Bugs found & fixed in passing

- **`TracciabilitaController::search()`** — `$op = … ? $op : 'like'` used `$op`
  **before** it was assigned. Harmless on SQLite (tests), but on PostgreSQL the
  ILIKE branch used an undefined operator. Fixed to `? 'ilike' : 'like'`.
- **Laravel `pluck` + qualified aggregate** — a table-qualified column inside
  `pluck(DB::raw('SUM(t.col)'), …)` breaks `stripTableForPluck` (it splits on the
  dot). Aggregate columns are aliased (`… as s`) or left unqualified.

---

## 4. Verification

- **Full suite after every phase:** Phase 1 → 72 tests, Phase 2 → 75, Phase 3 → 79, Phase 4 → **91**; 1 pre-existing failure throughout, zero new regressions.
- **New tests exercise the risky logic directly:** soft-delete + restore + force-delete round-trip; delete-guard blocking; **balance release** (a trashed production frees its consumed lot); recursive allergen propagation through a semilavorato; the 14-code validation whitelist; recall open → notify → close.
- **Frontend build** (`npm run build`) clean after each phase.
- **Migrations** apply cleanly on fresh SQLite (via `RefreshDatabase`).

> Pre-existing failure (unchanged): `AccessControlTest::test_operator_cannot_delete_admin_records` — `SubstituteBindings` returns 404 before the `admin` middleware redirects. Middleware-ordering quirk, unrelated to this session.

---

## 5. Documentation

| File | Change |
|---|---|
| `docs/BLUEPRINT.md` | **New** — full system overview (what it is, stack, architecture, patterns, data model, all features). |
| `docs/ARCHITECTURE.md` | Appended "Services & components added 2026-07-06" (AllergenService, CestinoController, soft-delete design note). |
| `docs/MODULES.md` | Appended "Modules added 2026-07-06" (Cestino, purchase/sale QR labels, allergens) + soft-delete/allergen sub-sections. |
| `docs/DATABASE.md` | Appended "Columns added 2026-07-06" (`deleted_at` on 7 tables; `allergeni`/`allergeni_tracce`). |
| `docs/WORKFLOWS.md` | Appended §17 (soft-delete/restore/guards) and §18 (allergen derivation). |
| `docs/API.md` | Appended "Endpoints added 2026-07-06" (cestino, etichette, materie-prime allergen fields). |
| `docs/ROADMAP.md` | Marked P-B2 (soft-delete) and P-B8 (lot labels) resolved; updated P-B13 (tests); added P-B16 (allergens). |
| `README.md` | Features table (Conformità / Sicurezza dati / allergens), docs table (BLUEPRINT, this changelog). |
| `docs/CHANGELOG-2026-07-06.md` | **New** — this file. |

> `schema.sql` still reflects the pre-2026-07-06 schema; the two new migrations are the source of truth for `deleted_at` and the allergen columns until the SQL dump is regenerated.

---

## 6. Remaining (roadmap)

- **Run `php artisan migrate`** on dev/prod DBs to apply the two new migrations (production `docker/start.sh` migrates on boot).
- **Full FIC consumer labels** — the allergen foundation is in place; the ingredient list + nutrition declaration remain if prepacked consumer sales are confirmed.
- **Schede CRUD tests** — the remaining largely-untested controller (P-B13).

---

# Part 2 — compliance & correctness gaps

A follow-up batch closing the gaps identified in a functionality review. Four
tested phases; suite grew to **98 tests, now fully green** (the former
pre-existing failure is fixed).

## A — Append-only audit / change log
- `2026_07_06_000003_create_audit_logs_table` + `App\Models\AuditLog`.
- `App\Concerns\Auditable` extended: on every `created`/`updated`/`deleted`/`restored`/`force_deleted` it writes an immutable row with the before→after value of each changed field, the acting user, and a label snapshot. No-op saves and `deleted_at`-only diffs are skipped; `restored` is registered only on SoftDeletes models (else `Recall` throws at boot).
- `AuditService::changeLog()` + reworked `AuditController` and `Audit/Index.vue` (event chips + `da → a` diffs).
- Resolves **P-B7** (audit trail was capture-only / current-state).

## B — Link incoming lots to materie prime
- `2026_07_06_000004_add_materia_prima_id_to_acquisti_righe` + `AcquistoRiga::materiaPrima()`.
- Optional raw-material select per line on the acquisti form; validated against `materie_prime`.
- Allergens now flow onto purchase lots: shown on the purchase-lot QR label and the traceability purchase node.

## C — Middleware ordering fix
- `bootstrap/app.php`: `prependToPriorityList(before: SubstituteBindings, prepend: EnsureAdmin)` so the admin check runs **before** route-model binding. An unauthorised operator is now cleanly redirected instead of leaking a 404. **Fixes the long-standing `AccessControlTest` baseline failure.**

## D — schema.sql & docs
- `schema.sql` extended with a 2026-07-06 section: `deleted_at` (7 tables), `allergeni`/`allergeni_tracce`, `acquisti_righe.materia_prima_id`, and the `audit_logs` table; final marker bumped to `2026_07_06_000004`.
- Docs updated: `BLUEPRINT`, `ARCHITECTURE`, `DATABASE`, `WORKFLOWS` (§19 audit log, §20 lot allergens), `ROADMAP` (P-B7 resolved).

## New tests
`AuditLogTest` (4), `PurchaseLotAllergenTest` (3) — plus the `AccessControlTest` delete case now passes.

## Migrations to run
`php artisan migrate` applies `2026_07_06_000003` (audit_logs) and `2026_07_06_000004` (materia_prima_id) in addition to the earlier two.
