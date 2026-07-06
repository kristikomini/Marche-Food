<template>
  <AppLayout>
    <div class="page-header">
      <div>
        <h1 class="page-title">Centro Avvisi</h1>
        <p class="page-sub">Notifiche operative in tempo reale: scadenze, certificati e recall.</p>
      </div>
    </div>

    <div v-if="!items.length" class="empty-state">
      <i class="pi pi-check-circle" />
      <p>Nessun avviso attivo. Tutto in regola.</p>
    </div>

    <div v-else class="notif-list">
      <Link v-for="(n, i) in items" :key="i" :href="n.url" :class="['notif', n.livello]">
        <i :class="['pi', iconFor(n.livello), 'notif-icon']" aria-hidden="true" />
        <div class="notif-body">
          <div class="notif-title">{{ n.titolo }}</div>
          <div class="notif-detail">{{ n.messaggio }}</div>
        </div>
        <i class="pi pi-arrow-right" aria-hidden="true" />
      </Link>
    </div>
  </AppLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ items: { type: Array, default: () => [] } });
function iconFor(l){ return { danger:'pi-exclamation-circle', warning:'pi-clock', info:'pi-info-circle' }[l] || 'pi-bell'; }
</script>

<style scoped>
.page-header { margin-bottom: 1.5rem; }
.page-title { font-size: 1.5rem; font-weight: 700; color: var(--ink); margin: 0 0 0.25rem 0; }
.page-sub { font-size: 0.875rem; color: var(--ink-2); margin: 0; }
.empty-state { text-align: center; padding: 3rem; color: var(--ok); }
.empty-state i { font-size: 2.5rem; display: block; margin-bottom: 0.75rem; }
.notif-list { display: flex; flex-direction: column; gap: 0.75rem; max-width: 720px; }
.notif { display: flex; align-items: center; gap: 1rem; background: var(--surface); border: 1px solid var(--border); border-left-width: 4px; border-radius: 8px; padding: 1rem 1.25rem; text-decoration: none; color: var(--ink); }
.notif:hover { background: var(--surface-2); }
.notif.danger { border-left-color: var(--danger); }
.notif.warning { border-left-color: var(--warn); }
.notif.info { border-left-color: var(--info); }
.notif-icon { font-size: 1.3rem; width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; }
.notif.danger .notif-icon { background: var(--danger-tint); color: var(--danger); }
.notif.warning .notif-icon { background: var(--warn-tint); color: var(--warn); }
.notif.info .notif-icon { background: var(--info-tint); color: var(--info); }
.notif-body { flex: 1; }
.notif-title { font-weight: 700; }
.notif-detail { font-size: 0.85rem; color: var(--ink-2); }
.notif > .pi-arrow-right { color: var(--ink-3); }
</style>
