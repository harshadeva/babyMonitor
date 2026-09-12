<script setup>
import { computed, onMounted, ref } from 'vue'
import { apiClient } from '@/api/client'
import { useBabyStore } from '@/stores/baby'

const babyStore = useBabyStore()
const loading = ref(true)
const items = ref([])

const TRACKER_META = {
  feedings: { label: 'Feed', emoji: '🍼', time: 'started_at' },
  sleeps: { label: 'Sleep', emoji: '😴', time: 'started_at' },
  diapers: { label: 'Diaper', emoji: '🧷', time: 'occurred_at' },
  temperatures: { label: 'Temp', emoji: '🌡️', time: 'measured_at' },
  growths: { label: 'Growth', emoji: '📏', time: 'measured_at' },
  medications: { label: 'Medicine', emoji: '💊', time: 'given_at' },
  symptoms: { label: 'Symptom', emoji: '📝', time: 'occurred_at' },
}

function summarize(entity, entry) {
  switch (entity) {
    case 'feedings':
      return entry.type === 'breast'
        ? `Breast (${entry.side})${entry.ended_at ? '' : ' — in progress'}`
        : `Bottle · ${entry.volume_ml}ml ${entry.contents === 'formula' ? 'formula' : 'breast milk'}`
    case 'sleeps':
      return entry.ended_at ? durationLabel(entry.started_at, entry.ended_at) : 'In progress'
    case 'diapers': {
      const parts = []
      if (entry.wet) parts.push('wet')
      if (entry.dirty) parts.push(`dirty${entry.stool_color_name ? ` (${entry.stool_color_name})` : ''}`)
      return parts.join(' + ') || '—'
    }
    case 'temperatures':
      return `${Number(entry.value_celsius).toFixed(1)}°C · ${entry.method}`
    case 'growths':
      return [
        entry.weight_grams ? `${entry.weight_grams}g` : null,
        entry.length_cm ? `${entry.length_cm}cm` : null,
        entry.head_circumference_cm ? `HC ${entry.head_circumference_cm}cm` : null,
      ].filter(Boolean).join(' · ')
    case 'medications':
      return `${entry.name}${entry.dose ? ` · ${entry.dose}` : ''}`
    case 'symptoms':
      return entry.tag.replace('_', ' ')
    default:
      return ''
  }
}

function durationLabel(start, end) {
  const min = Math.round((new Date(end) - new Date(start)) / 60_000)
  if (min < 60) return `${min} min`
  return `${Math.floor(min / 60)}h ${min % 60}m`
}

async function load() {
  loading.value = true
  const babyId = babyStore.currentBabyId
  const results = await Promise.allSettled(
    Object.keys(TRACKER_META).map((entity) =>
      apiClient.get(`/api/babies/${babyId}/${entity}`).then((r) => ({ entity, rows: r.data.data }))
    )
  )

  const merged = []
  for (const r of results) {
    if (r.status !== 'fulfilled') continue
    const { entity, rows } = r.value
    for (const row of rows) {
      merged.push({
        entity,
        id: row.id,
        at: row[TRACKER_META[entity].time],
        summary: summarize(entity, row),
        flagged: row.flagged_for_doctor || false,
      })
    }
  }
  merged.sort((a, b) => new Date(b.at) - new Date(a.at))
  items.value = merged
  loading.value = false
}

async function remove(item) {
  await apiClient.delete(`/api/${item.entity}/${item.id}`)
  items.value = items.value.filter((i) => !(i.entity === item.entity && i.id === item.id))
}

onMounted(async () => {
  await babyStore.load()
  await load()
})

const isOnline = computed(() => navigator.onLine)
</script>

<template>
  <div class="screen">
    <div class="top-bar">
      <h1>History</h1>
    </div>

    <p v-if="!isOnline" class="muted">You're offline — history needs a connection to load.</p>
    <p v-else-if="loading" class="muted">Loading…</p>
    <div v-else class="card">
      <p v-if="items.length === 0" class="muted">Nothing logged yet.</p>
      <div v-for="item in items" :key="item.entity + item.id" class="list-item">
        <div>
          <div style="font-weight:600;">
            {{ TRACKER_META[item.entity].emoji }} {{ TRACKER_META[item.entity].label }}
            <span v-if="item.flagged" class="pill alert">tell doctor</span>
          </div>
          <div class="muted">{{ item.summary }}</div>
        </div>
        <div style="text-align:right;">
          <div class="muted">{{ new Date(item.at).toLocaleString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) }}</div>
          <button class="btn btn-danger" style="min-height:36px; padding: 4px 12px; font-size:13px; margin-top:4px;" @click="remove(item)">Delete</button>
        </div>
      </div>
    </div>
  </div>
</template>
