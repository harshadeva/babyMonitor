<script setup>
import { onMounted, ref } from 'vue'
import { Bar, Line } from 'vue-chartjs'
import { apiClient } from '@/api/client'
import { useBabyStore } from '@/stores/baby'

const babyStore = useBabyStore()
const loading = ref(true)

const DAYS = 14

function lastNDayLabels(n) {
  const labels = []
  for (let i = n - 1; i >= 0; i--) {
    const d = new Date()
    d.setDate(d.getDate() - i)
    labels.push(d.toISOString().slice(0, 10))
  }
  return labels
}

const dayLabels = lastNDayLabels(DAYS)
const dayShortLabels = dayLabels.map((d) => new Date(d).toLocaleDateString([], { month: 'short', day: 'numeric' }))

const sleepData = ref(null)
const feedingData = ref(null)
const diaperData = ref(null)
const temperatureData = ref(null)

const chartOptions = { responsive: true, plugins: { legend: { display: true } } }

async function load() {
  loading.value = true
  const babyId = babyStore.currentBabyId

  const [sleeps, feedings, diapers, temps] = await Promise.all([
    apiClient.get(`/api/babies/${babyId}/sleeps`).then((r) => r.data.data),
    apiClient.get(`/api/babies/${babyId}/feedings`).then((r) => r.data.data),
    apiClient.get(`/api/babies/${babyId}/diapers`).then((r) => r.data.data),
    apiClient.get(`/api/babies/${babyId}/temperatures`).then((r) => r.data.data),
  ])

  const sleepHoursByDay = Object.fromEntries(dayLabels.map((d) => [d, 0]))
  for (const s of sleeps) {
    if (!s.ended_at) continue
    const day = s.started_at.slice(0, 10)
    if (day in sleepHoursByDay) {
      sleepHoursByDay[day] += (new Date(s.ended_at) - new Date(s.started_at)) / 3_600_000
    }
  }
  sleepData.value = {
    labels: dayShortLabels,
    datasets: [{ label: 'Sleep (hours)', data: dayLabels.map((d) => Math.round(sleepHoursByDay[d] * 10) / 10), backgroundColor: '#38bdf8' }],
  }

  const feedCountByDay = Object.fromEntries(dayLabels.map((d) => [d, 0]))
  for (const f of feedings) {
    const day = f.started_at.slice(0, 10)
    if (day in feedCountByDay) feedCountByDay[day] += 1
  }
  feedingData.value = {
    labels: dayShortLabels,
    datasets: [{ label: 'Feeds per day', data: dayLabels.map((d) => feedCountByDay[d]), backgroundColor: '#fbbf24' }],
  }

  const wetByDay = Object.fromEntries(dayLabels.map((d) => [d, 0]))
  const dirtyByDay = Object.fromEntries(dayLabels.map((d) => [d, 0]))
  for (const d of diapers) {
    const day = d.occurred_at.slice(0, 10)
    if (!(day in wetByDay)) continue
    if (d.wet) wetByDay[day] += 1
    if (d.dirty) dirtyByDay[day] += 1
  }
  diaperData.value = {
    labels: dayShortLabels,
    datasets: [
      { label: 'Wet', data: dayLabels.map((d) => wetByDay[d]), backgroundColor: '#38bdf8' },
      { label: 'Dirty', data: dayLabels.map((d) => dirtyByDay[d]), backgroundColor: '#a16207' },
    ],
  }

  const sortedTemps = [...temps].sort((a, b) => new Date(a.measured_at) - new Date(b.measured_at)).slice(-30)
  temperatureData.value = {
    labels: sortedTemps.map((t) => new Date(t.measured_at).toLocaleDateString([], { month: 'short', day: 'numeric' })),
    datasets: [{ label: '°C', data: sortedTemps.map((t) => Number(t.value_celsius)), borderColor: '#ef4444', tension: 0.3 }],
  }

  loading.value = false
}

onMounted(async () => {
  await babyStore.load()
  await load()
})
</script>

<template>
  <div class="screen">
    <div class="top-bar">
      <h1>Charts</h1>
    </div>

    <p v-if="loading" class="muted">Loading…</p>
    <template v-else>
      <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top:0;">Sleep — last {{ DAYS }} days</h3>
        <Bar :data="sleepData" :options="chartOptions" />
      </div>
      <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top:0;">Feeds per day</h3>
        <Bar :data="feedingData" :options="chartOptions" />
      </div>
      <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top:0;">Diapers per day</h3>
        <Bar :data="diaperData" :options="{ ...chartOptions, scales: { x: { stacked: true }, y: { stacked: true } } }" />
      </div>
      <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top:0;">Temperature readings</h3>
        <Line v-if="temperatureData.labels.length" :data="temperatureData" :options="chartOptions" />
        <p v-else class="muted">No readings yet.</p>
      </div>
    </template>
  </div>
</template>
