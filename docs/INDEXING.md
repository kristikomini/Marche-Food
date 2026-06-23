# INDEXING.md
## Marche International Food S.R.L. — Indexing Strategy

Database: **PostgreSQL 18**

---

## 1. Strategy

The schema serves two distinct query workloads:

**Operational CRUD** — paginated list views with filters by date range, supplier, customer, or document number. These queries always filter on a single table with at most one join for the display label (e.g., `acquisti JOIN fornitori`). Date-range filters on `data_documento` and free-text `ilike` searches on lot numbers are the dominant patterns.

**Traceability queries** — the `TracciabilitaController::search()` method executes deep eager-load chains across 4–6 tables to reconstruct forward and reverse lot traces. These are infrequent (user-initiated search) but span the widest join graph in the application. The critical path is: `acquisti_righe.lotto` → `produzioni_materie_prime.acquisto_riga_id` → `produzioni.id` → `schede_produzione` → `prodotti`.

The guiding principles are:

1. **Index every FK used in a JOIN** — PostgreSQL does not auto-create FK indexes. All FK columns that participate in `WITH` eager loads or explicit joins are indexed.
2. **Index every column used in a WHERE filter** — both date filters and lot number searches appear in user-facing list views and the traceability engine.
3. **Lot numbers get their own dedicated indexes** — `lotto` and `lotto_esterno` in both `acquisti_righe` and `vendite_righe` are the primary traceability keys. Searches use `ilike` with a `%term%` pattern, which cannot use a standard B-tree index for the leading wildcard. For small-to-medium data volumes (< 500k rows) the current B-tree indexes on these columns still eliminate most rows at the planner level; at scale, a `pg_trgm` GIN index would be more appropriate (see GAPS.md).
4. **No over-indexing on write-heavy paths** — `produzioni_materie_prime` receives a burst of inserts on every production save. It only has two indexes (on `produzione_id` and `acquisto_riga_id`), not a composite, because the two foreign keys are queried independently in eager loads.

---

## 2. Index Inventory

### Primary Key Indexes (implicit, created by `BIGSERIAL PRIMARY KEY`)

| Table | Index |
|---|---|
| `users` | `users_pkey` |
| `unita_misura` | `unita_misura_pkey` |
| `flussi_produzione` | `flussi_produzione_pkey` |
| `fornitori` | `fornitori_pkey` |
| `clienti` | `clienti_pkey` |
| `prodotti` | `prodotti_pkey` |
| `materie_prime` | `materie_prime_pkey` |
| `destinazione_ingredienti` | `destinazione_ingredienti_pkey` |
| `acquisti` | `acquisti_pkey` |
| `acquisti_righe` | `acquisti_righe_pkey` |
| `vendite` | `vendite_pkey` |
| `vendite_righe` | `vendite_righe_pkey` |
| `bolle_reso` | `bolle_reso_pkey` |
| `note_credito` | `note_credito_pkey` |
| `lotti_imballaggi_primari` | `lotti_imballaggi_primari_pkey` |
| `lotti_detergenti` | `lotti_detergenti_pkey` |
| `schede_produzione` | `schede_produzione_pkey` |
| `schede_produzione_flussi` | `schede_produzione_flussi_pkey` |
| `ricette` | `ricette_pkey` |
| `ricette_marinature` | `ricette_marinature_pkey` |
| `produzioni` | `produzioni_pkey` |
| `produzioni_materie_prime` | `produzioni_materie_prime_pkey` |

### Unique Indexes (implicit, created by `UNIQUE` constraints)

| Table | Column(s) | Purpose |
|---|---|---|
| `users` | `email` | Login lookup; prevents duplicate accounts |
| `unita_misura` | `codice` | Business code deduplication |
| `fornitori` | `codice` | Supplier code used by CSV import lookup |
| `clienti` | `codice_cliente` | Customer code used by CSV import lookup |
| `prodotti` | `codice_prodotto` | Product business key |
| `materie_prime` | `codice` | Raw material numeric code |
| `destinazione_ingredienti` | `(prodotto_id, materia_prima_id)` | Prevents duplicate ingredient→product mappings |
| `schede_produzione` | `(prodotto_id, revisione)` | Prevents duplicate scheda revisions |
| `produzioni` | `lotto_produzione` | Globally unique production lot identifier |

### Explicit Indexes (declared in `schema.sql`)

| Index Name | Table | Column | Query Pattern |
|---|---|---|---|
| `idx_acquisti_fornitore` | `acquisti` | `fornitore_id` | FK join in list view; filter by supplier |
| `idx_acquisti_data` | `acquisti` | `data_documento` | Date-range filter on acquisti list view |
| `idx_acquisti_righe_lotto` | `acquisti_righe` | `lotto` | Traceability search: `ilike %term%` on internal lot |
| `idx_acquisti_righe_lotto_est` | `acquisti_righe` | `lotto_esterno` | Traceability search: `ilike %term%` on supplier lot |
| `idx_acquisti_righe_data_in` | `acquisti_righe` | `data_in` | Dashboard expiry query: `WHERE scadenza BETWEEN ? AND ?` and `data_out IS NULL` |
| `idx_vendite_cliente` | `vendite` | `cliente_id` | FK join in list view; filter by customer |
| `idx_vendite_data` | `vendite` | `data_documento` | Date-range filter on vendite list view |
| `idx_vendite_righe_lotto` | `vendite_righe` | `lotto` | Forward traceability: find sales by lot |
| `idx_prodotti_codice` | `prodotti` | `codice_prodotto` | Lookup by business code (forms + search) |
| `idx_produzioni_scheda` | `produzioni` | `scheda_id` | FK join: produzioni → schede_produzione |
| `idx_produzioni_lotto` | `produzioni` | `lotto_produzione` | Traceability search by production lot |
| `idx_prod_mp_produzione` | `produzioni_materie_prime` | `produzione_id` | Eager load: all ingredients for a production |
| `idx_prod_mp_acquisto` | `produzioni_materie_prime` | `acquisto_riga_id` | Reverse trace: all productions using a purchase lot |

---

## 3. Previously Missing Indexes — Now Applied ✅

All 13 previously missing FK indexes plus `idx_vendite_righe_lotto_ext` were created by migration `2026_06_23_000001_add_missing_fk_indexes`.

| Index Name | Table | Column |
|---|---|---|
| `idx_acquisti_righe_acquisto` | `acquisti_righe` | `acquisto_id` |
| `idx_vendite_righe_vendita` | `vendite_righe` | `vendita_id` |
| `idx_bolle_reso_vendita_riga` | `bolle_reso` | `vendita_riga_id` |
| `idx_note_credito_vendita` | `note_credito` | `vendita_id` |
| `idx_note_credito_bolla` | `note_credito` | `bolla_reso_id` |
| `idx_schede_flussi_scheda` | `schede_produzione_flussi` | `scheda_id` |
| `idx_schede_flussi_flusso` | `schede_produzione_flussi` | `flusso_id` |
| `idx_ricette_scheda` | `ricette` | `scheda_id` |
| `idx_ricette_mp` | `ricette` | `materia_prima_id` |
| `idx_ricette_mar_scheda` | `ricette_marinature` | `scheda_id` |
| `idx_prod_mp_materia` | `produzioni_materie_prime` | `materia_prima_id` |
| `idx_imb_primari_fornitore` | `lotti_imballaggi_primari` | `fornitore_id` |
| `idx_detergenti_fornitore` | `lotti_detergenti` | `fornitore_id` |
| `idx_vendite_righe_lotto_ext` | `vendite_righe` | `lotto_esterno` |

---

## 4. Composite Index Rationale

No composite indexes are currently defined. The following would be beneficial at scale:

### `acquisti_righe(acquisto_id, data_in)` — Recommended

**Why**: The dashboard expiry query filters `acquisti_righe WHERE scadenza BETWEEN ? AND ? AND data_out IS NULL`. Combined with the eager load pattern that fetches righe by `acquisto_id`, a composite covering index would allow index-only scans on the most frequent traceability traversal.

**Left-prefix rule**: `acquisto_id` is placed first because it is always present in the eager-load query (`WHERE acquisto_id = ?`). `data_in` is placed second because it is used in range filters applied after the FK filter.

### `acquisti_righe(lotto, data_in)` — Recommended for traceability at scale

**Why**: The `TracciabilitaController` searches by `lotto ilike %term%` and then orders by `data_in DESC`. A composite `(lotto text_pattern_ops, data_in)` would allow the index to sort results without a filesort once the `pg_trgm` GIN index handles the text search.

### `vendite_righe(vendita_id, prodotto_id)` — Nice to have

**Why**: Sale line eager loads always filter by `vendita_id`. Adding `prodotto_id` as a second column supports index-only scans when the form only needs product IDs without fetching the full row.
