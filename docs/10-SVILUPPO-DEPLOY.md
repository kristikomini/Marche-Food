# 10 — Sviluppo, Deploy, Design System & Storia

## 1. Requisiti

- **PHP 8.4+** (le dipendenze in `composer.lock` richiedono ≥ 8.4.1; Docker prod: `php:8.4-apache`).
- **PostgreSQL 16+** (prod: 18).
- **Node.js 20+** (build Docker: Node 22), **Composer 2**.

## 2. Setup locale

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate

# .env — DB
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=marche_food
DB_USERNAME=postgres
DB_PASSWORD=...

php artisan migrate
php artisan db:seed --class=ClienteSeeder     # opzionale
php artisan db:seed --class=Screen3Seeder     # opzionale
npm run build
php artisan serve   # http://localhost:8000
```

## 3. Sviluppo

- `composer run dev` — server + queue worker + log tail + Vite hot-reload insieme.
- `npm run dev` — solo Vite (hot-reload). Su **Windows** può essere instabile: in tal
  caso usare `npm run build` + hard reload (`Ctrl+Shift+R`).
- **Gli asset compilati (`public/build/`) non sono versionati**: dopo modifiche a
  Vue/CSS/JS eseguire `npm run build` (o `dev`) perché abbiano effetto. Le modifiche
  **PHP/Blade** (controller, PDF) hanno effetto immediato senza build.

## 4. Test

```bash
php artisan test                      # suite completa (SQLite in-memory, no Postgres)
php artisan test --testsuite=Feature
php artisan test tests/Feature/XyzTest.php
```

- ~20 test funzionali in `tests/Feature/`: auth, controllo accessi, dashboard, acquisti,
  produzioni + bilanci, allergeni, cestino, audit, tracciabilità, import, etichette,
  utenti, estrazione certificati.
- **Baseline nota**: alcuni test che ricostruiscono lo schema su SQLite falliscono con
  `error … after drop column "codice_prodotto"` — è una **limitazione di SQLite** sul
  `DROP COLUMN` con index (non un bug applicativo). Su PostgreSQL non si presenta.
  Considerare questo il "verde di riferimento" quando si valutano regressioni.

## 5. Build & Deploy (Docker / Coolify / Hetzner)

- `Dockerfile` multi-stage: dipendenze Composer (no dev), asset Vite compilati nel layer
  di build e copiati in `public/build/`.
- Al boot, `docker/start.sh`:
  1. `php artisan migrate --force`
  2. avvia il **Laravel Scheduler** (ogni minuto) — digest scadenze, backup DB
  3. avvia il **queue worker** (`queue:work --tries=3 --max-time=3600`)
  4. lancia Apache in foreground

### Variabili d'ambiente obbligatorie in produzione
| Variabile | Valore |
|-----------|--------|
| `APP_ENV` | `production` |
| `APP_KEY` | generata (`php artisan key:generate`) |
| `APP_DEBUG` | `false` (GAP-S2) |
| `LOG_CHANNEL` | `stderr` |
| `LOG_LEVEL` | `warning` |
| `DB_CONNECTION` | `pgsql` |
| `DB_HOST` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | credenziali DB |
| `SESSION_ENCRYPT` | `true` (GAP-S3) |

### Env opzionali
- `HACCP_ALERT_GIORNI_LOTTI` (30), `HACCP_ALERT_GIORNI_CERTIFICATI` (60),
  `HACCP_ALERT_EMAILS` (destinatari extra digest).
- Chiave API Anthropic per l'estrazione certificati.

### Checklist deploy della Riforma (2026-07-08)
Backup DB → migrazione (potenzialmente distruttiva sulle tabelle produzione/varianti) →
`npm run build` → deploy Coolify → smoke test (crea vendita+PDF, crea produzione+scheda
PDF) → piano di rollback. La migrazione introduce `prodotto_varianti`, schede
imballaggi/gas, tabelle di cattura produzione e campi fattura.

## 6. Design System (UI)

- **Token** in `resources/css/app.css` (`@theme` + `:root`): palette **pino + ambra**
  su neutri caldi, tema chiaro/scuro (`:root.dark`), raggi/ombre, scale tipografiche.
- **Font**: **Inter Variable** self-hosted (`@fontsource-variable/inter`, importato in
  `app.js`), con fallback system (Segoe UI → system-ui). I token `--font-sans/-display`
  puntano a Inter.
  > ⚠️ Il font funziona **solo** se il plugin `@tailwindcss/vite` è registrato in
  > `vite.config.js` (compila i blocchi `@theme` in variabili `:root`). Vedi `02` §6.
- **Componenti**: PrimeVue preset **Aura** personalizzato (`MarchePreset` in `app.js`)
  per allineare primario/superfici alla palette del brand.

## 7. Storia & changelog (sintesi)

Cronologia consolidata dai vecchi `CHANGELOG-*`, `GAPS`, `ROADMAP`, `REFORM-PLAN`:

- **Giu 2026** — MVP: anagrafica, alimenti, imballaggi, schede, produzioni, tracciabilità,
  import, dashboard.
- **23 Giu 2026** — risolti 21 "gap" storici (sicurezza, debito tecnico, schema): rate
  limiting, audit trail, FK index, bilanci lotto & lock, ricetta enforced, note credito
  check, tracciabilità produzione↔vendita, versioning schede, semilavorati, conto terzi.
- **1 Lug 2026** — hardening: 2FA admin, recall workflow, notifiche in-app, backup DB
  automatico, alert scadenze via email/scheduler.
- **6 Lug 2026** — soft-delete + Cestino, allergeni (Reg. UE 1169/2011), audit log
  append-only, etichette QR acquisti/vendite, allergeni sui lotti in ingresso.
- **7–8 Lug 2026 (Riforma)** — varianti/pezzature prodotto, scheda vuota+compilata,
  catalogo gas, tabelle di cattura produzione, pricing e campi Fattura DdT, export Excel,
  confronto schede.
- **8 Lug 2026 (sessione UI/PDF)** — fedeltà PDF Fattura DdT (layout, logo, box
  destinatario), Scheda di Produzione su una pagina A4, fix pipeline **Tailwind/Inter**
  (plugin `@tailwindcss/vite` non registrato → font serif indesiderato), fix "Registra
  produzione" (422 `abort` → `ValidationException` inline), fix allineamento header/topbar.

## 8. Problemi noti / limiti aperti

- **Test SQLite**: fallimento baseline su `DROP COLUMN` con index (vedi §4) — valutare
  una migrazione SQLite-safe o l'esecuzione della suite su Postgres in CI.
- **`npm run dev` su Windows**: hot-reload instabile — preferire `build`.
- **PDF via browser preview ≠ dompdf**: verificare l'impaginazione sul PDF reale
  (dompdf rende le tabelle più alte del browser).
- **Confronto schede**: richiede ≥2 schede (per una singola scheda servirebbe una piccola
  estensione — non prioritario).
- La `Cliente.indirizzo` è un campo unico: il PDF Fattura ne fa il parsing per
  via/città/provincia; indirizzi in formati anomali degradano su una sola riga.

## 9. Mappa file di documentazione

Questa serie `01`–`10` sostituisce e riassume i precedenti documenti sparsi
(BLUEPRINT, ARCHITECTURE, MODULES, API, DATABASE, INDEXING, WORKFLOWS, INTEGRATIONS,
DEPLOY, GAPS, ROADMAP, REFORM-PLAN, CHANGELOG-*, fornitori). Per il dettaglio rotta-per-rotta
fare riferimento a `routes/web.php`; per lo schema, alle migrazioni in `database/migrations/`.
