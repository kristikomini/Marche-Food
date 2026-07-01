<template>
  <AppLayout>
    <div class="page-header">
      <h1 class="page-title">{{ isEdit ? 'Modifica Vendita' : 'Nuova Vendita' }}</h1>
      <Link href="/vendite">
        <Button label="Annulla" outlined icon="pi pi-arrow-left" />
      </Link>
    </div>

    <form @submit.prevent="submit">

      <!-- TESTATA -->
      <div class="form-card mb-4">
        <section class="form-section">
          <h2 class="section-title">Documento di vendita</h2>
          <div class="form-grid-4">

            <div class="field" style="grid-column: span 2">
              <label>Cliente *</label>
              <Select
                v-model="form.cliente_id"
                :options="clienti"
                option-label="ragione_sociale"
                option-value="id"
                placeholder="Seleziona cliente..."
                :invalid="!!form.errors.cliente_id"
                filter
                fluid
              />
              <small class="error">{{ form.errors.cliente_id }}</small>
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
          <h2 class="section-title" style="margin:0">Righe vendita</h2>
          <Button type="button" label="Aggiungi riga" icon="pi pi-plus" size="small" outlined @click="addRiga" />
        </div>

        <div v-if="form.errors['righe']" class="error" style="padding: 0.5rem 1.5rem">
          {{ form.errors['righe'] }}
        </div>

        <div class="table-wrapper">
          <table class="righe-table">
            <thead>
              <tr>
                <th style="min-width:200px">Prodotto / Descrizione *</th>
                <th style="width:90px">Pezzatura g</th>
                <th style="width:70px">U.M.</th>
                <th style="width:100px">Q.tà Pz</th>
                <th style="width:110px">Q.tà Kg *</th>
                <th style="width:130px">Lotto interno</th>
                <th style="width:130px">Lotto esterno</th>
                <th style="width:130px">Scadenza</th>
                <th style="min-width:220px">Lotto acquisto (rivendita diretta)</th>
                <th style="width:44px"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(riga, i) in form.righe" :key="i">
                <td>
                  <InputText
                    v-model="riga.nome_prodotto"
                    :invalid="!!form.errors[`righe.${i}.nome_prodotto`]"
                    fluid size="small"
                  />
                </td>
                <td>
                  <InputNumber
                    v-model="riga.pezzatura_gr"
                    :min-fraction-digits="0"
                    :max-fraction-digits="3"
                    fluid size="small"
                  />
                </td>
                <td>
                  <Select
                    v-model="riga.um"
                    :options="umOptions"
                    option-label="label"
                    option-value="value"
                    placeholder="—"
                    fluid size="small"
                  />
                </td>
                <td>
                  <InputNumber
                    v-model="riga.quantita_pz"
                    :min-fraction-digits="0"
                    :max-fraction-digits="3"
                    fluid size="small"
                  />
                </td>
                <td>
                  <InputNumber
                    v-model="riga.quantita_kg"
                    :min-fraction-digits="3"
                    :max-fraction-digits="3"
                    :invalid="!!form.errors[`righe.${i}.quantita_kg`]"
                    fluid size="small"
                  />
                </td>
                <td>
                  <InputText v-model="riga.lotto" fluid size="small" />
                </td>
                <td>
                  <InputText v-model="riga.lotto_esterno" fluid size="small" />
                </td>
                <td>
                  <DatePicker v-model="riga.scadenza" date-format="dd/mm/yy" fluid size="small" />
                </td>
                <td>
                  <Select
                    v-model="riga.acquisto_riga_id"
                    :options="acquisti_righe ?? []"
                    :option-label="acquistoRigaLabel"
                    option-value="id"
                    placeholder="— nessuno —"
                    :show-clear="true"
                    filter
                    fluid
                    size="small"
                  />
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
            :label="isEdit ? 'Salva modifiche' : 'Registra vendita'"
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
  vendita:         Object,
  clienti:         Array,
  acquisti_righe:  Array,
});

const isEdit = computed(() => !!props.vendita);

const tipiDocumento = [
  { label: 'DDT',              value: 'DDT' },
  { label: 'Fattura Immediata', value: 'FI' },
  { label: 'Nota di Credito',  value: 'NC' },
];

const umOptions = [
  { label: 'Kg',  value: 'kg' },
  { label: 'Pz',  value: 'pz' },
  { label: 'Lt',  value: 'lt' },
  { label: 'Box', value: 'box' },
];

function acquistoRigaLabel(r) {
  const lotto    = r.lotto || r.lotto_esterno || '—';
  const fornitore = r.acquisto?.fornitore?.ragione_sociale ?? '?';
  const ddt      = r.acquisto?.numero_documento ?? '?';
  return `${ddt} | ${fornitore} | ${r.nome_prodotto} | Lotto: ${lotto}`;
}

function emptyRiga() {
  return {
    id:               null,
    nome_prodotto:    '',
    pezzatura_gr:     null,
    um:               'pz',
    quantita_pz:      null,
    quantita_kg:      null,
    lotto:            '',
    lotto_esterno:    '',
    scadenza:         null,
    acquisto_riga_id: null,
  };
}

function parseDate(d) {
  return d ? new Date(d) : null;
}

const form = useForm({
  updated_at:      props.vendita?.updated_at ?? null,
  cliente_id:      props.vendita?.cliente_id      ?? null,
  numero_documento: props.vendita?.numero_documento ?? '',
  data_documento:  props.vendita?.data_documento  ? new Date(props.vendita.data_documento) : null,
  tipo_documento:  props.vendita?.tipo_documento  ?? 'DDT',
  note:            props.vendita?.note            ?? '',
  righe: props.vendita?.righe?.length
    ? props.vendita.righe.map(r => ({
        id:               r.id               ?? null,
        nome_prodotto:    r.nome_prodotto    ?? '',
        pezzatura_gr:     r.pezzatura_gr     ? Number(r.pezzatura_gr)  : null,
        um:               r.um               ?? 'pz',
        quantita_pz:      r.quantita_pz      ? Number(r.quantita_pz)  : null,
        quantita_kg:      r.quantita_kg      ? Number(r.quantita_kg)  : null,
        lotto:            r.lotto            ?? '',
        lotto_esterno:    r.lotto_esterno    ?? '',
        scadenza:         parseDate(r.scadenza),
        acquisto_riga_id: r.acquisto_riga_id ?? null,
      }))
    : [emptyRiga()],
});

watch(() => props.vendita, (v) => {
  form.cliente_id       = v?.cliente_id       ?? null;
  form.numero_documento = v?.numero_documento ?? '';
  form.data_documento   = v?.data_documento   ? new Date(v.data_documento) : null;
  form.tipo_documento   = v?.tipo_documento   ?? 'DDT';
  form.note             = v?.note             ?? '';
  form.righe = v?.righe?.length
    ? v.righe.map(r => ({
        id:               r.id               ?? null,
        nome_prodotto:    r.nome_prodotto    ?? '',
        pezzatura_gr:     r.pezzatura_gr     ? Number(r.pezzatura_gr)  : null,
        um:               r.um               ?? 'pz',
        quantita_pz:      r.quantita_pz      ? Number(r.quantita_pz)  : null,
        quantita_kg:      r.quantita_kg      ? Number(r.quantita_kg)  : null,
        lotto:            r.lotto            ?? '',
        lotto_esterno:    r.lotto_esterno    ?? '',
        scadenza:         parseDate(r.scadenza),
        acquisto_riga_id: r.acquisto_riga_id ?? null,
      }))
    : [emptyRiga()];
  form.clearErrors();
});

function addRiga() {
  form.righe.push(emptyRiga());
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
    })),
  };

  if (isEdit.value) {
    form.transform(() => payload).put(`/vendite/${props.vendita.id}`);
  } else {
    form.transform(() => payload).post('/vendite');
  }
}
</script>

<style scoped>
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; }
.page-title { font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0; }
.form-card { background: #fff; border-radius: 8px; border: 1px solid #e2e8f0; overflow: hidden; }
.mb-4 { margin-bottom: 1rem; }
.form-section { padding: 1.5rem; }
.section-title { font-size: 0.9rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin: 0 0 1rem 0; }
.form-grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; }
.field { display: flex; flex-direction: column; gap: 0.3rem; }
.field label { font-size: 0.85rem; font-weight: 600; color: #374151; }
.error { color: #dc2626; font-size: 0.78rem; }
.righe-header { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.5rem; border-bottom: 1px solid #f1f5f9; }
.table-wrapper { overflow-x: auto; }
.righe-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.righe-table th { padding: 0.6rem 0.5rem; text-align: left; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #64748b; background: #f8fafc; border-bottom: 1px solid #e2e8f0; white-space: nowrap; }
.righe-table td { padding: 0.4rem 0.5rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
.righe-table tbody tr:last-child td { border-bottom: none; }
.form-actions { padding: 1rem 1.5rem; background: #f8fafc; display: flex; align-items: center; justify-content: space-between; border-top: 1px solid #e2e8f0; }
.righe-count { font-size: 0.85rem; color: #64748b; }
</style>
