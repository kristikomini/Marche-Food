<template>
  <AppLayout>
    <div class="page-header">
      <div>
        <Link href="/materie-prime" class="back-link"><i class="pi pi-arrow-left" /> Materie Prime</Link>
        <h1 class="page-title">{{ materia.nome }}</h1>
        <div class="sub">Codice: <strong>{{ materia.codice ?? '—' }}</strong></div>
      </div>
      <Link v-if="isAdmin" :href="`/materie-prime/${materia.id}/edit`">
        <Button label="Modifica" icon="pi pi-pencil" outlined />
      </Link>
    </div>

    <!-- Allergeni -->
    <div class="allergen-chips" v-if="(materia.allergeni || []).length || (materia.allergeni_tracce || []).length">
      <span v-for="code in (materia.allergeni || [])" :key="code" class="chip chip-contiene">{{ allergeniLabels[code] || code }}</span>
      <span v-for="code in (materia.allergeni_tracce || [])" :key="`t-${code}`" class="chip chip-tracce">tracce: {{ allergeniLabels[code] || code }}</span>
    </div>

    <!-- Tabella 1: lotti di produzione in uscita -->
    <h2 class="section-title">Lotti di produzione in uscita</h2>
    <p class="section-hint">Lotti prodotti che hanno utilizzato questa materia prima come ingrediente.</p>
    <DataTable :value="lottiProduzione" class="mt-2" striped-rows size="small" paginator :rows="10" :rows-per-page-options="[10, 25, 50]">
      <Column field="lotto_produzione" header="Lotto Produzione">
        <template #body="{ data }">
          <Link :href="`/produzioni/${data.id}/edit`" class="row-link">{{ data.lotto_produzione }}</Link>
        </template>
      </Column>
      <Column header="Data">
        <template #body="{ data }">{{ formatDate(data.data_produzione) }}</template>
      </Column>
      <Column header="Prodotto">
        <template #body="{ data }">
          {{ data.prodotto ?? '—' }}
          <span v-if="data.codice_prodotto" class="text-muted"> · {{ data.codice_prodotto }}</span>
        </template>
      </Column>
      <Column header="Q.tà materia (kg)" style="text-align:right; width:150px">
        <template #body="{ data }"><span class="mono">{{ fmtKg(data.qta_materia_kg) }}</span></template>
      </Column>
      <Column header="Q.tà prodotta (kg)" style="text-align:right; width:160px">
        <template #body="{ data }"><span class="mono">{{ fmtKg(data.quantita_prodotta_kg) }}</span></template>
      </Column>
      <template #empty><EmptyState icon="pi pi-inbox" title="Nessun lotto di produzione collegato" /></template>
    </DataTable>

    <!-- Tabella 2: prodotti che utilizzano la materia prima -->
    <h2 class="section-title">Prodotti che utilizzano questa materia prima</h2>
    <p class="section-hint">Da ricette delle schede di produzione e destinazione ingredienti.</p>
    <DataTable :value="prodotti" class="mt-2" striped-rows size="small">
      <Column field="codice_prodotto" header="Codice" style="width:110px">
        <template #body="{ data }"><span class="text-muted">{{ data.codice_prodotto ?? '—' }}</span></template>
      </Column>
      <Column field="nome" header="Prodotto" />
      <Column header="Origine" style="width:220px">
        <template #body="{ data }">
          <span v-if="data.in_ricetta" class="chip chip-info">In ricetta</span>
          <span v-if="data.in_destinazione" class="chip chip-neutral">Destinazione</span>
        </template>
      </Column>
      <Column header="Stato" style="width:90px; text-align:center">
        <template #body="{ data }">
          <Tag :value="data.attivo ? 'Attivo' : 'Non attivo'" :severity="data.attivo ? 'success' : 'secondary'" />
        </template>
      </Column>
      <template #empty><EmptyState icon="pi pi-inbox" title="Nessun prodotto utilizza questa materia prima" /></template>
    </DataTable>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';

const props = defineProps({
  materia: Object,
  allergeniLabels: { type: Object, default: () => ({}) },
  lottiProduzione: { type: Array, default: () => [] },
  prodotti: { type: Array, default: () => [] },
});

const allergeniLabels = props.allergeniLabels;
const page = usePage();
const isAdmin = computed(() => page.props.auth?.user?.role === 'admin');

function formatDate(d) {
  return d ? new Date(d).toLocaleDateString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '—';
}
function fmtKg(v) {
  if (v === null || v === undefined || v === '') return '—';
  return Number(v).toLocaleString('it-IT', { minimumFractionDigits: 3, maximumFractionDigits: 3 });
}
</script>

<style scoped>
.page-header { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:1.25rem; }
.page-title { font-size:1.5rem; font-weight:700; color:var(--ink); margin:0.25rem 0 0; }
.sub { font-size:0.85rem; color:var(--ink-2); margin-top:0.15rem; }
.back-link { font-size:0.8rem; color:var(--info); text-decoration:none; }
.back-link:hover { text-decoration:underline; }
.section-title { font-size:1.05rem; font-weight:700; color:var(--ink); margin:1.75rem 0 0.15rem; }
.section-hint { font-size:0.8rem; color:var(--ink-3); margin:0 0 0.25rem; }
.row-link { color:var(--info); text-decoration:none; font-weight:500; }
.row-link:hover { text-decoration:underline; }
.text-muted { color:var(--ink-3); }
.mono { font-family:monospace; }
.mt-2 { margin-top:0.5rem; }
.allergen-chips { display:flex; flex-wrap:wrap; gap:0.3rem; margin-bottom:0.5rem; }
.chip { font-size:0.68rem; font-weight:600; padding:0.1rem 0.45rem; border-radius:99px; white-space:nowrap; }
.chip-contiene { background:var(--danger-tint); color:var(--danger); }
.chip-tracce { background:var(--warn-tint); color:var(--warn); }
.chip-info { background:var(--info-tint, #e0f2fe); color:var(--info); margin-right:0.25rem; }
.chip-neutral { background:var(--surface-200, #e2e8f0); color:var(--ink-2); }
</style>
