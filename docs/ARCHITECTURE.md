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
| **Queue** | Laravel Queue (database driver) | — | Background jobs stored in `jobs` table; `queue:listen` in dev, `queue:work --tries=3 --max-time=3600` in production (started in `start.sh`) |
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
            Controllers[Controllers\nAcquisti · Vendite · Produzione\nImballaggi · Tracciabilità · Recall · Report]
            Models[Eloquent Models\n24 models]
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
│   │   │   │   ├── LoginController.php             # Session login/logout
│   │   │   │   ├── ForgotPasswordController.php    # Send password reset email
│   │   │   │   └── ResetPasswordController.php     # Reset password via token
│   │   │   ├── AcquistoController.php         # Screen 1 — purchase documents (+ export)
│   │   │   ├── VenditaController.php          # Screen 1 — sales documents (+ export)
│   │   │   ├── BollaResoController.php        # Screen 1 — return notes
│   │   │   ├── NotaCreditoController.php      # Screen 1 — credit notes
│   │   │   ├── ImballaggioController.php      # Screen 2 — packaging lots
│   │   │   ├── SchedaProduzioneController.php # Screen 3 — HACCP production sheets
│   │   │   ├── ProduzioneController.php       # Screen 3 — production runs (+ export)
│   │   │   ├── FlussoProduzioneController.php # Screen 3 — workflow step config (admin)
│   │   │   ├── TracciabilitaController.php    # Cross-cutting lot search (forward+reverse+sales)
│   │   │   ├── RecallController.php           # Recall report — lots by supplier/product/date
│   │   │   ├── ReportController.php           # HACCP PDF download per production run
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
│   ├── Models/                                # 24 Eloquent models (see DATABASE.md)
│   ├── Mail/
│   │   └── AlertScadenzeMail.php              # Mailable: daily expiry alert digest to admin
│   ├── Console/
│   │   └── Commands/
│   │       ├── InviaAlertScadenze.php         # Artisan: haccp:alert-scadenze (runs daily 07:00)
│   │       └── BackupDatabase.php             # Artisan: db:backup — pg_dump + 14-day retention
│   ├── Concerns/
│   │   └── Auditable.php                      # Trait: auto-populates created_by/updated_by on model events
│   └── Providers/
│       └── AppServiceProvider.php
├── database/
│   ├── migrations/                            # 30 migration files (chronological)
│   ├── seeders/                               # Dev-only seed data
│   └── database.sqlite                        # Dev database (git-ignored in prod)
├── resources/
│   ├── js/
│   │   ├── Layouts/
│   │   │   └── AppLayout.vue                  # Shared shell: sidebar nav + header
│   │   └── Pages/                             # One subfolder per domain module
│   │       ├── Auth/Login.vue
│   │       ├── Auth/ForgotPassword.vue        # Request password reset email
│   │       ├── Auth/ResetPassword.vue         # Set new password via token
│   │       ├── Dashboard.vue
│   │       ├── Acquisti/{Index,Form,Print}.vue
│   │       ├── Vendite/{Index,Form}.vue
│   │       ├── BolleReso/{Index,Form}.vue
│   │       ├── NoteCredito/{Index,Form}.vue
│   │       ├── Imballaggi/{Index,FormPrimario,FormDetergente}.vue
│   │       ├── Schede/{Index,Form,Print}.vue
│   │       ├── Produzioni/{Index,Form,Print}.vue   # Index has CSV export + PDF per-row button
│   │       ├── Tracciabilita.vue
│   │       ├── Recall/Index.vue               # Recall report — cross-lot impact search
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
│       ├── errors/403.blade.php
│       ├── emails/alert_scadenze.blade.php    # HTML email: daily expiry alert
│       └── pdf/produzione.blade.php           # Blade PDF template (dompdf) for HACCP report
├── routes/
│   ├── web.php                                # All routes (no api.php used)
│   └── console.php                            # Scheduler: haccp:alert-scadenze @ 07:00, db:backup @ 03:00
├── docker/
│   └── start.sh                               # Entrypoint: artisan migrate → scheduler loop (bg) → queue worker (bg) → apache2-foreground
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
| **Rate limiting** | `throttle:10,1` / `throttle:5,1` | `POST /login` — 10 attempts/minute. `POST /forgot-password` — 5 attempts/minute. |
| **Password reset** | Laravel built-in token mechanism | `password_reset_tokens` table; 60-minute expiry; HMAC-signed. Token sent via email (SMTP). `POST /reset-password` validates token before allowing new password. |
| **HTTPS enforcement** | `URL::forceScheme('https')` | Enabled in `AppServiceProvider::boot()` when `APP_ENV=production`. All generated URLs are forced to HTTPS. Configure HSTS in Traefik for full coverage. |
| **Audit trail** | `Auditable` trait | All operational models (`Acquisto`, `Vendita`, `Produzione`, `BollaReso`, `NotaCredito`, `LottoImballaggioPrimario`, `LottoDetergente`, `Recall`) auto-populate `created_by` and `updated_by` FK columns referencing `users.id`. |
| **Security headers** | `SecurityHeaders` middleware | `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` on every response; HSTS in production over HTTPS. |
| **Two-factor auth** | `TotpService` + `TwoFactorController` | TOTP (RFC 6238) for admins: enrollment (QR), recovery codes, two-step login challenge. Secret/codes encrypted at rest. |
| **Optimistic locking** | `Controller::assertNotStale()` | `updated_at` conflict check on document edits. |

## Services & components added 2026-07-01

Dedicated **services** (`app/Services/`) keep controllers thin and are unit/simulation tested:

| Service | Responsibility |
|---|---|
| `InventoryService` | Purchase-lot & semilavorato balances, stock summary |
| `ReportService` | Date-range management-report aggregates |
| `AuditService` | Cross-table "who changed what" feed |
| `SearchService` | Cross-entity global search (driver-aware ILIKE/LIKE) |
| `NotificationService` | DB-driven in-app notifications (generate/prune/dismiss) |
| `TotpService` | RFC 6238 TOTP / HOTP / Base32 |
| `CertificateExtractionService` | AI (Claude Vision) certificate field extraction |

New controllers: `HealthController`, `MagazzinoController`, `AuditController`, `SearchController`, `NotificationController`, `KioskController`, `CertificatoController`, `Auth/TwoFactorController` (and `ReportController` extended). Vendored browser assets (no build step): `public/vendor/qrcode-generator.js` (lot labels, 2FA QR) and `public/vendor/html5-qrcode.min.js` (kiosk camera scanning).

## Services & components added 2026-07-06

Data-safety and compliance features (soft-delete, QR lot labels, allergen tracking):

| Service / component | Responsibility |
|---|---|
| `AllergenService` | The 14 EU allergens (Reg. 1169/2011); derives a production lot's allergen set from its ingredients, **recursively** through semi-finished (semilavorato) ingredients (cycle-guarded). |
| `App\Concerns\Auditable` (existing) | Unchanged; the 7 operational models now **also** use Laravel's `SoftDeletes`. |

New controller: `CestinoController` (admin-only trash: list soft-deleted documents, restore, permanent-delete).

New Blade view: `resources/views/labels/lotti.blade.php` (multi-lot QR label sheet for purchases/sales, reusing the vendored `qrcode-generator.js`).

New Vue page: `resources/js/Pages/Cestino/Index.vue`.

**Soft-delete design note.** Only the 7 operational **document** tables carry `deleted_at`; their line/pivot tables do not (they are preserved untouched when a parent is trashed). Because raw `DB::table()` queries bypass Eloquent's `SoftDeletingScope`, every raw balance/report/search/audit query was updated to exclude trashed parents via a join + `whereNull('<parent>.deleted_at')`. Each `destroy()` also carries an application-level guard that blocks trashing a record still referenced by an **active** (non-trashed) downstream document — preserving the invariant the database foreign keys used to enforce on hard delete.
