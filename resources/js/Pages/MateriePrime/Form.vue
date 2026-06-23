<template>
  <AppLayout>
    <div class="page-header">
      <h1 class="page-title">{{ isEdit ? 'Modifica Materia Prima' : 'Nuova Materia Prima' }}</h1>
      <Link href="/materie-prime"><Button label="Annulla" outlined icon="pi pi-arrow-left" /></Link>
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

const props = defineProps({ materia: Object });
const isEdit = computed(() => !!props.materia);
const form = useForm({
  codice: props.materia?.codice ?? null,
  nome:   props.materia?.nome   ?? '',
});
watch(() => props.materia, (m) => {
  form.codice = m?.codice ?? null;
  form.nome   = m?.nome   ?? '';
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
.form-actions { padding:1.25rem 1.5rem; background:#f8fafc; display:flex; justify-content:flex-end; border-top:1px solid #e2e8f0; }
</style>
