<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  modelValue: { type: String, default: '' },
})
const emit = defineEmits(['update:modelValue'])

const open = ref(false)

const preview = computed(() => {
  const text = props.modelValue.trim()
  return text.length > 40 ? `${text.slice(0, 40)}…` : text
})

function onInput(event) {
  emit('update:modelValue', event.target.value)
}
</script>

<template>
  <div class="remark-field">
    <button
      v-if="!open"
      type="button"
      class="remark-toggle"
      @click="open = true"
    >
      {{ modelValue ? `📝 “${preview}” · edit` : '📝 Add a remark (optional)' }}
    </button>

    <div v-else class="field remark-field-open">
      <label>
        Remark (optional)
        <button type="button" class="remark-hide" @click="open = false">Hide</button>
      </label>
      <textarea
        :value="modelValue"
        rows="2"
        placeholder="Anything else worth noting"
        @input="onInput"
      />
    </div>
  </div>
</template>
