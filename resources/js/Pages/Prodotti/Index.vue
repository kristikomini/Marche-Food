<template>
  <AppLayout>
    <div class="page-header">
      <h1 class="page-title">Prodotti</h1>
      <Link v-if="isAdmin" href="/prodotti/create"><Button label="Nuovo Prodotto" icon="pi pi-plus" /></Link>
    </div>

    <div class="filters-bar">
      <IconField>
        <InputIcon class="pi pi-search" />
        <InputText v-model="filters.search" placeholder="Cerca per nome o codice..." @input="debouncedSearch" style="width:280px" />
      </IconField>
    </div>

    <DataTable :value="prodotti.data" class="mt-4" striped-rows size="small">
      <Column field="codice_prodotto" header="Codice" style="width:120px" />
      <Column field="nome" header="Nome">
        <template #body="{ data }">
          <Link v-if="isAdmin" :href="`/prodotti/${data.id}/edit`" class="row-link">{{ data.nome }}</Link>
          <span v-else>{{ data.nome }}</span>
        </template>
      </Column>
      <Column header="Pezzatura" style="width:130px">
        <template #body="{ data }">
          <span v-if="data.pezzatura_valore" class="text-muted">{{ data.pezzatura_valore }} {{ data.pezzatura_um }}</span>
          <span v-else class="text-muted">—</span>
        </template>
      </Column>
      <Column header="Attivo" style="width:80px">
        <template #body="{ data }">
          <Tag :value="data.attivo ? 'Sì' : 'No'" :severity="data.attivo ? 'success' : 'secondary'" />
        </template>
      </Column>
      <Column v-if="isAdmin" header="Azioni" style="width:100px">
        <template #body="{ data }">
          <div style="display:flex;gap:0.4rem">
            <Link :href="`/prodotti/${data.id}/edit`"><Button icon="pi pi-pencil" aria-label="Modifica" size="small" outlined /></Link>
            <Button icon="pi pi-trash" aria-label="Elimina" size="small" outlined severity="danger" @click="confirmDelete(data)" />
          </div>
        </template>
      </Column>
      <template #empty><EmptyState icon="pi pi-tag" title="Nessun prodotto" /></template>
    </DataTable>

    <div v-if="prodotti.last_page > 1" class="pagination">
      <Button icon="pi pi-chevron-left" aria-label="Pagina precedente" outlined size="small" :disabled="!prodotti.prev_page_url" @click="router.visit(prodotti.prev_page_url)" />
      <span class="page-info">{{ prodotti.current_page }} / {{ prodotti.last_page }} ({{ prodotti.total }})</span>
      <Button icon="pi pi-chevron-right" aria-label="Pagina successiva" outlined size="small" :disabled="!prodotti.next_page_url" @click="router.visit(prodotti.next_page_url)" />
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
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

const props = defineProps({ prodotti: Object, filters: Object });
const confirm = useConfirm();
const page = usePage();
const isAdmin = computed(() => page.props.auth?.user?.role === 'admin');
const filters = ref({ search: props.filters?.search ?? '' });

let t = null;
function debouncedSearch() { clearTimeout(t); t = setTimeout(() => router.get('/prodotti', filters.value, { preserveState: true, replace: true }), 400); }

function confirmDelete(p) {
  confirm.require({
    message: `Eliminare "${p.nome}"?`, header: 'Conferma eliminazione',
    icon: 'pi pi-exclamation-triangle', acceptLabel: 'Elimina', rejectLabel: 'Annulla', acceptClass: 'p-button-danger',
    accept: () => router.delete(`/prodotti/${p.id}`),
  });
}
</script>

<style scoped>
.page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:1.5rem; }
.page-title { font-size:1.5rem; font-weight:700; color:var(--ink); margin:0; }
.filters-bar { display:flex; gap:1rem; flex-wrap:wrap; }
.row-link { color:var(--info); text-decoration:none; font-weight:500; }
.row-link:hover { text-decoration:underline; }
.text-muted { color:var(--ink-3); }
.mt-4 { margin-top:1rem; }
.pagination { display:flex; align-items:center; gap:1rem; margin-top:1rem; justify-content:center; }
.page-info { font-size:0.875rem; color:var(--ink-2); }
.empty-state { padding:2rem; text-align:center; color:var(--ink-3); }
</style>
