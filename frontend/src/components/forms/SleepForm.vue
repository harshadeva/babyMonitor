<script setup>
import { onMounted, ref } from 'vue'
import RemarkField from '@/components/RemarkField.vue'
import TimeAdjuster from '@/components/TimeAdjuster.vue'
import { apiClient, ensureCsrfCookie } from '@/api/client'
import { useEntryLogger } from '@/composables/useEntryLogger'
import { generateUuid } from '@/utils/uuid'
import { getSetting, setSetting } from '@/offline/db'

const props = defineProps({ babyId: { type: Number, required: true } })
const emit = defineEmits(['saved'])

const { submit, isSubmitting } = useEntryLogger('sleeps')

// active.id is set once the server knows about the session — from then on,
// every caregiver watching this baby sees it via the shared sleeps list, and
// ending it means updating that same row rather than creating a new one.
const active = ref(null)
const endTime = ref(new Date())
const notes = ref('')
const loadingActive = ref(true)
const isStarting = ref(false)
const isEnding = ref(false)

// "Log a sleep that already happened" — for when it's logged from memory, after the fact.
const loggingPast = ref(false)
const pastStart = ref(new Date(Date.now() - 60 * 60_000))
const pastEnd = ref(new Date())
const stillAsleep = ref(false)

onMounted(async () => {
  await refreshActive()
})

async function refreshActive() {
  loadingActive.value = true
  try {
    if (navigator.onLine) {
      const { data } = await apiClient.get(`/api/babies/${props.babyId}/sleeps`)
      const latest = data.data?.[0]
      if (latest && !latest.ended_at) {
        active.value = latest
        endTime.value = new Date()
        return
      }
    }
  } catch {
    // Couldn't reach the server — fall back to whatever this device knows below.
  } finally {
    loadingActive.value = false
  }

  // Offline, or the server has no open session — fall back to a session
  // this device started itself while offline (not yet synced).
  const stored = await getSetting('active_sleep')
  if (stored) {
    active.value = stored
    endTime.value = new Date()
  }
}

/**
 * Opens a sleep session on the server (no ended_at yet) so any other
 * caregiver watching this baby immediately sees "asleep now" too. Falls back
 * to purely local tracking if the request can't reach the server — no
 * network write is queued in that case, since the eventual wake-up will
 * submit one complete record instead (avoids ever creating two rows for one
 * nap).
 */
async function beginSleep(startedAtIso) {
  try {
    await ensureCsrfCookie()
    const { data } = await apiClient.post(`/api/babies/${props.babyId}/sleeps`, {
      client_uuid: generateUuid(),
      started_at: startedAtIso,
    })
    active.value = data.data
    await setSetting('active_sleep', null)
    return
  } catch {
    // offline, or unreachable — fall back below
  }

  const session = { started_at: startedAtIso }
  await setSetting('active_sleep', session)
  active.value = session
}

async function startSleep() {
  if (isStarting.value) return
  isStarting.value = true
  try {
    await beginSleep(new Date().toISOString())
    endTime.value = new Date()
  } finally {
    isStarting.value = false
  }
}

async function endSleep() {
  if (isEnding.value) return
  isEnding.value = true
  try {
    if (active.value?.id) {
      // Backend-tracked session — update the same row so every caregiver sees it end.
      await ensureCsrfCookie()
      const { data } = await apiClient.patch(`/api/sleeps/${active.value.id}`, {
        ended_at: endTime.value.toISOString(),
        notes: notes.value || null,
      })
      await setSetting('active_sleep', null)
      emit('saved', { synced: true, data: data.data })
    } else {
      // Started while offline, never reached the server — log it as one
      // complete record now (queued if we're still offline).
      const result = await submit(props.babyId, {
        started_at: active.value.started_at,
        ended_at: endTime.value.toISOString(),
        notes: notes.value || null,
      })
      await setSetting('active_sleep', null)
      emit('saved', result)
    }
  } catch (error) {
    emit('saved', {
      synced: false,
      error: error.response?.data || { message: "Could not reach the server — try again once you're back online." },
    })
  } finally {
    isEnding.value = false
  }
}

function openLogPast() {
  pastStart.value = new Date(Date.now() - 60 * 60_000)
  pastEnd.value = new Date()
  stillAsleep.value = false
  loggingPast.value = true
}

async function logPastSleep() {
  if (stillAsleep.value) {
    // Fell asleep at a known past time but hasn't woken up yet — opens the
    // same server-tracked session the live "Start sleep" flow uses, just
    // backdated, so it's visible to other caregivers too.
    await beginSleep(pastStart.value.toISOString())
    endTime.value = new Date()
    loggingPast.value = false
    return
  }

  const result = await submit(props.babyId, {
    started_at: pastStart.value.toISOString(),
    ended_at: pastEnd.value.toISOString(),
    notes: notes.value || null,
  })
  loggingPast.value = false
  emit('saved', result)
}
</script>

<template>
  <div>
    <p v-if="loadingActive" class="muted">Checking for an ongoing sleep…</p>

    <template v-else-if="active">
      <p class="muted">Asleep since {{ new Date(active.started_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}</p>
      <TimeAdjuster v-model="endTime" label="Ended" />
      <RemarkField v-model="notes" />
      <button class="btn btn-primary btn-block" :disabled="isEnding" @click="endSleep">
        {{ isEnding ? 'Saving…' : 'Wake up / end sleep' }}
      </button>
    </template>

    <template v-else-if="!loggingPast">
      <p class="muted" style="margin-bottom: 20px;">Tap when baby falls asleep. We'll ask again when they wake up.</p>
      <button class="btn btn-primary btn-block" style="margin-bottom: 10px;" :disabled="isStarting" @click="startSleep">
        {{ isStarting ? 'Starting…' : '😴 Start sleep now' }}
      </button>
      <button class="btn btn-secondary btn-block" @click="openLogPast">Log a sleep that already happened</button>
    </template>

    <template v-else>
      <TimeAdjuster v-model="pastStart" label="Fell asleep" />

      <div class="field">
        <label>Has baby woken up?</label>
        <div class="segmented">
          <button type="button" :class="{ active: !stillAsleep }" @click="stillAsleep = false">Yes</button>
          <button type="button" :class="{ active: stillAsleep }" @click="stillAsleep = true">Still asleep</button>
        </div>
      </div>

      <TimeAdjuster v-if="!stillAsleep" v-model="pastEnd" label="Woke up" />
      <p v-else class="muted" style="margin-bottom: 16px;">We'll show this as an ongoing nap until you log the wake-up time.</p>

      <RemarkField v-if="!stillAsleep" v-model="notes" />

      <button class="btn btn-primary btn-block" style="margin-bottom: 10px;" :disabled="isSubmitting" @click="logPastSleep">
        {{ isSubmitting ? 'Saving…' : stillAsleep ? 'Save (still sleeping)' : 'Save sleep' }}
      </button>
      <button class="btn btn-secondary btn-block" @click="loggingPast = false">Back</button>
    </template>
  </div>
</template>
