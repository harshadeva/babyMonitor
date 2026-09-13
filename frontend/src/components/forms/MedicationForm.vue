<script setup>
import { ref } from 'vue'
import RemarkField from '@/components/RemarkField.vue'
import TimeAdjuster from '@/components/TimeAdjuster.vue'
import { COMMON_MEDICATIONS } from '@/constants/options'
import { useEntryLogger } from '@/composables/useEntryLogger'

const props = defineProps({ babyId: { type: Number, required: true } })
const emit = defineEmits(['saved'])

const { submit, isSubmitting } = useEntryLogger('medications')

const when = ref(new Date())
const name = ref(COMMON_MEDICATIONS[0])
const customName = ref('')
const dose = ref('')
const notes = ref('')

async function save() {
  const result = await submit(props.babyId, {
    given_at: when.value.toISOString(),
    name: name.value === '__custom' ? customName.value : name.value,
    dose: dose.value || null,
    notes: notes.value || null,
  })
  emit('saved', result)
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

    <button class="btn btn-primary btn-block" :disabled="isSubmitting" @click="save">
      {{ isSubmitting ? 'Saving…' : 'Log dose' }}
    </button>
  </div>
</template>
