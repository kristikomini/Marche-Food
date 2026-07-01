# WORKFLOWS.md
## Marche International Food S.R.L. — Domain Workflows & Business Rules

This document explains the non-obvious business workflows enforced in the
application code. It complements `MODULES.md` (what each screen does) and
`DATABASE.md` (how tables relate). Source of truth for behaviour is the
controllers under `app/Http/Controllers/`.

---

## 1. Bidirectional lot traceability (HACCP core)

Every finished product can be traced back to the supplier lots it was made from,
and every incoming supplier lot can be traced forward to the customers who
received the finished product.

```
Fornitore → Acquisto → acquisti_righe (lotto, scadenza, data_in)
    → produzioni_materie_prime (quantità consumata per lotto)
        → Produzione (lotto_produzione)
            → lotti_semilavorati (opzionale, riuso interno)
            → vendite_righe (produzione_id / acquisto_riga_id)
                → Vendita → Cliente
```

The join table `produzioni_materie_prime` is the pivot that makes both
directions possible: it records, for each production run, the exact source lot
(`acquisto_riga_id`) **or** internal semi-finished lot (`semilavorato_id`) and
the quantity in kg consumed.

The **Tracciabilità** screen queries three legs (purchase lots, production lots,
sales lines) and the **Recall** screen resolves a production lot to the list of
customers who must be contacted.

---

## 2. Inventory balance & lot-closure enforcement (GAP-D2)

There is **no materialised stock column**. The available balance of a purchase
lot is computed on demand:

```
balance_kg(acquisto_riga) =
      quantita_kg (received)
    − Σ produzioni_materie_prime.quantita_kg (consumed in productions)
    − Σ vendite_righe.quantita_kg where acquisto_riga_id = this lot (direct resale)
```

When a production run is saved, `ProduzioneController::lockAndCheckBalance()`:

1. Acquires a **pessimistic lock** (`SELECT … FOR UPDATE`) on every source lot
   referenced by the run, in a deterministic order to avoid deadlocks.
2. Recomputes each lot's balance **inside the transaction**.
3. Rejects the save with a 422 if any ingredient would draw the balance below
   zero (tolerance: 0.001 kg for rounding). In edit mode, the run's own current
   consumption is excluded from the balance so a save is idempotent.

The production form shows the live balance per lot (`balance_kg`) with green /
red colour coding so operators cannot silently over-consume a lot.

**Semi-finished lots are covered too.** The same method locks (`SELECT … FOR
UPDATE`) and re-checks `lotti_semilavorati` balances
(`quantita_kg − Σ consumed in downstream productions`) and rejects a run that
would over-draw an internal lot — verified by the balance simulation in
`tests/` and the session change-log.

---

## 3. Semi-finished products (semilavorati)

A production run can output an intermediate ("semi-finished") lot that is later
consumed as an ingredient by another production run — enabling multi-stage
production chains (e.g. brined tuna → canned tuna).

- Register: `POST /produzioni/{id}/semilavorato` with `lotto` (unique in
  `lotti_semilavorati`), `nome_prodotto`, `quantita_kg`, `note`.
- A production can register **at most one** semi-finished lot. A second attempt
  returns a 422. Double-registration is prevented with a `lockForUpdate()` on
  the parent production inside the transaction.
- When used as an ingredient, the production line sets `source_type = interno`
  and `semilavorato_id` instead of `acquisto_riga_id`.
- Database guarantee (PostgreSQL): the `source_exactly_one` CHECK constraint on
  `produzioni_materie_prime` requires **exactly one** of `acquisto_riga_id` /
  `semilavorato_id` to be set. The controller enforces the same XOR rule in
  application code so SQLite deployments (tests) behave identically.

---

## 4. Conto terzi (third-party / toll processing)

Marche can process materials owned by a third party ("conto terzi"). These must
not pollute inventory valuation or financial KPIs.

- A supplier can have `tipo = 'conto_terzi'`.
- A purchase document carries `is_conto_terzi = true`.
- Documents flagged conto terzi are **excluded** from the dashboard KPI counts
  and from the standard CSV exports of purchased goods.
- Traceability still works normally — the material is tracked for HACCP, just
  not counted as owned stock.

---

## 5. Line diff-sync on edit (GAP-T1 / GAP-T4)

Acquisti and Vendite are header + lines. On **update**, lines are **not**
delete-and-recreated (which would churn primary keys and break downstream FKs).
Instead `AcquistoController` / `VenditaController` perform a **diff sync**:

- Submitted rows **with** an `id` are updated in place.
- Rows **without** an `id` are inserted.
- Rows present in the DB but absent from the submission are candidates for
  deletion — but only after a safety check.

**Safety check:** a purchase line linked to a production
(`produzioni_materie_prime`), or a sales line linked to a return
(`bolle_reso`), **cannot be deleted**. The whole update returns a 422 with a
clear Italian message. The entire operation runs inside `DB::transaction()`.

The frontend sends `id: r.id ?? null` per row so existing rows keep their IDs.

---

## 6. Recipe enforcement at production time (GAP-D3)

If the selected `scheda_produzione` defines a recipe (`ricette` /
`ricette_marinature`), the production run's ingredients are cross-checked
against the allowed ingredient set. Ingredients not in the recipe are rejected
with a 422 listing their names. If the scheda has **no** recipe defined, the
check is skipped (flexible production runs are allowed).

---

## 7. Scheda versioning (GAP-D7)

Creating a new **active** production sheet for a product automatically
deactivates all previously active revisions for that same product, guaranteeing
a single active recipe per product. Old revisions remain readable/printable but
are marked "Archiviata".

---

## 8. Audit trail (created_by / updated_by)

The `Auditable` trait (`app/Concerns/Auditable.php`) auto-populates `created_by`
on insert and `updated_by` on insert/update using the authenticated user id.

Tables carrying audit columns (migration `2026_06_23_000002`): `acquisti`,
`vendite`, `produzioni`, `bolle_reso`, `note_credito`,
`lotti_imballaggi_primari`, `lotti_detergenti`.

> Child/pivot tables (`acquisti_righe`, `vendite_righe`,
> `produzioni_materie_prime`) do **not** carry audit columns. There is currently
> **no UI** to browse the audit trail — the data exists on the row but is not
> surfaced. Both are candidates for the platform roadmap (see `ROADMAP.md`).

---

## 9. Packaging & detergent traceability (GAP-D1)

A production run links the specific primary-packaging (MOCA) lots and
detergent/sanitiser lots used, via `produzioni_imballaggi_primari` and
`produzioni_detergenti`. Both use `ON DELETE RESTRICT` toward the lot tables so
a lot that has been used in production cannot be deleted, and `ON DELETE
CASCADE` toward the production so removing a production cleans up its links.

---

## 10. Scheduled jobs

Defined in `routes/console.php`, run by the scheduler loop in `docker/start.sh`:

| Command | Schedule | Purpose |
|---|---|---|
| `haccp:alert-scadenze` | daily 07:00 | Emails admins lots expiring ≤30 days, lots already expired still in stock, and supplier HACCP certificates expiring ≤60 days. Requires `MAIL_*`. |
| `db:backup` | daily 03:00 | `pg_dump | gzip` into `storage/backups/`, prunes files older than 14 days. **Storage is inside the container** — mount a durable volume or copy off-site (see `ROADMAP.md`). |
