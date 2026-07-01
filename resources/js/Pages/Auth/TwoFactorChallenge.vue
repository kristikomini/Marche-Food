<template>
  <div class="tfa-shell">
    <div class="tfa-box">
      <div class="brand">
        <img src="/favicon.png" alt="Marche International Food" class="logo" />
        <h1>Verifica in due passaggi</h1>
      </div>

      <template v-if="!useRecovery">
        <p class="hint">Inserisci il codice a 6 cifre generato dalla tua app authenticator.</p>
        <form @submit.prevent="submit">
          <InputText v-model="form.code" inputmode="numeric" maxlength="6" placeholder="000000" autofocus fluid :invalid="!!form.errors.code" class="code-input" />
          <small v-if="form.errors.code" class="error">{{ form.errors.code }}</small>
          <Button type="submit" label="Verifica" icon="pi pi-check" :loading="form.processing" fluid class="submit" />
        </form>
        <button class="link" type="button" @click="useRecovery = true">Usa un codice di recupero</button>
      </template>

      <template v-else>
        <p class="hint">Inserisci uno dei tuoi codici di recupero.</p>
        <form @submit.prevent="submit">
          <InputText v-model="form.recovery_code" placeholder="XXXXX-XXXXX" autofocus fluid :invalid="!!form.errors.code" class="code-input" />
          <small v-if="form.errors.code" class="error">{{ form.errors.code }}</small>
          <Button type="submit" label="Verifica" icon="pi pi-check" :loading="form.processing" fluid class="submit" />
        </form>
        <button class="link" type="button" @click="useRecovery = false">Usa il codice dell'app</button>
      </template>

      <a href="/login" class="back">← Torna al login</a>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';

const useRecovery = ref(false);
const form = useForm({ code: '', recovery_code: '' });

function submit() {
  form.post('/2fa/challenge');
}
</script>

<style scoped>
.tfa-shell { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #1c3d28, #2a6941); padding: 1rem; }
.tfa-box { background: #fff; border-radius: 12px; padding: 2rem; width: 100%; max-width: 380px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
.brand { text-align: center; margin-bottom: 1.5rem; }
.logo { width: 56px; height: 56px; object-fit: contain; }
.brand h1 { font-size: 1.1rem; color: #1c3d28; margin-top: 0.5rem; }
.hint { font-size: 0.85rem; color: #64748b; text-align: center; margin-bottom: 1.25rem; }
.code-input { text-align: center; font-size: 1.2rem; letter-spacing: 0.2em; }
.error { color: #dc2626; font-size: 0.8rem; display: block; margin: 0.4rem 0; text-align: center; }
.submit { margin-top: 1rem; }
.link { background: none; border: none; color: #2a6941; font-size: 0.82rem; cursor: pointer; display: block; margin: 1rem auto 0; text-decoration: underline; }
.back { display: block; text-align: center; margin-top: 1.25rem; color: #94a3b8; font-size: 0.8rem; text-decoration: none; }
.back:hover { color: #2a6941; }
</style>
