<script setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue'
import BottomSheet from '@/components/BottomSheet.vue'
import FeedingForm from '@/components/forms/FeedingForm.vue'
import SleepForm from '@/components/forms/SleepForm.vue'
import DiaperForm from '@/components/forms/DiaperForm.vue'
import TemperatureForm from '@/components/forms/TemperatureForm.vue'
import GrowthForm from '@/components/forms/GrowthForm.vue'
import MedicationForm from '@/components/forms/MedicationForm.vue'
import SymptomForm from '@/components/forms/SymptomForm.vue'
import MilestoneForm from '@/components/forms/MilestoneForm.vue'
import { apiClient } from '@/api/client'
import { useBabyStore } from '@/stores/baby'
import { getReminderSettings } from '@/offline/db'
import { TRACKERS } from '@/constants/trackers'

const babyStore = useBabyStore()
const activeSheet = ref(null)
const toast = ref(null)
const reminderSettings = ref({})

const FORM_COMPONENTS = {
  feedings: FeedingForm,
  sleeps: SleepForm,
  diapers: DiaperForm,
  temperatures: TemperatureForm,
  growths: GrowthForm,
  medications: MedicationForm,
  symptoms: SymptomForm,
  milestones: MilestoneForm,
}

const trackers = Object.keys(TRACKERS).map((key) => ({
  key,
  ...TRACKERS[key],
  component: FORM_COMPONENTS[key],
}))

const lastEntries = reactive({})

// Sleep and (breastfeed) feeding are tracked on the server (not this
// device's local storage) precisely so a session started on one caregiver's
// phone shows as live on the other's — see lastEntries.sleeps/feedings, kept
// fresh by loadLastEntries below.
const activeSleep = computed(() => {
  const entry = lastEntries.sleeps
  return entry && !entry.ended_at ? entry : null
})

const activeFeeding = computed(() => {
  const entry = lastEntries.feedings
  return entry && !entry.ended_at ? entry : null
})

const activeTracker = computed(() => trackers.find((t) => t.key === activeSheet.value))

// Ticks every second so active-session tiles show a live-updating elapsed time.
const now = ref(Date.now())
let tickInterval = null
onMounted(() => {
  tickInterval = setInterval(() => {
    now.value = Date.now()
  }, 1000)
})
onUnmounted(() => {
  if (tickInterval) clearInterval(tickInterval)
  if (lastEntriesInterval) clearInterval(lastEntriesInterval)
  document.removeEventListener('visibilitychange', handleVisibilityChange)
})

function relativeTime(iso) {
  if (!iso) return null
  const diffMin = Math.round((Date.now() - new Date(iso).getTime()) / 60_000)
  if (diffMin < 1) return 'just now'
  if (diffMin < 60) return `${diffMin}m ago`
  const hours = Math.floor(diffMin / 60)
  if (hours < 24) return `${hours}h ago`
  return `${Math.floor(hours / 24)}d ago`
}

function elapsedLabel(startedAtIso) {
  const totalSeconds = Math.max(0, Math.floor((now.value - new Date(startedAtIso).getTime()) / 1000))
  const h = Math.floor(totalSeconds / 3600)
  const m = Math.floor((totalSeconds % 3600) / 60)
  const s = totalSeconds % 60
  if (h > 0) return `${h}h ${m}m`
  if (m > 0) return `${m}m ${s}s`
  return `${s}s`
}

function isLive(tracker) {
  return (
    (tracker.key === 'feedings' && !!activeFeeding.value) ||
    (tracker.key === 'sleeps' && !!activeSleep.value)
  )
}

function entryTimestamp(tracker) {
  const entry = lastEntries[tracker.key]
  return entry ? entry.started_at || entry.occurred_at || entry.measured_at || entry.given_at : null
}

function isOverdue(tracker) {
  if (isLive(tracker)) return false // an ongoing session isn't "overdue"
  const setting = reminderSettings.value[tracker.key]
  if (!setting?.enabled) return false
  const at = entryTimestamp(tracker)
  if (!at) return false
  const elapsedHours = (now.value - new Date(at).getTime()) / 3_600_000
  return elapsedHours > setting.hours
}

function subCaption(tracker) {
  if (tracker.key === 'feedings' && activeFeeding.value) {
    return `Feeding · ${elapsedLabel(activeFeeding.value.started_at)}`
  }
  if (tracker.key === 'sleeps' && activeSleep.value) {
    return `Asleep · ${elapsedLabel(activeSleep.value.started_at)}`
  }
  const at = entryTimestamp(tracker)
  if (!at) return 'No entries yet'
  const label = `Last: ${relativeTime(at)}`
  return isOverdue(tracker) ? `⏰ ${label}` : label
}

async function refreshState() {
  reminderSettings.value = await getReminderSettings()
}

// Sleep and feeding sessions can have an open (still-ongoing) record that
// was logged backdated — e.g. "started an hour ago, still going" — which may
// not be the most recent row by started_at if something else was logged more
// recently. Always prefer the open session so the live indicator doesn't
// miss it; otherwise fall back to the newest row.
const OPEN_SESSION_TRACKERS = ['sleeps', 'feedings']

async function loadLastEntries() {
  if (!babyStore.currentBabyId || !navigator.onLine) return
  await Promise.allSettled(
    trackers.map(async (t) => {
      const { data } = await apiClient.get(`/api/babies/${babyStore.currentBabyId}/${t.key}`)
      const rows = data.data ?? []
      if (rows.length === 0) return

      lastEntries[t.key] = (OPEN_SESSION_TRACKERS.includes(t.key) && rows.find((r) => !r.ended_at)) || rows[0]
    })
  )
}

// Another caregiver's new entry (or a sleep they just started/ended) only
// reaches this device through a refetch — without one, a "father"'s screen
// can keep showing a stale overdue alert (or a stale sleep timer) for
// something the "mother" already logged. Re-check periodically and whenever
// the app is brought back to the foreground, not just on first load.
let lastEntriesInterval = null
function handleVisibilityChange() {
  if (document.visibilityState === 'visible') loadLastEntries()
}

onMounted(async () => {
  await babyStore.load()
  await refreshState()
  await loadLastEntries()
  lastEntriesInterval = setInterval(loadLastEntries, 120_000)
  document.addEventListener('visibilitychange', handleVisibilityChange)
})

function openSheet(key) {
  activeSheet.value = key
}

async function closeSheet() {
  activeSheet.value = null
  // Starting/ending a feed or sleep doesn't always fire a 'saved' event
  // (e.g. starting is a bare state change), so both refreshes are needed
  // here too, not just after a full save — their live state lives in
  // lastEntries now, not local storage.
  await refreshState()
  await loadLastEntries()
}

async function onSaved(result) {
  if (result?.error) {
    // Keep the sheet open so the entry isn't lost — let the user fix it and retry.
    toast.value = { type: 'warning', message: 'Not saved — please check the entry and try again.' }
    setTimeout(() => (toast.value = null), 4500)
    return
  }

  closeSheet()
  await refreshState()
  await loadLastEntries()

  if (result?.queued) {
    toast.value = { type: 'warning', message: "Saved on this device — will sync when you're back online." }
  } else if (result?.possibleDuplicateOf) {
    toast.value = { type: 'warning', message: 'Heads up — a very similar entry was logged nearby in time.' }
  } else if (result?.meta?.is_fever) {
    toast.value = { type: 'warning', message: '🌡️ That reading is in fever range.' }
  } else if (result?.data?.flagged_for_doctor) {
    toast.value = { type: 'warning', message: '⚠️ That stool color is worth mentioning to your doctor.' }
  } else {
    toast.value = { type: 'ok', message: 'Logged ✓' }
  }

  setTimeout(() => (toast.value = null), 3500)
}
</script>

<template>
  <div class="screen">
    <div class="top-bar">
      <h1>{{ babyStore.currentBaby?.name || 'Baby Monitor' }}</h1>
    </div>

    <div class="quick-grid">
      <button
        v-for="t in trackers"
        :key="t.key"
        class="quick-btn"
        :class="{ live: isLive(t), overdue: isOverdue(t) }"
        @click="openSheet(t.key)"
      >
        <span v-if="isLive(t)" class="live-dot" aria-hidden="true"></span>
        <span v-else-if="isOverdue(t)" class="overdue-dot" aria-hidden="true">⏰</span>
        <span class="emoji">{{ t.emoji }}</span>
        <span>{{ t.label }}</span>
        <span class="sub">{{ subCaption(t) }}</span>
      </button>
    </div>

    <div v-if="toast" class="toast" :class="{ warning: toast.type === 'warning' }" style="top: auto; bottom: 88px;">
      {{ toast.message }}
    </div>

    <BottomSheet v-if="activeTracker" :title="activeTracker.label" @close="closeSheet">
      <component
        :is="activeTracker.component"
        :baby-id="babyStore.currentBabyId"
        @saved="onSaved"
      />
    </BottomSheet>
  </div>
</template>
