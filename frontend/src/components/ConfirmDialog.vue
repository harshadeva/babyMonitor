<script setup>
defineProps({
  title: { type: String, default: 'Are you sure?' },
  message: { type: String, default: '' },
  confirmLabel: { type: String, default: 'Delete' },
  cancelLabel: { type: String, default: 'Cancel' },
  icon: { type: String, default: '🗑️' },
})
const emit = defineEmits(['confirm', 'cancel'])
</script>

<template>
  <div class="confirm-backdrop" @click.self="emit('cancel')">
    <div class="confirm-card">
      <div class="confirm-icon">{{ icon }}</div>
      <h3 class="confirm-title">{{ title }}</h3>
      <p v-if="message" class="confirm-message">{{ message }}</p>
      <div class="confirm-actions">
        <button type="button" class="btn btn-secondary" @click="emit('cancel')">{{ cancelLabel }}</button>
        <button type="button" class="btn btn-danger" @click="emit('confirm')">{{ confirmLabel }}</button>
      </div>
    </div>
  </div>
</template>
