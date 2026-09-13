<script setup>
defineProps({
  modelValue: { type: Boolean, required: true },
  ariaLabel: { type: String, default: 'Toggle' },
})
const emit = defineEmits(['update:modelValue'])
</script>

<template>
  <button
    type="button"
    class="toggle-switch"
    :class="{ on: modelValue }"
    role="switch"
    :aria-checked="modelValue"
    :aria-label="ariaLabel"
    @click="emit('update:modelValue', !modelValue)"
  >
    <span class="toggle-knob" />
  </button>
</template>
