<template>
  <AppLayout>
    <div class="page-header">
      <div>
        <Link href="/schede" class="back-link"><i class="pi pi-arrow-left" /> Schede</Link>
        <h1 class="page-title">Confronto schede di produzione</h1>
      </div>
    </div>

    <div v-if="schede.length < 2" class="empty-hint">
      Seleziona almeno due schede dalla pagina Schede (colonna «Confronta») per vederle affiancate.
    </div>

    <div v-else class="table-wrapper">
      <table class="cmp">
        <thead>
          <tr>
            <th class="row-lbl">Campo</th>
            <th v-for="s in schede" :key="s.id">
              {{ s.prodotto ?? '—' }}
              <div class="sub">{{ s.modello }} REV{{ s.revisione }}</div>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td class="row-lbl">Revisione</td>
            <td v-for="s in schede" :key="s.id" :class="{ diff: differs('revisione', s) }">{{ s.modello }} REV{{ s.revisione }}<span v-if="s.data_revisione"> · {{ fmtDate(s.data_revisione) }}</span></td>
          </tr>
          <tr>
            <td class="row-lbl">Attiva</td>
            <td v-for="s in schede" :key="s.id">{{ s.attiva ? 'Sì' : 'No' }}</td>
          </tr>
          <tr>
            <td class="row-lbl">Marinatura</td>
            <td v-for="s in schede" :key="s.id">{{ s.ha_marinatura ? 'Sì' : 'No' }}</td>
          </tr>
          <tr>
            <td class="row-lbl">Varianti / Pezzature</td>
            <td v-for="s in schede" :key="s.id" :class="{ diff: differsList('varianti', s, v => `${v.codice} ${v.pezzatura || ''}`) }">
              <div v-for="(v, i) in s.varianti" :key="i" class="mono">{{ v.codice }} · {{ v.pezzatura || '—' }}</div>
              <span v-if="!s.varianti.length" class="muted">—</span>
            </td>
          </tr>
          <tr>
            <td class="row-lbl">Ricetta (ingredienti)</td>
            <td v-for="s in schede" :key="s.id" :class="{ diff: differsList('ricette', s, r => r.materia) }">
              <div v-for="(r, i) in s.ricette" :key="i">{{ r.materia ?? '—' }}<span v-if="r.percentuale" class="muted"> ({{ r.percentuale }}%)</span></div>
              <span v-if="!s.ricette.length" class="muted">—</span>
            </td>
          </tr>
          <tr>
            <td class="row-lbl">Imballaggi primari</td>
            <td v-for="s in schede" :key="s.id" :class="{ diff: differsList('imballaggi', s, x => x) }">
              <div v-for="(im, i) in s.imballaggi" :key="i">{{ im }}</div>
              <span v-if="!s.imballaggi.length" class="muted">—</span>
            </td>
          </tr>
          <tr>
            <td class="row-lbl">Gas</td>
            <td v-for="s in schede" :key="s.id" :class="{ diff: differsList('gas', s, x => x) }">
              <div v-for="(g, i) in s.gas" :key="i">{{ g }}</div>
              <span v-if="!s.gas.length" class="muted">—</span>
            </td>
          </tr>
          <tr>
            <td class="row-lbl">Ciclo di lavoro</td>
            <td v-for="s in schede" :key="s.id" :class="{ diff: differsList('ciclo', s, c => `${c.numero} ${c.nome}`) }">
              <div v-for="(c, i) in s.ciclo" :key="i">{{ c.numero }} · {{ c.nome }}</div>
              <span v-if="!s.ciclo.length" class="muted">—</span>
            </td>
          </tr>
        </tbody>
      </table>
      <p class="legend"><span class="chip-diff"></span> celle evidenziate = differenza tra le schede confrontate</p>
    </div>
  </AppLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ schede: { type: Array, default: () => [] } });

function fmtDate(d) {
  return d ? new Date(d).toLocaleDateString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '';
}

// Una cella è "diff" se il suo valore non è uguale in tutte le schede.
function differs(field, s) {
  const vals = props.schede.map(x => JSON.stringify(x[field]));
  return new Set(vals).size > 1;
}
function differsList(field, s, keyFn) {
  const sig = x => (x[field] || []).map(keyFn).join('|');
  const vals = props.schede.map(sig);
  return new Set(vals).size > 1;
}
</script>

<style scoped>
.page-header { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:1.25rem; }
.page-title { font-size:1.5rem; font-weight:700; color:var(--ink); margin:0.25rem 0 0; }
.back-link { font-size:0.8rem; color:var(--info); text-decoration:none; }
.back-link:hover { text-decoration:underline; }
.empty-hint { padding:2rem; text-align:center; color:var(--ink-3); background:var(--surface); border:1px solid var(--border); border-radius:8px; }
.table-wrapper { overflow-x:auto; }
.cmp { width:100%; border-collapse:collapse; font-size:0.85rem; background:var(--surface); }
.cmp th, .cmp td { border:1px solid var(--border); padding:0.5rem 0.75rem; vertical-align:top; text-align:left; }
.cmp thead th { background:var(--surface-2); font-weight:700; }
.cmp thead th .sub { font-size:0.72rem; font-weight:500; color:var(--ink-3); }
.row-lbl { font-weight:700; color:var(--ink-2); background:var(--surface-2); white-space:nowrap; width:180px; }
.mono { font-family:var(--font-mono, monospace); }
.muted { color:var(--ink-3); }
.diff { background:var(--warn-tint, #fef3c7); }
.legend { font-size:0.78rem; color:var(--ink-3); margin-top:0.6rem; }
.chip-diff { display:inline-block; width:12px; height:12px; border-radius:3px; background:var(--warn-tint, #fef3c7); border:1px solid var(--border); vertical-align:middle; margin-right:0.3rem; }
</style>
