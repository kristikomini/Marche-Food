<template>
  <AppLayout>
    <div class="page-header">
      <h1 class="page-title">{{ isEdit ? 'Modifica Cliente' : 'Nuovo Cliente' }}</h1>
      <Link href="/clienti">
        <Button label="Annulla" outlined icon="pi pi-arrow-left" aria-label="Indietro" />
      </Link>
    </div>

    <form @submit.prevent="submit" class="form-card">
      <section class="form-section">
        <h2 class="section-title">Dati generali</h2>
        <div class="form-grid">

          <div class="field">
            <label>Ragione Sociale *</label>
            <InputText v-model="form.ragione_sociale" :invalid="!!form.errors.ragione_sociale" fluid />
            <small class="error">{{ form.errors.ragione_sociale }}</small>
          </div>

          <div class="field">
            <label>Codice Cliente</label>
            <InputText v-model="form.codice_cliente" :invalid="!!form.errors.codice_cliente" fluid />
            <small class="error">{{ form.errors.codice_cliente }}</small>
          </div>

          <div class="field">
            <label>P. IVA</label>
            <InputText v-model="form.piva" fluid />
          </div>

          <div class="field">
            <label>Telefono</label>
            <InputText v-model="form.telefono" fluid />
          </div>

          <div class="field">
            <label>Email</label>
            <InputText v-model="form.email" type="email" fluid />
          </div>

          <div class="field field-full">
            <label>Indirizzo</label>
            <InputText v-model="form.indirizzo" fluid />
          </div>

          <div class="field field-full">
            <label>Note</label>
            <Textarea v-model="form.note" rows="2" fluid />
          </div>

          <div class="field field-inline">
            <ToggleSwitch v-model="form.attivo" input-id="attivo" />
            <label for="attivo">Attivo</label>
          </div>

        </div>
      </section>

      <div class="form-actions">
        <Button
          type="submit"
          :label="isEdit ? 'Salva modifiche' : 'Crea cliente'"
          icon="pi pi-check"
          :loading="form.processing"
        />
      </div>
    </form>
  </AppLayout>
</template>

<script setup>
import { computed, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import ToggleSwitch from 'primevue/toggleswitch';

const props = defineProps({
  cliente: Object,
});

const isEdit = computed(() => !!props.cliente);

const form = useForm({
  codice_cliente:  props.cliente?.codice_cliente  ?? '',
  ragione_sociale: props.cliente?.ragione_sociale ?? '',
  piva:            props.cliente?.piva            ?? '',
  indirizzo:       props.cliente?.indirizzo       ?? '',
  email:           props.cliente?.email           ?? '',
  telefono:        props.cliente?.telefono        ?? '',
  attivo:          props.cliente?.attivo          ?? true,
  note:            props.cliente?.note            ?? '',
});

watch(() => props.cliente, (c) => {
  form.codice_cliente  = c?.codice_cliente  ?? '';
  form.ragione_sociale = c?.ragione_sociale ?? '';
  form.piva            = c?.piva            ?? '';
  form.indirizzo       = c?.indirizzo       ?? '';
  form.email           = c?.email           ?? '';
  form.telefono        = c?.telefono        ?? '';
  form.attivo          = c?.attivo          ?? true;
  form.note            = c?.note            ?? '';
  form.clearErrors();
});

function submit() {
  if (isEdit.value) {
    form.put(`/clienti/${props.cliente.id}`);
  } else {
    form.post('/clienti');
  }
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
  color: #1e293b;
  margin: 0;
}
.form-card {
  background: #fff;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  overflow: hidden;
}
.form-section {
  padding: 1.5rem;
  border-bottom: 1px solid #f1f5f9;
}
.section-title {
  font-size: 0.9rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #64748b;
  margin: 0 0 1rem 0;
}
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}
.field { display: flex; flex-direction: column; gap: 0.3rem; }
.field label { font-size: 0.85rem; font-weight: 600; color: #374151; }
.field-full { grid-column: 1 / -1; }
.field-inline { flex-direction: row; align-items: center; gap: 0.6rem; }
.field-inline label { margin: 0; font-weight: 500; }
.error { color: #dc2626; font-size: 0.78rem; min-height: 1em; }
.form-actions {
  padding: 1.25rem 1.5rem;
  background: #f8fafc;
  display: flex;
  justify-content: flex-end;
}
</style>
