<template>
  <div class="app-shell">
    <!-- Mobile sidebar overlay -->
    <div v-if="sidebarOpen" class="sidebar-overlay" @click="sidebarOpen = false" />

    <!-- Sidebar -->
    <aside class="sidebar" :class="{ 'sidebar-open': sidebarOpen }">
      <div class="sidebar-logo">
        <img src="/favicon.png" alt="MIF" class="logo-img" />
        <div class="logo-text">
          <span class="logo-main">Marche Int. Food</span>
          <span class="logo-sub">S.r.l.</span>
        </div>
      </div>

      <nav class="sidebar-nav" @click="sidebarOpen = false">
        <Link href="/" :class="['nav-item', page.url === '/' ? 'active' : '']">
          <i class="pi pi-home" /> Dashboard
        </Link>

        <div class="nav-section-label">Anagrafica</div>
        <Link href="/fornitori"              :class="['nav-item', isActive('/fornitori')]">
          <i class="pi pi-building" /> Fornitori
        </Link>
        <Link href="/clienti"                :class="['nav-item', isActive('/clienti')]">
          <i class="pi pi-users" /> Clienti
        </Link>
        <Link href="/prodotti"               :class="['nav-item', isActive('/prodotti')]">
          <i class="pi pi-tag" /> Prodotti
        </Link>
        <Link href="/materie-prime"          :class="['nav-item', isActive('/materie-prime')]">
          <i class="pi pi-list" /> Materie Prime
        </Link>
        <template v-if="isAdmin">
          <Link href="/destinazione-ingredienti" :class="['nav-item', isActive('/destinazione-ingredienti')]">
            <i class="pi pi-directions" /> Dest. Ingredienti
          </Link>
        </template>

        <div class="nav-section-label">Acquisti & Vendite</div>
        <Link href="/acquisti"    :class="['nav-item', isActive('/acquisti')]">
          <i class="pi pi-download" /> Acquisti
        </Link>
        <Link href="/vendite"     :class="['nav-item', isActive('/vendite')]">
          <i class="pi pi-upload" /> Vendite
        </Link>
        <Link href="/bolle-reso"  :class="['nav-item', isActive('/bolle-reso')]">
          <i class="pi pi-reply" /> Bolle Reso
        </Link>
        <Link href="/note-credito" :class="['nav-item', isActive('/note-credito')]">
          <i class="pi pi-file-minus" /> Note di Credito
        </Link>

        <div class="nav-section-label">Imballaggi</div>
        <Link href="/imballaggi" :class="['nav-item', isActive('/imballaggi')]">
          <i class="pi pi-box" /> Imb. e Detergenti
        </Link>

        <div class="nav-section-label">Produzione</div>
        <Link href="/schede"     :class="['nav-item', isActive('/schede')]">
          <i class="pi pi-file-edit" /> Schede
        </Link>
        <Link href="/produzioni" :class="['nav-item', isActive('/produzioni')]">
          <i class="pi pi-cog" /> Produzioni
        </Link>
        <template v-if="isAdmin">
          <Link href="/flussi" :class="['nav-item', isActive('/flussi')]">
            <i class="pi pi-sitemap" /> Flussi di Lavorazione
          </Link>
        </template>

        <div class="nav-section-label">Tracciabilità</div>
        <Link href="/tracciabilita" :class="['nav-item', isActive('/tracciabilita')]" @click="sidebarOpen = false">
          <i class="pi pi-search" /> Ricerca Lotti
        </Link>
        <Link href="/recall" :class="['nav-item', isActive('/recall')]" @click="sidebarOpen = false">
          <i class="pi pi-exclamation-triangle" /> Rapporto Recall
        </Link>

        <div class="nav-section-label">Account</div>
        <Link href="/profilo" :class="['nav-item', isActive('/profilo')]">
          <i class="pi pi-lock" /> Cambia Password
        </Link>

        <template v-if="isAdmin">
          <div class="nav-section-label">Utilità</div>
          <Link href="/utenti" :class="['nav-item', isActive('/utenti')]">
            <i class="pi pi-user-edit" /> Gestione Utenti
          </Link>
          <Link href="/import" :class="['nav-item', isActive('/import')]">
            <i class="pi pi-database" /> Import Dati Storici
          </Link>
        </template>
      </nav>
    </aside>

    <!-- Main area -->
    <div class="main">
      <!-- Top header -->
      <header class="topbar">
        <div class="topbar-left">
          <button class="hamburger" @click="sidebarOpen = !sidebarOpen" aria-label="Menu">
            <i class="pi pi-bars" />
          </button>
          <span class="topbar-title">Marche International Food S.r.l.</span>
        </div>
        <div class="topbar-right">
          <span class="user-role-badge" :class="isAdmin ? 'badge-admin' : 'badge-operator'">
            {{ isAdmin ? 'Admin' : 'Operatore' }}
          </span>
          <span class="user-name">
            <i class="pi pi-user" />
            {{ auth?.name }}
          </span>
          <Link href="/logout" method="post" as="button" class="logout-btn">
            <i class="pi pi-sign-out" /> Esci
          </Link>
        </div>
      </header>

      <Toast position="top-right" />
      <ConfirmDialog />

      <main class="content">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import Toast from 'primevue/toast';
import ConfirmDialog from 'primevue/confirmdialog';
import { watchEffect } from 'vue';

const page = usePage();
const toast = useToast();
const sidebarOpen = ref(false);

const auth = computed(() => page.props.auth?.user);
const isAdmin = computed(() => auth.value?.role === 'admin');

watchEffect(() => {
  if (page.props.flash?.success) {
    toast.add({ severity: 'success', summary: 'Successo', detail: page.props.flash.success, life: 3500 });
  }
  if (page.props.flash?.error) {
    toast.add({ severity: 'error', summary: 'Errore', detail: page.props.flash.error, life: 4000 });
  }
});

function isActive(path) {
  return page.url.startsWith(path) ? 'active' : '';
}
</script>

<style scoped>
/* ── Shell ─────────────────────────────────────────────────────────────── */
.app-shell {
  display: flex;
  min-height: 100vh;
  font-family: 'Segoe UI', Arial, sans-serif;
}

/* ── Sidebar ──────────────────────────────────────────────────────────── */
.sidebar {
  width: 236px;
  min-width: 236px;
  background: #ffffff;
  border-right: 1px solid #dde8dd;
  display: flex;
  flex-direction: column;
  overflow-y: auto;
  flex-shrink: 0;
}

.sidebar-logo {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 1rem 1rem 0.9rem;
  border-bottom: 1px solid #e8f0e8;
  background: #ffffff;
}

.logo-img {
  width: 44px;
  height: 44px;
  object-fit: contain;
  flex-shrink: 0;
}

.logo-text {
  display: flex;
  flex-direction: column;
  line-height: 1.25;
}

.logo-main {
  font-size: 0.78rem;
  font-weight: 700;
  color: #1c3d28;
  letter-spacing: 0.01em;
}

.logo-sub {
  font-size: 0.68rem;
  color: #5a8c6a;
  font-weight: 500;
}

.sidebar-nav {
  padding: 0.5rem 0 1.5rem;
  flex: 1;
}

.nav-section-label {
  padding: 0.8rem 1rem 0.25rem;
  font-size: 0.6rem;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: #94a3b8;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0.45rem 1rem;
  font-size: 0.84rem;
  color: #4b5563;
  text-decoration: none;
  border-left: 3px solid transparent;
  transition: background 0.12s, color 0.12s;
  margin: 0 0.4rem;
  border-radius: 6px;
}

.nav-item i {
  font-size: 0.85rem;
  width: 16px;
  flex-shrink: 0;
  color: #6b7280;
}

.nav-item:hover {
  background: #f0faf2;
  color: #1c3d28;
}

.nav-item:hover i {
  color: #2a6941;
}

.nav-item.active {
  background: #edfaf2;
  color: #1c3d28;
  border-left-color: #2a6941;
  border-radius: 0 6px 6px 0;
  margin-left: 0;
  padding-left: calc(1rem + 0.4rem);
  font-weight: 600;
}

.nav-item.active i {
  color: #2a6941;
}

/* ── Main ─────────────────────────────────────────────────────────────── */
.main {
  flex: 1;
  background: #f4f7f4;
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  min-width: 0;
}

/* ── Topbar ───────────────────────────────────────────────────────────── */
.topbar {
  background: #fff;
  border-bottom: 1px solid #e5ece5;
  padding: 0 1.5rem;
  height: 54px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-shrink: 0;
}

.topbar-title {
  font-size: 0.875rem;
  font-weight: 700;
  color: #1c3d28;
  letter-spacing: 0.01em;
}

.topbar-right {
  display: flex;
  align-items: center;
  gap: 0.85rem;
}

.user-role-badge {
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  padding: 0.2rem 0.55rem;
  border-radius: 99px;
}

.badge-admin {
  background: #dcfce7;
  color: #166534;
}

.badge-operator {
  background: #fef9c3;
  color: #854d0e;
}

.user-name {
  font-size: 0.875rem;
  color: #374151;
  display: flex;
  align-items: center;
  gap: 0.4rem;
}

.user-name i {
  color: #2a6941;
  font-size: 0.85rem;
}

.logout-btn {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  background: none;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  padding: 0.3rem 0.75rem;
  font-size: 0.8rem;
  color: #6b7280;
  cursor: pointer;
  text-decoration: none;
  transition: border-color 0.13s, color 0.13s, background 0.13s;
}

.logout-btn:hover {
  border-color: #2a6941;
  color: #2a6941;
  background: #f0faf2;
}

/* ── Content ──────────────────────────────────────────────────────────── */
.content {
  padding: 2rem;
  flex: 1;
}

/* ── Hamburger (hidden on desktop) ───────────────────────────────────── */
.hamburger {
  display: none;
  background: none;
  border: none;
  cursor: pointer;
  padding: 0.25rem 0.5rem;
  font-size: 1.1rem;
  color: #1c3d28;
  margin-right: 0.5rem;
}

/* ── Sidebar overlay (mobile) ─────────────────────────────────────────── */
.sidebar-overlay {
  display: none;
}

/* ── Mobile breakpoint ─────────────────────────────────────────────────── */
@media (max-width: 768px) {
  .hamburger {
    display: block;
  }

  .sidebar {
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    z-index: 200;
    transform: translateX(-100%);
    transition: transform 0.22s ease;
    box-shadow: 4px 0 20px rgba(0,0,0,0.12);
  }

  .sidebar.sidebar-open {
    transform: translateX(0);
  }

  .sidebar-overlay {
    display: block;
    position: fixed;
    inset: 0;
    z-index: 199;
    background: rgba(0,0,0,0.35);
  }

  .content {
    padding: 1rem;
  }

  .topbar {
    padding: 0 1rem;
  }

  .topbar-title {
    font-size: 0.78rem;
  }

  .user-name span,
  .user-role-badge {
    display: none;
  }
}
</style>
