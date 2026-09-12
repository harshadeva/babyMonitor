<script setup>
import { onMounted, ref } from 'vue'
import TimeAdjuster from '@/components/TimeAdjuster.vue'
import { useEntryLogger } from '@/composables/useEntryLogger'
import { getSetting, setSetting } from '@/offline/db'

const props = defineProps({ babyId: { type: Number, required: true } })
const emit = defineEmits(['saved'])

const { submit, isSubmitting } = useEntryLogger('sleeps')

const active = ref(null)
const endTime = ref(new Date())

// "Log a sleep that already happened" — for when it's logged from memory, after the fact.
const loggingPast = ref(false)
const pastStart = ref(new Date(Date.now() - 60 * 60_000))
const pastEnd = ref(new Date())

onMounted(async () => {
  const stored = await getSetting('active_sleep')
  if (stored) {
    active.value = stored
    endTime.value = new Date()
  }
})

async function startSleep() {
  const session = { started_at: new Date().toISOString() }
  await setSetting('active_sleep', session)
  active.value = session
  endTime.value = new Date()
}

async function endSleep() {
  const result = await submit(props.babyId, {
    started_at: active.value.started_at,
    ended_at: endTime.value.toISOString(),
  })
  await setSetting('active_sleep', null)
  emit('saved', result)
}

function openLogPast() {
  pastStart.value = new Date(Date.now() - 60 * 60_000)
  pastEnd.value = new Date()
  loggingPast.value = true
}

async function logPastSleep() {
  const result = await submit(props.babyId, {
    started_at: pastStart.value.toISOString(),
    ended_at: pastEnd.value.toISOString(),
  })
  loggingPast.value = false
  emit('saved', result)
}
</script>

<template>
  <div>
    <template v-if="active">
      <p class="muted">Asleep since {{ new Date(active.started_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}</p>
      <TimeAdjuster v-model="endTime" label="Ended" />
      <button class="btn btn-primary btn-block" :disabled="isSubmitting" @click="endSleep">
        {{ isSubmitting ? 'Saving…' : 'Wake up / end sleep' }}
      </button>
    </template>

    <template v-else-if="!loggingPast">
      <p class="muted" style="margin-bottom: 20px;">Tap when baby falls asleep. We'll ask again when they wake up.</p>
      <button class="btn btn-primary btn-block" style="margin-bottom: 10px;" @click="startSleep">😴 Start sleep now</button>
      <button class="btn btn-secondary btn-block" @click="openLogPast">Log a sleep that already happened</button>
    </template>

    <template v-else>
      <TimeAdjuster v-model="pastStart" label="Fell asleep" />
      <TimeAdjuster v-model="pastEnd" label="Woke up" />
      <button class="btn btn-primary btn-block" style="margin-bottom: 10px;" :disabled="isSubmitting" @click="logPastSleep">
        {{ isSubmitting ? 'Saving…' : 'Save sleep' }}
      </button>
      <button class="btn btn-secondary btn-block" @click="loggingPast = false">Back</button>
    </template>
  </div>
</template>
