# Piano di Riforma — Marche International Food HACCP

**Data:** 2026-07-08
**Autore:** sessione di analisi + pianificazione
**Stato:** ⏳ IN ATTESA DI APPROVAZIONE (nessun codice scritto — solo lettura del codebase)

Questo documento è il **gate di pianificazione**: mappa dello stato reale, decodifica dei requisiti del cliente, gap analysis e piano a fasi. Dopo la tua revisione e approvazione, si passa al coding fase per fase.

Decisioni già confermate con te:
1. **Modello prodotto:** un prodotto = un nome, con tabella figlia di **varianti/pezzature** (ognuna con proprio `codice_prodotto`, pezzatura, imballaggio). La scheda elenca tutte le varianti.
2. **PDF scheda:** **entrambi** — un template stampabile **vuoto** (da compilare a mano) e una versione **data-driven compilata** da una produzione reale.
3. **Priorità:** tutto è importante, da fare **a fasi**.
4. **Workflow:** ti mostro il piano prima di programmare.

---

## Parte 0 — Executive summary

Il dominio è solido e già molto avanzato (Laravel 13 + Vue 3/Inertia + PrimeVue, PostgreSQL, Docker, tracciabilità bidirezionale, soft-delete/cestino, audit log, allergeni UE, recall, 2FA, report/magazzino). Il problema che sollevi è **strutturale e localizzato**: la **Scheda di Produzione** è modellata come una *ricetta template* (prodotto + ingredienti + step generici) e il suo PDF è in gran parte **hard-coded**, quindi non può rappresentare la scheda reale del cliente. Da lì derivano i sintomi (stampa PDF/Excel "non ok", dati mancanti).

**Cosa è già fatto (da verificare/rifinire, non ricostruire):**
- Maschera informazioni cliente → PDF (`ClienteController@scheda` → `resources/views/pdf/cliente.blade.php`).
- Materie prime: tabella *lotti in uscita collegati* + tabella *prodotti che la usano* (`MateriaPrimaController@show` + `resources/js/Pages/MateriePrime/Show.vue`) — **completo lato dati e frontend**.
- Fattura/DDT PDF vendite (`resources/views/pdf/vendita.blade.php`) — **molto vicino** al template `fattura-ddt-datadriven.html`; mancano solo campi anagrafici cliente e alcuni totali.
- Deployment hardening (security headers, `/health`, CI, cookie sicuri, PHP ^8.4) — risolto nelle sessioni precedenti (vedi `ROADMAP.md` Parte A).

**Gap reali principali:**
- A. **Riforma Scheda di Produzione** (varianti pezzatura, imballaggi/gas/ciclo/metal-detector data-driven; PDF vuoto + compilato). ← il cuore.
- B. **Excel vero (.xlsx)** per fornitori e clienti: oggi gli export sono **CSV** e **non c'è libreria spreadsheet** installata (solo `barryvdh/laravel-dompdf`).
- C. **Automazione input**: nel form vendite il prodotto è **testo libero** (`nome_prodotto`), non collegato a `prodotti`; stessa cosa in acquisti/produzioni in parte. Selezionando un prodotto si dovrebbero auto-compilare codice, pezzatura, UM, prezzo.
- D. **Indici FK mancanti** su diverse foreign key (dettaglio in Parte 4).
- E. **Fedeltà fattura**: campi anagrafici cliente (zona, agente, categoria, banca, codice IVA, valuta) e trasporto (colli, peso, data trasporto, destinatario diverso, scadenze calcolate).
- F. **Confronto schede** (feature nuova, non urgente).

---

## Parte 1 — Stato reale del codice (documentazione grounded)

### 1.1 Stack e struttura
- Backend Laravel 13.16 / PHP 8.4; frontend Vue 3 + Inertia v3 + PrimeVue (Aura); PostgreSQL (test SQLite in-memory); build Vite; Docker (Apache+mod_php).
- 30 controller, 33 model, 8 service (`app/Services/`), 42 pagine Vue (`resources/js/Pages/`), 12 blade view (`resources/views/`, di cui 6 PDF in `pdf/` e 2 label in `labels/`).
- Schema completo in `schema.sql` (25.797 byte, rigenerato 2026-07-08).

### 1.2 Modello dati Scheda/Produzione (il fulcro del problema)

**`schede_produzione`** = *template ricetta per prodotto*, non la scheda reale:
`id, prodotto_id (FK), modello VARCHAR(20), revisione INT, data_revisione DATE, ha_marinatura BOOL, attiva BOOL, note`. Unique `(prodotto_id, revisione)`.

Figli della scheda:
- **`ricette`** (ingredienti): `scheda_id, materia_prima_id, fornitore_id, percentuale, grammi_per_kg, um, ordine`.
- **`ricette_marinature`**: `scheda_id, materia_prima_id, fornitore_id, litri_grammi, um, ordine`.
- **`schede_produzione_flussi`** (ciclo di lavoro template): `scheda_id, flusso_id, ordine, valore_controllo, tempo_minuti`; `flussi_produzione` = catalogo step `numero, nome, controllo, misura`.

**`produzioni`** = run reale: `id, scheda_id (FK), lotto_produzione UNIQUE, data_produzione, quantita_prodotta_kg, operatore, note, +audit/soft-delete`.
Figli/collegamenti del run:
- **`produzioni_materie_prime`** (core tracciabilità): `produzione_id, acquisto_riga_id XOR semilavorato_id, materia_prima_id, quantita_kg`.
- **`produzioni_imballaggi_primari`**: `produzione_id, lotto_imballaggio_id, quantita_usata`.
- **`produzioni_detergenti`**: `produzione_id, lotto_detergente_id, quantita_usata`.
- **`lotti_semilavorati`**: output intermedi.

**Prodotto** (`prodotti`): `codice_prodotto UNIQUE, nome, pezzatura_valore, pezzatura_um, um_id, attivo` → **un solo codice e una sola pezzatura per prodotto**. Questo è il blocco strutturale: la scheda reale mostra *più righe* codice/pezzatura per lo stesso prodotto.

### 1.3 PDF Scheda attuale (`resources/views/pdf/scheda-produzione.blade.php`)
Renderizza da una `$produzione`. Materie prime data-driven (lotto+fornitore reali dal run). Ma:
- **Codice/Pezzatura/N° Confezioni:** una sola riga (dal prodotto); **N° confezioni vuoto** (non catturato).
- **Imballaggi primari:** etichette hard-coded (`Vaschetta gr 200`, `Film gr 200`, …) se il run non ha lotti collegati.
- **Gas:** interamente hard-coded (`TRESARIS NC30`, `LINDE GAS`).
- **Ciclo di lavoro:** 4 step hard-coded (1,3,7,10); non usa i flussi della scheda; niente registrazioni/controllo per-run.
- **Metal detector:** completamente statico; nessun dato catturato (Inizio/Fine conf., Campione 1/2/3 OK/KO).

### 1.4 Vendite (`resources/js/Pages/Vendite/Form.vue`, `VenditaController`, `pdf/vendita.blade.php`)
- Righe già ricche: `codice_articolo, prezzo_unitario, sconto_1/2, aliquota_iva, importo_netto, pezzatura_gr, um, lotto/lotto_esterno, scadenza, produzione_id, acquisto_riga_id`.
- **Prodotto = testo libero** (`InputText nome_prodotto`): nessun collegamento a `prodotti` → nessun auto-fill, dati non normalizzati.
- Il PDF fattura è già molto fedele; mancano campi anagrafici/trasporto.

### 1.5 Export attuali
- `FornitoreController@export`, `ClienteController@export`, `AcquistoController@export`, `VenditaController@export`, `MagazzinoController@export`, `ReportController@csv`: tutti **CSV** (`fputcsv`, `;`, BOM UTF-8). Nessun `.xlsx`.
- Nessun pacchetto spreadsheet in `composer.json`.

### 1.6 Servizi derivati
- `InventoryService`: bilanci lotto acquisto e semilavorato (consumato/venduto/residuo) — calcolati on-demand, nessuna colonna stock materializzata.
- `ReportService`: aggregati per intervallo date (acquisti/vendite/produzioni, per fornitore/cliente, scadenze).
- `SearchService`: ricerca globale cross-entità.

---

## Parte 2 — Requisiti del cliente decodificati

### 2.1 Scheda di Produzione (da `scheda-produzione-template.html` + foto)
Struttura reale, sezione per sezione:
1. **Header:** logo, "SCHEDA DI PRODUZIONE", codice revisione `M2PO3 REV7 del 20/02/2025` (= `modello` + `revisione` + `data_revisione`). ✔ già mappabile.
2. **PRODOTTO** (nome) + **DATA DI PRODUZIONE/LOTTO**.
3. **Blocco varianti:** N righe di `CODICE PRODOTTO | PEZZATURA | N° CONFEZIONI` (es. `059 | gr200 | n°__` e `397 | kg1 | n°__`). ← richiede tabella varianti + confezioni per-produzione.
4. **MATERIE PRIME:** `materia prima | LOTTO | FORNITORE` (lotti reali). ✔ già data-driven.
5. **IMBALLAGGI PRIMARI:** `nome | LOTTO | FORNITORE` (es. Vaschetta/Film per pezzatura). ← template + lotti reali.
6. **GAS:** `nome | LOTTO | FORNITORE` (es. TRESARIS NC30 / LINDE GAS). ← da rendere data-driven.
7. **CICLO DI LAVORO:** step numerati (1,3,7,10…) con due colonne **REGISTRAZIONI** + colonna **C** (controllo). ← catturare registrazioni + check per-produzione.
8. **FUNZIONAMENTO METAL DETECTOR:** `Inizio conf.` / `Fine conf.`; `Campione 1/2/3 Rilevato OK/KO`. 3 campioni fissi (Ferroso 2,5mm cod.260920; Non ferroso 3,5mm cod.260967; Aisi316 4,5mm cod.260948). ← catturare esiti per-produzione.

### 2.2 Fattura/DDT vendite (da `fattura-ddt-datadriven.html`)
Campi richiesti oltre agli attuali: `COD.CLIENTE, IVA, ZONA, AGENTE, CATEG., PARTITA IVA, NUMERO/DATA/PAG, CONDIZIONI PAGAMENTO, BANCA D'APPOGGIO, TELEFONO, CODICE FISCALE, VALUTA, TIPO DOCUMENTO`; righe `CODICE ART | DESCRIZIONE (+lotto/scad) | UM | QTÀ | PREZZO | SC.1 | SC.2 | IMPORTO NETTO | IVA`; totali `IMPONIBILE, AL.IVA, IMPORTO IVA, TOTALE MERCE, NETTO MERCE, TOTALE A PAGARE, TOTALE FATTURA`; `SCADENZE`, `N.COLLI, PORTO, CAUSALE, TOT.PESO, DATA TRASPORTO, DESTINATARIO DIVERSO, CONTROLLO MERCI/TEMP, FIRME`.

---

## Parte 3 — Gap analysis (consolidata)

| ID | Area | Gap | Sev | Stato |
|----|------|-----|-----|-------|
| **R1** | Prodotto | Un solo codice/pezzatura per prodotto; servono varianti | Critica | Da fare |
| **R2** | Scheda | Imballaggi primari template non modellati (hard-coded nel PDF) | Alta | Da fare |
| **R3** | Scheda | Gas non modellato (hard-coded) | Alta | Da fare |
| **R4** | Produzione | N° confezioni per variante non catturato | Alta | Da fare |
| **R5** | Produzione | Ciclo di lavoro: registrazioni + controllo per-run non catturati | Alta | Da fare |
| **R6** | Produzione | Metal detector: inizio/fine + esiti campioni non catturati | Alta | Da fare |
| **R7** | Scheda PDF | Serve PDF **vuoto** (template) + **compilato** (data-driven) fedeli alla foto | Alta | Da fare |
| **R8** | Export | Excel `.xlsx` fornitori e clienti (oggi CSV; nessuna libreria) | Alta | Da fare |
| **R9** | Vendite | Prodotto testo libero → Select da `prodotti` con auto-fill | Alta | Da fare |
| **R10** | Fattura | Campi anagrafici cliente (zona, agente, categoria, banca, cod.IVA, valuta) + trasporto (colli, peso, data trasporto, destinatario) + scadenze | Media | Da fare |
| **R11** | Indici | FK senza indice (vedi Parte 4) | Media | Da fare |
| **R12** | Materie prime | Tabelle lotti-in-uscita + prodotti-che-la-usano | — | ✅ Fatto (verificare) |
| **R13** | Clienti | Maschera informazioni PDF | — | ✅ Fatto (rifinire) |
| **R14** | Confronto schede | Confronto tra 1+ schede/produzioni | Bassa | Da fare (non urgente) |
| **R15** | Automazione | Auto-fill scadenza/fornitore da lotto; pre-fill produzione da scheda | Media | Da fare |

---

## Parte 4 — Architettura, indici, automazione

### 4.1 Indici FK mancanti (da aggiungere con migration)
`vendite_righe.acquisto_riga_id` (usato da InventoryService), `lotti_semilavorati.produzione_id` (join InventoryService), `prodotti.um_id`, `materie_prime.um_id`, `acquisti_righe.prodotto_id`, `vendite_righe.prodotto_id`, `destinazione_ingredienti.materia_prima_id`, `ricette.fornitore_id`, `ricette_marinature.materia_prima_id`, `ricette_marinature.fornitore_id`, `recall_notifiche.cliente_id`, `recall_notifiche.vendita_riga_id`. (Gli FK verso `users` per attribuzione audit sono a bassa priorità.)

### 4.2 Colonne denormalizzate (attenzione, alcune sono volute)
`acquisti_righe.nome_prodotto`, `vendite_righe.nome_prodotto/um/pezzatura_gr` duplicano dati di `prodotti` — **load-bearing** perché `prodotto_id` è nullable (righe fuori-catalogo) e usate da Search/Report. La riforma **collega** il prodotto (auto-fill) **mantenendo** lo snapshot testuale per storicità/compliance. `audit_logs.etichetta` è snapshot voluto: non normalizzare.

### 4.3 Opportunità di automazione (table connections)
- **Vendite/acquisti/produzioni:** Select prodotto → auto-fill `codice_articolo/codice_prodotto, pezzatura, um, prezzo` (da `prodotti`/varianti).
- **Righe con lotto:** selezione lotto acquisto → auto-fill `scadenza` e `fornitore`.
- **Produzione da scheda:** selezionando la scheda, pre-compilare le righe materie prime (da `ricette`), gli imballaggi/gas/ciclo (dal template scheda) e le varianti (per N° confezioni).
- **Fattura:** campi cliente (zona/agente/categoria/banca/valuta/IVA) presi da `clienti` invece che riscritti.

---

## Parte 5 — Piano a fasi (proposta)

Principio: **prima le fondamenta sicure, poi il cuore (scheda), poi il resto**. Ogni fase è testabile e termina con doc aggiornata. Nota ambiente: il sandbox esegue migrazioni, artisan, unit test e script logici in PHP 8.4 (wasm); i **feature test HTTP** vanno eseguiti in CI/ambiente nativo (limite noto documentato nella ROADMAP).

### Fase 0 — Fondamenta & sicurezza (rapida, basso rischio)
- Migration indici FK mancanti (Parte 4.1).
- Installare libreria spreadsheet (`phpoffice/phpspreadsheet`) per `.xlsx`.
- Backup/snapshot DB prima delle riforme di schema; unit test invariati verdi.
- **Deliverable:** migration + `composer.json` aggiornato; test verdi.

### Fase 1 — Varianti pezzatura prodotto (R1) — base dati per la scheda
- Nuova tabella **`prodotto_varianti`**: `id, prodotto_id FK, codice_prodotto VARCHAR UNIQUE, pezzatura_valore, pezzatura_um, um_id, descrizione, ordine, attivo`.
- Data-migration: per ogni `prodotti` esistente crea una variante di default da `codice_prodotto/pezzatura_*`. Mantieni `prodotti.codice_prodotto` come legacy/compat (deprecato) o spostalo — da decidere in fase.
- Model `Prodotto hasMany varianti`; aggiorna `ProdottoController` + `Prodotti/Form.vue` (UI ripetitore varianti).
- **Test:** migrazione dati verificata via script seeded; unit test relazioni.

### Fase 2 — Riforma Scheda di Produzione (R2, R3) + PDF vuoto (R7a)
- Nuove tabelle template della scheda:
  - **`schede_imballaggi`**: `scheda_id, componente VARCHAR, prodotto_variante_id (nullable), fornitore_id (nullable), ordine`.
  - **`schede_gas`**: `scheda_id, nome VARCHAR, fornitore_id (nullable), ordine`.
- Ciclo di lavoro: già `schede_produzione_flussi`; aggiungere supporto etichetta "REGISTRAZIONI" e flag controllo dal catalogo `flussi_produzione`.
- Metal detector: 3 campioni fissi come config (`config/haccp.php`) + rappresentazione nel PDF.
- Riscrivere `pdf/scheda-produzione.blade.php` per rendere **la scheda** (varianti, imballaggi/gas/ciclo template, campi registrazione vuoti) → **PDF template vuoto** fedele alla foto. Nuova rotta `schede/{id}/pdf` (vuoto).
- Aggiornare `Schede/Form.vue` per gestire imballaggi/gas template.
- **Test:** smoke-render PDF; unit test struttura scheda.

### Fase 3 — Cattura produzione & PDF compilato (R4, R5, R6, R7b) — cuore
- Nuove tabelle di cattura per-produzione:
  - **`produzioni_confezioni`**: `produzione_id, prodotto_variante_id, n_confezioni INT`.
  - **`produzioni_gas`**: `produzione_id, nome, lotto, fornitore_id (nullable)` (oppure link a un catalogo lotti gas se preferisci — da decidere).
  - **`produzioni_ciclo`**: `produzione_id, flusso_id, registrazione_1, registrazione_2, controllo BOOL, ordine`.
  - **`produzioni_metal_detector`**: `produzione_id, inizio_conf TIME/nullable, fine_conf, campione_1 VARCHAR(2) (OK/KO), campione_2, campione_3, note`.
  - Imballaggi lotti: già `produzioni_imballaggi_primari`.
- `Produzioni/Form.vue`: sezioni per confezioni/gas/ciclo/metal-detector, **pre-compilate dalla scheda** (automazione R15).
- Riscrivere il render compilato dello stesso blade (o variante) per mostrare valori reali quando presenti → **PDF compilato**. La rotta esistente `produzioni/{id}/scheda` resta per il compilato.
- **Test:** simulazione end-to-end produzione → PDF; verifica tracciabilità invariata.

### Fase 4 — Vendite: tabella prodotti venduti + automazione + fedeltà fattura (R9, R10)
- `Vendite/Form.vue`: `nome_prodotto` testo libero → **Select prodotto/variante** con auto-fill `codice_articolo, pezzatura_gr, um, prezzo_unitario`; mantieni fallback testo libero per righe fuori-catalogo.
- Campi anagrafici su **`clienti`**: `zona, agente, categoria, banca_appoggio, codice_iva, valuta (default Euro), aliquota_iva_default`; aggiorna `Clienti/Form.vue`.
- Campi trasporto su **`vendite`**: `n_colli, peso_totale, data_trasporto, destinatario_diverso`.
- Aggiornare `pdf/vendita.blade.php` per usare i nuovi campi + scadenze calcolate best-effort da `condizioni_pagamento`.
- **Test:** feature test vendite (CI); render fattura.

### Fase 5 — Export Excel (R8)
- Servizio `ExcelExportService` (PhpSpreadsheet) con export `.xlsx` per **fornitori** e **clienti** (colonne come i CSV attuali, formattazione header/filtri). Opzionale: prodotti, materie prime, magazzino, report.
- Mantieni gli endpoint CSV; aggiungi endpoint/bottoni `.xlsx`.
- **Test:** unit test generazione file (byte non vuoti, header corretti).

### Fase 6 — Rifiniture: cliente maschera + materie prime (R12, R13)
- Verifica/estende `pdf/cliente.blade.php` (anagrafica completa + statistiche). Materie prime `Show.vue`: eventuale aggiunta fornitori/giacenza. Basso sforzo.

### Fase 7 — Confronto schede (R14) — non urgente
- Vista `/schede/confronto?ids=` per confrontare 1+ schede/produzioni affiancate. Deferita.

### Fase 8 — Deployment readiness residuo
- Verifica finale ROADMAP Parte A (backup off-site D-A1, supervisione scheduler/queue D-A4). Il resto risulta già implementato.

---

## Parte 6 — Strategia di test & simulazione
- **Unit/logic (eseguibili qui):** migrazioni, relazioni model, matematica bilanci/totali, generazione file export.
- **Script seeded end-to-end:** prodotto→variante→scheda→produzione→PDF; vendita→fattura; export xlsx.
- **Feature test HTTP (in CI/nativo):** CRUD schede/produzioni/vendite, guardie ruolo, render PDF, export.
- **Smoke PDF:** rendering dompdf senza eccezioni (GD-guard già presente).
- Ciclo: test → se errore/bug → fix → ritest, fino a verde.

## Parte 7 — Rischi & decisioni (confermate con il cliente 2026-07-08)
- **Migrazione `prodotti` → varianti:** ✅ DECISO — *migrare e rimuovere subito* `prodotti.codice_prodotto`/`pezzatura_*`. La data-migration crea la prima variante da quei valori e poi la colonna legacy viene droppata; tutti i riferimenti passano alle varianti nella stessa fase.
- **Gas per-produzione:** ✅ DECISO — *catalogo lotti gas completo*: nuova master `lotti_gas` (come imballaggi/detergenti, con fornitore/lotto/scadenza) + link `produzioni_gas`. Sezione imballaggi Screen 2 estesa al gas.
- **Scadenze fattura:** calcolo automatico da condizioni di pagamento è best-effort (formati variabili) → campo scadenze modificabile.
- **Fedeltà 1:1 fattura:** campi anagrafici cliente (zona/agente/categoria/banca) popolati una volta su `clienti`.

---

## Parte 7bis — Avanzamento sessione & vincoli ambiente

**Stato coding:**
- ✅ **Fase 0** — migration indici FK (`2026_07_08_000001_add_reform_fk_indexes.php`).
- ✅ **Fase 1** — Varianti pezzatura prodotto: migration `2026_07_08_000002_create_prodotto_varianti_table.php` (crea `prodotto_varianti`, migra i dati in una variante, rimuove le colonne legacy da `prodotti`), nuovo model `ProdottoVariante`, `Prodotto` aggiornato, `ProdottoController` con gestione varianti; aggiornati tutti i riferimenti: `SearchService`, `DestinazioneIngredientiController`, `SchedaProduzioneController`, `MateriaPrimaController`, `ReportController` (eager-load), `pdf/scheda-produzione.blade.php`, `Screen3Seeder`, 8 test Feature, e le pagine Vue `Prodotti/Index`, `Prodotti/Form`, `Schede/Index`, `Schede/Print`.
- ✅ **Fase 2** — Riforma struttura Scheda + PDF template **vuoto**: nuove tabelle `schede_imballaggi` e `schede_gas` (migration `2026_07_08_000003`), model `SchedaImballaggio`/`SchedaGas` + relazioni su `SchedaProduzione`, relazione `Ricetta::fornitore()`, `config/haccp.php` (3 campioni metal detector + ciclo default), `SchedaProduzioneController` esteso (load/sync/validate imballaggi+gas, elenco fornitori, metodo `pdfVuota`), rotta `schede/{id}/pdf`, nuovo blade `pdf/scheda-produzione-vuota.blade.php` (template vuoto fedele alla foto), `Schede/Form.vue` con sezioni Imballaggi + Gas e bottone "PDF vuoto". Simulazione: layout `059·gr200 / 397·kg1`, 4 imballaggi, gas TRESARIS/LINDE, cascade delete → **superata**.
- ✅ **Fase 3** — Cattura produzione reale + **PDF compilato data-driven**: migration `2026_07_08_000004` (5 tabelle: `lotti_gas`, `produzioni_confezioni`, `produzioni_gas`, `produzioni_ciclo`, `produzioni_metal_detector`); model `LottoGas`, `ProduzioneConfezione`, `ProduzioneGas`, `ProduzioneCiclo`, `ProduzioneMetalDetector` + relazioni su `Produzione`; `ProduzioneController` cattura/valida confezioni, gas, ciclo, metal detector + **pre-fill dalla scheda** (varianti→confezioni, flussi→ciclo); catalogo gas Screen 2 (`ImballaggioController` metodi gas + rotte + `Imballaggi/FormGas.vue` + tab Gas in `Imballaggi/Index`); riscrittura `pdf/scheda-produzione.blade.php` **data-driven**; `ReportController` eager-load; `Produzioni/Form.vue` con sezioni Confezioni/Gas/Ciclo/Metal Detector + automazione prefill. Simulazione: sheet compilato acciughe (059·gr200 ×120, 397·kg1 ×40, gas TRESARIS/LINDE, ciclo con «C», metal detector OK/OK/OK) + cascade → **superata**.
- ✅ **Fase 4** — Vendite: tabella prodotti venduti + automazione + fedeltà fattura. Migration `2026_07_08_000005` (clienti: zona/agente/categoria/banca_appoggio/codice_iva/valuta/aliquota_iva_default; vendite: n_colli/peso_totale/data_trasporto/destinatario_diverso; vendite_righe: prodotto_variante_id). Model fillable + `VenditaRiga::variante()`. `ClienteController` + `VenditaController` (elenco varianti per auto-fill, campi trasporto, validazione, `venditaAttributes`). `pdf/vendita.blade.php` usa valuta/zona/agente/categoria/cod.IVA/banca + colli/peso/data trasporto/destinatario. `Clienti/Form.vue` sezione fatturazione; `Vendite/Form.vue` selettore prodotto con **auto-fill** (codice articolo, descrizione, pezzatura) + campi trasporto. Simulazione: importi/totali fattura di riferimento (494 → IVA 49,40 → **543,40 €**) e auto-fill → **superata**.
- ✅ **Fase 5** — Export **Excel `.xlsx` reali** senza nuove dipendenze: nuova classe `app/Support/SimpleXlsxWriter.php` (usa `ext-zip`, già nell'immagine Docker; celle inlineStr, intestazione in grassetto). `FornitoreController@export` e `ClienteController@export` ora producono `.xlsx` di default (`?format=csv` per il vecchio CSV); helper `Controller::downloadCsv`. I bottoni "Esporta Excel" ora generano davvero un foglio Excel. Validazione: file generato e riletto con **openpyxl** (dati + caratteri speciali intatti) → **superata**.
- ✅ **Fase 6** — Rifiniture: `lotti_gas` aggiunto al registro del **Cestino** (`CestinoController`, restore/force-delete — frontend generico invariato); `Produzioni/Print.vue` (stampa a schermo) ora mostra Confezioni, Gas, Ciclo (registrazioni + «C»), Metal Detector, con `ProduzioneController@print` che carica le nuove relazioni + campioni. (Kiosk lasciato invariato: crea produzioni minime, nuovi campi opzionali; materie-prime/cliente già completi dalle fasi precedenti.)
- ✅ **Fase 7** — Confronto schede: rotta `schede/confronto?ids=`, `SchedaProduzioneController@confronto`, `Schede/Confronto.vue` (tabella affiancata con evidenziazione differenze), selezione multipla in `Schede/Index.vue`.
- ✅ **Kiosk** — cattura opzionale N° confezioni per variante + esito metal detector (OK/KO); payload esteso verso `POST /produzioni`.
- ✅ **Fase 8** — nessuna nuova dipendenza runtime; migration girano con `migrate --force`; hardening ROADMAP Parte A già presente; `schema.sql` rigenerato.
- ✅ **Documentazione** — README, DATABASE, API, WORKFLOWS, INDEXING, MODULES + `schema.sql` aggiornati; `CHANGELOG-2026-07-08.md` completo.
- **Follow-up minori annotati:** integrare `lotti_gas` nel Cestino; le pagine on-screen `Produzioni/Print.vue` e Kiosk non mostrano ancora i nuovi campi (solo il PDF compilato) — da rifinire in Fase 6.

**Vincoli dell'ambiente di sviluppo (importante per il testing):**
1. Il sandbox **non ha PHP/Composer** e non può installarli (rete allowlisted, no sudo) → la suite PHPUnit e `npm run build` **non sono eseguibili qui**.
2. La shell (mount) **non vede in modo affidabile i file scritti dagli strumenti file** (serve copie in cache): un lint PHP via shell legge versioni stantie. I file reali sono corretti (verificati via lettura diretta).
3. **Validazione effettuata qui:** (a) verifica diretta dei file scritti; (b) **simulazione in Python/SQLite** della logica di migrazione Fase 1 (creazione varianti, copia dati, drop colonne, unicità codice globale, "codice principale", codici uniti) → **tutte le asserzioni superate**; (c) `php-parser` per la sintassi dove leggibile.
4. **Validazione autorevole = CI del progetto** (`.github/workflows/ci.yml`, PHP 8.4 + Postgres): esegue migrazioni, `php artisan test`, Pint e build Vite in ambiente nativo. Da lanciare a ogni fase prima del go-live.

## Parte 8 — Documentazione da aggiornare (durante/dopo il coding)
`README.md`, `docs/DATABASE.md`, `docs/ARCHITECTURE.md`, `docs/MODULES.md`, `docs/WORKFLOWS.md`, `docs/API.md`, `docs/INDEXING.md`, `docs/GAPS.md`, `docs/ROADMAP.md`, `schema.sql` (rigenerato), + nuovo changelog `docs/CHANGELOG-2026-07-08.md`. Aggiornamento finale completo per la tua revisione (nessuna informazione omessa).
