# 06 — Screen 3: Produzione

Il modulo più complesso. Distingue nettamente **Scheda** (template/ricetta, versionata,
admin) da **Produzione** (run reale, operatore + admin).

Controller: `SchedaProduzioneController`, `ProduzioneController`, `KioskController`,
`FlussoProduzioneController`. Pagine Vue in `resources/js/Pages/{Schede,Produzioni}`.

---

## 1. Schede di Produzione (template / ricetta)

Una scheda (`schede_produzione`) è il modulo HACCP di un prodotto: `modello` (es.
`M2PO3`), `revisione`, `data_revisione`, `attiva`, `ha_marinatura`. Contiene:

- **Ricette** (`ricette`, `ricette_marinature`): ingredienti previsti con percentuale /
  grammi per kg.
- **Flussi** (`schede_produzione_flussi`): passi del ciclo di lavoro proposti.
- **Imballaggi/Gas template** (`schede_imballaggi`, `schede_gas`).
- **Varianti/pezzature** dal prodotto collegato → sezione "N° Confezioni".

Rotte: `GET /schede` (index, tutti), CRUD **admin-only**, `GET /schede/{scheda}/print`,
`GET /schede/{scheda}/pdf` (scheda **vuota** = template stampabile),
`GET /schede/confronto` (vedi §6).

**Versioning (GAP-D7)**: le schede portano modello+revisione; una produzione registra
la scheda usata, così lo storico resta coerente anche dopo revisioni.

## 2. Produzioni (run reale)

Un run (`produzioni`) è basato su una scheda e cattura tutto ciò che il modulo cartaceo
registra a mano. Campi: `scheda_id`, `lotto_produzione` (**unique**), `data_produzione`,
`quantita_prodotta_kg`, `operatore`, `note`. Sotto-entità catturate:

| Sezione | Tabella | Contenuto |
|---------|---------|-----------|
| Materie prime | `produzioni_materie_prime` | consumo per lotto: `acquisto_riga_id` **XOR** `semilavorato_id`, quantità |
| Confezioni | `ProduzioneConfezione` | N° confezioni per variante/pezzatura |
| Imballaggi | `produzioni_imballaggi_primari` | lotti imballaggio usati |
| Detergenti | `produzioni_detergenti` | lotti detergente usati |
| Gas | `ProduzioneGas` | bombola/lotto gas usato |
| Ciclo di lavoro | `ProduzioneCiclo` | fasi con registrazioni 1/2 + controllo (C) + ordine |
| Metal detector | `ProduzioneMetalDetector` | inizio/fine conf., campione 1/2/3 (OK/KO) |

Rotte: `resource produzioni` (except show/destroy), `DELETE` admin,
`POST /produzioni/{produzione}/semilavorato`, `GET /produzioni/{produzione}/print`,
`/pdf` (registro lavorazione), `/scheda` (scheda compilata), `/etichetta` (QR), `/export`.

### 2a. Bilancio lotto & lock (GAP-D2)
Alla registrazione, dentro una **transazione** con `lockForUpdate`, il sistema verifica
che per ogni lotto la quantità richiesta non superi il **saldo disponibile**
(`ricevuto − consumato in altre produzioni − venduto direttamente`). In caso di
sforamento → `ValidationException` inline (messaggio "Lotto «X»: richiesti … disponibili …").
Il lock evita che due submit concorrenti sovra-consumino lo stesso lotto.

### 2b. Fonte XOR
Ogni riga materia prima deve avere **esattamente una** fonte: lotto d'acquisto
*oppure* semilavorato interno (validazione `after` nel controller).

### 2c. Ricetta enforced (GAP-D3)
Se la scheda ha una ricetta definita, gli ingredienti submitati devono appartenervi;
altrimenti errore. **Nota storica**: questo controllo usava `abort(422)` (pagina
d'errore generica "Oops! 422"); ora lancia una `ValidationException` sul campo
`materie_prime`, mostrata **inline** sul form (coerente con il controllo di bilancio).

## 3. Semilavorati (`lotti_semilavorati`)

Un run può generare un **semilavorato interno** (`POST …/semilavorato`), un lotto
riutilizzabile come materia prima in produzioni a valle. Guardie: registrazione unica
per produzione (lock sul parent); non eliminabile se consumato a valle o se il lotto
finito è stato venduto.

## 4. Modalità Kiosk (reparto / tablet)

`KioskController` (`/produzioni/kiosk`, `/produzioni/kiosk/lookup`): interfaccia
semplificata a tutto schermo per la registrazione della produzione dal reparto —
selezione scheda, scelta ingrediente/lotto, quantità, conferma. Pensata per touch.

## 5. PDF di produzione (due documenti distinti)

| Documento | Rotta | Vista | Uso |
|-----------|-------|-------|-----|
| **Scheda di Produzione (compilata)** | `/produzioni/{id}/scheda` | `pdf/scheda-produzione.blade.php` | Riproduce il modulo cartaceo **M2PO3** con dati reali |
| **Scheda vuota (template)** | `/schede/{id}/pdf` | `pdf/scheda-produzione-vuota.blade.php` | Modulo stampabile da compilare a mano |
| **Registro di Lavorazione** | `/produzioni/{id}/pdf` | `pdf/produzione.blade.php` | Documento interno alternativo (stile moderno) |

La **Scheda di Produzione** è costruita con tabelle Blade dompdf-safe e replica
fedelmente l'M2PO3: testata (logo + `SCHEDA DI PRODUZIONE` + `M2PO3 REVx`), PRODOTTO,
DATA/LOTTO, CODICE PRODOTTO/PEZZATURA/N° CONFEZIONI, MATERIE PRIME (lotto+fornitore),
IMBALLAGGI PRIMARI, GAS, CICLO DI LAVORO (con registrazioni e colonna C), FUNZIONAMENTO
METAL DETECTOR (OK/KO per campione), note dei campioni. Righe vuote di scrittura a mano
riempiono la pagina; dimensionata per stare su **una singola pagina A4** (row-count e
altezze tarati per dompdf). Campioni metal detector e ciclo default da `config/haccp.php`.

## 6. Confronto schede

Funzione **implementata**: dalla lista `/schede` si selezionano ≥2 schede (checkbox) e
si apre `GET /schede/confronto?ids=…` (`SchedaProduzioneController::confronto`, pagina
`Schede/Confronto.vue`). Mostra le schede **affiancate in colonne** (fino a 4) con
confronto riga per riga: prodotto, varianti, ricetta/ingredienti, imballaggi, gas, ciclo.

## 7. Etichette lotto (QR)

`GET /produzioni/{id}/etichetta` genera etichette con **QR** che linkano alla
tracciabilità del lotto (vedi `07`/`08`). Analoghe per acquisti e vendite.
