<template>
  <div class="kiosk">
    <header class="k-top">
      <div class="k-brand">Marche Int. Food — <strong>Modalità Kiosk</strong></div>
      <a href="/produzioni" class="k-exit"><i class="pi pi-times" aria-hidden="true" /> Esci</a>
    </header>

    <!-- Step 1: choose scheda -->
    <section v-if="step === 'scheda'" class="k-step">
      <h1 class="k-h1">Seleziona la scheda di produzione</h1>
      <div class="k-grid">
        <button v-for="s in schede" :key="s.id" class="k-card" @click="chooseScheda(s)">
          <span class="k-card-model">{{ s.modello }}.{{ String(s.revisione).padStart(2,'0') }}</span>
          <span class="k-card-name">{{ s.prodotto || '—' }}</span>
        </button>
        <div v-if="!schede.length" class="k-empty">Nessuna scheda attiva.</div>
      </div>
    </section>

    <!-- Step 2: build production -->
    <section v-else class="k-step">
      <div class="k-headline">
        <div>
          <div class="k-sub">Scheda</div>
          <div class="k-title">{{ scheda.prodotto }} <span class="k-muted">({{ scheda.modello }}.{{ String(scheda.revisione).padStart(2,'0') }})</span></div>
        </div>
        <button class="k-textbtn" @click="reset">Cambia scheda</button>
      </div>

      <div class="k-field">
        <label>Lotto di produzione</label>
        <input v-model="lottoProduzione" class="k-input" aria-label="Lotto di produzione" />
      </div>

      <!-- Added ingredients -->
      <div class="k-list" v-if="ingredienti.length">
        <div v-for="(ing, i) in ingredienti" :key="i" class="k-row">
          <div>
            <div class="k-row-name">{{ ing.nome }}</div>
            <div class="k-row-lot">Lotto {{ ing.lotto }} · {{ ing.quantita_kg }} kg</div>
          </div>
          <button class="k-remove" aria-label="Rimuovi" @click="ingredienti.splice(i,1)"><i class="pi pi-trash" /></button>
        </div>
      </div>

      <!-- Scan / add ingredient -->
      <div class="k-scanbox">
        <div class="k-scanrow">
          <input ref="scanInput" v-model="scanCode" class="k-input k-scan" placeholder="Scansiona o digita il lotto…" aria-label="Codice lotto" @keyup.enter="doLookup" />
          <button class="k-btn k-btn-scan" @click="doLookup" :disabled="looking"><i class="pi pi-search" aria-hidden="true" /> Cerca</button>
          <button class="k-btn k-btn-cam" @click="toggleCamera"><i class="pi pi-camera" aria-hidden="true" /> {{ cameraOn ? 'Chiudi' : 'Fotocamera' }}</button>
        </div>
        <div v-show="cameraOn" id="k-reader" class="k-reader"></div>
        <div v-if="lookupError" class="k-error">{{ lookupError }}</div>

        <!-- Found lot: pick materia prima (if needed) + keypad -->
        <div v-if="found" class="k-found">
          <div class="k-found-head">
            <strong>{{ found.nome_prodotto }}</strong> — Lotto {{ found.lotto }}
            <span class="k-badge" :class="found.balance_kg > 0 ? 'ok' : 'no'">Disp: {{ found.balance_kg }} kg</span>
          </div>

          <div v-if="!materiaPrimaId" class="k-mp-pick">
            <div class="k-sub">Associa alla materia prima:</div>
            <div class="k-chips">
              <button v-for="ing in scheda.ingredienti" :key="ing.materia_prima_id" class="k-chip" :class="{sel: materiaPrimaId === ing.materia_prima_id}" @click="materiaPrimaId = ing.materia_prima_id; materiaPrimaNome = ing.nome">{{ ing.nome }}</button>
            </div>
          </div>
          <div v-else class="k-sub">Materia prima: <strong>{{ materiaPrimaNome }}</strong> <button class="k-textbtn" @click="materiaPrimaId=null">cambia</button></div>

          <div class="k-keypad-wrap">
            <div class="k-kg" aria-live="polite">{{ kg || '0' }} <span>kg</span></div>
            <div class="k-keypad">
              <button v-for="key in keys" :key="key" class="k-key" @click="press(key)">{{ key }}</button>
              <button class="k-key k-key-wide" @click="press('back')" aria-label="Cancella"><i class="pi pi-delete-left" /></button>
            </div>
            <button class="k-btn k-btn-add" :disabled="!canAdd" @click="addIngredient"><i class="pi pi-plus" aria-hidden="true" /> Aggiungi ingrediente</button>
          </div>
        </div>
      </div>

      <div class="k-submit">
        <button class="k-btn k-btn-submit" :disabled="!ingredienti.length || submitting" @click="submit">
          <i class="pi pi-check" aria-hidden="true" /> Registra produzione ({{ ingredienti.length }})
        </button>
        <div v-if="submitError" class="k-error">{{ submitError }}</div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, computed, nextTick, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  schede:  { type: Array, default: () => [] },
  materie: { type: Array, default: () => [] },
});

const step = ref('scheda');
const scheda = ref(null);
const lottoProduzione = ref('');
const ingredienti = ref([]);

const scanInput = ref(null);
const scanCode = ref('');
const looking = ref(false);
const lookupError = ref('');
const found = ref(null);
const materiaPrimaId = ref(null);
const materiaPrimaNome = ref('');
const kg = ref('');
const keys = ['1','2','3','4','5','6','7','8','9','.','0'];
const submitting = ref(false);
const submitError = ref('');

const cameraOn = ref(false);
let html5qr = null;

const canAdd = computed(() => found.value && materiaPrimaId.value && parseFloat(kg.value) > 0);

function chooseScheda(s) {
  scheda.value = s;
  const now = new Date();
  const p = (n) => String(n).padStart(2, '0');
  lottoProduzione.value = `LP-${now.getFullYear()}${p(now.getMonth()+1)}${p(now.getDate())}-${p(now.getHours())}${p(now.getMinutes())}`;
  step.value = 'build';
  nextTick(() => scanInput.value?.focus());
}

function reset() {
  step.value = 'scheda';
  scheda.value = null;
  ingredienti.value = [];
  clearFound();
  stopCamera();
}

function clearFound() {
  found.value = null; materiaPrimaId.value = null; materiaPrimaNome.value = ''; kg.value = ''; scanCode.value = '';
}

async function doLookup() {
  const code = scanCode.value.trim();
  if (code.length < 1) return;
  looking.value = true; lookupError.value = ''; found.value = null;
  try {
    const res = await fetch(`/produzioni/kiosk/lookup?code=${encodeURIComponent(code)}`, { headers: { 'Accept': 'application/json' } });
    const data = await res.json();
    if (!data.found) { lookupError.value = data.messaggio || 'Lotto non trovato.'; return; }
    found.value = data.riga;
    materiaPrimaId.value = data.materia_prima_id ?? null;
    materiaPrimaNome.value = data.materia_prima_nome ?? '';
    // If only one recipe ingredient, auto-select it.
    if (!materiaPrimaId.value && scheda.value.ingredienti.length === 1) {
      materiaPrimaId.value = scheda.value.ingredienti[0].materia_prima_id;
      materiaPrimaNome.value = scheda.value.ingredienti[0].nome;
    }
  } catch (e) {
    lookupError.value = 'Errore di rete durante la ricerca.';
  } finally {
    looking.value = false;
  }
}

function press(k) {
  if (k === 'back') { kg.value = kg.value.slice(0, -1); return; }
  if (k === '.' && kg.value.includes('.')) return;
  kg.value = (kg.value + k).slice(0, 9);
}

function addIngredient() {
  if (!canAdd.value) return;
  ingredienti.value.push({
    materia_prima_id: materiaPrimaId.value,
    source_type: 'acquisto',
    acquisto_riga_id: found.value.id,
    nome: materiaPrimaNome.value || found.value.nome_prodotto,
    lotto: found.value.lotto,
    quantita_kg: parseFloat(kg.value),
  });
  clearFound();
  nextTick(() => scanInput.value?.focus());
}

function submit() {
  submitting.value = true; submitError.value = '';
  router.post('/produzioni', {
    scheda_id: scheda.value.id,
    lotto_produzione: lottoProduzione.value,
    data_produzione: new Date().toISOString().slice(0, 10),
    materie_prime: ingredienti.value.map(({ materia_prima_id, source_type, acquisto_riga_id, quantita_kg }) => ({ materia_prima_id, source_type, acquisto_riga_id, quantita_kg })),
  }, {
    onError: (errors) => { submitError.value = Object.values(errors)[0] || 'Errore nella registrazione.'; },
    onFinish: () => { submitting.value = false; },
  });
}

// Camera scanning (progressive enhancement via vendored html5-qrcode)
function loadCamLib() {
  return new Promise((resolve, reject) => {
    if (window.Html5Qrcode) { resolve(); return; }
    const s = document.createElement('script');
    s.src = '/vendor/html5-qrcode.min.js';
    s.onload = () => resolve();
    s.onerror = () => reject(new Error('lib'));
    document.head.appendChild(s);
  });
}
async function toggleCamera() {
  if (cameraOn.value) { stopCamera(); return; }
  try {
    await loadCamLib();
    cameraOn.value = true;
    await nextTick();
    html5qr = new window.Html5Qrcode('k-reader');
    await html5qr.start({ facingMode: 'environment' }, { fps: 10, qrbox: 220 }, (text) => {
      scanCode.value = text;
      stopCamera();
      doLookup();
    }, () => {});
  } catch (e) {
    cameraOn.value = false;
    lookupError.value = 'Fotocamera non disponibile. Usa lo scanner o digita il lotto.';
  }
}
function stopCamera() {
  cameraOn.value = false;
  if (html5qr) { try { html5qr.stop().then(() => html5qr.clear()).catch(() => {}); } catch (e) {} html5qr = null; }
}
onBeforeUnmount(stopCamera);
</script>

<style scoped>
/* The Kiosk is a bespoke, always-dark touchscreen view (production floor).
   It uses LITERAL brand-dark colours — not the semantic tokens — so it stays
   dark and legible regardless of the app's light/dark setting. Fonts stay as
   tokens (they don't change with theme). */
.kiosk { position: fixed; inset: 0; background: #14231c; color: #e9eeeb; display: flex; flex-direction: column; z-index: 500; font-family: var(--font-sans); }
.k-top { display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1.25rem; background: #10362a; }
.k-brand { font-size: 0.95rem; }
.k-exit { color: #a9c6ba; text-decoration: none; font-size: 0.9rem; padding: 0.4rem 0.8rem; border: 1px solid #34564a; border-radius: 8px; }
.k-step { flex: 1; overflow-y: auto; padding: 1.5rem; }
.k-h1 { font-size: 1.5rem; margin-bottom: 1.25rem; text-align: center; }
.k-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1rem; }
.k-card { background: #1d4638; border: 2px solid #2e6b57; border-radius: 14px; padding: 1.5rem; text-align: left; cursor: pointer; color: #e9eeeb; display: flex; flex-direction: column; gap: 0.4rem; min-height: 110px; }
.k-card:active { background: #245442; }
.k-card-model { font-family: var(--font-mono); color: #6cbfa2; font-size: 0.9rem; }
.k-card-name { font-size: 1.15rem; font-weight: 700; }
.k-empty { color: #93b3a6; }
.k-headline { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
.k-sub { font-size: 0.8rem; color: #93b3a6; }
.k-title { font-size: 1.3rem; font-weight: 700; }
.k-muted { color: #6cbfa2; font-size: 0.9rem; }
.k-textbtn { background: none; border: none; color: #6cbfa2; cursor: pointer; text-decoration: underline; font-size: 0.85rem; }
.k-field { margin-bottom: 1rem; }
.k-field label { display: block; font-size: 0.8rem; color: #93b3a6; margin-bottom: 0.3rem; }
.k-input { width: 100%; font-size: 1.2rem; padding: 0.8rem 1rem; border-radius: 10px; border: 2px solid #34564a; background: #1d4638; color: #fff; }
.k-list { margin-bottom: 1rem; display: flex; flex-direction: column; gap: 0.5rem; }
.k-row { display: flex; justify-content: space-between; align-items: center; background: #1d4638; border-radius: 10px; padding: 0.75rem 1rem; }
.k-row-name { font-weight: 700; }
.k-row-lot { font-size: 0.85rem; color: #93b3a6; }
.k-remove { background: none; border: none; color: #f0817b; font-size: 1.1rem; cursor: pointer; }
.k-scanbox { background: #183a2e; border-radius: 14px; padding: 1.25rem; margin-bottom: 1.25rem; }
.k-scanrow { display: flex; gap: 0.6rem; flex-wrap: wrap; }
.k-scan { flex: 1; min-width: 200px; }
.k-btn { border: none; border-radius: 10px; padding: 0.8rem 1.2rem; font-size: 1rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; }
.k-btn-scan { background: #2e6b57; color: #fff; }
.k-btn-cam { background: #45564f; color: #fff; }
.k-reader { margin-top: 1rem; max-width: 320px; }
.k-error { color: #f0817b; margin-top: 0.75rem; font-size: 0.9rem; }
.k-found { margin-top: 1.25rem; border-top: 1px solid #34564a; padding-top: 1rem; }
.k-found-head { font-size: 1.05rem; margin-bottom: 0.75rem; }
.k-badge { margin-left: 0.5rem; font-size: 0.8rem; padding: 0.15rem 0.55rem; border-radius: 99px; }
.k-badge.ok { background: #12362a; color: #5fd39a; }
.k-badge.no { background: #3a1a1a; color: #f0817b; }
.k-chips { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 0.4rem; }
.k-chip { background: #1d4638; border: 1px solid #34564a; color: #e9eeeb; border-radius: 8px; padding: 0.5rem 0.9rem; cursor: pointer; }
.k-chip.sel { background: #2e6b57; border-color: #2e6b57; }
.k-keypad-wrap { margin-top: 1rem; max-width: 320px; }
.k-kg { font-size: 2rem; font-weight: 700; text-align: right; margin-bottom: 0.5rem; }
.k-kg span { font-size: 1rem; color: #93b3a6; }
.k-keypad { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; }
.k-key { background: #1d4638; border: none; color: #fff; font-size: 1.4rem; padding: 1rem; border-radius: 10px; cursor: pointer; }
.k-key:active { background: #2e6b57; }
.k-key-wide { grid-column: span 3; }
.k-btn-add { background: #2e6b57; color: #fff; width: 100%; justify-content: center; margin-top: 0.75rem; }
.k-btn-add:disabled { opacity: 0.5; }
.k-submit { position: sticky; bottom: 0; padding-top: 0.5rem; }
.k-btn-submit { background: #2e7d55; color: #fff; width: 100%; justify-content: center; font-size: 1.15rem; padding: 1.1rem; }
.k-btn-submit:disabled { opacity: 0.5; }
</style>
