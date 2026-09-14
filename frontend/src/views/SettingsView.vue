<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import ToggleSwitch from '@/components/ToggleSwitch.vue'
import { useTheme } from '@/composables/useTheme'
import { TRACKERS } from '@/constants/trackers'
import { MAX_HOURS, MIN_HOURS } from '@/constants/reminders'
import { getReminderSettings, setReminderSetting } from '@/offline/db'
import { apiClient } from '@/api/client'
import { useBabyStore } from '@/stores/baby'

const { theme, toggleTheme } = useTheme()
const babyStore = useBabyStore()

const reminders = reactive({})
const exporting = ref(false)

// Irregular, one-off trackers (e.g. milestones) opt out via `remindable:
// false` — a "remind me every N hours" cadence doesn't make sense for them.
const remindableTrackers = computed(() =>
  Object.fromEntries(Object.entries(TRACKERS).filter(([, meta]) => meta.remindable !== false))
)

onMounted(async () => {
  const stored = await getReminderSettings()
  Object.assign(reminders, stored)
  await babyStore.load()
})

async function exportData() {
  if (exporting.value) return
  exporting.value = true
  try {
    const { data } = await apiClient.get(`/api/babies/${babyStore.currentBabyId}/export`)
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' })
    const url = URL.createObjectURL(blob)
    const babySlug = (babyStore.currentBaby?.name || 'baby').toLowerCase().replace(/[^a-z0-9]+/g, '-')
    const a = document.createElement('a')
    a.href = url
    a.download = `babymonitor-${babySlug}-${new Date().toISOString().slice(0, 10)}.json`
    document.body.appendChild(a)
    a.click()
    a.remove()
    URL.revokeObjectURL(url)
  } finally {
    exporting.value = false
  }
}

async function toggleReminder(key) {
  reminders[key] = { ...reminders[key], enabled: !reminders[key].enabled }
  await setReminderSetting(key, { enabled: reminders[key].enabled })
}

async function adjustHours(key, delta) {
  const next = Math.min(MAX_HOURS, Math.max(MIN_HOURS, reminders[key].hours + delta))
  reminders[key] = { ...reminders[key], hours: next }
  await setReminderSetting(key, { hours: next })
}
</script>

<template>
  <div class="screen">
    <div class="top-bar">
      <h1>Settings</h1>
    </div>

    <div class="card" style="margin-bottom: 16px;">
      <router-link to="/account" class="settings-row settings-link-row">
        <div class="history-icon" style="background: var(--color-surface-soft);">👤</div>
        <div class="settings-row-body">
          <div class="settings-row-title">Account &amp; baby</div>
          <div class="muted">Profile, password, baby details, log out</div>
        </div>
        <span class="chevron">›</span>
      </router-link>
    </div>

    <div class="settings-section-title">Appearance</div>
    <div class="card">
      <div class="settings-row">
        <div class="history-icon" style="background: var(--color-surface-soft);">{{ theme === 'light' ? '☀️' : '🌙' }}</div>
        <div class="settings-row-body">
          <div class="settings-row-title">Dark mode</div>
          <div class="muted">{{ theme === 'light' ? 'Off — bright and cheerful' : 'On — easier on the eyes at night' }}</div>
        </div>
        <ToggleSwitch :model-value="theme === 'dark'" aria-label="Toggle dark mode" @update:model-value="toggleTheme" />
      </div>
    </div>

    <div class="settings-section-title">Reminders</div>
    <p class="muted" style="margin: 0 0 10px 4px;">
      Get a gentle nudge on the Log screen if it's been a while since the last entry for these.
    </p>
    <div class="card">
      <div v-for="(meta, key) in remindableTrackers" :key="key" class="settings-row" style="align-items: flex-start;">
        <div class="history-icon" :style="{ background: meta.color + '2e' }">{{ meta.emoji }}</div>
        <div class="settings-row-body">
          <div class="settings-row-title">{{ meta.label }}</div>
          <div v-if="reminders[key]?.enabled" class="settings-hours-row">
            <button type="button" class="settings-hours-btn" @click="adjustHours(key, -1)">−</button>
            <span class="settings-hours-value">every {{ reminders[key].hours }}h</span>
            <button type="button" class="settings-hours-btn" @click="adjustHours(key, 1)">+</button>
          </div>
          <div v-else class="muted">No reminder</div>
        </div>
        <ToggleSwitch
          v-if="reminders[key]"
          :model-value="reminders[key].enabled"
          :aria-label="`Toggle ${meta.label} reminder`"
          @update:model-value="toggleReminder(key)"
        />
      </div>
    </div>

    <div class="settings-section-title">Data</div>
    <p class="muted" style="margin: 0 0 10px 4px;">
      Download every logged activity for this baby as a single JSON file — for your own backup or to hand to someone else.
    </p>
    <div class="card">
      <button type="button" class="btn btn-secondary btn-block" :disabled="exporting" @click="exportData">
        {{ exporting ? 'Preparing…' : '⬇️ Export data (JSON)' }}
      </button>
    </div>
  </div>
</template>
