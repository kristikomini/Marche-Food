# 05 — Screen 1 (Alimenti) & Screen 2 (Imballaggi)

Documenti food in ingresso/uscita e lotti dei consumabili. **Accesso**: operatore +
admin possono creare/modificare; **solo admin elimina** (soft-delete → Cestino).

Controller: `AcquistoController`, `VenditaController`, `BollaResoController`,
`NotaCreditoController`, `ImballaggioController`.

---

## 1. Acquisti (DDT / Fatture fornitori)

Testata (`acquisti`) + righe (`acquisti_righe`). Ogni riga registra il **lotto** in
ingresso (lotto interno + `lotto_esterno` del fornitore), scadenza, quantità (kg/pz),
`data_in`, e la `materia_prima_id` per collegare il lotto all'anagrafica.

- Rotte: `resource acquisti` (except show/destroy), `DELETE` admin,
  `GET /acquisti/{acquisto}/pdf`, `/print`, `/etichette` (QR), `/export`.
- **Diff-sync in modifica** (GAP-T1/T4): le righe sono aggiornate preservando gli ID;
  rifiuto se una riga è già collegata a una produzione.

## 2. Vendite (DDT / Fattura immediata / Nota Credito)

Testata (`vendite`) + righe (`vendite_righe`) con **pricing** e campi documento.

### Pricing (fonte di verità lato server)
`importo_netto = quantità × prezzo_unitario`, scontato di `sconto_1` e `sconto_2`.
La quantità fatturata sono i **pezzi** se presenti (righe a "N."), altrimenti i **kg**.
Il calcolo è **ricalcolato lato server** in fase di validazione (mai fidarsi del client).
IVA per aliquota; totali imponibile/imposta/totale calcolati in fase di stampa.

### Tipi documento
`DDT` (Documento di Trasporto), `FI` (Fattura immediata DdT), `NC` (Nota di Credito).

### Campi fattura/trasporto (Riforma 2026-07-08)
`condizioni_pagamento`, `causale_trasporto`, `n_colli`, `peso_totale`,
`data_trasporto`, `destinatario_diverso` (oltre ai dati cliente).

### Tracciabilità
Ogni riga può referenziare `produzione_id` e/o `acquisto_riga_id`, chiudendo la
catena produzione/acquisto → vendita → cliente.

### PDF Fattura immediata DdT (`resources/views/pdf/vendita.blade.php`)
Riproduce fedelmente il modulo cartaceo (rif. `docs from the client/fattura-ddt-datadriven.html`):

- Layout costruito con **tabelle Blade** (niente flexbox: dompdf 3.1 ha supporto flex
  incompleto per layout annidati) e box arrotondati con `border-spacing`.
- Testata: logo di progetto (`public/favicon.png`), blocco vendor, ovale sanitario
  (IT G5J07 CE), box destinatario **SPETT.LE** (nome / via / CAP-città + provincia nel
  corner, ottenuti parsando `cliente.indirizzo`).
- Righe articoli con sotto-riga lotto/scadenza; blocco totali (imponibile, IVA, totale
  merce, netto merce, totale a pagare/fattura); scadenze; riga trasporto; controllo
  merci/temperatura; firme; note.
- Font PDF: DejaVu Sans / DejaVu Sans Mono (bundlati con dompdf, glifi accentati).
- Rotte: `GET /vendite/{vendita}/pdf`, `/etichette` (QR), `/export`.

## 3. Bolle di Reso

Reso merce dal cliente, collegato alla riga di vendita (`vendita_riga_id`).
`resource bolle-reso` (except show/destroy) + `DELETE` admin. Vincolo: una riga di
vendita con bolle di reso collegate non può essere eliminata.

## 4. Note di Credito

Documento di storno. **Check DB (GAP-D4)**: esattamente una FK valorizzata (non
entrambe NULL). `resource note-credito` (except show/destroy) + `DELETE` admin.

## 5. Screen 2 — Imballaggi, Detergenti, Gas

Lotti dei materiali a contatto (MOCA) e consumabili, con certificati. Un unico
`ImballaggioController` gestisce tre sotto-entità con rotte dedicate:

| Sotto-entità | Rotte (create/store/edit/update) + destroy (admin) |
|--------------|----------------------------------------------------|
| Primari (`lotti_imballaggi_primari`) | `imballaggi.primari.*` |
| Detergenti (`lotti_detergenti`) | `imballaggi.detergenti.*` |
| Gas (`LottoGas`) | `imballaggi.gas.*` |

- `GET /imballaggi` è l'indice unico; i lotti alimentano le sezioni corrispondenti
  della produzione (imballaggi/gas usati nel run — vedi `06`).

## 6. Export CSV

Ogni modulo espone un export CSV (separatore `;`, BOM UTF-8): `acquisti.export`,
`vendite.export`. Per l'import massivo di dati storici vedere `08`.
