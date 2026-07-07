<template>
  <AppLayout>
    <div class="page-header">
      <h1 class="page-title">Fornitori</h1>
      <div class="header-actions">
        <a :href="exportUrl" class="export-link">
          <Button label="Esporta Excel" icon="pi pi-file-excel" severity="success" outlined />
        </a>
        <Link v-if="isAdmin" href="/fornitori/create">
          <Button label="Nuovo Fornitore" icon="pi pi-plus" />
        </Link>
      </div>
    </div>

    <!-- Filters -->
    <div class="filters-bar">
      <IconField>
        <InputIcon class="pi pi-search" />
        <InputText
          v-model="filters.search"
          placeholder="Cerca per ragione sociale o codice..."
          @input="debouncedSearch"
          style="width: 300px"
        />
      </IconField>

      <div class="tipo-filters">
        <Button
          label="Tutti"
          :outlined="filters.tipo !== ''"
          size="small"
          @click="setTipo('')"
        />
        <Button
          label="Alimentari"
          :outlined="filters.tipo !== 'alimentare'"
          severity="success"
          size="small"
          @click="setTipo('alimentare')"
        />
        <Button
          label="Imb. Primari"
          :outlined="filters.tipo !== 'imballaggio_primario'"
          severity="info"
          size="small"
          @click="setTipo('imballaggio_primario')"
        />
        <Button
          label="Detergenti"
          :outlined="filters.tipo !== 'detergente_secondario'"
          severity="warn"
          size="small"
          @click="setTipo('detergente_secondario')"
        />
      </div>
    </div>

    <!-- Table -->
    <DataTable
      :value="fornitori.data"
      class="mt-4"
      striped-rows
      size="small"
    >
      <Column field="codice" header="Codice" style="width: 90px" />
      <Column field="ragione_sociale" header="Ragione Sociale">
        <template #body="{ data }">
          <Link v-if="isAdmin" :href="`/fornitori/${data.id}/edit`" class="row-link">{{ data.ragione_sociale }}</Link>
          <span v-else>{{ data.ragione_sociale }}</span>
        </template>
      </Column>
      <Column header="Email">
        <template #body="{ data }"><span class="text-muted">{{ data.email ?? '—' }}</span></template>
      </Column>
      <Column header="Telefono" style="width: 130px">
        <template #body="{ data }"><span class="text-muted">{{ data.telefono ?? '—' }}</span></template>
      </Column>
      <Column field="tipo" header="Tipo" style="width: 150px">
        <template #body="{ data }">
          <Tag :value="tipoLabel[data.tipo]" :severity="tipoSeverity[data.tipo]" />
        </template>
      </Column>
      <Column header="HACCP" style="width: 80px; text-align:center">
        <template #body="{ data }">
          <i
            v-if="data.tipo === 'alimentare'"
            :class="data.haccp_certificato ? 'pi pi-check-circle' : 'pi pi-times-circle'"
            :style="{ color: data.haccp_certificato ? 'var(--ok)' : 'var(--danger)' }"
          />
          <span v-else class="text-muted">—</span>
        </template>
      </Column>
      <Column header="Scad. HACCP" style="width: 115px">
        <template #body="{ data }">
          <span v-if="data.tipo === 'alimentare' && data.haccp_scadenza" :class="isScaduto(data.haccp_scadenza) ? 'text-danger' : ''">
            {{ formatDate(data.haccp_scadenza) }}
          </span>
          <span v-else class="text-muted">—</span>
        </template>
      </Column>
      <Column header="MOCA" style="width: 80px; text-align:center">
        <template #body="{ data }">
          <i
            v-if="data.tipo === 'imballaggio_primario'"
            :class="data.moca_certificato ? 'pi pi-check-circle' : 'pi pi-times-circle'"
            :style="{ color: data.moca_certificato ? 'var(--ok)' : 'var(--danger)' }"
          />
          <span v-else class="text-muted">—</span>
        </template>
      </Column>
      <Column header="Attivo" style="width: 75px; text-align:center">
        <template #body="{ data }">
          <Tag :value="data.attivo ? 'Sì' : 'No'" :severity="data.attivo ? 'success' : 'secondary'" />
        </template>
      </Column>
      <Column v-if="isAdmin" header="Azioni" style="width: 110px">
        <template #body="{ data }">
          <div style="display:flex; gap:0.4rem">
            <Link :href="`/fornitori/${data.id}/edit`">
              <Button icon="pi pi-pencil" aria-label="Modifica" size="small" outlined />
            </Link>
            <Button icon="pi pi-trash" aria-label="Elimina" size="small" outlined severity="danger" @click="confirmDelete(data)" />
          </div>
        </template>
      </Column>

      <template #empty>
        <EmptyState icon="pi pi-building" title="Nessun fornitore" />
      </template>
    </DataTable>

    <!-- Pagination -->
    <div v-if="fornitori.last_page > 1" class="pagination">
      <Button
        icon="pi pi-chevron-left" aria-label="Pagina precedente"
        outlined
        size="small"
        :disabled="!fornitori.prev_page_url"
        @click="router.visit(fornitori.prev_page_url)"
      />
      <span class="page-info">
        Pagina {{ fornitori.current_page }} di {{ fornitori.last_page }}
        ({{ fornitori.total }} fornitori)
      </span>
      <Button
        icon="pi pi-chevron-right" aria-label="Pagina successiva"
        outlined
        size="small"
        :disabled="!fornitori.next_page_url"
        @click="router.visit(fornitori.next_page_url)"
      />
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, router, usePage, useForm } from '@inertiajs/vue3';
import { useConfirm } from 'primevue/useconfirm';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import Tag from 'primevue/tag';

const props = defineProps({
  fornitori: Object,
  filters: Object,
});

const confirm = useConfirm();
const page = usePage();
const deleteForm = useForm({});
const isAdmin = computed(() => page.props.auth?.user?.role === 'admin');

const filters = ref({
  search: props.filters?.search ?? '',
  tipo: props.filters?.tipo ?? '',
});

const exportUrl = computed(() => {
  const params = new URLSearchParams();
  if (filters.value.search) params.set('search', filters.value.search);
  if (filters.value.tipo) params.set('tipo', filters.value.tipo);
  const qs = params.toString();
  return '/fornitori/export' + (qs ? `?${qs}` : '');
});

function formatDate(d) {
  return d ? new Date(d).toLocaleDateString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '—';
}
function isScaduto(d) { return d && new Date(d) < new Date(); }

const tipoLabel = {
  alimentare: 'Alimentare',
  imballaggio_primario: 'Imb. Primario',
  detergente_secondario: 'Detergente',
};

const tipoSeverity = {
  alimentare: 'success',
  imballaggio_primario: 'info',
  detergente_secondario: 'warn',
};

let searchTimeout = null;
function debouncedSearch() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => applyFilters(), 400);
}

function setTipo(tipo) {
  filters.value.tipo = tipo;
  applyFilters();
}

function applyFilters() {
  router.get('/fornitori', filters.value, { preserveState: true, replace: true });
}

function confirmDelete(fornitore) {
  confirm.require({
    message: `Eliminare "${fornitore.ragione_sociale}"?`,
    header: 'Conferma eliminazione',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Elimina',
    rejectLabel: 'Annulla',
    acceptClass: 'p-button-danger',
    accept: () => {
      if (deleteForm.processing) return;
      deleteForm.delete(`/fornitori/${fornitore.id}`);
    },
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
.page-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--ink);
  margin: 0;
}
.header-actions {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}
.export-link { text-decoration: none; }
.filters-bar {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex-wrap: wrap;
}
.tipo-filters {
  display: flex;
  gap: 0.4rem;
}
.row-link {
  color: var(--info);
  text-decoration: none;
  font-weight: 500;
}
.row-link:hover { text-decoration: underline; }
.text-muted { color: var(--ink-3); }
.text-danger { color: var(--danger); font-weight: 600; }
.mt-4 { margin-top: 1rem; }
.pagination {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-top: 1rem;
  justify-content: center;
}
.page-info { font-size: 0.875rem; color: var(--ink-2); }
.empty-state { padding: 2rem; text-align: center; color: var(--ink-3); }
</style>
