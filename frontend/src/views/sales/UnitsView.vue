<template>
  <section>
    <PageHeader
      title="Sale units"
      subtitle="Track inventory, list prices, and buyer payment progress to support sales decisions."
    >
      <template #actions>
        <button type="button" class="btn-primary w-full sm:w-auto" @click="openCreate">Add unit</button>
      </template>
    </PageHeader>

    <div v-if="summary" class="dashboard-metrics-grid mb-4">
      <DashboardMetricCard
        label="Total units"
        :value="String(summary.total)"
        hint="In current filter"
        accent="neutral"
      />
      <DashboardMetricCard
        label="Available"
        :value="String(summary.available)"
        :hint="formatMoney(summary.available_list_value, 'sales') + ' list value'"
        accent="success"
      />
      <DashboardMetricCard
        label="Sold"
        :value="String(summary.sold)"
        :hint="`${summary.sell_through_rate}% sell-through`"
        accent="info"
      />
      <DashboardMetricCard
        label="Outstanding"
        :value="formatMoney(summary.outstanding_on_sold, 'sales')"
        hint="Balance due on sold units"
        accent="warning"
      />
    </div>

    <div
      v-if="summary && summary.sold > 0"
      class="mb-5 grid gap-3 rounded-xl border border-zinc-200 bg-zinc-50 dark:bg-zinc-900/80 p-4 sm:grid-cols-3"
    >
      <div>
        <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Agreed on sold units</p>
        <p class="mt-1 text-lg font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">
          {{ formatMoney(summary.sold_agreed_value, 'sales') }}
        </p>
      </div>
      <div>
        <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Collected</p>
        <p class="mt-1 text-lg font-semibold tabular-nums text-emerald-700">
          {{ formatMoney(summary.collected_on_sold, 'sales') }}
        </p>
      </div>
      <div>
        <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Collection rate</p>
        <p class="mt-1 text-lg font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">
          {{ collectionRate }}%
        </p>
        <div class="mt-2 h-2 overflow-hidden rounded-full bg-zinc-200">
          <div
            class="h-full rounded-full bg-emerald-500 transition-all duration-300"
            :style="{ width: `${collectionRate}%` }"
          />
        </div>
      </div>
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
          :class="{ 'segmented-option-active': filters.status === 'available' }"
          @click="setStatus('available')"
        >
          Available
        </button>
        <button
          type="button"
          class="segmented-option"
          :class="{ 'segmented-option-active': filters.status === 'sold' }"
          @click="setStatus('sold')"
        >
          Sold
        </button>
      </div>
    </div>

    <ResponsiveDataList
      :items="units"
      :columns="columns"
      money-module="sales"
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

      <template #cell-list_price="{ item }">
        <span class="font-medium tabular-nums text-zinc-900 dark:text-zinc-100">{{ formatMoney(item.list_price, 'sales') }}</span>
      </template>

      <template #cell-status="{ item }">
        <StatusBadge :variant="statusVariant(item.status)" :label="statusLabel(item.status)" />
      </template>

      <template #cell-buyer="{ item }">
        <ClientNameLink
          v-if="item.sale_client"
          :client-id="item.sale_client.id"
          :client-name="item.sale_client.name"
          :building-id="item.sale_building_id"
        />
        <span v-else class="text-sm font-medium text-emerald-700">Ready for sale</span>
      </template>

      <template #cell-sale_progress="{ item }">
        <div v-if="item.sale_client" class="min-w-[8rem]">
          <div class="flex items-center justify-between gap-2 text-xs">
            <span class="text-zinc-500 dark:text-zinc-400">Paid</span>
            <span class="font-medium tabular-nums text-zinc-900 dark:text-zinc-100">{{ saleProgressPercent(item) }}%</span>
          </div>
          <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-zinc-200">
            <div
              class="h-full rounded-full transition-all duration-300"
              :class="saleProgressBarClass(item)"
              :style="{ width: `${saleProgressPercent(item)}%` }"
            />
          </div>
          <p
            class="mt-1 text-xs tabular-nums"
            :class="Number(item.sale_client.balance) > 0 ? 'text-amber-700' : 'text-emerald-700'"
          >
            {{ saleProgressCaption(item) }}
          </p>
        </div>
        <span v-else class="text-xs text-zinc-500 dark:text-zinc-400">—</span>
      </template>

      <template #actions="{ item }">
        <button
          v-if="item.status === 'available'"
          type="button"
          class="btn-secondary w-full sm:w-auto"
          @click="openEdit(item)"
        >
          Edit
        </button>
        <button
          v-if="item.status === 'available'"
          type="button"
          class="btn-destructive w-full sm:w-auto"
          @click="remove(item)"
        >
          Delete
        </button>
      </template>
    </ResponsiveDataList>

    <AppDialog v-model:open="showForm" :title="editing ? 'Edit unit' : 'Add unit'" size="lg">
      <div class="grid gap-4 sm:grid-cols-2">
        <label class="label-field sm:col-span-2">
          Building
          <BuildingSearchSelect
            v-model="form.sale_building_id"
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
        <label class="label-field sm:col-span-2">
          Description
          <textarea v-model="form.description" rows="2" class="input-field" required />
        </label>
        <label class="label-field">
          {{ moneyLabel('List price', 'sales') }}
          <input v-model="form.list_price" type="number" min="0" step="0.01" class="input-field" required />
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
import ClientNameLink from '../../components/sales/ClientNameLink.vue'
import { createUnit, deleteUnit, fetchBuildings, fetchUnits, updateUnit } from '../../api/sales'
import { formatMoney, moneyLabel } from '../../utils/money'

const buildings = ref([])
const units = ref([])
const summary = ref(null)
const showForm = ref(false)
const editing = ref(null)
const error = ref('')
const filters = reactive({ building_id: '', status: '' })
const form = reactive({
  sale_building_id: '',
  house_number: '',
  floor: '',
  description: '',
  list_price: 0,
})

const columns = [
  { key: 'house_number', label: 'Unit', cardTitle: true },
  { key: 'building_name', label: 'Building', mobileCard: true },
  { key: 'description', label: 'Description', tabletCard: true },
  { key: 'list_price', label: 'List price', align: 'right', mobileCard: true },
  { key: 'status', label: 'Status', tabletCard: true },
  { key: 'buyer', label: 'Buyer', mobileCard: true },
  { key: 'sale_progress', label: 'Sale progress', mobileCard: true },
]

const emptyMessage = computed(() => {
  if (filters.status === 'available') return 'No available units match this filter.'
  if (filters.status === 'sold') return 'No sold units match this filter.'
  return 'No sale units found. Add inventory to start tracking availability.'
})

const collectionRate = computed(() => {
  if (!summary.value || Number(summary.value.sold_agreed_value) <= 0) return 0

  return Math.min(
    100,
    Math.round((Number(summary.value.collected_on_sold) / Number(summary.value.sold_agreed_value)) * 100),
  )
})

function statusLabel(status) {
  return status === 'available' ? 'Available' : 'Sold'
}

function statusVariant(status) {
  return status === 'available' ? 'success' : 'info'
}

function unitRowClass(item) {
  if (item.status !== 'available') return ''

  return 'border-emerald-200 bg-emerald-50 hover:bg-emerald-100/80 dark:border-emerald-800 dark:bg-emerald-950/40 dark:hover:bg-emerald-900/50'
}

function saleProgressPercent(item) {
  const agreed = Number(item.sale_client?.agreed_sale_price || 0)
  const paid = Number(item.sale_client?.paid_total || 0)
  if (agreed <= 0) return paid > 0 ? 100 : 0

  return Math.min(100, Math.round((paid / agreed) * 100))
}

function saleProgressBarClass(item) {
  const balance = Number(item.sale_client?.balance || 0)
  if (balance <= 0) return 'bg-emerald-500'
  if (saleProgressPercent(item) >= 50) return 'bg-amber-500'

  return 'bg-red-400'
}

function saleProgressCaption(item) {
  const balance = Number(item.sale_client?.balance || 0)
  if (balance <= 0) return 'Fully paid'

  return `${formatMoney(balance, 'sales')} outstanding`
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
    sale_building_id: filters.building_id || '',
    house_number: '',
    floor: '',
    description: '',
    list_price: 0,
  })
  error.value = ''
  showForm.value = true
}

function openEdit(unit) {
  editing.value = unit
  Object.assign(form, {
    sale_building_id: unit.sale_building_id,
    house_number: unit.house_number,
    floor: unit.floor,
    description: unit.description,
    list_price: unit.list_price,
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
    const payload = { ...form, list_price: Number(form.list_price) }
    if (editing.value) {
      await updateUnit(editing.value.id, payload)
    } else {
      await createUnit(payload)
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
