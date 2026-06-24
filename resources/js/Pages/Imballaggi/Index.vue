<template>
  <AppLayout>
    <div class="page-header">
      <h1 class="page-title">Imballaggi</h1>
    </div>

    <Tabs :value="activeTab" @update:value="activeTab = $event">
      <TabList>
        <Tab value="primari">Imb. Primari (MOCA)</Tab>
        <Tab value="detergenti">Detergenti</Tab>
      </TabList>

      <!-- ── IMBALLAGGI PRIMARI ─────────────────────────────────── -->
      <TabPanel value="primari">
        <div class="tab-toolbar">
          <IconField>
            <InputIcon class="pi pi-search" />
            <InputText
              v-model="searchP"
              placeholder="Cerca componente, lotto, DDT..."
              @input="debouncedP"
              style="width: 280px"
            />
          </IconField>
          <Link href="/imballaggi/primari/create">
            <Button label="Nuovo lotto" icon="pi pi-plus" size="small" />
          </Link>
        </div>

        <DataTable :value="primari.data" striped-rows size="small" class="mt-3">
          <Column header="Data entrata" style="width:110px">
            <template #body="{ data }">{{ formatDate(data.data_in) }}</template>
          </Column>
          <Column header="Fornitore">
            <template #body="{ data }">{{ data.fornitore?.ragione_sociale }}</template>
          </Column>
          <Column field="componente" header="Componente" />
          <Column field="codice_articolo" header="Cod. Articolo" style="width:120px">
            <template #body="{ data }"><span class="text-muted">{{ data.codice_articolo ?? '—' }}</span></template>
          </Column>
          <Column field="lotto" header="Lotto" style="width:120px">
            <template #body="{ data }"><span class="lotto-badge">{{ data.lotto ?? '—' }}</span></template>
          </Column>
          <Column header="Q.tà" style="width:90px">
            <template #body="{ data }">{{ data.quantita ? `${data.quantita} ${data.um ?? ''}` : '—' }}</template>
          </Column>
          <Column field="numero_ddt" header="N° DDT" style="width:110px">
            <template #body="{ data }"><span class="text-muted">{{ data.numero_ddt ?? '—' }}</span></template>
          </Column>
          <Column header="Data uscita" style="width:110px">
            <template #body="{ data }">
              <span :class="data.data_out ? 'text-out' : 'text-muted'">{{ data.data_out ? formatDate(data.data_out) : '—' }}</span>
            </template>
          </Column>
          <Column header="Azioni" style="width:90px">
            <template #body="{ data }">
              <div style="display:flex;gap:0.4rem">
                <Link :href="`/imballaggi/primari/${data.id}/edit`">
                  <Button icon="pi pi-pencil" size="small" outlined />
                </Link>
                <Button v-if="isAdmin" icon="pi pi-trash" size="small" outlined severity="danger" @click="confirmDeleteP(data)" />
              </div>
            </template>
          </Column>
          <template #empty><div class="empty-state">Nessun lotto trovato.</div></template>
        </DataTable>

        <div v-if="primari.last_page > 1" class="pagination">
          <Button icon="pi pi-chevron-left" outlined size="small" :disabled="!primari.prev_page_url" @click="router.visit(primari.prev_page_url)" />
          <span class="page-info">{{ primari.current_page }} / {{ primari.last_page }} ({{ primari.total }})</span>
          <Button icon="pi pi-chevron-right" outlined size="small" :disabled="!primari.next_page_url" @click="router.visit(primari.next_page_url)" />
        </div>
      </TabPanel>

      <!-- ── DETERGENTI ─────────────────────────────────────────── -->
      <TabPanel value="detergenti">
        <div class="tab-toolbar">
          <IconField>
            <InputIcon class="pi pi-search" />
            <InputText
              v-model="searchD"
              placeholder="Cerca componente, lotto, DDT..."
              @input="debouncedD"
              style="width: 280px"
            />
          </IconField>
          <Link href="/imballaggi/detergenti/create">
            <Button label="Nuovo lotto" icon="pi pi-plus" size="small" />
          </Link>
        </div>

        <DataTable :value="detergenti.data" striped-rows size="small" class="mt-3">
          <Column header="Data entrata" style="width:110px">
            <template #body="{ data }">{{ formatDate(data.data_in) }}</template>
          </Column>
          <Column header="Fornitore">
            <template #body="{ data }">{{ data.fornitore?.ragione_sociale }}</template>
          </Column>
          <Column field="componente" header="Componente" />
          <Column field="codice_articolo" header="Cod. Articolo" style="width:120px">
            <template #body="{ data }"><span class="text-muted">{{ data.codice_articolo ?? '—' }}</span></template>
          </Column>
          <Column field="lotto" header="Lotto" style="width:120px">
            <template #body="{ data }"><span class="lotto-badge">{{ data.lotto ?? '—' }}</span></template>
          </Column>
          <Column header="Q.tà" style="width:90px">
            <template #body="{ data }">{{ data.quantita ? `${data.quantita} ${data.um ?? ''}` : '—' }}</template>
          </Column>
          <Column header="Scadenza" style="width:110px">
            <template #body="{ data }">
              <span :class="isScaduto(data.scadenza) ? 'text-danger' : ''">{{ data.scadenza ? formatDate(data.scadenza) : '—' }}</span>
            </template>
          </Column>
          <Column field="numero_ddt" header="N° DDT" style="width:110px">
            <template #body="{ data }"><span class="text-muted">{{ data.numero_ddt ?? '—' }}</span></template>
          </Column>
          <Column header="Data uscita" style="width:110px">
            <template #body="{ data }">
              <span :class="data.data_out ? 'text-out' : 'text-muted'">{{ data.data_out ? formatDate(data.data_out) : '—' }}</span>
            </template>
          </Column>
          <Column header="Azioni" style="width:90px">
            <template #body="{ data }">
              <div style="display:flex;gap:0.4rem">
                <Link :href="`/imballaggi/detergenti/${data.id}/edit`">
                  <Button icon="pi pi-pencil" size="small" outlined />
                </Link>
                <Button v-if="isAdmin" icon="pi pi-trash" size="small" outlined severity="danger" @click="confirmDeleteD(data)" />
              </div>
            </template>
          </Column>
          <template #empty><div class="empty-state">Nessun lotto trovato.</div></template>
        </DataTable>

        <div v-if="detergenti.last_page > 1" class="pagination">
          <Button icon="pi pi-chevron-left" outlined size="small" :disabled="!detergenti.prev_page_url" @click="router.visit(detergenti.prev_page_url)" />
          <span class="page-info">{{ detergenti.current_page }} / {{ detergenti.last_page }} ({{ detergenti.total }})</span>
          <Button icon="pi pi-chevron-right" outlined size="small" :disabled="!detergenti.next_page_url" @click="router.visit(detergenti.next_page_url)" />
        </div>
      </TabPanel>
    </Tabs>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useConfirm } from 'primevue/useconfirm';
import AppLayout from '@/Layouts/AppLayout.vue';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanel from 'primevue/tabpanel';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';

const props = defineProps({
  primari:    Object,
  detergenti: Object,
  filters:    Object,
});

const confirm = useConfirm();
const page = usePage();
const isAdmin = computed(() => page.props.auth?.user?.role === 'admin');
const activeTab = ref(props.filters?.tab ?? 'primari');
const searchP = ref(props.filters?.search_p ?? '');
const searchD = ref(props.filters?.search_d ?? '');

function formatDate(d) {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function isScaduto(d) {
  return d && new Date(d) < new Date();
}

let tP = null, tD = null;
function debouncedP() { clearTimeout(tP); tP = setTimeout(() => applyFilters(), 400); }
function debouncedD() { clearTimeout(tD); tD = setTimeout(() => applyFilters(), 400); }

function applyFilters() {
  router.get('/imballaggi', {
    search_p: searchP.value,
    search_d: searchD.value,
    tab: activeTab.value,
  }, { preserveState: true, replace: true });
}

function confirmDeleteP(lotto) {
  confirm.require({
    message: `Eliminare il lotto "${lotto.componente}"?`,
    header: 'Conferma eliminazione',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Elimina',
    rejectLabel: 'Annulla',
    acceptClass: 'p-button-danger',
    accept: () => router.delete(`/imballaggi/primari/${lotto.id}`, {
      onSuccess: () => router.get('/imballaggi', { tab: 'primari' }),
    }),
  });
}

function confirmDeleteD(lotto) {
  confirm.require({
    message: `Eliminare il lotto "${lotto.componente}"?`,
    header: 'Conferma eliminazione',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Elimina',
    rejectLabel: 'Annulla',
    acceptClass: 'p-button-danger',
    accept: () => router.delete(`/imballaggi/detergenti/${lotto.id}`, {
      onSuccess: () => router.get('/imballaggi', { tab: 'detergenti' }),
    }),
  });
}
</script>

<style scoped>
.page-header { display: flex; align-items: center; margin-bottom: 1.5rem; }
.page-title { font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0; }
.tab-toolbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem; padding-top: 1rem; }
.lotto-badge { font-family: monospace; font-size: 0.82rem; background: #f1f5f9; padding: 0.15rem 0.4rem; border-radius: 4px; }
.text-muted { color: #94a3b8; }
.text-out { color: #64748b; }
.text-danger { color: #dc2626; font-weight: 600; }
.mt-3 { margin-top: 0.75rem; }
.pagination { display: flex; align-items: center; gap: 1rem; margin-top: 1rem; justify-content: center; }
.page-info { font-size: 0.875rem; color: #64748b; }
.empty-state { padding: 2rem; text-align: center; color: #94a3b8; }
</style>
