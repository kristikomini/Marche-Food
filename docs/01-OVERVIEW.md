# 01 — Panoramica / Overview

**Marche International Food S.r.l. — Sistema di Tracciabilità HACCP**

> Documentazione consolidata. Questo file e i successivi (`02`–`10`) sostituiscono la
> vecchia serie di documenti sparsi (BLUEPRINT, ARCHITECTURE, MODULES, WORKFLOWS,
> GAPS, ROADMAP, CHANGELOG-*, REFORM-*, ecc.), riassumendone i contenuti aggiornati.

---

## 1. Cos'è

Applicazione gestionale web che digitalizza la **tracciabilità alimentare HACCP** di
Marche International Food S.r.l. (Tolentino, MC — trasformazione/confezionamento di
prodotti ittici e gastronomia: acciughe, salmone marinato, insalata russa, ecc.).

Sostituisce i precedenti fogli Excel e i moduli cartacei con un'unica applicazione
che copre l'intera filiera: **acquisto materie prime → produzione → vendita**, con
tracciabilità bidirezionale dei lotti richiesta dalla normativa (Reg. CE 178/2002,
Reg. UE 1169/2011 per gli allergeni).

## 2. Obiettivi del sistema

- **Tracciabilità bidirezionale** dei lotti (forward: fornitore→cliente; backward:
  prodotto venduto→fornitori) per gestire richiami (recall) rapidi.
- **Conformità documentale**: riprodurre fedelmente i moduli cartacei ufficiali —
  Scheda di Produzione (mod. M2PO3), Fattura immediata / DdT, etichette lotto.
- **Controllo di gestione**: giacenze di magazzino, bilanci lotto, report periodici.
- **Sicurezza dei dati**: soft-delete con cestino, audit log, optimistic locking, 2FA.
- **Usabilità in reparto**: modalità Kiosk su tablet per la registrazione produzione.

## 3. Capacità principali (mappa rapida)

| Area | Funzioni |
|------|----------|
| **Anagrafica** | Fornitori (+ certificati HACCP con estrazione AI), Clienti, Prodotti + varianti/pezzature, Materie Prime (+ allergeni), Unità di Misura, Flussi, Destinazione Ingredienti |
| **Screen 1 — Alimenti** | Acquisti (DDT/Fatture fornitori) con lotti, Vendite (Fattura/DdT con PDF), Bolle Reso, Note di Credito |
| **Screen 2 — Imballaggi** | Lotti imballaggi primari (MOCA), detergenti, gas — certificati |
| **Screen 3 — Produzione** | Schede di Produzione (ricette, ciclo, imballaggi/gas template), Produzioni (cattura confezioni, lotti gas, ciclo di lavoro, metal detector, semilavorati), Confronto schede, Kiosk |
| **Conformità** | Tracciabilità lotti, Recall workflow, Allergeni UE 1169/2011, Etichette QR |
| **Reportistica** | Report gestionale (PDF/CSV), Magazzino/giacenze, PDF documenti, Dashboard |
| **Sicurezza** | Ruoli admin/operatore, Audit log, Cestino (soft-delete), 2FA, Notifiche |
| **Dati** | Import storico CSV, Export CSV per ogni entità |

Dettaglio funzionale in `04`–`08`; sicurezza in `09`; sviluppo/deploy in `10`.

## 4. Stack in breve

- **Backend**: Laravel 13 (PHP 8.4), monolite Inertia (nessuna API REST separata).
- **Frontend**: Vue 3 + Inertia.js v3, componenti PrimeVue (preset Aura personalizzato
  "Marche"), Tailwind CSS 4, font **Inter** (self-hosted).
- **PDF**: `barryvdh/laravel-dompdf` (dompdf 3.1) con viste Blade.
- **DB**: PostgreSQL in produzione; SQLite in-memory nei test.
- **Deploy**: Docker (Apache + mod_php) su Hetzner + Coolify.

Dettaglio in `02-ARCHITECTURE.md`.

## 5. Glossario dei termini di dominio (IT)

| Termine | Significato |
|---------|-------------|
| **Anagrafica** | Dati anagrafici / master data (fornitori, clienti, prodotti…) |
| **Acquisto** | Documento di ricezione merce da fornitore (DDT/Fattura), con righe/lotti |
| **Vendita** | Documento di vendita a cliente (DDT, Fattura immediata, Nota Credito) |
| **Riga** | Riga di documento (un lotto/prodotto in un acquisto o vendita) |
| **Lotto** | Identificativo di rintracciabilità di un blocco di merce |
| **Lotto esterno** | Lotto del fornitore (diverso dal lotto interno) |
| **Materia prima** | Ingrediente in ingresso (es. acciughe salate, olio) |
| **Scheda di Produzione** | Modulo/ricetta HACCP (mod. M2PO3) per un prodotto |
| **Produzione** | Singolo run di lavorazione basato su una scheda |
| **Ciclo di lavoro** | Fasi di lavorazione (prelievo, preparazione, confezionamento…) |
| **Semilavorato** | Lotto interno prodotto e riutilizzabile in produzioni successive |
| **Confezione / pezzatura** | Formato del prodotto finito (es. gr 200, kg 1) |
| **Metal detector** | Registrazione test rilevamento metalli (Campione 1/2/3, OK/KO) |
| **Bolla di reso** | Documento di reso merce dal cliente |
| **Nota di credito** | Documento contabile di storno |
| **Conto terzi** | Lavorazione per conto di terzi (toll processing) |
| **Cestino** | Area di ripristino dei record soft-deleted |
| **Giacenza / magazzino** | Bilancio disponibile per lotto (ricevuto − consumato − venduto) |

## 6. Indice della documentazione

| # | File | Contenuto |
|---|------|-----------|
| 01 | `01-OVERVIEW.md` | Questa panoramica: cos'è, obiettivi, stack, glossario |
| 02 | `02-ARCHITECTURE.md` | Architettura, layer, build pipeline, convenzioni |
| 03 | `03-DATA-MODEL.md` | Modello dati: tabelle, relazioni, vincoli, indici |
| 04 | `04-ANAGRAFICA.md` | Master data: fornitori, clienti, prodotti, materie prime |
| 05 | `05-ALIMENTI-ACQUISTI-VENDITE.md` | Acquisti, vendite, bolle reso, note credito, imballaggi |
| 06 | `06-PRODUZIONE.md` | Schede, produzioni, ciclo, metal detector, kiosk, confronto |
| 07 | `07-TRACCIABILITA-CONFORMITA.md` | Tracciabilità, recall, allergeni, ricerca, bilanci lotto |
| 08 | `08-REPORTISTICA-DOCUMENTI.md` | Report, PDF, etichette QR, export, magazzino, import |
| 09 | `09-SICUREZZA-DATI-INTEGRITA.md` | Ruoli, auth/2FA, audit, cestino, locking, notifiche |
| 10 | `10-SVILUPPO-DEPLOY.md` | Setup, test, build, deploy, design system, storia, gap |
