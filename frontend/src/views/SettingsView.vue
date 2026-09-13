<script setup>
import { onMounted, reactive } from 'vue'
import ToggleSwitch from '@/components/ToggleSwitch.vue'
import { useTheme } from '@/composables/useTheme'
import { TRACKERS } from '@/constants/trackers'
import { MAX_HOURS, MIN_HOURS } from '@/constants/reminders'
import { getReminderSettings, setReminderSetting } from '@/offline/db'

const { theme, toggleTheme } = useTheme()

const reminders = reactive({})

onMounted(async () => {
  const stored = await getReminderSettings()
  Object.assign(reminders, stored)
})

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
      <div v-for="(meta, key) in TRACKERS" :key="key" class="settings-row" style="align-items: flex-start;">
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
  </div>
</template>
