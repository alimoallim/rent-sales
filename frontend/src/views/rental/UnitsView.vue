<template>
  <section>
    <PageHeader
      title="Units"
      subtitle="Monitor apartment availability, occupancy, and monthly rent across your buildings."
    >
      <template #actions>
        <button type="button" class="btn-primary w-full sm:w-auto" @click="openCreate">
          Add unit
        </button>
      </template>
    </PageHeader>

    <div v-if="summary" class="dashboard-metrics-grid mb-5">
      <DashboardMetricCard
        label="Total units"
        :value="String(summary.total)"
        hint="In current filter"
        accent="neutral"
      />
      <DashboardMetricCard
        label="Available"
        :value="String(summary.vacant)"
        :hint="summary.vacant === 1 ? 'Ready to let' : 'Vacant units ready to let'"
        accent="success"
      />
      <DashboardMetricCard
        label="Occupied"
        :value="String(summary.occupied)"
        :hint="summary.occupied === 1 ? 'Currently tenanted' : 'Units with active tenants'"
        accent="info"
      />
      <DashboardMetricCard
        label="Occupancy rate"
        :value="`${summary.occupancy_rate}%`"
        :hint="`${summary.occupied} of ${summary.total} units occupied`"
        accent="warning"
      />
    </div>

    <div class="filter-bar">
      <BuildingSearchSelect
        v-model="filters.building_id"
        :buildings="buildings"
        include-all
        placeholder="All buildings"
        @change="load"
      />
      <div class="segmented-control">
        <button
          type="button"
          class="segmented-option"
          :class="{ 'segmented-option-active': filters.status === '' }"
          @click="setStatus('')"
        >
          All
        </button>
        <button
          type="button"
          class="segmented-option"
          :class="{ 'segmented-option-active': filters.status === 'vacant' }"
          @click="setStatus('vacant')"
        >
          Available
        </button>
        <button
          type="button"
          class="segmented-option"
          :class="{ 'segmented-option-active': filters.status === 'occupied' }"
          @click="setStatus('occupied')"
        >
          Occupied
        </button>
      </div>
    </div>

    <ResponsiveDataList
      :items="units"
      :columns="columns"
      :empty-message="emptyMessage"
      :row-class="unitRowClass"
    >
      <template #card-title-house_number="{ item }">
        <div class="flex items-center gap-2">
          <span class="font-semibold text-zinc-900 dark:text-zinc-100">Unit {{ item.house_number }}</span>
          <StatusBadge :variant="statusVariant(item.status)" :label="statusLabel(item.status)" />
        </div>
        <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">Floor {{ item.floor }}</p>
      </template>

      <template #cell-house_number="{ item }">
        <div>
          <p class="font-medium text-zinc-900 dark:text-zinc-100">Unit {{ item.house_number }}</p>
          <p class="text-xs text-zinc-500 dark:text-zinc-400">Floor {{ item.floor }}</p>
        </div>
      </template>

      <template #cell-description="{ item }">
        <p class="max-w-xs truncate text-zinc-700 dark:text-zinc-300" :title="item.description">{{ item.description || '—' }}</p>
      </template>

      <template #cell-monthly_rent="{ item }">
        <span class="font-medium tabular-nums text-zinc-900 dark:text-zinc-100">{{ formatMoney(item.monthly_rent, 'rental') }}</span>
      </template>

      <template #cell-status="{ item }">
        <StatusBadge :variant="statusVariant(item.status)" :label="statusLabel(item.status)" />
      </template>

      <template #cell-occupant="{ item }">
        <TenantNameMenu
          v-if="item.active_tenant"
          :tenant-id="item.active_tenant.id"
          :tenant-name="item.active_tenant.name"
          :building-id="item.rental_building_id"
        />
        <span v-else class="text-sm font-medium text-emerald-700">Available</span>
      </template>

      <template #actions="{ item }">
        <button type="button" class="btn-secondary w-full sm:w-auto" @click="openEdit(item)">Edit</button>
        <button
          v-if="item.status === 'vacant'"
          type="button"
          class="btn-destructive w-full sm:w-auto"
          @click="remove(item)"
        >
          Delete
        </button>
      </template>
    </ResponsiveDataList>

    <AppDialog v-model:open="showForm" :title="editing ? 'Edit unit' : 'Add unit'" size="md">
      <div class="grid gap-4">
        <label v-if="!editing" class="label-field">
          Building
          <BuildingSearchSelect
            v-model="form.rental_building_id"
            :buildings="buildings"
            required
          />
        </label>
        <label class="label-field">
          Unit number
          <input v-model="form.house_number" class="input-field" required />
        </label>
        <label class="label-field">
          Floor
          <input v-model="form.floor" class="input-field" required />
        </label>
        <label class="label-field">
          Description
          <input v-model="form.description" class="input-field" required />
        </label>
        <label class="label-field">
          {{ moneyLabel('Monthly rent', 'rental') }}
          <input v-model="form.monthly_rent" type="number" min="0" step="0.01" class="input-field" required />
        </label>
      </div>
      <p v-if="error" class="mt-3 text-sm text-red-600">{{ error }}</p>
      <template #footer>
        <button type="button" class="btn-secondary w-full sm:w-auto" @click="closeForm">Cancel</button>
        <button type="button" class="btn-primary w-full sm:w-auto" @click="save">Save</button>
      </template>
    </AppDialog>
  </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import PageHeader from '../../components/PageHeader.vue'
import AppDialog from '../../components/ui/AppDialog.vue'
import BuildingSearchSelect from '../../components/ui/BuildingSearchSelect.vue'
import StatusBadge from '../../components/ui/StatusBadge.vue'
import ResponsiveDataList from '../../components/data/ResponsiveDataList.vue'
import DashboardMetricCard from '../../components/dashboard/DashboardMetricCard.vue'
import TenantNameMenu from '../../components/rental/TenantNameMenu.vue'
import { createUnit, deleteUnit, fetchBuildings, fetchUnits, updateUnit } from '../../api/rental'
import { formatMoney, moneyLabel } from '../../utils/money'

const buildings = ref([])
const units = ref([])
const summary = ref(null)
const showForm = ref(false)
const editing = ref(null)
const error = ref('')
const filters = reactive({ building_id: '', status: '' })
const form = reactive({
  rental_building_id: '',
  house_number: '',
  floor: '',
  description: '',
  monthly_rent: '',
})

const columns = [
  { key: 'house_number', label: 'Unit', cardTitle: true },
  { key: 'building_name', label: 'Building', mobileCard: true },
  { key: 'description', label: 'Description', tabletCard: true },
  { key: 'monthly_rent', label: 'Monthly rent', align: 'right', mobileCard: true },
  { key: 'status', label: 'Status', tabletCard: true },
  { key: 'occupant', label: 'Occupant', mobileCard: true },
]

const emptyMessage = computed(() => {
  if (filters.status === 'vacant') return 'No available units match this filter.'
  if (filters.status === 'occupied') return 'No occupied units match this filter.'
  return 'No units found. Add your first unit to start tracking availability.'
})

function statusLabel(status) {
  return status === 'vacant' ? 'Available' : 'Occupied'
}

function statusVariant(status) {
  return status === 'vacant' ? 'success' : 'info'
}

function unitRowClass(item) {
  if (item.status !== 'vacant') return ''

  return 'border-emerald-200 bg-emerald-50 hover:bg-emerald-100/80 dark:border-emerald-800 dark:bg-emerald-950/40 dark:hover:bg-emerald-900/50'
}

function setStatus(status) {
  filters.status = status
  load()
}

async function loadBuildings() {
  const response = await fetchBuildings()
  buildings.value = response.data
}

async function load() {
  const params = {}
  if (filters.building_id) params.building_id = filters.building_id
  if (filters.status) params.status = filters.status
  const response = await fetchUnits(params)
  units.value = response.data
  summary.value = response.summary ?? null
}

function openCreate() {
  editing.value = null
  Object.assign(form, {
    rental_building_id: filters.building_id || '',
    house_number: '',
    floor: '',
    description: '',
    monthly_rent: '',
  })
  error.value = ''
  showForm.value = true
}

function openEdit(unit) {
  editing.value = unit
  Object.assign(form, {
    rental_building_id: unit.rental_building_id,
    house_number: unit.house_number,
    floor: unit.floor,
    description: unit.description,
    monthly_rent: unit.monthly_rent,
  })
  error.value = ''
  showForm.value = true
}

function closeForm() {
  showForm.value = false
}

async function save() {
  error.value = ''
  try {
    const payload = {
      house_number: form.house_number,
      floor: form.floor,
      description: form.description,
      monthly_rent: form.monthly_rent,
    }
    if (editing.value) {
      await updateUnit(editing.value.id, payload)
    } else {
      await createUnit({ ...payload, rental_building_id: form.rental_building_id })
    }
    closeForm()
    await load()
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not save unit.'
  }
}

async function remove(unit) {
  if (!confirm(`Delete unit ${unit.house_number}?`)) return
  try {
    await deleteUnit(unit.id)
    await load()
  } catch (e) {
    alert(e.response?.data?.message || 'Could not delete unit.')
  }
}

onMounted(async () => {
  await loadBuildings()
  await load()
})
</script>
