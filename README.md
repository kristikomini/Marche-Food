# Marche International Food — Sistema Tracciabilità HACCP

Sistema gestionale web per la tracciabilità alimentare conforme HACCP di **Marche International Food S.R.L.** Sostituisce i fogli Excel con un'applicazione Laravel + Vue 3.

## Funzionalità

| Area | Funzione |
|------|----------|
| **Screen 1 — Alimenti** | Acquisti (DDT/Fatture fornitori) con lotti, Vendite clienti, Bolle Reso, Note di Credito |
| **Screen 2 — Imballaggi** | Lotti imballaggi primari (MOCA) e detergenti certificati |
| **Screen 3 — Produzione** | Schede di produzione con **varianti/pezzature** prodotto, ricette, imballaggi/gas template, Flussi HACCP; Produzioni con cattura di N° confezioni, lotti gas, ciclo di lavoro (registrazioni + controllo) e test metal detector; **PDF scheda vuota** (template) e **compilata** (data-driven) fedeli al modulo cartaceo |
| **Anagrafica** | Fornitori, Clienti, Prodotti, Materie Prime (con **allergeni** UE 1169/2011), Destinazione Ingredienti |
| **Conformità** | Tracciabilità bidirezionale lotti, Recall workflow, Log attività (audit), Etichette lotto con QR (produzioni, acquisti, vendite), Allergeni derivati sui lotti di produzione |
| **Sicurezza dati** | Soft-delete con **Cestino** (ripristino / eliminazione definitiva), Optimistic locking, 2FA (admin), Notifiche in-app |
| **Import** | Migrazione dati storici via CSV |

## Requisiti

- PHP 8.4+ (il `composer.json` dichiara `^8.3`, ma le dipendenze bloccate nel `composer.lock` richiedono PHP ≥ 8.4.1 — vedi `vendor/composer/platform_check.php`; il Docker di produzione usa `php:8.4-apache`)
- PostgreSQL 16+ (produzione: PostgreSQL 18)
- Node.js 20+ (build Docker: Node 22)
- Composer 2

## Installazione (locale)

```bash
# 1. Installa dipendenze PHP e JS
composer install
npm install

# 2. Configura l'ambiente
cp .env.example .env
php artisan key:generate

# 3. Configura il database in .env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=marche_food
DB_USERNAME=postgres
DB_PASSWORD=la_tua_password

# Per sviluppo locale imposta anche:
APP_ENV=local
LOG_CHANNEL=stack
LOG_LEVEL=debug

# 4. Esegui le migrazioni
php artisan migrate

# 5. (Opzionale) Carica i dati di esempio
php artisan db:seed --class=ClienteSeeder
php artisan db:seed --class=Screen3Seeder

# 6. Compila gli asset frontend
npm run build

# 7. Avvia il server
php artisan serve
```

Aprire il browser su `http://localhost:8000`.

## Sviluppo

```bash
# Avvia tutti i processi (server, queue worker, log tailing, Vite hot-reload)
composer run dev

# Oppure solo build frontend
npm run build
```

> **Nota Windows:** `npm run dev` (hot-reload) può essere instabile su Windows. Usare `npm run build` + `Ctrl+Shift+R` nel browser in caso di problemi.

## Test

```bash
# Esegui tutta la suite (usa SQLite in-memory, non serve DB Postgres)
php artisan test

# Solo una categoria
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit
```

La suite copre autenticazione, controllo degli accessi (operatore vs admin), dashboard, CRUD acquisti e produzioni, e i CSV export.

## Deploy con Docker / Coolify

Il `Dockerfile` usa un build multi-stage: dipendenze Composer installate senza dev-packages, asset Vite compilati nel layer di build, copiati in `public/build/`.

Al boot, `docker/start.sh`:
1. Esegue `migrate --force`
2. Avvia il **Laravel Scheduler** in background (ogni minuto)
3. Avvia il **queue worker** in background (`queue:work --tries=3 --max-time=3600`)
4. Lancia Apache in foreground

**Variabili d'ambiente obbligatorie in produzione:**

| Variabile | Valore atteso |
|-----------|---------------|
| `APP_ENV` | `production` |
| `APP_KEY` | generata con `php artisan key:generate` |
| `APP_DEBUG` | `false` |
| `LOG_CHANNEL` | `stderr` |
| `LOG_LEVEL` | `warning` |
| `DB_CONNECTION` | `pgsql` |
| `DB_HOST` | host del database |
| `DB_DATABASE` | nome del database |
| `DB_USERNAME` | utente del database |
| `DB_PASSWORD` | password del database |
| `SESSION_ENCRYPT` | `true` |

## Import Dati Storici

Dalla sezione **Utilità → Import Dati Storici** è possibile caricare i dati degli anni precedenti via CSV.

1. Scaricare il **template CSV** dalla pagina Import
2. Compilarlo rispettando il formato (separatore `;`, date `DD/MM/YYYY`)
3. Caricare il file e cliccare **Importa**

**Formato acquisti:**
```
fornitore_codice;numero_documento;data_documento;tipo_documento;nome_prodotto;quantita_kg;quantita_pz;lotto;lotto_esterno;scadenza;data_in;note_documento
```

**Formato vendite:**
```
cliente_codice;numero_documento;data_documento;tipo_documento;nome_prodotto;pezzatura_gr;quantita_kg;quantita_pz;lotto;lotto_esterno;scadenza;note_documento
```

## Tracciabilità HACCP

Il sistema supporta la tracciabilità bidirezionale dei lotti:

- **Forward:** lotto acquistato → produzione → vendita → cliente
- **Backward:** prodotto venduto → produzione → lotti materie prime → fornitori

Implementata tramite `produzioni_materie_prime`, che collega ogni run di produzione ai lotti specifici di `acquisti_righe` utilizzati.

## Ruoli Utente

| Ruolo | Accesso |
|-------|---------|
| `admin` | Accesso completo: CRUD anagrafica, eliminazione record operativi, gestione utenti, import |
| `operator` | Creazione e modifica acquisti, vendite, produzioni, imballaggi; lettura anagrafica |

La gestione utenti (creazione, modifica password, cambio ruolo) è accessibile solo agli admin da **Impostazioni → Utenti**.

## Stack Tecnico

- **Backend:** Laravel 13 (13.16) + PHP 8.4
- **Frontend:** Vue 3 + Inertia.js v3 (no API separata)
- **UI:** PrimeVue (tema Aura)
- **Database:** PostgreSQL
- **Build:** Vite
- **Container:** Docker (Apache + mod_php)

Per lo schema SQL completo vedere [schema.sql](schema.sql).

## Documentazione

| Documento | Contenuto |
|-----------|-----------|
| [docs/BLUEPRINT.md](docs/BLUEPRINT.md) | **Panoramica completa del sistema**: cos'è, stack, architettura, pattern, modello dati, tutte le funzionalità |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Stack, architettura, flussi di richiesta |
| [docs/DATABASE.md](docs/DATABASE.md) | ERD e descrizione tabelle |
| [docs/MODULES.md](docs/MODULES.md) | Mappa e descrizione dei moduli |
| [docs/API.md](docs/API.md) | Mappa rotte e formato richieste |
| [docs/WORKFLOWS.md](docs/WORKFLOWS.md) | Regole di business: tracciabilità, bilanci lotti, semilavorati, conto terzi, audit |
| [docs/INDEXING.md](docs/INDEXING.md) | Strategia di indicizzazione |
| [docs/INTEGRATIONS.md](docs/INTEGRATIONS.md) | Integrazioni e import CSV |
| [docs/DEPLOY.md](docs/DEPLOY.md) | Deploy su Hetzner + Coolify, health check, backup, hardening |
| [docs/GAPS.md](docs/GAPS.md) | 21 gap storici risolti (giugno 2026) |
| [docs/ROADMAP.md](docs/ROADMAP.md) | Gap analysis deployment/piattaforma e piano a fasi |
| [docs/CHANGELOG-2026-07-01.md](docs/CHANGELOG-2026-07-01.md) | Modifiche della sessione di hardening (luglio 2026) |
| [docs/CHANGELOG-2026-07-06.md](docs/CHANGELOG-2026-07-06.md) | Soft-delete/Cestino, etichette QR acquisti/vendite, allergeni, test |
| [docs/REFORM-PLAN-2026-07-08.md](docs/REFORM-PLAN-2026-07-08.md) | **Piano di riforma** scheda/fattura: stato reale, gap analysis, piano a fasi, avanzamento |
| [docs/CHANGELOG-2026-07-08.md](docs/CHANGELOG-2026-07-08.md) | **Riforma 2026-07-08**: varianti prodotto, scheda vuota+compilata, catalogo gas, cattura produzione, fattura, export Excel |
| [docs/fornitori.md](docs/fornitori.md) | Approfondimento sul modulo Fornitori |
