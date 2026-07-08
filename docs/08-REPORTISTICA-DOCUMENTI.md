# 08 — Reportistica, Documenti & Import

Output del sistema: report gestionali, documenti PDF, etichette QR, export/import CSV,
dashboard e giacenze.

Controller: `ReportController`, `DashboardController`, `MagazzinoController`,
`ImportController`. Service: `ReportService`. Viste PDF in `resources/views/pdf/`,
etichette in `resources/views/labels/`.

---

## 1. Dashboard

`DashboardController` (`GET /`): home con KPI e stato del sistema (documenti recenti,
scadenze imminenti, contatori). Le statistiche sono **cacheable** (GAP-T7) per non
ricalcolarle ad ogni load.

## 2. Report gestionale

`ReportController` + `ReportService`:

- `GET /report` (vista con filtri periodo da/a), `GET /report/pdf`, `GET /report/csv`.
- Aggregati: totali acquisti/vendite/produzioni (documenti e kg), per fornitore e per
  cliente. Il CSV usa separatore `;` e BOM UTF-8.

## 3. Catalogo documenti PDF (dompdf)

| Documento | Rotta | Vista Blade |
|-----------|-------|-------------|
| Fattura immediata / DdT vendita | `/vendite/{vendita}/pdf` | `pdf/vendita.blade.php` |
| Scheda di Produzione compilata | `/produzioni/{produzione}/scheda` | `pdf/scheda-produzione.blade.php` |
| Scheda vuota (template) | `/schede/{scheda}/pdf` | `pdf/scheda-produzione-vuota.blade.php` |
| Registro di Lavorazione | `/produzioni/{produzione}/pdf` | `pdf/produzione.blade.php` |
| Acquisto | `/acquisti/{acquisto}/pdf` | `pdf/acquisto.blade.php` |
| Scheda cliente | `/clienti/{cliente}/scheda` | `pdf/cliente.blade.php` |
| Report gestionale | `/report/pdf` | `pdf/report.blade.php` |

**Note di implementazione PDF** (importanti per manutenzione):
- dompdf 3.1 ha supporto **flexbox incompleto**: i layout complessi (fattura, scheda)
  usano **tabelle Blade** e `border-spacing`, non `display:flex`.
- Il logo richiede **GD** per rasterizzare il PNG trasparente; se GD manca, il logo è
  saltato per non generare un 500 (guardia `extension_loaded('gd')`).
- Font PDF: DejaVu Sans / DejaVu Sans Mono (glifi accentati garantiti).
- La Scheda di Produzione è tarata per stare su **una pagina A4** (dompdf rende le righe
  più alte del browser: row-count e altezze verificati sul PDF reale, non solo in preview).

## 4. Etichette lotto con QR

Viste in `resources/views/labels/` (`lotti.blade.php`, `produzione.blade.php`).
Generano etichette con **QR code** che rimandano alla pagina di tracciabilità del lotto:

- `GET /produzioni/{produzione}/etichetta`
- `GET /acquisti/{acquisto}/etichette`
- `GET /vendite/{vendita}/etichette`

Il parametro `copie` (clampato) controlla il numero di etichette. Coperte da `EtichetteTest`.

## 5. Export CSV (per entità)

`acquisti.export`, `vendite.export`, `produzioni.export`, `fornitori.export`,
`clienti.export`, `magazzino.export`. Formato: separatore `;`, BOM UTF-8, date DD/MM/YYYY.

## 6. Import dati storici (CSV)

`ImportController` (admin-only, sezione **Utilità → Import**):

- `GET /import`, `POST /import/acquisti`, `POST /import/vendite`,
  `GET /import/template-acquisti`, `GET /import/template-vendite`.
- **Transazionale** (GAP-T2): l'import fallisce atomicamente in caso di errore su una riga.
- Formati (separatore `;`, date `DD/MM/YYYY`):
  - **Acquisti**: `fornitore_codice;numero_documento;data_documento;tipo_documento;nome_prodotto;quantita_kg;quantita_pz;lotto;lotto_esterno;scadenza;data_in;note_documento`
  - **Vendite**: `cliente_codice;numero_documento;data_documento;tipo_documento;nome_prodotto;pezzatura_gr;quantita_kg;quantita_pz;lotto;lotto_esterno;scadenza;note_documento`
- Coperto da `ImportHttpTest`.

## 7. Integrazione AI (certificati fornitore)

`CertificateExtractionService` (Anthropic Claude): estrae i dati dei certificati HACCP
fornitore per precompilare il form (vedi `04`). Richiede chiave API configurata; senza
configurazione ritorna 422 (test `CertificateExtractionTest`). È l'unica integrazione
esterna a runtime oltre all'invio email.
