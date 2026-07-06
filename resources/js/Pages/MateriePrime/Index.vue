<template>
  <AppLayout>
    <div class="page-header">
      <h1 class="page-title">Materie Prime</h1>
      <Link v-if="isAdmin" href="/materie-prime/create"><Button label="Nuova Materia Prima" icon="pi pi-plus" /></Link>
    </div>
    <div class="filters-bar">
      <IconField>
        <InputIcon class="pi pi-search" />
        <InputText v-model="filters.search" placeholder="Cerca per nome o codice..." @input="debouncedSearch" style="width:280px" />
      </IconField>
    </div>
    <DataTable :value="materie.data" class="mt-4" striped-rows size="small">
      <Column field="codice" header="Codice" style="width:90px">
        <template #body="{ data }"><span class="text-muted">{{ data.codice ?? '—' }}</span></template>
      </Column>
      <Column field="nome" header="Nome">
        <template #body="{ data }">
          <Link v-if="isAdmin" :href="`/materie-prime/${data.id}/edit`" class="row-link">{{ data.nome }}</Link>
          <span v-else>{{ data.nome }}</span>
        </template>
      </Column>
      <Column header="Allergeni">
        <template #body="{ data }">
          <div class="allergen-chips">
            <span v-for="code in (data.allergeni || [])" :key="code" class="chip chip-contiene">{{ allergeniLabels[code] || code }}</span>
            <span v-for="code in (data.allergeni_tracce || [])" :key="`t-${code}`" class="chip chip-tracce">tracce: {{ allergeniLabels[code] || code }}</span>
            <span v-if="!(data.allergeni || []).length && !(data.allergeni_tracce || []).length" class="text-muted">—</span>
          </div>
        </template>
      </Column>
      <Column v-if="isAdmin" header="Azioni" style="width:100px">
        <template #body="{ data }">
          <div style="display:flex;gap:0.4rem">
            <Link :href="`/materie-prime/${data.id}/edit`"><Button icon="pi pi-pencil" aria-label="Modifica" size="small" outlined /></Link>
            <Button icon="pi pi-trash" aria-label="Elimina" size="small" outlined severity="danger" @click="confirmDelete(data)" />
          </div>
        </template>
      </Column>
      <template #empty><div class="empty-state">Nessuna materia prima trovata.</div></template>
    </DataTable>
    <div v-if="materie.last_page > 1" class="pagination">
      <Button icon="pi pi-chevron-left" aria-label="Pagina precedente" outlined size="small" :disabled="!materie.prev_page_url" @click="router.visit(materie.prev_page_url)" />
      <span class="page-info">{{ materie.current_page }} / {{ materie.last_page }} ({{ materie.total }})</span>
      <Button icon="pi pi-chevron-right" aria-label="Pagina successiva" outlined size="small" :disabled="!materie.next_page_url" @click="router.visit(materie.next_page_url)" />
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

const props = defineProps({ materie: Object, filters: Object, allergeniLabels: { type: Object, default: () => ({}) } });
const allergeniLabels = props.allergeniLabels;
const confirm = useConfirm();
const page = usePage();
const isAdmin = computed(() => page.props.auth?.user?.role === 'admin');
const filters = ref({ search: props.filters?.search ?? '' });
let t = null;
function debouncedSearch() { clearTimeout(t); t = setTimeout(() => router.get('/materie-prime', filters.value, { preserveState: true, replace: true }), 400); }
function confirmDelete(m) {
  confirm.require({
    message: `Eliminare "${m.nome}"?`, header: 'Conferma eliminazione',
    icon: 'pi pi-exclamation-triangle', acceptLabel: 'Elimina', rejectLabel: 'Annulla', acceptClass: 'p-button-danger',
    accept: () => router.delete(`/materie-prime/${m.id}`),
  });
}
</script>

<style scoped>
.page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:1.5rem; }
.page-title { font-size:1.5rem; font-weight:700; color:#1e293b; margin:0; }
.filters-bar { display:flex; gap:1rem; }
.row-link { color:#1d4ed8; text-decoration:none; font-weight:500; }
.row-link:hover { text-decoration:underline; }
.text-muted { color:#94a3b8; }
.allergen-chips { display:flex; flex-wrap:wrap; gap:0.3rem; }
.chip { font-size:0.68rem; font-weight:600; padding:0.1rem 0.45rem; border-radius:99px; white-space:nowrap; }
.chip-contiene { background:#fef2f2; color:#b91c1c; }
.chip-tracce { background:#fffbeb; color:#b45309; }
.mt-4 { margin-top:1rem; }
.pagination { display:flex; align-items:center; gap:1rem; margin-top:1rem; justify-content:center; }
.page-info { font-size:0.875rem; color:#64748b; }
.empty-state { padding:2rem; text-align:center; color:#94a3b8; }
</style>
