<template>
  <AppLayout>
    <div class="page-header">
      <h1 class="page-title">Bolle Reso</h1>
      <Link href="/bolle-reso/create"><Button label="Nuova Bolla Reso" icon="pi pi-plus" /></Link>
    </div>
    <div class="filters-bar">
      <IconField>
        <InputIcon class="pi pi-search" />
        <InputText v-model="filters.search" placeholder="Cerca per numero bolla..." @input="debouncedSearch" style="width:260px" />
      </IconField>
    </div>
    <DataTable :value="bolle.data" class="mt-4" striped-rows size="small">
      <Column header="Data Reso" style="width:110px">
        <template #body="{ data }">{{ formatDate(data.data_reso) }}</template>
      </Column>
      <Column header="N° Bolla" style="width:140px">
        <template #body="{ data }">
          <Link :href="`/bolle-reso/${data.id}/edit`" class="row-link">{{ data.numero_bolla ?? '—' }}</Link>
        </template>
      </Column>
      <Column header="Cliente">
        <template #body="{ data }">{{ data.vendita_riga?.vendita?.cliente?.ragione_sociale ?? '—' }}</template>
      </Column>
      <Column header="Doc. Vendita" style="width:130px">
        <template #body="{ data }">
          <span class="text-muted">{{ data.vendita_riga?.vendita?.numero_documento ?? '—' }}</span>
        </template>
      </Column>
      <Column header="Prodotto">
        <template #body="{ data }">{{ data.vendita_riga?.nome_prodotto ?? '—' }}</template>
      </Column>
      <Column header="Q.tà Kg" style="width:100px">
        <template #body="{ data }">{{ Number(data.quantita_kg).toFixed(3) }} kg</template>
      </Column>
      <Column header="Azioni" style="width:100px">
        <template #body="{ data }">
          <div style="display:flex;gap:0.4rem">
            <Link :href="`/bolle-reso/${data.id}/edit`"><Button icon="pi pi-pencil" aria-label="Modifica" size="small" outlined /></Link>
            <Button v-if="isAdmin" icon="pi pi-trash" aria-label="Elimina" size="small" outlined severity="danger" @click="confirmDelete(data)" />
          </div>
        </template>
      </Column>
      <template #empty><div class="empty-state">Nessuna bolla reso trovata.</div></template>
    </DataTable>
    <div v-if="bolle.last_page > 1" class="pagination">
      <Button icon="pi pi-chevron-left" aria-label="Pagina precedente" outlined size="small" :disabled="!bolle.prev_page_url" @click="router.visit(bolle.prev_page_url)" />
      <span class="page-info">{{ bolle.current_page }} / {{ bolle.last_page }} ({{ bolle.total }})</span>
      <Button icon="pi pi-chevron-right" aria-label="Pagina successiva" outlined size="small" :disabled="!bolle.next_page_url" @click="router.visit(bolle.next_page_url)" />
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

const props = defineProps({ bolle: Object, filters: Object });
const confirm = useConfirm();
const page = usePage();
const isAdmin = computed(() => page.props.auth?.user?.role === 'admin');
const filters = ref({ search: props.filters?.search ?? '' });
let t = null;
function debouncedSearch() { clearTimeout(t); t = setTimeout(() => router.get('/bolle-reso', filters.value, { preserveState: true, replace: true }), 400); }
function formatDate(d) { return d ? new Date(d).toLocaleDateString('it-IT', { day:'2-digit', month:'2-digit', year:'numeric' }) : '—'; }
function confirmDelete(b) {
  confirm.require({
    message: `Eliminare la bolla reso "${b.numero_bolla ?? b.id}"?`, header: 'Conferma eliminazione',
    icon: 'pi pi-exclamation-triangle', acceptLabel: 'Elimina', rejectLabel: 'Annulla', acceptClass: 'p-button-danger',
    accept: () => router.delete(`/bolle-reso/${b.id}`),
  });
}
</script>
<style scoped>
.page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:1.5rem; }
.page-title { font-size:1.5rem; font-weight:700; color:var(--ink); margin:0; }
.filters-bar { display:flex; gap:1rem; }
.row-link { color:var(--info); text-decoration:none; font-weight:500; }
.row-link:hover { text-decoration:underline; }
.text-muted { color: var(--ink-3); }
.mt-4 { margin-top:1rem; }
.pagination { display:flex; align-items:center; gap:1rem; margin-top:1rem; justify-content:center; }
.page-info { font-size:0.875rem; color:var(--ink-2); }
.empty-state { padding:2rem; text-align:center; color:var(--ink-3); }
</style>
