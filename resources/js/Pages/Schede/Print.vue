<template>
  <div class="print-page">
    <div class="no-print toolbar">
      <button class="print-btn" @click="window.print()">&#128438; Stampa</button>
      <a :href="`/schede/${scheda.id}/edit`" class="back-btn">&#8592; Torna alla scheda</a>
    </div>

    <div class="doc">
      <!-- Header -->
      <div class="doc-header">
        <div class="company">
          <img src="/favicon.png" alt="MIF" class="logo" />
          <div>
            <div class="company-name">Marche International Food S.r.l.</div>
            <div class="company-sub">Scheda di Produzione HACCP</div>
          </div>
        </div>
        <div class="doc-meta">
          <div class="doc-type">Documento Tecnico</div>
          <div class="doc-num mono">{{ schedaCodice }}</div>
          <div class="doc-date">Rev. del {{ formatDate(scheda.data_revisione) }}</div>
          <div class="doc-status" :class="scheda.attiva ? 'status-active' : 'status-inactive'">
            {{ scheda.attiva ? 'ATTIVA' : 'NON ATTIVA' }}
          </div>
        </div>
      </div>

      <hr class="divider" />

      <!-- Product info -->
      <div class="info-grid">
        <div class="info-block" style="grid-column:span 2">
          <div class="info-label">Prodotto Finito</div>
          <div class="info-val">{{ scheda.prodotto?.nome ?? '—' }}</div>
          <div class="info-sub">{{ (scheda.prodotto?.varianti || []).map(v => v.codice_prodotto).filter(Boolean).join(', ') }}</div>
        </div>
        <div class="info-block">
          <div class="info-label">Marinatura</div>
          <div class="info-val">{{ scheda.ha_marinatura ? 'Sì' : 'No' }}</div>
        </div>
        <div class="info-block" v-if="scheda.note">
          <div class="info-label">Note</div>
          <div class="info-val">{{ scheda.note }}</div>
        </div>
      </div>

      <!-- Recipe -->
      <h2 class="section-title">Ricetta</h2>
      <table class="righe">
        <thead>
          <tr>
            <th class="left">Materia Prima</th>
            <th style="width:100px">%</th>
            <th style="width:100px">g/kg</th>
            <th style="width:70px">U.M.</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in scheda.ricette" :key="r.id">
            <td class="left">{{ r.materia_prima?.nome ?? '—' }}</td>
            <td class="right mono">{{ r.percentuale != null ? Number(r.percentuale).toFixed(3) : '—' }}</td>
            <td class="right mono">{{ r.grammi_per_kg != null ? Number(r.grammi_per_kg).toFixed(3) : '—' }}</td>
            <td class="center">{{ r.um ?? '—' }}</td>
          </tr>
          <tr v-if="!scheda.ricette?.length">
            <td colspan="4" class="empty">Nessun ingrediente in ricetta.</td>
          </tr>
        </tbody>
      </table>

      <!-- Marinatura ricetta -->
      <template v-if="scheda.ha_marinatura && scheda.ricette_marinature?.length">
        <h2 class="section-title" style="margin-top:1.25rem">Ricetta Marinatura</h2>
        <table class="righe">
          <thead>
            <tr>
              <th class="left">Materia Prima</th>
              <th style="width:100px">%</th>
              <th style="width:100px">g/kg</th>
              <th style="width:70px">U.M.</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in scheda.ricette_marinature" :key="r.id">
              <td class="left">{{ r.materia_prima?.nome ?? '—' }}</td>
              <td class="right mono">{{ r.percentuale != null ? Number(r.percentuale).toFixed(3) : '—' }}</td>
              <td class="right mono">{{ r.grammi_per_kg != null ? Number(r.grammi_per_kg).toFixed(3) : '—' }}</td>
              <td class="center">{{ r.um ?? '—' }}</td>
            </tr>
          </tbody>
        </table>
      </template>

      <!-- Flussi di lavorazione -->
      <template v-if="scheda.flussi?.length">
        <h2 class="section-title" style="margin-top:1.25rem">Flussi di Lavorazione</h2>
        <table class="righe">
          <thead>
            <tr>
              <th style="width:50px">N°</th>
              <th class="left">Fase</th>
              <th class="left">Controllo</th>
              <th style="width:100px">Misura</th>
              <th style="width:140px">Valore Controllo</th>
              <th style="width:80px">T (min)</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="f in scheda.flussi" :key="f.id">
              <td class="center">{{ f.flusso?.numero }}</td>
              <td class="left">{{ f.flusso?.nome }}</td>
              <td class="left">{{ f.flusso?.controllo ?? '—' }}</td>
              <td class="center">{{ f.flusso?.misura ?? '—' }}</td>
              <td class="center">{{ f.valore_controllo ?? '—' }}</td>
              <td class="center">{{ f.tempo_minuti ?? '—' }}</td>
            </tr>
          </tbody>
        </table>
      </template>

      <!-- Signatures -->
      <div class="signatures">
        <div class="sig-block">
          <div class="sig-label">Redatto da</div>
          <div class="sig-line"></div>
        </div>
        <div class="sig-block">
          <div class="sig-label">Approvato da</div>
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
        <div>{{ schedaCodice }} — Marche International Food S.r.l.</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({ scheda: Object });
const window = globalThis;

const today = new Date().toLocaleDateString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric' });

function formatDate(d) {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

const schedaCodice = computed(() => {
  if (!props.scheda) return '—';
  return `${props.scheda.modello}.${String(props.scheda.revisione ?? 0).padStart(2, '0')}`;
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
  background: #fff; max-width: 1000px; margin: 1.5rem auto;
  padding: 2rem 2.5rem; border: 1px solid #ddd;
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
.doc-status { display: inline-block; margin-top: 0.25rem; padding: 0.1rem 0.5rem; border-radius: 99px; font-size: 0.68rem; font-weight: 700; }
.status-active { background: #dcfce7; color: #166534; }
.status-inactive { background: #fee2e2; color: #991b1b; }

.divider { border: none; border-top: 2px solid #2e6b57; margin: 0.75rem 0; }

.info-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin: 1rem 0 1.5rem; }
.info-label { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; margin-bottom: 0.2rem; }
.info-val { font-size: 0.9rem; font-weight: 600; color: #1e293b; }
.info-sub { font-size: 0.75rem; color: #64748b; }

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

.signatures { display: grid; grid-template-columns: repeat(3, 1fr); gap: 2rem; margin-top: 2rem; }
.sig-label { font-size: 0.72rem; color: #64748b; margin-bottom: 0.75rem; }
.sig-line { border-top: 1px solid #1f5040; }

.doc-footer { margin-top: 1.5rem; display: flex; justify-content: space-between; font-size: 0.72rem; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 0.5rem; }

@media print {
  .no-print { display: none !important; }
  .print-page { padding-top: 0; }
  body { background: #fff; }
  .doc { box-shadow: none; border: none; margin: 0; padding: 1rem; max-width: 100%; }
}
</style>
