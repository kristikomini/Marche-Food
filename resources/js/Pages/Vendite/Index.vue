<template>
  <AppLayout>
    <div class="page-header">
      <h1 class="page-title">Vendite</h1>
      <div style="display:flex;gap:0.5rem">
        <a href="/vendite/export">
          <Button label="Esporta CSV" icon="pi pi-download" outlined severity="secondary" />
        </a>
        <Link href="/vendite/create">
          <Button label="Nuova Vendita" icon="pi pi-plus" />
        </Link>
      </div>
    </div>

    <div class="filters-bar">
      <IconField>
        <InputIcon class="pi pi-search" />
        <InputText
          v-model="filters.search"
          placeholder="Cerca per n° documento..."
          @input="debouncedSearch"
          style="width: 200px"
        />
      </IconField>

      <Select
        v-model="filters.cliente_id"
        :options="[{ id: '', ragione_sociale: 'Tutti i clienti' }, ...clienti]"
        option-label="ragione_sociale"
        option-value="id"
        placeholder="Cliente..."
        style="width: 220px"
        @change="applyFilters"
      />

      <div class="tipo-filters">
        <Button label="Tutti"  size="small" :outlined="filters.tipo_documento !== ''"    @click="setTipo('')" />
        <Button label="DDT"    size="small" :outlined="filters.tipo_documento !== 'DDT'" severity="info"    @click="setTipo('DDT')" />
        <Button label="F.I."   size="small" :outlined="filters.tipo_documento !== 'FI'"  severity="success" @click="setTipo('FI')" />
        <Button label="N.C."   size="small" :outlined="filters.tipo_documento !== 'NC'"  severity="warn"    @click="setTipo('NC')" />
      </div>

      <DatePicker v-model="dataDa" placeholder="Da data..." date-format="dd/mm/yy" show-button-bar style="width:140px" @date-select="applyFilters" @clear-click="applyFilters" />
      <DatePicker v-model="dataA"  placeholder="A data..."  date-format="dd/mm/yy" show-button-bar style="width:140px" @date-select="applyFilters" @clear-click="applyFilters" />
    </div>

    <DataTable :value="vendite.data" class="mt-4" striped-rows size="small">
      <Column field="data_documento" header="Data" style="width: 100px">
        <template #body="{ data }">{{ formatDate(data.data_documento) }}</template>
      </Column>
      <Column header="Cliente">
        <template #body="{ data }">{{ data.cliente?.ragione_sociale }}</template>
      </Column>
      <Column field="numero_documento" header="N° Documento" style="width: 160px">
        <template #body="{ data }">
          <Link :href="`/vendite/${data.id}/edit`" class="row-link">{{ data.numero_documento }}</Link>
        </template>
      </Column>
      <Column field="tipo_documento" header="Tipo" style="width: 80px">
        <template #body="{ data }">
          <Tag :value="data.tipo_documento" :severity="tipoSeverity[data.tipo_documento]" />
        </template>
      </Column>
      <Column header="Righe" style="width: 80px; text-align:center">
        <template #body="{ data }">
          <span class="badge">{{ data.righe_count }}</span>
        </template>
      </Column>
      <Column header="Azioni" style="width: 190px">
        <template #body="{ data }">
          <div style="display:flex; gap:0.4rem">
            <a :href="`/vendite/${data.id}/pdf`" target="_blank">
              <Button icon="pi pi-file-pdf" aria-label="Scarica PDF" size="small" outlined severity="secondary" v-tooltip.top="'Scarica PDF'" />
            </a>
            <a :href="`/vendite/${data.id}/etichette`" target="_blank">
              <Button icon="pi pi-qrcode" aria-label="Etichette QR lotti" size="small" outlined severity="secondary" v-tooltip.top="'Etichette QR lotti'" />
            </a>
            <Link :href="`/vendite/${data.id}/edit`">
              <Button icon="pi pi-pencil" aria-label="Modifica" size="small" outlined />
            </Link>
            <Button v-if="isAdmin" icon="pi pi-trash" aria-label="Elimina" size="small" outlined severity="danger" @click="confirmDelete(data)" />
          </div>
        </template>
      </Column>
      <template #empty>
        <div class="empty-state">Nessuna vendita trovata.</div>
      </template>
    </DataTable>

    <div v-if="vendite.last_page > 1" class="pagination">
      <Button icon="pi pi-chevron-left" aria-label="Pagina precedente" outlined size="small" :disabled="!vendite.prev_page_url" @click="router.visit(vendite.prev_page_url)" />
      <span class="page-info">Pagina {{ vendite.current_page }} di {{ vendite.last_page }} ({{ vendite.total }} vendite)</span>
      <Button icon="pi pi-chevron-right" aria-label="Pagina successiva" outlined size="small" :disabled="!vendite.next_page_url" @click="router.visit(vendite.next_page_url)" />
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
import Select from 'primevue/select';
import DatePicker from 'primevue/datepicker';
import Tag from 'primevue/tag';

const props = defineProps({
  vendite: Object,
  clienti: Array,
  filters: Object,
});

const confirm = useConfirm();
const page = usePage();
const isAdmin = computed(() => page.props.auth?.user?.role === 'admin');

const filters = ref({
  search:         props.filters?.search         ?? '',
  cliente_id:     props.filters?.cliente_id     ?? '',
  da:             props.filters?.da             ?? '',
  a:              props.filters?.a              ?? '',
  tipo_documento: props.filters?.tipo_documento ?? '',
});

const dataDa = ref(props.filters?.da ? new Date(props.filters.da) : null);
const dataA  = ref(props.filters?.a  ? new Date(props.filters.a)  : null);

const tipoSeverity = { DDT: 'info', FI: 'success', NC: 'warn' };

function formatDate(d) {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

let searchTimeout = null;
function debouncedSearch() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(applyFilters, 400);
}

function setTipo(tipo) {
  filters.value.tipo_documento = tipo;
  applyFilters();
}

function applyFilters() {
  router.get('/vendite', {
    search:         filters.value.search,
    cliente_id:     filters.value.cliente_id,
    tipo_documento: filters.value.tipo_documento,
    da: dataDa.value ? dataDa.value.toISOString().slice(0, 10) : '',
    a:  dataA.value  ? dataA.value.toISOString().slice(0, 10)  : '',
  }, { preserveState: true, replace: true });
}

function confirmDelete(vendita) {
  confirm.require({
    message: `Eliminare il documento "${vendita.numero_documento}"? Verranno eliminate anche tutte le righe.`,
    header: 'Conferma eliminazione',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Elimina',
    rejectLabel: 'Annulla',
    acceptClass: 'p-button-danger',
    accept: () => router.delete(`/vendite/${vendita.id}`),
  });
}
</script>

<style scoped>
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; }
.page-title { font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0; }
.filters-bar { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
.tipo-filters { display: flex; gap: 0.35rem; }
.row-link { color: #1d4ed8; text-decoration: none; font-weight: 500; }
.row-link:hover { text-decoration: underline; }
.badge { display: inline-block; background: #e2e8f0; color: #475569; border-radius: 9999px; padding: 0.1rem 0.55rem; font-size: 0.78rem; font-weight: 600; }
.mt-4 { margin-top: 1rem; }
.pagination { display: flex; align-items: center; gap: 1rem; margin-top: 1rem; justify-content: center; }
.page-info { font-size: 0.875rem; color: #64748b; }
.empty-state { padding: 2rem; text-align: center; color: #94a3b8; }
</style>
