<script setup>
import { ref } from 'vue'
import RemarkField from '@/components/RemarkField.vue'
import TimeAdjuster from '@/components/TimeAdjuster.vue'
import { COMMON_MEDICATIONS } from '@/constants/options'
import { useEntryLogger } from '@/composables/useEntryLogger'
import { useEntryEditor } from '@/composables/useEntryEditor'

const props = defineProps({
  babyId: { type: Number, required: true },
  record: { type: Object, default: null },
})
const emit = defineEmits(['saved'])
const isEditing = !!props.record
const isKnownMedication = isEditing && COMMON_MEDICATIONS.includes(props.record.name)

const { submit, isSubmitting } = useEntryLogger('medications')
const { saveEdit, isSaving } = useEntryEditor('medications')

const when = ref(isEditing ? new Date(props.record.given_at) : new Date())
const name = ref(isEditing ? (isKnownMedication ? props.record.name : '__custom') : COMMON_MEDICATIONS[0])
const customName = ref(isEditing && !isKnownMedication ? props.record.name : '')
const dose = ref(isEditing ? props.record.dose || '' : '')
const notes = ref(isEditing ? props.record.notes || '' : '')

async function save() {
  const payload = {
    given_at: when.value.toISOString(),
    name: name.value === '__custom' ? customName.value : name.value,
    dose: dose.value || null,
    notes: notes.value || null,
  }

  emit('saved', isEditing ? await saveEdit(props.record.id, payload) : await submit(props.babyId, payload))
}
</script>

<template>
  <div>
    <div class="field">
      <label>Medication / vitamin</label>
      <select v-model="name">
        <option v-for="m in COMMON_MEDICATIONS" :key="m" :value="m">{{ m }}</option>
        <option value="__custom">Other…</option>
      </select>
    </div>
    <div v-if="name === '__custom'" class="field">
      <label>Name</label>
      <input v-model="customName" type="text" placeholder="Medication name" />
    </div>
    <div class="field">
      <label>Dose (optional)</label>
      <input v-model="dose" type="text" placeholder="e.g. 400 IU, 1 ml" />
    </div>

    <TimeAdjuster v-model="when" />
    <RemarkField v-model="notes" />

    <button class="btn btn-primary btn-block" :disabled="isSubmitting || isSaving" @click="save">
      {{ (isSubmitting || isSaving) ? 'Saving…' : isEditing ? 'Save changes' : 'Log dose' }}
    </button>
  </div>
</template>
