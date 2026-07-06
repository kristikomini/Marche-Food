<template>
  <AppLayout>
    <div class="page-header">
      <div>
        <h1 class="page-title">Log Attività</h1>
        <p class="page-sub">Registro cronologico e non modificabile di ogni creazione, modifica, eliminazione e ripristino dei record operativi, con i valori prima → dopo.</p>
      </div>
    </div>

    <div class="result-card">
      <div class="table-wrap">
        <table class="result-table">
          <thead>
            <tr>
              <th>Data / ora</th>
              <th>Evento</th>
              <th>Tipo</th>
              <th>Riferimento</th>
              <th>Utente</th>
              <th>Modifiche</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in log" :key="r.id">
              <td class="nowrap">{{ formatDateTime(r.created_at) }}</td>
              <td><span class="tag" :class="eventClass(r.evento)">{{ eventLabel(r.evento) }}</span></td>
              <td>{{ r.tipo }}</td>
              <td class="mono">{{ r.etichetta || ('#' + r.record_id) }}</td>
              <td>{{ r.utente || '—' }}</td>
              <td>
                <template v-if="r.evento === 'updated' && r.modifiche">
                  <ul class="diff">
                    <li v-for="(v, campo) in r.modifiche" :key="campo">
                      <span class="field">{{ campo }}</span>:
                      <span class="old">{{ display(v.da) }}</span>
                      <span class="arrow">→</span>
                      <span class="new">{{ display(v.a) }}</span>
                    </li>
                  </ul>
                </template>
                <span v-else class="text-muted">{{ eventDetail(r.evento) }}</span>
              </td>
            </tr>
            <tr v-if="!log.length"><td colspan="6" class="empty">Nessuna attività registrata.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
  log: { type: Array, default: () => [] },
  attivita: { type: Array, default: () => [] },
});

const EVENT_LABELS = {
  created: 'Creato', updated: 'Modificato', deleted: 'Cestinato',
  restored: 'Ripristinato', force_deleted: 'Eliminato',
};
const EVENT_DETAIL = {
  created: 'Record creato', deleted: 'Spostato nel cestino',
  restored: 'Ripristinato dal cestino', force_deleted: 'Eliminato definitivamente',
};

function eventLabel(e) { return EVENT_LABELS[e] || e; }
function eventDetail(e) { return EVENT_DETAIL[e] || '—'; }
function eventClass(e) { return `ev-${e}`; }

function display(v) {
  if (v === null || v === undefined || v === '') return '∅';
  if (typeof v === 'boolean') return v ? 'sì' : 'no';
  return String(v);
}

function formatDateTime(d) {
  if (!d) return '—';
  return new Date(d).toLocaleString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>

<style scoped>
.page-header { margin-bottom:1.5rem; }
.page-title { font-size:1.5rem; font-weight:700; color:var(--ink); margin:0 0 0.25rem 0; }
.page-sub { font-size:0.875rem; color:var(--ink-2); margin:0; max-width:720px; }
.result-card { background:var(--surface); border:1px solid var(--border); border-radius:8px; overflow:hidden; }
.table-wrap { overflow-x:auto; }
.result-table { width:100%; border-collapse:collapse; font-size:0.85rem; }
.result-table th { padding:0.5rem 1rem; background:var(--surface-2); font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.04em; color:var(--ink-2); border-bottom:1px solid var(--border); text-align:left; white-space:nowrap; }
.result-table td { padding:0.55rem 1rem; border-bottom:1px solid var(--border); vertical-align:top; }
.nowrap { white-space:nowrap; }
.mono { font-family:var(--font-mono); font-size:0.8rem; }
.tag { font-size:0.68rem; font-weight:700; padding:0.15rem 0.5rem; border-radius:99px; white-space:nowrap; }
.ev-created { background:var(--ok-tint); color:var(--ok); }
.ev-updated { background:var(--info-tint); color:var(--info); }
.ev-deleted { background:var(--warn-tint); color:var(--warn); }
.ev-restored { background:var(--pine-tint); color:var(--pine-strong); }
.ev-force_deleted { background:var(--danger-tint); color:var(--danger); }
.diff { margin:0; padding:0; list-style:none; display:flex; flex-direction:column; gap:0.15rem; }
.diff li { font-size:0.8rem; }
.field { font-weight:600; color:var(--ink-2); }
.old { color:var(--danger); text-decoration:line-through; }
.new { color:var(--ok); font-weight:600; }
.arrow { color:var(--ink-3); margin:0 0.15rem; }
.text-muted { color:var(--ink-3); font-style:italic; }
.empty { text-align:center; color:var(--ink-3); font-style:italic; padding:1.5rem; }
</style>
