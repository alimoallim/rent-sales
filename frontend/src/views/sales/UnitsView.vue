<template>
  <section>
    <PageHeader
      title="Sale units"
      subtitle="Track inventory, list prices, and buyer payment progress."
      :breadcrumbs="[{ label: 'Sales', to: '/sales' }, { label: 'Units' }]"
    >
      <template #actions>
        <button type="button" class="btn-primary w-full sm:w-auto" @click="openCreate">Add unit</button>
      </template>
      <template v-if="summary" #kpis>
        <KpiCard
          label="Total units"
          :value="String(summary.total)"
          hint="In current filter"
          accent="neutral"
        />
        <KpiCard
          label="Available"
          :value="String(summary.available)"
          :hint="`${formatMoney(summary.available_list_value, 'sales')} list value`"
          accent="success"
        />
        <KpiCard
          label="Sold"
          :value="String(summary.sold)"
          :hint="`${summary.sell_through_rate}% sell-through`"
          accent="info"
        />
        <KpiCard
          label="Outstanding"
          :value="formatMoney(summary.outstanding_on_sold, 'sales')"
          hint="Balance due on sold units"
          accent="warning"
        />
      </template>
    </PageHeader>

    <div
      v-if="summary && summary.sold > 0"
      class="content-panel mb-5 grid gap-4 p-4 sm:grid-cols-3"
    >
      <div>
        <p class="text-[11px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Agreed on sold units</p>
        <p class="mt-1 text-lg font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">
          <MoneyCell :amount="summary.sold_agreed_value" module="sales" align="left" />
        </p>
      </div>
      <div>
        <p class="text-[11px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Collected</p>
        <p class="mt-1 text-lg font-semibold">
          <MoneyCell :amount="summary.collected_on_sold" module="sales" align="left" />
        </p>
      </div>
      <div>
        <p class="text-[11px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Collection rate</p>
        <p class="mt-1 text-lg font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ collectionRate }}%</p>
        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
          <div
            class="h-full rounded-full bg-emerald-500 transition-all duration-300"
            :style="{ width: `${collectionRate}%` }"
          />
        </div>
      </div>
    </div>

    <FilterBar>
      <BuildingSearchSelect
        v-model="filters.building_id"
        :buildings="buildings"
        include-all
        placeholder="All buildings"
        @change="loadTable"
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
    </FilterBar>

    <DataTable
      v-model:search="search"
      server-side
      :items="items"
      :columns="columns"
      :loading="loading"
      :pagination="pagination"
      money-module="sales"
      :empty-message="emptyMessage"
      :row-class="unitRowClass"
      @search="onSearchChange"
      @page-change="goToPage"
      @per-page-change="setPerPage"
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
        <MoneyCell :amount="item.list_price" module="sales" />
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
        <span v-else class="text-sm font-medium text-emerald-700 dark:text-emerald-400">Ready for sale</span>
      </template>

      <template #cell-sale_progress="{ item }">
        <div v-if="item.sale_client" class="min-w-[8rem]">
          <div class="flex items-center justify-between gap-2 text-xs">
            <span class="text-zinc-500 dark:text-zinc-400">Paid</span>
            <span class="font-medium tabular-nums text-zinc-900 dark:text-zinc-100">{{ saleProgressPercent(item) }}%</span>
          </div>
          <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
            <div
              class="h-full rounded-full transition-all duration-300"
              :class="saleProgressBarClass(item)"
              :style="{ width: `${saleProgressPercent(item)}%` }"
            />
          </div>
          <p class="mt-1 text-xs">
            <MoneyCell
              v-if="Number(item.sale_client.balance) > 0"
              :amount="item.sale_client.balance"
              module="sales"
              align="left"
            />
            <span v-else class="font-medium text-emerald-700 dark:text-emerald-400">Fully paid</span>
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
    </DataTable>

    <AppDialog v-model:open="showForm" :title="editing ? 'Edit unit' : 'Add unit'" size="lg">
      <div class="grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
          <FormField label="Building" required>
            <BuildingSearchSelect
              v-model="form.sale_building_id"
              :buildings="buildings"
              required
            />
          </FormField>
        </div>
        <FormField label="Unit number" required>
          <input v-model="form.house_number" class="input-field" required />
        </FormField>
        <FormField label="Floor" required>
          <input v-model="form.floor" class="input-field" required />
        </FormField>
        <div class="sm:col-span-2">
          <FormField label="Description" required>
            <textarea v-model="form.description" rows="2" class="input-field" required />
          </FormField>
        </div>
        <FormField :label="moneyLabel('List price', 'sales')" required>
          <input v-model="form.list_price" type="number" min="0" step="0.01" class="input-field" required />
        </FormField>
      </div>
      <p v-if="error" class="mt-3 text-sm text-red-600 dark:text-red-400" role="alert">{{ error }}</p>
      <template #footer>
        <button type="button" class="btn-secondary w-full sm:w-auto" :disabled="saving" @click="closeForm">Cancel</button>
        <button type="button" class="btn-primary w-full sm:w-auto" :disabled="saving" @click="save">
          {{ saving ? 'Saving…' : 'Save' }}
        </button>
      </template>
    </AppDialog>
  </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import PageHeader from '../../components/PageHeader.vue'
import AppDialog from '../../components/ui/AppDialog.vue'
import BuildingSearchSelect from '../../components/ui/BuildingSearchSelect.vue'
import FilterBar from '../../components/ui/FilterBar.vue'
import FormField from '../../components/ui/FormField.vue'
import KpiCard from '../../components/ui/KpiCard.vue'
import StatusBadge from '../../components/ui/StatusBadge.vue'
import DataTable from '../../components/data/DataTable.vue'
import MoneyCell from '../../components/data/MoneyCell.vue'
import ClientNameLink from '../../components/sales/ClientNameLink.vue'
import { useConfirm } from '../../composables/useConfirm'
import { usePaginatedList } from '../../composables/usePaginatedList'
import { useToast } from '../../composables/useToast'
import { createUnit, deleteUnit, fetchBuildings, fetchUnits, updateUnit } from '../../api/sales'
import { formatMoney, moneyLabel } from '../../utils/money'

const { confirm } = useConfirm()
const toast = useToast()

const buildings = ref([])
const summary = ref(null)
const saving = ref(false)
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

const {
  items,
  loading,
  search,
  pagination,
  load,
  reload,
  goToPage,
  setPerPage,
  onSearchChange,
} = usePaginatedList(async (params) => {
  const response = await fetchUnits({
    ...params,
    building_id: filters.building_id || undefined,
    status: filters.status || undefined,
  })
  summary.value = response.summary ?? null
  return response
})

const columns = [
  { key: 'house_number', label: 'Unit', cardTitle: true },
  { key: 'building_name', label: 'Building', mobileCard: true },
  { key: 'description', label: 'Description', tabletCard: true },
  { key: 'list_price', label: 'List price', align: 'right', money: true, mobileCard: true },
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

function setStatus(status) {
  filters.status = status
  reload()
}

async function loadBuildings() {
  try {
    const response = await fetchBuildings()
    buildings.value = response.data
  } catch {
    toast.error('Could not load buildings.')
  }
}

async function loadTable() {
  await reload()
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
  saving.value = true
  try {
    const payload = { ...form, list_price: Number(form.list_price) }
    if (editing.value) {
      await updateUnit(editing.value.id, payload)
      toast.success('Unit updated.')
    } else {
      await createUnit(payload)
      toast.success('Unit created.')
    }
    closeForm()
    await reload()
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not save unit.'
  } finally {
    saving.value = false
  }
}

async function remove(unit) {
  const ok = await confirm({
    title: 'Delete unit',
    message: `Delete unit ${unit.house_number}? This cannot be undone.`,
    confirmLabel: 'Delete',
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deleteUnit(unit.id)
    toast.success('Unit deleted.')
    await reload()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Could not delete unit.')
  }
}

onMounted(async () => {
  await loadBuildings()
  await load()
})
</script>
