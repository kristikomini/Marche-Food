# CHANGELOG — Riforma Scheda di Produzione & Fattura (2026-07-08)

Sessione di riforma completa guidata da `docs/REFORM-PLAN-2026-07-08.md`.
Obiettivo: allineare struttura dati, frontend e stampe (PDF/Excel) alla realtà
del cliente (foto e HTML in `docs from the client/`), automatizzare l'input via
collegamenti tra tabelle, e completare gli export.

**Ambiente/testing:** il sandbox di sviluppo non dispone di PHP/Composer e la
shell non legge in modo affidabile i file scritti dagli strumenti editor. La
validazione qui è stata fatta con: (a) **simulazioni Python/SQLite** della logica
di schema e delle stampe, (b) **php-parser** (sintassi sui file nuovi, 0 errori),
(c) **openpyxl** (validazione del file Excel generato). La **validazione
autorevole** resta la CI del progetto (`.github/workflows/ci.yml`, PHP 8.4 +
Postgres): esegue migrazioni, `php artisan test`, Pint e `npm run build`.
Prima del go-live: `php artisan migrate:fresh --seed && php artisan test` + `npm run build`.

---

## Fase 0 — Fondamenta

- **Migration** `2026_07_08_000001_add_reform_fk_indexes.php`: 11 indici FK mancanti
  (`vendite_righe.acquisto_riga_id`, `lotti_semilavorati.produzione_id`,
  `materie_prime.um_id`, `acquisti_righe.prodotto_id`, `vendite_righe.prodotto_id`,
  `destinazione_ingredienti.materia_prima_id`, `ricette.fornitore_id`,
  `ricette_marinature.materia_prima_id/fornitore_id`,
  `recall_notifiche.cliente_id/vendita_riga_id`). Idempotenti (CREATE INDEX IF NOT EXISTS).

## Fase 1 — Varianti pezzatura prodotto (breaking change)

Il modello prodotto passa da "un codice + una pezzatura" a **un prodotto con N
varianti** (ognuna con proprio `codice_prodotto` e pezzatura), come nella scheda
reale (es. *Acciughe salate in olio* → `059 · gr 200` e `397 · kg 1`).

- **Migration** `2026_07_08_000002_create_prodotto_varianti_table.php`:
  - crea `prodotto_varianti (id, prodotto_id, codice_prodotto UNIQUE, pezzatura_valore,
    pezzatura_um, um_id, descrizione, ordine, attiva, timestamps)`;
  - **data-migration**: crea una variante di default per ogni prodotto esistente;
  - **rimuove** da `prodotti` le colonne legacy `codice_prodotto, pezzatura_valore,
    pezzatura_um, um_id` (drop FK/indice prima su PostgreSQL). `down()` ripristina.
- **Model**: nuovo `App\Models\ProdottoVariante` (con accessor `pezzatura_label`);
  `Prodotto` aggiornato (`varianti()` hasMany, accessor `codice_principale`, fillable ridotto).
- **Riferimenti aggiornati** (sweep completo): `ProdottoController` (gestione varianti
  + unicità codice), `SearchService`, `DestinazioneIngredientiController`,
  `SchedaProduzioneController`, `MateriaPrimaController`, `ReportController` (eager-load),
  `Screen3Seeder`, `pdf/scheda-produzione.blade.php`, e le pagine Vue
  `Prodotti/Index`, `Prodotti/Form` (ripetitore varianti), `Schede/Index`, `Schede/Print`.
- **Test** aggiornati: rimosso `codice_prodotto` dalle create di `Prodotto` in 8 test Feature.

## Fase 2 — Struttura scheda + PDF template VUOTO

- **Migration** `2026_07_08_000003_create_schede_imballaggi_e_gas_tables.php`:
  - `schede_imballaggi (scheda_id, componente, prodotto_variante_id?, fornitore_id?, ordine)`;
  - `schede_gas (scheda_id, nome, fornitore_id?, ordine)`.
- **Model**: `SchedaImballaggio`, `SchedaGas`; relazioni `SchedaProduzione::imballaggi()/gas()`;
  relazione `Ricetta::fornitore()`.
- **config/haccp.php**: `metal_detector_campioni` (3 campioni fissi: Ferroso 2,5mm/260920,
  Non ferroso 3,5mm/260967, Aisi316 4,5mm/260948) e `ciclo_lavoro_default`.
- **SchedaProduzioneController**: load/sync/validate imballaggi+gas, elenco `fornitori`,
  nuovo metodo `pdfVuota()`.
- **Rotta**: `GET schede/{schede}/pdf` → `schede.pdf` (template vuoto).
- **Blade**: nuovo `resources/views/pdf/scheda-produzione-vuota.blade.php` (fedele alla
  foto, campi registrazione vuoti, renderizzato dalla scheda).
- **Vue**: `Schede/Form.vue` con sezioni **Imballaggi** e **Gas** + bottone "PDF vuoto".

## Fase 3 — Cattura produzione + PDF COMPILATO data-driven

- **Migration** `2026_07_08_000004_create_produzione_capture_tables.php` (5 tabelle):
  - `lotti_gas` — **catalogo gas Screen 2** (come imballaggi/detergenti: fornitore,
    componente, lotto, scadenza, data_in/out, audit, soft-delete);
  - `produzioni_confezioni (produzione_id, prodotto_variante_id, n_confezioni)`;
  - `produzioni_gas (produzione_id, lotto_gas_id, quantita_usata, note)`;
  - `produzioni_ciclo (produzione_id, flusso_id?, nome, registrazione_1, registrazione_2, controllo, ordine)`;
  - `produzioni_metal_detector (produzione_id UNIQUE, inizio_conf, fine_conf, campione_1/2/3, note)`.
- **Model**: `LottoGas`, `ProduzioneConfezione`, `ProduzioneGas`, `ProduzioneCiclo`,
  `ProduzioneMetalDetector`; relazioni su `Produzione` (`confezioni/gas/ciclo/metalDetector`).
- **ProduzioneController**: cattura e validazione dei 4 nuovi gruppi; `create/edit`
  espongono varianti, lotti gas e campioni; **pre-fill dalla scheda** (varianti→confezioni,
  flussi→ciclo).
- **Catalogo gas (Screen 2)**: `ImballaggioController` metodi `createGas/storeGas/editGas/
  updateGas/destroyGas` + `fornitoriGas/validateGas`; rotte `imballaggi/gas/*`; nuova
  `Imballaggi/FormGas.vue` + tab "Gas" in `Imballaggi/Index.vue`.
- **Blade**: `pdf/scheda-produzione.blade.php` riscritto **data-driven** (confezioni per
  variante, imballaggi/gas reali, ciclo con registrazioni + «C», metal detector OK/KO;
  fallback al template scheda quando un dato non è compilato).
- **Vue**: `Produzioni/Form.vue` con sezioni Confezioni / Gas / Ciclo / Metal Detector
  + automazione prefill; `ReportController@schedaProduzionePdf` carica le nuove relazioni.

## Fase 4 — Vendite: prodotti venduti + automazione + fedeltà fattura

- **Migration** `2026_07_08_000005_add_fattura_fields.php`:
  - `clienti`: `zona, agente, categoria, banca_appoggio, codice_iva, valuta (default Euro),
    aliquota_iva_default`;
  - `vendite`: `n_colli, peso_totale, data_trasporto, destinatario_diverso`;
  - `vendite_righe`: `prodotto_variante_id` (+ indice; FK applicativa via `exists`).
- **Model**: fillable aggiornati (`Cliente`, `Vendita`, `VenditaRiga`); `VenditaRiga::variante()`.
- **Controller**: `ClienteController` valida i nuovi campi; `VenditaController` fornisce
  l'elenco varianti per l'**auto-fill** delle righe, gestisce i campi trasporto e la
  validazione, helper `venditaAttributes()`.
- **Blade** `pdf/vendita.blade.php`: usa valuta/zona/agente/categoria/cod.IVA/banca del
  cliente + colli/peso/data trasporto/destinatario della vendita.
- **Vue**: `Clienti/Form.vue` sezione "Dati fatturazione"; `Vendite/Form.vue` selettore
  prodotto con auto-fill (codice articolo, descrizione, pezzatura) + campi trasporto.

## Fase 5 — Export Excel `.xlsx` reali

- **Nuova classe** `App\Support\SimpleXlsxWriter` — scrittore XLSX **senza dipendenze**
  (usa `ext-zip`, già nell'immagine Docker; celle inlineStr, intestazione grassetto).
  Nessuna modifica a `composer.json/lock`.
- `FornitoreController@export` e `ClienteController@export` producono `.xlsx` di default
  (`?format=csv` per il vecchio CSV). Helper condiviso `Controller::downloadCsv()`.
- L'export clienti include ora anche i nuovi campi fattura (zona, agente, categoria, banca,
  cod. IVA, valuta).

## Fase 6 — Rifiniture

- `lotti_gas` aggiunto al **Cestino** (`CestinoController`, restore/force-delete).
- `Produzioni/Print.vue` (stampa a schermo) mostra Confezioni, Gas, Ciclo (registrazioni
  + «C»), Metal Detector; `ProduzioneController@print` carica le nuove relazioni + campioni.

## Fase 7 — Confronto schede (IMPLEMENTATA)

- **Rotta** `GET schede/confronto?ids=1,2,3` → `SchedaProduzioneController@confronto` (2–4 schede).
- **Vue** `Schede/Confronto.vue`: tabella affiancata (colonne = schede) con righe
  Revisione, Attiva, Marinatura, Varianti/Pezzature, Ricetta, Imballaggi, Gas, Ciclo;
  le celle **differenti** tra le schede sono evidenziate.
- **Selezione** in `Schede/Index.vue`: checkbox per riga + bottone "Confronta selezionate".

## Kiosk — cattura confezioni + metal detector (IMPLEMENTATA)

`KioskController@index` passa ora `varianti` (per scheda) e `campioni`. `Produzioni/Kiosk.vue`
consente di inserire il **N° confezioni** per variante e l'esito **metal detector** (OK/KO per
campione) nello step di costruzione; il payload verso `POST /produzioni` include `confezioni[]`
e `metal_detector{}` quando valorizzati. Gli altri gruppi (gas, ciclo dettagliato) restano
compilabili dal form produzione completo.

## Fase 8 — Deployment

Nessuna nuova dipendenza runtime (solo `ext-zip`/`ext-gd`, già nel `Dockerfile`). Le 5
nuove migration girano con `migrate --force` al boot. Gli item di hardening di
`ROADMAP.md` Parte A risultano già implementati nelle sessioni precedenti. Rigenerare
`schema.sql` (fatto: vedi sezione "Reform 2026-07-08" in coda al file).

---

## Nuove rotte (riepilogo)

| Metodo | URI | Azione |
|--------|-----|--------|
| GET | `schede/{schede}/pdf` | `SchedaProduzioneController@pdfVuota` (template vuoto) |
| GET | `imballaggi/gas/create` | `ImballaggioController@createGas` |
| POST | `imballaggi/gas` | `storeGas` |
| GET | `imballaggi/gas/{gas}/edit` | `editGas` |
| PUT | `imballaggi/gas/{gas}` | `updateGas` |
| DELETE | `imballaggi/gas/{gas}` | `destroyGas` (admin) |
| GET | `fornitori/export?format=csv|xlsx` | export (xlsx default) |
| GET | `clienti/export?format=csv|xlsx` | export (xlsx default) |

## Follow-up noti (non bloccanti)

- Kiosk produzione: non cattura ancora confezioni/gas/ciclo/metal-detector (opzionali).
- Confronto schede (Fase 7) da implementare quando serve.
- Eventuale colonna denormalizzata `vendite_righe.nome_prodotto/um` resta per righe
  fuori-catalogo (intenzionale).
