# 07 — Tracciabilità & Conformità

Cuore normativo del sistema: rintracciabilità bidirezionale dei lotti, gestione richiami,
allergeni e bilanci di magazzino.

Controller: `TracciabilitaController`, `SearchController`, `RecallController`,
`MagazzinoController`. Service: `InventoryService`, `AllergenService`, `SearchService`.

---

## 1. Tracciabilità bidirezionale dei lotti (core HACCP)

- **Forward** (a valle): lotto acquistato → produzione → vendita → cliente.
- **Backward** (a monte): prodotto venduto → produzione → lotti materie prime → fornitori.

Implementata tramite `produzioni_materie_prime` (run ↔ `acquisti_righe`/`lotti_semilavorati`)
e `vendite_righe.produzione_id` / `.acquisto_riga_id` (produzione/acquisto ↔ vendita).
Include i **semilavorati**: un lotto interno consumato a valle mantiene la catena.

- Rotte: `GET /tracciabilita`, `GET /tracciabilita/search`.
- Risultati **paginati** (GAP-T5) per reggere volumi elevati.

## 2. Ricerca globale

`SearchController` + `SearchService` (`GET /cerca`): ricerca cross-entità (lotti,
documenti, anagrafiche) da un unico campo nella topbar. Utile per partire da un lotto
sospetto e risalire/discendere la filiera.

## 3. Recall (richiamo) — workflow con stato

`RecallController` gestisce un **workflow stateful** di richiamo:

- `GET /recall` (report/lista), `POST /recall` (apertura), `GET /recall/{recall}` (dettaglio),
  `PUT /recall/{recall}/stato` (avanzamento stato), `POST /recall/{recall}/notifiche/{notifica}`
  (marca cliente notificato).
- Dato un lotto compromesso, il sistema individua i clienti impattati (via tracciabilità
  forward) e traccia lo stato delle notifiche di richiamo (`recalls`, `recall_notifiche`).

## 4. Allergeni (Reg. UE 1169/2011)

`AllergenService` deriva gli allergeni di un lotto di produzione dagli allergeni delle
**materie prime** consumate:

- Distingue **"Contiene"** (presenza diretta) da **"Può contenere"** (tracce/cross-contact).
- Gli allergeni derivati compaiono su etichette e PDF di produzione.
- Il campo allergeni è definito sulle materie prime (`materie_prime.allergeni`) e sui
  lotti in ingresso (`acquisti_righe`). Coperto dai test `AllergenTest`,
  `PurchaseLotAllergenTest`.

## 5. Bilanci lotto & giacenze di magazzino

`InventoryService` + `MagazzinoController` (`GET /magazzino`, `/export`):

- **Saldo lotto acquistato** = ricevuto − consumato in produzioni − venduto direttamente.
- **Saldo semilavorato** = prodotto − consumato a valle.
- I record **soft-deleted sono esclusi** dai bilanci (`whereNull('deleted_at')`).
- Gli stessi calcoli alimentano l'enforcement in produzione (vedi `06` §2a) e la vista
  giacenze di magazzino con export CSV.

## 6. Scadenze & alert

- Le scadenze dei lotti e dei **certificati fornitore** generano avvisi.
  Soglie configurabili in `config/haccp.php` (`alert_giorni_lotti` default 30,
  `alert_giorni_certificati` default 60) e via env (`HACCP_ALERT_GIORNI_*`).
- Digest email giornaliero agli admin + destinatari extra (`HACCP_ALERT_EMAILS`),
  inviato dallo scheduler (vedi `10`). Notifiche in-app correlate (vedi `09`).
