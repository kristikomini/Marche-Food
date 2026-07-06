<template>
  <div class="empty-state">
    <div class="empty-icon"><i :class="icon" aria-hidden="true" /></div>
    <p class="empty-title">{{ title }}</p>
    <p v-if="message" class="empty-message">{{ message }}</p>
    <div v-if="$slots.default" class="empty-action"><slot /></div>
  </div>
</template>

<script setup>
defineProps({
  icon:    { type: String, default: 'pi pi-inbox' },
  title:   { type: String, default: 'Nessun dato' },
  message: { type: String, default: '' },
});
</script>

<style scoped>
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.4rem;
  padding: 3rem 1.5rem;
  text-align: center;
}
.empty-icon {
  width: 64px;
  height: 64px;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--pine-tint);
  color: var(--pine);
  font-size: 1.7rem;
  margin-bottom: 0.5rem;
}
.empty-title {
  font-family: var(--font-display);
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--ink);
  margin: 0;
}
.empty-message {
  font-size: 0.875rem;
  color: var(--ink-3);
  margin: 0;
  max-width: 42ch;
}
.empty-action {
  margin-top: 0.75rem;
}
</style>
