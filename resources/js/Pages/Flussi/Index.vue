<template>
  <AppLayout>
    <div class="page-header">
      <h1 class="page-title">Flussi di Lavorazione</h1>
    </div>
    <p class="desc">Elenco fasi del processo produttivo HACCP (usate nelle schede di produzione).</p>

    <div class="layout">
      <!-- ADD / EDIT FORM -->
      <div class="add-card">
        <h2 class="section-title">{{ editing ? 'Modifica flusso' : 'Nuovo flusso' }}</h2>
        <div class="add-form">
          <div class="field">
            <label>N° Fase *</label>
            <InputNumber v-model="form.numero" :min="1" :invalid="!!form.errors.numero" fluid />
            <small class="error">{{ form.errors.numero }}</small>
          </div>
          <div class="field">
            <label>Nome Fase *</label>
            <InputText v-model="form.nome" :invalid="!!form.errors.nome" fluid placeholder="es. Ricezione materie prime" />
            <small class="error">{{ form.errors.nome }}</small>
          </div>
          <div class="field">
            <label>Punto di Controllo (CCP)</label>
            <InputText v-model="form.controllo" fluid placeholder="es. Verifica temperatura" />
          </div>
          <div class="field">
            <label>Limite / Misura</label>
            <InputText v-model="form.misura" fluid placeholder="es. ≤ 4°C" />
          </div>
          <div style="display:flex;gap:0.5rem">
            <Button :label="editing ? 'Aggiorna' : 'Aggiungi'" icon="pi pi-check" @click="submit" :loading="form.processing" />
            <Button v-if="editing" label="Annulla" outlined @click="cancelEdit" />
          </div>
        </div>
      </div>

      <!-- TABLE -->
      <div class="table-card">
        <DataTable :value="flussi" striped-rows size="small" :sort-field="'numero'" :sort-order="1">
          <Column field="numero" header="N°" style="width:60px" sortable />
          <Column field="nome" header="Fase" />
          <Column field="controllo" header="Controllo (CCP)" />
          <Column field="misura" header="Limite / Misura" />
          <Column header="Azioni" style="width:100px">
            <template #body="{ data }">
              <div style="display:flex;gap:0.4rem">
                <Button icon="pi pi-pencil" aria-label="Modifica" size="small" outlined @click="startEdit(data)" />
                <Button icon="pi pi-trash" aria-label="Elimina" size="small" outlined severity="danger" @click="confirmDelete(data)" />
              </div>
            </template>
          </Column>
          <template #empty><div class="empty-state">Nessun flusso definito.</div></template>
        </DataTable>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { useConfirm } from 'primevue/useconfirm';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';

const props = defineProps({ flussi: Array });
const confirm = useConfirm();
const editing = ref(null);

const form = useForm({ numero: null, nome: '', controllo: '', misura: '' });

function submit() {
  if (editing.value) {
    form.put(`/flussi/${editing.value.id}`, { onSuccess: () => cancelEdit() });
  } else {
    form.post('/flussi', { onSuccess: () => form.reset() });
  }
}

function startEdit(f) {
  editing.value = f;
  form.numero   = f.numero;
  form.nome     = f.nome;
  form.controllo = f.controllo ?? '';
  form.misura   = f.misura ?? '';
}

function cancelEdit() {
  editing.value = null;
  form.reset();
}

function confirmDelete(f) {
  confirm.require({
    message: `Eliminare la fase "${f.nome}"?`, header: 'Conferma eliminazione',
    icon: 'pi pi-exclamation-triangle', acceptLabel: 'Elimina', rejectLabel: 'Annulla', acceptClass: 'p-button-danger',
    accept: () => router.delete(`/flussi/${f.id}`),
  });
}
</script>

<style scoped>
.page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:0.5rem; }
.page-title { font-size:1.5rem; font-weight:700; color:var(--ink); margin:0; }
.desc { color:var(--ink-2); margin:0 0 1.5rem 0; font-size:0.875rem; }
.layout { display:grid; grid-template-columns:320px 1fr; gap:1.5rem; align-items:start; }
.add-card { background:var(--surface); border-radius:8px; border:1px solid var(--border); padding:1.25rem; }
.table-card { background:var(--surface); border-radius:8px; border:1px solid var(--border); overflow:hidden; }
.section-title { font-size:0.85rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:var(--ink-2); margin:0 0 1rem 0; }
.add-form { display:flex; flex-direction:column; gap:0.75rem; }
.field { display:flex; flex-direction:column; gap:0.3rem; }
.field label { font-size:0.85rem; font-weight:600; color:var(--ink-2); }
.error { color:var(--danger); font-size:0.78rem; min-height:1em; }
.empty-state { padding:2rem; text-align:center; color:var(--ink-3); }
</style>
