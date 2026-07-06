<template>
  <div class="print-page">
    <div class="no-print toolbar">
      <button class="print-btn" @click="window.print()">&#128438; Stampa</button>
      <a :href="`/produzioni/${produzione.id}/edit`" class="back-btn">&#8592; Torna al documento</a>
    </div>

    <div class="doc">
      <!-- Header -->
      <div class="doc-header">
        <div class="company">
          <img src="/favicon.png" alt="MIF" class="logo" />
          <div>
            <div class="company-name">Marche International Food S.r.l.</div>
            <div class="company-sub">Scheda di Tracciabilità Produzione HACCP</div>
          </div>
        </div>
        <div class="doc-meta">
          <div class="doc-type">Lotto Produzione</div>
          <div class="doc-num">{{ produzione.lotto_produzione }}</div>
          <div class="doc-date">{{ formatDate(produzione.data_produzione) }}</div>
        </div>
      </div>

      <hr class="divider" />

      <!-- Product info -->
      <div class="info-grid">
        <div class="info-block">
          <div class="info-label">Prodotto Finito</div>
          <div class="info-val">{{ produzione.scheda?.prodotto?.nome ?? '—' }}</div>
        </div>
        <div class="info-block">
          <div class="info-label">Scheda di Produzione</div>
          <div class="info-val mono">{{ schedaCodice }}</div>
        </div>
        <div class="info-block">
          <div class="info-label">Q.tà Prodotta</div>
          <div class="info-val">{{ produzione.quantita_prodotta_kg != null ? Number(produzione.quantita_prodotta_kg).toFixed(3) + ' kg' : '—' }}</div>
        </div>
        <div class="info-block">
          <div class="info-label">Operatore</div>
          <div class="info-val">{{ produzione.operatore ?? '—' }}</div>
        </div>
        <div class="info-block" v-if="produzione.note" style="grid-column:span 2">
          <div class="info-label">Note</div>
          <div class="info-val">{{ produzione.note }}</div>
        </div>
      </div>

      <!-- Raw materials -->
      <h2 class="section-title">Materie Prime Utilizzate (Tracciabilità)</h2>
      <table class="righe">
        <thead>
          <tr>
            <th class="left">Materia Prima</th>
            <th class="left">Fornitore</th>
            <th>N° Doc. Acquisto</th>
            <th>Lotto Interno</th>
            <th>Lotto Esterno</th>
            <th>Q.tà Kg</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(r, i) in produzione.materie_prime" :key="i">
            <td class="left">{{ r.materia_prima?.nome ?? '—' }}</td>
            <td class="left">{{ r.acquisto_riga?.acquisto?.fornitore?.ragione_sociale ?? '—' }}</td>
            <td class="center mono">{{ r.acquisto_riga?.acquisto?.numero_documento ?? '—' }}</td>
            <td class="mono">{{ r.acquisto_riga?.lotto ?? '—' }}</td>
            <td class="mono">{{ r.acquisto_riga?.lotto_esterno ?? '—' }}</td>
            <td class="right mono">{{ r.quantita_kg != null ? Number(r.quantita_kg).toFixed(3) : '—' }}</td>
          </tr>
          <tr v-if="!produzione.materie_prime?.length">
            <td colspan="6" class="empty">Nessuna materia prima registrata.</td>
          </tr>
        </tbody>
        <tfoot v-if="produzione.materie_prime?.length">
          <tr>
            <td colspan="5" class="right total-label">Totale Kg utilizzati:</td>
            <td class="right mono total-val">{{ totalKg }}</td>
          </tr>
        </tfoot>
      </table>

      <!-- Flussi di lavorazione -->
      <template v-if="produzione.scheda?.flussi?.length">
        <h2 class="section-title" style="margin-top:1.5rem">Flussi di Lavorazione</h2>
        <table class="righe">
          <thead>
            <tr>
              <th style="width:50px">N°</th>
              <th class="left">Fase</th>
              <th class="left">Controllo</th>
              <th style="width:100px">Misura</th>
              <th style="width:130px">Valore Rilevato</th>
              <th style="width:80px">T (min)</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="f in produzione.scheda.flussi" :key="f.id">
              <td class="center">{{ f.flusso?.numero }}</td>
              <td class="left">{{ f.flusso?.nome }}</td>
              <td class="left">{{ f.flusso?.controllo ?? '—' }}</td>
              <td class="center">{{ f.flusso?.misura ?? '—' }}</td>
              <td class="center">{{ f.valore_controllo ?? '' }}</td>
              <td class="center">{{ f.tempo_minuti ?? '' }}</td>
            </tr>
          </tbody>
        </table>
      </template>

      <!-- Signatures -->
      <div class="signatures">
        <div class="sig-block">
          <div class="sig-label">Responsabile di produzione</div>
          <div class="sig-line"></div>
        </div>
        <div class="sig-block">
          <div class="sig-label">Controllo qualità</div>
          <div class="sig-line"></div>
        </div>
        <div class="sig-block">
          <div class="sig-label">Data e firma</div>
          <div class="sig-line"></div>
        </div>
      </div>

      <!-- Footer -->
      <div class="doc-footer">
        <div>Stampato il {{ today }}</div>
        <div>Sistema Tracciabilità HACCP — Marche International Food S.r.l.</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({ produzione: Object });
const window = globalThis;

const today = new Date().toLocaleDateString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric' });

function formatDate(d) {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

const schedaCodice = computed(() => {
  const s = props.produzione.scheda;
  if (!s) return '—';
  return `${s.modello}.${String(s.revisione ?? 0).padStart(2, '0')}`;
});

const totalKg = computed(() => {
  const sum = (props.produzione.materie_prime ?? []).reduce((a, r) => a + (parseFloat(r.quantita_kg) || 0), 0);
  return sum > 0 ? sum.toFixed(3) : '—';
});
</script>

<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f4f4; color: #111; font-size: 12px; }

.toolbar {
  position: fixed; top: 0; left: 0; right: 0; z-index: 100;
  background: #2e6b57; padding: 0.5rem 1.5rem;
  display: flex; gap: 1rem; align-items: center;
}
.print-btn, .back-btn {
  padding: 0.4rem 1rem; border-radius: 4px; border: none; cursor: pointer;
  font-size: 0.85rem; font-weight: 600; text-decoration: none;
}
.print-btn { background: #fff; color: #2e6b57; }
.back-btn { background: rgba(255,255,255,0.15); color: #fff; }

.print-page { padding-top: 52px; }

.doc {
  background: #fff;
  max-width: 1000px;
  margin: 1.5rem auto;
  padding: 2rem 2.5rem;
  border: 1px solid #ddd;
  box-shadow: 0 2px 8px rgba(0,0,0,.08);
}

.doc-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; }
.company { display: flex; align-items: center; gap: 0.75rem; }
.logo { width: 44px; height: 44px; object-fit: contain; }
.company-name { font-size: 1rem; font-weight: 700; color: #1f5040; }
.company-sub { font-size: 0.72rem; color: #5a8c6a; }
.doc-meta { text-align: right; }
.doc-type { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; }
.doc-num { font-size: 1.2rem; font-weight: 800; color: #1f5040; }
.doc-date { font-size: 0.82rem; color: #374151; }

.divider { border: none; border-top: 2px solid #2e6b57; margin: 0.75rem 0; }

.info-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin: 1rem 0 1.5rem; }
.info-label { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; margin-bottom: 0.2rem; }
.info-val { font-size: 0.9rem; font-weight: 600; color: #1e293b; }

.section-title { font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #2e6b57; margin: 0 0 0.5rem 0; border-bottom: 1px solid #e8f0e8; padding-bottom: 0.25rem; }

.righe { width: 100%; border-collapse: collapse; font-size: 0.78rem; }
.righe th { background: #1f5040; color: #fff; padding: 0.35rem 0.5rem; text-align: center; font-weight: 600; font-size: 0.72rem; }
.righe th.left { text-align: left; }
.righe td { padding: 0.3rem 0.5rem; border-bottom: 1px solid #e8f0e8; }
.righe tr:nth-child(even) td { background: #f8fdf8; }
.left { text-align: left; }
.center { text-align: center; }
.right { text-align: right; }
.mono { font-family: monospace; }
.empty { text-align: center; color: #94a3b8; padding: 0.75rem; }
tfoot td { border-top: 2px solid #2e6b57; font-weight: 700; padding-top: 0.4rem; }
.total-label { color: #2e6b57; }
.total-val { font-size: 0.88rem; }

.signatures { display: grid; grid-template-columns: repeat(3, 1fr); gap: 2rem; margin-top: 2rem; }
.sig-label { font-size: 0.72rem; color: #64748b; margin-bottom: 0.75rem; }
.sig-line { border-top: 1px solid #1f5040; width: 100%; }

.doc-footer { margin-top: 1.5rem; display: flex; justify-content: space-between; font-size: 0.72rem; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 0.5rem; }

@media print {
  .no-print { display: none !important; }
  .print-page { padding-top: 0; }
  body { background: #fff; }
  .doc { box-shadow: none; border: none; margin: 0; padding: 1rem; max-width: 100%; }
}
</style>
