<template>
  <div class="login-page">
    <div class="brand-panel">
      <div class="brand-arches">
        <div class="arch arch-1"></div><div class="arch arch-2"></div>
        <div class="arch arch-3"></div><div class="arch arch-4"></div>
        <div class="sun"></div>
      </div>
      <div class="brand-text">
        <h1 class="brand-name">Marche International<br>Food S.r.l.</h1>
        <p class="brand-tagline">Sistema di Tracciabilità HACCP</p>
      </div>
    </div>

    <div class="form-panel">
      <div class="login-card">
        <div class="card-logo"><img src="/favicon.png" alt="Marche International Food" /></div>
        <h2 class="card-title">Nuova password</h2>
        <p class="card-subtitle">Scegli una nuova password per il tuo account.</p>

        <form @submit.prevent="submit" class="form">
          <div class="field">
            <label>Email</label>
            <InputText v-model="form.email" type="email" :invalid="!!form.errors.email" fluid />
            <small class="error">{{ form.errors.email }}</small>
          </div>
          <div class="field">
            <label>Nuova password</label>
            <Password v-model="form.password" :feedback="true" toggle-mask :invalid="!!form.errors.password" fluid />
            <small class="error">{{ form.errors.password }}</small>
          </div>
          <div class="field">
            <label>Conferma password</label>
            <Password v-model="form.password_confirmation" :feedback="false" toggle-mask fluid />
          </div>
          <Button type="submit" label="Imposta nuova password" icon="pi pi-lock" :loading="form.processing" fluid class="submit-btn" />
        </form>

        <div class="back-link">
          <Link href="/login"><i class="pi pi-arrow-left" /> Torna al login</Link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Button from 'primevue/button';

const props = defineProps({ token: String, email: String });

const form = useForm({
  token:                 props.token,
  email:                 props.email ?? '',
  password:              '',
  password_confirmation: '',
});

function submit() {
  form.post('/reset-password', { onFinish: () => form.reset('password', 'password_confirmation') });
}
</script>

<style scoped>
* { box-sizing:border-box; }
.login-page { display:flex; min-height:100vh; font-family:'Segoe UI',Arial,sans-serif; }
.brand-panel { flex:1; background:linear-gradient(160deg,#162b1c 0%,#1c3d28 50%,#2a6941 100%); display:flex; flex-direction:column; align-items:center; justify-content:center; padding:3rem; position:relative; overflow:hidden; }
.brand-arches { position:absolute; bottom:-40px; left:50%; transform:translateX(-50%); width:500px; height:320px; }
.arch { position:absolute; bottom:0; border-radius:50% 50% 0 0; }
.arch-1 { width:340px; height:280px; background:rgba(42,105,65,0.7); left:80px; }
.arch-2 { width:260px; height:220px; background:rgba(74,154,93,0.6); left:140px; }
.arch-3 { width:180px; height:160px; background:rgba(128,196,144,0.5); left:180px; }
.arch-4 { width:200px; height:170px; background:rgba(204,68,68,0.35); left:280px; }
.sun { position:absolute; width:70px; height:70px; border-radius:50%; background:#f5c534; top:20px; left:210px; }
.brand-text { position:relative; z-index:10; text-align:center; color:#fff; }
.brand-name { font-size:2.2rem; font-weight:800; line-height:1.2; margin:0 0 1rem 0; }
.brand-tagline { font-size:1rem; color:rgba(255,255,255,0.7); letter-spacing:0.08em; text-transform:uppercase; margin:0; }
.form-panel { width:460px; min-width:420px; background:#f5f7f5; display:flex; align-items:center; justify-content:center; padding:2rem; }
.login-card { background:#fff; border-radius:16px; box-shadow:0 8px 40px rgba(22,43,28,0.10); padding:2.5rem 2rem; width:100%; max-width:380px; }
.card-logo { display:flex; justify-content:center; margin-bottom:1.25rem; }
.card-logo img { width:80px; height:80px; object-fit:contain; }
.card-title { text-align:center; font-size:1.5rem; font-weight:700; color:#1a3d28; margin:0 0 0.25rem 0; }
.card-subtitle { text-align:center; font-size:0.875rem; color:#64748b; margin:0 0 1.5rem 0; }
.form { display:flex; flex-direction:column; gap:1rem; }
.field { display:flex; flex-direction:column; gap:0.35rem; }
.field label { font-size:0.875rem; font-weight:600; color:#374151; }
.error { color:#dc2626; font-size:0.78rem; min-height:1rem; }
.submit-btn { margin-top:0.5rem; background:#2a6941 !important; border-color:#2a6941 !important; font-weight:600; padding:0.75rem; }
.back-link { text-align:center; margin-top:1.25rem; font-size:0.875rem; }
.back-link a { color:#2a6941; text-decoration:none; display:inline-flex; align-items:center; gap:0.4rem; }
@media (max-width:768px) {
  .login-page { flex-direction:column; }
  .brand-panel { padding:2rem; min-height:220px; }
  .form-panel { width:100%; min-width:0; }
  .brand-arches { display:none; }
}
</style>
