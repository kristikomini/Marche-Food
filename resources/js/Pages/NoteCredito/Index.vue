<template>
  <AppLayout>
    <div class="page-header">
      <h1 class="page-title">Note di Credito</h1>
      <Link href="/note-credito/create"><Button label="Nuova Nota Credito" icon="pi pi-plus" /></Link>
    </div>
    <div class="filters-bar">
      <IconField>
        <InputIcon class="pi pi-search" />
        <InputText v-model="filters.search" placeholder="Cerca numero nota..." @input="debouncedSearch" style="width:260px" />
      </IconField>
    </div>
    <DataTable :value="note.data" class="mt-4" striped-rows size="small">
      <Column header="Data" style="width:110px">
        <template #body="{ data }">{{ formatDate(data.data_documento) }}</template>
      </Column>
      <Column header="Numero NC" style="width:140px">
        <template #body="{ data }">
          <Link :href="`/note-credito/${data.id}/edit`" class="row-link">{{ data.numero_documento ?? '—' }}</Link>
        </template>
      </Column>
      <Column header="Cliente">
        <template #body="{ data }">{{ data.vendita?.cliente?.ragione_sociale ?? '—' }}</template>
      </Column>
      <Column header="Doc. Vendita" style="width:130px">
        <template #body="{ data }">
          <span class="text-muted">{{ data.vendita?.numero_documento ?? '—' }}</span>
        </template>
      </Column>
      <Column header="Bolla Reso" style="width:130px">
        <template #body="{ data }">{{ data.bolla_reso?.numero_bolla ?? '—' }}</template>
      </Column>
      <Column header="Importo €" style="width:110px">
        <template #body="{ data }">{{ data.importo != null ? Number(data.importo).toFixed(2) + ' €' : '—' }}</template>
      </Column>
      <Column header="Azioni" style="width:100px">
        <template #body="{ data }">
          <div style="display:flex;gap:0.4rem">
            <Link :href="`/note-credito/${data.id}/edit`"><Button icon="pi pi-pencil" size="small" outlined /></Link>
            <Button v-if="isAdmin" icon="pi pi-trash" size="small" outlined severity="danger" @click="confirmDelete(data)" />
          </div>
        </template>
      </Column>
      <template #empty><div class="empty-state">Nessuna nota di credito trovata.</div></template>
    </DataTable>
    <div v-if="note.last_page > 1" class="pagination">
      <Button icon="pi pi-chevron-left" outlined size="small" :disabled="!note.prev_page_url" @click="router.visit(note.prev_page_url)" />
      <span class="page-info">{{ note.current_page }} / {{ note.last_page }} ({{ note.total }})</span>
      <Button icon="pi pi-chevron-right" outlined size="small" :disabled="!note.next_page_url" @click="router.visit(note.next_page_url)" />
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

const props = defineProps({ note: Object, filters: Object });
const confirm = useConfirm();
const page = usePage();
const isAdmin = computed(() => page.props.auth?.user?.role === 'admin');
const filters = ref({ search: props.filters?.search ?? '' });
let t = null;
function debouncedSearch() { clearTimeout(t); t = setTimeout(() => router.get('/note-credito', filters.value, { preserveState: true, replace: true }), 400); }
function formatDate(d) { return d ? new Date(d).toLocaleDateString('it-IT', { day:'2-digit', month:'2-digit', year:'numeric' }) : '—'; }
function confirmDelete(n) {
  confirm.require({
    message: `Eliminare la nota credito "${n.numero_nc ?? n.id}"?`, header: 'Conferma eliminazione',
    icon: 'pi pi-exclamation-triangle', acceptLabel: 'Elimina', rejectLabel: 'Annulla', acceptClass: 'p-button-danger',
    accept: () => router.delete(`/note-credito/${n.id}`),
  });
}
</script>
<style scoped>
.page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:1.5rem; }
.page-title { font-size:1.5rem; font-weight:700; color:#1e293b; margin:0; }
.filters-bar { display:flex; gap:1rem; }
.row-link { color:#1d4ed8; text-decoration:none; font-weight:500; }
.row-link:hover { text-decoration:underline; }
.text-muted { color: #94a3b8; }
.mt-4 { margin-top:1rem; }
.pagination { display:flex; align-items:center; gap:1rem; margin-top:1rem; justify-content:center; }
.page-info { font-size:0.875rem; color:#64748b; }
.empty-state { padding:2rem; text-align:center; color:#94a3b8; }
</style>
