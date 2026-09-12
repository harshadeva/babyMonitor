<script setup>
defineProps({
  title: { type: String, required: true },
})
const emit = defineEmits(['close'])
</script>

<template>
  <div class="sheet-backdrop" @click.self="emit('close')">
    <div class="sheet">
      <div class="sheet-handle" />
      <h2 class="sheet-title">{{ title }}</h2>
      <slot />
    </div>
  </div>
</template>
