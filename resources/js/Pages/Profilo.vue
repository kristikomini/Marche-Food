<template>
  <AppLayout>
    <div class="page-header">
      <h1 class="page-title">Il mio Profilo</h1>
    </div>

    <div class="profile-grid">
      <!-- Info card -->
      <div class="card info-card">
        <h2 class="section-title">Informazioni account</h2>
        <div class="info-row"><span class="info-label">Nome</span><span class="info-value">{{ user.name }}</span></div>
        <div class="info-row"><span class="info-label">Email</span><span class="info-value">{{ user.email }}</span></div>
        <div class="info-row">
          <span class="info-label">Ruolo</span>
          <Tag :value="user.role === 'admin' ? 'Amministratore' : 'Operatore'" :severity="user.role === 'admin' ? 'success' : 'warn'" />
        </div>
      </div>

      <!-- Change password card -->
      <div class="card">
        <h2 class="section-title">Cambia Password</h2>
        <div class="pw-form">
          <div class="field">
            <label>Password attuale *</label>
            <Password v-model="form.current_password" :feedback="false" toggle-mask :invalid="!!form.errors.current_password" fluid placeholder="Inserisci la password attuale" />
            <small class="error">{{ form.errors.current_password }}</small>
          </div>
          <div class="field">
            <label>Nuova password *</label>
            <Password v-model="form.password" :feedback="true" toggle-mask :invalid="!!form.errors.password" fluid placeholder="min. 8 caratteri" prompt-label="Scegli una password" weak-label="Debole" medium-label="Media" strong-label="Forte" />
            <small class="error">{{ form.errors.password }}</small>
          </div>
          <div class="field">
            <label>Conferma nuova password *</label>
            <Password v-model="form.password_confirmation" :feedback="false" toggle-mask fluid placeholder="Ripeti la nuova password" />
          </div>
          <Button label="Aggiorna password" icon="pi pi-check" @click="submit" :loading="form.processing" style="align-self:flex-start" />
        </div>
      </div>
    </div>

    <!-- Two-factor authentication card (admins only) -->
    <div v-if="user.role === 'admin'" class="card tfa-card">
      <h2 class="section-title">Autenticazione a due fattori (2FA)</h2>

      <!-- Disabled -->
      <template v-if="!twoFactor.enabled && !twoFactor.pending">
        <p class="tfa-desc">Aggiungi un secondo fattore di sicurezza: un codice temporaneo generato da un'app authenticator (Google Authenticator, Authy, ecc.).</p>
        <Button label="Attiva 2FA" icon="pi pi-lock" @click="enable" :loading="busy" />
      </template>

      <!-- Setup in progress -->
      <template v-else-if="twoFactor.pending">
        <p class="tfa-desc">1. Scansiona questo QR code con la tua app authenticator (oppure inserisci la chiave manuale). 2. Inserisci il codice a 6 cifre per confermare.</p>
        <div class="tfa-setup">
          <div class="tfa-qr" ref="qrEl"></div>
          <div class="tfa-setup-right">
            <div class="tfa-secret">
              <span class="tfa-secret-label">Chiave manuale</span>
              <code>{{ twoFactor.secret }}</code>
            </div>
            <div class="field">
              <label>Codice di verifica *</label>
              <InputText v-model="confirmForm.code" inputmode="numeric" maxlength="6" placeholder="000000" :invalid="!!confirmForm.errors.code" />
              <small class="error">{{ confirmForm.errors.code }}</small>
            </div>
            <div class="tfa-actions">
              <Button label="Conferma e attiva" icon="pi pi-check" @click="confirm" :loading="confirmForm.processing" />
              <Button label="Annulla" text severity="secondary" @click="disable" />
            </div>
          </div>
        </div>
      </template>

      <!-- Enabled -->
      <template v-else>
        <div class="tfa-enabled">
          <i class="pi pi-check-circle" />
          <span>2FA attiva sul tuo account.</span>
        </div>
        <div v-if="twoFactor.recoveryCodes && twoFactor.recoveryCodes.length" class="tfa-recovery">
          <div class="tfa-secret-label">Codici di recupero (ognuno utilizzabile una sola volta)</div>
          <div class="tfa-codes">
            <code v-for="(c, i) in twoFactor.recoveryCodes" :key="i">{{ c }}</code>
          </div>
        </div>
        <Button label="Disattiva 2FA" icon="pi pi-times" severity="danger" outlined @click="disable" :loading="busy" style="margin-top:1rem" />
      </template>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, ref, watch, nextTick, onMounted } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from 'primevue/button';
import Password from 'primevue/password';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';

const page = usePage();
const user = computed(() => page.props.auth?.user ?? {});
const twoFactor = computed(() => page.props.twoFactor ?? { enabled: false, pending: false });

const form = useForm({ current_password: '', password: '', password_confirmation: '' });
const confirmForm = useForm({ code: '' });
const busy = ref(false);
const qrEl = ref(null);

function submit() { form.put('/profilo/password', { onSuccess: () => form.reset() }); }

function enable() {
  busy.value = true;
  router.post('/profilo/2fa/enable', {}, { preserveScroll: true, onFinish: () => { busy.value = false; } });
}
function confirm() {
  confirmForm.post('/profilo/2fa/confirm', { preserveScroll: true, onSuccess: () => confirmForm.reset() });
}
function disable() {
  busy.value = true;
  router.delete('/profilo/2fa', { preserveScroll: true, onFinish: () => { busy.value = false; } });
}

// Render the enrollment QR using the vendored qrcode-generator library.
function loadQrLib() {
  return new Promise((resolve) => {
    if (typeof window.qrcode === 'function') { resolve(); return; }
    const s = document.createElement('script');
    s.src = '/vendor/qrcode-generator.js';
    s.onload = () => resolve();
    document.head.appendChild(s);
  });
}
async function renderQr() {
  if (!twoFactor.value.pending || !twoFactor.value.otpauthUri) return;
  await loadQrLib();
  await nextTick();
  if (!qrEl.value || typeof window.qrcode !== 'function') return;
  const qr = window.qrcode(0, 'M');
  qr.addData(twoFactor.value.otpauthUri);
  qr.make();
  qrEl.value.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 2, scalable: true });
}

onMounted(renderQr);
watch(() => twoFactor.value.pending, renderQr);
</script>

<style scoped>
.page-header { margin-bottom: 1.5rem; }
.page-title { font-size: 1.5rem; font-weight: 700; color: var(--ink); margin: 0; }
.profile-grid { display: grid; grid-template-columns: 320px 1fr; gap: 1.5rem; align-items: start; }
.card { background: var(--surface); border-radius: 8px; border: 1px solid var(--border); padding: 1.5rem; }
.tfa-card { margin-top: 1.5rem; }
.section-title { font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--ink-2); margin: 0 0 1.25rem 0; }
.info-row { display: flex; align-items: center; justify-content: space-between; padding: 0.65rem 0; border-bottom: 1px solid var(--border); }
.info-row:last-child { border-bottom: none; }
.info-label { font-size: 0.85rem; color: var(--ink-2); font-weight: 500; }
.info-value { font-size: 0.875rem; color: var(--ink); font-weight: 600; }
.pw-form { display: flex; flex-direction: column; gap: 1rem; }
.field { display: flex; flex-direction: column; gap: 0.3rem; }
.field label { font-size: 0.85rem; font-weight: 600; color: var(--ink-2); }
.error { color: var(--danger); font-size: 0.78rem; min-height: 1rem; }
.tfa-desc { font-size: 0.875rem; color: var(--ink-2); margin-bottom: 1rem; max-width: 640px; line-height: 1.5; }
.tfa-setup { display: flex; gap: 1.5rem; flex-wrap: wrap; align-items: flex-start; }
.tfa-qr { width: 160px; height: 160px; background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 8px; }
.tfa-qr :deep(svg) { width: 100%; height: 100%; }
.tfa-setup-right { flex: 1; min-width: 240px; display: flex; flex-direction: column; gap: 1rem; }
.tfa-secret-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--ink-3); font-weight: 700; display: block; margin-bottom: 0.3rem; }
.tfa-secret code { font-family: var(--font-mono); font-size: 0.95rem; background: var(--border); padding: 0.4rem 0.6rem; border-radius: 6px; display: inline-block; letter-spacing: 0.1em; }
.tfa-actions { display: flex; gap: 0.6rem; }
.tfa-enabled { display: flex; align-items: center; gap: 0.6rem; color: var(--ok); font-weight: 600; margin-bottom: 1rem; }
.tfa-enabled i { font-size: 1.2rem; }
.tfa-recovery { background: var(--surface-2); border: 1px solid var(--border); border-radius: 8px; padding: 1rem; }
.tfa-codes { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 0.5rem; margin-top: 0.5rem; }
.tfa-codes code { font-family: var(--font-mono); font-size: 0.85rem; background: var(--surface); border: 1px solid var(--border); border-radius: 4px; padding: 0.3rem 0.5rem; text-align: center; }
@media (max-width: 768px) { .profile-grid { grid-template-columns: 1fr; } }
</style>
