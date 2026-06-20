<template>
  <AppLayout>
    <div class="page-header">
      <h1 class="page-title">{{ isEdit ? 'Modifica Fornitore' : 'Nuovo Fornitore' }}</h1>
      <Link href="/fornitori">
        <Button label="Annulla" outlined icon="pi pi-arrow-left" />
      </Link>
    </div>

    <form @submit.prevent="submit" class="form-card">

      <!-- DATI GENERALI -->
      <section class="form-section">
        <h2 class="section-title">Dati generali</h2>
        <div class="form-grid">

          <div class="field">
            <label>Ragione Sociale *</label>
            <InputText v-model="form.ragione_sociale" :invalid="!!form.errors.ragione_sociale" fluid />
            <small class="error">{{ form.errors.ragione_sociale }}</small>
          </div>

          <div class="field">
            <label>Tipo *</label>
            <Select
              v-model="form.tipo"
              :options="tipoOptions"
              option-label="label"
              option-value="value"
              placeholder="Seleziona tipo..."
              :invalid="!!form.errors.tipo"
              fluid
            />
            <small class="error">{{ form.errors.tipo }}</small>
          </div>

          <div class="field">
            <label>Codice</label>
            <InputText v-model="form.codice" :invalid="!!form.errors.codice" fluid />
            <small class="error">{{ form.errors.codice }}</small>
          </div>

          <div class="field">
            <label>P. IVA</label>
            <InputText v-model="form.piva" fluid />
          </div>

          <div class="field field-full">
            <label>Indirizzo</label>
            <InputText v-model="form.indirizzo" fluid />
          </div>

          <div class="field">
            <label>Email</label>
            <InputText v-model="form.email" type="email" fluid />
          </div>

          <div class="field">
            <label>Telefono</label>
            <InputText v-model="form.telefono" fluid />
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

      <!-- CERTIFICAZIONI HACCP (solo alimentare) -->
      <section v-if="form.tipo === 'alimentare'" class="form-section">
        <h2 class="section-title">Certificazioni HACCP</h2>
        <div class="form-grid">

          <div class="field field-inline">
            <ToggleSwitch v-model="form.haccp_certificato" input-id="haccp_cert" />
            <label for="haccp_cert">Certificato HACCP</label>
          </div>

          <div class="field">
            <label>Scadenza HACCP</label>
            <DatePicker v-model="form.haccp_scadenza" date-format="dd/mm/yy" fluid show-button-bar />
          </div>

          <div class="field field-full">
            <label>Certificazioni volontarie / note</label>
            <Textarea v-model="form.certificazioni_note" rows="3" fluid />
          </div>

        </div>
      </section>

      <!-- CERTIFICAZIONI MOCA (solo imballaggio primario) -->
      <section v-if="form.tipo === 'imballaggio_primario'" class="form-section">
        <h2 class="section-title">Certificazioni MOCA</h2>
        <div class="form-grid">

          <div class="field field-inline">
            <ToggleSwitch v-model="form.moca_certificato" input-id="moca_cert" />
            <label for="moca_cert">Certificato MOCA</label>
          </div>

          <div class="field">
            <label>Numero certificato MOCA</label>
            <InputText v-model="form.moca_numero" fluid />
          </div>

        </div>
      </section>

      <!-- SUBMIT -->
      <div class="form-actions">
        <Button
          type="submit"
          :label="isEdit ? 'Salva modifiche' : 'Crea fornitore'"
          icon="pi pi-check"
          :loading="form.processing"
        />
      </div>

    </form>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import DatePicker from 'primevue/datepicker';
import ToggleSwitch from 'primevue/toggleswitch';

const props = defineProps({
  fornitore: Object,
});

const isEdit = computed(() => !!props.fornitore);

const form = useForm({
  codice:              props.fornitore?.codice              ?? '',
  ragione_sociale:     props.fornitore?.ragione_sociale     ?? '',
  tipo:                props.fornitore?.tipo                ?? '',
  piva:                props.fornitore?.piva                ?? '',
  indirizzo:           props.fornitore?.indirizzo           ?? '',
  email:               props.fornitore?.email               ?? '',
  telefono:            props.fornitore?.telefono            ?? '',
  haccp_certificato:   props.fornitore?.haccp_certificato   ?? false,
  haccp_scadenza:      props.fornitore?.haccp_scadenza ? new Date(props.fornitore.haccp_scadenza) : null,
  certificazioni_note: props.fornitore?.certificazioni_note ?? '',
  moca_certificato:    props.fornitore?.moca_certificato    ?? false,
  moca_numero:         props.fornitore?.moca_numero         ?? '',
  attivo:              props.fornitore?.attivo              ?? true,
  note:                props.fornitore?.note                ?? '',
});

const tipoOptions = [
  { label: 'Alimentare',                    value: 'alimentare' },
  { label: 'Imballaggio Primario (MOCA)',   value: 'imballaggio_primario' },
  { label: 'Detergente / Imb. Secondario',  value: 'detergente_secondario' },
];

function submit() {
  const payload = {
    ...form.data(),
    haccp_scadenza: form.haccp_scadenza ? form.haccp_scadenza.toISOString().slice(0, 10) : null,
  };
  if (isEdit.value) {
    form.transform(() => payload).put(`/fornitori/${props.fornitore.id}`);
  } else {
    form.transform(() => payload).post('/fornitori');
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
.form-section:last-of-type { border-bottom: none; }
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
