<script setup>
import { onMounted, reactive, watch } from 'vue'
import { Bar, Line } from 'vue-chartjs'
import ChartCard from '@/components/ChartCard.vue'
import { apiClient } from '@/api/client'
import { useBabyStore } from '@/stores/baby'

const babyStore = useBabyStore()
const FEVER_C = 38.0

function isoDate(d) {
  return d.toISOString().slice(0, 10)
}

function lastNDayLabels(n) {
  const labels = []
  for (let i = n - 1; i >= 0; i--) {
    const d = new Date()
    d.setDate(d.getDate() - i)
    labels.push(isoDate(d))
  }
  return labels
}

function shortLabels(dayLabels) {
  return dayLabels.map((d) => new Date(d).toLocaleDateString([], { month: 'short', day: 'numeric' }))
}

function isoDaysAgo(days) {
  const d = new Date()
  d.setHours(0, 0, 0, 0)
  d.setDate(d.getDate() - (days - 1))
  return d.toISOString()
}

function formatHour(v) {
  const h = Math.round(((v % 24) + 24) % 24)
  const hh = h % 12 === 0 ? 12 : h % 12
  return `${hh}${h < 12 ? 'am' : 'pm'}`
}

// Fetches one tracker's rows for the last `days` days. per_page scales with
// the window so a wide range doesn't silently truncate to the newest 50 rows.
async function fetchRange(entity, days) {
  const babyId = babyStore.currentBabyId
  const { data } = await apiClient.get(`/api/babies/${babyId}/${entity}`, {
    params: { from: isoDaysAgo(days), per_page: Math.min(days * 20, 2000) },
  })
  return data.data
}

// Small state machine shared by every chart card: an adjustable `range` (in
// days) that re-runs `loader` whenever it changes, independent of every
// other chart on the page.
function useRangedChart(defaultDays, loader) {
  const state = reactive({ range: defaultDays, data: null, loading: true, empty: false, stats: [] })
  async function run() {
    state.loading = true
    try {
      const result = await loader(state.range)
      state.data = result.data
      state.stats = result.stats || []
      state.empty = !!result.empty
    } finally {
      state.loading = false
    }
  }
  watch(() => state.range, run)
  return { state, run }
}

const chartOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: true } } }

// ---- Daily totals ----------------------------------------------------

const sleep = useRangedChart(14, async (days) => {
  const dayLabels = lastNDayLabels(days)
  const rows = await fetchRange('sleeps', days)
  const byDay = Object.fromEntries(dayLabels.map((d) => [d, 0]))
  for (const s of rows) {
    if (!s.ended_at) continue
    const day = s.started_at.slice(0, 10)
    if (day in byDay) byDay[day] += (new Date(s.ended_at) - new Date(s.started_at)) / 3_600_000
  }
  const values = dayLabels.map((d) => Math.round(byDay[d] * 10) / 10)
  const total = values.reduce((a, b) => a + b, 0)
  return {
    empty: rows.length === 0,
    data: {
      labels: shortLabels(dayLabels),
      datasets: [{ label: 'Sleep (hours)', data: values, backgroundColor: '#b9a6e8', borderRadius: 6 }],
    },
    stats: [
      { label: 'Avg / day', value: `${(total / days).toFixed(1)}h` },
      { label: 'Best day', value: `${Math.max(...values, 0).toFixed(1)}h` },
      { label: 'Total', value: `${total.toFixed(1)}h` },
    ],
  }
})

const feeding = useRangedChart(14, async (days) => {
  const dayLabels = lastNDayLabels(days)
  const rows = await fetchRange('feedings', days)
  const breast = Object.fromEntries(dayLabels.map((d) => [d, 0]))
  const pumped = Object.fromEntries(dayLabels.map((d) => [d, 0]))
  const formula = Object.fromEntries(dayLabels.map((d) => [d, 0]))
  for (const f of rows) {
    const day = f.started_at.slice(0, 10)
    if (!(day in breast)) continue
    if (f.type === 'breast') breast[day] += 1
    else if (f.contents === 'expressed_milk') pumped[day] += 1
    else if (f.contents === 'formula') formula[day] += 1
  }
  const totalPerDay = dayLabels.map((d) => breast[d] + pumped[d] + formula[d])
  const total = totalPerDay.reduce((a, b) => a + b, 0)
  return {
    empty: rows.length === 0,
    data: {
      labels: shortLabels(dayLabels),
      datasets: [
        { label: 'Breastfed', data: dayLabels.map((d) => breast[d]), backgroundColor: '#ff9d66' },
        { label: 'Pumped milk', data: dayLabels.map((d) => pumped[d]), backgroundColor: '#ffb894' },
        { label: 'Formula', data: dayLabels.map((d) => formula[d]), backgroundColor: '#e8c88a' },
      ],
    },
    stats: [
      { label: 'Avg / day', value: (total / days).toFixed(1) },
      { label: 'Busiest day', value: Math.max(...totalPerDay, 0) },
      { label: 'Total feeds', value: total },
    ],
  }
})
const feedingOptions = { ...chartOptions, scales: { x: { stacked: true }, y: { stacked: true } } }

const diaper = useRangedChart(14, async (days) => {
  const dayLabels = lastNDayLabels(days)
  const rows = await fetchRange('diapers', days)
  const wet = Object.fromEntries(dayLabels.map((d) => [d, 0]))
  const dirty = Object.fromEntries(dayLabels.map((d) => [d, 0]))
  for (const d of rows) {
    const day = d.occurred_at.slice(0, 10)
    if (!(day in wet)) continue
    if (d.wet) wet[day] += 1
    if (d.dirty) dirty[day] += 1
  }
  const totalWet = Object.values(wet).reduce((a, b) => a + b, 0)
  const totalDirty = Object.values(dirty).reduce((a, b) => a + b, 0)
  return {
    empty: rows.length === 0,
    data: {
      labels: shortLabels(dayLabels),
      datasets: [
        { label: 'Wet', data: dayLabels.map((d) => wet[d]), backgroundColor: '#8fd9c4', borderRadius: 6 },
        { label: 'Dirty', data: dayLabels.map((d) => dirty[d]), backgroundColor: '#c97a1a', borderRadius: 6 },
      ],
    },
    stats: [
      { label: 'Avg wet/day', value: (totalWet / days).toFixed(1) },
      { label: 'Avg dirty/day', value: (totalDirty / days).toFixed(1) },
      { label: 'Total changes', value: totalWet + totalDirty },
    ],
  }
})

const temperature = useRangedChart(30, async (days) => {
  const rows = await fetchRange('temperatures', days)
  const sorted = [...rows].sort((a, b) => new Date(a.measured_at) - new Date(b.measured_at))
  const labels = sorted.map((t) => new Date(t.measured_at).toLocaleDateString([], { month: 'short', day: 'numeric' }))
  const values = sorted.map((t) => Number(t.value_celsius))
  return {
    empty: sorted.length === 0,
    data: {
      labels,
      datasets: [
        { label: '°C', data: values, borderColor: '#ff8fab', backgroundColor: '#ff8fab', tension: 0.3, pointRadius: 3 },
        {
          label: 'Fever threshold',
          data: values.map(() => FEVER_C),
          borderColor: '#e0555a',
          borderDash: [6, 4],
          borderWidth: 1,
          pointRadius: 0,
        },
      ],
    },
    stats: [
      { label: 'Readings', value: sorted.length },
      { label: 'Highest', value: values.length ? `${Math.max(...values).toFixed(1)}°C` : '—' },
      { label: 'Fever readings', value: values.filter((v) => v >= FEVER_C).length },
    ],
  }
})

// ---- Patterns & correlations ------------------------------------------

const rhythm = useRangedChart(5, async (days) => {
  const dayLabels = lastNDayLabels(days)
  const dayShort = shortLabels(dayLabels)
  const shortByIso = Object.fromEntries(dayLabels.map((d, i) => [d, dayShort[i]]))
  const [sleeps, feeds] = await Promise.all([fetchRange('sleeps', days), fetchRange('feedings', days)])

  const sleepSegments = []
  for (const s of sleeps) {
    if (!s.started_at) continue
    const start = new Date(s.started_at)
    const end = s.ended_at ? new Date(s.ended_at) : new Date()
    let cursor = new Date(start)
    while (cursor < end) {
      const dayKey = isoDate(cursor)
      const dayEnd = new Date(cursor)
      dayEnd.setHours(24, 0, 0, 0)
      const segEnd = end < dayEnd ? end : dayEnd
      if (dayKey in shortByIso) {
        const startHour = cursor.getHours() + cursor.getMinutes() / 60
        const endHour = segEnd >= dayEnd ? 24 : segEnd.getHours() + segEnd.getMinutes() / 60
        sleepSegments.push({ y: shortByIso[dayKey], x: [startHour, Math.max(endHour, startHour + 0.05)] })
      }
      cursor = dayEnd
    }
  }

  const feedPoints = []
  for (const f of feeds) {
    const day = f.started_at.slice(0, 10)
    if (day in shortByIso) {
      const d = new Date(f.started_at)
      feedPoints.push({ y: shortByIso[day], x: d.getHours() + d.getMinutes() / 60 })
    }
  }

  const totalSleepHours = sleepSegments.reduce((sum, seg) => sum + (seg.x[1] - seg.x[0]), 0)

  return {
    empty: sleeps.length === 0 && feeds.length === 0,
    data: {
      labels: dayShort,
      datasets: [
        {
          type: 'bar',
          label: 'Asleep',
          data: sleepSegments,
          backgroundColor: '#b9a6e8',
          barThickness: 16,
          borderRadius: 4,
        },
        {
          type: 'line',
          label: 'Feed',
          data: feedPoints,
          showLine: false,
          pointBackgroundColor: '#ff9d66',
          pointBorderColor: '#ff9d66',
          pointRadius: 5,
          pointHoverRadius: 7,
        },
      ],
    },
    stats: [
      { label: 'Sleep blocks', value: sleepSegments.length },
      { label: 'Feeds', value: feedPoints.length },
      { label: 'Avg sleep/day', value: `${(totalSleepHours / days).toFixed(1)}h` },
    ],
  }
})
const rhythmOptions = {
  responsive: true,
  maintainAspectRatio: false,
  indexAxis: 'y',
  plugins: {
    legend: { display: true },
    tooltip: {
      callbacks: {
        label: (ctx) =>
          ctx.dataset.label === 'Feed'
            ? `Fed at ${formatHour(ctx.raw.x)}`
            : `Asleep ${formatHour(ctx.raw.x[0])} – ${formatHour(ctx.raw.x[1])}`,
      },
    },
  },
  scales: {
    x: { min: 0, max: 24, ticks: { stepSize: 6, callback: (v) => formatHour(v) } },
    y: { grid: { display: false } },
  },
}

const feedsVsDiapers = useRangedChart(14, async (days) => {
  const dayLabels = lastNDayLabels(days)
  const [feeds, diapers] = await Promise.all([fetchRange('feedings', days), fetchRange('diapers', days)])
  const feedCount = Object.fromEntries(dayLabels.map((d) => [d, 0]))
  const wetCount = Object.fromEntries(dayLabels.map((d) => [d, 0]))
  const dirtyCount = Object.fromEntries(dayLabels.map((d) => [d, 0]))
  for (const f of feeds) {
    const day = f.started_at.slice(0, 10)
    if (day in feedCount) feedCount[day] += 1
  }
  for (const d of diapers) {
    const day = d.occurred_at.slice(0, 10)
    if (!(day in wetCount)) continue
    if (d.wet) wetCount[day] += 1
    if (d.dirty) dirtyCount[day] += 1
  }
  const avgFeeds = Object.values(feedCount).reduce((a, b) => a + b, 0) / days
  const avgWet = Object.values(wetCount).reduce((a, b) => a + b, 0) / days
  const lowOutputDays = dayLabels.filter((d) => feedCount[d] >= 4 && wetCount[d] < 6).length
  return {
    empty: feeds.length === 0 && diapers.length === 0,
    data: {
      labels: shortLabels(dayLabels),
      datasets: [
        { label: 'Feeds', data: dayLabels.map((d) => feedCount[d]), backgroundColor: '#ff9d66', borderRadius: 6 },
        { label: 'Wet diapers', data: dayLabels.map((d) => wetCount[d]), backgroundColor: '#8fd9c4', borderRadius: 6 },
        { label: 'Dirty diapers', data: dayLabels.map((d) => dirtyCount[d]), backgroundColor: '#c97a1a', borderRadius: 6 },
      ],
    },
    stats: [
      { label: 'Avg feeds/day', value: avgFeeds.toFixed(1) },
      { label: 'Avg wet/day', value: avgWet.toFixed(1) },
      { label: 'Low-output days', value: lowOutputDays },
    ],
  }
})

const feedsVsWeight = useRangedChart(30, async (days) => {
  const dayLabels = lastNDayLabels(days)
  const [feeds, growths] = await Promise.all([fetchRange('feedings', days), fetchRange('growths', days)])
  const feedCount = Object.fromEntries(dayLabels.map((d) => [d, 0]))
  for (const f of feeds) {
    const day = f.started_at.slice(0, 10)
    if (day in feedCount) feedCount[day] += 1
  }

  const weightByDay = {}
  const weightPoints = []
  for (const g of growths) {
    if (g.weight_grams == null) continue
    const day = g.measured_at.slice(0, 10)
    const kg = Math.round(g.weight_grams / 10) / 100
    weightByDay[day] = kg
    weightPoints.push({ day, kg })
  }
  weightPoints.sort((a, b) => a.day.localeCompare(b.day))

  return {
    empty: feeds.length === 0 && growths.length === 0,
    data: {
      labels: shortLabels(dayLabels),
      datasets: [
        {
          type: 'bar',
          label: 'Feeds/day',
          data: dayLabels.map((d) => feedCount[d]),
          backgroundColor: '#ffd976',
          yAxisID: 'y',
          borderRadius: 4,
        },
        {
          type: 'line',
          label: 'Weight (kg)',
          data: dayLabels.map((d) => weightByDay[d] ?? null),
          spanGaps: true,
          borderColor: '#8fc1e8',
          backgroundColor: '#8fc1e8',
          yAxisID: 'y1',
          pointRadius: (ctx) => (weightByDay[dayLabels[ctx.dataIndex]] != null ? 5 : 0),
          tension: 0.3,
        },
      ],
    },
    stats: [
      { label: 'Weigh-ins', value: weightPoints.length },
      { label: 'Latest weight', value: weightPoints.length ? `${weightPoints[weightPoints.length - 1].kg}kg` : '—' },
      { label: 'Avg feeds/day', value: (Object.values(feedCount).reduce((a, b) => a + b, 0) / days).toFixed(1) },
    ],
  }
})
const feedsVsWeightOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: true } },
  scales: {
    y: { type: 'linear', position: 'left', beginAtZero: true, title: { display: true, text: 'Feeds/day' } },
    y1: { type: 'linear', position: 'right', title: { display: true, text: 'Weight (kg)' }, grid: { drawOnChartArea: false } },
  },
}

onMounted(async () => {
  await babyStore.load()
  await Promise.all([
    sleep.run(),
    feeding.run(),
    diaper.run(),
    temperature.run(),
    rhythm.run(),
    feedsVsDiapers.run(),
    feedsVsWeight.run(),
  ])
})
</script>

<template>
  <div class="screen">
    <div class="top-bar">
      <h1>Charts</h1>
    </div>

    <div class="chart-section-title">Daily totals</div>

    <ChartCard
      title="Sleep"
      :range="sleep.state.range"
      :range-options="[7, 14, 30, 90]"
      :loading="sleep.state.loading"
      :empty="sleep.state.empty"
      :stats="sleep.state.stats"
      :chart-data="sleep.state.data"
      svg-kind="bar"
      entity="sleeps"
      empty-text="No sleep logged in this window."
      @update:range="(v) => (sleep.state.range = v)"
    >
      <Bar :data="sleep.state.data" :options="chartOptions" />
    </ChartCard>

    <ChartCard
      title="Feeds per day"
      :range="feeding.state.range"
      :range-options="[7, 14, 30, 90]"
      :loading="feeding.state.loading"
      :empty="feeding.state.empty"
      :stats="feeding.state.stats"
      :chart-data="feeding.state.data"
      svg-kind="bar-stacked"
      entity="feedings"
      empty-text="No feeds logged in this window."
      @update:range="(v) => (feeding.state.range = v)"
    >
      <Bar :data="feeding.state.data" :options="feedingOptions" />
    </ChartCard>

    <ChartCard
      title="Diapers per day"
      :range="diaper.state.range"
      :range-options="[7, 14, 30, 90]"
      :loading="diaper.state.loading"
      :empty="diaper.state.empty"
      :stats="diaper.state.stats"
      :chart-data="diaper.state.data"
      svg-kind="bar"
      entity="diapers"
      empty-text="No diaper changes logged in this window."
      @update:range="(v) => (diaper.state.range = v)"
    >
      <Bar :data="diaper.state.data" :options="chartOptions" />
    </ChartCard>

    <ChartCard
      title="Temperature readings"
      :range="temperature.state.range"
      :range-options="[7, 14, 30, 90]"
      :loading="temperature.state.loading"
      :empty="temperature.state.empty"
      :stats="temperature.state.stats"
      :chart-data="temperature.state.data"
      svg-kind="line"
      entity="temperatures"
      empty-text="No readings in this window."
      @update:range="(v) => (temperature.state.range = v)"
    >
      <Line :data="temperature.state.data" :options="chartOptions" />
    </ChartCard>

    <div class="chart-section-title">Patterns &amp; correlations</div>

    <ChartCard
      title="Daily rhythm"
      blurb="When sleep and feeds happen across the day — watch for day/night sleep separating out and feeds clustering."
      :range="rhythm.state.range"
      :range-options="[3, 5, 7, 10]"
      :loading="rhythm.state.loading"
      :empty="rhythm.state.empty"
      :stats="rhythm.state.stats"
      :chart-data="rhythm.state.data"
      svg-kind="rhythm"
      :entity="['sleeps', 'feedings']"
      empty-text="Not enough data yet for a rhythm view."
      @update:range="(v) => (rhythm.state.range = v)"
    >
      <Bar :data="rhythm.state.data" :options="rhythmOptions" />
    </ChartCard>

    <ChartCard
      title="Feeds vs. diapers"
      blurb="Roughly 6+ wet diapers a day is a common sign feeding is going well — watch for days wet count lags well behind feeds."
      :range="feedsVsDiapers.state.range"
      :range-options="[7, 14, 30, 90]"
      :loading="feedsVsDiapers.state.loading"
      :empty="feedsVsDiapers.state.empty"
      :stats="feedsVsDiapers.state.stats"
      :chart-data="feedsVsDiapers.state.data"
      svg-kind="bar"
      :entity="['feedings', 'diapers']"
      empty-text="No feeds or diaper changes in this window."
      @update:range="(v) => (feedsVsDiapers.state.range = v)"
    >
      <Bar :data="feedsVsDiapers.state.data" :options="chartOptions" />
    </ChartCard>

    <ChartCard
      title="Feeding vs. weight gain"
      blurb="Weight is only measured occasionally — the line connects real weigh-ins against daily feed frequency."
      :range="feedsVsWeight.state.range"
      :range-options="[14, 30, 60, 90]"
      :loading="feedsVsWeight.state.loading"
      :empty="feedsVsWeight.state.empty"
      :stats="feedsVsWeight.state.stats"
      :chart-data="feedsVsWeight.state.data"
      svg-kind="combo"
      :entity="['feedings', 'growths']"
      empty-text="No feeds or weight measurements in this window."
      @update:range="(v) => (feedsVsWeight.state.range = v)"
    >
      <Bar :data="feedsVsWeight.state.data" :options="feedsVsWeightOptions" />
    </ChartCard>
  </div>
</template>
