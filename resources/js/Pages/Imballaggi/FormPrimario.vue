<template>
  <AppLayout>
    <div class="page-header">
      <h1 class="page-title">{{ isEdit ? 'Modifica Lotto Imballaggio' : 'Nuovo Lotto Imballaggio' }}</h1>
      <Link href="/imballaggi?tab=primari">
        <Button label="Annulla" outlined icon="pi pi-arrow-left" />
      </Link>
    </div>

    <form @submit.prevent="submit" class="form-card">
      <section class="form-section">
        <h2 class="section-title">Imballaggio Primario (MOCA)</h2>
        <div class="form-grid">

          <div class="field field-full">
            <label>Fornitore *</label>
            <Select
              v-model="form.fornitore_id"
              :options="fornitori"
              option-label="ragione_sociale"
              option-value="id"
              placeholder="Seleziona fornitore MOCA..."
              :invalid="!!form.errors.fornitore_id"
              filter
              fluid
            />
            <small class="error">{{ form.errors.fornitore_id }}</small>
          </div>

          <div class="field">
            <label>Componente *</label>
            <InputText v-model="form.componente" :invalid="!!form.errors.componente" fluid placeholder="es. Vaschetta PP 500g, Film PE..." />
            <small class="error">{{ form.errors.componente }}</small>
          </div>

          <div class="field">
            <label>Codice Articolo</label>
            <InputText v-model="form.codice_articolo" fluid />
          </div>

          <div class="field">
            <label>Quantità</label>
            <InputNumber v-model="form.quantita" :min-fraction-digits="0" :max-fraction-digits="3" fluid />
          </div>

          <div class="field">
            <label>U.M.</label>
            <Select
              v-model="form.um"
              :options="umOptions"
              option-label="label"
              option-value="value"
              placeholder="—"
              fluid
            />
          </div>

          <div class="field">
            <label>Lotto</label>
            <InputText v-model="form.lotto" fluid />
          </div>

          <div class="field">
            <label>N° DDT</label>
            <InputText v-model="form.numero_ddt" fluid />
          </div>

          <div class="field">
            <label>Data Entrata *</label>
            <DatePicker v-model="form.data_in" date-format="dd/mm/yy" :invalid="!!form.errors.data_in" show-button-bar fluid />
            <small class="error">{{ form.errors.data_in }}</small>
          </div>

          <div class="field">
            <label>Data Uscita</label>
            <DatePicker v-model="form.data_out" date-format="dd/mm/yy" show-button-bar fluid />
          </div>

          <div class="field field-full">
            <label>Note</label>
            <Textarea v-model="form.note" rows="2" fluid />
          </div>

        </div>
      </section>

      <div class="form-actions">
        <Button type="submit" :label="isEdit ? 'Salva modifiche' : 'Registra lotto'" icon="pi pi-check" :loading="form.processing" />
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
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import DatePicker from 'primevue/datepicker';
import Textarea from 'primevue/textarea';

const props = defineProps({ lotto: Object, fornitori: Array });
const isEdit = computed(() => !!props.lotto);

const umOptions = [
  { label: 'Pz', value: 'pz' }, { label: 'Rotoli', value: 'rot' },
  { label: 'Scatole', value: 'scat' }, { label: 'Kg', value: 'kg' },
  { label: 'Mt', value: 'mt' },
];

const form = useForm({
  fornitore_id:   props.lotto?.fornitore_id   ?? null,
  componente:     props.lotto?.componente     ?? '',
  codice_articolo: props.lotto?.codice_articolo ?? '',
  um:             props.lotto?.um             ?? 'pz',
  quantita:       props.lotto?.quantita       ? Number(props.lotto.quantita) : null,
  lotto:          props.lotto?.lotto          ?? '',
  numero_ddt:     props.lotto?.numero_ddt     ?? '',
  data_in:        props.lotto?.data_in        ? new Date(props.lotto.data_in)  : null,
  data_out:       props.lotto?.data_out       ? new Date(props.lotto.data_out) : null,
  note:           props.lotto?.note           ?? '',
});

watch(() => props.lotto, (l) => {
  form.fornitore_id    = l?.fornitore_id    ?? null;
  form.componente      = l?.componente      ?? '';
  form.codice_articolo = l?.codice_articolo ?? '';
  form.um              = l?.um              ?? 'pz';
  form.quantita        = l?.quantita        ? Number(l.quantita) : null;
  form.lotto           = l?.lotto           ?? '';
  form.numero_ddt      = l?.numero_ddt      ?? '';
  form.data_in         = l?.data_in         ? new Date(l.data_in)  : null;
  form.data_out        = l?.data_out        ? new Date(l.data_out) : null;
  form.note            = l?.note            ?? '';
  form.clearErrors();
});

function submit() {
  const payload = {
    ...form.data(),
    data_in:  form.data_in  ? form.data_in.toISOString().slice(0, 10)  : null,
    data_out: form.data_out ? form.data_out.toISOString().slice(0, 10) : null,
  };
  if (isEdit.value) {
    form.transform(() => payload).put(`/imballaggi/primari/${props.lotto.id}`);
  } else {
    form.transform(() => payload).post('/imballaggi/primari');
  }
}
</script>

<style scoped>
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; }
.page-title { font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0; }
.form-card { background: #fff; border-radius: 8px; border: 1px solid #e2e8f0; overflow: hidden; }
.form-section { padding: 1.5rem; }
.section-title { font-size: 0.9rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin: 0 0 1rem 0; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.field { display: flex; flex-direction: column; gap: 0.3rem; }
.field label { font-size: 0.85rem; font-weight: 600; color: #374151; }
.field-full { grid-column: 1 / -1; }
.error { color: #dc2626; font-size: 0.78rem; min-height: 1em; }
.form-actions { padding: 1.25rem 1.5rem; background: #f8fafc; display: flex; justify-content: flex-end; border-top: 1px solid #e2e8f0; }
</style>
