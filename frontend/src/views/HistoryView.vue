<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import BottomSheet from '@/components/BottomSheet.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import HistoryDetailModal from '@/components/HistoryDetailModal.vue'
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
import { TRACKERS } from '@/constants/trackers'

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

const route = useRoute()
const babyStore = useBabyStore()
const loading = ref(true)
const items = ref([])
const pendingDelete = ref(null)
const selectedItem = ref(null)
const editingItem = ref(null)
const toast = ref(null)

// All types shown by default; tapping a chip narrows the list down to it. A
// chart's "View records" link can arrive with ?entity=feedings,diapers to
// preset which ones are active instead of showing everything.
const requestedEntities = String(route.query.entity || '')
  .split(',')
  .filter((k) => k in TRACKERS)
const filters = reactive(
  Object.fromEntries(
    Object.keys(TRACKERS).map((k) => [k, requestedEntities.length ? requestedEntities.includes(k) : true])
  )
)
const allFiltersActive = computed(() => Object.values(filters).every(Boolean))

function toggleFilter(entity) {
  filters[entity] = !filters[entity]
}

function showAll() {
  for (const key of Object.keys(filters)) filters[key] = true
}

const filteredItems = computed(() => items.value.filter((i) => filters[i.entity]))

function dayLabel(dateStr) {
  const d = new Date(dateStr)
  const today = new Date()
  const yesterday = new Date(today)
  yesterday.setDate(today.getDate() - 1)
  const sameDay = (a, b) => a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate()
  if (sameDay(d, today)) return 'Today'
  if (sameDay(d, yesterday)) return 'Yesterday'
  return d.toLocaleDateString([], { weekday: 'long', month: 'short', day: 'numeric' })
}

// filteredItems is already sorted newest-first, so a simple run-length group
// keeps each day's entries together without re-sorting.
const groupedItems = computed(() => {
  const groups = []
  let currentKey = null
  let currentGroup = null
  for (const item of filteredItems.value) {
    const key = new Date(item.at).toDateString()
    if (key !== currentKey) {
      currentGroup = { key, label: dayLabel(item.at), items: [] }
      groups.push(currentGroup)
      currentKey = key
    }
    currentGroup.items.push(item)
  }
  return groups
})

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
    case 'milestones':
      return entry.title
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
    Object.keys(TRACKERS).map((entity) =>
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
        at: row[TRACKERS[entity].time],
        summary: summarize(entity, row),
        flagged: row.flagged_for_doctor || false,
        raw: row,
      })
    }
  }
  merged.sort((a, b) => new Date(b.at) - new Date(a.at))
  items.value = merged
  loading.value = false
}

function openDetail(item) {
  selectedItem.value = item
}

function openEdit(item) {
  selectedItem.value = null
  editingItem.value = item
}

function closeEdit() {
  editingItem.value = null
}

async function onEditSaved(result) {
  if (result?.error) {
    toast.value = { type: 'warning', message: result.error.message || 'Not saved — please check the entry and try again.' }
    setTimeout(() => (toast.value = null), 4500)
    return
  }

  closeEdit()
  await load()
  toast.value = { type: 'ok', message: 'Changes saved ✓' }
  setTimeout(() => (toast.value = null), 3500)
}

function askDelete(item) {
  pendingDelete.value = item
}

function cancelDelete() {
  pendingDelete.value = null
}

async function confirmDeleteEntry() {
  const item = pendingDelete.value
  if (!item) return
  await apiClient.delete(`/api/${item.entity}/${item.id}`)
  items.value = items.value.filter((i) => !(i.entity === item.entity && i.id === item.id))
  pendingDelete.value = null
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
    <template v-else-if="loading">
      <p class="muted">Loading…</p>
    </template>
    <template v-else>
      <div class="filter-chips">
        <button type="button" class="filter-chip" :class="{ active: allFiltersActive }" @click="showAll">
          All
        </button>
        <button
          v-for="(meta, key) in TRACKERS"
          :key="key"
          type="button"
          class="filter-chip"
          :class="{ active: filters[key] }"
          @click="toggleFilter(key)"
        >
          {{ meta.emoji }} {{ meta.label }}
        </button>
      </div>

      <p v-if="items.length === 0" class="muted">Nothing logged yet.</p>
      <p v-else-if="groupedItems.length === 0" class="muted">No entries match this filter.</p>

      <div v-for="group in groupedItems" :key="group.key" class="history-day-group">
        <div class="history-day-header">{{ group.label }}</div>
        <div class="card" style="padding: 4px 12px;">
          <div v-for="item in group.items" :key="item.entity + item.id" class="history-row" @click="openDetail(item)">
            <div class="history-icon" :style="{ background: TRACKERS[item.entity].color + '2e' }">
              {{ TRACKERS[item.entity].emoji }}
            </div>
            <div class="history-row-body">
              <div class="history-row-title">
                {{ TRACKERS[item.entity].label }}
                <span v-if="item.flagged" class="pill alert">tell doctor</span>
              </div>
              <div class="history-row-summary">{{ item.summary }}</div>
            </div>
            <div class="history-row-meta">
              <div class="history-row-time">{{ new Date(item.at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}</div>
              <button type="button" class="history-delete-btn" aria-label="Delete entry" @click.stop="askDelete(item)">🗑️</button>
            </div>
          </div>
        </div>
      </div>
    </template>

    <ConfirmDialog
      v-if="pendingDelete"
      title="Delete this entry?"
      :message="`${TRACKERS[pendingDelete.entity].label} · ${pendingDelete.summary}`"
      @confirm="confirmDeleteEntry"
      @cancel="cancelDelete"
    />

    <HistoryDetailModal
      v-if="selectedItem"
      :item="selectedItem"
      @close="selectedItem = null"
      @edit="openEdit"
    />

    <BottomSheet v-if="editingItem" :title="`Edit ${TRACKERS[editingItem.entity].label}`" @close="closeEdit">
      <component
        :is="FORM_COMPONENTS[editingItem.entity]"
        :baby-id="babyStore.currentBabyId"
        :record="editingItem.raw"
        @saved="onEditSaved"
      />
    </BottomSheet>

    <div v-if="toast" class="toast" :class="{ warning: toast.type === 'warning' }" style="top: auto; bottom: 88px;">
      {{ toast.message }}
    </div>
  </div>
</template>
