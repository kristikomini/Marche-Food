# 04 — Anagrafica (Master Data)

Dati di base condivisi da tutti i moduli operativi. **Regola di accesso**: l'indice
(lettura) è aperto a tutti gli utenti autenticati; **creazione/modifica/eliminazione
sono admin-only** (middleware `admin`). Ogni entità ha export CSV.

Controller: `FornitoreController`, `ClienteController`, `ProdottoController`,
`MateriaPrimaController`, `DestinazioneIngredientiController`, `FlussoProduzioneController`,
`CertificatoController`. Pagine Vue in `resources/js/Pages/{Fornitori,Clienti,Prodotti,MateriePrime,DestinazioneIngredienti,Flussi}`.

---

## 1. Fornitori

Gestione fornitori con dati del **certificato HACCP/sanitario** e relativa scadenza
(usata per gli alert di scadenza — vedi `08`).

- Rotte: `GET /fornitori` (index), CRUD admin (`resource … except show,index`),
  `GET /fornitori/export` (CSV), `POST /fornitori/estrai-certificato` (AI, admin).
- **Estrazione AI del certificato** (`CertificatoController` + `CertificateExtractionService`):
  l'admin carica il certificato del fornitore e il sistema estrae automaticamente i
  campi (numero, ente, scadenza) tramite Anthropic Claude, precompilando il form.
  Richiede la configurazione della chiave API (vedi `10`/env). Se non configurato →
  errore 422 esplicito (coperto da test `CertificateExtractionTest`).
- Relazioni: un fornitore ha molti acquisti e molti lotti (imballaggi/detergenti/gas).
- Approfondimento storico: il vecchio `docs/fornitori.md` è confluito qui.

## 2. Clienti

Anagrafica clienti con i campi necessari alla **Fattura/DdT** (vedi `05`):
`codice_cliente`, `ragione_sociale`, `piva`, `indirizzo`, `zona`, `agente`,
`categoria`, `banca_appoggio`, `codice_iva`, `valuta`, `aliquota_iva_default`.

- Rotte: `GET /clienti`, CRUD admin, `GET /clienti/export`, `GET /clienti/{cliente}/scheda`
  (PDF "maschera cliente").
- Nota PDF: l'indirizzo è un unico campo `indirizzo`; il PDF Fattura lo scompone in
  via / CAP-città / provincia parsando il formato `"VIA … - CAP CITTÀ (PR)"`.

## 3. Prodotti e Varianti/Pezzature

Un **prodotto** (es. "Acciughe salate in olio") ha una o più **varianti/pezzature**
(`prodotto_varianti`), ciascuna con `codice_prodotto` e pezzatura (es. `059 · gr 200`,
`397 · kg 1`). `pezzatura_label` è un accessor calcolato da `pezzatura_valore` + `um`.

- Le varianti guidano l'auto-fill delle righe vendita e la sezione "N° Confezioni"
  della Scheda di Produzione.
- Rotte: `GET /prodotti`, CRUD admin.

## 4. Materie Prime (+ Allergeni)

Ingredienti in ingresso, con tracciamento **allergeni** secondo Reg. UE 1169/2011
(campo allergeni su `materie_prime`, aggiunto 2026-07-06).

- Gli allergeni delle materie prime si propagano ai lotti di produzione tramite
  `AllergenService` (derivazione "Contiene" / "Può contenere") — vedi `07`.
- Rotte: `GET /materie-prime` (index), CRUD admin,
  `GET /materie-prime/{materiePrime}` (scheda dettaglio: lotti in uscita + prodotti
  collegati — registrata dopo la resource per non collidere con create/edit),
  `GET /materie-prime/export`.

## 5. Destinazione Ingredienti

Mappatura materia prima → destinazione d'uso. Rotte ridotte: `GET` index +
`store`/`destroy` (admin).

## 6. Unità di Misura & Flussi di Lavorazione

- **Unità di Misura** (`unita_misura`): tabella di supporto (KG, N., ecc.).
- **Flussi di Lavorazione** (`flussi_produzione`): configurazione admin dei passi del
  ciclo di lavoro (numero, nome, flag controllo). Rotte `resource flussi`
  (`index/store/update/destroy`, admin-only). I flussi alimentano il pre-fill del
  ciclo nelle schede e nelle produzioni; i default sono anche in `config/haccp.php`
  (`ciclo_lavoro_default`: 1 Prelievo prodotti, 3 Preparazione ingred.+additivi,
  7 Porzionatura e confezionamento, 10 Immagaz./pallet/spedizione).

## 7. Riepilogo accessi (anagrafica)

| Azione | Operatore | Admin |
|--------|:--------:|:-----:|
| Visualizzare elenchi | ✅ | ✅ |
| Creare / modificare / eliminare | ❌ | ✅ |
| Estrazione AI certificato | ❌ | ✅ |
| Export CSV | ✅ (via link) | ✅ |
