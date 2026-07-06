<template>
  <AppLayout>
    <div class="page-header">
      <h1 class="page-title">Destinazione Ingredienti</h1>
    </div>
    <p class="desc">Definisce quali materie prime possono essere usate in ciascun prodotto finito.</p>

    <div class="layout">
      <!-- ADD FORM (admin only) -->
      <div v-if="isAdmin" class="add-card">
        <h2 class="section-title">Aggiungi collegamento</h2>
        <div class="add-form">
          <div class="field">
            <label>Prodotto *</label>
            <Select
              v-model="form.prodotto_id"
              :options="prodotti"
              :option-label="p => `${p.codice_prodotto} — ${p.nome}`"
              option-value="id"
              placeholder="Seleziona prodotto..."
              filter
              fluid
              :invalid="!!form.errors.prodotto_id"
            />
            <small class="error">{{ form.errors.prodotto_id }}</small>
          </div>
          <div class="field">
            <label>Materia Prima *</label>
            <Select
              v-model="form.materia_prima_id"
              :options="materie"
              option-label="nome"
              option-value="id"
              placeholder="Seleziona materia prima..."
              filter
              fluid
              :invalid="!!form.errors.materia_prima_id"
            />
            <small class="error">{{ form.errors.materia_prima_id }}</small>
          </div>
          <Button label="Aggiungi" icon="pi pi-plus" @click="submit" :loading="form.processing" />
        </div>
      </div>

      <!-- TABLE -->
      <div class="table-card">
        <DataTable :value="destinazioni" striped-rows size="small" :group-rows-by="'prodotto.nome'" row-group-mode="rowspan">
          <Column header="Prodotto">
            <template #body="{ data }">
              <span class="prodotto-name">{{ data.prodotto?.nome ?? '—' }}</span>
              <span class="prodotto-code">{{ data.prodotto?.codice_prodotto }}</span>
            </template>
          </Column>
          <Column header="Materia Prima">
            <template #body="{ data }">{{ data.materia_prima?.nome ?? '—' }}</template>
          </Column>
          <Column v-if="isAdmin" header="Rimuovi" style="width:80px">
            <template #body="{ data }">
              <Button icon="pi pi-trash" aria-label="Elimina" size="small" outlined severity="danger" @click="confirmDelete(data)" />
            </template>
          </Column>
          <template #empty><div class="empty-state">Nessun collegamento definito.</div></template>
        </DataTable>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import { useConfirm } from 'primevue/useconfirm';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Select from 'primevue/select';

const props = defineProps({ destinazioni: Array, prodotti: Array, materie: Array });
const confirm = useConfirm();
const page = usePage();
const isAdmin = computed(() => page.props.auth?.user?.role === 'admin');

const form = useForm({ prodotto_id: null, materia_prima_id: null });

function submit() {
  form.post('/destinazione-ingredienti', { onSuccess: () => form.reset() });
}

function confirmDelete(d) {
  confirm.require({
    message: `Rimuovere "${d.materia_prima?.nome}" da "${d.prodotto?.nome}"?`,
    header: 'Conferma rimozione',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Rimuovi', rejectLabel: 'Annulla', acceptClass: 'p-button-danger',
    accept: () => router.delete(`/destinazione-ingredienti/${d.id}`),
  });
}
</script>

<style scoped>
.page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:0.5rem; }
.page-title { font-size:1.5rem; font-weight:700; color:var(--ink); margin:0; }
.desc { color:var(--ink-2); margin:0 0 1.5rem 0; font-size:0.875rem; }
.layout { display:grid; grid-template-columns:340px 1fr; gap:1.5rem; align-items:start; }
.add-card { background:var(--surface); border-radius:8px; border:1px solid var(--border); padding:1.25rem; }
.table-card { background:var(--surface); border-radius:8px; border:1px solid var(--border); overflow:hidden; }
.section-title { font-size:0.85rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:var(--ink-2); margin:0 0 1rem 0; }
.add-form { display:flex; flex-direction:column; gap:0.75rem; }
.field { display:flex; flex-direction:column; gap:0.3rem; }
.field label { font-size:0.85rem; font-weight:600; color:var(--ink-2); }
.error { color:var(--danger); font-size:0.78rem; min-height:1em; }
.prodotto-name { font-weight:600; display:block; }
.prodotto-code { font-size:0.78rem; color:var(--ink-2); }
.empty-state { padding:2rem; text-align:center; color:var(--ink-3); }
</style>
