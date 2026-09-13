<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { onSyncStateChange, pendingCount } from '@/offline/sync'
import { useTheme } from '@/composables/useTheme'

const route = useRoute()
const isOnline = ref(navigator.onLine)
const pending = ref(0)
const { theme, toggleTheme } = useTheme()

function updateOnline() {
  isOnline.value = navigator.onLine
}

onMounted(async () => {
  window.addEventListener('online', updateOnline)
  window.addEventListener('offline', updateOnline)
  pending.value = await pendingCount()
  onSyncStateChange((state) => {
    pending.value = state.remaining
  })
})
</script>

<template>
  <div class="app-shell">
    <button
      class="theme-toggle"
      type="button"
      :aria-label="theme === 'light' ? 'Switch to dark mode' : 'Switch to light mode'"
      @click="toggleTheme"
    >
      {{ theme === 'light' ? '🌙' : '☀️' }}
    </button>

    <div v-if="!isOnline || pending > 0" class="toast" :class="{ warning: !isOnline }" style="position: sticky; top: 0; left: auto; right: auto; margin: 0 0 8px;">
      <span v-if="!isOnline">Offline — entries are being saved on this device</span>
      <span v-else>Syncing {{ pending }} saved {{ pending === 1 ? 'entry' : 'entries' }}…</span>
    </div>

    <router-view />

    <nav v-if="!route.meta.public" class="bottom-nav">
      <div class="bottom-nav-inner">
        <router-link to="/">🏠<br />Log</router-link>
        <router-link to="/history">🕘<br />History</router-link>
        <router-link to="/charts">📈<br />Charts</router-link>
        <router-link to="/settings">⚙️<br />Settings</router-link>
      </div>
    </nav>
  </div>
</template>
