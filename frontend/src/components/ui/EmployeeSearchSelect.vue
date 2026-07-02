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
import { employeeOptions } from '../../utils/selectOptions'
import { formatMoney } from '../../utils/money'

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  employees: { type: Array, default: () => [] },
  placeholder: { type: String, default: 'Select employee' },
  searchPlaceholder: { type: String, default: 'Search employee name or position…' },
  disabled: { type: Boolean, default: false },
  required: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'change'])

const options = computed(() =>
  employeeOptions(props.employees, (amount) => formatMoney(amount, 'rental')),
)
</script>
