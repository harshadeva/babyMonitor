<script setup>
import { ref } from 'vue'
import { buildChartSvg, downloadSvg } from '@/utils/chartSvg'

const props = defineProps({
  title: { type: String, required: true },
  range: { type: Number, default: null },
  rangeOptions: { type: Array, default: () => [] },
  entity: { type: [String, Array], default: null },
  loading: { type: Boolean, default: false },
  empty: { type: Boolean, default: false },
  emptyText: { type: String, default: 'No data yet.' },
  stats: { type: Array, default: () => [] },
  blurb: { type: String, default: '' },
  chartData: { type: Object, default: null },
  svgKind: { type: String, default: 'bar' },
})
defineEmits(['update:range'])

const expanded = ref(false)

const recordsLink = () => {
  const entities = Array.isArray(props.entity) ? props.entity : [props.entity]
  return { path: '/history', query: { entity: entities.join(',') } }
}

function rangeLabel() {
  if (!props.range) return ''
  const end = new Date()
  const start = new Date()
  start.setDate(start.getDate() - (props.range - 1))
  const fmt = (d) => d.toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' })
  return `Last ${props.range} days · ${fmt(start)} – ${fmt(end)}`
}

function exportSvg() {
  const svg = buildChartSvg({
    title: props.title,
    description: props.blurb,
    filterNote: rangeLabel(),
    data: props.chartData,
    kind: props.svgKind,
  })
  const slug = props.title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')
  downloadSvg(svg, `${slug}-${new Date().toISOString().slice(0, 10)}.svg`)
}
</script>

<template>
  <div class="card chart-card">
    <div class="chart-card-header">
      <h3>{{ title }}</h3>
      <select
        v-if="rangeOptions.length"
        class="chart-range-select"
        :value="range"
        @change="$emit('update:range', Number($event.target.value))"
      >
        <option v-for="opt in rangeOptions" :key="opt" :value="opt">{{ opt }}d</option>
      </select>
    </div>
    <p v-if="blurb" class="chart-blurb">{{ blurb }}</p>

    <template v-if="!expanded">
      <p v-if="loading" class="muted">Loading…</p>
      <p v-else-if="empty" class="muted">{{ emptyText }}</p>
      <div v-else class="chart-canvas-wrap">
        <slot />
      </div>

      <div class="chart-card-footer">
        <button
          type="button"
          class="chart-icon-btn"
          aria-label="Expand chart"
          :disabled="loading || empty"
          @click="expanded = true"
        >
          ⤢ Expand
        </button>
        <router-link v-if="entity" :to="recordsLink()" class="chart-link-btn">📋 Records</router-link>
      </div>
    </template>

    <div v-else class="detail-backdrop" @click.self="expanded = false">
      <div class="chart-expand-card">
        <div class="chart-card-header">
          <h3>{{ title }}</h3>
          <button type="button" class="detail-close" aria-label="Close" @click="expanded = false">✕</button>
        </div>

        <div v-if="stats.length" class="chart-stats-row">
          <div v-for="s in stats" :key="s.label" class="chart-stat">
            <div class="chart-stat-value">{{ s.value }}</div>
            <div class="chart-stat-label">{{ s.label }}</div>
          </div>
        </div>

        <div class="chart-canvas-wrap chart-canvas-wrap-expanded">
          <slot />
        </div>

        <div class="chart-expand-actions">
          <button v-if="chartData" type="button" class="btn btn-secondary btn-block" @click="exportSvg">
            ⬇️ Export as SVG
          </button>
          <router-link v-if="entity" :to="recordsLink()" class="btn btn-secondary btn-block">
            View related records
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>
