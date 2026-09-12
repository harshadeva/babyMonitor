<script setup>
import { computed } from 'vue'

const props = defineProps({
  modelValue: { type: Date, required: true },
  label: { type: String, default: 'When' },
})
const emit = defineEmits(['update:modelValue'])

function adjust(minutes) {
  const next = new Date(props.modelValue.getTime() + minutes * 60_000)
  emit('update:modelValue', next)
}

const relativeLabel = computed(() => {
  const diffMs = Date.now() - props.modelValue.getTime()
  const diffMin = Math.round(diffMs / 60_000)
  if (diffMin === 0) return 'Now'
  if (diffMin > 0) return `${diffMin} min ago`
  return `in ${Math.abs(diffMin)} min`
})

const timeString = computed(() =>
  props.modelValue.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
)
</script>

<template>
  <div class="field">
    <label>{{ label }}</label>
    <div style="display:flex; align-items:center; justify-content:space-between; gap: 8px;">
      <button type="button" class="btn btn-secondary" @click="adjust(-5)">-5m</button>
      <button type="button" class="btn btn-secondary" @click="adjust(-1)">-1m</button>
      <div style="text-align:center; flex:1;">
        <div style="font-weight:700; font-size:18px;">{{ timeString }}</div>
        <div class="muted">{{ relativeLabel }}</div>
      </div>
      <button type="button" class="btn btn-secondary" @click="adjust(1)">+1m</button>
      <button type="button" class="btn btn-secondary" @click="adjust(5)">+5m</button>
    </div>
  </div>
</template>
