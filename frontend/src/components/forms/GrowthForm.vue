<script setup>
import { ref } from 'vue'
import TimeAdjuster from '@/components/TimeAdjuster.vue'
import { useEntryLogger } from '@/composables/useEntryLogger'

const props = defineProps({ babyId: { type: Number, required: true } })
const emit = defineEmits(['saved'])

const { submit, isSubmitting } = useEntryLogger('growths')

const when = ref(new Date())
const weightGrams = ref(null)
const lengthCm = ref(null)
const headCm = ref(null)

async function save() {
  const result = await submit(props.babyId, {
    measured_at: when.value.toISOString(),
    weight_grams: weightGrams.value || null,
    length_cm: lengthCm.value || null,
    head_circumference_cm: headCm.value || null,
  })
  emit('saved', result)
}
</script>

<template>
  <div>
    <div class="field">
      <label>Weight (grams)</label>
      <input v-model.number="weightGrams" type="number" min="0" max="30000" step="10" placeholder="e.g. 3600" />
    </div>
    <div class="field">
      <label>Length (cm)</label>
      <input v-model.number="lengthCm" type="number" min="0" max="120" step="0.1" placeholder="e.g. 51.5" />
    </div>
    <div class="field">
      <label>Head circumference (cm)</label>
      <input v-model.number="headCm" type="number" min="0" max="60" step="0.1" placeholder="e.g. 35.0" />
    </div>

    <TimeAdjuster v-model="when" />

    <button class="btn btn-primary btn-block" :disabled="isSubmitting" @click="save">
      {{ isSubmitting ? 'Saving…' : 'Log measurement' }}
    </button>
  </div>
</template>
