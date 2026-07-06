<template>
  <AppLayout>
    <div class="page-header">
      <div>
        <h1 class="page-title">Tracciabilità Lotti</h1>
        <p class="page-sub">Ricerca forward (lotto acquisto → produzioni) e reverse (lotto produzione → materie prime)</p>
      </div>
    </div>

    <!-- Search bar -->
    <div class="search-card">
      <form @submit.prevent="doSearch" class="search-form">
        <IconField style="flex:1">
          <InputIcon class="pi pi-search" />
          <InputText
            v-model="term"
            placeholder="Inserisci numero lotto, nome prodotto, o lotto produzione..."
            style="width:100%"
            autofocus
          />
        </IconField>
        <Button type="submit" label="Cerca" icon="pi pi-search" :loading="loading" />
        <Button v-if="query" type="button" label="Pulisci" outlined severity="secondary" icon="pi pi-times" @click="clear" />
      </form>
      <p v-if="errore" class="search-error">{{ errore }}</p>
      <p class="search-hint">
        Cerca per: <strong>lotto interno</strong> (es. <code>LT2024-001</code>) ·
        <strong>lotto esterno/fornitore</strong> · <strong>nome materia prima</strong> ·
        <strong>lotto di produzione</strong> (es. <code>LP2024-001</code>) ·
        <strong>nome prodotto finito</strong>
      </p>
    </div>

    <!-- No results -->
    <div v-if="risultati && righeAcquisto.length === 0 && produzioni.length === 0 && venditeRighe.length === 0" class="no-results">
      <i class="pi pi-search" style="font-size:2rem;color:var(--ink-3)" />
      <p>Nessun risultato per <strong>"{{ query }}"</strong></p>
      <p class="no-results-sub">Prova con un lotto diverso o verifica che i dati siano stati registrati.</p>
    </div>

    <template v-if="risultati">

      <!-- ── FORWARD TRACE: Purchase lots ────────────────────────────────── -->
      <template v-if="righeAcquisto.length > 0">
        <div class="section-header">
          <i class="pi pi-download" />
          <span>Lotti di acquisto trovati ({{ righeAcquisto.length }}<template v-if="truncatedRighe"> di {{ risultati.total_righe }}</template>)</span>
          <span class="section-hint">→ Mostra quali produzioni hanno usato questi lotti</span>
        </div>
        <div v-if="truncatedRighe" class="truncation-warning">
          <i class="pi pi-exclamation-triangle" /> Mostrati i primi {{ risultati.limit_righe }} di {{ risultati.total_righe }} risultati. Affina la ricerca per vedere tutti i lotti.
        </div>

        <div v-for="riga in righeAcquisto" :key="riga.id" class="trace-block">
          <!-- Purchase riga -->
          <div class="trace-node trace-purchase">
            <div class="node-icon"><i class="pi pi-download" /></div>
            <div class="node-body">
              <div class="node-title">{{ riga.nome_prodotto }}</div>
              <div class="node-meta">
                <span class="badge badge-supplier">{{ riga.acquisto?.fornitore?.ragione_sociale ?? '—' }}</span>
                <span class="meta-sep">·</span>
                <span>DDT N° <strong>{{ riga.acquisto?.numero_documento }}</strong></span>
                <span class="meta-sep">·</span>
                <span>{{ formatDate(riga.acquisto?.data_documento) }}</span>
              </div>
              <div class="node-lots">
                <span v-if="riga.lotto" class="lot-chip lot-internal">Int: {{ riga.lotto }}</span>
                <span v-if="riga.lotto_esterno" class="lot-chip lot-external">Est: {{ riga.lotto_esterno }}</span>
                <span class="lot-chip lot-qty">{{ riga.quantita_kg != null ? Number(riga.quantita_kg).toFixed(3) + ' kg' : '' }}</span>
                <span v-if="riga.scadenza" class="lot-chip" :class="isScaduto(riga.scadenza) ? 'lot-expired' : 'lot-expiry'">
                  Scad: {{ formatDate(riga.scadenza) }}
                </span>
              </div>
              <div v-if="riga.allergeni && (riga.allergeni.contiene.length || riga.allergeni.tracce.length)" class="allergeni-row">
                <template v-if="riga.allergeni.contiene.length">
                  <span class="allergeni-label">Allergeni:</span>
                  <span v-for="a in riga.allergeni.contiene" :key="a" class="chip chip-contiene">{{ a }}</span>
                </template>
                <template v-if="riga.allergeni.tracce.length">
                  <span class="allergeni-label">Può contenere:</span>
                  <span v-for="a in riga.allergeni.tracce" :key="`t-${a}`" class="chip chip-tracce">{{ a }}</span>
                </template>
              </div>
            </div>
            <div class="node-actions">
              <Link :href="`/acquisti/${riga.acquisto_id}/edit`" class="node-link">Vedi acquisto</Link>
            </div>
          </div>

          <!-- Productions that used this lot -->
          <div v-if="riga.produzioni_materie_prime?.length > 0" class="trace-children">
            <div class="connector-line" />
            <div class="trace-child-label">
              <i class="pi pi-arrow-down" />
              Usato in {{ riga.produzioni_materie_prime.length }} produzione{{ riga.produzioni_materie_prime.length > 1 ? 'i' : '' }}:
            </div>
            <div
              v-for="mp in riga.produzioni_materie_prime"
              :key="mp.id"
              class="trace-node trace-production"
            >
              <div class="node-icon"><i class="pi pi-cog" /></div>
              <div class="node-body">
                <div class="node-title mono">{{ mp.produzione?.lotto_produzione }}</div>
                <div class="node-meta">
                  <span class="badge badge-product">{{ mp.produzione?.scheda?.prodotto?.nome ?? '—' }}</span>
                  <span class="meta-sep">·</span>
                  <span>{{ formatDate(mp.produzione?.data_produzione) }}</span>
                  <span class="meta-sep">·</span>
                  <span>{{ mp.quantita_kg != null ? Number(mp.quantita_kg).toFixed(3) + ' kg usati' : '' }}</span>
                </div>
              </div>
              <div class="node-actions">
                <Link :href="`/produzioni/${mp.produzione?.id}/edit`" class="node-link">Vedi produzione</Link>
                <Link :href="`/produzioni/${mp.produzione?.id}/print`" target="_blank" class="node-link node-link-sm">Stampa</Link>
              </div>
            </div>
          </div>

          <div v-else class="trace-no-use">
            <i class="pi pi-info-circle" />
            Questo lotto non risulta ancora utilizzato in produzioni registrate.
          </div>
        </div>
      </template>

      <!-- ── REVERSE TRACE: Production lots ──────────────────────────────── -->
      <template v-if="produzioni.length > 0">
        <div class="section-header" :style="righeAcquisto.length > 0 ? 'margin-top:2rem' : ''">
          <i class="pi pi-cog" />
          <span>Lotti di produzione trovati ({{ produzioni.length }}<template v-if="truncatedProd"> di {{ risultati.total_produzioni }}</template>)</span>
          <span class="section-hint">→ Mostra le materie prime usate (tracciabilità a ritroso)</span>
        </div>
        <div v-if="truncatedProd" class="truncation-warning">
          <i class="pi pi-exclamation-triangle" /> Mostrati i primi {{ risultati.limit_produzioni }} di {{ risultati.total_produzioni }} risultati. Affina la ricerca.
        </div>

        <div v-for="prod in produzioni" :key="prod.id" class="trace-block">
          <!-- Production header -->
          <div class="trace-node trace-production trace-prod-main">
            <div class="node-icon"><i class="pi pi-cog" /></div>
            <div class="node-body">
              <div class="node-title mono">{{ prod.lotto_produzione }}</div>
              <div class="node-meta">
                <span class="badge badge-product">{{ prod.scheda?.prodotto?.nome ?? '—' }}</span>
                <span class="meta-sep">·</span>
                <span>{{ formatDate(prod.data_produzione) }}</span>
                <span v-if="prod.quantita_prodotta_kg" class="meta-sep">·</span>
                <span v-if="prod.quantita_prodotta_kg">{{ Number(prod.quantita_prodotta_kg).toFixed(3) }} kg prodotti</span>
                <span v-if="prod.operatore" class="meta-sep">·</span>
                <span v-if="prod.operatore" class="text-muted">Op: {{ prod.operatore }}</span>
              </div>
              <div v-if="prod.allergeni && (prod.allergeni.contiene.length || prod.allergeni.tracce.length)" class="allergeni-row">
                <template v-if="prod.allergeni.contiene.length">
                  <span class="allergeni-label">Allergeni:</span>
                  <span v-for="a in prod.allergeni.contiene" :key="a" class="chip chip-contiene">{{ a }}</span>
                </template>
                <template v-if="prod.allergeni.tracce.length">
                  <span class="allergeni-label">Può contenere:</span>
                  <span v-for="a in prod.allergeni.tracce" :key="`t-${a}`" class="chip chip-tracce">{{ a }}</span>
                </template>
              </div>
            </div>
            <div class="node-actions">
              <Link :href="`/produzioni/${prod.id}/edit`" class="node-link">Vedi produzione</Link>
              <Link :href="`/produzioni/${prod.id}/print`" target="_blank" class="node-link node-link-sm">Stampa</Link>
            </div>
          </div>

          <!-- Raw materials used -->
          <div v-if="prod.materie_prime?.length > 0" class="trace-children">
            <div class="connector-line" />
            <div class="trace-child-label">
              <i class="pi pi-arrow-up" />
              Materie prime utilizzate ({{ prod.materie_prime.length }}):
            </div>
            <div
              v-for="mp in prod.materie_prime"
              :key="mp.id"
              class="trace-node trace-purchase"
            >
              <div class="node-icon"><i class="pi pi-download" /></div>
              <div class="node-body">
                <div class="node-title">{{ mp.materia_prima?.nome ?? mp.acquisto_riga?.nome_prodotto ?? '—' }}</div>
                <div class="node-meta">
                  <span class="badge badge-supplier">{{ mp.acquisto_riga?.acquisto?.fornitore?.ragione_sociale ?? '—' }}</span>
                  <span class="meta-sep">·</span>
                  <span>DDT N° <strong>{{ mp.acquisto_riga?.acquisto?.numero_documento ?? '—' }}</strong></span>
                </div>
                <div class="node-lots">
                  <span v-if="mp.acquisto_riga?.lotto" class="lot-chip lot-internal">Int: {{ mp.acquisto_riga.lotto }}</span>
                  <span v-if="mp.acquisto_riga?.lotto_esterno" class="lot-chip lot-external">Est: {{ mp.acquisto_riga.lotto_esterno }}</span>
                  <span class="lot-chip lot-qty">{{ mp.quantita_kg != null ? Number(mp.quantita_kg).toFixed(3) + ' kg' : '' }}</span>
                </div>
              </div>
              <div class="node-actions">
                <Link :href="`/acquisti/${mp.acquisto_riga?.acquisto_id}/edit`" class="node-link">Vedi acquisto</Link>
              </div>
            </div>
          </div>

          <div v-else class="trace-no-use">
            <i class="pi pi-info-circle" />
            Nessuna materia prima collegata a questa produzione.
          </div>
        </div>
      </template>

      <!-- ── GAP-D6: SALES LEG — finished lots delivered to customers ──── -->
      <template v-if="venditeRighe.length > 0">
        <div class="section-header" :style="(righeAcquisto.length > 0 || produzioni.length > 0) ? 'margin-top:2rem' : ''">
          <i class="pi pi-send" />
          <span>Righe di vendita trovate ({{ venditeRighe.length }}<template v-if="truncatedVendite"> di {{ risultati.total_vendite }}</template>)</span>
          <span class="section-hint">→ Mostra a quali clienti è stato consegnato questo lotto</span>
        </div>
        <div v-if="truncatedVendite" class="truncation-warning">
          <i class="pi pi-exclamation-triangle" /> Mostrati i primi {{ risultati.limit_vendite }} di {{ risultati.total_vendite }} risultati. Affina la ricerca.
        </div>

        <div v-for="vr in venditeRighe" :key="vr.id" class="trace-block">
          <div class="trace-node trace-sale">
            <div class="node-icon"><i class="pi pi-send" /></div>
            <div class="node-body">
              <div class="node-title">{{ vr.nome_prodotto }}</div>
              <div class="node-meta">
                <span class="badge badge-customer">{{ vr.vendita?.cliente?.ragione_sociale ?? '—' }}</span>
                <span class="meta-sep">·</span>
                <span>Doc N° <strong>{{ vr.vendita?.numero_documento }}</strong></span>
                <span class="meta-sep">·</span>
                <span>{{ formatDate(vr.vendita?.data_documento) }}</span>
              </div>
              <div class="node-lots">
                <span v-if="vr.lotto" class="lot-chip lot-internal">Int: {{ vr.lotto }}</span>
                <span v-if="vr.lotto_esterno" class="lot-chip lot-external">Est: {{ vr.lotto_esterno }}</span>
                <span class="lot-chip lot-qty">{{ vr.quantita_kg != null ? Number(vr.quantita_kg).toFixed(3) + ' kg' : '' }}</span>
                <span v-if="vr.scadenza" class="lot-chip" :class="isScaduto(vr.scadenza) ? 'lot-expired' : 'lot-expiry'">
                  Scad: {{ formatDate(vr.scadenza) }}
                </span>
              </div>
            </div>
            <div class="node-actions">
              <Link :href="`/vendite/${vr.vendita_id}/edit`" class="node-link">Vedi vendita</Link>
            </div>
          </div>
        </div>
      </template>

    </template>

    <!-- Empty state (before first search) -->
    <div v-if="!risultati && !query" class="empty-state">
      <i class="pi pi-search empty-icon" />
      <p class="empty-title">Ricerca tracciabilità lotti</p>
      <p class="empty-sub">
        Inserisci un numero di lotto nel campo di ricerca per vedere la catena di tracciabilità completa:
        da quale fornitore proviene, in quale produzione è stato usato, e il prodotto finito ottenuto.
      </p>
    </div>

  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router, usePage, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputText from 'primevue/inputtext';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import Button from 'primevue/button';

const props = defineProps({
  risultati: Object,
  query:     String,
  errore:    String,
});

const term    = ref(props.query ?? '');
const loading = ref(false);

const righeAcquisto = computed(() => props.risultati?.righe_acquisto ?? []);
const produzioni    = computed(() => props.risultati?.produzioni ?? []);
const venditeRighe  = computed(() => props.risultati?.vendite_righe ?? []);

const truncatedRighe    = computed(() => props.risultati && props.risultati.total_righe > props.risultati.limit_righe);
const truncatedProd     = computed(() => props.risultati && props.risultati.total_produzioni > props.risultati.limit_produzioni);
const truncatedVendite  = computed(() => props.risultati && props.risultati.total_vendite > props.risultati.limit_vendite);

function doSearch() {
  if (!term.value.trim()) return;
  loading.value = true;
  router.get('/tracciabilita/search', { q: term.value.trim() }, {
    onFinish: () => { loading.value = false; },
  });
}

function clear() {
  term.value = '';
  router.get('/tracciabilita');
}

function formatDate(d) {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function isScaduto(d) {
  return d && new Date(d) < new Date();
}
</script>

<style scoped>
.page-header { margin-bottom: 1.5rem; }
.page-title  { font-size: 1.5rem; font-weight: 700; color: var(--ink); margin: 0 0 0.25rem 0; }
.page-sub    { font-size: 0.85rem; color: var(--ink-2); margin: 0; }

/* ── Search ─────────────────────────────────────────────────────── */
.search-card  { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 1.25rem; margin-bottom: 1.5rem; }
.search-form  { display: flex; gap: 0.75rem; align-items: center; }
.search-error { color: var(--danger); font-size: 0.82rem; margin: 0.5rem 0 0 0; }
.search-hint  { font-size: 0.78rem; color: var(--ink-3); margin: 0.6rem 0 0 0; }
.search-hint code { font-family: var(--font-mono); background: var(--border); padding: 0.05rem 0.3rem; border-radius: 3px; }

/* ── Section header ─────────────────────────────────────────────── */
.section-header {
  display: flex; align-items: center; gap: 0.5rem;
  font-size: 0.88rem; font-weight: 700; color: var(--ink);
  margin-bottom: 1rem;
}
.section-header i { color: var(--pine); }
.section-hint { font-size: 0.78rem; color: var(--ink-3); font-weight: 400; }

/* ── Trace blocks ───────────────────────────────────────────────── */
.trace-block  { margin-bottom: 1.25rem; }

.trace-node {
  display: flex; align-items: flex-start; gap: 0.85rem;
  background: var(--surface); border: 1px solid var(--border); border-radius: 8px;
  padding: 0.85rem 1rem;
}
.trace-purchase   { border-left: 3px solid var(--pine); }
.trace-production { border-left: 3px solid var(--ambra); }
.trace-sale       { border-left: 3px solid var(--info); }
.trace-prod-main  { border-left-width: 4px; }

.node-icon {
  width: 34px; height: 34px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
  font-size: 0.9rem;
}
.trace-purchase   .node-icon { background: var(--ok-tint); color: var(--ok); }
.trace-production .node-icon { background: var(--ambra-tint); color: var(--ambra); }
.trace-sale       .node-icon { background: var(--info-tint); color: var(--info); }

.node-body  { flex: 1; min-width: 0; }
.node-title { font-size: 0.9rem; font-weight: 700; color: var(--ink); margin-bottom: 0.3rem; }
.node-meta  { display: flex; align-items: center; gap: 0.35rem; flex-wrap: wrap; font-size: 0.78rem; color: var(--ink-2); margin-bottom: 0.35rem; }
.meta-sep   { color: var(--ink-3); }
.node-lots  { display: flex; gap: 0.35rem; flex-wrap: wrap; }
.node-actions { display: flex; flex-direction: column; gap: 0.3rem; flex-shrink: 0; align-items: flex-end; }
.node-link  { font-size: 0.78rem; color: var(--pine); text-decoration: none; font-weight: 600; white-space: nowrap; }
.node-link:hover { text-decoration: underline; }
.node-link-sm { color: var(--ink-3); font-weight: 400; }

/* ── Badges & chips ─────────────────────────────────────────────── */
.badge         { display: inline-block; padding: 0.1rem 0.45rem; border-radius: 99px; font-size: 0.72rem; font-weight: 700; }
.badge-supplier  { background: var(--ok-tint); color: var(--ok); }
.badge-product   { background: var(--ambra-tint); color: var(--ambra); }
.badge-customer  { background: var(--info-tint); color: var(--info); }

.truncation-warning { background: var(--warn-tint); border: 1px solid var(--warn-tint); border-radius: 6px; padding: 0.5rem 0.85rem; font-size: 0.78rem; color: var(--warn); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.4rem; }

.lot-chip     { display: inline-block; padding: 0.1rem 0.4rem; border-radius: 4px; font-family: var(--font-mono); font-size: 0.72rem; font-weight: 600; }
.lot-internal { background: var(--info-tint); color: var(--info); }
.lot-external { background: var(--ok-tint); color: var(--ok); border: 1px solid var(--border); }
.lot-qty      { background: var(--border); color: var(--ink-2); }
.lot-expiry   { background: var(--warn-tint); color: var(--warn); }
.lot-expired  { background: var(--danger-tint); color: var(--danger); }

/* ── Children (connector) ───────────────────────────────────────── */
.trace-children  { margin-left: 1.75rem; margin-top: 0.35rem; }
.connector-line  { width: 2px; height: 0.6rem; background: var(--border-strong); margin-left: 1rem; }
.trace-child-label { font-size: 0.75rem; color: var(--ink-2); font-weight: 600; margin: 0.2rem 0 0.4rem 0; display: flex; align-items: center; gap: 0.3rem; }
.trace-child-label i { font-size: 0.65rem; }

.trace-no-use { margin-left: 1.75rem; margin-top: 0.35rem; font-size: 0.78rem; color: var(--ink-3); display: flex; align-items: center; gap: 0.4rem; }

.text-muted { color: var(--ink-3); }
.allergeni-row { display:flex; flex-wrap:wrap; align-items:center; gap:0.3rem; margin-top:0.4rem; }
.allergeni-label { font-size:0.72rem; font-weight:700; color:var(--ink-2); text-transform:uppercase; letter-spacing:0.03em; }
.chip { font-size:0.68rem; font-weight:600; padding:0.1rem 0.45rem; border-radius:99px; white-space:nowrap; }
.chip-contiene { background:var(--danger-tint); color:var(--danger); }
.chip-tracce { background:var(--warn-tint); color:var(--warn); }
.mono { font-family: var(--font-mono); }

/* ── Empty states ───────────────────────────────────────────────── */
.no-results { text-align: center; padding: 3rem 1rem; }
.no-results p { color: var(--ink-2); font-size: 0.95rem; margin: 0.5rem 0; }
.no-results-sub { color: var(--ink-3); font-size: 0.82rem; }

.empty-state { text-align: center; padding: 4rem 1rem; }
.empty-icon  { font-size: 3rem; color: var(--ink-3); margin-bottom: 1rem; }
.empty-title { font-size: 1.1rem; font-weight: 700; color: var(--ink-2); margin: 0 0 0.5rem 0; }
.empty-sub   { max-width: 480px; margin: 0 auto; font-size: 0.875rem; color: var(--ink-3); line-height: 1.6; }

/* Mobile refinement (Epic 6): stack/wrap trace nodes on small screens */
@media (max-width: 768px) {
  .node-body { flex-direction: column; align-items: flex-start; gap: 0.5rem; }
  .node-meta, .node-lots, .node-actions, .section-header { flex-wrap: wrap; }
  .trace-node { padding-left: 0.75rem; padding-right: 0.75rem; }
}
</style>
