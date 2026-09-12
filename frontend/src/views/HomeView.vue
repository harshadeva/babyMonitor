<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import BottomSheet from '@/components/BottomSheet.vue'
import FeedingForm from '@/components/forms/FeedingForm.vue'
import SleepForm from '@/components/forms/SleepForm.vue'
import DiaperForm from '@/components/forms/DiaperForm.vue'
import TemperatureForm from '@/components/forms/TemperatureForm.vue'
import GrowthForm from '@/components/forms/GrowthForm.vue'
import MedicationForm from '@/components/forms/MedicationForm.vue'
import SymptomForm from '@/components/forms/SymptomForm.vue'
import { apiClient } from '@/api/client'
import { useBabyStore } from '@/stores/baby'
import { getSetting } from '@/offline/db'

const babyStore = useBabyStore()
const activeSheet = ref(null)
const toast = ref(null)
const activeFeeding = ref(null)
const activeSleep = ref(null)

const trackers = [
  { key: 'feedings', label: 'Feed', emoji: '🍼', component: FeedingForm },
  { key: 'sleeps', label: 'Sleep', emoji: '😴', component: SleepForm },
  { key: 'diapers', label: 'Diaper', emoji: '🧷', component: DiaperForm },
  { key: 'temperatures', label: 'Temperature', emoji: '🌡️', component: TemperatureForm },
  { key: 'growths', label: 'Growth', emoji: '📏', component: GrowthForm },
  { key: 'medications', label: 'Medicine', emoji: '💊', component: MedicationForm },
  { key: 'symptoms', label: 'Symptom', emoji: '📝', component: SymptomForm },
]

const lastEntries = reactive({})

const activeTracker = computed(() => trackers.find((t) => t.key === activeSheet.value))

function relativeTime(iso) {
  if (!iso) return null
  const diffMin = Math.round((Date.now() - new Date(iso).getTime()) / 60_000)
  if (diffMin < 1) return 'just now'
  if (diffMin < 60) return `${diffMin}m ago`
  const hours = Math.floor(diffMin / 60)
  if (hours < 24) return `${hours}h ago`
  return `${Math.floor(hours / 24)}d ago`
}

function subCaption(tracker) {
  if (tracker.key === 'feedings' && activeFeeding.value) return 'Feeding now…'
  if (tracker.key === 'sleeps' && activeSleep.value) return 'Asleep now…'
  const entry = lastEntries[tracker.key]
  if (!entry) return 'No entries yet'
  const at = entry.started_at || entry.occurred_at || entry.measured_at || entry.given_at
  return `Last: ${relativeTime(at)}`
}

async function refreshState() {
  activeFeeding.value = await getSetting('active_feeding')
  activeSleep.value = await getSetting('active_sleep')
}

async function loadLastEntries() {
  if (!babyStore.currentBabyId || !navigator.onLine) return
  await Promise.allSettled(
    trackers.map(async (t) => {
      const { data } = await apiClient.get(`/api/babies/${babyStore.currentBabyId}/${t.key}`)
      if (data.data?.[0]) lastEntries[t.key] = data.data[0]
    })
  )
}

onMounted(async () => {
  await babyStore.load()
  await refreshState()
  await loadLastEntries()
})

function openSheet(key) {
  activeSheet.value = key
}

function closeSheet() {
  activeSheet.value = null
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
        @click="openSheet(t.key)"
      >
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
