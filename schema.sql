-- =============================================================================
-- Marche International Food S.R.L. — Database Schema (PostgreSQL 18)
-- Sistema di tracciabilità alimentare HACCP
--
-- Documento di riferimento: rispecchia le migrazioni di dominio fino a
-- 2026_06_23_000008 (audit, imballaggi/detergenti in produzione, semilavorati,
-- conto terzi, vincoli CHECK e indici FK).
--
-- NOTA: le tabelle di framework Laravel (users, sessions, cache, jobs,
-- password_reset_tokens) sono create dalle migrazioni di default e NON sono
-- riportate qui. Le colonne created_by/updated_by referenziano users(id).
-- La fonte di verità resta la cartella database/migrations/.
-- =============================================================================

-- ---------------------------------------------------------------------------
-- ANAGRAFICA BASE
-- ---------------------------------------------------------------------------

CREATE TABLE unita_misura (
    id          BIGSERIAL PRIMARY KEY,
    codice      VARCHAR(20)  NOT NULL UNIQUE,
    descrizione VARCHAR(100),
    tipo        VARCHAR(5)   CHECK (tipo IN ('kg', 'lt', 'n'))
);

CREATE TABLE flussi_produzione (
    id        BIGSERIAL PRIMARY KEY,
    numero    INTEGER      NOT NULL,
    nome      VARCHAR(100) NOT NULL,
    controllo VARCHAR(100),     -- punto di controllo CCP (es. "Temp. Sonda > 75°C")
    misura    VARCHAR(50)       -- etichetta del valore (es. "Temperatura:", "Tempo:")
);

CREATE TABLE fornitori (
    id                   BIGSERIAL PRIMARY KEY,
    codice               VARCHAR(20)  UNIQUE,
    ragione_sociale      VARCHAR(200) NOT NULL,
    tipo                 VARCHAR(30)  NOT NULL
                             CONSTRAINT fornitori_tipo_values
                             CHECK (tipo IN ('alimentare','imballaggio_primario','detergente_secondario','conto_terzi')),
    piva                 VARCHAR(20),
    indirizzo            TEXT,
    email                VARCHAR(100),
    telefono             VARCHAR(30),
    haccp_certificato    BOOLEAN      NOT NULL DEFAULT FALSE,
    haccp_scadenza       DATE,
    certificazioni_note  TEXT,
    moca_certificato     BOOLEAN      NOT NULL DEFAULT FALSE,
    moca_numero          VARCHAR(50),
    attivo               BOOLEAN      NOT NULL DEFAULT TRUE,
    note                 TEXT,
    created_at           TIMESTAMPTZ  DEFAULT NOW(),
    updated_at           TIMESTAMPTZ  DEFAULT NOW()
);

CREATE TABLE clienti (
    id               BIGSERIAL PRIMARY KEY,
    codice_cliente   VARCHAR(20)  NOT NULL UNIQUE,
    ragione_sociale  VARCHAR(200) NOT NULL,
    piva             VARCHAR(20),
    indirizzo        TEXT,
    email            VARCHAR(100),
    telefono         VARCHAR(30),
    attivo           BOOLEAN      NOT NULL DEFAULT TRUE,
    note             TEXT,
    created_at       TIMESTAMPTZ  DEFAULT NOW(),
    updated_at       TIMESTAMPTZ  DEFAULT NOW()
);

CREATE TABLE prodotti (
    id               BIGSERIAL PRIMARY KEY,
    codice_prodotto  VARCHAR(20)  NOT NULL UNIQUE,
    nome             VARCHAR(200) NOT NULL,
    pezzatura_valore NUMERIC(10,3),
    pezzatura_um     VARCHAR(10),
    um_id            BIGINT REFERENCES unita_misura(id),
    attivo           BOOLEAN      NOT NULL DEFAULT TRUE,
    note             TEXT,
    created_at       TIMESTAMPTZ  DEFAULT NOW(),
    updated_at       TIMESTAMPTZ  DEFAULT NOW()
);

CREATE TABLE materie_prime (
    id        BIGSERIAL PRIMARY KEY,
    codice    INTEGER UNIQUE,
    nome      VARCHAR(200) NOT NULL,
    um_id     BIGINT REFERENCES unita_misura(id),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Quali materie prime possono entrare in quali prodotti
CREATE TABLE destinazione_ingredienti (
    id                BIGSERIAL PRIMARY KEY,
    prodotto_id       BIGINT NOT NULL REFERENCES prodotti(id),
    materia_prima_id  BIGINT NOT NULL REFERENCES materie_prime(id),
    UNIQUE (prodotto_id, materia_prima_id)
);

-- ---------------------------------------------------------------------------
-- SCREEN 1 — ALIMENTI: ACQUISTI
-- ---------------------------------------------------------------------------

CREATE TABLE acquisti (
    id               BIGSERIAL PRIMARY KEY,
    fornitore_id     BIGINT       NOT NULL REFERENCES fornitori(id),
    numero_documento VARCHAR(50)  NOT NULL,
    data_documento   DATE         NOT NULL,
    tipo_documento   VARCHAR(10)  NOT NULL DEFAULT 'DDT',
    note             TEXT,
    is_conto_terzi   BOOLEAN      NOT NULL DEFAULT FALSE,   -- 2026_06_23_000008: materiale lavorato per conto terzi
    created_by       BIGINT       REFERENCES users(id) ON DELETE SET NULL,  -- 2026_06_23_000002 audit
    updated_by       BIGINT       REFERENCES users(id) ON DELETE SET NULL,
    created_at       TIMESTAMPTZ  DEFAULT NOW(),
    updated_at       TIMESTAMPTZ  DEFAULT NOW()
);

CREATE TABLE acquisti_righe (
    id                BIGSERIAL PRIMARY KEY,
    acquisto_id       BIGINT        NOT NULL REFERENCES acquisti(id) ON DELETE CASCADE,
    prodotto_id       BIGINT        REFERENCES prodotti(id),
    nome_prodotto     VARCHAR(200),
    um                VARCHAR(10),
    quantita_pz       NUMERIC(10,3),
    quantita_kg       NUMERIC(10,3) NOT NULL,
    lotto             VARCHAR(100),
    lotto_esterno     VARCHAR(100),
    scadenza          DATE,
    data_in           DATE          NOT NULL,
    data_out          DATE,
    nota_credito_ref  VARCHAR(50),
    created_at        TIMESTAMPTZ   DEFAULT NOW(),
    updated_at        TIMESTAMPTZ   DEFAULT NOW(),
    -- lotto e lotto_esterno sono mutuamente esclusivi
    CONSTRAINT lotto_xor CHECK (NOT (lotto IS NOT NULL AND lotto_esterno IS NOT NULL))
);

-- ---------------------------------------------------------------------------
-- SCREEN 1 — ALIMENTI: VENDITE
-- ---------------------------------------------------------------------------

CREATE TABLE vendite (
    id               BIGSERIAL PRIMARY KEY,
    cliente_id       BIGINT       NOT NULL REFERENCES clienti(id),
    numero_documento VARCHAR(50)  NOT NULL,
    data_documento   DATE         NOT NULL,
    tipo_documento   VARCHAR(5)   NOT NULL CHECK (tipo_documento IN ('DDT','FI','NC')),
    note             TEXT,
    created_by       BIGINT       REFERENCES users(id) ON DELETE SET NULL,  -- 2026_06_23_000002 audit
    updated_by       BIGINT       REFERENCES users(id) ON DELETE SET NULL,
    created_at       TIMESTAMPTZ  DEFAULT NOW(),
    updated_at       TIMESTAMPTZ  DEFAULT NOW()
);

CREATE TABLE vendite_righe (
    id            BIGSERIAL PRIMARY KEY,
    vendita_id    BIGINT        NOT NULL REFERENCES vendite(id) ON DELETE CASCADE,
    prodotto_id   BIGINT        REFERENCES prodotti(id),
    nome_prodotto VARCHAR(200),
    pezzatura_gr  NUMERIC(10,3),
    um            VARCHAR(10),
    quantita_pz   NUMERIC(10,3),
    quantita_kg   NUMERIC(10,3) NOT NULL,
    lotto         VARCHAR(100),
    lotto_esterno VARCHAR(100),
    scadenza      DATE,
    -- 2026_06_23_000005 / _000007: legano una riga di vendita alla produzione
    -- o al lotto di acquisto (rivendita diretta). Le FK verso produzioni sono
    -- aggiunte in fondo al file (ALTER TABLE) per rispettare l'ordine di creazione.
    produzione_id    BIGINT,
    acquisto_riga_id BIGINT     REFERENCES acquisti_righe(id),
    created_at    TIMESTAMPTZ   DEFAULT NOW(),
    updated_at    TIMESTAMPTZ   DEFAULT NOW(),
    CONSTRAINT lotto_xor CHECK (NOT (lotto IS NOT NULL AND lotto_esterno IS NOT NULL))
);

CREATE TABLE bolle_reso (
    id               BIGSERIAL PRIMARY KEY,
    vendita_riga_id  BIGINT        NOT NULL REFERENCES vendite_righe(id),
    numero_bolla     VARCHAR(50),
    quantita_pz      NUMERIC(10,3),
    quantita_kg      NUMERIC(10,3) NOT NULL,
    data_reso        DATE          NOT NULL,
    note             TEXT,
    created_by       BIGINT        REFERENCES users(id) ON DELETE SET NULL,  -- 2026_06_23_000002 audit
    updated_by       BIGINT        REFERENCES users(id) ON DELETE SET NULL,
    created_at       TIMESTAMPTZ   DEFAULT NOW(),
    updated_at       TIMESTAMPTZ   DEFAULT NOW()
);

CREATE TABLE note_credito (
    id               BIGSERIAL PRIMARY KEY,
    vendita_id       BIGINT        REFERENCES vendite(id),
    bolla_reso_id    BIGINT        REFERENCES bolle_reso(id),
    numero_documento VARCHAR(50)   NOT NULL,
    data_documento   DATE          NOT NULL,
    importo          NUMERIC(12,2),
    note             TEXT,
    created_by       BIGINT        REFERENCES users(id) ON DELETE SET NULL,  -- 2026_06_23_000002 audit
    updated_by       BIGINT        REFERENCES users(id) ON DELETE SET NULL,
    created_at       TIMESTAMPTZ   DEFAULT NOW(),
    updated_at       TIMESTAMPTZ   DEFAULT NOW(),
    -- 2026_06_23_000003: una nota di credito deve riferirsi ad almeno un genitore
    CONSTRAINT note_credito_requires_parent
        CHECK (vendita_id IS NOT NULL OR bolla_reso_id IS NOT NULL)
);

-- ---------------------------------------------------------------------------
-- SCREEN 2 — IMBALLAGGI
-- ---------------------------------------------------------------------------

CREATE TABLE lotti_imballaggi_primari (
    id              BIGSERIAL PRIMARY KEY,
    fornitore_id    BIGINT        NOT NULL REFERENCES fornitori(id),
    codice_articolo VARCHAR(50),
    componente      VARCHAR(200)  NOT NULL,
    um              VARCHAR(10),
    quantita        NUMERIC(10,3),
    lotto           VARCHAR(100),
    numero_ddt      VARCHAR(50),
    data_in         DATE          NOT NULL,
    data_out        DATE,
    note            TEXT,
    created_by      BIGINT        REFERENCES users(id) ON DELETE SET NULL,  -- 2026_06_23_000002 audit
    updated_by      BIGINT        REFERENCES users(id) ON DELETE SET NULL,
    created_at      TIMESTAMPTZ   DEFAULT NOW(),
    updated_at      TIMESTAMPTZ   DEFAULT NOW()
);

CREATE TABLE lotti_detergenti (
    id              BIGSERIAL PRIMARY KEY,
    fornitore_id    BIGINT        NOT NULL REFERENCES fornitori(id),
    codice_articolo VARCHAR(50),
    componente      VARCHAR(200)  NOT NULL,
    um              VARCHAR(10),
    quantita        NUMERIC(10,3),
    lotto           VARCHAR(100),
    scadenza        DATE,
    numero_ddt      VARCHAR(50),
    data_in         DATE          NOT NULL,
    data_out        DATE,
    note            TEXT,
    created_by      BIGINT        REFERENCES users(id) ON DELETE SET NULL,  -- 2026_06_23_000002 audit
    updated_by      BIGINT        REFERENCES users(id) ON DELETE SET NULL,
    created_at      TIMESTAMPTZ   DEFAULT NOW(),
    updated_at      TIMESTAMPTZ   DEFAULT NOW()
);

-- ---------------------------------------------------------------------------
-- SCREEN 3 — PRODUZIONE
-- ---------------------------------------------------------------------------

CREATE TABLE schede_produzione (
    id              BIGSERIAL PRIMARY KEY,
    prodotto_id     BIGINT       NOT NULL REFERENCES prodotti(id),
    modello         VARCHAR(20)  NOT NULL,
    revisione       INTEGER      NOT NULL DEFAULT 0,
    data_revisione  DATE         NOT NULL,
    ha_marinatura   BOOLEAN      NOT NULL DEFAULT FALSE,
    attiva          BOOLEAN      NOT NULL DEFAULT TRUE,
    note            TEXT,
    created_at      TIMESTAMPTZ  DEFAULT NOW(),
    updated_at      TIMESTAMPTZ  DEFAULT NOW(),
    UNIQUE (prodotto_id, revisione)
);

CREATE TABLE schede_produzione_flussi (
    id                BIGSERIAL PRIMARY KEY,
    scheda_id         BIGINT       NOT NULL REFERENCES schede_produzione(id) ON DELETE CASCADE,
    flusso_id         BIGINT       NOT NULL REFERENCES flussi_produzione(id),
    ordine            INTEGER      NOT NULL,
    valore_controllo  VARCHAR(100),
    tempo_minuti      INTEGER
);

CREATE TABLE ricette (
    id                BIGSERIAL PRIMARY KEY,
    scheda_id         BIGINT        NOT NULL REFERENCES schede_produzione(id) ON DELETE CASCADE,
    materia_prima_id  BIGINT        NOT NULL REFERENCES materie_prime(id),
    fornitore_id      BIGINT        REFERENCES fornitori(id),
    percentuale       NUMERIC(6,3),
    grammi_per_kg     NUMERIC(8,3),
    um                VARCHAR(10),
    ordine            INTEGER
);

CREATE TABLE ricette_marinature (
    id                BIGSERIAL PRIMARY KEY,
    scheda_id         BIGINT        NOT NULL REFERENCES schede_produzione(id) ON DELETE CASCADE,
    materia_prima_id  BIGINT        NOT NULL REFERENCES materie_prime(id),
    fornitore_id      BIGINT        REFERENCES fornitori(id),
    litri_grammi      NUMERIC(8,3),
    um                VARCHAR(10),
    ordine            INTEGER
);

CREATE TABLE produzioni (
    id                    BIGSERIAL PRIMARY KEY,
    scheda_id             BIGINT        NOT NULL REFERENCES schede_produzione(id),
    lotto_produzione      VARCHAR(100)  NOT NULL UNIQUE,
    data_produzione       DATE          NOT NULL,
    quantita_prodotta_kg  NUMERIC(10,3),
    operatore             VARCHAR(100),
    note                  TEXT,
    created_by            BIGINT        REFERENCES users(id) ON DELETE SET NULL,  -- 2026_06_23_000002 audit
    updated_by            BIGINT        REFERENCES users(id) ON DELETE SET NULL,
    created_at            TIMESTAMPTZ   DEFAULT NOW(),
    updated_at            TIMESTAMPTZ   DEFAULT NOW()
);

-- 2026_06_23_000006: lotti semilavorati generati da una produzione e
-- riutilizzabili come ingrediente interno in produzioni successive.
CREATE TABLE lotti_semilavorati (
    id               BIGSERIAL PRIMARY KEY,
    produzione_id    BIGINT        NOT NULL REFERENCES produzioni(id) ON DELETE CASCADE,
    lotto            VARCHAR(100)  NOT NULL UNIQUE,
    nome_prodotto    VARCHAR(200)  NOT NULL,
    quantita_kg      NUMERIC(10,3) NOT NULL,
    data_produzione  DATE          NOT NULL,
    data_out         DATE,
    note             TEXT,
    created_at       TIMESTAMPTZ   DEFAULT NOW(),
    updated_at       TIMESTAMPTZ   DEFAULT NOW()
);

-- Cuore della tracciabilità HACCP: lega ogni run di produzione ai lotti
-- esatti di acquisto (o ai semilavorati interni) utilizzati per ogni ingrediente.
-- Tracciabilità inversa: acquisti_righe → produzioni_materie_prime
--   → produzioni → vendite_righe → vendite → clienti
CREATE TABLE produzioni_materie_prime (
    id                BIGSERIAL PRIMARY KEY,
    produzione_id     BIGINT        NOT NULL REFERENCES produzioni(id) ON DELETE CASCADE,
    -- 2026_06_23_000006: acquisto_riga_id reso nullable; aggiunto semilavorato_id.
    acquisto_riga_id  BIGINT        REFERENCES acquisti_righe(id),
    semilavorato_id   BIGINT        REFERENCES lotti_semilavorati(id) ON DELETE SET NULL,
    materia_prima_id  BIGINT        NOT NULL REFERENCES materie_prime(id),
    quantita_kg       NUMERIC(10,3) NOT NULL,
    -- La fonte dell'ingrediente è esattamente una: lotto d'acquisto XOR semilavorato.
    CONSTRAINT source_exactly_one CHECK (
        (acquisto_riga_id IS NOT NULL AND semilavorato_id IS NULL) OR
        (acquisto_riga_id IS NULL     AND semilavorato_id IS NOT NULL)
    )
);

-- 2026_06_23_000004: collega i lotti di imballaggio primario (MOCA) a una produzione.
CREATE TABLE produzioni_imballaggi_primari (
    id                    BIGSERIAL PRIMARY KEY,
    produzione_id         BIGINT        NOT NULL REFERENCES produzioni(id) ON DELETE CASCADE,
    lotto_imballaggio_id  BIGINT        NOT NULL REFERENCES lotti_imballaggi_primari(id) ON DELETE RESTRICT,
    quantita_usata        NUMERIC(12,3),
    note                  TEXT,
    created_at            TIMESTAMPTZ   DEFAULT NOW(),
    updated_at            TIMESTAMPTZ   DEFAULT NOW()
);

-- 2026_06_23_000004: collega i lotti di detergente/sanificante a una produzione.
CREATE TABLE produzioni_detergenti (
    id                  BIGSERIAL PRIMARY KEY,
    produzione_id       BIGINT        NOT NULL REFERENCES produzioni(id) ON DELETE CASCADE,
    lotto_detergente_id BIGINT        NOT NULL REFERENCES lotti_detergenti(id) ON DELETE RESTRICT,
    quantita_usata      NUMERIC(12,3),
    note                TEXT,
    created_at          TIMESTAMPTZ   DEFAULT NOW(),
    updated_at          TIMESTAMPTZ   DEFAULT NOW()
);

-- =============================================================================
-- FOREIGN KEY POST-CREAZIONE
-- (aggiunte via ALTER perché la tabella di destinazione è definita più in basso)
-- =============================================================================

-- 2026_06_23_000005: riga di vendita → produzione di provenienza
ALTER TABLE vendite_righe
    ADD CONSTRAINT vendite_righe_produzione_id_foreign
    FOREIGN KEY (produzione_id) REFERENCES produzioni(id);

-- =============================================================================
-- INDICI
-- =============================================================================

CREATE INDEX idx_acquisti_fornitore        ON acquisti(fornitore_id);
CREATE INDEX idx_acquisti_data             ON acquisti(data_documento);
CREATE INDEX idx_acquisti_righe_lotto      ON acquisti_righe(lotto);
CREATE INDEX idx_acquisti_righe_lotto_est  ON acquisti_righe(lotto_esterno);
CREATE INDEX idx_acquisti_righe_data_in    ON acquisti_righe(data_in);
CREATE INDEX idx_vendite_cliente           ON vendite(cliente_id);
CREATE INDEX idx_vendite_data              ON vendite(data_documento);
CREATE INDEX idx_vendite_righe_lotto       ON vendite_righe(lotto);
CREATE INDEX idx_prodotti_codice           ON prodotti(codice_prodotto);
CREATE INDEX idx_produzioni_scheda         ON produzioni(scheda_id);
CREATE INDEX idx_produzioni_lotto          ON produzioni(lotto_produzione);
CREATE INDEX idx_prod_mp_produzione        ON produzioni_materie_prime(produzione_id);
CREATE INDEX idx_prod_mp_acquisto          ON produzioni_materie_prime(acquisto_riga_id);

-- 2026_06_23_000001: 13 indici FK mancanti + idx_vendite_righe_lotto_ext (GAP-T3/T6)
CREATE INDEX idx_acquisti_righe_acquisto   ON acquisti_righe(acquisto_id);
CREATE INDEX idx_vendite_righe_vendita     ON vendite_righe(vendita_id);
CREATE INDEX idx_bolle_reso_vendita_riga   ON bolle_reso(vendita_riga_id);
CREATE INDEX idx_note_credito_vendita      ON note_credito(vendita_id);
CREATE INDEX idx_note_credito_bolla        ON note_credito(bolla_reso_id);
CREATE INDEX idx_schede_flussi_scheda      ON schede_produzione_flussi(scheda_id);
CREATE INDEX idx_schede_flussi_flusso      ON schede_produzione_flussi(flusso_id);
CREATE INDEX idx_ricette_scheda            ON ricette(scheda_id);
CREATE INDEX idx_ricette_mp                ON ricette(materia_prima_id);
CREATE INDEX idx_ricette_mar_scheda        ON ricette_marinature(scheda_id);
CREATE INDEX idx_prod_mp_materia           ON produzioni_materie_prime(materia_prima_id);
CREATE INDEX idx_imb_primari_fornitore     ON lotti_imballaggi_primari(fornitore_id);
CREATE INDEX idx_detergenti_fornitore      ON lotti_detergenti(fornitore_id);
CREATE INDEX idx_vendite_righe_lotto_ext   ON vendite_righe(lotto_esterno);

-- 2026_06_23_000004/_000005/_000006: indici delle nuove FK
CREATE INDEX idx_vendite_righe_produzione  ON vendite_righe(produzione_id);
CREATE INDEX idx_prod_mp_semilavorato      ON produzioni_materie_prime(semilavorato_id);
CREATE INDEX idx_prod_imb_produzione       ON produzioni_imballaggi_primari(produzione_id);
CREATE INDEX idx_prod_imb_lotto            ON produzioni_imballaggi_primari(lotto_imballaggio_id);
CREATE INDEX idx_prod_det_produzione       ON produzioni_detergenti(produzione_id);
CREATE INDEX idx_prod_det_lotto            ON produzioni_detergenti(lotto_detergente_id);

-- Fine schema (allineato alle migrazioni fino a 2026_06_23_000008).
