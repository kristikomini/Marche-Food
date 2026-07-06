<template>
  <AppLayout>
    <div class="page-header">
      <div>
        <h1 class="page-title">Rapporto di Richiamo (Recall)</h1>
        <p class="page-sub">Cerca un lotto per identificare i clienti impattati, poi apri un recall tracciato con notifiche.</p>
      </div>
    </div>

    <!-- Active recalls -->
    <div v-if="recalls.length" class="result-card mb-4">
      <div class="result-header">
        <i class="pi pi-megaphone result-icon recall" />
        <div>
          <div class="result-title">Recall registrati</div>
          <div class="result-sub">{{ recalls.length }} recall</div>
        </div>
      </div>
      <table class="result-table">
        <thead><tr><th>Lotto</th><th>Prodotto</th><th>Stato</th><th>Notifiche</th><th>Aperto il</th><th></th></tr></thead>
        <tbody>
          <tr v-for="r in recalls" :key="r.id">
            <td><strong>{{ r.lotto }}</strong></td>
            <td>{{ r.prodotto || '—' }}</td>
            <td><span :class="['tag', r.stato]">{{ statoLabel(r.stato) }}</span></td>
            <td>{{ r.notificate_count }} / {{ r.notifiche_count }}</td>
            <td>{{ formatDate(r.data_apertura) }}</td>
            <td><Link :href="`/recall/${r.id}`" class="link">Apri <i class="pi pi-arrow-right" /></Link></td>
          </tr>
        </tbody>
      </table>
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
      <div v-if="!produzioni.length && !venditeRighe.length" class="empty-state">
        <i class="pi pi-inbox" />
        <p>Nessun risultato per <strong>{{ q }}</strong></p>
      </div>

      <template v-else>
        <div v-if="produzioni.length" class="result-card mb-4">
          <div class="result-header">
            <i class="pi pi-cog result-icon production" />
            <div>
              <div class="result-title">Produzioni corrispondenti</div>
              <div class="result-sub">{{ produzioni.length }} lotto/i trovato/i</div>
            </div>
          </div>
          <table class="result-table">
            <thead><tr><th>Lotto Produzione</th><th>Data</th><th>Prodotto</th><th>Q.tà (kg)</th><th></th></tr></thead>
            <tbody>
              <tr v-for="p in produzioni" :key="p.id">
                <td><strong>{{ p.lotto_produzione }}</strong></td>
                <td>{{ formatDate(p.data_produzione) }}</td>
                <td>{{ p.scheda?.prodotto?.nome ?? '—' }}</td>
                <td>{{ p.quantita_prodotta_kg ? Number(p.quantita_prodotta_kg).toFixed(3) + ' kg' : '—' }}</td>
                <td><Button label="Apri recall" size="small" severity="danger" icon="pi pi-megaphone" @click="openRecallDialog(p.lotto_produzione, p.scheda?.prodotto?.nome)" /></td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="venditeRighe.length" class="result-card">
          <div class="result-header">
            <i class="pi pi-users result-icon customer" />
            <div>
              <div class="result-title">Clienti che hanno ricevuto il prodotto</div>
              <div class="result-sub">{{ venditeRighe.length }} vendita/e — da contattare in caso di richiamo</div>
            </div>
          </div>
          <table class="result-table">
            <thead><tr><th>Cliente</th><th>N° Documento</th><th>Data Vendita</th><th>Prodotto</th><th>Lotto</th><th>Q.tà (kg)</th></tr></thead>
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

    <!-- Open recall dialog -->
    <Dialog v-model:visible="dialogVisible" modal header="Apri recall" :style="{ width: '440px' }">
      <div class="dlg">
        <div class="field">
          <label>Lotto</label>
          <InputText v-model="form.lotto" fluid />
        </div>
        <div class="field">
          <label>Prodotto</label>
          <InputText v-model="form.prodotto" fluid />
        </div>
        <div class="field">
          <label>Motivo del richiamo *</label>
          <Textarea v-model="form.motivo" rows="3" fluid />
          <small v-if="form.errors.motivo" class="err">{{ form.errors.motivo }}</small>
        </div>
      </div>
      <template #footer>
        <Button label="Annulla" text @click="dialogVisible = false" />
        <Button label="Apri recall" severity="danger" icon="pi pi-megaphone" :loading="form.processing" @click="submitRecall" />
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import Dialog from 'primevue/dialog';
import Textarea from 'primevue/textarea';

const props = defineProps({
  q:            { type: String, default: '' },
  produzioni:   { type: Array, default: () => [] },
  venditeRighe: { type: Array, default: () => [] },
  recalls:      { type: Array, default: () => [] },
});

const query     = ref(props.q);
const searching = ref(false);
const dialogVisible = ref(false);

const form = useForm({ lotto: '', prodotto: '', motivo: '' });

function search() {
  searching.value = true;
  router.get('/recall', { q: query.value }, { preserveState: true, onFinish: () => { searching.value = false; } });
}
function openRecallDialog(lotto, prodotto) {
  form.reset();
  form.clearErrors();
  form.lotto = lotto || '';
  form.prodotto = prodotto || '';
  dialogVisible.value = true;
}
function submitRecall() {
  form.post('/recall', { onSuccess: () => { dialogVisible.value = false; } });
}
function statoLabel(s) { return { aperto: 'Aperto', in_corso: 'In corso', chiuso: 'Chiuso' }[s] ?? s; }
function formatDate(d) {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric' });
}
</script>

<style scoped>
.page-header { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:1.5rem; }
.page-title { font-size:1.5rem; font-weight:700; color:var(--ink); margin:0 0 0.25rem 0; }
.page-sub { font-size:0.875rem; color:var(--ink-2); margin:0; }
.search-card { background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:1.25rem 1.5rem; margin-bottom:1.5rem; }
.search-row { display:flex; gap:0.75rem; align-items:center; }
.mb-4 { margin-bottom:1rem; }
.empty-state { text-align:center; padding:3rem; color:var(--ink-3); }
.empty-state i { font-size:2.5rem; display:block; margin-bottom:0.75rem; }
.result-card { background:var(--surface); border:1px solid var(--border); border-radius:8px; overflow:hidden; margin-bottom:1rem; }
.result-header { display:flex; align-items:center; gap:1rem; padding:1rem 1.5rem; border-bottom:1px solid var(--border); }
.result-icon { font-size:1.4rem; width:40px; height:40px; border-radius:8px; display:flex; align-items:center; justify-content:center; }
.result-icon.production { background:var(--pine-tint); color:var(--pine); }
.result-icon.customer { background:var(--warn-tint); color:var(--warn); }
.result-icon.recall { background:var(--danger-tint); color:var(--danger); }
.result-title { font-weight:700; color:var(--ink); font-size:0.95rem; }
.result-sub { font-size:0.8rem; color:var(--ink-2); }
.result-table { width:100%; border-collapse:collapse; font-size:0.85rem; }
.result-table th { padding:0.5rem 1rem; background:var(--surface-2); font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.04em; color:var(--ink-2); border-bottom:1px solid var(--border); text-align:left; }
.result-table td { padding:0.6rem 1rem; border-bottom:1px solid var(--border); }
.recall-row td:first-child { color:var(--warn); }
.recall-warning { margin-top:1rem; background:var(--warn-tint); border:1px solid var(--warn); border-radius:8px; padding:1rem 1.25rem; font-size:0.875rem; color:var(--warn); display:flex; gap:0.75rem; align-items:flex-start; }
.recall-warning i { font-size:1.1rem; flex-shrink:0; margin-top:1px; }
.link { color:var(--pine); text-decoration:none; font-weight:600; font-size:0.82rem; }
.tag { font-size:0.72rem; font-weight:700; padding:0.15rem 0.55rem; border-radius:99px; }
.tag.aperto { background:var(--danger-tint); color:var(--danger); }
.tag.in_corso { background:var(--warn-tint); color:var(--warn); }
.tag.chiuso { background:var(--ok-tint); color:var(--ok); }
.dlg { display:flex; flex-direction:column; gap:0.9rem; }
.field { display:flex; flex-direction:column; gap:0.3rem; }
.field label { font-size:0.78rem; color:var(--ink-2); font-weight:600; }
.err { color:var(--danger); font-size:0.78rem; }
</style>
