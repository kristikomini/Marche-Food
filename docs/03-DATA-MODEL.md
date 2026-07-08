# 03 — Modello Dati

Schema PostgreSQL evolutivo (46 migrazioni in `database/migrations/`). 38 model Eloquent
in `app/Models/`. Di seguito le tabelle raggruppate per dominio con relazioni e vincoli
chiave. Per il DDL storico completo vedere `schema.sql` (se presente) e le migrazioni.

## 1. Anagrafica (master data)

| Tabella / Model | Campi chiave | Relazioni |
|-----------------|-------------|-----------|
| `unita_misura` / `UnitaMisura` | sigla, descrizione | — |
| `flussi_produzione` / `FlussoProduzione` | numero, nome, controllo | usato da schede/produzioni |
| `fornitori` / `Fornitore` | ragione_sociale, codice, tipo, dati certificato HACCP + scadenza | hasMany acquisti, lotti |
| `clienti` / `Cliente` | codice_cliente, ragione_sociale, piva, indirizzo, zona, agente, categoria, banca_appoggio, codice_iva, valuta | hasMany vendite |
| `prodotti` / `Prodotto` | nome, attivo | hasMany `prodotto_varianti`, schede |
| `prodotto_varianti` / `ProdottoVariante` | codice_prodotto, pezzatura_valore/um, `pezzatura_label` (accessor) | belongsTo prodotto |
| `materie_prime` / `MateriaPrima` | codice, nome, **allergeni** (Reg. UE 1169/2011) | usata in ricette, acquisti, produzioni |
| `destinazione_ingredienti` / `DestinazioneIngrediente` | mappa materia prima → destinazione d'uso | — |

## 2. Screen 1 — Alimenti (documenti food)

| Tabella / Model | Note |
|-----------------|------|
| `acquisti` / `Acquisto` | testata DDT/Fattura fornitore (fornitore_id, numero_documento, data, tipo) |
| `acquisti_righe` / `AcquistoRiga` | riga: nome_prodotto, quantita_kg, lotto, lotto_esterno, scadenza, data_in/out, `materia_prima_id` |
| `vendite` / `Vendita` | testata; **campi fattura**: condizioni_pagamento, causale_trasporto, n_colli, peso_totale, data_trasporto, destinatario_diverso |
| `vendite_righe` / `VenditaRiga` | riga con pricing (prezzo_unitario, sconto_1/2, aliquota_iva, importo_netto), `produzione_id`, `acquisto_riga_id` per tracciabilità |
| `bolle_reso` / `BollaReso` | reso merce, collegato a `vendita_riga_id` |
| `note_credito` / `NotaCredito` | storno; **check constraint**: esattamente una FK valorizzata (GAP-D4) |

## 3. Screen 2 — Imballaggi / consumabili

| Tabella / Model | Note |
|-----------------|------|
| `lotti_imballaggi_primari` / `LottoImballaggioPrimario` | MOCA: componente, lotto, fornitore, numero_ddt, quantita, um, data_in/out |
| `lotti_detergenti` / `LottoDetergente` | detergenti/sanificanti certificati |
| *(gas)* `LottoGas` | bombole gas (TRESARIS…): componente, lotto, fornitore, quantita |

## 4. Screen 3 — Produzione

### Scheda (template / ricetta)
| Tabella / Model | Note |
|-----------------|------|
| `schede_produzione` / `SchedaProduzione` | prodotto_id, modello (es. M2PO3), revisione, data_revisione, attiva, ha_marinatura |
| `schede_produzione_flussi` / `SchedaFlussoProduzione` | passi del ciclo proposti dalla scheda (ordine, flusso) |
| `ricette` / `Ricetta` | ingredienti previsti: materia_prima_id, percentuale, grammi_per_kg, ordine |
| `ricette_marinature` / `RicettaMarinatura` | ingredienti marinatura |
| `schede_imballaggi` / `SchedaImballaggio` | imballaggi template della scheda |
| `schede_gas` / `SchedaGas` | gas template della scheda |

### Produzione (run reale)
| Tabella / Model | Note |
|-----------------|------|
| `produzioni` / `Produzione` | scheda_id, lotto_produzione (**unique**), data_produzione, quantita_prodotta_kg, operatore, note |
| `produzioni_materie_prime` / `ProduzioneMateriaPrima` | consumo: `acquisto_riga_id` **XOR** `semilavorato_id`, materia_prima_id, quantita_kg |
| `lotti_semilavorati` / `LottoSemilavorato` | semilavorato interno (riutilizzabile a valle) |
| `produzioni_imballaggi_primari` / `ProduzioneImballaggioPrimario` | imballaggi usati nel run |
| `produzioni_detergenti` / `ProduzioneDetergente` | detergenti usati |
| `ProduzioneGas` | gas usato (lotto_gas_id) |
| `ProduzioneConfezione` | N° confezioni per variante/pezzatura |
| `ProduzioneCiclo` | ciclo di lavoro compilato (flusso, registrazioni 1/2, controllo, ordine) |
| `ProduzioneMetalDetector` | inizio/fine conf., campione_1/2/3 (OK/KO), note |

## 5. Conformità & sicurezza

| Tabella / Model | Note |
|-----------------|------|
| `recalls` / `Recall`, `RecallNotifica` | workflow di richiamo con stato + notifiche clienti |
| `audit_logs` / `AuditLog` | change log append-only (chi/cosa/quando) |
| `notifications` / `AppNotification`, `NotificationRead` | notifiche in-app |
| `users` / `User` | ruolo (admin/operator), campi 2FA (secret, recovery codes) |

## 6. Vincoli e integrità (highlights)

- **Tracciabilità**: `produzioni_materie_prime` collega ogni run ai lotti specifici di
  `acquisti_righe` (o a `lotti_semilavorati`). `vendite_righe.produzione_id` e
  `.acquisto_riga_id` chiudono la catena produzione→vendita.
- **XOR fonte materia prima**: ogni riga di consumo ha *esattamente* uno tra
  `acquisto_riga_id` e `semilavorato_id` (validato in `ProduzioneController`).
- **Note credito**: check DB — esattamente una FK valorizzata (GAP-D4).
- **Lotto produzione unico**: `produzioni.lotto_produzione` UNIQUE.
- **Soft-delete**: colonna `deleted_at` sulle entità operative (acquisti, vendite,
  bolle_reso, note_credito, produzioni, imballaggi…). I bilanci escludono i record
  soft-deleted (`whereNull('deleted_at')`).
- **Audit columns**: `created_by` / `updated_by` popolate dal trait `Auditable`.

## 7. Strategia di indicizzazione

- PK implicite (`BIGSERIAL`), UNIQUE su codici naturali (lotto_produzione, codici anagrafica).
- **FK index espliciti** su tutte le colonne di join (aggiunti a più riprese:
  `add_missing_fk_indexes`, `add_reform_fk_indexes`) per evitare seq-scan sui join di
  tracciabilità e sui bilanci lotto.
- Index su `vendite_righe.lotto_esterno`, `acquisti_righe(lotto, data_in)` per la ricerca lotti.
- Regola: ogni nuova FK ⇒ index dedicato; ogni query di tracciabilità/bilancio deve
  poggiare su un index (vedi migrazioni `*_indexes`).

## 8. Evoluzione dello schema (tappe)

- **2026-06-18** — schema base (anagrafica, alimenti, imballaggi, schede, produzioni).
- **2026-06-23** — FK index, audit columns, check note_credito, imballaggi produzione,
  semilavorati, conto terzi, `produzione_id`/`acquisto_riga_id` su vendite_righe.
- **2026-07-01** — recall, 2FA, notifiche.
- **2026-07-06** — soft-delete, allergeni su materie_prime, audit_logs, `materia_prima_id`
  su acquisti_righe.
- **2026-07-07 / 07-08 (Riforma)** — pricing vendite, `prodotto_varianti`, schede
  imballaggi/gas, tabelle di cattura produzione, campi fattura DdT.
