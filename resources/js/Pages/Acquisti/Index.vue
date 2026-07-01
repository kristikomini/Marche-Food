<template>
  <AppLayout>
    <div class="page-header">
      <h1 class="page-title">Acquisti</h1>
      <div style="display:flex;gap:0.5rem">
        <a href="/acquisti/export">
          <Button label="Esporta CSV" icon="pi pi-download" outlined severity="secondary" />
        </a>
        <Link href="/acquisti/create">
          <Button label="Nuovo Acquisto" icon="pi pi-plus" />
        </Link>
      </div>
    </div>

    <!-- Filters -->
    <div class="filters-bar">
      <IconField>
        <InputIcon class="pi pi-search" />
        <InputText
          v-model="filters.search"
          placeholder="Cerca per n° documento..."
          @input="debouncedSearch"
          style="width: 220px"
        />
      </IconField>

      <Select
        v-model="filters.fornitore_id"
        :options="[{ id: '', ragione_sociale: 'Tutti i fornitori' }, ...fornitori]"
        option-label="ragione_sociale"
        option-value="id"
        placeholder="Fornitore..."
        style="width: 220px"
        @change="applyFilters"
      />

      <DatePicker
        v-model="dataDa"
        placeholder="Da data..."
        date-format="dd/mm/yy"
        show-button-bar
        style="width: 150px"
        @date-select="applyFilters"
        @clear-click="applyFilters"
      />
      <DatePicker
        v-model="dataA"
        placeholder="A data..."
        date-format="dd/mm/yy"
        show-button-bar
        style="width: 150px"
        @date-select="applyFilters"
        @clear-click="applyFilters"
      />
    </div>

    <DataTable :value="acquisti.data" class="mt-4" striped-rows size="small">
      <Column field="data_documento" header="Data" style="width: 100px">
        <template #body="{ data }">
          {{ formatDate(data.data_documento) }}
        </template>
      </Column>
      <Column header="Fornitore">
        <template #body="{ data }">
          {{ data.fornitore?.ragione_sociale }}
        </template>
      </Column>
      <Column field="numero_documento" header="N° Documento" style="width: 160px">
        <template #body="{ data }">
          <Link :href="`/acquisti/${data.id}/edit`" class="row-link">
            {{ data.numero_documento }}
          </Link>
        </template>
      </Column>
      <Column field="tipo_documento" header="Tipo" style="width: 90px">
        <template #body="{ data }">
          <Tag :value="data.tipo_documento" :severity="tipoSeverity[data.tipo_documento]" />
        </template>
      </Column>
      <Column header="Righe" style="width: 80px; text-align:center">
        <template #body="{ data }">
          <span class="badge">{{ data.righe_count }}</span>
        </template>
      </Column>
      <Column header="Azioni" style="width: 150px">
        <template #body="{ data }">
          <div style="display:flex; gap:0.4rem">
            <a :href="`/acquisti/${data.id}/pdf`" target="_blank">
              <Button icon="pi pi-file-pdf" size="small" outlined severity="secondary" v-tooltip.top="'Scarica PDF'" />
            </a>
            <Link :href="`/acquisti/${data.id}/edit`">
              <Button icon="pi pi-pencil" size="small" outlined />
            </Link>
            <Button
              v-if="isAdmin"
              icon="pi pi-trash"
              size="small"
              outlined
              severity="danger"
              @click="confirmDelete(data)"
            />
          </div>
        </template>
      </Column>

      <template #empty>
        <div class="empty-state">Nessun acquisto trovato.</div>
      </template>
    </DataTable>

    <div v-if="acquisti.last_page > 1" class="pagination">
      <Button
        icon="pi pi-chevron-left"
        outlined
        size="small"
        :disabled="!acquisti.prev_page_url"
        @click="router.visit(acquisti.prev_page_url)"
      />
      <span class="page-info">
        Pagina {{ acquisti.current_page }} di {{ acquisti.last_page }}
        ({{ acquisti.total }} acquisti)
      </span>
      <Button
        icon="pi pi-chevron-right"
        outlined
        size="small"
        :disabled="!acquisti.next_page_url"
        @click="router.visit(acquisti.next_page_url)"
      />
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
  acquisti: Object,
  fornitori: Array,
  filters: Object,
});

const confirm = useConfirm();
const page = usePage();
const isAdmin = computed(() => page.props.auth?.user?.role === 'admin');

const filters = ref({
  search:      props.filters?.search      ?? '',
  fornitore_id: props.filters?.fornitore_id ?? '',
  da:          props.filters?.da          ?? '',
  a:           props.filters?.a           ?? '',
});

const dataDa = ref(props.filters?.da ? new Date(props.filters.da) : null);
const dataA  = ref(props.filters?.a  ? new Date(props.filters.a)  : null);

const tipoSeverity = { DDT: 'info', Fattura: 'success', Bolla: 'warn' };

function formatDate(d) {
  if (!d) return '—';
  const dt = new Date(d);
  return dt.toLocaleDateString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

let searchTimeout = null;
function debouncedSearch() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(applyFilters, 400);
}

function applyFilters() {
  router.get('/acquisti', {
    search:       filters.value.search,
    fornitore_id: filters.value.fornitore_id,
    da: dataDa.value ? dataDa.value.toISOString().slice(0, 10) : '',
    a:  dataA.value  ? dataA.value.toISOString().slice(0, 10)  : '',
  }, { preserveState: true, replace: true });
}

function confirmDelete(acquisto) {
  confirm.require({
    message: `Eliminare il documento "${acquisto.numero_documento}"? Verranno eliminate anche tutte le righe.`,
    header: 'Conferma eliminazione',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Elimina',
    rejectLabel: 'Annulla',
    acceptClass: 'p-button-danger',
    accept: () => router.delete(`/acquisti/${acquisto.id}`),
  });
}
</script>

<style scoped>
.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1.5rem;
}
.page-title { font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0; }
.filters-bar { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
.row-link { color: #1d4ed8; text-decoration: none; font-weight: 500; }
.row-link:hover { text-decoration: underline; }
.badge {
  display: inline-block;
  background: #e2e8f0;
  color: #475569;
  border-radius: 9999px;
  padding: 0.1rem 0.55rem;
  font-size: 0.78rem;
  font-weight: 600;
}
.mt-4 { margin-top: 1rem; }
.pagination { display: flex; align-items: center; gap: 1rem; margin-top: 1rem; justify-content: center; }
.page-info { font-size: 0.875rem; color: #64748b; }
.empty-state { padding: 2rem; text-align: center; color: #94a3b8; }
</style>
