<script setup>
import { onMounted, ref } from 'vue'
import RemarkField from '@/components/RemarkField.vue'
import TimeAdjuster from '@/components/TimeAdjuster.vue'
import { apiClient, ensureCsrfCookie } from '@/api/client'
import { useEntryLogger } from '@/composables/useEntryLogger'
import { useEntryEditor } from '@/composables/useEntryEditor'
import { generateUuid } from '@/utils/uuid'
import { getSetting, setSetting } from '@/offline/db'

const props = defineProps({
  babyId: { type: Number, required: true },
  record: { type: Object, default: null },
})
const emit = defineEmits(['saved', 'close'])
const isEditing = !!props.record

const { submit, isSubmitting } = useEntryLogger('feedings')
const { saveEdit, isSaving } = useEntryEditor('feedings')

// active.id is set once the server knows about the session — from then on,
// every caregiver watching this baby sees it via the shared feedings list,
// and ending it means updating that same row rather than creating a new one.
const active = ref(null) // { id?, type: 'breast', side, started_at }
const type = ref(isEditing ? props.record.type : 'breast')
const side = ref(isEditing && props.record.side ? props.record.side : 'left')
const volume = ref(isEditing && props.record.volume_ml != null ? props.record.volume_ml : 30)
const contents = ref(isEditing && props.record.contents ? props.record.contents : 'formula')
const endTime = ref(new Date())
const bottleTime = ref(new Date())
const notes = ref(isEditing ? props.record.notes || '' : '')
const loadingActive = ref(!isEditing)
const isStarting = ref(false)
const isEnding = ref(false)

// "Log a feed that already happened" — for when it's logged from memory, after the fact.
const loggingPast = ref(false)
const pastStart = ref(new Date(Date.now() - 10 * 60_000))
const pastEnd = ref(new Date())
const stillFeeding = ref(false)
const isLoggingPast = ref(false)

// Edit mode only: a completed feeding's start/end times, shown together
// rather than through the live start/end flow below (which is for new feeds).
const editStart = ref(isEditing ? new Date(props.record.started_at) : new Date())
const editEnd = ref(isEditing && props.record.ended_at ? new Date(props.record.ended_at) : new Date())
const editStillFeeding = ref(isEditing && props.record.type === 'breast' ? !props.record.ended_at : false)

async function saveEditedFeeding() {
  const stillOpen = type.value === 'breast' && editStillFeeding.value
  const payload = {
    type: type.value,
    started_at: editStart.value.toISOString(),
    ended_at: stillOpen ? null : editEnd.value.toISOString(),
    side: type.value === 'breast' ? side.value : null,
    volume_ml: type.value === 'bottle' ? volume.value : null,
    contents: type.value === 'bottle' ? contents.value : null,
    notes: notes.value || null,
  }
  emit('saved', await saveEdit(props.record.id, payload))
}

onMounted(async () => {
  if (isEditing) return
  await refreshActive()
})

async function refreshActive() {
  loadingActive.value = true
  try {
    if (navigator.onLine) {
      const { data } = await apiClient.get(`/api/babies/${props.babyId}/feedings`)
      // A backdated "still feeding" entry can have an earlier started_at than
      // a since-completed feed, so it isn't necessarily the top of the
      // started_at-DESC list — search for the open one rather than assuming.
      const open = data.data?.find((r) => !r.ended_at)
      if (open) {
        active.value = open
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
  const stored = await getSetting('active_feeding')
  if (stored) {
    active.value = stored
    endTime.value = new Date()
  }
}

/**
 * Opens a breastfeed session on the server (no ended_at yet) so any other
 * caregiver watching this baby immediately sees "feeding now" too. Falls
 * back to purely local tracking if the request can't reach the server — no
 * network write is queued in that case, since the eventual end will submit
 * one complete record instead (avoids ever creating two rows for one feed).
 */
async function beginBreastFeed(startedAtIso, sideValue) {
  try {
    await ensureCsrfCookie()
    const { data } = await apiClient.post(`/api/babies/${props.babyId}/feedings`, {
      client_uuid: generateUuid(),
      type: 'breast',
      side: sideValue,
      started_at: startedAtIso,
    })
    active.value = data.data
    await setSetting('active_feeding', null)
    return { synced: true, data: data.data }
  } catch {
    // offline, or unreachable — fall back below
  }

  const session = { type: 'breast', side: sideValue, started_at: startedAtIso }
  await setSetting('active_feeding', session)
  active.value = session
  return { synced: false, queued: true, data: session }
}

async function startBreastFeed() {
  if (isStarting.value) return
  isStarting.value = true
  try {
    await beginBreastFeed(new Date().toISOString(), side.value)
    endTime.value = new Date()
  } finally {
    isStarting.value = false
  }
}

async function endBreastFeed() {
  if (isEnding.value) return
  isEnding.value = true
  try {
    if (active.value?.id) {
      // Backend-tracked session — update the same row so every caregiver sees it end.
      await ensureCsrfCookie()
      const { data } = await apiClient.patch(`/api/feedings/${active.value.id}`, {
        ended_at: endTime.value.toISOString(),
        notes: notes.value || null,
      })
      await setSetting('active_feeding', null)
      emit('saved', { synced: true, data: data.data })
    } else {
      // Started while offline, never reached the server — log it as one
      // complete record now (queued if we're still offline).
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
  pastStart.value = new Date(Date.now() - 10 * 60_000)
  pastEnd.value = new Date()
  stillFeeding.value = false
  loggingPast.value = true
}

async function logPastBreastFeed() {
  if (isLoggingPast.value) return
  isLoggingPast.value = true
  try {
    if (stillFeeding.value) {
      // Started at a known past time but hasn't finished yet — opens the same
      // server-tracked session the live "Start feeding now" flow uses, just
      // backdated, so it's visible to other caregivers too. Closes the sheet
      // straight away instead of dropping into the "end feeding" screen — the
      // caregiver is recording something from memory, not watching it run.
      const result = await beginBreastFeed(pastStart.value.toISOString(), side.value)
      loggingPast.value = false
      emit('saved', result)
      return
    }

    const result = await submit(props.babyId, {
      type: 'breast',
      side: side.value,
      started_at: pastStart.value.toISOString(),
      ended_at: pastEnd.value.toISOString(),
      notes: notes.value || null,
    })
    loggingPast.value = false
    emit('saved', result)
  } finally {
    isLoggingPast.value = false
  }
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

      <div v-if="type === 'breast'" class="field">
        <label>Is baby still feeding?</label>
        <div class="segmented">
          <button type="button" :class="{ active: !editStillFeeding }" @click="editStillFeeding = false">No</button>
          <button type="button" :class="{ active: editStillFeeding }" @click="editStillFeeding = true">Still feeding</button>
        </div>
      </div>

      <TimeAdjuster v-if="!(type === 'breast' && editStillFeeding)" v-model="editEnd" label="Ended" />
      <p v-else class="muted" style="margin-bottom: 16px;">This will show as an ongoing feed until an end time is logged.</p>

      <RemarkField v-model="notes" />

      <button class="btn btn-primary btn-block" :disabled="isSaving" @click="saveEditedFeeding">
        {{ isSaving ? 'Saving…' : 'Save changes' }}
      </button>
    </template>

    <p v-else-if="loadingActive" class="muted">Checking for an ongoing feed…</p>

    <template v-else-if="active">
      <p class="muted">Breastfeeding since {{ new Date(active.started_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }} · side: {{ active.side }}</p>
      <TimeAdjuster v-model="endTime" label="Ended" />
      <RemarkField v-model="notes" />
      <button class="btn btn-primary btn-block" :disabled="isEnding" @click="endBreastFeed">
        {{ isEnding ? 'Saving…' : 'End feeding' }}
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
          <button class="btn btn-primary btn-block" style="margin-bottom: 10px;" :disabled="isStarting" @click="startBreastFeed">
            {{ isStarting ? 'Starting…' : 'Start feeding now' }}
          </button>
          <button class="btn btn-secondary btn-block" @click="openLogPast">Log a feed that already happened</button>
        </template>

        <template v-else>
          <TimeAdjuster v-model="pastStart" label="Started" />

          <div class="field">
            <label>Is baby still feeding?</label>
            <div class="segmented">
              <button type="button" :class="{ active: !stillFeeding }" @click="stillFeeding = false">No</button>
              <button type="button" :class="{ active: stillFeeding }" @click="stillFeeding = true">Still feeding</button>
            </div>
          </div>

          <TimeAdjuster v-if="!stillFeeding" v-model="pastEnd" label="Ended" />
          <p v-else class="muted" style="margin-bottom: 16px;">We'll show this as an ongoing feed until you log the end time.</p>

          <RemarkField v-if="!stillFeeding" v-model="notes" />

          <button class="btn btn-primary btn-block" style="margin-bottom: 10px;" :disabled="isLoggingPast" @click="logPastBreastFeed">
            {{ isLoggingPast ? 'Saving…' : stillFeeding ? 'Save (still feeding)' : 'Save feed' }}
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
