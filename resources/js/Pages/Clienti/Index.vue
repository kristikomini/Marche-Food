<template>
  <AppLayout>
    <div class="page-header">
      <h1 class="page-title">Clienti</h1>
      <Link v-if="isAdmin" href="/clienti/create">
        <Button label="Nuovo Cliente" icon="pi pi-plus" />
      </Link>
    </div>

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

      <div class="toggle-filter">
        <ToggleSwitch v-model="soloAttivi" input-id="solo_attivi" @change="applyFilters" />
        <label for="solo_attivi">Solo attivi</label>
      </div>
    </div>

    <DataTable :value="clienti.data" class="mt-4" striped-rows size="small">
      <Column field="codice_cliente" header="Codice" style="width: 110px" />
      <Column field="ragione_sociale" header="Ragione Sociale">
        <template #body="{ data }">
          <Link v-if="isAdmin" :href="`/clienti/${data.id}/edit`" class="row-link">{{ data.ragione_sociale }}</Link>
          <span v-else>{{ data.ragione_sociale }}</span>
        </template>
      </Column>
      <Column field="piva" header="P. IVA" style="width: 150px">
        <template #body="{ data }">
          <span class="text-muted">{{ data.piva ?? '—' }}</span>
        </template>
      </Column>
      <Column field="email" header="Email" style="width: 200px">
        <template #body="{ data }">
          <span class="text-muted">{{ data.email ?? '—' }}</span>
        </template>
      </Column>
      <Column field="telefono" header="Telefono" style="width: 130px">
        <template #body="{ data }">
          <span class="text-muted">{{ data.telefono ?? '—' }}</span>
        </template>
      </Column>
      <Column header="Attivo" style="width: 80px; text-align:center">
        <template #body="{ data }">
          <Tag :value="data.attivo ? 'Sì' : 'No'" :severity="data.attivo ? 'success' : 'secondary'" />
        </template>
      </Column>
      <Column v-if="isAdmin" header="Azioni" style="width: 110px">
        <template #body="{ data }">
          <div style="display:flex; gap:0.4rem">
            <Link :href="`/clienti/${data.id}/edit`"><Button icon="pi pi-pencil" aria-label="Modifica" size="small" outlined /></Link>
            <Button icon="pi pi-trash" aria-label="Elimina" size="small" outlined severity="danger" @click="confirmDelete(data)" />
          </div>
        </template>
      </Column>

      <template #empty>
        <div class="empty-state">Nessun cliente trovato.</div>
      </template>
    </DataTable>

    <div v-if="clienti.last_page > 1" class="pagination">
      <Button
        icon="pi pi-chevron-left" aria-label="Pagina precedente"
        outlined
        size="small"
        :disabled="!clienti.prev_page_url"
        @click="router.visit(clienti.prev_page_url)"
      />
      <span class="page-info">
        Pagina {{ clienti.current_page }} di {{ clienti.last_page }}
        ({{ clienti.total }} clienti)
      </span>
      <Button
        icon="pi pi-chevron-right" aria-label="Pagina successiva"
        outlined
        size="small"
        :disabled="!clienti.next_page_url"
        @click="router.visit(clienti.next_page_url)"
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
import Tag from 'primevue/tag';
import ToggleSwitch from 'primevue/toggleswitch';

const props = defineProps({
  clienti: Object,
  filters: Object,
});

const confirm = useConfirm();
const page = usePage();
const isAdmin = computed(() => page.props.auth?.user?.role === 'admin');

const filters = ref({
  search: props.filters?.search ?? '',
  solo_attivi: props.filters?.solo_attivi ?? '',
});

const soloAttivi = ref(!!props.filters?.solo_attivi);

let searchTimeout = null;
function debouncedSearch() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => applyFilters(), 400);
}

function applyFilters() {
  router.get('/clienti', {
    search: filters.value.search,
    solo_attivi: soloAttivi.value ? '1' : '',
  }, { preserveState: true, replace: true });
}

function confirmDelete(cliente) {
  confirm.require({
    message: `Eliminare "${cliente.ragione_sociale}"?`,
    header: 'Conferma eliminazione',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Elimina',
    rejectLabel: 'Annulla',
    acceptClass: 'p-button-danger',
    accept: () => router.delete(`/clienti/${cliente.id}`),
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
.filters-bar {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex-wrap: wrap;
}
.toggle-filter {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  color: var(--ink-2);
}
.row-link {
  color: var(--info);
  text-decoration: none;
  font-weight: 500;
}
.row-link:hover { text-decoration: underline; }
.text-muted { color: var(--ink-3); }
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
