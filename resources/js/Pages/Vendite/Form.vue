<template>
  <AppLayout>
    <div class="page-header">
      <h1 class="page-title">{{ isEdit ? 'Modifica Vendita' : 'Nuova Vendita' }}</h1>
      <Link href="/vendite">
        <Button label="Annulla" outlined icon="pi pi-arrow-left" aria-label="Indietro" />
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
              <label>Condizioni di pagamento</label>
              <InputText v-model="form.condizioni_pagamento" placeholder="es. Bonifico bancario 90+10 GG FM" fluid />
            </div>

            <div class="field" style="grid-column: span 2">
              <label>Causale del trasporto</label>
              <InputText v-model="form.causale_trasporto" placeholder="VENDITA" fluid />
            </div>

            <div class="field" style="grid-column: span 4">
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
                <th style="width:80px">Cod. Art.</th>
                <th style="min-width:200px">Prodotto / Descrizione *</th>
                <th style="width:90px">Pezzatura g</th>
                <th style="width:70px">U.M.</th>
                <th style="width:100px">Q.tà Pz</th>
                <th style="width:110px">Q.tà Kg *</th>
                <th style="width:110px">Prezzo Unit.</th>
                <th style="width:75px">SC.1%</th>
                <th style="width:75px">SC.2%</th>
                <th style="width:75px">IVA %</th>
                <th style="width:110px">Importo</th>
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
                  <InputText v-model="riga.codice_articolo" fluid size="small" />
                </td>
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
                  <InputNumber v-model="riga.prezzo_unitario" :min-fraction-digits="2" :max-fraction-digits="4" :min="0" fluid size="small" />
                </td>
                <td>
                  <InputNumber v-model="riga.sconto_1" :min-fraction-digits="0" :max-fraction-digits="2" :min="0" :max="100" fluid size="small" />
                </td>
                <td>
                  <InputNumber v-model="riga.sconto_2" :min-fraction-digits="0" :max-fraction-digits="2" :min="0" :max="100" fluid size="small" />
                </td>
                <td>
                  <InputNumber v-model="riga.aliquota_iva" :min-fraction-digits="0" :max-fraction-digits="2" :min="0" :max="100" fluid size="small" />
                </td>
                <td class="importo-cell">{{ fmtImporto(rigaImporto(riga)) }}</td>
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
                    icon="pi pi-trash" aria-label="Elimina"
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

        <div class="totali-bar">
          <div class="tot-item"><span class="tot-k">Imponibile</span><span class="tot-v">{{ fmtImporto(totali.imponibile) }} €</span></div>
          <div class="tot-item"><span class="tot-k">Imposta IVA</span><span class="tot-v">{{ fmtImporto(totali.imposta) }} €</span></div>
          <div class="tot-item grand"><span class="tot-k">Totale a pagare</span><span class="tot-v">{{ fmtImporto(totali.totale) }} €</span></div>
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
    codice_articolo:  '',
    nome_prodotto:    '',
    pezzatura_gr:     null,
    um:               'pz',
    quantita_pz:      null,
    quantita_kg:      null,
    prezzo_unitario:  null,
    sconto_1:         null,
    sconto_2:         null,
    aliquota_iva:     null,
    lotto:            '',
    lotto_esterno:    '',
    scadenza:         null,
    acquisto_riga_id: null,
  };
}

// Importo netto riga = q.tà (pezzi se presenti, altrimenti kg) × prezzo, scontato.
function rigaImporto(r) {
  const prezzo = Number(r.prezzo_unitario);
  if (!prezzo || Number.isNaN(prezzo)) return null;
  const qta = (Number(r.quantita_pz) > 0) ? Number(r.quantita_pz) : Number(r.quantita_kg) || 0;
  let v = qta * prezzo;
  v *= (1 - (Number(r.sconto_1) || 0) / 100);
  v *= (1 - (Number(r.sconto_2) || 0) / 100);
  return Math.round(v * 100) / 100;
}

function fmtImporto(v) {
  if (v === null || v === undefined) return '—';
  return Number(v).toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

const totali = computed(() => {
  let imponibile = 0;
  let imposta = 0;
  for (const r of form.righe) {
    const imp = rigaImporto(r) || 0;
    imponibile += imp;
    imposta += imp * ((Number(r.aliquota_iva) || 0) / 100);
  }
  imponibile = Math.round(imponibile * 100) / 100;
  imposta = Math.round(imposta * 100) / 100;
  return { imponibile, imposta, totale: Math.round((imponibile + imposta) * 100) / 100 };
});

function mapRiga(r) {
  return {
    id:               r.id               ?? null,
    codice_articolo:  r.codice_articolo  ?? '',
    nome_prodotto:    r.nome_prodotto    ?? '',
    pezzatura_gr:     r.pezzatura_gr     ? Number(r.pezzatura_gr)  : null,
    um:               r.um               ?? 'pz',
    quantita_pz:      r.quantita_pz      ? Number(r.quantita_pz)  : null,
    quantita_kg:      r.quantita_kg      ? Number(r.quantita_kg)  : null,
    prezzo_unitario:  r.prezzo_unitario  != null && r.prezzo_unitario !== '' ? Number(r.prezzo_unitario) : null,
    sconto_1:         r.sconto_1         != null && r.sconto_1 !== '' ? Number(r.sconto_1) : null,
    sconto_2:         r.sconto_2         != null && r.sconto_2 !== '' ? Number(r.sconto_2) : null,
    aliquota_iva:     r.aliquota_iva     != null && r.aliquota_iva !== '' ? Number(r.aliquota_iva) : null,
    lotto:            r.lotto            ?? '',
    lotto_esterno:    r.lotto_esterno    ?? '',
    scadenza:         parseDate(r.scadenza),
    acquisto_riga_id: r.acquisto_riga_id ?? null,
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
  condizioni_pagamento: props.vendita?.condizioni_pagamento ?? '',
  causale_trasporto:    props.vendita?.causale_trasporto    ?? '',
  note:            props.vendita?.note            ?? '',
  righe: props.vendita?.righe?.length
    ? props.vendita.righe.map(mapRiga)
    : [emptyRiga()],
});

watch(() => props.vendita, (v) => {
  form.cliente_id           = v?.cliente_id       ?? null;
  form.numero_documento     = v?.numero_documento ?? '';
  form.data_documento       = v?.data_documento   ? new Date(v.data_documento) : null;
  form.tipo_documento       = v?.tipo_documento   ?? 'DDT';
  form.condizioni_pagamento = v?.condizioni_pagamento ?? '';
  form.causale_trasporto    = v?.causale_trasporto    ?? '';
  form.note                 = v?.note             ?? '';
  form.righe = v?.righe?.length ? v.righe.map(mapRiga) : [emptyRiga()];
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
.page-title { font-size: 1.5rem; font-weight: 700; color: var(--ink); margin: 0; }
.form-card { background: var(--surface); border-radius: 8px; border: 1px solid var(--border); overflow: hidden; }
.mb-4 { margin-bottom: 1rem; }
.form-section { padding: 1.5rem; }
.section-title { font-size: 0.9rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--ink-2); margin: 0 0 1rem 0; }
.form-grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; }
.field { display: flex; flex-direction: column; gap: 0.3rem; }
.field label { font-size: 0.85rem; font-weight: 600; color: var(--ink-2); }
.error { color: var(--danger); font-size: 0.78rem; }
.righe-header { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.5rem; border-bottom: 1px solid var(--border); }
.table-wrapper { overflow-x: auto; }
.righe-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.righe-table th { padding: 0.6rem 0.5rem; text-align: left; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--ink-2); background: var(--surface-2); border-bottom: 1px solid var(--border); white-space: nowrap; }
.righe-table td { padding: 0.4rem 0.5rem; border-bottom: 1px solid var(--border); vertical-align: middle; }
.righe-table tbody tr:last-child td { border-bottom: none; }
.form-actions { padding: 1rem 1.5rem; background: var(--surface-2); display: flex; align-items: center; justify-content: space-between; border-top: 1px solid var(--border); }
.righe-count { font-size: 0.85rem; color: var(--ink-2); }
.importo-cell { text-align: right; font-family: monospace; font-weight: 600; color: var(--ink); white-space: nowrap; }
.totali-bar { display: flex; justify-content: flex-end; gap: 2rem; padding: 0.9rem 1.5rem; border-top: 1px solid var(--border); background: var(--surface); }
.tot-item { display: flex; flex-direction: column; align-items: flex-end; }
.tot-k { font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--ink-3); }
.tot-v { font-size: 1rem; font-weight: 700; color: var(--ink); font-family: monospace; }
.tot-item.grand .tot-v { color: var(--primary, #1f5040); font-size: 1.2rem; }
</style>
