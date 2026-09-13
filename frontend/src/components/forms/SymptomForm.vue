<script setup>
import { ref } from 'vue'
import RemarkField from '@/components/RemarkField.vue'
import TimeAdjuster from '@/components/TimeAdjuster.vue'
import { SYMPTOM_TAGS } from '@/constants/options'
import { useEntryLogger } from '@/composables/useEntryLogger'

const props = defineProps({ babyId: { type: Number, required: true } })
const emit = defineEmits(['saved'])

const { submit, isSubmitting } = useEntryLogger('symptoms')

const when = ref(new Date())
const tag = ref('fussy')
const notes = ref('')

async function save() {
  const result = await submit(props.babyId, {
    occurred_at: when.value.toISOString(),
    tag: tag.value,
    notes: notes.value || null,
  })
  emit('saved', result)
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

    <button class="btn btn-primary btn-block" :disabled="isSubmitting" @click="save">
      {{ isSubmitting ? 'Saving…' : 'Log note' }}
    </button>
  </div>
</template>
