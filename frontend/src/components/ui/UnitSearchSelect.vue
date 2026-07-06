<template>
  <SearchableSelect
    :model-value="modelValue"
    :options="options"
    :placeholder="placeholder"
    :search-placeholder="searchPlaceholder"
    :disabled="disabled || !canSearch"
    :required="required"
    :remote-search="usesRemoteSearch"
    :loading="loading"
    @update:model-value="emit('update:modelValue', $event)"
    @change="emit('change', $event)"
    @search-query="onSearchQuery"
  />
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import SearchableSelect from './SearchableSelect.vue'
import { fetchUnits as fetchRentalUnits } from '../../api/rental'
import { fetchUnits as fetchSaleUnits } from '../../api/sales'
import { rentalUnitOptions, saleUnitOptions } from '../../utils/selectOptions'

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  buildingId: { type: [String, Number], default: '' },
  units: { type: Array, default: null },
  module: { type: String, default: 'rental' },
  placeholder: { type: String, default: 'Select unit' },
  searchPlaceholder: { type: String, default: 'Search unit number or description…' },
  disabled: { type: Boolean, default: false },
  required: { type: Boolean, default: false },
  status: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'change'])

const remoteUnits = ref([])
const loading = ref(false)

const usesRemoteSearch = computed(() => props.units === null && props.buildingId !== '' && props.buildingId != null)
const canSearch = computed(() => !usesRemoteSearch.value || Boolean(props.buildingId))

const options = computed(() => {
  const source = props.units ?? remoteUnits.value
  return props.module === 'sales' ? saleUnitOptions(source) : rentalUnitOptions(source)
})

async function loadRemoteUnits(search = '') {
  if (!usesRemoteSearch.value || !props.buildingId) {
    remoteUnits.value = []
    return
  }

  loading.value = true
  try {
    const params = {
      building_id: props.buildingId,
      search: search || undefined,
      per_page: 100,
    }

    if (props.status) {
      params.status = props.status
    }

    const response = props.module === 'sales'
      ? await fetchSaleUnits(params)
      : await fetchRentalUnits(params)

    remoteUnits.value = response.data ?? []
  } finally {
    loading.value = false
  }
}

function onSearchQuery(query) {
  if (!usesRemoteSearch.value) return
  loadRemoteUnits(query)
}

watch(
  () => [props.buildingId, props.module, props.status, usesRemoteSearch.value],
  () => {
    if (!usesRemoteSearch.value) return
    loadRemoteUnits('')
  },
  { immediate: true },
)
</script>
