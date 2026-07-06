<template>
  <AppLayout>
    <div class="page-header">
      <h1 class="page-title">{{ isEdit ? 'Modifica Materia Prima' : 'Nuova Materia Prima' }}</h1>
      <Link href="/materie-prime"><Button label="Annulla" outlined icon="pi pi-arrow-left" aria-label="Indietro" /></Link>
    </div>
    <form @submit.prevent="submit" class="form-card">
      <section class="form-section">
        <div class="form-grid">
          <div class="field">
            <label>Codice</label>
            <InputNumber v-model="form.codice" :use-grouping="false" :invalid="!!form.errors.codice" fluid />
            <small class="error">{{ form.errors.codice }}</small>
          </div>
          <div class="field">
            <label>Nome *</label>
            <InputText v-model="form.nome" :invalid="!!form.errors.nome" fluid />
            <small class="error">{{ form.errors.nome }}</small>
          </div>
        </div>
      </section>
      <section class="form-section allergeni-section">
        <h2 class="section-title">Allergeni (Reg. UE 1169/2011)</h2>
        <div class="form-grid">
          <div class="field">
            <label>Contiene</label>
            <MultiSelect
              v-model="form.allergeni"
              :options="allergeniOptions"
              option-label="label"
              option-value="code"
              display="chip"
              filter
              placeholder="Seleziona allergeni presenti"
              :invalid="!!form.errors.allergeni"
              fluid
            />
            <small class="hint">Allergeni effettivamente presenti nella materia prima.</small>
          </div>
          <div class="field">
            <label>Può contenere (tracce)</label>
            <MultiSelect
              v-model="form.allergeni_tracce"
              :options="allergeniOptions"
              option-label="label"
              option-value="code"
              display="chip"
              filter
              placeholder="Seleziona tracce (cross-contact)"
              :invalid="!!form.errors.allergeni_tracce"
              fluid
            />
            <small class="hint">Contaminazione crociata possibile ("può contenere").</small>
          </div>
        </div>
      </section>
      <div class="form-actions">
        <Button type="submit" :label="isEdit ? 'Salva modifiche' : 'Crea materia prima'" icon="pi pi-check" :loading="form.processing" />
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
import MultiSelect from 'primevue/multiselect';

const props = defineProps({ materia: Object, allergeniOptions: { type: Array, default: () => [] } });
const isEdit = computed(() => !!props.materia);
const form = useForm({
  codice: props.materia?.codice ?? null,
  nome:   props.materia?.nome   ?? '',
  allergeni:        props.materia?.allergeni        ?? [],
  allergeni_tracce: props.materia?.allergeni_tracce ?? [],
});
watch(() => props.materia, (m) => {
  form.codice = m?.codice ?? null;
  form.nome   = m?.nome   ?? '';
  form.allergeni        = m?.allergeni        ?? [];
  form.allergeni_tracce = m?.allergeni_tracce ?? [];
  form.clearErrors();
});

function submit() {
  isEdit.value ? form.put(`/materie-prime/${props.materia.id}`) : form.post('/materie-prime');
}
</script>

<style scoped>
.page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:1.5rem; }
.page-title { font-size:1.5rem; font-weight:700; color:#1e293b; margin:0; }
.form-card { background:#fff; border-radius:8px; border:1px solid #e2e8f0; overflow:hidden; }
.form-section { padding:1.5rem; }
.form-grid { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
.field { display:flex; flex-direction:column; gap:0.3rem; }
.field label { font-size:0.85rem; font-weight:600; color:#374151; }
.error { color:#dc2626; font-size:0.78rem; min-height:1em; }
.allergeni-section { border-top:1px solid #e2e8f0; }
.section-title { font-size:0.95rem; font-weight:700; color:#1c3d28; margin:0 0 1rem 0; }
.hint { color:#64748b; font-size:0.75rem; }
.form-actions { padding:1.25rem 1.5rem; background:#f8fafc; display:flex; justify-content:flex-end; border-top:1px solid #e2e8f0; }
</style>
