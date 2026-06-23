<template>
  <AppLayout>
    <div class="page-header">
      <h1 class="page-title">{{ isEdit ? 'Modifica Bolla Reso' : 'Nuova Bolla Reso' }}</h1>
      <Link href="/bolle-reso"><Button label="Annulla" outlined icon="pi pi-arrow-left" /></Link>
    </div>
    <form @submit.prevent="submit" class="form-card">
      <section class="form-section">
        <h2 class="section-title">Bolla di reso</h2>
        <div class="form-grid">

          <div class="field field-full">
            <label>Vendita di riferimento *</label>
            <Select
              v-model="selectedVenditaId"
              :options="vendite"
              :option-label="v => `${v.numero_documento} — ${v.cliente?.ragione_sociale} (${formatDate(v.data_documento)})`"
              option-value="id"
              placeholder="Seleziona vendita..."
              filter
              fluid
              @change="form.vendita_riga_id = null"
            />
          </div>

          <div class="field field-full">
            <label>Riga prodotto da restituire *</label>
            <Select
              v-model="form.vendita_riga_id"
              :options="righeVendita"
              :option-label="r => `${r.nome_prodotto} — ${Number(r.quantita_kg).toFixed(3)} kg — Lotto: ${r.lotto || r.lotto_esterno || '—'}`"
              option-value="id"
              placeholder="Seleziona riga..."
              :disabled="!selectedVenditaId"
              :invalid="!!form.errors.vendita_riga_id"
              fluid
            />
            <small class="error">{{ form.errors.vendita_riga_id }}</small>
          </div>

          <div class="field">
            <label>N° Bolla</label>
            <InputText v-model="form.numero_bolla" fluid />
          </div>

          <div class="field">
            <label>Data Reso *</label>
            <DatePicker v-model="form.data_reso" date-format="dd/mm/yy" :invalid="!!form.errors.data_reso" show-button-bar fluid />
            <small class="error">{{ form.errors.data_reso }}</small>
          </div>

          <div class="field">
            <label>Q.tà Pz</label>
            <InputNumber v-model="form.quantita_pz" :min-fraction-digits="0" :max-fraction-digits="3" fluid />
          </div>

          <div class="field">
            <label>Q.tà Kg *</label>
            <InputNumber v-model="form.quantita_kg" :min-fraction-digits="3" :max-fraction-digits="3" :invalid="!!form.errors.quantita_kg" fluid />
            <small class="error">{{ form.errors.quantita_kg }}</small>
          </div>

          <div class="field field-full">
            <label>Note</label>
            <Textarea v-model="form.note" rows="2" fluid />
          </div>
        </div>
      </section>
      <div class="form-actions">
        <Button type="submit" :label="isEdit ? 'Salva modifiche' : 'Registra bolla reso'" icon="pi pi-check" :loading="form.processing" />
      </div>
    </form>
  </AppLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import DatePicker from 'primevue/datepicker';
import Textarea from 'primevue/textarea';

const props = defineProps({ bolla: Object, vendite: Array });
const isEdit = computed(() => !!props.bolla);

function formatDate(d) { return d ? new Date(d).toLocaleDateString('it-IT', { day:'2-digit', month:'2-digit', year:'numeric' }) : '—'; }

// Pre-select the vendita from existing bolla
const selectedVenditaId = ref(props.bolla?.vendita_riga?.vendita_id ?? null);

const righeVendita = computed(() => {
  if (!selectedVenditaId.value) return [];
  return props.vendite.find(v => v.id === selectedVenditaId.value)?.righe ?? [];
});

const form = useForm({
  vendita_riga_id: props.bolla?.vendita_riga_id ?? null,
  numero_bolla:    props.bolla?.numero_bolla    ?? '',
  quantita_pz:     props.bolla?.quantita_pz     ? Number(props.bolla.quantita_pz) : null,
  quantita_kg:     props.bolla?.quantita_kg     ? Number(props.bolla.quantita_kg) : null,
  data_reso:       props.bolla?.data_reso       ? new Date(props.bolla.data_reso) : null,
  note:            props.bolla?.note            ?? '',
});

watch(() => props.bolla, (b) => {
  selectedVenditaId.value = b?.vendita_riga?.vendita_id ?? null;
  form.vendita_riga_id = b?.vendita_riga_id ?? null;
  form.numero_bolla    = b?.numero_bolla    ?? '';
  form.quantita_pz     = b?.quantita_pz     ? Number(b.quantita_pz) : null;
  form.quantita_kg     = b?.quantita_kg     ? Number(b.quantita_kg) : null;
  form.data_reso       = b?.data_reso       ? new Date(b.data_reso) : null;
  form.note            = b?.note            ?? '';
  form.clearErrors();
});

function submit() {
  const payload = { ...form.data(), data_reso: form.data_reso ? form.data_reso.toISOString().slice(0,10) : null };
  if (isEdit.value) {
    form.transform(() => payload).put(`/bolle-reso/${props.bolla.id}`);
  } else {
    form.transform(() => payload).post('/bolle-reso');
  }
}
</script>

<style scoped>
.page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:1.5rem; }
.page-title { font-size:1.5rem; font-weight:700; color:#1e293b; margin:0; }
.form-card { background:#fff; border-radius:8px; border:1px solid #e2e8f0; overflow:hidden; }
.form-section { padding:1.5rem; }
.section-title { font-size:0.9rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:#64748b; margin:0 0 1rem 0; }
.form-grid { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
.field { display:flex; flex-direction:column; gap:0.3rem; }
.field label { font-size:0.85rem; font-weight:600; color:#374151; }
.field-full { grid-column:1 / -1; }
.error { color:#dc2626; font-size:0.78rem; min-height:1em; }
.form-actions { padding:1.25rem 1.5rem; background:#f8fafc; display:flex; justify-content:flex-end; border-top:1px solid #e2e8f0; }
</style>
