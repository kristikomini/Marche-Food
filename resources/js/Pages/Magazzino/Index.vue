<template>
  <AppLayout>
    <div class="page-header">
      <div>
        <h1 class="page-title">Giacenze di Magazzino</h1>
        <p class="page-sub">Bilancio dei lotti: ricevuto − consumato in produzione − venduto. Calcolo in tempo reale.</p>
      </div>
      <div class="header-actions">
        <a :href="`/magazzino/export?solo_giacenza=${soloGiacenza ? 1 : 0}`" class="btn-export">
          <i class="pi pi-download" /> Esporta CSV
        </a>
      </div>
    </div>

    <!-- Summary cards -->
    <div class="stat-grid">
      <div class="stat-card">
        <div class="stat-label">Lotti acquisto in giacenza</div>
        <div class="stat-value">{{ summary.lotti_acquisto }}</div>
        <div class="stat-sub">{{ fmt(summary.kg_giacenza_acquisto) }} kg disponibili</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Lotti semilavorato</div>
        <div class="stat-value">{{ summary.lotti_semilavorato }}</div>
        <div class="stat-sub">{{ fmt(summary.kg_giacenza_semilavorato) }} kg disponibili</div>
      </div>
      <div class="stat-card filter-card">
        <label class="stat-label">Filtro</label>
        <div class="toggle-row">
          <ToggleSwitch v-model="soloGiacenza" @update:modelValue="reload" />
          <span>Solo lotti con giacenza &gt; 0</span>
        </div>
      </div>
    </div>

    <!-- Purchase lots -->
    <div class="result-card mb-4">
      <div class="result-header">
        <i class="pi pi-download result-icon purchase" />
        <div>
          <div class="result-title">Lotti di acquisto</div>
          <div class="result-sub">{{ acquisti.length }} lotto/i</div>
        </div>
      </div>
      <div class="table-wrap">
        <table class="result-table">
          <thead>
            <tr><th>Prodotto</th><th>Fornitore</th><th>Lotto</th><th class="r">Ricevuto</th><th class="r">Consumato</th><th class="r">Venduto</th><th class="r">Giacenza</th><th>Scadenza</th><th>C/terzi</th></tr>
          </thead>
          <tbody>
            <tr v-for="r in acquisti" :key="r.id">
              <td>{{ r.nome_prodotto }}</td>
              <td>{{ r.acquisto?.fornitore?.ragione_sociale ?? '—' }}</td>
              <td class="mono">{{ r.lotto || r.lotto_esterno || '—' }}</td>
              <td class="r">{{ fmt(r.quantita_kg) }}</td>
              <td class="r">{{ fmt(r.consumato_kg) }}</td>
              <td class="r">{{ fmt(r.venduto_kg) }}</td>
              <td class="r"><span :class="['bal', Number(r.balance_kg) > 0 ? 'pos' : 'zero']">{{ fmt(r.balance_kg) }}</span></td>
              <td :class="{ expired: isExpired(r.scadenza) }">{{ formatDate(r.scadenza) }}</td>
              <td>{{ r.is_conto_terzi ? 'Sì' : '—' }}</td>
            </tr>
            <tr v-if="!acquisti.length"><td colspan="9" class="empty">Nessun lotto in giacenza.</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Semi-finished lots -->
    <div class="result-card">
      <div class="result-header">
        <i class="pi pi-box result-icon internal" />
        <div>
          <div class="result-title">Semilavorati</div>
          <div class="result-sub">{{ semilavorati.length }} lotto/i interno/i</div>
        </div>
      </div>
      <div class="table-wrap">
        <table class="result-table">
          <thead>
            <tr><th>Prodotto</th><th>Lotto</th><th>Data</th><th class="r">Prodotto (kg)</th><th class="r">Consumato</th><th class="r">Giacenza</th></tr>
          </thead>
          <tbody>
            <tr v-for="r in semilavorati" :key="r.id">
              <td>{{ r.nome_prodotto }}</td>
              <td class="mono">{{ r.lotto }}</td>
              <td>{{ formatDate(r.data_produzione) }}</td>
              <td class="r">{{ fmt(r.quantita_kg) }}</td>
              <td class="r">{{ fmt(r.consumato_kg) }}</td>
              <td class="r"><span :class="['bal', Number(r.balance_kg) > 0 ? 'pos' : 'zero']">{{ fmt(r.balance_kg) }}</span></td>
            </tr>
            <tr v-if="!semilavorati.length"><td colspan="6" class="empty">Nessun semilavorato in giacenza.</td></tr>
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
import ToggleSwitch from 'primevue/toggleswitch';

const props = defineProps({
  acquisti:     { type: Array, default: () => [] },
  semilavorati: { type: Array, default: () => [] },
  summary:      { type: Object, default: () => ({ lotti_acquisto: 0, kg_giacenza_acquisto: 0, lotti_semilavorato: 0, kg_giacenza_semilavorato: 0 }) },
  filters:      { type: Object, default: () => ({ solo_giacenza: true }) },
});

const soloGiacenza = ref(props.filters.solo_giacenza !== false);

function reload() {
  router.get('/magazzino', { solo_giacenza: soloGiacenza.value ? 1 : 0 }, { preserveState: true, preserveScroll: true });
}

function fmt(n) {
  if (n === null || n === undefined || n === '') return '—';
  return Number(n).toLocaleString('it-IT', { minimumFractionDigits: 3, maximumFractionDigits: 3 });
}
function formatDate(d) {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric' });
}
function isExpired(d) {
  return d ? new Date(d) < new Date() : false;
}
</script>

<style scoped>
.page-header { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:1.5rem; gap:1rem; }
.page-title { font-size:1.5rem; font-weight:700; color:var(--ink); margin:0 0 0.25rem 0; }
.page-sub { font-size:0.875rem; color:var(--ink-2); margin:0; }
.btn-export { display:inline-flex; align-items:center; gap:0.4rem; background:var(--pine); color:#fff; border-radius:6px; padding:0.5rem 0.9rem; font-size:0.85rem; text-decoration:none; }
.btn-export:hover { background:var(--pine-strong); }
.stat-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; margin-bottom:1.5rem; }
.stat-card { background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:1rem 1.25rem; }
.stat-label { font-size:0.72rem; text-transform:uppercase; letter-spacing:0.06em; color:var(--ink-3); font-weight:700; }
.stat-value { font-size:1.8rem; font-weight:700; color:var(--pine-strong); margin-top:0.3rem; }
.stat-sub { font-size:0.8rem; color:var(--ink-2); }
.filter-card { display:flex; flex-direction:column; gap:0.6rem; justify-content:center; }
.toggle-row { display:flex; align-items:center; gap:0.6rem; font-size:0.85rem; color:var(--ink-2); }
.mb-4 { margin-bottom:1rem; }
.result-card { background:var(--surface); border:1px solid var(--border); border-radius:8px; overflow:hidden; }
.result-header { display:flex; align-items:center; gap:1rem; padding:1rem 1.5rem; border-bottom:1px solid var(--border); }
.result-icon { font-size:1.2rem; width:40px; height:40px; border-radius:8px; display:flex; align-items:center; justify-content:center; }
.result-icon.purchase { background:var(--pine-tint); color:var(--pine); }
.result-icon.internal { background:var(--info-tint); color:var(--info); }
.result-title { font-weight:700; color:var(--ink); font-size:0.95rem; }
.result-sub { font-size:0.8rem; color:var(--ink-2); }
.table-wrap { overflow-x:auto; }
.result-table { width:100%; border-collapse:collapse; font-size:0.85rem; }
.result-table th { padding:0.5rem 1rem; background:var(--surface-2); font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.04em; color:var(--ink-2); border-bottom:1px solid var(--border); text-align:left; white-space:nowrap; }
.result-table th.r, .result-table td.r { text-align:right; }
.result-table td { padding:0.55rem 1rem; border-bottom:1px solid var(--border); white-space:nowrap; }
.mono { font-family:var(--font-mono); font-size:0.8rem; }
.bal { font-weight:700; }
.bal.pos { color:var(--ok); }
.bal.zero { color:var(--danger); }
.expired { color:var(--danger); font-weight:600; }
.empty { text-align:center; color:var(--ink-3); font-style:italic; padding:1.5rem; }
@media (max-width:768px){ .stat-grid { grid-template-columns:1fr; } }
</style>
