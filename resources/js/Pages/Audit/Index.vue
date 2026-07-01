<template>
  <AppLayout>
    <div class="page-header">
      <div>
        <h1 class="page-title">Log Attività</h1>
        <p class="page-sub">Chi ha creato o modificato i record operativi (acquisti, vendite, produzioni, imballaggi, note di credito).</p>
      </div>
    </div>

    <div class="result-card">
      <div class="table-wrap">
        <table class="result-table">
          <thead>
            <tr>
              <th>Tipo</th>
              <th>Riferimento</th>
              <th>Creato da</th>
              <th>Modificato da</th>
              <th>Creato il</th>
              <th>Ultima modifica</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(r, i) in attivita" :key="i">
              <td><span class="tag">{{ r.tipo }}</span></td>
              <td class="mono">{{ r.etichetta || '—' }}</td>
              <td>{{ r.creato_da || '—' }}</td>
              <td>{{ r.modificato_da || '—' }}</td>
              <td>{{ formatDateTime(r.created_at) }}</td>
              <td>{{ formatDateTime(r.updated_at) }}</td>
            </tr>
            <tr v-if="!attivita.length"><td colspan="6" class="empty">Nessuna attività registrata.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
  attivita: { type: Array, default: () => [] },
});

function formatDateTime(d) {
  if (!d) return '—';
  return new Date(d).toLocaleString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>

<style scoped>
.page-header { margin-bottom:1.5rem; }
.page-title { font-size:1.5rem; font-weight:700; color:#1e293b; margin:0 0 0.25rem 0; }
.page-sub { font-size:0.875rem; color:#64748b; margin:0; }
.result-card { background:#fff; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; }
.table-wrap { overflow-x:auto; }
.result-table { width:100%; border-collapse:collapse; font-size:0.85rem; }
.result-table th { padding:0.5rem 1rem; background:#f8fafc; font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.04em; color:#64748b; border-bottom:1px solid #e2e8f0; text-align:left; white-space:nowrap; }
.result-table td { padding:0.55rem 1rem; border-bottom:1px solid #f1f5f9; white-space:nowrap; }
.mono { font-family:'SFMono-Regular',Consolas,monospace; font-size:0.8rem; }
.tag { font-size:0.72rem; font-weight:700; padding:0.15rem 0.5rem; border-radius:99px; background:#f0fdf4; color:#2a6941; }
.empty { text-align:center; color:#94a3b8; font-style:italic; padding:1.5rem; }
</style>
