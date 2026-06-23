# INTEGRATIONS.md
## Marche International Food S.R.L. — External Integrations

---

## 1. External Services

This application has **no third-party API integrations**. There is no payment processor, no email delivery service, no OAuth provider, no cloud storage, and no external analytics or monitoring service wired into the application code.

The only external I/O is:
- User-facing HTTPS served by Traefik (Coolify-managed)
- Database connections to PostgreSQL (internal network within Coolify)
- CSV file uploads processed entirely in-memory by PHP

All environment keys for AWS, Redis, and mail in `.env.example` are Laravel framework defaults carried over from the project skeleton. **None of them are used by this application.**

---

## 2. Internal Data Exchange — CSV Import

The closest thing to an integration is the CSV bulk import feature, which allows historical data to be loaded from spreadsheet exports. This is an inbound-only, file-based data exchange with no external system.

### Import Flow — Acquisti CSV

```mermaid
sequenceDiagram
    actor Admin
    participant Vue as Import/Index.vue
    participant Ctrl as ImportController
    participant FS as PHP File System
    participant DB as PostgreSQL

    Admin->>Vue: Selects acquisti CSV file (max 10 MB)
    Admin->>Vue: Clicks "Importa Acquisti"
    Vue->>Ctrl: POST /import/acquisti\nmultipart/form-data {file}

    Ctrl->>Ctrl: validate: mimes=csv,txt, max=10240
    Ctrl->>FS: $request->file->getRealPath()
    Ctrl->>FS: fgetcsv(handle, 0, ';') — reads headers row
    loop Each data row
        Ctrl->>Ctrl: Build $grouped[fornitore|doc_number|date][]
    end

    Ctrl->>DB: BEGIN TRANSACTION
    loop Each document group
        Ctrl->>DB: SELECT fornitori WHERE codice = ?
        alt Fornitore not found
            Ctrl->>Ctrl: Append to $errors[], skip group
        else Fornitore found
            Ctrl->>DB: INSERT acquisti (header)
            loop Each row in group
                Ctrl->>Ctrl: Validate quantita_kg > 0
                Ctrl->>DB: INSERT acquisti_righe
            end
        end
    end
    Ctrl->>DB: COMMIT (or ROLLBACK on exception)

    Ctrl-->>Admin: redirect /import + flash message\n"Importati N righe in M documenti. Avvisi: ..."
```

### Import Flow — Vendite CSV

Identical flow to acquisti, but groups by `cliente_codice|numero_documento|data_documento` and inserts into `vendite` + `vendite_righe`.

### CSV Format

**Template Acquisti** (`GET /import/template-acquisti`):

| Column | Required | Notes |
|---|---|---|
| `fornitore_codice` | Yes | Must match `fornitori.codice` exactly |
| `numero_documento` | Yes | Free text, max 50 chars |
| `data_documento` | Yes | Format: `DD/MM/YYYY` or `YYYY-MM-DD` |
| `tipo_documento` | No | DDT, Fattura, or Bolla; defaults to DDT |
| `nome_prodotto` | Yes | Free text description |
| `quantita_kg` | Yes | Decimal, comma or dot separator |
| `quantita_pz` | No | Decimal |
| `lotto` | No | Internal lot code (mutually exclusive with `lotto_esterno`) |
| `lotto_esterno` | No | Supplier lot code |
| `scadenza` | No | Date: `DD/MM/YYYY` |
| `data_in` | No | Defaults to `data_documento` if empty |
| `note_documento` | No | Applied to header only (first row of group) |

**Template Vendite** (`GET /import/template-vendite`):

| Column | Required | Notes |
|---|---|---|
| `cliente_codice` | Yes | Must match `clienti.codice_cliente` exactly |
| `numero_documento` | Yes | |
| `data_documento` | Yes | Format: `DD/MM/YYYY` or `YYYY-MM-DD` |
| `tipo_documento` | No | DDT, FI, or NC; defaults to DDT |
| `nome_prodotto` | Yes | |
| `pezzatura_gr` | No | Pack size in grams |
| `quantita_kg` | Yes | Decimal |
| `quantita_pz` | No | Decimal |
| `lotto` | No | |
| `lotto_esterno` | No | |
| `scadenza` | No | |
| `note_documento` | No | |

### Import Error Handling

The import is **fully transactional** — both `importAcquisti` and `importVendite` wrap the entire import loop in `DB::beginTransaction()` / `DB::commit()` / `DB::rollBack()`. If any unhandled exception occurs mid-import, the entire batch is rolled back and nothing is committed. Supplier-not-found and validation errors are collected in `$errors[]` and do not throw; those groups are skipped. The transaction only rolls back on an unexpected database or PHP exception. Error messages are collected and returned as a single concatenated string in the flash message, truncated to the first 5 warnings.

---

## 3. Environment Variables

All environment variables required for this application. Variables marked **Unused** are Laravel skeleton defaults that serve no function in this codebase and can be removed. `Yes*` = required for email-dependent features (password reset, expiry alert emails); the app runs without them but those features will error.

| Variable | Required | Used | Value |
|---|---|---|---|
| `APP_NAME` | Yes | Yes | Application name shown in UI |
| `APP_ENV` | Yes | Yes | `production` in prod |
| `APP_KEY` | Yes | Yes | 32-byte base64 key — `php artisan key:generate` |
| `APP_DEBUG` | Yes | Yes | `false` in production |
| `APP_URL` | Yes | Yes | Public HTTPS URL (e.g., `https://app.marchefood.it`) |
| `APP_LOCALE` | No | No | Skeleton default (`en`) — unused |
| `DB_CONNECTION` | Yes | Yes | `pgsql` in production |
| `DB_HOST` | Yes | Yes | PostgreSQL host (Coolify internal hostname) |
| `DB_PORT` | Yes | Yes | `5432` |
| `DB_DATABASE` | Yes | Yes | Database name |
| `DB_USERNAME` | Yes | Yes | Database user |
| `DB_PASSWORD` | Yes | Yes | Database password |
| `SESSION_DRIVER` | Yes | Yes | `database` |
| `SESSION_LIFETIME` | No | Yes | `120` (minutes) |
| `SESSION_ENCRYPT` | No | Yes | `true` — sessions encrypted at rest (GAP-S3) |
| `CACHE_STORE` | No | Yes | `database` |
| `QUEUE_CONNECTION` | No | Yes | `database` (queue worker runs in dev; not used in prod) |
| `LOG_CHANNEL` | No | Yes | `stack` |
| `LOG_LEVEL` | No | Yes | `error` in production |
| `BCRYPT_ROUNDS` | No | Yes | `12` |
| `MAIL_MAILER` | Yes* | Yes | `smtp` — required for password reset emails and expiry alert emails |
| `MAIL_HOST` | Yes* | Yes | SMTP server hostname (e.g., `smtp.mailgun.org`) |
| `MAIL_PORT` | Yes* | Yes | SMTP port (587 for TLS) |
| `MAIL_USERNAME` | Yes* | Yes | SMTP username |
| `MAIL_PASSWORD` | Yes* | Yes | SMTP password |
| `MAIL_FROM_ADDRESS` | Yes* | Yes | Sender address for all outgoing email |
| `REDIS_HOST` | No | **Unused** | Redis not used |
| `REDIS_PASSWORD` | No | **Unused** | — |
| `REDIS_PORT` | No | **Unused** | — |
| `AWS_ACCESS_KEY_ID` | No | **Unused** | S3 not used |
| `AWS_SECRET_ACCESS_KEY` | No | **Unused** | — |
| `AWS_DEFAULT_REGION` | No | **Unused** | — |
| `AWS_BUCKET` | No | **Unused** | — |
| `VITE_APP_NAME` | No | No | Build-time only; baked into compiled assets |
| `BROADCAST_CONNECTION` | No | **Unused** | Broadcasting not used |
| `FILESYSTEM_DISK` | No | No | `local`; uploaded CSV files are read from temp path and never persisted |
