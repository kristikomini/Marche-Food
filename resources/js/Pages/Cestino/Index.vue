<template>
  <AppLayout>
    <div class="page-header">
      <div>
        <h1 class="page-title">Cestino</h1>
        <p class="page-sub">Documenti eliminati. Puoi ripristinarli o eliminarli definitivamente. Gli elementi ripristinati tornano visibili nelle rispettive sezioni.</p>
      </div>
    </div>

    <div class="result-card">
      <div class="table-wrap">
        <table class="result-table">
          <thead>
            <tr>
              <th>Tipo</th>
              <th>Riferimento</th>
              <th>Eliminato il</th>
              <th class="actions-col">Azioni</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in items" :key="`${item.tipo}-${item.id}`">
              <td><span class="tag">{{ item.titolo }}</span></td>
              <td class="mono">{{ item.etichetta || '—' }}</td>
              <td>{{ formatDateTime(item.deleted_at) }}</td>
              <td class="actions-col">
                <Button
                  icon="pi pi-replay"
                  label="Ripristina"
                  size="small"
                  outlined
                  severity="success"
                  @click="restore(item)"
                />
                <Button
                  icon="pi pi-trash"
                  label="Elimina definitivamente"
                  size="small"
                  outlined
                  severity="danger"
                  @click="confirmForceDelete(item)"
                />
              </td>
            </tr>
            <tr v-if="!items.length">
              <td colspan="4" class="empty">Il cestino è vuoto.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import { useConfirm } from 'primevue/useconfirm';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from 'primevue/button';

defineProps({
  items: { type: Array, default: () => [] },
});

const confirm = useConfirm();

function formatDateTime(d) {
  if (!d) return '—';
  return new Date(d).toLocaleString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function restore(item) {
  router.post(`/cestino/${item.tipo}/${item.id}/restore`, {}, { preserveScroll: true });
}

function confirmForceDelete(item) {
  confirm.require({
    message: `Eliminare definitivamente "${item.etichetta || item.titolo}"? L'operazione non è reversibile.`,
    header: 'Eliminazione definitiva',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Elimina definitivamente',
    rejectLabel: 'Annulla',
    acceptClass: 'p-button-danger',
    accept: () => router.delete(`/cestino/${item.tipo}/${item.id}`, { preserveScroll: true }),
  });
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
.actions-col { display:flex; gap:0.5rem; }
.empty { text-align:center; color:#94a3b8; font-style:italic; padding:1.5rem; }
</style>
