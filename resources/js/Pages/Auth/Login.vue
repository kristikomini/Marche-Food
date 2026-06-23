<template>
  <div class="login-page">
    <!-- Decorative brand panel -->
    <div class="brand-panel">
      <div class="brand-arches">
        <div class="arch arch-1"></div>
        <div class="arch arch-2"></div>
        <div class="arch arch-3"></div>
        <div class="arch arch-4"></div>
        <div class="sun"></div>
      </div>
      <div class="brand-text">
        <h1 class="brand-name">Marche International<br>Food S.r.l.</h1>
        <p class="brand-tagline">Sistema di Tracciabilità HACCP</p>
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
  font-family: 'Segoe UI', Arial, sans-serif;
}

/* ── Brand Panel ─────────────────────────────── */
.brand-panel {
  flex: 1;
  background: linear-gradient(160deg, #162b1c 0%, #1c3d28 50%, #2a6941 100%);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem;
  position: relative;
  overflow: hidden;
}

.brand-arches {
  position: absolute;
  bottom: -40px;
  left: 50%;
  transform: translateX(-50%);
  width: 500px;
  height: 320px;
}

.arch {
  position: absolute;
  bottom: 0;
  border-radius: 50% 50% 0 0;
}
.arch-1 {
  width: 340px; height: 280px;
  background: rgba(42, 105, 65, 0.7);
  left: 80px;
}
.arch-2 {
  width: 260px; height: 220px;
  background: rgba(74, 154, 93, 0.6);
  left: 140px;
}
.arch-3 {
  width: 180px; height: 160px;
  background: rgba(128, 196, 144, 0.5);
  left: 180px;
  bottom: 0;
}
.arch-4 {
  width: 200px; height: 170px;
  background: rgba(204, 68, 68, 0.35);
  left: 280px;
}
.sun {
  position: absolute;
  width: 70px; height: 70px;
  border-radius: 50%;
  background: #f5c534;
  top: 20px;
  left: 210px;
  box-shadow: 0 0 40px rgba(245, 197, 52, 0.4);
}

.brand-text {
  position: relative;
  z-index: 10;
  text-align: center;
  color: #fff;
}

.brand-name {
  font-size: 2.2rem;
  font-weight: 800;
  line-height: 1.2;
  letter-spacing: 0.01em;
  margin: 0 0 1rem 0;
  text-shadow: 0 2px 12px rgba(0,0,0,0.3);
}

.brand-tagline {
  font-size: 1rem;
  color: rgba(255,255,255,0.7);
  letter-spacing: 0.08em;
  text-transform: uppercase;
  font-weight: 500;
  margin: 0;
}

/* ── Form Panel ──────────────────────────────── */
.form-panel {
  width: 460px;
  min-width: 420px;
  background: #f5f7f5;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem;
}

.login-card {
  background: #fff;
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
  color: #1a3d28;
  margin: 0 0 0.25rem 0;
}

.card-subtitle {
  text-align: center;
  font-size: 0.875rem;
  color: #64748b;
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
  color: #374151;
}

.error {
  color: #dc2626;
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
  color: #4b5563;
}

.forgot-link {
  font-size: 0.8rem;
  color: #2a6941;
  text-decoration: none;
  font-weight: 500;
}
.forgot-link:hover { text-decoration: underline; }

.flash-status {
  background: #f0fdf4;
  border: 1px solid #86efac;
  color: #166534;
  border-radius: 8px;
  padding: 0.65rem 0.9rem;
  font-size: 0.85rem;
  margin-bottom: 0.5rem;
}

.submit-btn {
  margin-top: 0.5rem;
  background: #2a6941 !important;
  border-color: #2a6941 !important;
  font-weight: 600;
  padding: 0.75rem;
}

.submit-btn:hover {
  background: #1c4f30 !important;
  border-color: #1c4f30 !important;
}

@media (max-width: 768px) {
  .login-page { flex-direction: column; }
  .brand-panel { padding: 2rem; min-height: 220px; }
  .brand-name { font-size: 1.5rem; }
  .form-panel { width: 100%; min-width: 0; }
  .brand-arches { display: none; }
}
</style>
