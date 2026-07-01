<template>
  <AppLayout>
    <div class="page-header">
      <div>
        <h1 class="page-title">Recall — Lotto {{ recall.lotto }}</h1>
        <p class="page-sub">{{ recall.prodotto || 'Prodotto non specificato' }} · aperto il {{ formatDate(recall.data_apertura) }}</p>
      </div>
      <div class="header-actions">
        <Link href="/recall" class="btn-ghost"><i class="pi pi-arrow-left" /> Torna</Link>
      </div>
    </div>

    <div class="grid">
      <!-- Recall info -->
      <div class="info-card">
        <div class="row"><span class="lbl">Stato</span><span :class="['tag', recall.stato]">{{ statoLabel(recall.stato) }}</span></div>
        <div class="row"><span class="lbl">Motivo</span><span>{{ recall.motivo }}</span></div>
        <div class="row"><span class="lbl">Da notificare</span><span>{{ daNotificare }} / {{ notifiche.length }}</span></div>
        <div v-if="recall.data_chiusura" class="row"><span class="lbl">Chiuso il</span><span>{{ formatDate(recall.data_chiusura) }}</span></div>

        <div class="progress"><div class="progress-bar" :style="{ width: progress + '%' }" /></div>

        <div class="actions">
          <Button v-if="recall.stato !== 'chiuso'" label="Chiudi recall" icon="pi pi-check" severity="success" size="small"
                  @click="setStato('chiuso')" :disabled="daNotificare > 0"
                  v-tooltip.top="daNotificare > 0 ? 'Notifica tutti i clienti prima di chiudere' : ''" />
          <Button v-if="recall.stato === 'chiuso'" label="Riapri" icon="pi pi-refresh" severity="secondary" size="small" @click="setStato('in_corso')" />
        </div>
      </div>

      <!-- Notifications -->
      <div class="result-card">
        <div class="result-header">
          <i class="pi pi-send result-icon" />
          <div>
            <div class="result-title">Clienti da notificare</div>
            <div class="result-sub">Segna ogni cliente come contattato</div>
          </div>
        </div>
        <div class="table-wrap">
          <table class="result-table">
            <thead><tr><th>Cliente</th><th>Documento</th><th class="r">Kg</th><th>Stato</th><th>Azione</th></tr></thead>
            <tbody>
              <tr v-for="n in notifiche" :key="n.id" :class="{ done: n.notificato }">
                <td><strong>{{ n.cliente?.ragione_sociale ?? '—' }}</strong></td>
                <td>{{ n.documento ?? '—' }}</td>
                <td class="r">{{ n.quantita_kg ? Number(n.quantita_kg).toFixed(3) : '—' }}</td>
                <td>
                  <span v-if="n.notificato" class="ok"><i class="pi pi-check-circle" /> Notificato</span>
                  <span v-else class="pending"><i class="pi pi-clock" /> In attesa</span>
                </td>
                <td>
                  <Button v-if="!n.notificato" label="Segna notificato" size="small" icon="pi pi-check" @click="mark(n, true)" />
                  <Button v-else label="Annulla" size="small" severity="secondary" text @click="mark(n, false)" />
                </td>
              </tr>
              <tr v-if="!notifiche.length"><td colspan="5" class="empty">Nessun cliente ha ricevuto questo lotto.</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from 'primevue/button';

const props = defineProps({
  recall:    { type: Object, required: true },
  notifiche: { type: Array, default: () => [] },
});

const daNotificare = computed(() => props.notifiche.filter(n => !n.notificato).length);
const progress = computed(() => props.notifiche.length ? Math.round((props.notifiche.length - daNotificare.value) / props.notifiche.length * 100) : 0);

function mark(n, notificato) {
  router.post(`/recall/${props.recall.id}/notifiche/${n.id}`, { notificato }, { preserveScroll: true });
}
function setStato(stato) {
  router.put(`/recall/${props.recall.id}/stato`, { stato }, { preserveScroll: true });
}
function statoLabel(s) { return { aperto: 'Aperto', in_corso: 'In corso', chiuso: 'Chiuso' }[s] ?? s; }
function formatDate(d) { return d ? new Date(d).toLocaleDateString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '—'; }
</script>

<style scoped>
.page-header { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:1.5rem; gap:1rem; }
.page-title { font-size:1.5rem; font-weight:700; color:#1e293b; margin:0 0 0.25rem 0; }
.page-sub { font-size:0.875rem; color:#64748b; margin:0; }
.btn-ghost { display:inline-flex; align-items:center; gap:0.4rem; border:1px solid #d1d5db; color:#374151; border-radius:6px; padding:0.5rem 0.9rem; font-size:0.85rem; text-decoration:none; }
.grid { display:grid; grid-template-columns:300px 1fr; gap:1rem; align-items:start; }
.info-card { background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:1.25rem; }
.info-card .row { display:flex; justify-content:space-between; gap:1rem; padding:0.5rem 0; border-bottom:1px solid #f1f5f9; font-size:0.85rem; }
.info-card .lbl { color:#64748b; font-weight:600; }
.progress { height:8px; background:#f1f5f9; border-radius:99px; margin:1rem 0; overflow:hidden; }
.progress-bar { height:100%; background:#2a6941; transition:width 0.2s; }
.actions { margin-top:0.5rem; }
.tag { font-size:0.72rem; font-weight:700; padding:0.15rem 0.6rem; border-radius:99px; }
.tag.aperto { background:#fee2e2; color:#b91c1c; }
.tag.in_corso { background:#ffedd5; color:#b45309; }
.tag.chiuso { background:#dcfce7; color:#166534; }
.result-card { background:#fff; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; }
.result-header { display:flex; align-items:center; gap:1rem; padding:0.9rem 1.5rem; border-bottom:1px solid #f1f5f9; }
.result-icon { font-size:1.1rem; width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; background:#fff7ed; color:#c2410c; }
.result-title { font-weight:700; color:#1e293b; font-size:0.95rem; }
.result-sub { font-size:0.8rem; color:#64748b; }
.table-wrap { overflow-x:auto; }
.result-table { width:100%; border-collapse:collapse; font-size:0.85rem; }
.result-table th { padding:0.5rem 1rem; background:#f8fafc; font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.04em; color:#64748b; border-bottom:1px solid #e2e8f0; text-align:left; }
.result-table th.r, .result-table td.r { text-align:right; }
.result-table td { padding:0.55rem 1rem; border-bottom:1px solid #f1f5f9; }
.result-table tr.done { background:#f6fdf9; }
.ok { color:#166534; font-weight:600; display:inline-flex; align-items:center; gap:0.3rem; }
.pending { color:#b45309; display:inline-flex; align-items:center; gap:0.3rem; }
.empty { text-align:center; color:#94a3b8; font-style:italic; padding:1.5rem; }
@media (max-width:768px){ .grid { grid-template-columns:1fr; } }
</style>
