<template>
  <AppLayout>
    <div class="page-header">
      <h1 class="page-title">{{ isEdit ? 'Modifica Nota di Credito' : 'Nuova Nota di Credito' }}</h1>
      <Link href="/note-credito"><Button label="Annulla" outlined icon="pi pi-arrow-left" aria-label="Indietro" /></Link>
    </div>
    <form @submit.prevent="submit" class="form-card">
      <section class="form-section">
        <h2 class="section-title">Nota di credito</h2>
        <div class="form-grid">

          <div class="field">
            <label>Numero NC *</label>
            <InputText v-model="form.numero_documento" :invalid="!!form.errors.numero_documento" fluid />
            <small class="error">{{ form.errors.numero_documento }}</small>
          </div>

          <div class="field">
            <label>Data *</label>
            <DatePicker v-model="form.data_documento" date-format="dd/mm/yy" :invalid="!!form.errors.data_documento" show-button-bar fluid />
            <small class="error">{{ form.errors.data_documento }}</small>
          </div>

          <div class="field">
            <label>Vendita collegata</label>
            <Select
              v-model="form.vendita_id"
              :options="vendite"
              :option-label="v => `${v.numero_documento} — ${v.cliente?.ragione_sociale} (${formatDate(v.data_documento)})`"
              option-value="id"
              placeholder="Seleziona vendita..."
              filter
              show-clear
              fluid
            />
          </div>

          <div class="field">
            <label>Bolla Reso collegata</label>
            <Select
              v-model="form.bolla_reso_id"
              :options="bolleReso"
              :option-label="b => `${b.numero_bolla || ('Bolla #' + b.id)} — ${formatDate(b.data_reso)}`"
              option-value="id"
              placeholder="Seleziona bolla reso..."
              filter
              show-clear
              fluid
            />
          </div>

          <div class="field">
            <label>Importo €</label>
            <InputNumber v-model="form.importo" mode="currency" currency="EUR" locale="it-IT" :invalid="!!form.errors.importo" fluid />
            <small class="error">{{ form.errors.importo }}</small>
          </div>

          <div class="field field-full">
            <label>Note</label>
            <Textarea v-model="form.note" rows="2" fluid />
          </div>
        </div>
      </section>
      <div class="form-actions">
        <Button type="submit" :label="isEdit ? 'Salva modifiche' : 'Registra nota credito'" icon="pi pi-check" :loading="form.processing" />
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

const props = defineProps({ nota: Object, vendite: Array, bolleReso: Array });
const isEdit = computed(() => !!props.nota);

function formatDate(d) { return d ? new Date(d).toLocaleDateString('it-IT', { day:'2-digit', month:'2-digit', year:'numeric' }) : '—'; }

const form = useForm({
  numero_documento: props.nota?.numero_documento ?? '',
  vendita_id:       props.nota?.vendita_id       ?? null,
  bolla_reso_id:    props.nota?.bolla_reso_id    ?? null,
  importo:          props.nota?.importo           ? Number(props.nota.importo) : null,
  data_documento:   props.nota?.data_documento    ? new Date(props.nota.data_documento) : null,
  note:             props.nota?.note             ?? '',
});

watch(() => props.nota, (n) => {
  form.numero_documento = n?.numero_documento ?? '';
  form.vendita_id       = n?.vendita_id       ?? null;
  form.bolla_reso_id    = n?.bolla_reso_id    ?? null;
  form.importo          = n?.importo           ? Number(n.importo) : null;
  form.data_documento   = n?.data_documento    ? new Date(n.data_documento) : null;
  form.note             = n?.note             ?? '';
  form.clearErrors();
});

function submit() {
  const payload = { ...form.data(), data_documento: form.data_documento ? form.data_documento.toISOString().slice(0,10) : null };
  if (isEdit.value) {
    form.transform(() => payload).put(`/note-credito/${props.nota.id}`);
  } else {
    form.transform(() => payload).post('/note-credito');
  }
}
</script>

<style scoped>
.page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:1.5rem; }
.page-title { font-size:1.5rem; font-weight:700; color:var(--ink); margin:0; }
.form-card { background:var(--surface); border-radius:8px; border:1px solid var(--border); overflow:hidden; }
.form-section { padding:1.5rem; }
.section-title { font-size:0.9rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:var(--ink-2); margin:0 0 1rem 0; }
.form-grid { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
.field { display:flex; flex-direction:column; gap:0.3rem; }
.field label { font-size:0.85rem; font-weight:600; color:var(--ink-2); }
.field-full { grid-column:1 / -1; }
.error { color:var(--danger); font-size:0.78rem; min-height:1em; }
.form-actions { padding:1.25rem 1.5rem; background:var(--surface-2); display:flex; justify-content:flex-end; border-top:1px solid var(--border); }
</style>
