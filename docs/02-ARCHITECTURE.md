# 02 — Architettura

## 1. Stack tecnico (versioni correnti)

| Livello | Tecnologia |
|---------|-----------|
| Linguaggio | PHP 8.4 |
| Framework | Laravel 13.8 |
| Ponte SSR/SPA | Inertia.js v3 (`inertiajs/inertia-laravel ^3.1`, `@inertiajs/vue3 ^3.4`) |
| Frontend | Vue 3.5 (Composition API, SFC) |
| Componenti UI | PrimeVue 4.5 (preset **Aura** personalizzato → `MarchePreset`) + PrimeIcons |
| CSS | Tailwind CSS 4 (plugin `@tailwindcss/vite`) + design tokens custom |
| Font | **Inter Variable** self-hosted (`@fontsource-variable/inter`), fallback system |
| Bundler | Vite 8 (`laravel-vite-plugin`, `@vitejs/plugin-vue`) |
| PDF | `barryvdh/laravel-dompdf ^3.1` (dompdf 3.1) su viste Blade |
| Database | PostgreSQL (prod 18); SQLite in-memory nei test |
| Container | Docker (Apache + mod_php, `php:8.4-apache`) |
| Deploy | Hetzner + Coolify |

## 2. Il monolite Inertia (niente API separata)

Il sistema è un **monolite server-driven**: non esiste una API REST pubblica. Il flusso è:

```
Browser → route Laravel → Controller → Inertia::render('Pagina', props)
        → Inertia carica il componente Vue e vi inietta le props (JSON)
```

- Le **props** delle pagine sono serializzate dal controller (spesso `->paginate()`),
  non da endpoint JSON dedicati. La "API" del sistema sono le rotte web (vedi
  elenco completo di rotte nei file `04`–`10` e in `routes/web.php`).
- I **form** usano `useForm()` di Inertia: `form.post()/put()`. Gli errori di
  validazione (`ValidationException`, HTTP 422) tornano **inline** sui campi;
  un `abort()` invece mostra la pagina d'errore generica (da evitare nei form —
  vedi nota storica in `06`/`10`).
- Le **azioni non-pagina** (PDF, CSV, etichette) sono rotte `GET` che ritornano
  `Response`/stream invece di `Inertia::render`.

## 3. Dove vive la logica (layer)

| Layer | Percorso | Responsabilità |
|-------|----------|----------------|
| Rotte | `routes/web.php` | Mappa URL→controller, middleware (`auth`, `admin`, `guest`, `throttle`) |
| Controller | `app/Http/Controllers/*` | Orchestrazione: validazione, transazioni, render Inertia |
| Model (Eloquent) | `app/Models/*` | Tabelle, relazioni, cast, scope, soft-delete |
| Service | `app/Services/*` | Logica di dominio riusabile (vedi §4) |
| Concern/Trait | `app/Concerns/*` | Es. `Auditable` (created_by/updated_by + change log) |
| Vista Blade | `resources/views/pdf/*`, `labels/*`, `emails/*` | PDF, etichette, email |
| Pagina Vue | `resources/js/Pages/*` | UI (una cartella per modulo) |
| Layout Vue | `resources/js/Layouts/AppLayout.vue` | Sidebar, topbar, ricerca globale, notifiche, tema |
| Config dominio | `config/haccp.php` | Campioni metal detector, ciclo di lavoro default, soglie alert |

## 4. Service layer

| Service | Ruolo |
|---------|-------|
| `InventoryService` | Bilanci lotto (ricevuto − consumato − venduto), giacenze magazzino |
| `AllergenService` | Derivazione allergeni sui lotti di produzione (Reg. UE 1169/2011) |
| `AuditService` | Scrittura append-only del change log (`audit_logs`) |
| `ReportService` | Aggregati per il report gestionale e per gli alert scadenze |
| `SearchService` | Ricerca globale cross-entità |
| `NotificationService` | Notifiche in-app (scadenze, eventi) |
| `CertificateExtractionService` | Estrazione AI dei dati dei certificati HACCP fornitore |
| `TotpService` | Generazione/verifica TOTP per la 2FA |

## 5. Directory layout (landmark)

```
app/
  Concerns/Auditable.php        # audit trait (created_by/updated_by + log)
  Http/Controllers/            # 30 controller (uno per modulo + Auth/)
  Http/Middleware/             # admin, ecc.
  Models/                      # 38 model Eloquent
  Services/                    # 8 service di dominio
config/haccp.php               # costanti di dominio HACCP
database/
  migrations/                  # 46 migrazioni (schema evolutivo)
  seeders/                     # ClienteSeeder, Screen3Seeder, FornitoreSeeder…
resources/
  css/app.css                  # design tokens + font (@theme), tema chiaro/scuro
  js/app.js                    # bootstrap Inertia + PrimeVue (MarchePreset) + Inter
  js/Pages/<Modulo>/           # pagine Vue (Index/Form/Print…)
  js/Layouts/AppLayout.vue     # shell applicativa
  views/pdf/                   # Blade dei PDF (vendita, scheda-produzione, acquisto…)
  views/labels/                # etichette lotto con QR
routes/web.php                 # tutte le rotte (auth-gated)
tests/Feature/                 # ~20 test funzionali
docker/                        # start.sh, config Apache
vite.config.js                 # laravel + tailwindcss() + vue()
```

## 6. Build pipeline (importante)

- **Entrypoint**: `resources/js/app.js` importa Inter, `app.css`, monta Inertia + PrimeVue.
- `vite.config.js` registra i plugin **`laravel()` → `tailwindcss()` → `vue()`**.
  > ⚠️ Nota storica: il plugin `@tailwindcss/vite` era stato installato ma **non
  > registrato** in `vite.config.js`; di conseguenza i blocchi `@theme` (inclusi i
  > token dei font) non venivano compilati e l'app cadeva sul serif di default.
  > Ora è registrato: i token `--font-*` finiscono in `:root` e Inter è attivo.
- I **design token** (colori, font, raggi, ombre) sono in `resources/css/app.css`
  come variabili CSS; il tema scuro ridefinisce le stesse variabili sotto `:root.dark`.
- **Gli asset compilati (`public/build/`) NON sono versionati** (in `.gitignore`):
  ogni deploy deve eseguire `npm run build`.

## 7. Convenzioni

- **Lingua**: UI e dominio in italiano; nomi tecnici/commit spesso in inglese.
- **Denaro/quantità**: formati it-IT (virgola decimale) nei PDF; server come fonte
  di verità per i calcoli (es. importo netto vendite ricalcolato lato server).
- **Soft-delete** su tutte le entità operative (vedi `09`); i controller ricostruiscono
  le righe con **diff-sync** (preservano gli ID) invece di delete-and-recreate.
- **Transazioni** (`DB::transaction`) attorno alle operazioni multi-tabella
  (produzione, vendita, import) con `lockForUpdate` per i controlli di bilancio.
- **Optimistic locking** sui form di modifica (rifiuto se il record è cambiato).
