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
import { fetchClient, fetchClients } from '../../api/sales'
import { clientOptions } from '../../utils/selectOptions'

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  buildingId: { type: [String, Number], default: '' },
  clients: { type: Array, default: null },
  placeholder: { type: String, default: 'Select client' },
  searchPlaceholder: { type: String, default: 'Search client name or unit…' },
  disabled: { type: Boolean, default: false },
  required: { type: Boolean, default: false },
  status: { type: String, default: 'active' },
})

const emit = defineEmits(['update:modelValue', 'change'])

const remoteClients = ref([])
const loading = ref(false)

const usesRemoteSearch = computed(() => props.clients === null && props.buildingId !== '' && props.buildingId != null)
const canSearch = computed(() => !usesRemoteSearch.value || Boolean(props.buildingId))

const options = computed(() => clientOptions(props.clients ?? remoteClients.value))

async function ensureSelectedClient(records) {
  if (!props.modelValue) return records

  if (records.some((client) => String(client.id) === String(props.modelValue))) {
    return records
  }

  try {
    const client = await fetchClient(props.modelValue)
    return [client, ...records]
  } catch {
    return records
  }
}

async function loadRemoteClients(search = '') {
  if (!usesRemoteSearch.value || !props.buildingId) {
    remoteClients.value = []
    return
  }

  loading.value = true
  try {
    const response = await fetchClients({
      building_id: props.buildingId,
      status: props.status,
      search: search || undefined,
      per_page: 50,
    })
    remoteClients.value = await ensureSelectedClient(response.data ?? [])
  } finally {
    loading.value = false
  }
}

function onSearchQuery(query) {
  if (!usesRemoteSearch.value) return
  loadRemoteClients(query)
}

watch(
  () => [props.buildingId, props.status, usesRemoteSearch.value],
  () => {
    if (!usesRemoteSearch.value) return
    loadRemoteClients('')
  },
  { immediate: true },
)

watch(
  () => props.modelValue,
  () => {
    if (!usesRemoteSearch.value || !props.modelValue) return
    loadRemoteClients('')
  },
)
</script>
