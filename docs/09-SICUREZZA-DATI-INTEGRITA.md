# 09 — Sicurezza, Accessi & Integrità dei Dati

Controller: `Auth/*`, `ProfileController`, `UtenteController`, `AuditController`,
`CestinoController`, `NotificationController`. Service: `TotpService`, `AuditService`,
`NotificationService`. Trait: `app/Concerns/Auditable.php`.

---

## 1. Autenticazione

- Login gated (`guest`/`auth`), con **rate limiting** (GAP-S1): `throttle:10,1` su login e
  challenge 2FA, `throttle:5,1` su richiesta reset password.
- **Reset password self-service**: `/forgot-password`, `/reset-password/{token}`.
- **Sessioni cifrate a riposo** in produzione (`SESSION_ENCRYPT=true`, GAP-S3).
- HTTPS forzato a livello applicativo in produzione (GAP-S4).

## 2. Autenticazione a due fattori (2FA / TOTP)

- `TwoFactorController` + `TotpService`. **Enrollment admin-only**
  (`/profilo/2fa/enable|confirm`, `DELETE /profilo/2fa`); challenge mid-login
  (`/2fa/challenge`, `/2fa/verify`) prima che la sessione sia autenticata.
- Campi 2FA su `users` (secret + recovery codes), migrazione `add_two_factor_to_users`.

## 3. Ruoli & controllo accessi

Due ruoli (`users.role`): **admin** e **operator**.

| Capacità | Operatore | Admin |
|----------|:--------:|:-----:|
| Lettura anagrafiche & documenti | ✅ | ✅ |
| Creare/modificare acquisti, vendite, produzioni, imballaggi | ✅ | ✅ |
| Eliminare record operativi (soft-delete) | ❌ | ✅ |
| CRUD anagrafica, schede, flussi | ❌ | ✅ |
| Import, Audit, Cestino, Utenti, 2FA enrollment | ❌ | ✅ |

Enforcement via middleware **`admin`** su gruppi di rotte (`routes/web.php`). Gestione
utenti admin-only (`resource utenti`, reset password). Un admin non può eliminare il
proprio account (GAP-S6). Coperto da `AccessControlTest`, `AuthTest`, `UtenteTest`.

## 4. Audit log (change log append-only)

- Trait **`Auditable`** popola `created_by`/`updated_by` e registra le modifiche.
- `AuditService` scrive record **append-only** in `audit_logs`; `AuditController`
  (`GET /audit`, admin) espone "chi ha fatto cosa e quando" (GAP-S5).
- Coperto da `AuditLogTest`.

## 5. Soft-delete & Cestino (data safety)

- Le entità operative usano `SoftDeletes` (`deleted_at`). L'eliminazione **non** rimuove
  fisicamente: sposta nel **Cestino**.
- `CestinoController` (admin): `GET /cestino`, `POST /cestino/{tipo}/{id}/restore`
  (ripristino), `DELETE /cestino/{tipo}/{id}` (eliminazione definitiva).
- **Guardie di eliminazione**: non si elimina una vendita/riga con bolle di reso
  collegate, né una produzione il cui semilavorato è consumato a valle o il cui lotto
  è già venduto. Coperto da `CestinoTest`.
- I record soft-deleted sono esclusi da bilanci e tracciabilità.

## 6. Optimistic locking

I form di modifica (es. produzioni, vendite) rifiutano il salvataggio se il record è
cambiato dopo il caricamento (`assertNotStale`), prevenendo sovrascritture concorrenti.

## 7. Concorrenza sui bilanci

Le registrazioni che consumano lotti girano in **transazione** con `lockForUpdate` e
ri-verifica del saldo *dentro* la transazione, così due submit concorrenti non possono
sovra-consumare lo stesso lotto (vedi `06`/`07`).

## 8. Notifiche in-app

`NotificationService` + `NotificationController`: centro avvisi (`/notifiche`), con
dismiss singolo e "segna tutte lette". Alimentato da scadenze lotti/certificati e altri
eventi. Badge nella topbar (`AppLayout.vue`).

## 9. Validazione: pattern corretto

Gli errori di validazione devono tornare **inline** sui form Inertia: usare
`ValidationException` (HTTP 422 gestito da Inertia), **non** `abort(422)` (che mostra la
pagina d'errore generica). Questo pattern è stato uniformato nel controllo ricetta della
produzione (vedi `06` §2c).
