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
import { fetchTenant, fetchTenants } from '../../api/rental'
import { tenantOptions } from '../../utils/selectOptions'

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  buildingId: { type: [String, Number], default: '' },
  tenants: { type: Array, default: null },
  placeholder: { type: String, default: 'Select tenant' },
  searchPlaceholder: { type: String, default: 'Search tenant name or unit…' },
  disabled: { type: Boolean, default: false },
  required: { type: Boolean, default: false },
  status: { type: String, default: 'active' },
})

const emit = defineEmits(['update:modelValue', 'change'])

const remoteTenants = ref([])
const loading = ref(false)

const usesRemoteSearch = computed(() => props.tenants === null && props.buildingId !== '' && props.buildingId != null)
const canSearch = computed(() => !usesRemoteSearch.value || Boolean(props.buildingId))

const options = computed(() => tenantOptions(props.tenants ?? remoteTenants.value))

async function ensureSelectedTenant(records) {
  if (!props.modelValue) return records

  if (records.some((tenant) => String(tenant.id) === String(props.modelValue))) {
    return records
  }

  try {
    const tenant = await fetchTenant(props.modelValue)
    return [tenant, ...records]
  } catch {
    return records
  }
}

async function loadRemoteTenants(search = '') {
  if (!usesRemoteSearch.value || !props.buildingId) {
    remoteTenants.value = []
    return
  }

  loading.value = true
  try {
    const response = await fetchTenants({
      building_id: props.buildingId,
      status: props.status,
      search: search || undefined,
      per_page: 50,
    })
    remoteTenants.value = await ensureSelectedTenant(response.data ?? [])
  } finally {
    loading.value = false
  }
}

function onSearchQuery(query) {
  if (!usesRemoteSearch.value) return
  loadRemoteTenants(query)
}

watch(
  () => [props.buildingId, props.status, usesRemoteSearch.value],
  () => {
    if (!usesRemoteSearch.value) return
    loadRemoteTenants('')
  },
  { immediate: true },
)

watch(
  () => props.modelValue,
  () => {
    if (!usesRemoteSearch.value || !props.modelValue) return
    loadRemoteTenants('')
  },
)
</script>
