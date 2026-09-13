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
  const diffMin = Math.round((Date.now() - props.modelValue.getTime()) / 60_000)
  const abs = Math.abs(diffMin)
  const direction = (text) => (diffMin >= 0 ? `${text} ago` : `in ${text}`)

  if (abs < 1) return 'Now'
  if (abs < 60) return direction(`${abs} min`)
  const hours = Math.floor(abs / 60)
  if (hours < 24) return direction(`${hours}h`)
  const days = Math.floor(hours / 24)
  return direction(`${days}d`)
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

// Changing the date is rare (defaults to today), so it rides along on the
// small caption line rather than getting its own prominent control.
// <input type="date"> wants "YYYY-MM-DD".
const dateInputValue = computed(() => {
  const d = props.modelValue
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
})

const dateLabel = computed(() => {
  const d = props.modelValue
  const today = new Date()
  const isToday =
    d.getFullYear() === today.getFullYear() && d.getMonth() === today.getMonth() && d.getDate() === today.getDate()
  return isToday ? 'Today' : d.toLocaleDateString([], { month: 'short', day: 'numeric' })
})

function onDateInput(event) {
  const value = event.target.value
  if (!value) return
  const [year, month, day] = value.split('-').map(Number)
  if ([year, month, day].some(Number.isNaN)) return
  const next = new Date(props.modelValue)
  next.setFullYear(year, month - 1, day)
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
        <div class="time-sub-row">
          <span class="muted">{{ relativeLabel }}</span>
          <span class="muted">·</span>
          <!-- Changing the date is rare, so it's just small underlined text —
               tapping it opens the native date picker (invisible input laid
               directly over the label, so the tap always lands on it). -->
          <span class="date-field-wrap">
            <span class="date-value-label">{{ dateLabel }}</span>
            <input
              type="date"
              class="date-value-input"
              :value="dateInputValue"
              :aria-label="label + ' date'"
              @input="onDateInput"
            />
          </span>
        </div>
      </div>
      <button type="button" class="btn btn-secondary" @click="adjust(1)">+1m</button>
      <button type="button" class="btn btn-secondary" @click="adjust(5)">+5m</button>
    </div>
  </div>
</template>
