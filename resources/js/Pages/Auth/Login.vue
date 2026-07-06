<template>
  <div class="login-page">
    <!-- Brand panel — the hills of Le Marche at dawn -->
    <div class="brand-panel">
      <svg class="brand-scene" viewBox="0 0 1200 500" preserveAspectRatio="xMidYMax slice" aria-hidden="true">
        <defs>
          <radialGradient id="sun-glow" cx="50%" cy="50%" r="50%">
            <stop offset="0%" stop-color="#f0c869" stop-opacity="0.42" />
            <stop offset="100%" stop-color="#f0c869" stop-opacity="0" />
          </radialGradient>
        </defs>
        <circle cx="830" cy="262" r="150" fill="url(#sun-glow)" />
        <circle cx="830" cy="262" r="52" fill="#e6b453" />
        <!-- distant, hazy hill -->
        <path d="M0,250 C220,190 380,280 620,220 S1000,180 1200,240 L1200,500 L0,500 Z" fill="#3f7d68" opacity="0.55" />
        <!-- mid hill -->
        <path d="M0,300 C260,250 460,330 720,278 S1060,268 1200,308 L1200,500 L0,500 Z" fill="#265a49" />
        <!-- front hill -->
        <path d="M0,360 C300,326 520,392 820,350 S1100,356 1200,378 L1200,500 L0,500 Z" fill="#173a2c" />
      </svg>
      <div class="brand-text">
        <span class="brand-mark">mif</span>
        <h1 class="brand-name">Marche International Food</h1>
        <p class="brand-tagline-it">Dalle Marche alla tua tavola</p>
        <p class="brand-system">Sistema di Tracciabilità HACCP</p>
      </div>
    </div>

    <!-- Login form panel -->
    <div class="form-panel">
      <div class="login-card">
        <div class="card-logo">
          <img src="/favicon.png" alt="Marche International Food" />
        </div>
        <h2 class="card-title">Accedi</h2>
        <p class="card-subtitle">Inserisci le tue credenziali per continuare</p>

        <div v-if="$page.props.flash?.status" class="flash-status">
          {{ $page.props.flash.status }}
        </div>

        <form @submit.prevent="submit" class="form">
          <div class="field">
            <label for="email">Indirizzo email</label>
            <InputText
              id="email"
              v-model="form.email"
              type="email"
              autocomplete="username"
              placeholder="nome@marche.it"
              :invalid="!!form.errors.email"
              fluid
            />
            <small class="error">{{ form.errors.email }}</small>
          </div>

          <div class="field">
            <label for="password">Password</label>
            <Password
              id="password"
              v-model="form.password"
              :feedback="false"
              toggle-mask
              autocomplete="current-password"
              placeholder="••••••••"
              :invalid="!!form.errors.password"
              fluid
            />
            <small class="error">{{ form.errors.password }}</small>
          </div>

          <div class="field-row">
            <div class="remember">
              <Checkbox v-model="form.remember" input-id="remember" binary />
              <label for="remember">Ricordami</label>
            </div>
            <a href="/forgot-password" class="forgot-link">Password dimenticata?</a>
          </div>

          <Button
            type="submit"
            label="Accedi"
            icon="pi pi-sign-in"
            :loading="form.processing"
            fluid
            class="submit-btn"
          />
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Checkbox from 'primevue/checkbox';
import Button from 'primevue/button';

const form = useForm({
  email:    '',
  password: '',
  remember: false,
});

function submit() {
  form.post('/login', {
    onFinish: () => form.reset('password'),
  });
}
</script>

<style scoped>
* { box-sizing: border-box; }

.login-page {
  display: flex;
  min-height: 100vh;
  font-family: var(--font-sans);
}

/* ── Brand Panel ─────────────────────────────── */
.brand-panel {
  flex: 1;
  /* Literal brand greens so the panel stays dark-on-dawn regardless of app theme */
  background: linear-gradient(165deg, #123528 0%, #1f5040 62%, #2e6b57 100%);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: flex-start;
  padding: 3rem;
  padding-top: 21vh;
  position: relative;
  overflow: hidden;
}

.brand-scene {
  position: absolute;
  bottom: 0;
  left: 0;
  width: 100%;
  height: 62%;
}

.brand-text {
  position: relative;
  z-index: 10;
  text-align: center;
  color: #fff;
  margin-bottom: 2rem;
}

.brand-mark {
  display: inline-block;
  font-family: var(--font-display);
  font-weight: 600;
  font-size: 1.15rem;
  letter-spacing: 0.03em;
  padding: 0.3rem 0.8rem;
  border: 1.5px solid rgba(255, 255, 255, 0.55);
  border-radius: 9px;
  margin-bottom: 1.5rem;
}

.brand-name {
  font-size: 2.6rem;
  font-weight: 600;
  line-height: 1.12;
  letter-spacing: -0.01em;
  margin: 0 0 0.75rem 0;
  text-wrap: balance;
  text-shadow: 0 1px 3px rgba(9, 20, 15, 0.55), 0 2px 20px rgba(9, 20, 15, 0.35);
}

.brand-tagline-it {
  font-family: var(--font-display);
  font-style: italic;
  font-size: 1.2rem;
  color: rgba(255, 255, 255, 0.9);
  margin: 0 0 1.5rem 0;
}

.brand-system {
  font-size: 0.78rem;
  color: rgba(255, 255, 255, 0.62);
  letter-spacing: 0.14em;
  text-transform: uppercase;
  font-weight: 500;
  margin: 0;
}

/* ── Form Panel ──────────────────────────────── */
.form-panel {
  width: 460px;
  min-width: 420px;
  background: var(--ground);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem;
}

.login-card {
  background: var(--surface);
  border-radius: 16px;
  box-shadow: 0 8px 40px rgba(22, 43, 28, 0.10);
  padding: 2.5rem 2rem;
  width: 100%;
  max-width: 380px;
}

.card-logo {
  display: flex;
  justify-content: center;
  margin-bottom: 1.25rem;
}

.card-logo img {
  width: 80px;
  height: 80px;
  object-fit: contain;
}

.card-title {
  text-align: center;
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--pine-strong);
  margin: 0 0 0.25rem 0;
}

.card-subtitle {
  text-align: center;
  font-size: 0.875rem;
  color: var(--ink-2);
  margin: 0 0 1.75rem 0;
}

.form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.field label {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--ink-2);
}

.error {
  color: var(--danger);
  font-size: 0.78rem;
  min-height: 1rem;
}

.field-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.remember {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  color: var(--ink-2);
}

.forgot-link {
  font-size: 0.8rem;
  color: var(--pine);
  text-decoration: none;
  font-weight: 500;
}
.forgot-link:hover { text-decoration: underline; }

.flash-status {
  background: var(--ok-tint);
  border: 1px solid var(--ok);
  color: var(--ok);
  border-radius: 8px;
  padding: 0.65rem 0.9rem;
  font-size: 0.85rem;
  margin-bottom: 0.5rem;
}

.submit-btn {
  margin-top: 0.5rem;
  background: var(--pine) !important;
  border-color: var(--pine) !important;
  font-weight: 600;
  padding: 0.75rem;
}

.submit-btn:hover {
  background: var(--pine-strong) !important;
  border-color: var(--pine-strong) !important;
}

@media (max-width: 768px) {
  .login-page { flex-direction: column; }
  .brand-panel { padding: 2.5rem 2rem 3rem; min-height: 260px; }
  .brand-name { font-size: 1.75rem; }
  .brand-tagline-it { font-size: 1.05rem; margin-bottom: 0.5rem; }
  .brand-mark { margin-bottom: 1rem; }
  .brand-text { margin-bottom: 0; }
  .form-panel { width: 100%; min-width: 0; }
  .brand-scene { height: 45%; }
}
</style>
