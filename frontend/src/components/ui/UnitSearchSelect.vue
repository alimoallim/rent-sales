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
import { rentalUnitOptions, saleUnitOptions } from '../../utils/selectOptions'

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  units: { type: Array, default: () => [] },
  module: { type: String, default: 'rental' },
  placeholder: { type: String, default: 'Select unit' },
  searchPlaceholder: { type: String, default: 'Search unit number or description…' },
  disabled: { type: Boolean, default: false },
  required: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'change'])

const options = computed(() =>
  props.module === 'sales' ? saleUnitOptions(props.units) : rentalUnitOptions(props.units),
)
</script>
