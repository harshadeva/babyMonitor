<script setup>
import { onMounted, ref } from 'vue'
import TimeAdjuster from '@/components/TimeAdjuster.vue'
import { useEntryLogger } from '@/composables/useEntryLogger'
import { getSetting, setSetting } from '@/offline/db'

const props = defineProps({ babyId: { type: Number, required: true } })
const emit = defineEmits(['saved', 'close'])

const { submit, isSubmitting } = useEntryLogger('feedings')

const active = ref(null) // { type: 'breast', side, started_at }
const type = ref('breast')
const side = ref('left')
const volume = ref(30)
const contents = ref('formula')
const endTime = ref(new Date())
const bottleTime = ref(new Date())

// "Log a feed that already happened" — for when it's logged from memory, after the fact.
const loggingPast = ref(false)
const pastStart = ref(new Date(Date.now() - 10 * 60_000))
const pastEnd = ref(new Date())

onMounted(async () => {
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
  })
  emit('saved', result)
}
</script>

<template>
  <div>
    <template v-if="active">
      <p class="muted">Breastfeeding started at {{ new Date(active.started_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }} · side: {{ active.side }}</p>
      <TimeAdjuster v-model="endTime" label="Ended" />
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
        <button class="btn btn-primary btn-block" :disabled="isSubmitting" @click="logBottle">
          {{ isSubmitting ? 'Saving…' : 'Log bottle feed' }}
        </button>
      </template>
    </template>
  </div>
</template>
