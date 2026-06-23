# ARCHITECTURE.md
## Marche International Food S.R.L. — Sistema di Tracciabilità HACCP

---

## 1. Tech Stack

| Layer | Technology | Version | Notes |
|---|---|---|---|
| **Runtime** | PHP | 8.4 | Apache mod_php inside Docker |
| **Framework** | Laravel | 13.x | Full-stack, session-based, Inertia adapter |
| **Frontend** | Vue 3 + Inertia.js | 3.5 / 3.4 | SPA-like navigation without a separate API |
| **UI Library** | PrimeVue + PrimeIcons | 4.5 | Component library; Tailwind CSS 4 for utilities |
| **Build Tool** | Vite | 8.x | Multi-stage Docker build; assets compiled at image build time |
| **Database (prod)** | PostgreSQL | 18 | Declared in `schema.sql`; `ilike` queries confirm PG dialect |
| **Database (dev)** | SQLite | — | Default in `.env.example`; file at `database/database.sqlite` |
| **Auth** | Laravel Session Auth | — | Email + password; `remember_me` cookie; CSRF via Laravel middleware |
| **Roles** | Custom `EnsureAdmin` middleware | — | Two roles: `operator` (default) and `admin` |
| **Containerization** | Docker (multi-stage) | — | Stage 1: Node 22 Alpine (Vite build); Stage 2: PHP 8.4 Apache |
| **Hosting** | Hetzner VPS + Coolify | — | Coolify manages container lifecycle, SSL, and reverse proxy |
| **Queue** | Laravel Queue (database driver) | — | Background jobs stored in `jobs` table; worker runs via `queue:listen` in dev |
| **Cache** | Database driver | — | `cache` table; no Redis in production by default |
| **Session** | Database driver | — | `sessions` table; 120-minute lifetime |

---

## 2. High-Level Architecture

```mermaid
flowchart TB
    subgraph Browser["Browser (Vue 3 / Inertia.js)"]
        UI[PrimeVue Components]
        Inertia[Inertia Router]
    end

    subgraph Coolify["Hetzner VPS — Coolify"]
        Proxy[Traefik Reverse Proxy\nSSL Termination]

        subgraph Container["Docker Container — PHP 8.4 Apache"]
            Apache[Apache 2.4\nmod_rewrite → public/index.php]
            Laravel[Laravel 13\nMiddleware Stack]
            Controllers[Controllers\nAcquisti · Vendite · Produzione\nImballaggi · Tracciabilità]
            Models[Eloquent Models\n20 models]
            Inertia_Server[Inertia Server Adapter\nRendering Vue pages]
        end

        DB[(PostgreSQL 18\nTracciabilità HACCP)]
        DBSessions[(sessions / cache / jobs\ntables in same DB)]
    end

    subgraph Assets["Static Assets"]
        Build[public/build/\nVite-compiled JS + CSS\nbaked into Docker image]
    end

    Browser -->|HTTPS| Proxy
    Proxy -->|HTTP| Apache
    Apache --> Laravel
    Laravel --> Controllers
    Controllers --> Models
    Models --> DB
    Laravel --> DBSessions
    Laravel --> Inertia_Server
    Inertia_Server -->|Inertia JSON / HTML| Browser
    Browser --> Build
```

---

## 3. Directory Layout

```
marche-food/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/
│   │   │   │   └── LoginController.php        # Session login/logout
│   │   │   ├── AcquistoController.php         # Screen 1 — purchase documents
│   │   │   ├── VenditaController.php          # Screen 1 — sales documents
│   │   │   ├── BollaResoController.php        # Screen 1 — return notes
│   │   │   ├── NotaCreditoController.php      # Screen 1 — credit notes
│   │   │   ├── ImballaggioController.php      # Screen 2 — packaging lots
│   │   │   ├── SchedaProduzioneController.php # Screen 3 — HACCP production sheets
│   │   │   ├── ProduzioneController.php       # Screen 3 — production runs (lot linking)
│   │   │   ├── FlussoProduzioneController.php # Screen 3 — workflow step config (admin)
│   │   │   ├── TracciabilitaController.php    # Cross-cutting lot search (forward+reverse)
│   │   │   ├── DashboardController.php        # KPIs + expiry alerts
│   │   │   ├── ImportController.php           # CSV bulk import (acquisti + vendite)
│   │   │   ├── FornitoreController.php        # Supplier registry (anagrafica)
│   │   │   ├── ClienteController.php          # Customer registry
│   │   │   ├── ProdottoController.php         # Finished product catalogue
│   │   │   ├── MateriaPrimaController.php     # Raw material catalogue
│   │   │   ├── DestinazioneIngredientiController.php # Allowed ingredient→product mappings
│   │   │   ├── UtenteController.php           # User management (admin only)
│   │   │   └── ProfileController.php          # Self-service password change
│   │   └── Middleware/
│   │       ├── EnsureAdmin.php                # role === 'admin' gate
│   │       └── HandleInertiaRequests.php      # Shares auth user to all Inertia pages
│   ├── Models/                                # 20 Eloquent models (see DATABASE.md)
│   └── Providers/
│       └── AppServiceProvider.php
├── database/
│   ├── migrations/                            # 22 migration files (chronological)
│   ├── seeders/                               # Dev-only seed data
│   └── database.sqlite                        # Dev database (git-ignored in prod)
├── resources/
│   ├── js/
│   │   ├── Layouts/
│   │   │   └── AppLayout.vue                  # Shared shell: sidebar nav + header
│   │   └── Pages/                             # One subfolder per domain module
│   │       ├── Auth/Login.vue
│   │       ├── Dashboard.vue
│   │       ├── Acquisti/{Index,Form,Print}.vue
│   │       ├── Vendite/{Index,Form}.vue
│   │       ├── BolleReso/{Index,Form}.vue
│   │       ├── NoteCredito/{Index,Form}.vue
│   │       ├── Imballaggi/{Index,FormPrimario,FormDetergente}.vue
│   │       ├── Schede/{Index,Form,Print}.vue
│   │       ├── Produzioni/{Index,Form,Print}.vue
│   │       ├── Tracciabilita.vue
│   │       ├── Fornitori/{Index,Form}.vue
│   │       ├── Clienti/{Index,Form}.vue
│   │       ├── Prodotti/{Index,Form}.vue
│   │       ├── MateriePrime/{Index,Form}.vue
│   │       ├── DestinazioneIngredienti/Index.vue
│   │       ├── Flussi/Index.vue
│   │       ├── Import/Index.vue
│   │       ├── Utenti/Index.vue
│   │       └── Profilo.vue
│   ├── css/app.css                            # Tailwind entry point
│   └── views/
│       ├── app.blade.php                      # Single Blade template (Inertia root)
│       └── errors/403.blade.php
├── routes/
│   └── web.php                                # All routes (no api.php used)
├── docker/
│   └── start.sh                               # Entrypoint: artisan migrate → apache2-foreground
├── public/
│   └── build/                                 # Vite output (baked into image at build time)
├── schema.sql                                 # Canonical PostgreSQL DDL (source of truth)
├── Dockerfile                                 # Multi-stage: Node assets → PHP Apache
├── .env.example
└── composer.json
```

---

## 4. Request Flow

### 4a. Initial Page Load (unauthenticated → dashboard)

```mermaid
sequenceDiagram
    actor User as Browser
    participant Traefik as Traefik (SSL)
    participant Apache
    participant Laravel
    participant Auth as Auth Middleware
    participant Ctrl as DashboardController
    participant DB as PostgreSQL

    User->>Traefik: GET / (HTTPS)
    Traefik->>Apache: GET / (HTTP, forwarded)
    Apache->>Laravel: public/index.php
    Laravel->>Auth: Check session cookie
    Auth-->>Laravel: Not authenticated
    Laravel-->>User: 302 Redirect → /login

    User->>Traefik: GET /login
    Traefik->>Apache: GET /login
    Apache->>Laravel: public/index.php
    Laravel-->>User: 200 HTML (Inertia root shell + Login.vue props)

    User->>Traefik: POST /login {email, password}
    Traefik->>Apache: POST /login
    Apache->>Laravel: LoginController::login()
    Laravel->>DB: SELECT users WHERE email=?
    DB-->>Laravel: User record
    Laravel->>Laravel: Auth::attempt() → bcrypt verify
    Laravel->>DB: INSERT sessions
    Laravel-->>User: 302 Redirect → / (with session cookie)

    User->>Traefik: GET / (with session)
    Traefik->>Apache: GET /
    Apache->>Laravel: DashboardController::index()
    Laravel->>DB: COUNT acquisti, vendite, produzioni\nSELECT acquisti_righe WHERE scadenza near
    DB-->>Laravel: Stats + expiry rows
    Laravel-->>User: Inertia JSON {component:"Dashboard", props:{stats,…}}
    Note over User: Vue hydrates dashboard in-place
```

### 4b. Production Lot Registration (core HACCP flow)

```mermaid
sequenceDiagram
    actor Op as Operator
    participant Vue as Produzioni/Form.vue
    participant Inertia
    participant Ctrl as ProduzioneController
    participant DB as PostgreSQL

    Op->>Vue: Opens /produzioni/create
    Ctrl->>DB: SELECT schede_produzione WHERE attiva=true
    Ctrl->>DB: SELECT acquisti_righe (all open lots)
    DB-->>Vue: schede[], materie[], acquisti_righe[]

    Op->>Vue: Selects scheda, enters lotto_produzione,\nlinks N acquisti_righe per ingredient

    Vue->>Inertia: POST /produzioni\n{scheda_id, lotto_produzione, data_produzione,\n materie_prime:[{acquisto_riga_id, materia_prima_id, quantita_kg}]}

    Inertia->>Ctrl: store(Request)
    Ctrl->>Ctrl: validateRequest() — unique lotto,\nexists checks on scheda + acquisto_riga
    Ctrl->>DB: INSERT produzioni
    Ctrl->>DB: INSERT produzioni_materie_prime × N rows
    DB-->>Ctrl: OK
    Ctrl-->>Op: 302 → /produzioni (success flash)
```

---

## 5. Security Model

| Concern | Mechanism | Detail |
|---|---|---|
| **Authentication** | Laravel Session Auth | Email + bcrypt password. Session stored in `sessions` DB table. CSRF token required on all state-changing requests (enforced by Laravel's `VerifyCsrfToken` middleware). |
| **Remember Me** | Signed cookie | `remember_token` column in `users` table; signed by `APP_KEY`. |
| **Authorization — read** | `auth` middleware | All routes except `/login` require a valid session. Unauthenticated requests receive a 302 to `/login`. |
| **Authorization — write/delete** | `admin` middleware (`EnsureAdmin`) | DELETE verbs on all operational records, all schede CRUD, flussi config, user management, and CSV import are behind this middleware. Non-admin users are redirected to `/` with an error flash. |
| **Role escalation** | DB column `users.role` | `operator` (default) or `admin`. Only an admin can create/edit users via `UtenteController`. There is no self-registration endpoint. |
| **CSRF protection** | Laravel default | `VerifyCsrfToken` middleware active on all non-GET routes. Inertia automatically includes the `X-XSRF-TOKEN` header on XHR requests. |
| **Direct file access** | Apache `DocumentRoot` → `public/` | Application code, `.env`, and `storage/` are outside the web root. The Dockerfile explicitly sets `APACHE_DOCUMENT_ROOT=/var/www/html/public`. |
| **Password hashing** | bcrypt | `BCRYPT_ROUNDS=12` (configurable via env). |
| **Session fixation** | `session()->regenerate()` | Called in `LoginController::login()` immediately after `Auth::attempt()` succeeds. |
| **Mass assignment** | Eloquent `$fillable` | All models define explicit `$fillable` arrays. No `$guarded = []` shortcuts observed. |
| **Input validation** | Laravel `Request::validate()` | Every controller write method validates before touching the database. |
| **SQL injection** | Eloquent + Query Builder | All user input passed through parameterized queries. Raw `ilike` searches use `->where('col', 'ilike', $term)` with bound parameters, not string interpolation. |
| **Missing** | Rate limiting | No `throttle` middleware applied to the `/login` route. Brute-force protection relies solely on Coolify/Traefik config if any. |
| **Missing** | HTTPS enforcement | No `ForceHttps` middleware or HSTS header in application code. SSL is expected to be handled entirely by Traefik/Coolify. |
