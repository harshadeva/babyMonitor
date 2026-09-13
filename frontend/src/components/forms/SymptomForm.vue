<script setup>
import { ref } from 'vue'
import RemarkField from '@/components/RemarkField.vue'
import TimeAdjuster from '@/components/TimeAdjuster.vue'
import { SYMPTOM_TAGS } from '@/constants/options'
import { useEntryLogger } from '@/composables/useEntryLogger'
import { useEntryEditor } from '@/composables/useEntryEditor'

const props = defineProps({
  babyId: { type: Number, required: true },
  record: { type: Object, default: null },
})
const emit = defineEmits(['saved'])
const isEditing = !!props.record

const { submit, isSubmitting } = useEntryLogger('symptoms')
const { saveEdit, isSaving } = useEntryEditor('symptoms')

const when = ref(isEditing ? new Date(props.record.occurred_at) : new Date())
const tag = ref(isEditing ? props.record.tag : 'fussy')
const notes = ref(isEditing ? props.record.notes || '' : '')

async function save() {
  const payload = {
    occurred_at: when.value.toISOString(),
    tag: tag.value,
    notes: notes.value || null,
  }

  emit('saved', isEditing ? await saveEdit(props.record.id, payload) : await submit(props.babyId, payload))
}
</script>

<template>
  <div>
    <div class="field">
      <label>What did you notice?</label>
      <div class="color-swatch-grid">
        <div
          v-for="t in SYMPTOM_TAGS"
          :key="t.value"
          class="color-swatch"
          :class="{ selected: tag === t.value }"
          @click="tag = t.value"
        >
          <span style="font-size: 24px;">{{ t.emoji }}</span>
          <span class="color-name">{{ t.label }}</span>
        </div>
      </div>
    </div>
    <RemarkField v-model="notes" />

    <TimeAdjuster v-model="when" />

    <button class="btn btn-primary btn-block" :disabled="isSubmitting || isSaving" @click="save">
      {{ (isSubmitting || isSaving) ? 'Saving…' : isEditing ? 'Save changes' : 'Log note' }}
    </button>
  </div>
</template>
