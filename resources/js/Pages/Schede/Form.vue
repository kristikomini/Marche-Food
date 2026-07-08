<template>
  <AppLayout>
    <div class="page-header">
      <h1 class="page-title">{{ isEdit ? 'Modifica Scheda' : 'Nuova Scheda di Produzione' }}</h1>
      <div style="display:flex;gap:0.5rem;align-items:center">
        <Link v-if="isEdit" :href="`/schede/${props.scheda.id}/print`" target="_blank">
          <Button label="Stampa" icon="pi pi-print" aria-label="Stampa" outlined severity="secondary" />
        </Link>
        <a v-if="isEdit" :href="`/schede/${props.scheda.id}/pdf`" target="_blank">
          <Button label="PDF vuoto" icon="pi pi-file-pdf" aria-label="PDF scheda vuota" outlined severity="secondary" />
        </a>
        <Link href="/schede"><Button label="Annulla" outlined icon="pi pi-arrow-left" aria-label="Indietro" /></Link>
      </div>
    </div>

    <form @submit.prevent="submit">
      <!-- TESTATA -->
      <div class="form-card mb-4">
        <section class="form-section">
          <h2 class="section-title">Intestazione scheda</h2>
          <div class="form-grid-4">
            <div class="field" style="grid-column:span 2">
              <label>Prodotto *</label>
              <Select v-model="form.prodotto_id" :options="prodotti" option-label="nome" option-value="id" placeholder="Seleziona prodotto..." :invalid="!!form.errors.prodotto_id" filter fluid />
              <small class="error">{{ form.errors.prodotto_id }}</small>
            </div>
            <div class="field">
              <label>Modello *</label>
              <InputText v-model="form.modello" placeholder="es. MOD01" :invalid="!!form.errors.modello" fluid />
              <small class="error">{{ form.errors.modello }}</small>
            </div>
            <div class="field">
              <label>Revisione</label>
              <InputNumber v-model="form.revisione" :min="0" :max="99" fluid />
            </div>
            <div class="field" style="grid-column:span 2">
              <label>Data Revisione *</label>
              <DatePicker v-model="form.data_revisione" date-format="dd/mm/yy" show-button-bar :invalid="!!form.errors.data_revisione" fluid />
              <small class="error">{{ form.errors.data_revisione }}</small>
            </div>
            <div class="field field-inline">
              <ToggleSwitch v-model="form.ha_marinatura" input-id="marinatura" />
              <label for="marinatura">Ha marinatura</label>
            </div>
            <div class="field field-inline">
              <ToggleSwitch v-model="form.attiva" input-id="attiva" />
              <label for="attiva">Scheda attiva</label>
            </div>
            <div class="field" style="grid-column:span 4">
              <label>Note</label>
              <InputText v-model="form.note" fluid />
            </div>
          </div>
        </section>
      </div>

      <!-- RICETTA -->
      <div class="form-card mb-4">
        <div class="righe-header">
          <h2 class="section-title" style="margin:0">Ricetta</h2>
          <Button type="button" label="Aggiungi ingrediente" icon="pi pi-plus" size="small" outlined @click="addRiga('ricette')" />
        </div>
        <div class="table-wrapper">
          <table class="righe-table">
            <thead>
              <tr>
                <th>Materia Prima *</th>
                <th style="width:110px">%</th>
                <th style="width:110px">g/kg</th>
                <th style="width:80px">U.M.</th>
                <th style="width:44px"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(r, i) in form.ricette" :key="i">
                <td>
                  <Select v-model="r.materia_prima_id" :options="materie" option-label="nome" option-value="id" placeholder="Seleziona..." filter fluid size="small" />
                </td>
                <td><InputNumber v-model="r.percentuale" :min-fraction-digits="3" :max-fraction-digits="3" fluid size="small" /></td>
                <td><InputNumber v-model="r.grammi_per_kg" :min-fraction-digits="3" :max-fraction-digits="3" fluid size="small" /></td>
                <td>
                  <Select v-model="r.um" :options="umOpts" option-label="label" option-value="value" placeholder="—" fluid size="small" />
                </td>
                <td><Button type="button" icon="pi pi-trash" aria-label="Elimina" size="small" text severity="danger" @click="removeRiga('ricette', i)" /></td>
              </tr>
              <tr v-if="!form.ricette.length">
                <td colspan="5" style="text-align:center;color:var(--ink-3);padding:1rem">Nessun ingrediente aggiunto.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- RICETTA MARINATURA -->
      <div v-if="form.ha_marinatura" class="form-card mb-4">
        <div class="righe-header">
          <h2 class="section-title" style="margin:0">Ricetta Marinatura</h2>
          <Button type="button" label="Aggiungi ingrediente" icon="pi pi-plus" size="small" outlined @click="addRiga('ricette_marinature')" />
        </div>
        <div class="table-wrapper">
          <table class="righe-table">
            <thead>
              <tr>
                <th>Materia Prima *</th>
                <th style="width:130px">Lt/g</th>
                <th style="width:80px">U.M.</th>
                <th style="width:44px"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(r, i) in form.ricette_marinature" :key="i">
                <td><Select v-model="r.materia_prima_id" :options="materie" option-label="nome" option-value="id" placeholder="Seleziona..." filter fluid size="small" /></td>
                <td><InputNumber v-model="r.litri_grammi" :min-fraction-digits="3" :max-fraction-digits="3" fluid size="small" /></td>
                <td><Select v-model="r.um" :options="umOpts" option-label="label" option-value="value" placeholder="—" fluid size="small" /></td>
                <td><Button type="button" icon="pi pi-trash" aria-label="Elimina" size="small" text severity="danger" @click="removeRiga('ricette_marinature', i)" /></td>
              </tr>
              <tr v-if="!form.ricette_marinature.length">
                <td colspan="4" style="text-align:center;color:var(--ink-3);padding:1rem">Nessun ingrediente aggiunto.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- FLUSSI DI LAVORAZIONE -->
      <div class="form-card mb-4">
        <div class="righe-header">
          <h2 class="section-title" style="margin:0">Flussi di Lavorazione</h2>
          <Button type="button" label="Aggiungi fase" icon="pi pi-plus" size="small" outlined @click="addFlusso" />
        </div>
        <div class="table-wrapper">
          <table class="righe-table">
            <thead>
              <tr>
                <th>Fase *</th>
                <th style="width:180px">Valore Controllo</th>
                <th style="width:120px">Tempo (min)</th>
                <th style="width:44px"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(f, i) in form.scheda_flussi" :key="i">
                <td>
                  <Select
                    v-model="f.flusso_id"
                    :options="flussi"
                    :option-label="fl => `${fl.numero}. ${fl.nome}`"
                    option-value="id"
                    placeholder="Seleziona fase..."
                    filter
                    fluid
                    size="small"
                  />
                </td>
                <td><InputText v-model="f.valore_controllo" placeholder="es. ≤ 4°C" fluid size="small" /></td>
                <td><InputNumber v-model="f.tempo_minuti" :min="0" fluid size="small" /></td>
                <td><Button type="button" icon="pi pi-trash" aria-label="Elimina" size="small" text severity="danger" @click="form.scheda_flussi.splice(i, 1)" /></td>
              </tr>
              <tr v-if="!form.scheda_flussi.length">
                <td colspan="4" style="text-align:center;color:var(--ink-3);padding:1rem">Nessuna fase aggiunta.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- IMBALLAGGI PRIMARI (template) -->
      <div class="form-card mb-4">
        <div class="righe-header">
          <h2 class="section-title" style="margin:0">Imballaggi primari</h2>
          <Button type="button" label="Aggiungi imballaggio" icon="pi pi-plus" size="small" outlined @click="addImballaggio" />
        </div>
        <div class="table-wrapper">
          <table class="righe-table">
            <thead>
              <tr>
                <th>Componente * (es. Vaschetta gr 200)</th>
                <th style="width:200px">Pezzatura collegata</th>
                <th style="width:220px">Fornitore</th>
                <th style="width:44px"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(im, i) in form.imballaggi" :key="i">
                <td><InputText v-model="im.componente" placeholder="es. Film kg 1" fluid size="small" /></td>
                <td>
                  <Select v-model="im.prodotto_variante_id" :options="variantiSelezionate" option-label="label" option-value="id" placeholder="—" show-clear fluid size="small" />
                </td>
                <td>
                  <Select v-model="im.fornitore_id" :options="fornitori" option-label="ragione_sociale" option-value="id" placeholder="—" show-clear filter fluid size="small" />
                </td>
                <td><Button type="button" icon="pi pi-trash" aria-label="Elimina" size="small" text severity="danger" @click="form.imballaggi.splice(i, 1)" /></td>
              </tr>
              <tr v-if="!form.imballaggi.length">
                <td colspan="4" style="text-align:center;color:var(--ink-3);padding:1rem">Nessun imballaggio aggiunto.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- GAS (template) -->
      <div class="form-card mb-4">
        <div class="righe-header">
          <h2 class="section-title" style="margin:0">Gas</h2>
          <Button type="button" label="Aggiungi gas" icon="pi pi-plus" size="small" outlined @click="addGas" />
        </div>
        <div class="table-wrapper">
          <table class="righe-table">
            <thead>
              <tr>
                <th>Nome * (es. TRESARIS NC30 bombola grande)</th>
                <th style="width:220px">Fornitore</th>
                <th style="width:44px"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(g, i) in form.gas" :key="i">
                <td><InputText v-model="g.nome" placeholder="es. TRESARIS NC30" fluid size="small" /></td>
                <td>
                  <Select v-model="g.fornitore_id" :options="fornitori" option-label="ragione_sociale" option-value="id" placeholder="—" show-clear filter fluid size="small" />
                </td>
                <td><Button type="button" icon="pi pi-trash" aria-label="Elimina" size="small" text severity="danger" @click="form.gas.splice(i, 1)" /></td>
              </tr>
              <tr v-if="!form.gas.length">
                <td colspan="3" style="text-align:center;color:var(--ink-3);padding:1rem">Nessun gas aggiunto.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="form-actions-outer">
        <Button type="submit" :label="isEdit ? 'Salva modifiche' : 'Crea scheda'" icon="pi pi-check" :loading="form.processing" />
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
import ToggleSwitch from 'primevue/toggleswitch';

const props = defineProps({ scheda: Object, prodotti: Array, materie: Array, flussi: Array, fornitori: { type: Array, default: () => [] } });
const isEdit = computed(() => !!props.scheda);

const umOpts = [
  { label: 'g', value: 'g' }, { label: 'Kg', value: 'kg' },
  { label: 'ml', value: 'ml' }, { label: 'Lt', value: 'lt' },
];

// Varianti del prodotto selezionato, per collegare gli imballaggi alla pezzatura.
const variantiSelezionate = computed(() => {
  const p = (props.prodotti || []).find(x => x.id === form.prodotto_id);
  return (p?.varianti || []).map(v => ({
    id: v.id,
    label: `${v.codice_prodotto}${v.pezzatura_label ? ' · ' + v.pezzatura_label : ''}`,
  }));
});

function emptyRiga() { return { materia_prima_id: null, percentuale: null, grammi_per_kg: null, um: 'g' }; }
function emptyMarinatura() { return { materia_prima_id: null, litri_grammi: null, um: 'lt' }; }
function emptyFlusso() { return { flusso_id: null, valore_controllo: '', tempo_minuti: null }; }
function emptyImballaggio() { return { componente: '', prodotto_variante_id: null, fornitore_id: null }; }
function emptyGas() { return { nome: '', fornitore_id: null }; }

const form = useForm({
  prodotto_id:    props.scheda?.prodotto_id    ?? null,
  modello:        props.scheda?.modello        ?? '',
  revisione:      props.scheda?.revisione      ?? 0,
  data_revisione: props.scheda?.data_revisione ? new Date(props.scheda.data_revisione) : null,
  ha_marinatura:  props.scheda?.ha_marinatura  ?? false,
  attiva:         props.scheda?.attiva         ?? true,
  note:           props.scheda?.note           ?? '',
  ricette: props.scheda?.ricette?.length
    ? props.scheda.ricette.map(r => ({ materia_prima_id: r.materia_prima_id, percentuale: r.percentuale ? Number(r.percentuale) : null, grammi_per_kg: r.grammi_per_kg ? Number(r.grammi_per_kg) : null, um: r.um ?? 'g' }))
    : [],
  ricette_marinature: props.scheda?.ricette_marinature?.length
    ? props.scheda.ricette_marinature.map(r => ({ materia_prima_id: r.materia_prima_id, litri_grammi: r.litri_grammi ? Number(r.litri_grammi) : null, um: r.um ?? 'lt' }))
    : [],
  scheda_flussi: props.scheda?.flussi?.length
    ? props.scheda.flussi.map(f => ({ flusso_id: f.flusso_id, valore_controllo: f.valore_controllo ?? '', tempo_minuti: f.tempo_minuti ?? null }))
    : [],
  imballaggi: props.scheda?.imballaggi?.length
    ? props.scheda.imballaggi.map(im => ({ componente: im.componente ?? '', prodotto_variante_id: im.prodotto_variante_id ?? null, fornitore_id: im.fornitore_id ?? null }))
    : [],
  gas: props.scheda?.gas?.length
    ? props.scheda.gas.map(g => ({ nome: g.nome ?? '', fornitore_id: g.fornitore_id ?? null }))
    : [],
});

watch(() => props.scheda, (s) => {
  form.prodotto_id    = s?.prodotto_id    ?? null;
  form.modello        = s?.modello        ?? '';
  form.revisione      = s?.revisione      ?? 0;
  form.data_revisione = s?.data_revisione ? new Date(s.data_revisione) : null;
  form.ha_marinatura  = s?.ha_marinatura  ?? false;
  form.attiva         = s?.attiva         ?? true;
  form.note           = s?.note           ?? '';
  form.ricette = s?.ricette?.length
    ? s.ricette.map(r => ({ materia_prima_id: r.materia_prima_id, percentuale: r.percentuale ? Number(r.percentuale) : null, grammi_per_kg: r.grammi_per_kg ? Number(r.grammi_per_kg) : null, um: r.um ?? 'g' }))
    : [];
  form.ricette_marinature = s?.ricette_marinature?.length
    ? s.ricette_marinature.map(r => ({ materia_prima_id: r.materia_prima_id, litri_grammi: r.litri_grammi ? Number(r.litri_grammi) : null, um: r.um ?? 'lt' }))
    : [];
  form.scheda_flussi = s?.flussi?.length
    ? s.flussi.map(f => ({ flusso_id: f.flusso_id, valore_controllo: f.valore_controllo ?? '', tempo_minuti: f.tempo_minuti ?? null }))
    : [];
  form.imballaggi = s?.imballaggi?.length
    ? s.imballaggi.map(im => ({ componente: im.componente ?? '', prodotto_variante_id: im.prodotto_variante_id ?? null, fornitore_id: im.fornitore_id ?? null }))
    : [];
  form.gas = s?.gas?.length
    ? s.gas.map(g => ({ nome: g.nome ?? '', fornitore_id: g.fornitore_id ?? null }))
    : [];
  form.clearErrors();
});

function addRiga(key) {
  form[key].push(key === 'ricette' ? emptyRiga() : emptyMarinatura());
}
function removeRiga(key, i) { form[key].splice(i, 1); }
function addFlusso() { form.scheda_flussi.push(emptyFlusso()); }
function addImballaggio() { form.imballaggi.push(emptyImballaggio()); }
function addGas() { form.gas.push(emptyGas()); }

function submit() {
  const payload = {
    ...form.data(),
    data_revisione: form.data_revisione ? form.data_revisione.toISOString().slice(0, 10) : null,
  };
  if (isEdit.value) {
    form.transform(() => payload).put(`/schede/${props.scheda.id}`);
  } else {
    form.transform(() => payload).post('/schede');
  }
}
</script>

<style scoped>
.page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:1.5rem; }
.page-title { font-size:1.5rem; font-weight:700; color:var(--ink); margin:0; }
.form-card { background:var(--surface); border-radius:8px; border:1px solid var(--border); overflow:hidden; }
.mb-4 { margin-bottom:1rem; }
.form-section { padding:1.5rem; }
.section-title { font-size:0.9rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:var(--ink-2); margin:0 0 1rem 0; }
.form-grid-4 { display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; }
.field { display:flex; flex-direction:column; gap:0.3rem; }
.field label { font-size:0.85rem; font-weight:600; color:var(--ink-2); }
.field-inline { flex-direction:row; align-items:center; gap:0.6rem; }
.field-inline label { margin:0; font-weight:500; }
.error { color:var(--danger); font-size:0.78rem; }
.righe-header { display:flex; align-items:center; justify-content:space-between; padding:1rem 1.5rem; border-bottom:1px solid var(--border); }
.table-wrapper { overflow-x:auto; }
.righe-table { width:100%; border-collapse:collapse; font-size:0.85rem; }
.righe-table th { padding:0.6rem 0.5rem; text-align:left; font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.04em; color:var(--ink-2); background:var(--surface-2); border-bottom:1px solid var(--border); }
.righe-table td { padding:0.4rem 0.5rem; border-bottom:1px solid var(--border); vertical-align:middle; }
.form-actions-outer { display:flex; justify-content:flex-end; margin-top:1rem; }
</style>
