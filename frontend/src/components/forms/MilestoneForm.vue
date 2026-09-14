<script setup>
import { ref } from 'vue'
import RemarkField from '@/components/RemarkField.vue'
import TimeAdjuster from '@/components/TimeAdjuster.vue'
import { COMMON_MILESTONES } from '@/constants/options'
import { useEntryLogger } from '@/composables/useEntryLogger'
import { useEntryEditor } from '@/composables/useEntryEditor'

const props = defineProps({
  babyId: { type: Number, required: true },
  record: { type: Object, default: null },
})
const emit = defineEmits(['saved'])
const isEditing = !!props.record
const isKnownMilestone = isEditing && COMMON_MILESTONES.includes(props.record.title)

const { submit, isSubmitting } = useEntryLogger('milestones')
const { saveEdit, isSaving } = useEntryEditor('milestones')

const when = ref(isEditing ? new Date(props.record.occurred_at) : new Date())
const title = ref(isEditing ? (isKnownMilestone ? props.record.title : '__custom') : COMMON_MILESTONES[0])
const customTitle = ref(isEditing && !isKnownMilestone ? props.record.title : '')
const notes = ref(isEditing ? props.record.notes || '' : '')

async function save() {
  const payload = {
    occurred_at: when.value.toISOString(),
    title: title.value === '__custom' ? customTitle.value : title.value,
    notes: notes.value || null,
  }

  emit('saved', isEditing ? await saveEdit(props.record.id, payload) : await submit(props.babyId, payload))
}
</script>

<template>
  <div>
    <div class="field">
      <label>What happened?</label>
      <select v-model="title">
        <option v-for="m in COMMON_MILESTONES" :key="m" :value="m">{{ m }}</option>
        <option value="__custom">Something else…</option>
      </select>
    </div>
    <div v-if="title === '__custom'" class="field">
      <label>Name it</label>
      <input v-model="customTitle" type="text" placeholder="e.g. Blew a raspberry" />
    </div>

    <TimeAdjuster v-model="when" />
    <RemarkField v-model="notes" />

    <button class="btn btn-primary btn-block" :disabled="isSubmitting || isSaving" @click="save">
      {{ (isSubmitting || isSaving) ? 'Saving…' : isEditing ? 'Save changes' : '🌟 Log milestone' }}
    </button>
  </div>
</template>
