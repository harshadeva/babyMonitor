<script setup>
import { onMounted, ref } from 'vue'
import RemarkField from '@/components/RemarkField.vue'
import TimeAdjuster from '@/components/TimeAdjuster.vue'
import { useEntryLogger } from '@/composables/useEntryLogger'
import { useEntryEditor } from '@/composables/useEntryEditor'
import { getSetting, setSetting } from '@/offline/db'

const props = defineProps({
  babyId: { type: Number, required: true },
  record: { type: Object, default: null },
})
const emit = defineEmits(['saved', 'close'])
const isEditing = !!props.record

const { submit, isSubmitting } = useEntryLogger('feedings')
const { saveEdit, isSaving } = useEntryEditor('feedings')

const active = ref(null) // { type: 'breast', side, started_at }
const type = ref(isEditing ? props.record.type : 'breast')
const side = ref(isEditing && props.record.side ? props.record.side : 'left')
const volume = ref(isEditing && props.record.volume_ml != null ? props.record.volume_ml : 30)
const contents = ref(isEditing && props.record.contents ? props.record.contents : 'formula')
const endTime = ref(new Date())
const bottleTime = ref(new Date())
const notes = ref(isEditing ? props.record.notes || '' : '')

// "Log a feed that already happened" — for when it's logged from memory, after the fact.
const loggingPast = ref(false)
const pastStart = ref(new Date(Date.now() - 10 * 60_000))
const pastEnd = ref(new Date())

// Edit mode only: a completed feeding's start/end times, shown together
// rather than through the live start/end flow below (which is for new feeds).
const editStart = ref(isEditing ? new Date(props.record.started_at) : new Date())
const editEnd = ref(isEditing && props.record.ended_at ? new Date(props.record.ended_at) : new Date())

async function saveEditedFeeding() {
  const payload = {
    type: type.value,
    started_at: editStart.value.toISOString(),
    ended_at: editEnd.value.toISOString(),
    side: type.value === 'breast' ? side.value : null,
    volume_ml: type.value === 'bottle' ? volume.value : null,
    contents: type.value === 'bottle' ? contents.value : null,
    notes: notes.value || null,
  }
  emit('saved', await saveEdit(props.record.id, payload))
}

onMounted(async () => {
  if (isEditing) return
  const stored = await getSetting('active_feeding')
  if (stored) {
    active.value = stored
    endTime.value = new Date()
  }
})

async function startBreastFeed() {
  const session = { type: 'breast', side: side.value, started_at: new Date().toISOString() }
  await setSetting('active_feeding', session)
  active.value = session
  endTime.value = new Date()
}

async function endBreastFeed() {
  const result = await submit(props.babyId, {
    type: 'breast',
    side: active.value.side,
    started_at: active.value.started_at,
    ended_at: endTime.value.toISOString(),
    notes: notes.value || null,
  })
  await setSetting('active_feeding', null)
  emit('saved', result)
}

function openLogPast() {
  pastStart.value = new Date(Date.now() - 10 * 60_000)
  pastEnd.value = new Date()
  loggingPast.value = true
}

async function logPastBreastFeed() {
  const result = await submit(props.babyId, {
    type: 'breast',
    side: side.value,
    started_at: pastStart.value.toISOString(),
    ended_at: pastEnd.value.toISOString(),
    notes: notes.value || null,
  })
  loggingPast.value = false
  emit('saved', result)
}

async function logBottle() {
  const result = await submit(props.babyId, {
    type: 'bottle',
    volume_ml: volume.value,
    contents: contents.value,
    started_at: bottleTime.value.toISOString(),
    ended_at: bottleTime.value.toISOString(),
    notes: notes.value || null,
  })
  emit('saved', result)
}
</script>

<template>
  <div>
    <template v-if="isEditing">
      <div class="segmented" style="margin-bottom: 16px;">
        <button type="button" :class="{ active: type === 'breast' }" @click="type = 'breast'">Breast</button>
        <button type="button" :class="{ active: type === 'bottle' }" @click="type = 'bottle'">Bottle</button>
      </div>

      <div v-if="type === 'breast'" class="field">
        <label>Side</label>
        <div class="segmented">
          <button type="button" :class="{ active: side === 'left' }" @click="side = 'left'">Left</button>
          <button type="button" :class="{ active: side === 'right' }" @click="side = 'right'">Right</button>
          <button type="button" :class="{ active: side === 'both' }" @click="side = 'both'">Both</button>
        </div>
      </div>

      <template v-else>
        <div class="field">
          <label>Contents</label>
          <div class="segmented">
            <button type="button" :class="{ active: contents === 'formula' }" @click="contents = 'formula'">Formula</button>
            <button type="button" :class="{ active: contents === 'expressed_milk' }" @click="contents = 'expressed_milk'">Breast milk</button>
          </div>
        </div>
        <div class="field">
          <label>Volume (ml)</label>
          <input v-model.number="volume" type="number" min="0" max="500" step="5" />
        </div>
      </template>

      <TimeAdjuster v-model="editStart" label="Started" />
      <TimeAdjuster v-model="editEnd" label="Ended" />
      <RemarkField v-model="notes" />

      <button class="btn btn-primary btn-block" :disabled="isSaving" @click="saveEditedFeeding">
        {{ isSaving ? 'Saving…' : 'Save changes' }}
      </button>
    </template>

    <template v-else-if="active">
      <p class="muted">Breastfeeding started at {{ new Date(active.started_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }} · side: {{ active.side }}</p>
      <TimeAdjuster v-model="endTime" label="Ended" />
      <RemarkField v-model="notes" />
      <button class="btn btn-primary btn-block" :disabled="isSubmitting" @click="endBreastFeed">
        {{ isSubmitting ? 'Saving…' : 'End feeding' }}
      </button>
    </template>

    <template v-else>
      <div class="segmented" style="margin-bottom: 16px;">
        <button type="button" :class="{ active: type === 'breast' }" @click="type = 'breast'; loggingPast = false">Breast</button>
        <button type="button" :class="{ active: type === 'bottle' }" @click="type = 'bottle'; loggingPast = false">Bottle</button>
      </div>

      <template v-if="type === 'breast'">
        <div class="field">
          <label>Side</label>
          <div class="segmented">
            <button type="button" :class="{ active: side === 'left' }" @click="side = 'left'">Left</button>
            <button type="button" :class="{ active: side === 'right' }" @click="side = 'right'">Right</button>
            <button type="button" :class="{ active: side === 'both' }" @click="side = 'both'">Both</button>
          </div>
        </div>

        <template v-if="!loggingPast">
          <button class="btn btn-primary btn-block" style="margin-bottom: 10px;" @click="startBreastFeed">Start feeding now</button>
          <button class="btn btn-secondary btn-block" @click="openLogPast">Log a feed that already happened</button>
        </template>

        <template v-else>
          <TimeAdjuster v-model="pastStart" label="Started" />
          <TimeAdjuster v-model="pastEnd" label="Ended" />
          <RemarkField v-model="notes" />
          <button class="btn btn-primary btn-block" style="margin-bottom: 10px;" :disabled="isSubmitting" @click="logPastBreastFeed">
            {{ isSubmitting ? 'Saving…' : 'Save feed' }}
          </button>
          <button class="btn btn-secondary btn-block" @click="loggingPast = false">Back</button>
        </template>
      </template>

      <template v-else>
        <div class="field">
          <label>Contents</label>
          <div class="segmented">
            <button type="button" :class="{ active: contents === 'formula' }" @click="contents = 'formula'">Formula</button>
            <button type="button" :class="{ active: contents === 'expressed_milk' }" @click="contents = 'expressed_milk'">Breast milk</button>
          </div>
        </div>
        <div class="field">
          <label>Volume (ml)</label>
          <input v-model.number="volume" type="number" min="0" max="500" step="5" />
        </div>
        <TimeAdjuster v-model="bottleTime" />
        <RemarkField v-model="notes" />
        <button class="btn btn-primary btn-block" :disabled="isSubmitting" @click="logBottle">
          {{ isSubmitting ? 'Saving…' : 'Log bottle feed' }}
        </button>
      </template>
    </template>
  </div>
</template>
