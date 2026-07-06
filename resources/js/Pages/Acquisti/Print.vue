<template>
  <div class="print-page">
    <div class="no-print toolbar">
      <button class="print-btn" @click="window.print()">&#128438; Stampa</button>
      <a :href="`/acquisti/${acquisto.id}/edit`" class="back-btn">&#8592; Torna al documento</a>
    </div>

    <div class="doc">
      <!-- Header -->
      <div class="doc-header">
        <div class="company">
          <img src="/favicon.png" alt="MIF" class="logo" />
          <div>
            <div class="company-name">Marche International Food S.r.l.</div>
            <div class="company-sub">Sistema di Tracciabilità HACCP</div>
          </div>
        </div>
        <div class="doc-meta">
          <div class="doc-type">{{ acquisto.tipo_documento ?? 'Acquisto' }}</div>
          <div class="doc-num">N° {{ acquisto.numero_documento }}</div>
          <div class="doc-date">Del {{ formatDate(acquisto.data_documento) }}</div>
        </div>
      </div>

      <hr class="divider" />

      <!-- Parties -->
      <div class="parties">
        <div class="party">
          <div class="party-label">Fornitore</div>
          <div class="party-name">{{ acquisto.fornitore?.ragione_sociale ?? '—' }}</div>
          <div class="party-code" v-if="acquisto.fornitore?.codice">Cod. {{ acquisto.fornitore.codice }}</div>
        </div>
        <div class="party" v-if="acquisto.note">
          <div class="party-label">Note</div>
          <div class="party-name">{{ acquisto.note }}</div>
        </div>
      </div>

      <!-- Lines -->
      <table class="righe">
        <thead>
          <tr>
            <th class="left">Prodotto / Descrizione</th>
            <th>U.M.</th>
            <th>Q.tà Pz</th>
            <th>Q.tà Kg</th>
            <th>Lotto Int.</th>
            <th>Lotto Esterno</th>
            <th>Scadenza</th>
            <th>Data Entrata</th>
            <th>Data Uscita</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(r, i) in acquisto.righe" :key="i">
            <td class="left">{{ r.nome_prodotto }}</td>
            <td class="center">{{ r.um ?? '—' }}</td>
            <td class="right">{{ r.quantita_pz != null ? Number(r.quantita_pz).toLocaleString('it-IT') : '—' }}</td>
            <td class="right mono">{{ r.quantita_kg != null ? Number(r.quantita_kg).toFixed(3) : '—' }}</td>
            <td class="mono">{{ r.lotto ?? '—' }}</td>
            <td class="mono">{{ r.lotto_esterno ?? '—' }}</td>
            <td class="center">{{ r.scadenza ? formatDate(r.scadenza) : '—' }}</td>
            <td class="center">{{ r.data_in ? formatDate(r.data_in) : '—' }}</td>
            <td class="center">{{ r.data_out ? formatDate(r.data_out) : '—' }}</td>
          </tr>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="3" class="right total-label">Totale Kg:</td>
            <td class="right mono total-val">{{ totalKg }}</td>
            <td colspan="5"></td>
          </tr>
        </tfoot>
      </table>

      <!-- Footer -->
      <div class="doc-footer">
        <div>Stampato il {{ today }}</div>
        <div>Sistema Tracciabilità — Marche International Food S.r.l.</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue';

const props = defineProps({ acquisto: Object });
const window = globalThis;

const today = new Date().toLocaleDateString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric' });

function formatDate(d) {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

const totalKg = computed(() => {
  const sum = (props.acquisto.righe ?? []).reduce((a, r) => a + (parseFloat(r.quantita_kg) || 0), 0);
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
.back-btn:hover { background: rgba(255,255,255,0.25); }

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

.parties { display: flex; gap: 2rem; margin: 1rem 0 1.5rem; }
.party-label { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; margin-bottom: 0.2rem; }
.party-name { font-size: 0.95rem; font-weight: 600; color: #1e293b; }
.party-code { font-size: 0.75rem; color: #64748b; }

.righe { width: 100%; border-collapse: collapse; font-size: 0.78rem; }
.righe th { background: #1f5040; color: #fff; padding: 0.35rem 0.5rem; text-align: center; font-weight: 600; font-size: 0.72rem; }
.righe th.left { text-align: left; }
.righe td { padding: 0.3rem 0.5rem; border-bottom: 1px solid #e8f0e8; }
.righe tr:nth-child(even) td { background: #f8fdf8; }
.left { text-align: left; }
.center { text-align: center; }
.right { text-align: right; }
.mono { font-family: monospace; }
tfoot td { border-top: 2px solid #2e6b57; font-weight: 700; padding-top: 0.4rem; }
.total-label { color: #2e6b57; }
.total-val { font-size: 0.88rem; }

.doc-footer { margin-top: 1.5rem; display: flex; justify-content: space-between; font-size: 0.72rem; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 0.5rem; }

@media print {
  .no-print { display: none !important; }
  .print-page { padding-top: 0; }
  body { background: #fff; }
  .doc { box-shadow: none; border: none; margin: 0; padding: 1rem; max-width: 100%; }
}
</style>
