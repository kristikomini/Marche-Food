<template>
  <AppLayout>
    <div class="page-header">
      <div>
        <h1 class="page-title">Report Gestionale</h1>
        <p class="page-sub">Volumi di acquisto, vendita e produzione nel periodo, con dettaglio per fornitore e cliente.</p>
      </div>
      <div class="header-actions">
        <a :href="exportUrl('csv')" class="btn-ghost"><i class="pi pi-file" /> CSV</a>
        <a :href="exportUrl('pdf')" class="btn-export"><i class="pi pi-file-pdf" /> PDF</a>
      </div>
    </div>

    <!-- Date filter -->
    <div class="filter-bar">
      <div class="field">
        <label>Dal</label>
        <DatePicker v-model="da" dateFormat="dd/mm/yy" showButtonBar />
      </div>
      <div class="field">
        <label>Al</label>
        <DatePicker v-model="a" dateFormat="dd/mm/yy" showButtonBar />
      </div>
      <Button label="Aggiorna" icon="pi pi-refresh" @click="apply" :loading="loading" />
    </div>

    <!-- KPIs -->
    <div class="stat-grid">
      <div class="stat-card">
        <div class="stat-label">Acquisti</div>
        <div class="stat-value">{{ fmt0(summary.totali.acquisti_kg) }} <span class="unit">kg</span></div>
        <div class="stat-sub">{{ summary.totali.acquisti_docs }} documenti (escl. conto terzi)</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Vendite</div>
        <div class="stat-value">{{ fmt0(summary.totali.vendite_kg) }} <span class="unit">kg</span></div>
        <div class="stat-sub">{{ summary.totali.vendite_docs }} documenti</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Produzioni</div>
        <div class="stat-value">{{ fmt0(summary.totali.produzioni_kg) }} <span class="unit">kg</span></div>
        <div class="stat-sub">{{ summary.totali.produzioni }} lotti</div>
      </div>
    </div>

    <div class="two-col">
      <div class="result-card">
        <div class="result-header"><i class="pi pi-building result-icon g" /><div><div class="result-title">Acquisti per fornitore</div></div></div>
        <div class="table-wrap">
          <table class="result-table">
            <thead><tr><th>Fornitore</th><th class="r">Doc.</th><th class="r">Kg</th></tr></thead>
            <tbody>
              <tr v-for="(r,i) in summary.per_fornitore" :key="i"><td>{{ r.nome }}</td><td class="r">{{ r.documenti }}</td><td class="r">{{ fmt(r.kg) }}</td></tr>
              <tr v-if="!summary.per_fornitore.length"><td colspan="3" class="empty">Nessun dato.</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="result-card">
        <div class="result-header"><i class="pi pi-users result-icon b" /><div><div class="result-title">Vendite per cliente</div></div></div>
        <div class="table-wrap">
          <table class="result-table">
            <thead><tr><th>Cliente</th><th class="r">Doc.</th><th class="r">Kg</th></tr></thead>
            <tbody>
              <tr v-for="(r,i) in summary.per_cliente" :key="i"><td>{{ r.nome }}</td><td class="r">{{ r.documenti }}</td><td class="r">{{ fmt(r.kg) }}</td></tr>
              <tr v-if="!summary.per_cliente.length"><td colspan="3" class="empty">Nessun dato.</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="result-card mt-4">
      <div class="result-header"><i class="pi pi-exclamation-triangle result-icon o" /><div><div class="result-title">Lotti in scadenza / scaduti (giacenza)</div><div class="result-sub">{{ scadenze.length }} lotto/i entro 30 giorni</div></div></div>
      <div class="table-wrap">
        <table class="result-table">
          <thead><tr><th>Prodotto</th><th>Fornitore</th><th>Lotto</th><th class="r">Kg</th><th>Scadenza</th><th>Stato</th></tr></thead>
          <tbody>
            <tr v-for="(r,i) in scadenze" :key="i">
              <td>{{ r.nome_prodotto }}</td>
              <td>{{ r.fornitore }}</td>
              <td class="mono">{{ r.lotto || r.lotto_esterno }}</td>
              <td class="r">{{ fmt(r.quantita_kg) }}</td>
              <td>{{ formatDate(r.scadenza) }}</td>
              <td><span :class="['tag', r.stato]">{{ r.stato === 'scaduto' ? 'Scaduto' : 'In scadenza' }}</span></td>
            </tr>
            <tr v-if="!scadenze.length"><td colspan="6" class="empty">Nessun lotto in scadenza.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';

const props = defineProps({
  summary:  { type: Object, required: true },
  scadenze: { type: Array, default: () => [] },
  filters:  { type: Object, default: () => ({ da: null, a: null }) },
});

const da = ref(props.filters.da ? new Date(props.filters.da) : null);
const a  = ref(props.filters.a ? new Date(props.filters.a) : null);
const loading = ref(false);

function iso(d) {
  if (!d) return null;
  const dt = new Date(d);
  return `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}-${String(dt.getDate()).padStart(2, '0')}`;
}
function apply() {
  loading.value = true;
  router.get('/report', { da: iso(da.value), a: iso(a.value) }, { preserveState: true, preserveScroll: true, onFinish: () => (loading.value = false) });
}
function exportUrl(kind) {
  const q = new URLSearchParams();
  if (iso(da.value)) q.set('da', iso(da.value));
  if (iso(a.value)) q.set('a', iso(a.value));
  return `/report/${kind}?${q.toString()}`;
}
function fmt(n) { return n === null || n === undefined ? '—' : Number(n).toLocaleString('it-IT', { minimumFractionDigits: 3, maximumFractionDigits: 3 }); }
function fmt0(n) { return n === null || n === undefined ? '0' : Number(n).toLocaleString('it-IT', { maximumFractionDigits: 0 }); }
function formatDate(d) { return d ? new Date(d).toLocaleDateString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '—'; }
</script>

<style scoped>
.page-header { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:1.5rem; gap:1rem; }
.page-title { font-size:1.5rem; font-weight:700; color:#1e293b; margin:0 0 0.25rem 0; }
.page-sub { font-size:0.875rem; color:#64748b; margin:0; }
.header-actions { display:flex; gap:0.5rem; }
.btn-export { display:inline-flex; align-items:center; gap:0.4rem; background:#2a6941; color:#fff; border-radius:6px; padding:0.5rem 0.9rem; font-size:0.85rem; text-decoration:none; }
.btn-export:hover { background:#1c3d28; }
.btn-ghost { display:inline-flex; align-items:center; gap:0.4rem; border:1px solid #d1d5db; color:#374151; border-radius:6px; padding:0.5rem 0.9rem; font-size:0.85rem; text-decoration:none; }
.btn-ghost:hover { border-color:#2a6941; color:#2a6941; }
.filter-bar { display:flex; gap:1rem; align-items:flex-end; background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:1rem 1.25rem; margin-bottom:1.5rem; flex-wrap:wrap; }
.field { display:flex; flex-direction:column; gap:0.3rem; }
.field label { font-size:0.75rem; color:#64748b; font-weight:600; }
.stat-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; margin-bottom:1.5rem; }
.stat-card { background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:1rem 1.25rem; }
.stat-label { font-size:0.72rem; text-transform:uppercase; letter-spacing:0.06em; color:#94a3b8; font-weight:700; }
.stat-value { font-size:1.7rem; font-weight:700; color:#1c3d28; margin-top:0.3rem; }
.stat-value .unit { font-size:0.9rem; color:#64748b; font-weight:600; }
.stat-sub { font-size:0.8rem; color:#64748b; }
.two-col { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
.mt-4 { margin-top:1rem; }
.result-card { background:#fff; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; }
.result-header { display:flex; align-items:center; gap:1rem; padding:0.9rem 1.5rem; border-bottom:1px solid #f1f5f9; }
.result-icon { font-size:1.1rem; width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; }
.result-icon.g { background:#f0fdf4; color:#2a6941; }
.result-icon.b { background:#eff6ff; color:#1d4ed8; }
.result-icon.o { background:#fff7ed; color:#c2410c; }
.result-title { font-weight:700; color:#1e293b; font-size:0.95rem; }
.result-sub { font-size:0.8rem; color:#64748b; }
.table-wrap { overflow-x:auto; }
.result-table { width:100%; border-collapse:collapse; font-size:0.85rem; }
.result-table th { padding:0.5rem 1rem; background:#f8fafc; font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.04em; color:#64748b; border-bottom:1px solid #e2e8f0; text-align:left; white-space:nowrap; }
.result-table th.r, .result-table td.r { text-align:right; }
.result-table td { padding:0.55rem 1rem; border-bottom:1px solid #f1f5f9; }
.mono { font-family:'SFMono-Regular',Consolas,monospace; font-size:0.8rem; }
.empty { text-align:center; color:#94a3b8; font-style:italic; padding:1.25rem; }
.tag { font-size:0.72rem; font-weight:700; padding:0.15rem 0.5rem; border-radius:99px; }
.tag.scaduto { background:#fee2e2; color:#b91c1c; }
.tag.in_scadenza { background:#ffedd5; color:#b45309; }
@media (max-width:768px){ .stat-grid, .two-col { grid-template-columns:1fr; } }
</style>
