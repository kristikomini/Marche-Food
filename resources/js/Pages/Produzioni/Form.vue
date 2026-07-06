<template>
  <AppLayout>
    <div class="page-header">
      <h1 class="page-title">{{ isEdit ? 'Modifica Produzione' : 'Nuova Produzione' }}</h1>
      <div style="display:flex;gap:0.5rem;align-items:center">
        <Link v-if="isEdit" :href="`/produzioni/${props.produzione.id}/print`" target="_blank">
          <Button label="Stampa" icon="pi pi-print" aria-label="Stampa" outlined severity="secondary" />
        </Link>
        <Link href="/produzioni"><Button label="Annulla" outlined icon="pi pi-arrow-left" aria-label="Indietro" /></Link>
      </div>
    </div>

    <form @submit.prevent="submit">
      <!-- TESTATA -->
      <div class="form-card mb-4">
        <section class="form-section">
          <h2 class="section-title">Dati produzione</h2>
          <div class="form-grid-4">
            <div class="field" style="grid-column:span 2">
              <label>Scheda di Produzione *</label>
              <Select
                v-model="form.scheda_id"
                :options="schede"
                :option-label="s => `${s.modello}.${String(s.revisione).padStart(2,'0')} — ${s.prodotto?.nome}`"
                option-value="id"
                placeholder="Seleziona scheda..."
                :invalid="!!form.errors.scheda_id"
                filter
                fluid
              />
              <small class="error">{{ form.errors.scheda_id }}</small>
            </div>
            <div class="field" style="grid-column:span 2">
              <label>Lotto Produzione *</label>
              <InputText v-model="form.lotto_produzione" :invalid="!!form.errors.lotto_produzione" placeholder="es. LP2024-001" fluid />
              <small class="error">{{ form.errors.lotto_produzione }}</small>
            </div>
            <div class="field">
              <label>Data Produzione *</label>
              <DatePicker v-model="form.data_produzione" date-format="dd/mm/yy" :invalid="!!form.errors.data_produzione" show-button-bar fluid />
              <small class="error">{{ form.errors.data_produzione }}</small>
            </div>
            <div class="field">
              <label>Q.tà Prodotta (Kg)</label>
              <InputNumber v-model="form.quantita_prodotta_kg" :min-fraction-digits="3" :max-fraction-digits="3" fluid />
            </div>
            <div class="field">
              <label>Operatore</label>
              <InputText v-model="form.operatore" fluid />
            </div>
            <div class="field">
              <label>Note</label>
              <InputText v-model="form.note" fluid />
            </div>
          </div>
        </section>
      </div>

      <!-- MATERIE PRIME UTILIZZATE -->
      <div class="form-card mb-4">
        <div class="righe-header">
          <div>
            <h2 class="section-title" style="margin:0">Materie prime utilizzate</h2>
            <p class="section-sub">Collegare ogni ingrediente al lotto di acquisto (o semilavorato interno) per la tracciabilità HACCP</p>
          </div>
          <Button type="button" label="Aggiungi riga" icon="pi pi-plus" size="small" outlined @click="addMateriaPrima" />
        </div>
        <div v-if="form.errors['materie_prime']" class="error" style="padding:0.5rem 1.5rem">{{ form.errors['materie_prime'] }}</div>

        <div class="table-wrapper">
          <table class="righe-table">
            <thead>
              <tr>
                <th style="min-width:180px">Materia Prima *</th>
                <th style="min-width:300px">Lotto *</th>
                <th style="width:120px">Q.tà Kg *</th>
                <th style="width:110px">Disponibile</th>
                <th style="width:44px"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(r, i) in form.materie_prime" :key="i">
                <td>
                  <Select v-model="r.materia_prima_id" :options="materie" option-label="nome" option-value="id" placeholder="Seleziona..." filter fluid size="small" />
                </td>
                <td>
                  <Select
                    v-model="r.lot_id"
                    :options="lotti_disponibili"
                    :option-label="lotLabel"
                    option-value="id"
                    placeholder="Seleziona lotto..."
                    filter fluid size="small"
                    :invalid="!!form.errors[`materie_prime.${i}`]"
                    @change="(e) => onLotChange(r, e.value)"
                  />
                  <small v-if="form.errors[`materie_prime.${i}`]" class="error">{{ form.errors[`materie_prime.${i}`] }}</small>
                </td>
                <td>
                  <InputNumber v-model="r.quantita_kg" :min-fraction-digits="3" :max-fraction-digits="3" :invalid="!!form.errors[`materie_prime.${i}.quantita_kg`]" fluid size="small" />
                </td>
                <td>
                  <span v-if="balanceFor(r.lot_id) !== null" :class="balanceFor(r.lot_id) < 0 ? 'balance-negative' : 'balance-ok'">
                    {{ Number(balanceFor(r.lot_id)).toFixed(3) }} kg
                  </span>
                  <span v-else class="balance-empty">—</span>
                </td>
                <td>
                  <Button type="button" icon="pi pi-trash" aria-label="Elimina" size="small" text severity="danger" @click="removeMateriaPrima(i)" />
                </td>
              </tr>
              <tr v-if="!form.materie_prime.length">
                <td colspan="5" style="text-align:center;color:var(--ink-3);padding:1rem">Nessuna materia prima aggiunta.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="form-actions-sub">
          <span class="righe-count">{{ form.materie_prime.length }} riga/righe</span>
        </div>
      </div>

      <!-- IMBALLAGGI PRIMARI -->
      <div class="form-card mb-4">
        <div class="righe-header">
          <div>
            <h2 class="section-title" style="margin:0">Imballaggi primari utilizzati</h2>
            <p class="section-sub">Collegare i lotti di imballaggio usati in questa produzione (MOCA)</p>
          </div>
          <Button type="button" label="Aggiungi" icon="pi pi-plus" size="small" outlined @click="addImballaggio" />
        </div>

        <div class="table-wrapper">
          <table class="righe-table">
            <thead>
              <tr>
                <th style="min-width:280px">Lotto Imballaggio *</th>
                <th style="width:130px">Q.tà Usata</th>
                <th style="min-width:160px">Note</th>
                <th style="width:44px"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(r, i) in form.imballaggi" :key="i">
                <td>
                  <Select
                    v-model="r.lotto_imballaggio_id"
                    :options="lotti_imballaggi"
                    :option-label="imballaggioLabel"
                    option-value="id"
                    placeholder="Seleziona lotto..."
                    filter fluid size="small"
                    :invalid="!!form.errors[`imballaggi.${i}.lotto_imballaggio_id`]"
                  />
                </td>
                <td>
                  <InputNumber v-model="r.quantita_usata" :min-fraction-digits="3" :max-fraction-digits="3" fluid size="small" />
                </td>
                <td>
                  <InputText v-model="r.note" fluid size="small" />
                </td>
                <td>
                  <Button type="button" icon="pi pi-trash" aria-label="Elimina" size="small" text severity="danger" @click="removeImballaggio(i)" />
                </td>
              </tr>
              <tr v-if="!form.imballaggi.length">
                <td colspan="4" style="text-align:center;color:var(--ink-3);padding:1rem">Nessun imballaggio collegato.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="form-actions-sub">
          <span class="righe-count">{{ form.imballaggi.length }} riga/righe</span>
        </div>
      </div>

      <!-- DETERGENTI -->
      <div class="form-card mb-4">
        <div class="righe-header">
          <div>
            <h2 class="section-title" style="margin:0">Detergenti e sanificanti utilizzati</h2>
            <p class="section-sub">Collegare i lotti di detergente usati in questa sessione di pulizia</p>
          </div>
          <Button type="button" label="Aggiungi" icon="pi pi-plus" size="small" outlined @click="addDetergente" />
        </div>

        <div class="table-wrapper">
          <table class="righe-table">
            <thead>
              <tr>
                <th style="min-width:280px">Lotto Detergente *</th>
                <th style="width:130px">Q.tà Usata</th>
                <th style="min-width:160px">Note</th>
                <th style="width:44px"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(r, i) in form.detergenti" :key="i">
                <td>
                  <Select
                    v-model="r.lotto_detergente_id"
                    :options="lotti_detergenti"
                    :option-label="detergenteLabel"
                    option-value="id"
                    placeholder="Seleziona lotto..."
                    filter fluid size="small"
                    :invalid="!!form.errors[`detergenti.${i}.lotto_detergente_id`]"
                  />
                </td>
                <td>
                  <InputNumber v-model="r.quantita_usata" :min-fraction-digits="3" :max-fraction-digits="3" fluid size="small" />
                </td>
                <td>
                  <InputText v-model="r.note" fluid size="small" />
                </td>
                <td>
                  <Button type="button" icon="pi pi-trash" aria-label="Elimina" size="small" text severity="danger" @click="removeDetergente(i)" />
                </td>
              </tr>
              <tr v-if="!form.detergenti.length">
                <td colspan="4" style="text-align:center;color:var(--ink-3);padding:1rem">Nessun detergente collegato.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="form-actions">
          <span class="righe-count">{{ form.detergenti.length }} riga/righe</span>
          <Button type="submit" :label="isEdit ? 'Salva modifiche' : 'Registra produzione'" icon="pi pi-check" :loading="form.processing" />
        </div>
      </div>
    </form>

    <!-- LOTTO SEMILAVORATO (edit only, outside the main form) -->
    <div v-if="isEdit" class="form-card mb-4">
      <div class="righe-header">
        <div>
          <h2 class="section-title" style="margin:0">Lotto Semilavorato</h2>
          <p class="section-sub">Rendi questa produzione disponibile come ingrediente interno per produzioni future</p>
        </div>
      </div>
      <div class="form-section">

        <!-- Already registered -->
        <template v-if="props.lotto_semilavorato">
          <div class="semi-info">
            <div class="semi-info-row">
              <span class="semi-label">Lotto</span>
              <span class="semi-value">{{ props.lotto_semilavorato.lotto }}</span>
            </div>
            <div class="semi-info-row">
              <span class="semi-label">Prodotto</span>
              <span class="semi-value">{{ props.lotto_semilavorato.nome_prodotto }}</span>
            </div>
            <div class="semi-info-row">
              <span class="semi-label">Quantità</span>
              <span class="semi-value">{{ Number(props.lotto_semilavorato.quantita_kg).toFixed(3) }} kg</span>
            </div>
            <div v-if="props.lotto_semilavorato.note" class="semi-info-row">
              <span class="semi-label">Note</span>
              <span class="semi-value">{{ props.lotto_semilavorato.note }}</span>
            </div>
          </div>
        </template>

        <!-- Not yet registered -->
        <template v-else>
          <Button
            v-if="!showSemiForm"
            type="button"
            label="Rendi disponibile come semilavorato"
            icon="pi pi-box"
            outlined
            @click="openSemiForm"
          />
          <div v-if="showSemiForm" class="form-grid-4">
            <div class="field" style="grid-column:span 2">
              <label>Lotto *</label>
              <InputText v-model="semiForm.lotto" :invalid="!!semiForm.errors.lotto" fluid />
              <small class="error">{{ semiForm.errors.lotto }}</small>
            </div>
            <div class="field" style="grid-column:span 2">
              <label>Nome Prodotto *</label>
              <InputText v-model="semiForm.nome_prodotto" :invalid="!!semiForm.errors.nome_prodotto" fluid />
              <small class="error">{{ semiForm.errors.nome_prodotto }}</small>
            </div>
            <div class="field">
              <label>Quantità Kg *</label>
              <InputNumber v-model="semiForm.quantita_kg" :min-fraction-digits="3" :max-fraction-digits="3" :invalid="!!semiForm.errors.quantita_kg" fluid />
              <small class="error">{{ semiForm.errors.quantita_kg }}</small>
            </div>
            <div class="field" style="grid-column:span 3">
              <label>Note</label>
              <InputText v-model="semiForm.note" fluid />
            </div>
            <div class="field" style="grid-column:span 4;display:flex;gap:0.5rem;padding-top:0.5rem">
              <Button type="button" label="Registra" icon="pi pi-check" :loading="semiForm.processing" @click="submitSemi" />
              <Button type="button" label="Annulla" text severity="secondary" @click="showSemiForm = false" />
            </div>
          </div>
        </template>

      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import DatePicker from 'primevue/datepicker';

const props = defineProps({
  produzione:       Object,
  schede:           Array,
  materie:          Array,
  lotti_disponibili: Array,
  lotti_imballaggi: Array,
  lotti_detergenti: Array,
  lotto_semilavorato: Object,
});

const isEdit = computed(() => !!props.produzione);

// ── Balance lookup ────────────────────────────────────────────────────────────

const balanceMap = computed(() => {
  const map = {};
  (props.lotti_disponibili ?? []).forEach(l => { map[l.id] = l.balance_kg; });
  return map;
});

function balanceFor(lot_id) {
  return lot_id != null ? (balanceMap.value[lot_id] ?? null) : null;
}

// ── Lot dropdown ──────────────────────────────────────────────────────────────

function lotLabel(l) {
  const bal = l.balance_kg != null ? ` | Disp: ${Number(l.balance_kg).toFixed(3)} kg` : '';
  if (l.source_type === 'interno') {
    return `[INTERNO] ${l.nome_prodotto} | Lotto: ${l.lotto}${bal}`;
  }
  const lotto    = l.lotto || l.lotto_esterno || '—';
  const fornitore = l.acquisto?.fornitore?.ragione_sociale ?? '?';
  const ddt      = l.acquisto?.numero_documento ?? '?';
  return `${ddt} | ${fornitore} | ${l.nome_prodotto} | Lotto: ${lotto}${bal}`;
}

// When the user picks a lot, record the source_type on the row
function onLotChange(row, lotId) {
  const item = (props.lotti_disponibili ?? []).find(l => l.id === lotId);
  if (item) row.source_type = item.source_type;
}

function imballaggioLabel(r) {
  const lotto    = r.lotto || '—';
  const fornitore = r.fornitore?.ragione_sociale ?? '?';
  return `${r.componente} | ${fornitore} | Lotto: ${lotto}`;
}

function detergenteLabel(r) {
  const lotto    = r.lotto || '—';
  const fornitore = r.fornitore?.ragione_sociale ?? '?';
  return `${r.componente} | ${fornitore} | Lotto: ${lotto}`;
}

// ── Form state ────────────────────────────────────────────────────────────────

function emptyMateriaPrima() {
  return { materia_prima_id: null, lot_id: null, source_type: null, quantita_kg: null };
}
function emptyImballaggio() {
  return { lotto_imballaggio_id: null, quantita_usata: null, note: '' };
}
function emptyDetergente() {
  return { lotto_detergente_id: null, quantita_usata: null, note: '' };
}

const form = useForm({
  scheda_id:            props.produzione?.scheda_id            ?? null,
  updated_at:           props.produzione?.updated_at            ?? null,
  lotto_produzione:     props.produzione?.lotto_produzione     ?? '',
  data_produzione:      props.produzione?.data_produzione      ? new Date(props.produzione.data_produzione) : null,
  quantita_prodotta_kg: props.produzione?.quantita_prodotta_kg ? Number(props.produzione.quantita_prodotta_kg) : null,
  operatore:            props.produzione?.operatore            ?? '',
  note:                 props.produzione?.note                 ?? '',
  materie_prime: props.produzione?.materie_prime?.length
    ? props.produzione.materie_prime.map(m => ({
        materia_prima_id: m.materia_prima_id,
        lot_id:      m.acquisto_riga_id ?? m.semilavorato_id,
        source_type: m.acquisto_riga_id ? 'acquisto' : 'interno',
        quantita_kg: Number(m.quantita_kg),
      }))
    : [],
  imballaggi: props.produzione?.imballaggi_primari?.length
    ? props.produzione.imballaggi_primari.map(x => ({
        lotto_imballaggio_id: x.lotto_imballaggio_id,
        quantita_usata:       x.quantita_usata ? Number(x.quantita_usata) : null,
        note:                 x.note ?? '',
      }))
    : [],
  detergenti: props.produzione?.detergenti?.length
    ? props.produzione.detergenti.map(x => ({
        lotto_detergente_id: x.lotto_detergente_id,
        quantita_usata:      x.quantita_usata ? Number(x.quantita_usata) : null,
        note:                x.note ?? '',
      }))
    : [],
});

watch(() => props.produzione, (p) => {
  form.scheda_id            = p?.scheda_id            ?? null;
  form.lotto_produzione     = p?.lotto_produzione     ?? '';
  form.data_produzione      = p?.data_produzione      ? new Date(p.data_produzione) : null;
  form.quantita_prodotta_kg = p?.quantita_prodotta_kg ? Number(p.quantita_prodotta_kg) : null;
  form.operatore            = p?.operatore            ?? '';
  form.note                 = p?.note                 ?? '';
  form.materie_prime = p?.materie_prime?.length
    ? p.materie_prime.map(m => ({
        materia_prima_id: m.materia_prima_id,
        lot_id:           m.acquisto_riga_id ?? m.semilavorato_id,
        source_type:      m.acquisto_riga_id ? 'acquisto' : 'interno',
        quantita_kg:      Number(m.quantita_kg),
      }))
    : [];
  form.imballaggi = p?.imballaggi_primari?.length
    ? p.imballaggi_primari.map(x => ({
        lotto_imballaggio_id: x.lotto_imballaggio_id,
        quantita_usata:       x.quantita_usata ? Number(x.quantita_usata) : null,
        note:                 x.note ?? '',
      }))
    : [];
  form.detergenti = p?.detergenti?.length
    ? p.detergenti.map(x => ({
        lotto_detergente_id: x.lotto_detergente_id,
        quantita_usata:      x.quantita_usata ? Number(x.quantita_usata) : null,
        note:                x.note ?? '',
      }))
    : [];
  form.clearErrors();
});

function addMateriaPrima()     { form.materie_prime.push(emptyMateriaPrima()); }
function removeMateriaPrima(i) { form.materie_prime.splice(i, 1); }
function addImballaggio()      { form.imballaggi.push(emptyImballaggio()); }
function removeImballaggio(i)  { form.imballaggi.splice(i, 1); }
function addDetergente()       { form.detergenti.push(emptyDetergente()); }
function removeDetergente(i)   { form.detergenti.splice(i, 1); }

function submit() {
  const payload = {
    ...form.data(),
    data_produzione: form.data_produzione ? form.data_produzione.toISOString().slice(0, 10) : null,
    materie_prime: form.materie_prime.map(r => ({
      materia_prima_id: r.materia_prima_id,
      acquisto_riga_id: r.source_type === 'acquisto' ? r.lot_id : null,
      semilavorato_id:  r.source_type === 'interno'  ? r.lot_id : null,
      source_type:      r.source_type,
      quantita_kg:      r.quantita_kg,
    })),
  };
  if (isEdit.value) {
    form.transform(() => payload).put(`/produzioni/${props.produzione.id}`);
  } else {
    form.transform(() => payload).post('/produzioni');
  }
}

// ── Semilavorato section ──────────────────────────────────────────────────────

const showSemiForm = ref(false);

const schedaSelezionata = computed(() =>
  (props.schede ?? []).find(s => s.id === form.scheda_id) ?? null
);

const semiForm = useForm({
  lotto:         '',
  nome_prodotto: '',
  quantita_kg:   null,
  note:          '',
});

function openSemiForm() {
  semiForm.lotto         = `SL-${props.produzione?.lotto_produzione ?? ''}`;
  semiForm.nome_prodotto = schedaSelezionata.value?.prodotto?.nome ?? '';
  semiForm.quantita_kg   = props.produzione?.quantita_prodotta_kg
    ? Number(props.produzione.quantita_prodotta_kg)
    : null;
  semiForm.note = '';
  showSemiForm.value = true;
}

function submitSemi() {
  semiForm.post(`/produzioni/${props.produzione.id}/semilavorato`);
}
</script>

<style scoped>
.page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:1.5rem; }
.page-title { font-size:1.5rem; font-weight:700; color:var(--ink); margin:0; }
.form-card { background:var(--surface); border-radius:8px; border:1px solid var(--border); overflow:hidden; }
.mb-4 { margin-bottom:1rem; }
.form-section { padding:1.5rem; }
.section-title { font-size:0.9rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:var(--ink-2); margin:0 0 0.25rem 0; }
.section-sub { font-size:0.78rem; color:var(--ink-3); margin:0; }
.form-grid-4 { display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; }
.field { display:flex; flex-direction:column; gap:0.3rem; }
.field label { font-size:0.85rem; font-weight:600; color:var(--ink-2); }
.error { color:var(--danger); font-size:0.78rem; }
.righe-header { display:flex; align-items:flex-start; justify-content:space-between; padding:1rem 1.5rem; border-bottom:1px solid var(--border); }
.table-wrapper { overflow-x:auto; }
.righe-table { width:100%; border-collapse:collapse; font-size:0.85rem; }
.righe-table th { padding:0.6rem 0.5rem; text-align:left; font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.04em; color:var(--ink-2); background:var(--surface-2); border-bottom:1px solid var(--border); white-space:nowrap; }
.righe-table td { padding:0.4rem 0.5rem; border-bottom:1px solid var(--border); vertical-align:middle; }
.form-actions { padding:1rem 1.5rem; background:var(--surface-2); display:flex; align-items:center; justify-content:space-between; border-top:1px solid var(--border); }
.form-actions-sub { padding:0.5rem 1.5rem; background:var(--surface-2); display:flex; align-items:center; justify-content:flex-start; border-top:1px solid var(--border); }
.righe-count { font-size:0.85rem; color:var(--ink-2); }
.balance-ok { font-size:0.82rem; font-weight:600; color:var(--ok); font-family:var(--font-mono); }
.balance-negative { font-size:0.82rem; font-weight:600; color:var(--danger); font-family:var(--font-mono); }
.balance-empty { font-size:0.82rem; color:var(--ink-3); }
.semi-info { display:flex; flex-direction:column; gap:0.5rem; }
.semi-info-row { display:flex; gap:1rem; align-items:baseline; }
.semi-label { font-size:0.82rem; font-weight:600; color:var(--ink-2); min-width:80px; }
.semi-value { font-size:0.9rem; color:var(--ink); }
</style>
