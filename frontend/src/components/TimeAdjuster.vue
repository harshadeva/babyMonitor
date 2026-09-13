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

// <input type="time"> wants "HH:MM" in 24-hour form.
const timeInputValue = computed(() => {
  const d = props.modelValue
  return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
})

function onTimeInput(event) {
  const value = event.target.value
  if (!value) return
  const [hours, minutes] = value.split(':').map(Number)
  if (Number.isNaN(hours) || Number.isNaN(minutes)) return
  const next = new Date(props.modelValue)
  next.setHours(hours, minutes, 0, 0)
  emit('update:modelValue', next)
}
</script>

<template>
  <div class="field">
    <label>{{ label }}</label>
    <div style="display:flex; align-items:center; justify-content:space-between; gap: 8px;">
      <button type="button" class="btn btn-secondary" @click="adjust(-5)">-5m</button>
      <button type="button" class="btn btn-secondary" @click="adjust(-1)">-1m</button>
      <div style="text-align:center; flex:1;">
        <!-- Tapping the time directly opens the device's native time picker. -->
        <input
          type="time"
          class="time-value-input"
          :value="timeInputValue"
          :aria-label="label"
          @input="onTimeInput"
        />
        <div class="muted">{{ relativeLabel }}</div>
      </div>
      <button type="button" class="btn btn-secondary" @click="adjust(1)">+1m</button>
      <button type="button" class="btn btn-secondary" @click="adjust(5)">+5m</button>
    </div>
  </div>
</template>
