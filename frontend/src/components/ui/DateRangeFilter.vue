<template>
  <div class="date-range-filter">
    <div class="date-range-field">
      <label :for="fromId" class="date-range-label">From</label>
      <input
        :id="fromId"
        :value="from"
        type="date"
        class="input-field date-range-input"
        :max="to || undefined"
        @input="onFromInput"
      />
    </div>
    <span class="date-range-separator" aria-hidden="true">–</span>
    <div class="date-range-field">
      <label :for="toId" class="date-range-label">To</label>
      <input
        :id="toId"
        :value="to"
        type="date"
        class="input-field date-range-input"
        :min="from || undefined"
        @input="onToInput"
      />
    </div>
    <button
      v-if="from || to"
      type="button"
      class="date-range-clear"
      @click="clear"
    >
      Clear
    </button>
  </div>
</template>

<script setup>
import { useId } from 'vue'

defineProps({
  from: { type: String, default: '' },
  to: { type: String, default: '' },
})

const emit = defineEmits(['update:from', 'update:to', 'change'])

const fromId = useId()
const toId = useId()

function onFromInput(event) {
  emit('update:from', event.target.value)
  emit('change')
}

function onToInput(event) {
  emit('update:to', event.target.value)
  emit('change')
}

function clear() {
  emit('update:from', '')
  emit('update:to', '')
  emit('change')
}
</script>
