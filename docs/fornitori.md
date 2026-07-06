# Fornitori — Come funziona

Questo documento spiega dall'inizio alla fine come funziona il modulo Fornitori: la struttura del database, il codice PHP, le pagine Vue, e come tutto si collega al resto dell'applicazione.

---

## 1. Cos'è un Fornitore

Un **fornitore** è un'azienda da cui Marche International Food acquista qualcosa. Esistono tre tipi:

| Tipo (valore nel DB)      | Cosa fornisce                          | Modulo dove appare           |
|---------------------------|----------------------------------------|------------------------------|
| `alimentare`              | Materie prime (carne, spezie, ecc.)    | Acquisti                     |
| `conto_terzi`             | Lavorazione per conto terzi            | Acquisti (con `is_conto_terzi = TRUE`) |
| `imballaggio_primario`    | Imballaggi a contatto con il cibo (MOCA) | Imballaggi → tab "Primari"   |
| `detergente_secondario`   | Detergenti / imballaggi secondari      | Imballaggi → tab "Detergenti"|

Il tipo non è solo un'etichetta: **controlla quale sezione del database può referenziare il fornitore** e **quali campi di certificazione appaiono nel form** (HACCP per alimentari, MOCA per imballaggi primari).

---

## 2. La tabella SQL `fornitori`

Questa è la tabella nel database PostgreSQL, creata dal file di migrazione `2026_06_18_230003_create_fornitori_table.php`.

```sql
CREATE TABLE fornitori (
    id                  BIGSERIAL PRIMARY KEY,
    codice              VARCHAR(20)  UNIQUE,           -- codice interno opzionale (es. "FOR001")
    ragione_sociale     VARCHAR(200) NOT NULL,          -- nome ufficiale dell'azienda
    tipo                VARCHAR(30)  NOT NULL,          -- 'alimentare' | 'conto_terzi' | 'imballaggio_primario' | 'detergente_secondario'
    piva                VARCHAR(20),                   -- Partita IVA
    indirizzo           TEXT,
    email               VARCHAR(100),
    telefono            VARCHAR(30),
    haccp_certificato   BOOLEAN      DEFAULT FALSE,    -- ha il certificato HACCP?
    haccp_scadenza      DATE,                          -- quando scade il certificato
    certificazioni_note TEXT,                          -- ISO, BRC, IFS, ecc.
    moca_certificato    BOOLEAN      DEFAULT FALSE,    -- ha il certificato MOCA?
    moca_numero         VARCHAR(50),                   -- numero del certificato MOCA
    attivo              BOOLEAN      DEFAULT TRUE,      -- se FALSE, non appare nelle dropdown
    note                TEXT,
    created_at          TIMESTAMPTZ,
    updated_at          TIMESTAMPTZ
);
```

### Colonna per colonna

| Colonna | Tipo | Obbligatorio | Spiegazione |
|---|---|---|---|
| `id` | BIGSERIAL | sì (auto) | Numero intero generato automaticamente. È la chiave primaria — ogni fornitore ha un ID unico che non cambia mai. |
| `codice` | VARCHAR(20) | no | Un codice che l'azienda assegna internamente (es. "FOR001"). Non può essere duplicato tra fornitori (`UNIQUE`). |
| `ragione_sociale` | VARCHAR(200) | **sì** | Il nome legale dell'azienda fornitrice. È l'unico campo obbligatorio oltre a `tipo`. |
| `tipo` | VARCHAR(30) | **sì** | Uno dei quattro valori fissi. Determina in quale sezione dell'app il fornitore può essere usato. |
| `piva` | VARCHAR(20) | no | Partita IVA, usata solo per riferimento. |
| `indirizzo` | TEXT | no | Indirizzo completo in formato libero. |
| `email` | VARCHAR(100) | no | Email di contatto. |
| `telefono` | VARCHAR(30) | no | Telefono. |
| `haccp_certificato` | BOOLEAN | no | `TRUE` se il fornitore ha la certificazione HACCP. Visibile solo per i fornitori di tipo `alimentare`. |
| `haccp_scadenza` | DATE | no | Data di scadenza del certificato HACCP. |
| `certificazioni_note` | TEXT | no | Testo libero per annotare certificazioni volontarie come ISO 22000, BRC, IFS. |
| `moca_certificato` | BOOLEAN | no | `TRUE` se ha il certificato MOCA (Materiali e Oggetti a Contatto con Alimenti). Solo per `imballaggio_primario`. |
| `moca_numero` | VARCHAR(50) | no | Numero del certificato MOCA. |
| `attivo` | BOOLEAN | no | Quando è `FALSE`, il fornitore non compare nelle dropdown degli acquisti/imballaggi. Utile per "archiviare" fornitori senza cancellarli. |
| `note` | TEXT | no | Campo libero per annotazioni interne. |
| `created_at` / `updated_at` | TIMESTAMPTZ | sì (auto) | Laravel li aggiorna automaticamente ad ogni creazione e modifica. |

---

## 3. Connessioni con le altre tabelle (Chiavi Esterne)

Un fornitore non esiste in isolamento: è **referenziato da tre altre tabelle** tramite chiavi esterne (`FOREIGN KEY`).

```
fornitori (id)
    │
    ├── acquisti.fornitore_id          → ogni acquisto/DDT appartiene a UN fornitore alimentare
    │
    ├── lotti_imballaggi_primari.fornitore_id  → ogni lotto di imballaggio appartiene a UN fornitore MOCA
    │
    └── lotti_detergenti.fornitore_id  → ogni lotto di detergente appartiene a UN fornitore detergente
```

### Cosa significa nella pratica

- Se un fornitore viene **eliminato**, il database rifiuta l'operazione se ha acquisti o lotti collegati (protezione automatica integrità referenziale di PostgreSQL).
- Se un fornitore viene **disattivato** (`attivo = FALSE`), i dati storici restano, ma non appare più nelle dropdown quando si crea un nuovo acquisto.
- L'`id` del fornitore viene salvato come `fornitore_id` nelle tabelle figlie — non il nome, non il codice, solo il numero intero. Questo permette di rinominare un fornitore senza rompere niente.

### SQL della relazione in `acquisti`

```sql
-- Quando si crea la tabella acquisti:
ALTER TABLE acquisti
  ADD CONSTRAINT acquisti_fornitore_id_foreign
  FOREIGN KEY (fornitore_id)
  REFERENCES fornitori(id);

-- Query per vedere tutti gli acquisti di un fornitore:
SELECT a.numero_documento, a.data_documento, a.tipo_documento
FROM acquisti a
WHERE a.fornitore_id = 5;   -- dove 5 è l'id del fornitore

-- Query con JOIN (come lo fa Laravel internamente):
SELECT a.numero_documento, a.data_documento, f.ragione_sociale
FROM acquisti a
JOIN fornitori f ON f.id = a.fornitore_id
WHERE f.tipo = 'alimentare'
ORDER BY a.data_documento DESC;
```

---

## 4. Il Model Laravel — `app/Models/Fornitore.php`

Il Model è il layer PHP che parla con la tabella SQL. Non scrive mai SQL a mano — usa Eloquent ORM di Laravel.

```php
class Fornitore extends Model
{
    protected $table = 'fornitori';   // dice a Laravel quale tabella usare

    protected $fillable = [           // lista delle colonne che possono essere scritte in massa
        'codice', 'ragione_sociale', 'tipo', 'piva', 'indirizzo',
        'email', 'telefono', 'haccp_certificato', 'haccp_scadenza',
        'certificazioni_note', 'moca_certificato', 'moca_numero',
        'attivo', 'note',
    ];

    protected $casts = [              // converte i tipi automaticamente
        'haccp_certificato' => 'boolean',   // '0'/'1' nel DB → true/false in PHP
        'haccp_scadenza'    => 'date',      // stringa nel DB → oggetto Carbon (data)
        'moca_certificato'  => 'boolean',
        'attivo'            => 'boolean',
    ];
}
```

### Perché `$fillable`?

Senza `$fillable`, Laravel rifiuta di salvare dati che arrivano dall'esterno (protezione contro mass assignment attacks). Ogni colonna che vuoi poter scrivere tramite `create()` o `update()` deve stare qui.

### Come Eloquent traduce le operazioni in SQL

| Codice PHP | SQL generato |
|---|---|
| `Fornitore::all()` | `SELECT * FROM fornitori` |
| `Fornitore::find(5)` | `SELECT * FROM fornitori WHERE id = 5 LIMIT 1` |
| `Fornitore::create([...])` | `INSERT INTO fornitori (...) VALUES (...)` |
| `$f->update([...])` | `UPDATE fornitori SET ... WHERE id = 5` |
| `$f->delete()` | `DELETE FROM fornitori WHERE id = 5` |
| `Fornitore::where('tipo', 'alimentare')->get()` | `SELECT * FROM fornitori WHERE tipo = 'alimentare'` |

---

## 5. Il Controller — `app/Http/Controllers/FornitoreController.php`

Il Controller è il **punto di ingresso HTTP**. Quando un browser fa una richiesta, Laravel smista la richiesta al metodo giusto del controller.

### I 5 metodi

#### `index(Request $request)` — Mostra la lista

```php
public function index(Request $request)
{
    $fornitori = Fornitore::query()
        ->when($request->search, fn($q, $s) =>
            $q->where('ragione_sociale', 'ilike', "%{$s}%")
              ->orWhere('codice', 'ilike', "%{$s}%")
        )
        ->when($request->tipo, fn($q, $t) => $q->where('tipo', $t))
        ->orderBy('ragione_sociale')
        ->paginate(25)
        ->withQueryString();

    return Inertia::render('Fornitori/Index', [
        'fornitori' => $fornitori,
        'filters'   => $request->only(['search', 'tipo']),
    ]);
}
```

Cosa fa passo per passo:
1. Inizia una query sulla tabella `fornitori`
2. Se nella URL c'è `?search=abc` → aggiunge `WHERE ragione_sociale ILIKE '%abc%' OR codice ILIKE '%abc%'` (`ILIKE` è LIKE case-insensitive di PostgreSQL)
3. Se c'è `?tipo=alimentare` → aggiunge `WHERE tipo = 'alimentare'`
4. Ordina per ragione sociale
5. Prende solo 25 righe alla volta (paginazione)
6. Manda i dati alla pagina Vue tramite Inertia

#### `create()` — Mostra il form vuoto

```php
public function create()
{
    return Inertia::render('Fornitori/Form', ['fornitore' => null]);
}
```

Manda `null` come `fornitore` alla pagina Vue. Il form Vue lo usa per capire se è in modalità "crea" o "modifica".

#### `store(Request $request)` — Salva il nuovo fornitore

```php
public function store(Request $request)
{
    $data = $this->validated($request);   // valida i dati
    Fornitore::create($data);             // INSERT SQL
    return redirect()->route('fornitori.index')
        ->with('success', 'Fornitore creato con successo.');
}
```

Cosa fa:
1. `validated()` controlla che i dati siano corretti (ragione sociale non vuota, tipo valido, ecc.). Se qualcosa è sbagliato, torna indietro con errori senza toccare il DB.
2. `Fornitore::create($data)` esegue `INSERT INTO fornitori (...) VALUES (...)`.
3. Redirige alla lista con un messaggio di successo (che AppLayout mostra come toast verde).

#### `edit(Fornitore $fornitore)` — Mostra il form compilato

```php
public function edit(Fornitore $fornitore)
{
    return Inertia::render('Fornitori/Form', ['fornitore' => $fornitore]);
}
```

Laravel risolve `{fornitore}` nella URL automaticamente: se la URL è `/fornitori/5/edit`, esegue `SELECT * FROM fornitori WHERE id = 5` e passa il risultato al metodo. Questo si chiama **Route Model Binding**.

#### `update(Request $request, Fornitore $fornitore)` — Salva le modifiche

```php
public function update(Request $request, Fornitore $fornitore)
{
    $data = $this->validated($request, $fornitore->id);
    $fornitore->update($data);
    return redirect()->route('fornitori.index')
        ->with('success', 'Fornitore aggiornato con successo.');
}
```

`UPDATE fornitori SET ragione_sociale = '...', tipo = '...' WHERE id = 5`

Nota: `$fornitore->id` viene passato a `validated()` per escludere il fornitore corrente dal controllo di unicità del `codice` (altrimenti salvare senza cambiare il codice darebbe errore "codice già esistente").

#### `destroy(Fornitore $fornitore)` — Elimina

```php
public function destroy(Fornitore $fornitore)
{
    $fornitore->delete();
    return redirect()->route('fornitori.index')
        ->with('success', 'Fornitore eliminato.');
}
```

`DELETE FROM fornitori WHERE id = 5`

Se il fornitore ha acquisti collegati, PostgreSQL blocca la cancellazione con un errore di integrità referenziale. Il codice attuale non gestisce questo caso in modo amichevole — sarebbe da aggiungere un controllo preventivo.

### La validazione

```php
private function validated(Request $request, ?int $ignoreId = null): array
{
    return $request->validate([
        'codice'          => "nullable|string|max:20|unique:fornitori,codice,{$ignoreId}",
        'ragione_sociale' => 'required|string|max:200',
        'tipo'            => 'required|in:alimentare,conto_terzi,imballaggio_primario,detergente_secondario',
        'piva'            => 'nullable|string|max:20',
        'email'           => 'nullable|email|max:100',
        'haccp_certificato' => 'boolean',
        'haccp_scadenza'  => 'nullable|date',
        'moca_certificato'=> 'boolean',
        // ...
    ]);
}
```

Le regole di validazione funzionano così:
- `required` → il campo non può essere vuoto
- `nullable` → il campo può essere vuoto/null
- `string|max:200` → deve essere testo, massimo 200 caratteri
- `in:alimentare,conto_terzi,...` → deve essere uno di questi valori esatti
- `unique:fornitori,codice,{$ignoreId}` → deve essere unico nella colonna `codice` della tabella `fornitori`, ignorando la riga con id `$ignoreId`
- `email` → deve essere formato email valido
- `boolean` → deve essere true o false

Se una regola fallisce, Laravel non esegue nessun SQL e torna alla pagina con gli errori evidenziati in rosso.

---

## 6. Le Route — `routes/web.php`

```php
// Accessibile a tutti gli utenti autenticati
Route::get('fornitori', [FornitoreController::class, 'index'])
    ->name('fornitori.index');

// Solo admin
Route::middleware('admin')->group(function () {
    Route::resource('fornitori', FornitoreController::class)
        ->except(['show', 'index']);
});
```

Questo genera automaticamente queste URL:

| Metodo HTTP | URL | Controller method | Chi può accedere |
|---|---|---|---|
| GET | `/fornitori` | `index()` | tutti |
| GET | `/fornitori/create` | `create()` | admin |
| POST | `/fornitori` | `store()` | admin |
| GET | `/fornitori/{id}/edit` | `edit()` | admin |
| PUT | `/fornitori/{id}` | `update()` | admin |
| DELETE | `/fornitori/{id}` | `destroy()` | admin |

`Route::resource` è un helper che crea le 7 rotte standard di un CRUD con una sola riga. `except(['show', 'index'])` esclude le due rotte che sono già definite altrove.

---

## 7. Le pagine Vue

### `Fornitori/Index.vue` — la lista

Riceve da Laravel (via Inertia) due prop:
- `fornitori` — oggetto paginato con `.data` (array dei fornitori), `.current_page`, `.last_page`, `.prev_page_url`, `.next_page_url`
- `filters` — `{ search: '...', tipo: '...' }` per pre-popolare i filtri

Cosa fa il componente:
1. Mostra i filtri (barra di ricerca + bottoni tipo)
2. Mostra la tabella con DataTable di PrimeVue
3. Quando l'utente scrive nella ricerca → chiama `router.get('/fornitori', { search: ... })` (Inertia fa una richiesta AJAX, Laravel risponde con nuovi dati, la pagina si aggiorna senza reload)
4. I bottoni Modifica/Elimina appaiono solo se `isAdmin` è true (controllato via `page.props.auth.user.role`)

### `Fornitori/Form.vue` — il form

Riceve da Laravel:
- `fornitore` — `null` se nuovo, oppure l'oggetto fornitore se modifica

Il form usa `useForm()` di Inertia — è un helper che:
- Traccia lo stato di tutti i campi
- Gestisce gli errori di validazione campo per campo
- Mostra un indicatore di caricamento durante il salvataggio
- In caso di errori Laravel, popola automaticamente i messaggi rossi sotto ogni campo

```js
const form = useForm({
    ragione_sociale: props.fornitore?.ragione_sociale ?? '',
    tipo: props.fornitore?.tipo ?? '',
    // ...
});

function submit() {
    if (isEdit.value) {
        form.put(`/fornitori/${props.fornitore.id}`);   // PUT HTTP → update()
    } else {
        form.post('/fornitori');                         // POST HTTP → store()
    }
}
```

Le sezioni HACCP e MOCA appaiono condizionalmente:
- `v-if="form.tipo === 'alimentare'"` → mostra HACCP solo per alimentari
- `v-if="form.tipo === 'imballaggio_primario'"` → mostra MOCA solo per imballaggi primari

---

## 8. Il flusso completo — esempio reale

### Scenario: un operatore admin crea un nuovo fornitore alimentare

```
1. L'admin clicca "Nuovo Fornitore" in /fornitori
   → Browser fa GET /fornitori/create
   → Laravel chiama FornitoreController::create()
   → Inertia manda null come fornitore
   → Vue mostra il form vuoto

2. L'admin compila il form e clicca "Crea fornitore"
   → Vue chiama form.post('/fornitori') con i dati
   → Browser fa POST /fornitori con body JSON:
     {
       "ragione_sociale": "Freschi del Sud S.r.l.",
       "tipo": "alimentare",
       "codice": "FOR042",
       "haccp_certificato": true,
       "haccp_scadenza": "2026-12-31",
       "attivo": true
     }

3. Laravel riceve la richiesta
   → Verifica middleware 'auth' (utente loggato?) ✓
   → Verifica middleware 'admin' (ruolo admin?) ✓
   → Chiama FornitoreController::store()
   → Esegue la validazione

4. Validazione passa → Laravel esegue:
   INSERT INTO fornitori
     (ragione_sociale, tipo, codice, haccp_certificato, haccp_scadenza, attivo, created_at, updated_at)
   VALUES
     ('Freschi del Sud S.r.l.', 'alimentare', 'FOR042', TRUE, '2026-12-31', TRUE, now(), now())
   RETURNING id;
   -- il DB assegna automaticamente id = 43 (per esempio)

5. Laravel redirige a /fornitori con flash 'success'
   → Inertia aggiorna la pagina
   → AppLayout mostra il toast verde "Fornitore creato con successo"
   → Il nuovo fornitore appare nella lista

6. D'ora in poi, quando si crea un acquisto:
   SELECT id, ragione_sociale FROM fornitori
   WHERE tipo = 'alimentare' AND attivo = TRUE
   ORDER BY ragione_sociale;
   -- "Freschi del Sud S.r.l." appare nella dropdown
```

### Scenario: creazione di un acquisto che usa il fornitore

```sql
-- Quando si registra un DDT da "Freschi del Sud S.r.l.":
INSERT INTO acquisti (fornitore_id, numero_documento, data_documento, tipo_documento)
VALUES (43, 'DDT/2024/0156', '2024-11-15', 'DDT');
-- fornitore_id = 43 → punta a "Freschi del Sud S.r.l."

-- Le righe del DDT:
INSERT INTO acquisti_righe
  (acquisto_id, nome_prodotto, quantita_kg, lotto, data_in)
VALUES
  (1001, 'Lonza di maiale', 250.500, 'LT2024-0890', '2024-11-15');

-- Per recuperare l'acquisto con il nome del fornitore (JOIN):
SELECT a.numero_documento, f.ragione_sociale, r.nome_prodotto, r.quantita_kg, r.lotto
FROM acquisti a
JOIN fornitori f    ON f.id = a.fornitore_id
JOIN acquisti_righe r ON r.acquisto_id = a.id
WHERE a.id = 1001;
```

---

## 9. Dove si tocca il codice se si vuole aggiungere qualcosa

| Cosa si vuole aggiungere | File da modificare |
|---|---|
| Nuovo campo (es. `sito_web`) | Migration (aggiungi colonna) + `Fornitore.php` ($fillable) + `FornitoreController.php` (validated) + `Form.vue` (campo HTML) |
| Nuovo tipo di fornitore | `FornitoreController.php` (regola `in:...`) + `Form.vue` (tipoOptions array) + eventuale nuova tabella che lo referenzia |
| Mostrare gli acquisti di un fornitore nella scheda | `Fornitore.php` (aggiungi relazione `acquisti()`) + `FornitoreController.php` (edit: carica `$fornitore->load('acquisti')`) + `Form.vue` (mostra la lista) |
| Impedire la cancellazione se ha acquisti | `FornitoreController.php` (destroy: controlla `$fornitore->acquisti()->exists()` prima di delete) |
| Cercare anche per P.IVA | `FornitoreController.php` (index: aggiungi `->orWhere('piva', 'ilike', ...)`) |

---

## Estrazione AI del certificato (Epic 2)

Nel form Fornitore (tipo `alimentare` o `imballaggio_primario`) è disponibile il
caricamento di un certificato **HACCP/MOCA** (PDF o immagine). Il pulsante
"Estrai dati" invia il file a `POST /fornitori/estrai-certificato` (solo admin),
che tramite `CertificateExtractionService` interroga una vision LLM (Anthropic
Claude, configurabile in `config/ai.php`) e restituisce `haccp_scadenza` e
`moca_numero`, compilando automaticamente i campi del form (da verificare prima
del salvataggio).

- Richiede `ANTHROPIC_API_KEY`; senza chiave la funzione risponde 422 con un
  messaggio e l'operatore compila a mano.
- Vedi `INTEGRATIONS.md` per dettagli su richiesta/risposta e sulle implicazioni
  di privacy nell'invio dei certificati a un servizio esterno.
