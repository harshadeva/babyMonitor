<script setup>
import { onMounted, ref } from 'vue'
import RemarkField from '@/components/RemarkField.vue'
import TimeAdjuster from '@/components/TimeAdjuster.vue'
import { STOOL_COLORS, STOOL_CONSISTENCIES } from '@/constants/options'
import { useEntryLogger } from '@/composables/useEntryLogger'
import { useEntryEditor } from '@/composables/useEntryEditor'
import { getSetting, setSetting } from '@/offline/db'

const props = defineProps({
  babyId: { type: Number, required: true },
  record: { type: Object, default: null },
})
const emit = defineEmits(['saved'])
const isEditing = !!props.record

const { submit, isSubmitting } = useEntryLogger('diapers')
const { saveEdit, isSaving } = useEntryEditor('diapers')

const presetMatch = isEditing ? STOOL_COLORS.find((c) => c.name === props.record.stool_color_name) : null

const when = ref(isEditing ? new Date(props.record.occurred_at) : new Date())
const product = ref(isEditing ? props.record.product : 'disposable')
const wet = ref(isEditing ? props.record.wet : true)
const dirty = ref(isEditing ? props.record.dirty : false)
const selectedColor = ref(presetMatch || STOOL_COLORS[3]) // mustard yellow — the common healthy default
const customHex = ref(isEditing && !presetMatch ? props.record.stool_color_hex || '#6b4423' : '#6b4423')
const useCustomColor = ref(isEditing ? !presetMatch && !!props.record.stool_color_name : false)
const consistency = ref(isEditing ? props.record.stool_consistency : null)
const notes = ref(isEditing ? props.record.notes || '' : '')

onMounted(async () => {
  if (isEditing) return
  product.value = await getSetting('last_diaper_product', 'disposable')
})

function pickColor(color) {
  selectedColor.value = color
  useCustomColor.value = false
}

async function save() {
  const colorName = useCustomColor.value ? 'Custom' : selectedColor.value?.name ?? null
  const colorHex = useCustomColor.value ? customHex.value : selectedColor.value?.hex ?? null

  const payload = {
    occurred_at: when.value.toISOString(),
    product: product.value,
    wet: wet.value,
    dirty: dirty.value,
    stool_color_name: dirty.value ? colorName : null,
    stool_color_hex: dirty.value ? colorHex : null,
    stool_consistency: dirty.value ? consistency.value : null,
    notes: notes.value || null,
  }

  if (isEditing) {
    emit('saved', await saveEdit(props.record.id, payload))
    return
  }

  await setSetting('last_diaper_product', product.value)
  emit('saved', await submit(props.babyId, payload))
}
</script>

<template>
  <div>
    <div class="field">
      <label>Product</label>
      <div class="segmented">
        <button type="button" :class="{ active: product === 'disposable' }" @click="product = 'disposable'">Diaper (disposable)</button>
        <button type="button" :class="{ active: product === 'cloth' }" @click="product = 'cloth'">Napkin (cloth)</button>
      </div>
    </div>

    <div class="field">
      <label>Type</label>
      <div class="segmented">
        <button type="button" :class="{ active: wet }" @click="wet = !wet">💧 Wet {{ wet ? '✓' : '' }}</button>
        <button type="button" :class="{ active: dirty }" @click="dirty = !dirty">💩 Dirty {{ dirty ? '✓' : '' }}</button>
      </div>
    </div>

    <template v-if="dirty">
      <div class="field">
        <label>Stool color</label>
        <div class="color-swatch-grid">
          <div
            v-for="c in STOOL_COLORS"
            :key="c.name"
            class="color-swatch"
            :class="{ selected: !useCustomColor && selectedColor?.name === c.name }"
            @click="pickColor(c)"
          >
            <span class="color-dot" :style="{ background: c.hex }" />
            <span class="color-name">{{ c.name }}</span>
          </div>
          <div class="color-swatch" :class="{ selected: useCustomColor }" @click="useCustomColor = true">
            <span class="color-dot" :style="{ background: customHex }" />
            <span class="color-name">Custom…</span>
          </div>
        </div>
        <input v-if="useCustomColor" v-model="customHex" type="color" style="width:100%; height:48px;" />
      </div>

      <div class="field">
        <label>Consistency</label>
        <select v-model="consistency">
          <option :value="null">—</option>
          <option v-for="c in STOOL_CONSISTENCIES" :key="c.value" :value="c.value">{{ c.label }}</option>
        </select>
      </div>
    </template>

    <TimeAdjuster v-model="when" />
    <RemarkField v-model="notes" />

    <button class="btn btn-primary btn-block" :disabled="isSubmitting || isSaving" @click="save">
      {{ (isSubmitting || isSaving) ? 'Saving…' : isEditing ? 'Save changes' : 'Log diaper change' }}
    </button>
  </div>
</template>
