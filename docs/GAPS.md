# GAPS.md
## Marche International Food S.R.L. — Technical Debt, Security & Domain Gaps

Severity scale: **Critical** · **High** · **Medium** · **Low**

---

## 1. Security Gaps

### GAP-S1 — No Login Rate Limiting
**Severity: High**

The `POST /login` route has no `throttle` middleware. An attacker can attempt unlimited password guesses with no delay or lockout. Laravel ships a `throttle:6,1` (6 attempts per minute) built-in for auth routes — it is simply not applied here. All protection relies on Traefik/Coolify/Hetzner firewall configuration, which may or may not be enabled.

**Fix:**
```php
// routes/web.php
Route::post('/login', [LoginController::class, 'login'])
    ->middleware('throttle:10,1');
```

---

### GAP-S2 — `APP_DEBUG=true` Risk in Production
**Severity: High**

The `.env.example` ships with `APP_DEBUG=true`. If a developer copies `.env.example` to `.env` without changing this value (or if Coolify's environment variables don't override it), Laravel will expose full stack traces — including DB credentials, `.env` contents, and internal file paths — in HTTP error responses to the browser.

**Fix:** Set `APP_DEBUG=false` in the Coolify environment variable panel. Never rely on the file.

---

### GAP-S3 — Sessions Not Encrypted at Rest
**Severity: Medium**

`SESSION_ENCRYPT=false` means session records in the `sessions` DB table are stored in plaintext. If the database is ever accessed by an unauthorized party, session tokens can be read directly and used to impersonate users.

**Fix:** Set `SESSION_ENCRYPT=true`. Has no performance impact on a single-server deployment.

---

### GAP-S4 — No HTTPS Enforcement in Application Code
**Severity: Medium**

The application does not include a `ForceHttps` middleware or set `HSTS` headers. If Traefik/Coolify is misconfigured or bypassed, the app will serve over plain HTTP. Inertia requests containing CSRF tokens would be transmitted in cleartext.

**Fix:**
```php
// app/Providers/AppServiceProvider.php
if (app()->environment('production')) {
    \URL::forceScheme('https');
}
```
And configure an HSTS header in Traefik.

---

### GAP-S5 — No Audit Trail (Who Did What)
**Severity: Medium**

No operational table (`acquisti`, `vendite`, `produzioni`, etc.) records which user created or last modified a record. In an HACCP-regulated food environment, an audit trail of operator actions is often a regulatory requirement (tracing who registered a production run, who deleted a lot, etc.).

**Fix:** Add `created_by` / `updated_by` FK columns referencing `users.id` to all operational tables. Populate via an Eloquent observer or a global scope on the model `creating`/`updating` events.

---

### GAP-S6 — Admin Can Delete Their Own Account (partial)
**Severity: Low**

`UtenteController::destroy()` blocks a user from deleting their own account with a check `if ($utente->id === auth()->id())`. However, if there is only one admin in the system, any other admin can delete them, leaving the system without admin access. There is no check for "last admin" protection.

**Fix:** Add a guard: `if (User::where('role', 'admin')->count() === 1 && $utente->role === 'admin') abort(403, 'Cannot delete the last admin.')`.

---

## 2. Technical Debt

### GAP-T1 — Acquisto Edit Silently Fails When Lines Are Linked to Productions
**Severity: Critical**

`AcquistoController::update()` and `VenditaController::update()` perform a full delete-and-recreate of all child lines (`$acquisto->righe()->delete()`). If any `acquisto_riga` has been referenced in `produzioni_materie_prime`, the `DELETE` will throw a PostgreSQL FK violation error because there is no `ON DELETE CASCADE` from `acquisti_righe` to `produzioni_materie_prime`.

In production with `APP_DEBUG=false`, this surfaces as an unhandled 500 error with no meaningful user message. The operator has no way to know why the save failed.

**Fix (short-term):** Wrap the update in a try/catch and return a user-friendly validation error: `"Impossibile modificare: alcune righe sono già collegate a produzioni."`.

**Fix (long-term):** Implement a partial-update strategy that only replaces unlinked lines, or disallow editing of acquisti that have any linked productions (soft-lock with a warning in the UI).

---

### GAP-T2 — Import Is Not Transactional
**Severity: High**

The CSV import in `ImportController` commits each document group to the database as it is processed. If an error occurs mid-file (e.g., row 80 of 200 has an invalid date), rows 1–79 are already committed and rows 80–200 are skipped. The resulting partial state is presented to the user as a success with warnings, but there is no way to roll back the partial import.

**Fix:** Wrap the entire import loop in `DB::transaction()`. Either the whole file succeeds or nothing is committed.

```php
DB::transaction(function () use ($grouped, &$imported, &$errors) {
    // all the foreach loops
});
```

---

### GAP-T3 — Missing FK Indexes (13 columns)
**Severity: High**

See INDEXING.md Section 3 for the full list. PostgreSQL does not automatically index FK columns. Thirteen FK columns that participate in eager loads or joins have no indexes. On a table with thousands of rows (e.g., `acquisti_righe`, `produzioni_materie_prime`), these generate sequential scans on every Inertia page load.

**Fix:** Run the 13 `CREATE INDEX` statements listed in INDEXING.md §3.

---

### GAP-T4 — Full Line Delete-Recreate on Every Edit
**Severity: Medium**

Both `AcquistoController::update()` and `VenditaController::update()` (and `SchedaProduzioneController` for ricette/flussi) delete all child rows and recreate them on every save, even if nothing changed. This is fine for small documents but has two side-effects:
1. Auto-incremented IDs of child rows change on every save, invalidating any external references.
2. The approach fails entirely when child rows have downstream FK dependencies (see GAP-T1).

**Fix:** Implement a diff-based sync: compare submitted rows against existing rows, insert new ones, update changed ones, delete removed ones.

---

### GAP-T5 — No Pagination on Traceability Results
**Severity: Medium**

`TracciabilitaController::search()` applies hard limits (50 rows for `righe_acquisto`, 20 for `produzioni`) with no pagination. A company with many lot numbers matching a common substring (e.g., searching "2024") will hit the limit silently, with no indication that results were truncated.

**Fix:** Add pagination to the traceability search results and surface a "Showing first N results" warning when limits are hit.

---

### GAP-T6 — `lotto_esterno` Not Indexed in `vendite_righe`
**Severity: Medium**

`idx_acquisti_righe_lotto_est` indexes `lotto_esterno` on purchase lines. The equivalent column on `vendite_righe.lotto` is indexed (`idx_vendite_righe_lotto`), but `vendite_righe.lotto_esterno` has no index. The traceability search currently only searches acquisti_righe for lot numbers — if extended to sales lines, `lotto_esterno` searches on vendite_righe would be unindexed.

**Fix:** `CREATE INDEX idx_vendite_righe_lotto_ext ON vendite_righe(lotto_esterno);`

---

### GAP-T7 — Dashboard Stats Are Not Cached
**Severity: Low**

`DashboardController::index()` runs 8 database queries synchronously on every page load (6 COUNT queries + 2 SELECT queries for recent records + 1 expiry query). As the database grows, this page will become the slowest in the application. There is no cache layer.

**Fix:** Cache the KPI counts for 5 minutes using Laravel's cache facade:
```php
$stats = Cache::remember('dashboard_stats', 300, fn() => [...]);
```
The expiry alert query (safety-critical data) should remain uncached or cached for no more than 60 seconds.

---

### GAP-T8 — No Log Persistence Across Container Restarts
**Severity: Low**

Application logs write to `storage/logs/laravel.log` inside the container filesystem. This file is destroyed on every container rebuild or restart, making post-incident debugging impossible.

**Fix:** Mount a Docker volume to `/var/www/html/storage/logs/` in Coolify, or configure `LOG_CHANNEL=stderr` and capture logs via Coolify's log aggregation.

---

## 3. Domain / Schema Flaws

### GAP-D1 — Packaging Lots Not Linked to Productions
**Severity: High**

`lotti_imballaggi_primari` (packaging) and `lotti_detergenti` (cleaning products) are tracked for MOCA/hygiene compliance, but there is no `produzioni_imballaggi` or `produzioni_detergenti` junction table. This means the system cannot answer the question: "Which production runs used packaging lot X?" or "Which batches of product Y were packed in materials from supplier Z?"

For full HACCP traceability (as required by EU Regulation 178/2002), packaging and cleaning materials should be traceable to production runs just as ingredients are.

**Fix:** Create `produzioni_imballaggi_primari` and/or `produzioni_detergenti` tables mirroring the `produzioni_materie_prime` pattern, and add them to the production form UI.

---

### GAP-D2 — No Inventory Balance / Lot Closure Enforcement
**Severity: High**

The schema records `acquisti_righe.quantita_kg` (received) and `produzioni_materie_prime.quantita_kg` (consumed per production run), but **never computes or stores the remaining balance**. There is no constraint preventing a production run from consuming more of a lot than was received, and no alert when a lot is fully consumed.

`data_out` on `acquisti_righe` is a manually set field — operators must remember to close lots themselves. The system will not auto-close a lot when its quantity reaches zero.

**Fix (short-term):** Add a computed balance display in the Produzioni form showing `received_kg - SUM(consumed_kg)` for each open lot.

**Fix (long-term):** Add a DB CHECK or trigger that validates `SUM(produzioni_materie_prime.quantita_kg) <= acquisti_righe.quantita_kg` per riga.

---

### GAP-D3 — Recipe Is Not Enforced at Production Time
**Severity: Medium**

`schede_produzione` defines which ingredients (via `ricette`) belong to which product. When creating a `produzione`, the operator selects a scheda and then manually links ingredient lots. The system does not verify that:
- All recipe ingredients are covered by a linked `acquisto_riga`
- The linked lots contain the correct `materia_prima_id` specified in the recipe
- Quantities are consistent with recipe percentages

An operator can link completely unrelated ingredient lots to a production run without any warning.

**Fix:** Add backend validation in `ProduzioneController::store()` that cross-checks `materie_prime[].materia_prima_id` against the recipe of the selected scheda.

---

### GAP-D4 — `note_credito` Allows Both FK Columns to Be NULL
**Severity: Medium**

`note_credito.vendita_id` and `note_credito.bolla_reso_id` are both nullable. There is no `CHECK` constraint requiring at least one to be populated. An admin can create a credit note with no parent reference, creating an orphaned financial record.

**Fix:**
```sql
ALTER TABLE note_credito
    ADD CONSTRAINT note_credito_requires_parent
    CHECK (vendita_id IS NOT NULL OR bolla_reso_id IS NOT NULL);
```

---

### GAP-D5 — `tipo_documento` on `acquisti` Is Inconsistent with `vendite`
**Severity: Low**

`acquisti.tipo_documento` allows `DDT`, `Fattura`, `Bolla` (as string values). `vendite.tipo_documento` allows `DDT`, `FI`, `NC`. These are defined as VARCHAR with `CHECK` constraints, not as foreign keys to a lookup table. Adding new document types requires a schema migration. Additionally, the Import controller accepts `Fattura` for acquisti but the schema CHECK lists uppercase `DDT` — mixed casing could cause check violations depending on the PostgreSQL collation in use.

**Fix:** Normalize to a `tipi_documento` lookup table, or at minimum add a `LOWER()` normalization step in the import and validation logic.

---

### GAP-D6 — Traceability Does Not Connect Productions to Sales
**Severity: Medium**

The tracciabilità module traces ingredient lots to production runs (`acquisti_righe → produzioni_materie_prime → produzioni`). However, it does not continue the chain forward to show which customers received the finished product. The link between a `produzione.lotto_produzione` and a `vendita_riga.lotto` is a **business convention** (the operator writes the same lot number into both), not a database foreign key.

This means:
- There is no FK from `vendite_righe.lotto` to `produzioni.lotto_produzione`
- The traceability search cannot programmatically answer "Customer X received product from lot Y"
- A typo in either lot string breaks the chain silently

**Fix (schema):** Add `produzione_id BIGINT REFERENCES produzioni(id)` to `vendite_righe`. Populate it when recording sales from production lots.

**Fix (short-term):** Extend `TracciabilitaController::search()` to also query `vendite_righe WHERE lotto ILIKE ?` and include sales results in the traceability panel.

---

### GAP-D7 — No Scheda Versioning Workflow
**Severity: Low**

When a production sheet (`scheda_produzione`) is updated, the system creates a new record with an incremented `revisione` number. However:
- There is no mechanism to deactivate the previous revision automatically
- Productions linked to old revisions remain valid and display the old scheda data (which no longer exists if updated in place rather than versioned)
- There is no diff view between revisions

In regulated food production, recipe changes must be documented and traceable. The current model supports this data structure but provides no workflow enforcement.

**Fix:** When creating a new revision, automatically set `attiva = false` on all previous revisions for the same `prodotto_id`. Add a revision history view to the Schede module.

---

## 4. Summary Table

| ID | Severity | Category | Short Description |
|---|---|---|---|
| GAP-S1 | **High** | Security | No login rate limiting |
| GAP-S2 | **High** | Security | `APP_DEBUG=true` default |
| GAP-S3 | Medium | Security | Sessions not encrypted at rest |
| GAP-S4 | Medium | Security | No HTTPS enforcement in app code |
| GAP-S5 | Medium | Security | No audit trail (who created/modified records) |
| GAP-S6 | Low | Security | No "last admin" deletion guard |
| GAP-T1 | **Critical** | Tech Debt | Acquisto edit crashes (500) when lines are linked to productions |
| GAP-T2 | **High** | Tech Debt | CSV import not wrapped in a DB transaction |
| GAP-T3 | **High** | Tech Debt | 13 missing FK indexes → sequential scans |
| GAP-T4 | Medium | Tech Debt | Full line delete-recreate on every edit |
| GAP-T5 | Medium | Tech Debt | No pagination on traceability results (hard-cut at 50/20) |
| GAP-T6 | Medium | Tech Debt | `vendite_righe.lotto_esterno` unindexed |
| GAP-T7 | Low | Tech Debt | Dashboard stats not cached |
| GAP-T8 | Low | Tech Debt | Logs lost on container rebuild |
| GAP-D1 | **High** | Domain | Packaging lots not linked to productions (incomplete HACCP chain) |
| GAP-D2 | **High** | Domain | No inventory balance — no lot quantity enforcement |
| GAP-D3 | Medium | Domain | Recipe not enforced at production time |
| GAP-D4 | Medium | Domain | `note_credito` allows both FKs to be NULL |
| GAP-D5 | Low | Domain | Inconsistent `tipo_documento` values between acquisti and vendite |
| GAP-D6 | Medium | Domain | Traceability chain broken between productions and sales |
| GAP-D7 | Low | Domain | No automated scheda versioning workflow |
