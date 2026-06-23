<template>
  <AppLayout>
    <div class="page-header">
      <h1 class="page-title">Import Dati Storici</h1>
    </div>
    <p class="desc">Importa acquisti e vendite storiche da file CSV (separatore: punto e virgola).</p>

    <div class="cards">
      <!-- ACQUISTI -->
      <div class="import-card">
        <div class="card-icon-wrap acquisti"><i class="pi pi-download" /></div>
        <h2 class="card-title">Acquisti</h2>
        <p class="card-desc">Importa DDT/fatture fornitore con righe articolo e lotti.</p>
        <div class="template-row">
          <a href="/import/template-acquisti" class="template-link"><i class="pi pi-file-export" /> Scarica template CSV</a>
        </div>
        <form @submit.prevent="submitAcquisti" class="upload-form">
          <FileUpload
            mode="basic"
            name="file"
            accept=".csv,.txt"
            :auto="false"
            choose-label="Scegli file CSV"
            @select="onSelectAcquisti"
          />
          <Button type="submit" label="Importa Acquisti" icon="pi pi-upload" :loading="formAcquisti.processing" :disabled="!fileAcquisti" class="mt-2" />
        </form>
      </div>

      <!-- VENDITE -->
      <div class="import-card">
        <div class="card-icon-wrap vendite"><i class="pi pi-upload" /></div>
        <h2 class="card-title">Vendite</h2>
        <p class="card-desc">Importa DDT/fatture cliente con righe articolo e lotti.</p>
        <div class="template-row">
          <a href="/import/template-vendite" class="template-link"><i class="pi pi-file-export" /> Scarica template CSV</a>
        </div>
        <form @submit.prevent="submitVendite" class="upload-form">
          <FileUpload
            mode="basic"
            name="file"
            accept=".csv,.txt"
            :auto="false"
            choose-label="Scegli file CSV"
            @select="onSelectVendite"
          />
          <Button type="submit" label="Importa Vendite" icon="pi pi-upload" :loading="formVendite.processing" :disabled="!fileVendite" class="mt-2" />
        </form>
      </div>
    </div>

    <!-- INSTRUCTIONS -->
    <div class="instructions">
      <h3 class="instr-title">Formato CSV Acquisti</h3>
      <div class="csv-table-wrap">
        <table class="csv-table">
          <thead>
            <tr>
              <th>fornitore_codice</th>
              <th>numero_documento</th>
              <th>data_documento</th>
              <th>tipo_documento</th>
              <th>nome_prodotto</th>
              <th>quantita_kg</th>
              <th>quantita_pz</th>
              <th>lotto</th>
              <th>lotto_esterno</th>
              <th>scadenza</th>
              <th>data_in</th>
              <th>note_documento</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>FOR001</td>
              <td>DDT/2024/001</td>
              <td>01/01/2024</td>
              <td>DDT</td>
              <td>Tonno al naturale</td>
              <td>100.000</td>
              <td class="muted">—</td>
              <td>A2024001</td>
              <td class="muted">—</td>
              <td>31/12/2025</td>
              <td>01/01/2024</td>
              <td class="muted">—</td>
            </tr>
          </tbody>
        </table>
      </div>

      <h3 class="instr-title mt-4">Formato CSV Vendite</h3>
      <div class="csv-table-wrap">
        <table class="csv-table">
          <thead>
            <tr>
              <th>cliente_codice</th>
              <th>numero_documento</th>
              <th>data_documento</th>
              <th>tipo_documento</th>
              <th>nome_prodotto</th>
              <th>pezzatura_gr</th>
              <th>quantita_kg</th>
              <th>quantita_pz</th>
              <th>lotto</th>
              <th>lotto_esterno</th>
              <th>scadenza</th>
              <th>note_documento</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>CLI001</td>
              <td>DDT/2024/001</td>
              <td>01/01/2024</td>
              <td>DDT</td>
              <td>Tonno all'olio 800g</td>
              <td>800</td>
              <td>50.000</td>
              <td>62</td>
              <td>LP2024-001</td>
              <td class="muted">—</td>
              <td>31/12/2025</td>
              <td class="muted">—</td>
            </tr>
          </tbody>
        </table>
      </div>

      <ul class="instr-list">
        <li>Date nel formato <strong>DD/MM/YYYY</strong> o <strong>YYYY-MM-DD</strong></li>
        <li>Separatore decimale: <strong>punto</strong> o <strong>virgola</strong></li>
        <li>Righe multiple con stesso documento/fornitore vengono raggruppate in un unico acquisto</li>
        <li>Il codice fornitore/cliente deve esistere nell'anagrafica</li>
      </ul>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from 'primevue/button';
import FileUpload from 'primevue/fileupload';

const fileAcquisti = ref(null);
const fileVendite  = ref(null);
const formAcquisti = useForm({ file: null });
const formVendite  = useForm({ file: null });

function onSelectAcquisti(e) { fileAcquisti.value = e.files[0]; }
function onSelectVendite(e)  { fileVendite.value  = e.files[0]; }

function submitAcquisti() {
  formAcquisti.file = fileAcquisti.value;
  formAcquisti.post('/import/acquisti', { forceFormData: true });
}

function submitVendite() {
  formVendite.file = fileVendite.value;
  formVendite.post('/import/vendite', { forceFormData: true });
}
</script>

<style scoped>
.page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:0.5rem; }
.page-title { font-size:1.5rem; font-weight:700; color:#1e293b; margin:0; }
.desc { color:#64748b; margin:0 0 1.5rem 0; font-size:0.875rem; }
.cards { display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; margin-bottom:2rem; }
.import-card { background:#fff; border-radius:8px; border:1px solid #e2e8f0; padding:1.5rem; }
.card-icon-wrap { width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; margin-bottom:1rem; font-size:1.5rem; }
.card-icon-wrap.acquisti { background:#dbeafe; color:#1d4ed8; }
.card-icon-wrap.vendite  { background:#dcfce7; color:#15803d; }
.card-title { font-size:1.1rem; font-weight:700; color:#1e293b; margin:0 0 0.5rem 0; }
.card-desc { color:#64748b; font-size:0.875rem; margin:0 0 1rem 0; }
.template-row { margin-bottom:1rem; }
.template-link { color:#1d4ed8; font-size:0.875rem; text-decoration:none; display:inline-flex; align-items:center; gap:0.3rem; }
.template-link:hover { text-decoration:underline; }
.upload-form { display:flex; flex-direction:column; gap:0.5rem; }
.mt-2 { margin-top:0.5rem; }
.instructions { background:#fff; border-radius:8px; border:1px solid #e2e8f0; padding:1.5rem; }
.instr-title { font-size:0.9rem; font-weight:700; color:#374151; margin:0 0 0.5rem 0; }
.mt-4 { margin-top:1.25rem; }
.csv-table-wrap { overflow-x:auto; border-radius:6px; border:1px solid #e2e8f0; margin-bottom:0.5rem; }
.csv-table { width:100%; border-collapse:collapse; font-size:0.78rem; white-space:nowrap; }
.csv-table thead tr { background:#f1f5f9; }
.csv-table th { padding:0.45rem 0.75rem; text-align:left; font-weight:700; color:#475569; font-size:0.7rem; text-transform:uppercase; letter-spacing:0.04em; border-bottom:2px solid #e2e8f0; border-right:1px solid #e2e8f0; }
.csv-table th:last-child { border-right:none; }
.csv-table td { padding:0.45rem 0.75rem; color:#1e293b; border-right:1px solid #f1f5f9; background:#fff; }
.csv-table td:last-child { border-right:none; }
.csv-table td.muted { color:#94a3b8; }
.instr-list { margin:1rem 0 0 0; padding-left:1.25rem; color:#475569; font-size:0.875rem; display:flex; flex-direction:column; gap:0.3rem; }
</style>
