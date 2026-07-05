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
import { tenantOptions } from '../../utils/selectOptions'

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  tenants: { type: Array, default: () => [] },
  placeholder: { type: String, default: 'Select tenant' },
  searchPlaceholder: { type: String, default: 'Search tenant name or unit…' },
  disabled: { type: Boolean, default: false },
  required: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'change'])

const options = computed(() => tenantOptions(props.tenants))
</script>
