<template>
  <SearchableSelect
    :model-value="modelValue"
    :options="options"
    :placeholder="placeholder"
    :search-placeholder="searchPlaceholder"
    :disabled="disabled"
    :required="required"
    @update:model-value="emit('update:modelValue', $event)"
    @change="emit('change', $event)"
  />
</template>

<script setup>
import { computed } from 'vue'
import SearchableSelect from './SearchableSelect.vue'
import { buildingOptions } from '../../utils/selectOptions'

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  buildings: { type: Array, default: () => [] },
  includeAll: { type: Boolean, default: false },
  allLabel: { type: String, default: 'All buildings' },
  placeholder: { type: String, default: 'Select building' },
  searchPlaceholder: { type: String, default: 'Search buildings…' },
  disabled: { type: Boolean, default: false },
  required: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'change'])

const options = computed(() => buildingOptions(props.buildings, {
  includeAll: props.includeAll,
  allLabel: props.allLabel,
}))
</script>
