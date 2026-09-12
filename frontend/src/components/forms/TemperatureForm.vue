<script setup>
import { computed, onMounted, ref } from 'vue'
import TimeAdjuster from '@/components/TimeAdjuster.vue'
import { TEMPERATURE_METHODS } from '@/constants/options'
import { useEntryLogger } from '@/composables/useEntryLogger'
import { getSetting, setSetting } from '@/offline/db'

const props = defineProps({ babyId: { type: Number, required: true } })
const emit = defineEmits(['saved'])

const { submit, isSubmitting } = useEntryLogger('temperatures')

const unit = ref('C')
const method = ref('armpit')
const when = ref(new Date())
const valueC = ref(36.5)

onMounted(async () => {
  unit.value = await getSetting('temp_unit', 'C')
  method.value = await getSetting('temp_method', 'armpit')
})

const displayValue = computed(() =>
  unit.value === 'C' ? valueC.value : Math.round(((valueC.value * 9) / 5 + 32) * 10) / 10
)

function adjust(deltaC) {
  valueC.value = Math.round((valueC.value + deltaC) * 10) / 10
}

function step() {
  return unit.value === 'C' ? 0.1 : 0.1 * (5 / 9)
}

async function save() {
  await setSetting('temp_unit', unit.value)
  await setSetting('temp_method', method.value)

  const result = await submit(props.babyId, {
    measured_at: when.value.toISOString(),
    value_celsius: valueC.value,
    method: method.value,
  })
  emit('saved', result)
}
</script>

<template>
  <div>
    <div class="field">
      <label>Unit</label>
      <div class="segmented">
        <button type="button" :class="{ active: unit === 'C' }" @click="unit = 'C'">°C</button>
        <button type="button" :class="{ active: unit === 'F' }" @click="unit = 'F'">°F</button>
      </div>
    </div>

    <div class="field">
      <label>Method</label>
      <select v-model="method">
        <option v-for="m in TEMPERATURE_METHODS" :key="m.value" :value="m.value">{{ m.label }}</option>
      </select>
    </div>

    <div class="stepper" style="margin: 24px 0;">
      <button type="button" @click="adjust(-step())">−</button>
      <div class="value">{{ displayValue.toFixed(1) }}°{{ unit }}</div>
      <button type="button" @click="adjust(step())">+</button>
    </div>

    <TimeAdjuster v-model="when" />

    <button class="btn btn-primary btn-block" :disabled="isSubmitting" @click="save">
      {{ isSubmitting ? 'Saving…' : 'Log temperature' }}
    </button>
  </div>
</template>
