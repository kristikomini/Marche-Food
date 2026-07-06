<template>
  <AppLayout>
    <div class="page-header">
      <div>
        <h1 class="page-title">Ricerca globale</h1>
        <p class="page-sub">Cerca fornitori, clienti, prodotti, materie prime e lotti.</p>
      </div>
    </div>

    <div class="search-card">
      <form @submit.prevent="run">
        <div class="search-row">
          <IconField style="flex:1">
            <InputIcon class="pi pi-search" />
            <InputText v-model="query" placeholder="Cerca in tutto il sistema..." fluid autofocus @keydown.enter="run" />
          </IconField>
          <Button type="submit" label="Cerca" icon="pi pi-search" :loading="loading" />
        </div>
      </form>
    </div>

    <template v-if="q">
      <div v-if="!gruppi.length" class="empty-state">
        <i class="pi pi-inbox" />
        <p>Nessun risultato per <strong>{{ q }}</strong></p>
      </div>

      <div v-for="(g, gi) in gruppi" :key="gi" class="result-card">
        <div class="result-header">
          <i :class="['pi', g.icona, 'result-icon']" />
          <div class="result-title">{{ g.tipo }}</div>
        </div>
        <ul class="hit-list">
          <li v-for="(it, i) in g.items" :key="i">
            <Link :href="it.url" class="hit">
              <span class="hit-label">{{ it.label }}</span>
              <span v-if="it.sub" class="hit-sub">{{ it.sub }}</span>
              <i class="pi pi-arrow-right" />
            </Link>
          </li>
        </ul>
      </div>
    </template>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';

const props = defineProps({
  q:      { type: String, default: '' },
  gruppi: { type: Array, default: () => [] },
});

const query = ref(props.q);
const loading = ref(false);

function run() {
  loading.value = true;
  router.get('/cerca', { q: query.value }, { preserveState: true, onFinish: () => { loading.value = false; } });
}
</script>

<style scoped>
.page-header { margin-bottom:1.5rem; }
.page-title { font-size:1.5rem; font-weight:700; color:var(--ink); margin:0 0 0.25rem 0; }
.page-sub { font-size:0.875rem; color:var(--ink-2); margin:0; }
.search-card { background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:1.25rem 1.5rem; margin-bottom:1.5rem; }
.search-row { display:flex; gap:0.75rem; align-items:center; }
.empty-state { text-align:center; padding:3rem; color:var(--ink-3); }
.empty-state i { font-size:2.5rem; display:block; margin-bottom:0.75rem; }
.result-card { background:var(--surface); border:1px solid var(--border); border-radius:8px; overflow:hidden; margin-bottom:1rem; }
.result-header { display:flex; align-items:center; gap:0.8rem; padding:0.8rem 1.25rem; border-bottom:1px solid var(--border); }
.result-icon { font-size:1rem; width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; background:var(--pine-tint); color:var(--pine); }
.result-title { font-weight:700; color:var(--ink); font-size:0.9rem; }
.hit-list { list-style:none; margin:0; padding:0; }
.hit { display:flex; align-items:center; gap:0.6rem; padding:0.6rem 1.25rem; border-bottom:1px solid var(--border); text-decoration:none; color:var(--ink-2); }
.hit:hover { background:var(--pine-tint); }
.hit-label { font-weight:600; color:var(--ink); }
.hit-sub { font-size:0.8rem; color:var(--ink-3); }
.hit i { margin-left:auto; color:var(--ink-3); font-size:0.8rem; }
</style>
