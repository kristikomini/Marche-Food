<template>
  <AppLayout>
    <div class="page-header">
      <h1 class="page-title">{{ isEdit ? 'Modifica Prodotto' : 'Nuovo Prodotto' }}</h1>
      <Link href="/prodotti"><Button label="Annulla" outlined icon="pi pi-arrow-left" aria-label="Indietro" /></Link>
    </div>
    <form @submit.prevent="submit" class="form-card">
      <section class="form-section">
        <h2 class="section-title">Dati prodotto</h2>
        <div class="form-grid">
          <div class="field">
            <label>Codice Prodotto *</label>
            <InputText v-model="form.codice_prodotto" :invalid="!!form.errors.codice_prodotto" fluid />
            <small class="error">{{ form.errors.codice_prodotto }}</small>
          </div>
          <div class="field">
            <label>Nome *</label>
            <InputText v-model="form.nome" :invalid="!!form.errors.nome" fluid />
            <small class="error">{{ form.errors.nome }}</small>
          </div>
          <div class="field">
            <label>Pezzatura (valore)</label>
            <InputNumber v-model="form.pezzatura_valore" :min-fraction-digits="0" :max-fraction-digits="3" fluid />
          </div>
          <div class="field">
            <label>Pezzatura (U.M.)</label>
            <Select v-model="form.pezzatura_um" :options="umOptions" option-label="label" option-value="value" placeholder="—" fluid />
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
        <Button type="submit" :label="isEdit ? 'Salva modifiche' : 'Crea prodotto'" icon="pi pi-check" :loading="form.processing" />
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
import Textarea from 'primevue/textarea';
import ToggleSwitch from 'primevue/toggleswitch';

const props = defineProps({ prodotto: Object });
const isEdit = computed(() => !!props.prodotto);
const umOptions = [
  { label: 'g', value: 'g' }, { label: 'Kg', value: 'kg' },
  { label: 'ml', value: 'ml' }, { label: 'Lt', value: 'lt' }, { label: 'Pz', value: 'pz' },
];
const form = useForm({
  codice_prodotto:  props.prodotto?.codice_prodotto  ?? '',
  nome:             props.prodotto?.nome             ?? '',
  pezzatura_valore: props.prodotto?.pezzatura_valore ? Number(props.prodotto.pezzatura_valore) : null,
  pezzatura_um:     props.prodotto?.pezzatura_um     ?? 'g',
  attivo:           props.prodotto?.attivo           ?? true,
  note:             props.prodotto?.note             ?? '',
});
watch(() => props.prodotto, (p) => {
  form.codice_prodotto  = p?.codice_prodotto  ?? '';
  form.nome             = p?.nome             ?? '';
  form.pezzatura_valore = p?.pezzatura_valore ? Number(p.pezzatura_valore) : null;
  form.pezzatura_um     = p?.pezzatura_um     ?? 'g';
  form.attivo           = p?.attivo           ?? true;
  form.note             = p?.note             ?? '';
  form.clearErrors();
});

function submit() {
  isEdit.value ? form.put(`/prodotti/${props.prodotto.id}`) : form.post('/prodotti');
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
.field-inline { flex-direction:row; align-items:center; gap:0.6rem; }
.field-inline label { margin:0; font-weight:500; }
.error { color:#dc2626; font-size:0.78rem; min-height:1em; }
.form-actions { padding:1.25rem 1.5rem; background:#f8fafc; display:flex; justify-content:flex-end; border-top:1px solid #e2e8f0; }
</style>
