<template>
  <AppLayout>
    <div class="page-header">
      <h1 class="page-title">Gestione Utenti</h1>
    </div>

    <!-- Create user form -->
    <div class="card mb-4">
      <h2 class="section-title">Nuovo Utente</h2>
      <div class="form-grid">
        <div class="field">
          <label>Nome *</label>
          <InputText v-model="createForm.name" :invalid="!!createForm.errors.name" fluid placeholder="Nome e Cognome" />
          <small class="error">{{ createForm.errors.name }}</small>
        </div>
        <div class="field">
          <label>Email *</label>
          <InputText v-model="createForm.email" type="email" :invalid="!!createForm.errors.email" fluid placeholder="nome@marche.it" />
          <small class="error">{{ createForm.errors.email }}</small>
        </div>
        <div class="field">
          <label>Ruolo *</label>
          <Select v-model="createForm.role" :options="roleOptions" option-label="label" option-value="value" fluid />
          <small class="error">{{ createForm.errors.role }}</small>
        </div>
        <div class="field">
          <label>Password *</label>
          <Password v-model="createForm.password" :feedback="false" toggle-mask :invalid="!!createForm.errors.password" fluid placeholder="min. 8 caratteri" />
          <small class="error">{{ createForm.errors.password }}</small>
        </div>
        <div class="field">
          <label>Conferma Password *</label>
          <Password v-model="createForm.password_confirmation" :feedback="false" toggle-mask fluid placeholder="Ripeti password" />
        </div>
        <div class="field field-action">
          <Button label="Crea Utente" icon="pi pi-plus" @click="submitCreate" :loading="createForm.processing" />
        </div>
      </div>
    </div>

    <!-- Users table -->
    <div class="card">
      <h2 class="section-title">Utenti registrati</h2>
      <DataTable :value="utenti" striped-rows size="small">
        <Column field="name" header="Nome" />
        <Column field="email" header="Email" />
        <Column field="role" header="Ruolo" style="width:120px">
          <template #body="{ data }">
            <Tag
              :value="data.role === 'admin' ? 'Admin' : 'Operatore'"
              :severity="data.role === 'admin' ? 'success' : 'warn'"
            />
          </template>
        </Column>
        <Column header="Azioni" style="width:200px">
          <template #body="{ data }">
            <div style="display:flex;gap:0.4rem;flex-wrap:wrap">
              <Button
                icon="pi pi-pencil" aria-label="Modifica"
                size="small"
                outlined
                label="Modifica"
                @click="openEdit(data)"
              />
              <Button
                icon="pi pi-key" aria-label="Reimposta password"
                size="small"
                outlined
                severity="warn"
                label="Password"
                @click="openReset(data)"
              />
              <Button
                v-if="data.id !== currentUserId"
                icon="pi pi-trash" aria-label="Elimina"
                size="small"
                outlined
                severity="danger"
                @click="confirmDelete(data)"
              />
            </div>
          </template>
        </Column>
        <template #empty><div class="empty-state">Nessun utente trovato.</div></template>
      </DataTable>
    </div>

    <!-- Edit dialog -->
    <Dialog v-model:visible="showEdit" header="Modifica Utente" modal style="width:400px">
      <div class="dialog-form">
        <div class="field">
          <label>Nome *</label>
          <InputText v-model="editForm.name" :invalid="!!editForm.errors.name" fluid />
          <small class="error">{{ editForm.errors.name }}</small>
        </div>
        <div class="field">
          <label>Email *</label>
          <InputText v-model="editForm.email" type="email" :invalid="!!editForm.errors.email" fluid />
          <small class="error">{{ editForm.errors.email }}</small>
        </div>
        <div class="field">
          <label>Ruolo *</label>
          <Select v-model="editForm.role" :options="roleOptions" option-label="label" option-value="value" fluid />
        </div>
      </div>
      <template #footer>
        <Button label="Annulla" outlined @click="showEdit = false" />
        <Button label="Salva" icon="pi pi-check" @click="submitEdit" :loading="editForm.processing" />
      </template>
    </Dialog>

    <!-- Reset password dialog -->
    <Dialog v-model:visible="showReset" header="Reimposta Password" modal style="width:380px">
      <div class="dialog-form">
        <p class="dialog-note">Reimposta la password per <strong>{{ resetTarget?.name }}</strong>.</p>
        <div class="field">
          <label>Nuova Password *</label>
          <Password v-model="resetForm.password" :feedback="false" toggle-mask :invalid="!!resetForm.errors.password" fluid />
          <small class="error">{{ resetForm.errors.password }}</small>
        </div>
        <div class="field">
          <label>Conferma Password *</label>
          <Password v-model="resetForm.password_confirmation" :feedback="false" toggle-mask fluid />
        </div>
      </div>
      <template #footer>
        <Button label="Annulla" outlined @click="showReset = false" />
        <Button label="Reimposta" icon="pi pi-key" aria-label="Reimposta password" severity="warn" @click="submitReset" :loading="resetForm.processing" />
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { useConfirm } from 'primevue/useconfirm';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Dialog from 'primevue/dialog';

const props = defineProps({ utenti: Array });
const confirm = useConfirm();
const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id);

const roleOptions = [
  { label: 'Operatore', value: 'operator' },
  { label: 'Admin', value: 'admin' },
];

// ── Create ──────────────────────────────────────────────────────────────────
const createForm = useForm({ name: '', email: '', role: 'operator', password: '', password_confirmation: '' });

function submitCreate() {
  createForm.post('/utenti', { onSuccess: () => createForm.reset() });
}

// ── Edit ────────────────────────────────────────────────────────────────────
const showEdit = ref(false);
const editTarget = ref(null);
const editForm = useForm({ name: '', email: '', role: 'operator' });

function openEdit(u) {
  editTarget.value = u;
  editForm.name  = u.name;
  editForm.email = u.email;
  editForm.role  = u.role;
  showEdit.value = true;
}

function submitEdit() {
  editForm.put(`/utenti/${editTarget.value.id}`, {
    onSuccess: () => { showEdit.value = false; },
  });
}

// ── Reset password ───────────────────────────────────────────────────────────
const showReset = ref(false);
const resetTarget = ref(null);
const resetForm = useForm({ password: '', password_confirmation: '' });

function openReset(u) {
  resetTarget.value = u;
  resetForm.reset();
  showReset.value = true;
}

function submitReset() {
  resetForm.post(`/utenti/${resetTarget.value.id}/reset-password`, {
    onSuccess: () => { showReset.value = false; },
  });
}

// ── Delete ───────────────────────────────────────────────────────────────────
function confirmDelete(u) {
  confirm.require({
    message: `Eliminare l'utente "${u.name}"?`,
    header: 'Conferma eliminazione',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Elimina', rejectLabel: 'Annulla', acceptClass: 'p-button-danger',
    accept: () => { useForm({}).delete(`/utenti/${u.id}`); },
  });
}
</script>

<style scoped>
.page-header { display:flex; align-items:center; margin-bottom:1.5rem; }
.page-title { font-size:1.5rem; font-weight:700; color:var(--ink); margin:0; }
.card { background:var(--surface); border-radius:8px; border:1px solid var(--border); padding:1.5rem; }
.mb-4 { margin-bottom:1rem; }
.section-title { font-size:0.85rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:var(--ink-2); margin:0 0 1rem 0; }
.form-grid { display:grid; grid-template-columns:repeat(3, 1fr); gap:1rem; align-items:end; }
.field { display:flex; flex-direction:column; gap:0.3rem; }
.field label { font-size:0.85rem; font-weight:600; color:var(--ink-2); }
.field-action { justify-content:flex-end; }
.error { color:var(--danger); font-size:0.78rem; min-height:1rem; }
.dialog-form { display:flex; flex-direction:column; gap:0.85rem; padding:0.5rem 0; }
.dialog-note { margin:0 0 0.5rem 0; color:var(--ink-2); font-size:0.875rem; }
.empty-state { padding:2rem; text-align:center; color:var(--ink-3); }
</style>
