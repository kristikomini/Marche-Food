<template>
  <AppLayout>
    <div class="page-header">
      <div>
        <h1 class="page-title">Rapporto di Richiamo (Recall)</h1>
        <p class="page-sub">Inserisci un lotto di produzione per identificare tutti i clienti che hanno ricevuto quel prodotto.</p>
      </div>
    </div>

    <!-- Search -->
    <div class="search-card">
      <form @submit.prevent="search">
        <div class="search-row">
          <IconField style="flex:1">
            <InputIcon class="pi pi-search" />
            <InputText v-model="query" placeholder="Cerca per lotto di produzione..." fluid @keydown.enter="search" />
          </IconField>
          <Button type="submit" label="Cerca" icon="pi pi-search" :loading="searching" />
        </div>
      </form>
    </div>

    <!-- Results -->
    <template v-if="q">
      <!-- No results -->
      <div v-if="!produzioni.length && !venditeRighe.length" class="empty-state">
        <i class="pi pi-inbox" />
        <p>Nessun risultato per <strong>{{ q }}</strong></p>
      </div>

      <template v-else>
        <!-- Matching Productions -->
        <div v-if="produzioni.length" class="result-card mb-4">
          <div class="result-header">
            <i class="pi pi-cog result-icon production" />
            <div>
              <div class="result-title">Produzioni corrispondenti</div>
              <div class="result-sub">{{ produzioni.length }} lotto/i trovato/i</div>
            </div>
          </div>
          <table class="result-table">
            <thead>
              <tr><th>Lotto Produzione</th><th>Data</th><th>Prodotto</th><th>Q.tà (kg)</th></tr>
            </thead>
            <tbody>
              <tr v-for="p in produzioni" :key="p.id">
                <td><strong>{{ p.lotto_produzione }}</strong></td>
                <td>{{ formatDate(p.data_produzione) }}</td>
                <td>{{ p.scheda?.prodotto?.nome ?? '—' }}</td>
                <td>{{ p.quantita_prodotta_kg ? Number(p.quantita_prodotta_kg).toFixed(3) + ' kg' : '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Sales (recall targets) -->
        <div v-if="venditeRighe.length" class="result-card">
          <div class="result-header">
            <i class="pi pi-users result-icon customer" />
            <div>
              <div class="result-title">Clienti che hanno ricevuto il prodotto</div>
              <div class="result-sub">{{ venditeRighe.length }} vendita/e — questi clienti devono essere contattati in caso di richiamo</div>
            </div>
          </div>
          <table class="result-table">
            <thead>
              <tr><th>Cliente</th><th>N° Documento</th><th>Data Vendita</th><th>Prodotto</th><th>Lotto</th><th>Q.tà (kg)</th></tr>
            </thead>
            <tbody>
              <tr v-for="r in venditeRighe" :key="r.id" class="recall-row">
                <td><strong>{{ r.vendita?.cliente?.ragione_sociale ?? '—' }}</strong></td>
                <td>{{ r.vendita?.numero_documento ?? '—' }}</td>
                <td>{{ r.vendita?.data_documento ? formatDate(r.vendita.data_documento) : '—' }}</td>
                <td>{{ r.nome_prodotto }}</td>
                <td>{{ r.lotto || r.lotto_esterno || '—' }}</td>
                <td>{{ r.quantita_kg ? Number(r.quantita_kg).toFixed(3) : '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="venditeRighe.length" class="recall-warning">
          <i class="pi pi-exclamation-triangle" />
          <strong>Attenzione:</strong> In caso di richiamo attivo, contattare immediatamente i {{ venditeRighe.length }} clienti sopra elencati e notificare l'autorità competente (ASL/RASFF).
        </div>
      </template>
    </template>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';

const props = defineProps({
  q:            { type: String, default: '' },
  produzioni:   { type: Array, default: () => [] },
  venditeRighe: { type: Array, default: () => [] },
});

const query     = ref(props.q);
const searching = ref(false);

function search() {
  searching.value = true;
  router.get('/recall', { q: query.value }, {
    preserveState: true,
    onFinish: () => { searching.value = false; },
  });
}

function formatDate(d) {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('it-IT', { day:'2-digit', month:'2-digit', year:'numeric' });
}
</script>

<style scoped>
.page-header { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:1.5rem; }
.page-title { font-size:1.5rem; font-weight:700; color:#1e293b; margin:0 0 0.25rem 0; }
.page-sub { font-size:0.875rem; color:#64748b; margin:0; }
.search-card { background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:1.25rem 1.5rem; margin-bottom:1.5rem; }
.search-row { display:flex; gap:0.75rem; align-items:center; }
.mb-4 { margin-bottom:1rem; }
.empty-state { text-align:center; padding:3rem; color:#94a3b8; }
.empty-state i { font-size:2.5rem; display:block; margin-bottom:0.75rem; }
.result-card { background:#fff; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; }
.result-header { display:flex; align-items:center; gap:1rem; padding:1rem 1.5rem; border-bottom:1px solid #f1f5f9; }
.result-icon { font-size:1.4rem; width:40px; height:40px; border-radius:8px; display:flex; align-items:center; justify-content:center; }
.result-icon.production { background:#f0fdf4; color:#2a6941; }
.result-icon.customer { background:#fff7ed; color:#c2410c; }
.result-title { font-weight:700; color:#1e293b; font-size:0.95rem; }
.result-sub { font-size:0.8rem; color:#64748b; }
.result-table { width:100%; border-collapse:collapse; font-size:0.85rem; }
.result-table th { padding:0.5rem 1rem; background:#f8fafc; font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.04em; color:#64748b; border-bottom:1px solid #e2e8f0; text-align:left; }
.result-table td { padding:0.6rem 1rem; border-bottom:1px solid #f1f5f9; }
.recall-row td:first-child { color:#c2410c; }
.recall-warning { margin-top:1rem; background:#fff7ed; border:1px solid #fdba74; border-radius:8px; padding:1rem 1.25rem; font-size:0.875rem; color:#9a3412; display:flex; gap:0.75rem; align-items:flex-start; }
.recall-warning i { font-size:1.1rem; flex-shrink:0; margin-top:1px; }
</style>
