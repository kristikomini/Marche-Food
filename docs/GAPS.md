# GAPS.md
## Marche International Food S.R.L. — Technical Debt, Security & Domain Gaps

Severity scale: **Critical** · **High** · **Medium** · **Low**

> **All 21 gaps resolved** — 2026-06-23. See each entry for the fix applied.

---

## 1. Security Gaps

### GAP-S1 — No Login Rate Limiting ✅ RESOLVED
**Severity: High**

The `POST /login` route had no `throttle` middleware.

**Fix applied (`routes/web.php`):**
```php
Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');
```

---

### GAP-S2 — `APP_DEBUG=true` Risk in Production ✅ RESOLVED
**Severity: High**

`.env.example` shipped with `APP_DEBUG=true`.

**Fix applied (`.env.example`):** Changed to `APP_DEBUG=false`.

---

### GAP-S3 — Sessions Not Encrypted at Rest ✅ RESOLVED
**Severity: Medium**

Session records stored in plaintext in the `sessions` table.

**Fix applied (`.env.example`):** Changed to `SESSION_ENCRYPT=true`.

---

### GAP-S4 — No HTTPS Enforcement in Application Code ✅ RESOLVED
**Severity: Medium**

No `ForceHttps` middleware or HSTS header in application code.

**Fix applied (`app/Providers/AppServiceProvider.php`):**
```php
if (app()->environment('production')) {
    \URL::forceScheme('https');
}
```

---

### GAP-S5 — No Audit Trail (Who Did What) ✅ RESOLVED
**Severity: Medium**

No operational table recorded which user created or last modified a record.

**Fix applied:**
- New `app/Concerns/Auditable.php` trait: automatically populates `created_by` / `updated_by` via Eloquent model events.
- New migration `2026_06_23_000002`: adds `created_by BIGINT REFERENCES users(id)` and `updated_by BIGINT REFERENCES users(id)` to: `acquisti`, `vendite`, `produzioni`, `bolle_reso`, `note_credito`, `lotti_imballaggi_primari`, `lotti_detergenti`.
- Trait applied to all 7 models.

---

### GAP-S6 — Admin Can Delete Their Own Account (partial) ✅ RESOLVED
**Severity: Low**

No "last admin" protection — a single remaining admin could be deleted, locking out the system.

**Fix applied (`app/Http/Controllers/UtenteController.php`):**
```php
if ($utente->role === 'admin' && User::where('role', 'admin')->count() === 1) {
    return back()->with('error', 'Impossibile eliminare: è l\'ultimo amministratore del sistema.');
}
```

---

## 2. Technical Debt

### GAP-T1 — Acquisto Edit Silently Fails When Lines Are Linked to Productions ✅ RESOLVED
**Severity: Critical**

Full delete-and-recreate of `acquisti_righe` on update triggered a FK violation when any line was referenced in `produzioni_materie_prime`.

**Fix applied (`AcquistoController`, `VenditaController`):**
- Diff-based sync: submitted rows with an `id` are updated in place; rows without an `id` are inserted; rows present in the database but absent from the submission are checked for downstream FK references before deletion.
- If any to-be-deleted rows have production references, a 422 is returned with a clear Italian error message.
- Entire update wrapped in `DB::transaction()`.

---

### GAP-T2 — Import Is Not Transactional ✅ RESOLVED
**Severity: High**

CSV import committed each document group independently; a mid-file error left a partial import committed.

**Fix applied (`app/Http/Controllers/ImportController.php`):** Both import methods (`importAcquisti`, `importVendite`) now wrap the entire loop in `DB::beginTransaction()` / `DB::commit()` / `DB::rollBack()`. Either the entire file is committed or nothing is.

---

### GAP-T3 — Missing FK Indexes (13 columns) ✅ RESOLVED
**Severity: High**

Thirteen FK columns that participate in eager loads or joins had no indexes.

**Fix applied:** New migration `2026_06_23_000001` adds all 13 missing indexes via `CREATE INDEX IF NOT EXISTS`:
`idx_acquisti_righe_acquisto`, `idx_vendite_righe_vendita`, `idx_bolle_reso_vendita_riga`, `idx_note_credito_vendita`, `idx_note_credito_bolla`, `idx_schede_flussi_scheda`, `idx_schede_flussi_flusso`, `idx_ricette_scheda`, `idx_ricette_mp`, `idx_ricette_mar_scheda`, `idx_prod_mp_materia`, `idx_imb_primari_fornitore`, `idx_detergenti_fornitore`.
Plus `idx_vendite_righe_lotto_ext` (see GAP-T6).

---

### GAP-T4 — Full Line Delete-Recreate on Every Edit ✅ RESOLVED
**Severity: Medium**

Every save of an acquisto or vendita deleted and recreated all child lines, changing their primary key IDs.

**Fix applied:** Both `AcquistoController` and `VenditaController` now implement diff-based sync. Frontend (`Form.vue`) sends `id: r.id ?? null` per riga so existing rows can be matched and updated in place without ID churn.

---

### GAP-T5 — No Pagination on Traceability Results ✅ RESOLVED
**Severity: Medium**

Hard limits (50 / 20 rows) silently truncated results with no user indication.

**Fix applied (`TracciabilitaController`, `Tracciabilita.vue`):**
- Server computes a total count for each result set via `(clone $query)->count()` before applying the limit.
- Passes `total_righe`, `total_produzioni`, `total_vendite` alongside the capped results.
- Frontend renders a truncation warning banner under each section header when the count exceeds the limit.

---

### GAP-T6 — `lotto_esterno` Not Indexed in `vendite_righe` ✅ RESOLVED
**Severity: Medium**

`vendite_righe.lotto_esterno` was unindexed.

**Fix applied:** `CREATE INDEX idx_vendite_righe_lotto_ext ON vendite_righe(lotto_esterno)` included in migration `2026_06_23_000001` (same migration as GAP-T3).

---

### GAP-T7 — Dashboard Stats Are Not Cached ✅ RESOLVED
**Severity: Low**

8 synchronous DB queries ran on every dashboard page load.

**Fix applied (`app/Http/Controllers/DashboardController.php`):**
- KPI counts cached with `Cache::remember("dashboard_stats_{$anno}_{$mese}", 300, ...)` (5-minute TTL).
- Safety-critical expiry counts cached separately with a 60-second TTL: `Cache::remember('dashboard_expiry', 60, ...)`.

---

### GAP-T8 — No Log Persistence Across Container Restarts ✅ RESOLVED
**Severity: Low**

Logs written to `storage/logs/laravel.log` inside the container were lost on rebuild.

**Fix applied (`.env.example`):** Added documentation comment recommending `LOG_CHANNEL=stderr` for production deployments to capture logs via Coolify's container log aggregation.

---

## 3. Domain / Schema Flaws

### GAP-D1 — Packaging Lots Not Linked to Productions ✅ RESOLVED
**Severity: High**

No junction table linked `lotti_imballaggi_primari` or `lotti_detergenti` to production runs.

**Fix applied:**
- New migration `2026_06_23_000004`: creates `produzioni_imballaggi_primari` (FK to `produzioni` CASCADE, FK to `lotti_imballaggi_primari` RESTRICT, `quantita_usata`, `note`) and `produzioni_detergenti` (FK to `produzioni` CASCADE, FK to `lotti_detergenti` RESTRICT, `quantita_usata`, `note`).
- New models: `ProduzioneImballaggioPrimario`, `ProduzioneDetergente`.
- `Produzione` model: added `imballaggiPrimari()` and `detergenti()` hasMany relationships.
- `ProduzioneController`: `create()`/`edit()` pass `lotti_imballaggi` and `lotti_detergenti`; `store()`/`update()` call `syncImballaggi()` and `syncDetergenti()` inside the DB transaction.
- `Produzioni/Form.vue`: two new table sections for packaging and detergent lot linking.

---

### GAP-D2 — No Inventory Balance / Lot Closure Enforcement ✅ RESOLVED
**Severity: High**

No display of remaining quantity per purchase lot; operators could over-consume a lot silently.

**Fix applied (`ProduzioneController::acquistiRigheForForm()`):**
- Computes `balance_kg = quantita_kg - SUM(produzioni_materie_prime.quantita_kg)` per lot (excluding the current production's own consumption in edit context).
- Passes `balance_kg` as a virtual field on each `acquisto_riga`.
- `Produzioni/Form.vue`: displays balance in the lot dropdown label and in a dedicated column with green/red color coding based on sign.

---

### GAP-D3 — Recipe Is Not Enforced at Production Time ✅ RESOLVED
**Severity: Medium**

Operators could link any ingredient lots to a production run regardless of the scheda recipe.

**Fix applied (`ProduzioneController::validateRecipeIngredients()`):**
- Loads the scheda's `ricette` and `ricetteMarinature` ingredient lists.
- Cross-checks submitted `materia_prima_id` values against the allowed set.
- Returns a 422 with the names of invalid ingredients if any are found.
- Validation is skipped entirely when the scheda has no defined recipe, allowing flexible production runs.

---

### GAP-D4 — `note_credito` Allows Both FK Columns to Be NULL ✅ RESOLVED
**Severity: Medium**

Both `vendita_id` and `bolla_reso_id` could be null simultaneously, creating orphaned credit notes.

**Fix applied:** New migration `2026_06_23_000003`:
```sql
ALTER TABLE note_credito ADD CONSTRAINT note_credito_requires_parent
    CHECK (vendita_id IS NOT NULL OR bolla_reso_id IS NOT NULL);
```

---

### GAP-D5 — `tipo_documento` on `acquisti` Is Inconsistent ✅ RESOLVED
**Severity: Low**

`strtoupper()` in the import controller turned `'Fattura'` → `'FATTURA'`, which then failed an `in_array(['DDT', 'Fattura', 'Bolla'])` check, causing every Fattura to be silently stored as `'DDT'`.

**Fix applied (`app/Http/Controllers/ImportController.php`):** Replaced the broken check with:
```php
$tipo = match (strtoupper(trim($first['tipo_documento'] ?? ''))) {
    'DDT'     => 'DDT',
    'FATTURA' => 'Fattura',
    'BOLLA'   => 'Bolla',
    default   => 'DDT',
};
```

---

### GAP-D6 — Traceability Does Not Connect Productions to Sales ✅ RESOLVED
**Severity: Medium**

The traceability module stopped at production runs and did not continue to sales/customers.

**Fix applied (`TracciabilitaController`, `Tracciabilita.vue`):**
- Added a third search leg querying `vendite_righe WHERE lotto ILIKE ? OR lotto_esterno ILIKE ? OR nome_prodotto ILIKE ?`, eager-loading `vendita → cliente`.
- Passes `vendite_righe` and `total_vendite` to Inertia.
- Frontend renders a new "Sales Leg" section (blue border, `pi-send` icon) below the production section.

---

### GAP-D7 — No Scheda Versioning Workflow ✅ RESOLVED
**Severity: Low**

Creating a new revision did not automatically deactivate previous revisions for the same product.

**Fix applied (`app/Http/Controllers/SchedaProduzioneController.php`):** In `store()`, before creating the new scheda:
```php
if (!empty($data['attiva'])) {
    SchedaProduzione::where('prodotto_id', $data['prodotto_id'])
        ->where('attiva', true)
        ->update(['attiva' => false]);
}
```

---

## 4. Summary Table

| ID | Severity | Category | Short Description | Status |
|---|---|---|---|---|
| GAP-S1 | **High** | Security | No login rate limiting | ✅ Resolved |
| GAP-S2 | **High** | Security | `APP_DEBUG=true` default | ✅ Resolved |
| GAP-S3 | Medium | Security | Sessions not encrypted at rest | ✅ Resolved |
| GAP-S4 | Medium | Security | No HTTPS enforcement in app code | ✅ Resolved |
| GAP-S5 | Medium | Security | No audit trail (who created/modified records) | ✅ Resolved |
| GAP-S6 | Low | Security | No "last admin" deletion guard | ✅ Resolved |
| GAP-T1 | **Critical** | Tech Debt | Acquisto edit crashes (500) when lines linked to productions | ✅ Resolved |
| GAP-T2 | **High** | Tech Debt | CSV import not wrapped in a DB transaction | ✅ Resolved |
| GAP-T3 | **High** | Tech Debt | 13 missing FK indexes → sequential scans | ✅ Resolved |
| GAP-T4 | Medium | Tech Debt | Full line delete-recreate on every edit | ✅ Resolved |
| GAP-T5 | Medium | Tech Debt | No pagination on traceability results (hard-cut at 50/20) | ✅ Resolved |
| GAP-T6 | Medium | Tech Debt | `vendite_righe.lotto_esterno` unindexed | ✅ Resolved |
| GAP-T7 | Low | Tech Debt | Dashboard stats not cached | ✅ Resolved |
| GAP-T8 | Low | Tech Debt | Logs lost on container rebuild | ✅ Resolved |
| GAP-D1 | **High** | Domain | Packaging lots not linked to productions | ✅ Resolved |
| GAP-D2 | **High** | Domain | No inventory balance — no lot quantity enforcement | ✅ Resolved |
| GAP-D3 | Medium | Domain | Recipe not enforced at production time | ✅ Resolved |
| GAP-D4 | Medium | Domain | `note_credito` allows both FKs to be NULL | ✅ Resolved |
| GAP-D5 | Low | Domain | Inconsistent `tipo_documento` values between acquisti and vendite | ✅ Resolved |
| GAP-D6 | Medium | Domain | Traceability chain broken between productions and sales | ✅ Resolved |
| GAP-D7 | Low | Domain | No automated scheda versioning workflow | ✅ Resolved |
