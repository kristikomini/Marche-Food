<template>
  <AppLayout>
    <div class="page-header">
      <h1 class="page-title">{{ isEdit ? 'Modifica Acquisto' : 'Nuovo Acquisto' }}</h1>
      <div style="display:flex;gap:0.5rem;align-items:center">
        <Link v-if="isEdit" :href="`/acquisti/${props.acquisto.id}/print`" target="_blank">
          <Button label="Stampa" icon="pi pi-print" outlined severity="secondary" />
        </Link>
        <Link href="/acquisti">
          <Button label="Annulla" outlined icon="pi pi-arrow-left" />
        </Link>
      </div>
    </div>

    <form @submit.prevent="submit">

      <!-- TESTATA -->
      <div class="form-card mb-4">
        <section class="form-section">
          <h2 class="section-title">Documento di acquisto</h2>
          <div class="form-grid-4">

            <div class="field" style="grid-column: span 2">
              <label>Fornitore *</label>
              <Select
                v-model="form.fornitore_id"
                :options="fornitori"
                option-label="ragione_sociale"
                option-value="id"
                placeholder="Seleziona fornitore..."
                :invalid="!!form.errors.fornitore_id"
                filter
                fluid
              />
              <small class="error">{{ form.errors.fornitore_id }}</small>
            </div>

            <div class="field">
              <label>N° Documento *</label>
              <InputText v-model="form.numero_documento" :invalid="!!form.errors.numero_documento" fluid />
              <small class="error">{{ form.errors.numero_documento }}</small>
            </div>

            <div class="field">
              <label>Tipo *</label>
              <Select
                v-model="form.tipo_documento"
                :options="tipiDocumento"
                option-label="label"
                option-value="value"
                fluid
              />
            </div>

            <div class="field" style="grid-column: span 2">
              <label>Data Documento *</label>
              <DatePicker
                v-model="form.data_documento"
                date-format="dd/mm/yy"
                :invalid="!!form.errors.data_documento"
                show-button-bar
                fluid
                @date-select="syncDataIn"
              />
              <small class="error">{{ form.errors.data_documento }}</small>
            </div>

            <div class="field" style="grid-column: span 2">
              <label>Note</label>
              <InputText v-model="form.note" fluid />
            </div>

          </div>
        </section>
      </div>

      <!-- RIGHE -->
      <div class="form-card">
        <div class="righe-header">
          <h2 class="section-title" style="margin:0">Righe acquisto</h2>
          <Button
            type="button"
            label="Aggiungi riga"
            icon="pi pi-plus"
            size="small"
            outlined
            @click="addRiga"
          />
        </div>

        <div v-if="form.errors['righe']" class="error" style="padding: 0.5rem 1.5rem">
          {{ form.errors['righe'] }}
        </div>

        <div class="table-wrapper">
          <table class="righe-table">
            <thead>
              <tr>
                <th style="min-width:200px">Prodotto / Descrizione *</th>
                <th style="width:70px">U.M.</th>
                <th style="width:100px">Q.tà Pz</th>
                <th style="width:110px">Q.tà Kg *</th>
                <th style="width:130px">Lotto interno</th>
                <th style="width:130px">Lotto esterno</th>
                <th style="width:130px">Scadenza</th>
                <th style="width:120px">Data entrata *</th>
                <th style="width:120px">Data uscita</th>
                <th style="width:100px">Rif. NC</th>
                <th style="width:44px"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(riga, i) in form.righe" :key="i">
                <td>
                  <InputText
                    v-model="riga.nome_prodotto"
                    :invalid="!!form.errors[`righe.${i}.nome_prodotto`]"
                    fluid
                    size="small"
                  />
                </td>
                <td>
                  <Select
                    v-model="riga.um"
                    :options="umOptions"
                    option-label="label"
                    option-value="value"
                    placeholder="—"
                    fluid
                    size="small"
                  />
                </td>
                <td>
                  <InputNumber
                    v-model="riga.quantita_pz"
                    :min-fraction-digits="0"
                    :max-fraction-digits="3"
                    fluid
                    size="small"
                  />
                </td>
                <td>
                  <InputNumber
                    v-model="riga.quantita_kg"
                    :min-fraction-digits="3"
                    :max-fraction-digits="3"
                    :invalid="!!form.errors[`righe.${i}.quantita_kg`]"
                    fluid
                    size="small"
                  />
                </td>
                <td>
                  <InputText v-model="riga.lotto" fluid size="small" />
                </td>
                <td>
                  <InputText v-model="riga.lotto_esterno" fluid size="small" />
                </td>
                <td>
                  <DatePicker
                    v-model="riga.scadenza"
                    date-format="dd/mm/yy"
                    fluid
                    size="small"
                  />
                </td>
                <td>
                  <DatePicker
                    v-model="riga.data_in"
                    date-format="dd/mm/yy"
                    :invalid="!!form.errors[`righe.${i}.data_in`]"
                    fluid
                    size="small"
                  />
                </td>
                <td>
                  <DatePicker v-model="riga.data_out" date-format="dd/mm/yy" fluid size="small" />
                </td>
                <td>
                  <InputText v-model="riga.nota_credito_ref" fluid size="small" placeholder="NC/..." />
                </td>
                <td>
                  <Button
                    type="button"
                    icon="pi pi-trash"
                    size="small"
                    text
                    severity="danger"
                    :disabled="form.righe.length === 1"
                    @click="removeRiga(i)"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="form-actions">
          <span class="righe-count">{{ form.righe.length }} riga/righe</span>
          <Button
            type="submit"
            :label="isEdit ? 'Salva modifiche' : 'Registra acquisto'"
            icon="pi pi-check"
            :loading="form.processing"
          />
        </div>
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

const props = defineProps({
  acquisto: Object,
  fornitori: Array,
});

const isEdit = computed(() => !!props.acquisto);

const tipiDocumento = [
  { label: 'DDT',     value: 'DDT' },
  { label: 'Fattura', value: 'Fattura' },
  { label: 'Bolla',   value: 'Bolla' },
];

const umOptions = [
  { label: 'Kg',  value: 'kg' },
  { label: 'Pz',  value: 'pz' },
  { label: 'Lt',  value: 'lt' },
  { label: 'Box', value: 'box' },
];

function emptyRiga(dataIn = null) {
  return {
    id: null,
    nome_prodotto: '',
    um: 'kg',
    quantita_pz: null,
    quantita_kg: null,
    lotto: '',
    lotto_esterno: '',
    scadenza: null,
    data_in: dataIn,
    data_out: null,
    nota_credito_ref: '',
  };
}

function parseDate(d) {
  return d ? new Date(d) : null;
}

const form = useForm({
  fornitore_id:    props.acquisto?.fornitore_id    ?? null,
  numero_documento: props.acquisto?.numero_documento ?? '',
  data_documento:  props.acquisto?.data_documento  ? new Date(props.acquisto.data_documento) : null,
  tipo_documento:  props.acquisto?.tipo_documento  ?? 'DDT',
  note:            props.acquisto?.note            ?? '',
  righe: props.acquisto?.righe?.length
    ? props.acquisto.righe.map(r => ({
        id:               r.id           ?? null,
        nome_prodotto:    r.nome_prodotto ?? '',
        um:               r.um            ?? 'kg',
        quantita_pz:      r.quantita_pz   ? Number(r.quantita_pz)  : null,
        quantita_kg:      r.quantita_kg   ? Number(r.quantita_kg)  : null,
        lotto:            r.lotto            ?? '',
        lotto_esterno:    r.lotto_esterno    ?? '',
        scadenza:         parseDate(r.scadenza),
        data_in:          parseDate(r.data_in),
        data_out:         parseDate(r.data_out),
        nota_credito_ref: r.nota_credito_ref ?? '',
      }))
    : [emptyRiga()],
});

watch(() => props.acquisto, (a) => {
  form.fornitore_id     = a?.fornitore_id     ?? null;
  form.numero_documento = a?.numero_documento ?? '';
  form.data_documento   = a?.data_documento   ? new Date(a.data_documento) : null;
  form.tipo_documento   = a?.tipo_documento   ?? 'DDT';
  form.note             = a?.note             ?? '';
  form.righe = a?.righe?.length
    ? a.righe.map(r => ({
        id:               r.id               ?? null,
        nome_prodotto:    r.nome_prodotto    ?? '',
        um:               r.um               ?? 'kg',
        quantita_pz:      r.quantita_pz      ? Number(r.quantita_pz)  : null,
        quantita_kg:      r.quantita_kg      ? Number(r.quantita_kg)  : null,
        lotto:            r.lotto            ?? '',
        lotto_esterno:    r.lotto_esterno    ?? '',
        scadenza:         parseDate(r.scadenza),
        data_in:          parseDate(r.data_in),
        data_out:         parseDate(r.data_out),
        nota_credito_ref: r.nota_credito_ref ?? '',
      }))
    : [emptyRiga()];
  form.clearErrors();
});

function syncDataIn() {
  form.righe.forEach(r => {
    if (!r.data_in) r.data_in = form.data_documento;
  });
}

function addRiga() {
  form.righe.push(emptyRiga(form.data_documento));
}

function removeRiga(i) {
  form.righe.splice(i, 1);
}

function submit() {
  const payload = {
    ...form.data(),
    data_documento: form.data_documento
      ? form.data_documento.toISOString().slice(0, 10)
      : null,
    righe: form.righe.map(r => ({
      ...r,
      scadenza: r.scadenza ? r.scadenza.toISOString().slice(0, 10) : null,
      data_in:  r.data_in  ? r.data_in.toISOString().slice(0, 10)  : null,
      data_out: r.data_out ? r.data_out.toISOString().slice(0, 10) : null,
    })),
  };

  if (isEdit.value) {
    form.transform(() => payload).put(`/acquisti/${props.acquisto.id}`);
  } else {
    form.transform(() => payload).post('/acquisti');
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
.page-title { font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0; }
.form-card {
  background: #fff;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  overflow: hidden;
}
.mb-4 { margin-bottom: 1rem; }
.form-section { padding: 1.5rem; }
.section-title {
  font-size: 0.9rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #64748b;
  margin: 0 0 1rem 0;
}
.form-grid-4 {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;
}
.field { display: flex; flex-direction: column; gap: 0.3rem; }
.field label { font-size: 0.85rem; font-weight: 600; color: #374151; }
.error { color: #dc2626; font-size: 0.78rem; }
.righe-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.5rem;
  border-bottom: 1px solid #f1f5f9;
}
.table-wrapper {
  overflow-x: auto;
}
.righe-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.85rem;
}
.righe-table th {
  padding: 0.6rem 0.5rem;
  text-align: left;
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #64748b;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  white-space: nowrap;
}
.righe-table td {
  padding: 0.4rem 0.5rem;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
}
.righe-table tbody tr:last-child td { border-bottom: none; }
.form-actions {
  padding: 1rem 1.5rem;
  background: #f8fafc;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-top: 1px solid #e2e8f0;
}
.righe-count { font-size: 0.85rem; color: #64748b; }
</style>
