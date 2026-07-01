<template>
  <AppLayout>
    <div class="page-header">
      <h1 class="page-title">Schede di Produzione</h1>
      <Link v-if="isAdmin" href="/schede/create"><Button label="Nuova Scheda" icon="pi pi-plus" /></Link>
    </div>
    <div class="filters-bar">
      <IconField>
        <InputIcon class="pi pi-search" />
        <InputText v-model="filters.search" placeholder="Cerca prodotto o modello..." @input="debouncedSearch" style="width:260px" />
      </IconField>
      <div class="toggle-filter">
        <ToggleSwitch v-model="soloAttive" input-id="solo_attive" @change="applyFilters" />
        <label for="solo_attive">Solo attive</label>
      </div>
    </div>

    <DataTable :value="schede.data" class="mt-4" striped-rows size="small">
      <Column field="modello" header="Modello" style="width:120px">
        <template #body="{ data }">
          <span class="mono">{{ data.modello }}.{{ String(data.revisione).padStart(2, '0') }}</span>
        </template>
      </Column>
      <Column header="Prodotto">
        <template #body="{ data }">
          <Link v-if="isAdmin" :href="`/schede/${data.id}/edit`" class="row-link">{{ data.prodotto?.nome }}</Link>
          <span v-else>{{ data.prodotto?.nome }}</span>
          <span class="text-muted" style="font-size:0.78rem; margin-left:0.4rem">{{ data.prodotto?.codice_prodotto }}</span>
        </template>
      </Column>
      <Column header="Data Rev." style="width:110px">
        <template #body="{ data }">{{ formatDate(data.data_revisione) }}</template>
      </Column>
      <Column header="Marinatura" style="width:100px; text-align:center">
        <template #body="{ data }">
          <i v-if="data.ha_marinatura" class="pi pi-check-circle" style="color:#16a34a" />
          <span v-else class="text-muted">—</span>
        </template>
      </Column>
      <Column header="Stato" style="width:80px">
        <template #body="{ data }">
          <Tag :value="data.attiva ? 'Attiva' : 'Archiviata'" :severity="data.attiva ? 'success' : 'secondary'" />
        </template>
      </Column>
      <Column header="Azioni" style="width:130px">
        <template #body="{ data }">
          <div style="display:flex;gap:0.4rem">
            <Link :href="`/schede/${data.id}/print`" target="_blank">
              <Button icon="pi pi-print" aria-label="Stampa" size="small" outlined severity="secondary" v-tooltip="'Stampa'" />
            </Link>
            <template v-if="isAdmin">
              <Link :href="`/schede/${data.id}/edit`"><Button icon="pi pi-pencil" aria-label="Modifica" size="small" outlined /></Link>
              <Button icon="pi pi-trash" aria-label="Elimina" size="small" outlined severity="danger" @click="confirmDelete(data)" />
            </template>
          </div>
        </template>
      </Column>
      <template #empty><div class="empty-state">Nessuna scheda trovata.</div></template>
    </DataTable>

    <div v-if="schede.last_page > 1" class="pagination">
      <Button icon="pi pi-chevron-left" aria-label="Pagina precedente" outlined size="small" :disabled="!schede.prev_page_url" @click="router.visit(schede.prev_page_url)" />
      <span class="page-info">{{ schede.current_page }} / {{ schede.last_page }} ({{ schede.total }})</span>
      <Button icon="pi pi-chevron-right" aria-label="Pagina successiva" outlined size="small" :disabled="!schede.next_page_url" @click="router.visit(schede.next_page_url)" />
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
import Tag from 'primevue/tag';
import ToggleSwitch from 'primevue/toggleswitch';

const props = defineProps({ schede: Object, filters: Object });
const confirm = useConfirm();
const page = usePage();
const isAdmin = computed(() => page.props.auth?.user?.role === 'admin');
const filters = ref({ search: props.filters?.search ?? '', solo_attive: props.filters?.solo_attive ?? '' });
const soloAttive = ref(!!props.filters?.solo_attive);

function formatDate(d) {
  return d ? new Date(d).toLocaleDateString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '—';
}
let t = null;
function debouncedSearch() { clearTimeout(t); t = setTimeout(applyFilters, 400); }
function applyFilters() {
  router.get('/schede', { search: filters.value.search, solo_attive: soloAttive.value ? '1' : '' }, { preserveState: true, replace: true });
}
function confirmDelete(s) {
  confirm.require({
    message: `Eliminare la scheda "${s.modello}"?`, header: 'Conferma eliminazione',
    icon: 'pi pi-exclamation-triangle', acceptLabel: 'Elimina', rejectLabel: 'Annulla', acceptClass: 'p-button-danger',
    accept: () => router.delete(`/schede/${s.id}`),
  });
}
</script>

<style scoped>
.page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:1.5rem; }
.page-title { font-size:1.5rem; font-weight:700; color:#1e293b; margin:0; }
.filters-bar { display:flex; align-items:center; gap:1rem; flex-wrap:wrap; }
.toggle-filter { display:flex; align-items:center; gap:0.5rem; font-size:0.875rem; color:#374151; }
.row-link { color:#1d4ed8; text-decoration:none; font-weight:500; }
.row-link:hover { text-decoration:underline; }
.text-muted { color:#94a3b8; }
.mono { font-family:monospace; font-size:0.88rem; font-weight:700; color:#1e293b; }
.mt-4 { margin-top:1rem; }
.pagination { display:flex; align-items:center; gap:1rem; margin-top:1rem; justify-content:center; }
.page-info { font-size:0.875rem; color:#64748b; }
.empty-state { padding:2rem; text-align:center; color:#94a3b8; }
</style>
