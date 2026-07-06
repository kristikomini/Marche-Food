<template>
  <div class="app-shell">
    <a href="#main-content" class="skip-link">Vai al contenuto</a>
    <!-- Mobile sidebar overlay -->
    <div v-if="sidebarOpen" class="sidebar-overlay" @click="sidebarOpen = false" />

    <!-- Sidebar -->
    <aside class="sidebar" :class="{ 'sidebar-open': sidebarOpen }">
      <div class="sidebar-logo">
        <img src="/favicon.png" alt="Marche International Food" class="logo-img" />
        <div class="logo-text">
          <span class="logo-main">Marche Int. Food</span>
          <span class="logo-sub">S.r.l.</span>
        </div>
      </div>

      <nav class="sidebar-nav" aria-label="Navigazione principale" @click="sidebarOpen = false">
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
        <Link href="/produzioni/kiosk" :class="['nav-item', isActive('/produzioni/kiosk')]">
          <i class="pi pi-tablet" /> Kiosk Produzione
        </Link>
        <template v-if="isAdmin">
          <Link href="/flussi" :class="['nav-item', isActive('/flussi')]">
            <i class="pi pi-sitemap" /> Flussi di Lavorazione
          </Link>
        </template>

        <div class="nav-section-label">Tracciabilità</div>
        <Link href="/cerca" :class="['nav-item', isActive('/cerca')]" @click="sidebarOpen = false">
          <i class="pi pi-search" /> Ricerca Globale
        </Link>
        <Link href="/tracciabilita" :class="['nav-item', isActive('/tracciabilita')]" @click="sidebarOpen = false">
          <i class="pi pi-search" /> Ricerca Lotti
        </Link>
        <Link href="/recall" :class="['nav-item', isActive('/recall')]" @click="sidebarOpen = false">
          <i class="pi pi-exclamation-triangle" /> Rapporto Recall
        </Link>

        <div class="nav-section-label">Reportistica</div>
        <Link href="/report" :class="['nav-item', isActive('/report')]">
          <i class="pi pi-chart-bar" /> Report Gestionale
        </Link>
        <Link href="/magazzino" :class="['nav-item', isActive('/magazzino')]">
          <i class="pi pi-inbox" /> Giacenze Magazzino
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
          <Link href="/audit" :class="['nav-item', isActive('/audit')]">
            <i class="pi pi-history" /> Log Attività
          </Link>
          <Link href="/cestino" :class="['nav-item', isActive('/cestino')]">
            <i class="pi pi-trash" /> Cestino
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
          <form class="global-search" @submit.prevent="globalSearch" role="search">
            <i class="pi pi-search" aria-hidden="true" />
            <input v-model="globalQuery" type="search" placeholder="Cerca..." aria-label="Ricerca globale" />
          </form>
        </div>
        <div class="topbar-right">
          <button class="theme-toggle" type="button" :aria-label="isDark ? 'Attiva tema chiaro' : 'Attiva tema scuro'" @click="toggleTheme">
            <i :class="isDark ? 'pi pi-sun' : 'pi pi-moon'" aria-hidden="true" />
          </button>
          <div class="notif-wrap">
            <button class="notif-bell" type="button" :aria-label="'Notifiche: ' + notificheCount + ' non lette'" aria-haspopup="true" :aria-expanded="notifOpen ? 'true' : 'false'" @click="notifOpen = !notifOpen">
              <i class="pi pi-bell" aria-hidden="true" />
              <span v-if="notificheCount > 0" class="notif-badge">{{ notificheCount }}</span>
            </button>
            <div v-if="notifOpen" class="notif-backdrop" @click="notifOpen = false" />
            <div v-if="notifOpen" class="notif-dropdown" role="menu">
              <div class="notif-dd-head">
                <span>Notifiche</span>
                <button v-if="notifiche.length" type="button" class="notif-dd-clear" @click="dismissAll">Segna tutte lette</button>
              </div>
              <div v-if="!notifiche.length" class="notif-dd-empty">Nessuna notifica</div>
              <ul v-else class="notif-dd-list">
                <li v-for="n in notifiche" :key="n.id" :class="['notif-dd-item', n.livello]">
                  <Link :href="n.url || '/notifiche'" class="notif-dd-link" @click="notifOpen = false">
                    <span class="notif-dd-title">{{ n.titolo }}</span>
                    <span class="notif-dd-msg">{{ n.messaggio }}</span>
                  </Link>
                  <button type="button" class="notif-dd-x" aria-label="Ignora notifica" @click.stop="dismiss(n)"><i class="pi pi-times" aria-hidden="true" /></button>
                </li>
              </ul>
              <Link href="/notifiche" class="notif-dd-all" @click="notifOpen = false">Vedi tutte</Link>
            </div>
          </div>
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

      <main id="main-content" class="content" tabindex="-1">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import Toast from 'primevue/toast';
import ConfirmDialog from 'primevue/confirmdialog';
import { watchEffect } from 'vue';

const page = usePage();
const toast = useToast();
const sidebarOpen = ref(false);
const globalQuery = ref('');
function globalSearch() {
  if (globalQuery.value.trim().length < 2) return;
  router.get('/cerca', { q: globalQuery.value }, { preserveState: true });
}

const auth = computed(() => page.props.auth?.user);
const isAdmin = computed(() => auth.value?.role === 'admin');

// Light/dark theme — `.dark` on <html>, persisted; pre-applied in app.blade.php to avoid FOUC.
const isDark = ref(typeof document !== 'undefined' && document.documentElement.classList.contains('dark'));
function toggleTheme() {
  isDark.value = !isDark.value;
  document.documentElement.classList.toggle('dark', isDark.value);
  try { localStorage.setItem('mif-theme', isDark.value ? 'dark' : 'light'); } catch (e) { /* ignore */ }
}
const notificheCount = computed(() => page.props.notificheCount ?? 0);
const notifiche = computed(() => page.props.notifiche ?? []);
const notifOpen = ref(false);
function dismiss(n) { router.post(`/notifiche/${n.id}/dismiss`, {}, { preserveScroll: true, preserveState: true }); }
function dismissAll() { router.post('/notifiche/dismiss-all', {}, { preserveScroll: true, preserveState: true, onSuccess: () => { notifOpen.value = false; } }); }

watchEffect(() => {
  if (page.props.flash?.success) {
    toast.add({ severity: 'success', summary: 'Successo', detail: page.props.flash.success, life: 3500 });
  }
  if (page.props.flash?.error) {
    toast.add({ severity: 'error', summary: 'Errore', detail: page.props.flash.error, life: 4000 });
  }
  // Optimistic-locking conflict (surfaced globally so every form benefits)
  if (page.props.errors?.updated_at) {
    toast.add({ severity: 'warn', summary: 'Conflitto di modifica', detail: page.props.errors.updated_at, life: 6000 });
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
  font-family: var(--font-sans);
}

/* ── Sidebar ──────────────────────────────────────────────────────────── */
.sidebar {
  width: 236px;
  min-width: 236px;
  background: var(--surface);
  border-right: 1px solid var(--border);
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
  border-bottom: 1px solid var(--border);
  background: var(--surface);
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
  font-family: var(--font-display);
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--pine-strong);
  letter-spacing: 0.005em;
}

.logo-sub {
  font-size: 0.68rem;
  color: var(--ink-3);
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
  color: var(--ink-3);
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0.45rem 1rem;
  font-size: 0.84rem;
  color: var(--ink-2);
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
  color: var(--ink-3);
}

.nav-item:hover {
  background: var(--pine-tint);
  color: var(--pine-strong);
}

.nav-item:hover i {
  color: var(--pine);
}

.nav-item.active {
  background: var(--pine-tint);
  color: var(--pine-strong);
  border-left-color: var(--pine);
  border-radius: 0 6px 6px 0;
  margin-left: 0;
  padding-left: calc(1rem + 0.4rem);
  font-weight: 600;
}

.nav-item.active i {
  color: var(--pine);
}

/* ── Main ─────────────────────────────────────────────────────────────── */
.main {
  flex: 1;
  background: var(--ground);
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  min-width: 0;
}

/* ── Topbar ───────────────────────────────────────────────────────────── */
.topbar {
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  padding: 0 1.5rem;
  height: 54px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-shrink: 0;
}

.topbar-title {
  font-family: var(--font-display);
  font-size: 0.98rem;
  font-weight: 600;
  color: var(--pine-strong);
  letter-spacing: 0.005em;
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
  background: var(--ok-tint);
  color: var(--ok);
}

.badge-operator {
  background: var(--warn-tint);
  color: var(--warn);
}

.user-name {
  font-size: 0.875rem;
  color: var(--ink-2);
  display: flex;
  align-items: center;
  gap: 0.4rem;
}

.user-name i {
  color: var(--pine);
  font-size: 0.85rem;
}

.logout-btn {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  background: none;
  border: 1px solid var(--border-strong);
  border-radius: 6px;
  padding: 0.3rem 0.75rem;
  font-size: 0.8rem;
  color: var(--ink-2);
  cursor: pointer;
  text-decoration: none;
  transition: border-color 0.13s, color 0.13s, background 0.13s;
}

.logout-btn:hover {
  border-color: var(--pine);
  color: var(--pine);
  background: var(--pine-tint);
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
  color: var(--ink);
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

.global-search { display:flex; align-items:center; gap:0.4rem; background:var(--surface-2); border:1px solid var(--border); border-radius:6px; padding:0.25rem 0.6rem; margin-left:1rem; }
.global-search i { color:var(--ink-3); font-size:0.8rem; }
.global-search input { border:none; background:transparent; outline:none; font-size:0.82rem; width:150px; color:var(--ink-2); }
@media (max-width:768px){ .global-search { display:none; } }

.skip-link { position:absolute; left:-999px; top:0; z-index:1000; background:var(--pine-strong); color:#fff; padding:0.5rem 1rem; border-radius:0 0 6px 0; }
.skip-link:focus { left:0; }


.theme-toggle { display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; border-radius:8px; color:var(--ink-2); background:none; border:none; cursor:pointer; font-size:0.95rem; }
.theme-toggle:hover { background:var(--pine-tint); color:var(--pine); }

.notif-wrap { position:relative; }
.notif-bell { position:relative; display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; border-radius:8px; color:var(--ink-2); background:none; border:none; cursor:pointer; }
.notif-bell:hover { background:var(--pine-tint); color:var(--pine); }
.notif-backdrop { position:fixed; inset:0; z-index:150; }
.notif-dropdown { position:absolute; right:0; top:44px; width:340px; max-height:70vh; overflow-y:auto; background:var(--surface); border:1px solid var(--border); border-radius:10px; box-shadow:var(--shadow-2); z-index:151; }
.notif-dd-head { display:flex; justify-content:space-between; align-items:center; padding:0.75rem 1rem; border-bottom:1px solid var(--border); font-weight:700; font-size:0.85rem; color:var(--ink); }
.notif-dd-clear { background:none; border:none; color:var(--pine); font-size:0.75rem; cursor:pointer; }
.notif-dd-empty { padding:1.5rem; text-align:center; color:var(--ink-3); font-size:0.85rem; }
.notif-dd-list { list-style:none; margin:0; padding:0; }
.notif-dd-item { display:flex; align-items:flex-start; gap:0.5rem; padding:0.6rem 0.85rem; border-bottom:1px solid var(--border); border-left:3px solid transparent; }
.notif-dd-item.danger { border-left-color:var(--danger); }
.notif-dd-item.warning { border-left-color:var(--warn); }
.notif-dd-item.info { border-left-color:var(--info); }
.notif-dd-link { flex:1; text-decoration:none; display:flex; flex-direction:column; gap:2px; }
.notif-dd-title { font-size:0.82rem; font-weight:700; color:var(--ink); }
.notif-dd-msg { font-size:0.76rem; color:var(--ink-2); }
.notif-dd-x { background:none; border:none; color:var(--ink-3); cursor:pointer; padding:2px; }
.notif-dd-x:hover { color:var(--danger); }
.notif-dd-all { display:block; text-align:center; padding:0.65rem; font-size:0.8rem; color:var(--pine); text-decoration:none; border-top:1px solid var(--border); font-weight:600; }
.notif-badge { position:absolute; top:2px; right:2px; min-width:16px; height:16px; padding:0 4px; background:var(--danger); color:#fff; font-size:0.62rem; font-weight:700; border-radius:99px; display:flex; align-items:center; justify-content:center; }
</style>
