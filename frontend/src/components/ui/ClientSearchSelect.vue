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
import { clientOptions } from '../../utils/selectOptions'

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  clients: { type: Array, default: () => [] },
  placeholder: { type: String, default: 'Select client' },
  searchPlaceholder: { type: String, default: 'Search client name or unit…' },
  disabled: { type: Boolean, default: false },
  required: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'change'])

const options = computed(() => clientOptions(props.clients))
</script>
