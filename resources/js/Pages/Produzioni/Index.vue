<template>
  <AppLayout>
    <div class="page-header">
      <h1 class="page-title">Produzioni</h1>
      <div style="display:flex;gap:0.5rem">
        <a href="/produzioni/export">
          <Button label="Esporta CSV" icon="pi pi-download" outlined severity="secondary" />
        </a>
        <Link href="/produzioni/create"><Button label="Nuova Produzione" icon="pi pi-plus" /></Link>
      </div>
    </div>

    <div class="filters-bar">
      <IconField>
        <InputIcon class="pi pi-search" />
        <InputText v-model="filters.search" placeholder="Cerca lotto produzione..." @input="debouncedSearch" style="width:240px" />
      </IconField>
      <DatePicker v-model="dataDa" placeholder="Da data..." date-format="dd/mm/yy" show-button-bar style="width:140px" @date-select="applyFilters" @clear-click="applyFilters" />
      <DatePicker v-model="dataA"  placeholder="A data..."  date-format="dd/mm/yy" show-button-bar style="width:140px" @date-select="applyFilters" @clear-click="applyFilters" />
    </div>

    <DataTable :value="produzioni.data" class="mt-4" striped-rows size="small">
      <Column header="Data" style="width:100px">
        <template #body="{ data }">{{ formatDate(data.data_produzione) }}</template>
      </Column>
      <Column header="Lotto Produzione" style="width:160px">
        <template #body="{ data }">
          <Link :href="`/produzioni/${data.id}/edit`" class="row-link mono">{{ data.lotto_produzione }}</Link>
        </template>
      </Column>
      <Column header="Prodotto">
        <template #body="{ data }">{{ data.scheda?.prodotto?.nome }}</template>
      </Column>
      <Column header="Scheda" style="width:110px">
        <template #body="{ data }">
          <span class="mono">{{ data.scheda?.modello }}.{{ String(data.scheda?.revisione ?? 0).padStart(2,'0') }}</span>
        </template>
      </Column>
      <Column header="Q.tà Kg" style="width:100px">
        <template #body="{ data }">
          <span>{{ data.quantita_prodotta_kg ? `${Number(data.quantita_prodotta_kg).toFixed(3)} kg` : '—' }}</span>
        </template>
      </Column>
      <Column field="operatore" header="Operatore" style="width:130px">
        <template #body="{ data }"><span class="text-muted">{{ data.operatore ?? '—' }}</span></template>
      </Column>
      <Column header="Azioni" style="width:130px">
        <template #body="{ data }">
          <div style="display:flex;gap:0.4rem">
            <Link :href="`/produzioni/${data.id}/edit`"><Button icon="pi pi-pencil" size="small" outlined /></Link>
            <a :href="`/produzioni/${data.id}/pdf`" target="_blank">
              <Button icon="pi pi-file-pdf" size="small" outlined severity="danger" v-tooltip="'Scarica PDF'" />
            </a>
            <Button v-if="isAdmin" icon="pi pi-trash" size="small" outlined severity="danger" @click="confirmDelete(data)" />
          </div>
        </template>
      </Column>
      <template #empty><div class="empty-state">Nessuna produzione trovata.</div></template>
    </DataTable>

    <div v-if="produzioni.last_page > 1" class="pagination">
      <Button icon="pi pi-chevron-left" outlined size="small" :disabled="!produzioni.prev_page_url" @click="router.visit(produzioni.prev_page_url)" />
      <span class="page-info">{{ produzioni.current_page }} / {{ produzioni.last_page }} ({{ produzioni.total }})</span>
      <Button icon="pi pi-chevron-right" outlined size="small" :disabled="!produzioni.next_page_url" @click="router.visit(produzioni.next_page_url)" />
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useConfirm } from 'primevue/useconfirm';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import DatePicker from 'primevue/datepicker';

const props = defineProps({ produzioni: Object, filters: Object });
const confirm = useConfirm();
const page = usePage();
const isAdmin = computed(() => page.props.auth?.user?.role === 'admin');
const filters = ref({ search: props.filters?.search ?? '' });
const dataDa = ref(props.filters?.da ? new Date(props.filters.da) : null);
const dataA  = ref(props.filters?.a  ? new Date(props.filters.a)  : null);

function formatDate(d) {
  return d ? new Date(d).toLocaleDateString('it-IT', { day:'2-digit', month:'2-digit', year:'numeric' }) : '—';
}
let t = null;
function debouncedSearch() { clearTimeout(t); t = setTimeout(applyFilters, 400); }
function applyFilters() {
  router.get('/produzioni', {
    search: filters.value.search,
    da: dataDa.value ? dataDa.value.toISOString().slice(0,10) : '',
    a:  dataA.value  ? dataA.value.toISOString().slice(0,10)  : '',
  }, { preserveState: true, replace: true });
}
function confirmDelete(p) {
  confirm.require({
    message: `Eliminare la produzione lotto "${p.lotto_produzione}"?`,
    header: 'Conferma eliminazione', icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Elimina', rejectLabel: 'Annulla', acceptClass: 'p-button-danger',
    accept: () => router.delete(`/produzioni/${p.id}`),
  });
}
</script>

<style scoped>
.page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:1.5rem; }
.page-title { font-size:1.5rem; font-weight:700; color:#1e293b; margin:0; }
.filters-bar { display:flex; align-items:center; gap:0.75rem; flex-wrap:wrap; }
.row-link { color:#1d4ed8; text-decoration:none; font-weight:500; }
.row-link:hover { text-decoration:underline; }
.text-muted { color:#94a3b8; }
.mono { font-family:monospace; font-size:0.88rem; }
.mt-4 { margin-top:1rem; }
.pagination { display:flex; align-items:center; gap:1rem; margin-top:1rem; justify-content:center; }
.page-info { font-size:0.875rem; color:#64748b; }
.empty-state { padding:2rem; text-align:center; color:#94a3b8; }
</style>
