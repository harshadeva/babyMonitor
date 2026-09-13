<script setup>
import { computed } from 'vue'
import { TRACKERS } from '@/constants/trackers'

const props = defineProps({
  item: { type: Object, required: true }, // { entity, id, at, summary, flagged, raw }
})
const emit = defineEmits(['close', 'edit'])

const meta = computed(() => TRACKERS[props.item.entity])
const raw = computed(() => props.item.raw)

function fmtTime(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString([], {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function durationLabel(start, end) {
  const min = Math.round((new Date(end) - new Date(start)) / 60_000)
  if (min < 60) return `${min} min`
  return `${Math.floor(min / 60)}h ${min % 60}m`
}

const detailRows = computed(() => {
  const r = raw.value
  switch (props.item.entity) {
    case 'feedings':
      return [
        { label: 'Type', value: r.type === 'breast' ? 'Breastfeeding' : 'Bottle' },
        ...(r.type === 'breast'
          ? [{ label: 'Side', value: r.side }]
          : [
              { label: 'Volume', value: `${r.volume_ml} ml` },
              { label: 'Contents', value: r.contents === 'formula' ? 'Formula' : 'Breast milk (bottle)' },
            ]),
        { label: 'Started', value: fmtTime(r.started_at) },
        { label: 'Ended', value: r.ended_at ? fmtTime(r.ended_at) : 'In progress' },
        ...(r.ended_at ? [{ label: 'Duration', value: durationLabel(r.started_at, r.ended_at) }] : []),
      ]
    case 'sleeps':
      return [
        { label: 'Fell asleep', value: fmtTime(r.started_at) },
        { label: 'Woke up', value: r.ended_at ? fmtTime(r.ended_at) : 'Still asleep' },
        ...(r.ended_at ? [{ label: 'Duration', value: durationLabel(r.started_at, r.ended_at) }] : []),
      ]
    case 'diapers':
      return [
        { label: 'Product', value: r.product === 'disposable' ? 'Disposable' : 'Cloth' },
        { label: 'Wet', value: r.wet ? 'Yes' : 'No' },
        { label: 'Dirty', value: r.dirty ? 'Yes' : 'No' },
        ...(r.dirty
          ? [
              { label: 'Stool color', value: r.stool_color_name || '—' },
              { label: 'Consistency', value: r.stool_consistency ? r.stool_consistency.replace('_', ' ') : '—' },
            ]
          : []),
        ...(r.flagged_for_doctor ? [{ label: '⚠️ Flagged', value: 'Mention at next doctor visit' }] : []),
      ]
    case 'temperatures':
      return [
        { label: 'Reading', value: `${Number(r.value_celsius).toFixed(1)}°C` },
        { label: 'Method', value: r.method.replace('_', ' ') },
      ]
    case 'growths':
      return [
        r.weight_grams ? { label: 'Weight', value: `${r.weight_grams} g` } : null,
        r.length_cm ? { label: 'Length', value: `${r.length_cm} cm` } : null,
        r.head_circumference_cm ? { label: 'Head circumference', value: `${r.head_circumference_cm} cm` } : null,
      ].filter(Boolean)
    case 'medications':
      return [{ label: 'Medication', value: r.name }, ...(r.dose ? [{ label: 'Dose', value: r.dose }] : [])]
    case 'symptoms':
      return [{ label: 'Symptom', value: r.tag.replace('_', ' ') }]
    default:
      return []
  }
})
</script>

<template>
  <div class="detail-backdrop" @click.self="emit('close')">
    <div class="detail-card">
      <div class="detail-header">
        <div class="detail-icon" :style="{ background: meta.color + '2e' }">{{ meta.emoji }}</div>
        <div>
          <div class="detail-title">{{ meta.label }}</div>
          <div class="detail-time">{{ fmtTime(item.at) }}</div>
        </div>
        <button type="button" class="detail-edit" aria-label="Edit entry" @click="emit('edit', item)">✏️</button>
        <button type="button" class="detail-close" aria-label="Close" @click="emit('close')">✕</button>
      </div>

      <div class="detail-rows">
        <div v-for="row in detailRows" :key="row.label" class="detail-row">
          <span class="detail-row-label">{{ row.label }}</span>
          <span class="detail-row-value">{{ row.value }}</span>
        </div>
      </div>

      <div v-if="raw.notes" class="detail-remark">
        <div class="detail-remark-label">Remark</div>
        <div class="detail-remark-text">{{ raw.notes }}</div>
      </div>

      <div class="detail-footer">
        Record created {{ fmtTime(raw.created_at) }}<template v-if="raw.created_by_name"> by {{ raw.created_by_name }}</template>
      </div>
    </div>
  </div>
</template>
