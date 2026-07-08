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
          <div class="field field-full">
            <label>Nome *</label>
            <InputText v-model="form.nome" :invalid="!!form.errors.nome" fluid />
            <small class="error">{{ form.errors.nome }}</small>
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

      <section class="form-section variants">
        <div class="variants-head">
          <h2 class="section-title" style="margin:0">Varianti / Pezzature</h2>
          <Button type="button" label="Aggiungi variante" icon="pi pi-plus" size="small" outlined @click="addVariante" />
        </div>
        <p class="hint">Ogni variante ha il proprio codice prodotto e pezzatura (es. 059 · gr 200 e 397 · kg 1), come nella scheda di produzione.</p>
        <small v-if="form.errors.varianti" class="error">{{ form.errors.varianti }}</small>

        <div class="table-wrapper">
          <table class="variants-table">
            <thead>
              <tr>
                <th style="width:130px">Codice *</th>
                <th style="width:120px">Pezzatura</th>
                <th style="width:110px">U.M. pezz.</th>
                <th>Descrizione</th>
                <th style="width:80px">Attiva</th>
                <th style="width:44px"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(v, i) in form.varianti" :key="i">
                <td>
                  <InputText v-model="v.codice_prodotto" :invalid="!!form.errors[`varianti.${i}.codice_prodotto`]" fluid size="small" />
                  <small class="error">{{ form.errors[`varianti.${i}.codice_prodotto`] }}</small>
                </td>
                <td>
                  <InputNumber v-model="v.pezzatura_valore" :min-fraction-digits="0" :max-fraction-digits="3" :min="0" fluid size="small" />
                </td>
                <td>
                  <Select v-model="v.pezzatura_um" :options="umLabels" editable placeholder="—" fluid size="small" />
                </td>
                <td>
                  <InputText v-model="v.descrizione" fluid size="small" />
                </td>
                <td style="text-align:center">
                  <ToggleSwitch v-model="v.attiva" />
                </td>
                <td>
                  <Button type="button" icon="pi pi-trash" aria-label="Elimina variante" size="small" text severity="danger"
                          :disabled="form.varianti.length === 1" @click="removeVariante(i)" />
                </td>
              </tr>
            </tbody>
          </table>
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

const props = defineProps({ prodotto: Object, umOptions: { type: Array, default: () => [] } });
const isEdit = computed(() => !!props.prodotto);

const umLabels = ['gr', 'kg', 'ml', 'lt', 'pz', 'g'];

function emptyVariante() {
  return { codice_prodotto: '', pezzatura_valore: null, pezzatura_um: 'gr', um_id: null, descrizione: '', attiva: true };
}

function mapVariante(v) {
  return {
    codice_prodotto:  v.codice_prodotto ?? '',
    pezzatura_valore: v.pezzatura_valore != null && v.pezzatura_valore !== '' ? Number(v.pezzatura_valore) : null,
    pezzatura_um:     v.pezzatura_um ?? 'gr',
    um_id:            v.um_id ?? null,
    descrizione:      v.descrizione ?? '',
    attiva:           v.attiva ?? true,
  };
}

function initialVarianti(p) {
  return p?.varianti?.length ? p.varianti.map(mapVariante) : [emptyVariante()];
}

const form = useForm({
  nome:     props.prodotto?.nome   ?? '',
  attivo:   props.prodotto?.attivo ?? true,
  note:     props.prodotto?.note   ?? '',
  varianti: initialVarianti(props.prodotto),
});

watch(() => props.prodotto, (p) => {
  form.nome     = p?.nome   ?? '';
  form.attivo   = p?.attivo ?? true;
  form.note     = p?.note   ?? '';
  form.varianti = initialVarianti(p);
  form.clearErrors();
});

function addVariante() { form.varianti.push(emptyVariante()); }
function removeVariante(i) { form.varianti.splice(i, 1); }

function submit() {
  isEdit.value ? form.put(`/prodotti/${props.prodotto.id}`) : form.post('/prodotti');
}
</script>

<style scoped>
.page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:1.5rem; }
.page-title { font-size:1.5rem; font-weight:700; color:var(--ink); margin:0; }
.form-card { background:var(--surface); border-radius:8px; border:1px solid var(--border); overflow:hidden; }
.form-section { padding:1.5rem; }
.form-section.variants { border-top:1px solid var(--border); }
.section-title { font-size:0.9rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:var(--ink-2); margin:0 0 1rem 0; }
.form-grid { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
.field { display:flex; flex-direction:column; gap:0.3rem; }
.field label { font-size:0.85rem; font-weight:600; color:var(--ink-2); }
.field-full { grid-column:1 / -1; }
.field-inline { flex-direction:row; align-items:center; gap:0.6rem; }
.field-inline label { margin:0; font-weight:500; }
.error { color:var(--danger); font-size:0.78rem; min-height:1em; }
.variants-head { display:flex; align-items:center; justify-content:space-between; }
.hint { font-size:0.8rem; color:var(--ink-3); margin:0.25rem 0 0.75rem; }
.table-wrapper { overflow-x:auto; }
.variants-table { width:100%; border-collapse:collapse; font-size:0.85rem; }
.variants-table th { padding:0.5rem; text-align:left; font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.04em; color:var(--ink-2); background:var(--surface-2); border-bottom:1px solid var(--border); white-space:nowrap; }
.variants-table td { padding:0.4rem 0.5rem; border-bottom:1px solid var(--border); vertical-align:top; }
.variants-table tbody tr:last-child td { border-bottom:none; }
.form-actions { padding:1.25rem 1.5rem; background:var(--surface-2); display:flex; justify-content:flex-end; border-top:1px solid var(--border); }
</style>
