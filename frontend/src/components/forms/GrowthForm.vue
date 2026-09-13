<script setup>
import { ref } from 'vue'
import RemarkField from '@/components/RemarkField.vue'
import TimeAdjuster from '@/components/TimeAdjuster.vue'
import { useEntryLogger } from '@/composables/useEntryLogger'
import { useEntryEditor } from '@/composables/useEntryEditor'

const props = defineProps({
  babyId: { type: Number, required: true },
  record: { type: Object, default: null },
})
const emit = defineEmits(['saved'])
const isEditing = !!props.record

const { submit, isSubmitting } = useEntryLogger('growths')
const { saveEdit, isSaving } = useEntryEditor('growths')

const when = ref(isEditing ? new Date(props.record.measured_at) : new Date())
const weightGrams = ref(isEditing ? props.record.weight_grams : null)
const lengthCm = ref(isEditing ? props.record.length_cm : null)
const headCm = ref(isEditing ? props.record.head_circumference_cm : null)
const notes = ref(isEditing ? props.record.notes || '' : '')

async function save() {
  const payload = {
    measured_at: when.value.toISOString(),
    weight_grams: weightGrams.value || null,
    length_cm: lengthCm.value || null,
    head_circumference_cm: headCm.value || null,
    notes: notes.value || null,
  }

  emit('saved', isEditing ? await saveEdit(props.record.id, payload) : await submit(props.babyId, payload))
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
    <RemarkField v-model="notes" />

    <button class="btn btn-primary btn-block" :disabled="isSubmitting || isSaving" @click="save">
      {{ (isSubmitting || isSaving) ? 'Saving…' : isEditing ? 'Save changes' : 'Log measurement' }}
    </button>
  </div>
</template>
