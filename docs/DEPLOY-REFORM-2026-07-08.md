# Deploy Checklist — Riforma 2026-07-08

Guida operativa per portare in produzione (Hetzner + Coolify) la riforma
scheda/fattura descritta in `CHANGELOG-2026-07-08.md`. **Leggere prima di fare
il deploy**: una delle migrazioni è **distruttiva** (rimuove colonne da `prodotti`).

> ⚠️ **Migrazione distruttiva**: `2026_07_08_000002_create_prodotto_varianti_table.php`
> sposta `codice_prodotto` + pezzatura di ogni prodotto in una riga di
> `prodotto_varianti` e poi **elimina** le colonne `codice_prodotto`,
> `pezzatura_valore`, `pezzatura_um`, `um_id` da `prodotti`. Su dati reali è di
> fatto a senso unico. **Backup del DB obbligatorio prima del deploy.**

---

## 0. Regola importante sull'ambiente

I file corretti sono **sul tuo computer** (`...\Marche Food`). Se hai lavorato
in una sessione con sandbox, **NON** fare commit da lì: la sandbox può contenere
copie parziali/troncate. Committa dalla tua macchina, dove i file sono completi.

Verifica veloce prima del commit: apri `app/Models/Prodotto.php` e controlla che
finisca correttamente con la parentesi `}` dopo `getCodicePrincipaleAttribute()`.

---

## 1. Verifica locale (prima di push)

Dalla cartella del progetto sulla tua macchina:

```bash
# Dipendenze (se non già installate)
composer install
npm install

# 1) Il frontend compila? (Coolify esegue lo stesso build; un errore Vue blocca il deploy)
npm run build

# 2) La suite test passa? (usa SQLite in-memory, non tocca il DB di produzione)
php artisan test

# 3) Le migrazioni girano su un DB pulito?
php artisan migrate:fresh --seed
```

Se `php artisan test` o `npm run build` falliscono, **non fare deploy**: correggi
prima (o inviami l'output).

## 2. Prova la migrazione sui DATI DI PRODUZIONE (staging)

Questo è il passo che conta di più, perché la migrazione varianti trasforma i
dati reali.

```bash
# Sul server / in locale: dump del DB di produzione
pg_dump -U <user> -h <host> -d <db_produzione> -Fc -f prod_backup.dump

# Ripristina su un DB di prova
createdb marche_staging
pg_restore -U <user> -h <host> -d marche_staging prod_backup.dump

# Punta .env a marche_staging e applica SOLO le nuove migrazioni
php artisan migrate            # NON migrate:fresh (perderesti i dati!)
php artisan test
```

Controlli attesi dopo la migrazione su staging:
- ogni prodotto ha almeno una riga in `prodotto_varianti` con lo stesso codice di prima;
- `prodotti` non ha più `codice_prodotto/pezzatura_valore/pezzatura_um/um_id`;
- le pagine Prodotti, Schede, Produzioni, Vendite si aprono senza errori;
- stampa una **scheda vuota** (`/schede/{id}/pdf`) e una **compilata**
  (`/produzioni/{id}/scheda`); export **xlsx** fornitori/clienti.

## 3. Commit & push (dalla tua macchina)

Consigliato: **branch dedicato**, non direttamente il ramo che Coolify osserva.

```bash
git checkout -b reform-2026-07-08
git add -A
git status                     # verifica l'elenco file
git commit -m "Reform scheda produzione + fattura (varianti, PDF vuota/compilata, gas, cattura produzione, auto-fill vendite, export xlsx)"
git push origin reform-2026-07-08
```

Lascia girare la **CI** (`.github/workflows/ci.yml`: migrazioni + test + pint + build).
Se verde, apri la PR e fai merge sul ramo di deploy (`main`).

File nuovi inclusi (11): 8 model (`ProdottoVariante`, `SchedaImballaggio`,
`SchedaGas`, `LottoGas`, `ProduzioneConfezione`, `ProduzioneGas`,
`ProduzioneCiclo`, `ProduzioneMetalDetector`), `app/Support/SimpleXlsxWriter.php`,
5 migrazioni `2026_07_08_000001..000005`, 3 view/Vue nuove
(`pdf/scheda-produzione-vuota.blade.php`, `Imballaggi/FormGas.vue`,
`Schede/Confronto.vue`). `.env` resta ignorato.
Valuta se committare anche `docs from the client/` (utile come riferimento) e se
aggiungere `.claude/` a `.gitignore` (impostazioni editor locali).

## 4. Deploy con Coolify

1. **Backup DB di produzione** (di nuovo, subito prima del deploy):
   `pg_dump -U <user> -h <host> -d <db> -Fc -f pre_reform_$(date +%F).dump`
2. Merge sul ramo di deploy → Coolify avvia il build.
   - Build multi-stage: `composer install --no-dev` + `npm run build`. Un errore
     qui **fa fallire il build** e NON manda in produzione (buono).
3. Al boot del container, `docker/start.sh` esegue `php artisan migrate --force`
   → applica le 5 migrazioni sul DB di produzione.
4. Verifica l'health check (`/health`) e i log del container per errori di migrazione.

> **Nota single-instance**: `migrate --force` gira a ogni boot. Con una sola
> istanza (setup attuale) è ok; con più repliche serve un release-command dedicato.

## 5. Smoke test post-deploy (in produzione)

- Login ok; dashboard carica.
- **Prodotti**: apri un prodotto → vedi le varianti (codice/pezzatura).
- **Schede**: apri una scheda → sezioni Imballaggi/Gas presenti; bottone "PDF vuoto"
  genera il template; "Confronta selezionate" funziona.
- **Produzioni**: nuova produzione → selezione scheda pre-compila confezioni e ciclo;
  registra; `/produzioni/{id}/scheda` produce il PDF **compilato**.
- **Imballaggi**: tab **Gas** → crea un lotto gas.
- **Vendite**: nuova vendita → selettore prodotto auto-compila codice/descrizione;
  PDF fattura mostra i nuovi campi; totali corretti.
- **Export**: `Esporta Excel` fornitori/clienti scarica un `.xlsx` apribile.

## 6. Rollback (se qualcosa va storto)

La via più sicura per una migrazione con perdita di colonne è il **ripristino del
backup**, non il rollback applicativo:

```bash
# Ripristino completo (perdi i dati inseriti dopo il backup)
dropdb <db> && createdb <db>
pg_restore -U <user> -h <host> -d <db> pre_reform_YYYY-MM-DD.dump
# e ridistribuisci il commit precedente su Coolify
```

In alternativa, `php artisan migrate:rollback --step=5` esegue i `down()` (che
ricreano le colonne e ricopiano dalla prima variante), ma il ripristino del dump
resta l'opzione più prevedibile in produzione.

---

### TL;DR
1. `npm run build` + `php artisan test` locali → verdi.
2. **Backup DB** e prova `php artisan migrate` su una **copia** dei dati di produzione.
3. Commit/push da **branch**, CI verde, merge sul ramo di deploy.
4. **Backup DB** ancora, poi lascia che Coolify faccia build + `migrate --force`.
5. Smoke test. Se serve rollback → ripristina il dump.
