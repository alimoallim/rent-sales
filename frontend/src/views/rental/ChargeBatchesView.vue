<template>
  <section>
    <PageHeader
      title="Charge batches"
      subtitle="Generate, review, and approve monthly charges before they post to tenant balances."
      :breadcrumbs="[{ label: 'Rental', to: '/rental' }, { label: 'Charge batches' }]"
    >
      <template v-if="batch" #kpis>
        <KpiCard
          label="Tenants in batch"
          :value="String(batchStats.total)"
          :hint="`${batchStats.approved} approved · ${batchStats.excluded} excluded`"
          accent="neutral"
        />
        <KpiCard
          label="Ready to approve"
          :value="String(batchStats.ready)"
          hint="Draft or partially approved tenants"
          accent="accent"
        />
        <KpiCard
          label="Pending readings"
          :value="String(batchStats.pending)"
          hint="Waiting for water or electricity readings"
          accent="warning"
        />
        <KpiCard
          label="Batch total"
          :value="formatMoney(batchGrandTotal, 'rental')"
          :hint="`${approvalProgress}% approval progress`"
          accent="success"
        />
      </template>
    </PageHeader>

    <FilterBar>
      <BuildingSearchSelect
        v-model="filters.building_id"
        :buildings="buildings"
        placeholder="Select building"
        search-placeholder="Search buildings…"
        @change="onFiltersChange"
      />
      <select v-model="filters.billing_month" class="input-field mt-0 sm:min-w-[9rem]" @change="onFiltersChange">
        <option v-for="month in months" :key="month.value" :value="month.value">{{ month.label }}</option>
      </select>
      <input
        v-model="filters.billing_year"
        type="number"
        min="2000"
        class="input-field mt-0 w-full sm:w-28"
        @change="onFiltersChange"
      />
    </FilterBar>

    <div v-if="error" class="alert-error mb-4">{{ error }}</div>

    <div v-if="!filters.building_id" class="content-panel">
      <EmptyState
        title="Select a building and billing period"
        description="Choose the property and month above to view or create a charge batch."
      />
    </div>

    <TableSkeleton v-else-if="loading" :rows="6" :columns="7" />

    <div v-else-if="!batch" class="content-panel">
      <EmptyState
        title="No charge batch for this period"
        :description="`Generate a draft batch for ${selectedBuildingName} · ${periodLabel}. Review rent, service, water, and electricity lines per tenant, then approve to post charges.`"
      >
        <template #action>
          <button type="button" class="btn-primary" :disabled="actionLoading" @click="generate">
            {{ actionLoading ? 'Generating…' : 'Generate draft batch' }}
          </button>
        </template>
      </EmptyState>
    </div>

    <div v-else class="space-y-4">
      <div class="content-panel charge-batch-summary">
        <div class="charge-batch-summary-grid">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Monthly charge batch</p>
              <span class="badge" :class="statusBadgeClass(batch.status, batch.is_complete)">
                {{ statusLabel(batch.status, batch.is_complete) }}
              </span>
            </div>
            <h2 class="mt-1 text-lg font-semibold tracking-tight text-zinc-900 dark:text-zinc-100 sm:text-xl">
              {{ batch.building_name }} · {{ batch.period_label }}
            </h2>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
              Generated {{ formatDateTime(batch.generated_at) }} by {{ batch.generated_by_name }}
            </p>
            <div class="mt-4 max-w-xl">
              <div class="flex items-center justify-between gap-3 text-xs text-zinc-500 dark:text-zinc-400">
                <span>Approval progress</span>
                <span class="font-medium tabular-nums text-zinc-700 dark:text-zinc-300">
                  {{ batchStats.approved }} / {{ batchStats.total - batchStats.excluded }} tenants
                </span>
              </div>
              <div class="charge-batch-progress-track">
                <div class="charge-batch-progress-fill" :style="{ width: `${approvalProgress}%` }" />
              </div>
            </div>
            <p v-if="batch.is_complete" class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">
              All tenants approved or excluded. You can still edit amounts or reopen a tenant to adjust.
            </p>
          </div>

          <div class="charge-batch-actions lg:flex lg:flex-col lg:justify-center lg:border-l lg:border-zinc-200 lg:pl-5 dark:lg:border-zinc-700">
            <button type="button" class="btn-primary" :disabled="actionLoading" @click="confirmApproveAll">
              Approve all ready tenants
            </button>
            <button type="button" class="btn-secondary" :disabled="actionLoading" @click="refreshPending">
              Refresh amounts & readings
            </button>
          </div>
        </div>
      </div>

      <div class="data-table">
        <div class="data-table-toolbar">
          <div class="data-table-search">
            <label class="sr-only" for="charge-batch-search">Search tenants</label>
            <svg class="data-table-search-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path stroke-linecap="round" d="m21 21-5.2-5.2M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" />
            </svg>
            <input
              id="charge-batch-search"
              v-model="search"
              type="search"
              class="input-field data-table-search-input"
              placeholder="Search tenant or apartment…"
              autocomplete="off"
            />
          </div>

          <div class="data-table-toolbar-meta flex-wrap">
            <div class="w-full overflow-x-auto sm:w-auto">
              <div class="segmented-control min-w-max">
                <button
                  v-for="option in statusOptions"
                  :key="option.value"
                  type="button"
                  class="segmented-option whitespace-nowrap"
                  :class="{ 'segmented-option-active': statusFilter === option.value }"
                  @click="statusFilter = option.value"
                >
                  {{ option.label }}
                  <span v-if="option.count !== null" class="ml-1 tabular-nums text-zinc-400">({{ option.count }})</span>
                </button>
              </div>
            </div>
            <p class="data-table-summary">
              {{ filteredGroups.length }} tenant{{ filteredGroups.length === 1 ? '' : 's' }}
            </p>
          </div>
        </div>

        <!-- Mobile / tablet cards -->
        <div class="space-y-2 lg:hidden">
          <article
            v-for="group in paginatedGroups"
            :key="group.tenant_id"
            class="charge-batch-card"
            :class="{ 'charge-batch-card-excluded': group.tenant_status === 'excluded' }"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="font-semibold text-zinc-900 dark:text-zinc-100">{{ group.tenant_name }}</p>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ group.unit_label || 'No unit' }}</p>
              </div>
              <span class="badge shrink-0" :class="tenantStatusClass(group.tenant_status)">
                {{ tenantStatusLabel(group.tenant_status) }}
              </span>
            </div>

            <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2 text-sm">
              <div>
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Rent</dt>
                <dd class="mt-0.5">
                  <ChargeBatchAmountCell :item="itemForType(group, 'rent')" :editable="true" @edit="openEditItem" />
                </dd>
              </div>
              <div>
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Service</dt>
                <dd class="mt-0.5">
                  <ChargeBatchAmountCell :item="itemForType(group, 'service')" :editable="true" @edit="openEditItem" />
                </dd>
              </div>
              <div>
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Water</dt>
                <dd class="mt-0.5">
                  <ChargeBatchAmountCell :item="itemForType(group, 'water')" :editable="true" @edit="openEditItem" />
                </dd>
              </div>
              <div>
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Electricity</dt>
                <dd class="mt-0.5">
                  <ChargeBatchAmountCell :item="itemForType(group, 'electricity')" :editable="true" @edit="openEditItem" />
                </dd>
              </div>
            </dl>

            <div class="mt-3 flex items-center justify-between border-t border-zinc-200 pt-3 dark:border-zinc-700">
              <div>
                <p class="text-[10px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Subtotal</p>
                <MoneyCell :amount="group.subtotal" module="rental" />
              </div>
              <div v-if="group.tenant_status !== 'excluded'" class="flex flex-wrap justify-end gap-1">
                <button
                  v-if="group.tenant_status !== 'approved'"
                  type="button"
                  class="btn-secondary !min-h-8 !px-2 !py-1 text-xs"
                  :disabled="actionLoading"
                  @click="approveTenant(group.tenant_id)"
                >
                  Approve
                </button>
                <button
                  v-if="group.tenant_status === 'approved'"
                  type="button"
                  class="btn-secondary !min-h-8 !px-2 !py-1 text-xs"
                  :disabled="actionLoading"
                  @click="reopenTenant(group.tenant_id)"
                >
                  Reopen
                </button>
                <button
                  v-if="group.tenant_status !== 'approved'"
                  type="button"
                  class="btn-ghost !min-h-8 !px-2 !py-1 text-xs text-amber-800 dark:text-amber-300"
                  :disabled="actionLoading"
                  @click="excludeTenant(group)"
                >
                  Exclude
                </button>
              </div>
            </div>

            <p v-if="groupExclusionReason(group)" class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
              {{ groupExclusionReason(group) }}
            </p>
          </article>

          <p v-if="filteredGroups.length === 0" class="empty-state-panel">
            No tenants match your search or filter.
          </p>
        </div>

        <!-- Desktop table -->
        <div class="table-shell hidden lg:block">
          <div class="table-scroll">
            <table class="data-grid min-w-[56rem] w-full text-sm">
              <thead class="data-grid-head">
                <tr>
                  <th class="data-grid-th min-w-[11rem]">Tenant / apartment</th>
                  <th class="data-grid-th w-28 text-right">Rent</th>
                  <th class="data-grid-th w-28 text-right">Service</th>
                  <th class="data-grid-th w-28 text-right">Water</th>
                  <th class="data-grid-th w-28 text-right">Electricity</th>
                  <th class="data-grid-th w-32 text-right">Subtotal</th>
                  <th class="data-grid-th w-36 text-right">Actions</th>
                </tr>
              </thead>
              <tbody class="data-grid-body">
                <tr
                  v-for="group in paginatedGroups"
                  :key="group.tenant_id"
                  class="data-grid-row"
                  :class="group.tenant_status === 'excluded' ? 'opacity-60' : ''"
                >
                  <td class="data-grid-td">
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                      <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ group.tenant_name }}</span>
                      <span class="text-xs text-zinc-500 dark:text-zinc-400">· {{ group.unit_label || 'No unit' }}</span>
                      <span class="badge !py-0 text-[10px]" :class="tenantStatusClass(group.tenant_status)">
                        {{ tenantStatusLabel(group.tenant_status) }}
                      </span>
                    </div>
                    <p
                      v-if="groupExclusionReason(group)"
                      class="mt-0.5 truncate text-xs text-zinc-500 dark:text-zinc-400"
                      :title="groupExclusionReason(group)"
                    >
                      {{ groupExclusionReason(group) }}
                    </p>
                  </td>
                  <td class="data-grid-td text-right">
                    <ChargeBatchAmountCell :item="itemForType(group, 'rent')" :editable="true" @edit="openEditItem" />
                  </td>
                  <td class="data-grid-td text-right">
                    <ChargeBatchAmountCell :item="itemForType(group, 'service')" :editable="true" @edit="openEditItem" />
                  </td>
                  <td class="data-grid-td text-right">
                    <ChargeBatchAmountCell :item="itemForType(group, 'water')" :editable="true" @edit="openEditItem" />
                  </td>
                  <td class="data-grid-td text-right">
                    <ChargeBatchAmountCell :item="itemForType(group, 'electricity')" :editable="true" @edit="openEditItem" />
                  </td>
                  <td class="data-grid-td text-right font-semibold">
                    <MoneyCell :amount="group.subtotal" module="rental" />
                  </td>
                  <td class="data-grid-td text-right">
                    <div v-if="group.tenant_status !== 'excluded'" class="flex flex-wrap justify-end gap-1">
                      <button
                        v-if="group.tenant_status !== 'approved'"
                        type="button"
                        class="btn-secondary !min-h-8 !px-2 !py-1 text-xs"
                        :disabled="actionLoading"
                        @click="approveTenant(group.tenant_id)"
                      >
                        Approve
                      </button>
                      <button
                        v-if="group.tenant_status === 'approved'"
                        type="button"
                        class="btn-secondary !min-h-8 !px-2 !py-1 text-xs"
                        :disabled="actionLoading"
                        @click="reopenTenant(group.tenant_id)"
                      >
                        Reopen
                      </button>
                      <button
                        v-if="group.tenant_status !== 'approved'"
                        type="button"
                        class="btn-ghost !min-h-8 !px-2 !py-1 text-xs text-amber-800 dark:text-amber-300"
                        :disabled="actionLoading"
                        @click="excludeTenant(group)"
                      >
                        Exclude
                      </button>
                    </div>
                    <span v-else class="text-xs text-zinc-400">—</span>
                  </td>
                </tr>
                <tr v-if="filteredGroups.length === 0">
                  <td colspan="7" class="data-grid-empty">No tenants match your search or filter.</td>
                </tr>
              </tbody>
              <tfoot v-if="filteredGroups.length" class="data-grid-foot">
                <tr>
                  <td class="data-grid-td" colspan="5">
                    Batch total ({{ batchStats.total }} tenants)
                  </td>
                  <td class="data-grid-td text-right">
                    <MoneyCell :amount="batchGrandTotal" module="rental" />
                  </td>
                  <td class="data-grid-td" />
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <TablePagination
          v-if="filteredGroups.length"
          :current-page="page"
          :last-page="lastPage"
          :per-page="perPage"
          :total="filteredGroups.length"
          :from="paginationFrom"
          :to="paginationTo"
          :page-size-options="[10, 25, 50, 100]"
          @update:page="page = $event"
          @update:per-page="onPerPageChange"
        />
      </div>
    </div>

    <AppDialog v-model:open="editDialogOpen" title="Adjust line item" size="md">
      <form class="space-y-4" @submit.prevent="saveItemEdit">
        <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ chargeTypeLabel(editingItem?.charge_type) }} for selected tenant.</p>
        <FormField :label="amountLabel('rental')" required>
          <input v-model="editForm.amount" type="number" min="0" step="0.01" class="input-field" required />
        </FormField>
        <FormField label="Reason for adjustment" hint="Optional note for audit trail">
          <textarea v-model="editForm.adjustment_note" rows="2" class="input-field" placeholder="Optional note for audit trail" />
        </FormField>
        <div class="flex justify-end gap-2">
          <button type="button" class="btn-secondary" @click="editDialogOpen = false">Cancel</button>
          <button type="submit" class="btn-primary" :disabled="actionLoading">{{ actionLoading ? 'Saving…' : 'Save' }}</button>
        </div>
      </form>
    </AppDialog>

    <AppDialog v-model:open="approveAllDialogOpen" title="Approve charge batch" size="md">
      <p class="text-sm text-zinc-700 dark:text-zinc-300">
        You are approving charges for all tenants without pending meter readings for
        <strong>{{ batch?.building_name }}</strong> · <strong>{{ batch?.period_label }}</strong>.
        Approved charges post to tenant balances. The batch stays open so you can edit amounts or reopen tenants if something was wrong.
      </p>
      <div class="mt-4 flex justify-end gap-2">
        <button type="button" class="btn-secondary" @click="approveAllDialogOpen = false">Cancel</button>
        <button type="button" class="btn-primary" :disabled="actionLoading" @click="approveAll">Confirm approval</button>
      </div>
    </AppDialog>
  </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { valuesMatchSearch } from '../../utils/search'
import { formatMoney, amountLabel } from '../../utils/money'
import { useRoute } from 'vue-router'
import PageHeader from '../../components/PageHeader.vue'
import AppDialog from '../../components/ui/AppDialog.vue'
import BuildingSearchSelect from '../../components/ui/BuildingSearchSelect.vue'
import FilterBar from '../../components/ui/FilterBar.vue'
import FormField from '../../components/ui/FormField.vue'
import EmptyState from '../../components/ui/EmptyState.vue'
import KpiCard from '../../components/ui/KpiCard.vue'
import TableSkeleton from '../../components/data/TableSkeleton.vue'
import TablePagination from '../../components/data/TablePagination.vue'
import MoneyCell from '../../components/data/MoneyCell.vue'
import ChargeBatchAmountCell from '../../components/rental/ChargeBatchAmountCell.vue'
import { useConfirm } from '../../composables/useConfirm'
import { useToast } from '../../composables/useToast'
import {
  approveAllChargeBatch,
  approveChargeBatchTenant,
  excludeChargeBatchTenant,
  fetchBuildings,
  fetchChargeBatch,
  generateChargeBatch,
  refreshChargeBatchPending,
  reopenChargeBatchTenant,
  updateChargeBatchItem,
} from '../../api/rental'

const { confirm: askConfirm } = useConfirm()
const toast = useToast()

const route = useRoute()
const buildings = ref([])
const batch = ref(null)
const loading = ref(false)
const actionLoading = ref(false)
const error = ref('')
const editDialogOpen = ref(false)
const approveAllDialogOpen = ref(false)
const editingItem = ref(null)
const search = ref('')
const statusFilter = ref('all')
const page = ref(1)
const perPage = ref(25)
const now = new Date()
const filters = reactive({
  building_id: '',
  billing_month: now.getMonth() + 1,
  billing_year: now.getFullYear(),
})
const editForm = reactive({
  amount: '',
  adjustment_note: '',
})

const months = [
  { value: 1, label: 'January' }, { value: 2, label: 'February' }, { value: 3, label: 'March' },
  { value: 4, label: 'April' }, { value: 5, label: 'May' }, { value: 6, label: 'June' },
  { value: 7, label: 'July' }, { value: 8, label: 'August' }, { value: 9, label: 'September' },
  { value: 10, label: 'October' }, { value: 11, label: 'November' }, { value: 12, label: 'December' },
]

const batchGrandTotal = computed(() => {
  if (!batch.value?.tenant_groups?.length) return '0.00'
  const total = batch.value.tenant_groups.reduce((sum, group) => sum + Number(group.subtotal || 0), 0)
  return total.toFixed(2)
})

const batchStats = computed(() => {
  const groups = batch.value?.tenant_groups ?? []
  return {
    total: groups.length,
    approved: groups.filter((g) => g.tenant_status === 'approved').length,
    pending: groups.filter((g) => g.tenant_status === 'pending').length,
    excluded: groups.filter((g) => g.tenant_status === 'excluded').length,
    ready: groups.filter((g) => ['draft', 'partial'].includes(g.tenant_status)).length,
  }
})

const approvalProgress = computed(() => {
  const actionable = batchStats.value.total - batchStats.value.excluded
  if (actionable <= 0) return batchStats.value.total > 0 ? 100 : 0
  return Math.round((batchStats.value.approved / actionable) * 100)
})

const statusOptions = computed(() => {
  const groups = batch.value?.tenant_groups ?? []
  const count = (status) => (status === 'all' ? groups.length : groups.filter((g) => g.tenant_status === status).length)

  return [
    { value: 'all', label: 'All', count: count('all') },
    { value: 'draft', label: 'Draft', count: count('draft') },
    { value: 'pending', label: 'Pending', count: count('pending') },
    { value: 'partial', label: 'Partial', count: count('partial') },
    { value: 'approved', label: 'Approved', count: count('approved') },
    { value: 'excluded', label: 'Excluded', count: count('excluded') },
  ]
})

const filteredGroups = computed(() => {
  let groups = batch.value?.tenant_groups ?? []

  if (statusFilter.value !== 'all') {
    groups = groups.filter((g) => g.tenant_status === statusFilter.value)
  }

  const query = search.value

  if (String(query ?? '').trim()) {
    groups = groups.filter((g) =>
      valuesMatchSearch([g.tenant_name, g.unit_label], query),
    )
  }

  return groups
})

const lastPage = computed(() => Math.max(1, Math.ceil(filteredGroups.value.length / perPage.value)))

const paginatedGroups = computed(() => {
  const start = (page.value - 1) * perPage.value
  return filteredGroups.value.slice(start, start + perPage.value)
})

const paginationFrom = computed(() => {
  if (filteredGroups.value.length === 0) return 0
  return (page.value - 1) * perPage.value + 1
})

const paginationTo = computed(() => {
  if (filteredGroups.value.length === 0) return 0
  return Math.min(page.value * perPage.value, filteredGroups.value.length)
})

const selectedBuildingName = computed(() => {
  const building = buildings.value.find((b) => String(b.id) === String(filters.building_id))
  return building?.name ?? 'this building'
})

const periodLabel = computed(() => {
  const month = months.find((m) => m.value === Number(filters.billing_month))
  return `${month?.label ?? filters.billing_month} ${filters.billing_year}`
})

watch([search, statusFilter, perPage], () => {
  page.value = 1
})

watch(lastPage, (value) => {
  if (page.value > value) {
    page.value = value
  }
})

function onPerPageChange(value) {
  perPage.value = value
  page.value = 1
}

function onFiltersChange() {
  search.value = ''
  statusFilter.value = 'all'
  page.value = 1
  load()
}

function itemForType(group, type) {
  return group.items?.find((item) => item.charge_type === type) || null
}

function groupExclusionReason(group) {
  const excluded = group.items?.find((item) => item.exclusion_reason)
  return excluded?.exclusion_reason || ''
}

function formatDateTime(value) {
  if (!value) return '—'
  return new Date(value).toLocaleString('en-KE', { dateStyle: 'medium', timeStyle: 'short' })
}

function chargeTypeLabel(type) {
  return ({ rent: 'Rent', service: 'Service', water: 'Water', electricity: 'Electricity' })[type] || type
}

function statusLabel(status, isComplete) {
  if (isComplete) return 'Complete'
  return ({ draft: 'Draft', partially_approved: 'In progress', locked: 'In progress' })[status] || status
}

function statusBadgeClass(status, isComplete) {
  if (isComplete) return 'badge-success'
  if (status === 'partially_approved' || status === 'locked') return 'badge-warning'
  return 'badge-info'
}

function tenantStatusLabel(status) {
  return ({
    draft: 'Draft',
    pending: 'Pending readings',
    partial: 'Partially approved',
    approved: 'Approved',
    excluded: 'Excluded',
  })[status] || status
}

function tenantStatusClass(status) {
  if (status === 'approved') return 'badge-success'
  if (status === 'excluded') return 'badge-neutral'
  if (status === 'pending') return 'badge-warning'
  if (status === 'partial') return 'badge-accent'
  return 'badge-info'
}

async function load() {
  error.value = ''
  batch.value = null
  if (!filters.building_id) return

  loading.value = true
  try {
    const response = await fetchChargeBatch({
      building_id: filters.building_id,
      billing_month: filters.billing_month,
      billing_year: filters.billing_year,
    })
    batch.value = response.data
    page.value = 1
  } catch (e) {
    const message = e.response?.data?.message || 'Could not load charge batch.'
    error.value = message
    toast.error(message)
  } finally {
    loading.value = false
  }
}

async function generate() {
  error.value = ''
  actionLoading.value = true
  try {
    const response = await generateChargeBatch({
      building_id: filters.building_id,
      billing_month: filters.billing_month,
      billing_year: filters.billing_year,
    })
    batch.value = response.data
    page.value = 1
    toast.success('Draft charge batch generated.')
  } catch (e) {
    const message = e.response?.data?.message || e.response?.data?.errors?.billing_month?.[0] || 'Could not generate batch.'
    error.value = message
    toast.error(message)
  } finally {
    actionLoading.value = false
  }
}

async function refreshPending() {
  if (!batch.value) return
  actionLoading.value = true
  try {
    const response = await refreshChargeBatchPending(batch.value.id)
    batch.value = response.data
    toast.success('Amounts and readings refreshed.')
  } catch (e) {
    const message = e.response?.data?.message || 'Could not refresh pending items.'
    error.value = message
    toast.error(message)
  } finally {
    actionLoading.value = false
  }
}

function openEditItem(item) {
  editingItem.value = item
  editForm.amount = item.amount ?? ''
  editForm.adjustment_note = ''
  editDialogOpen.value = true
}

async function saveItemEdit() {
  if (!batch.value || !editingItem.value) return
  actionLoading.value = true
  try {
    const response = await updateChargeBatchItem(batch.value.id, editingItem.value.id, {
      amount: editForm.amount,
      adjustment_note: editForm.adjustment_note || null,
    })
    batch.value = response.data
    editDialogOpen.value = false
    toast.success('Line item updated.')
  } catch (e) {
    const message = e.response?.data?.message || 'Could not update line item.'
    error.value = message
    toast.error(message)
  } finally {
    actionLoading.value = false
  }
}

async function approveTenant(tenantId) {
  if (!batch.value) return
  actionLoading.value = true
  try {
    const response = await approveChargeBatchTenant(batch.value.id, tenantId)
    batch.value = response.data
    toast.success('Tenant approved.')
  } catch (e) {
    const message = e.response?.data?.message || 'Could not approve tenant.'
    error.value = message
    toast.error(message)
  } finally {
    actionLoading.value = false
  }
}

async function reopenTenant(tenantId) {
  if (!batch.value) return
  const ok = await askConfirm({
    title: 'Reopen tenant',
    message: 'Reopen this tenant for editing? Posted charges stay on the ledger until you change amounts or approve again.',
    confirmLabel: 'Reopen',
  })
  if (!ok) return
  actionLoading.value = true
  try {
    const response = await reopenChargeBatchTenant(batch.value.id, tenantId)
    batch.value = response.data
    toast.success('Tenant reopened for editing.')
  } catch (e) {
    const message = e.response?.data?.message || 'Could not reopen tenant.'
    error.value = message
    toast.error(message)
  } finally {
    actionLoading.value = false
  }
}

async function excludeTenant(group) {
  const reason = await askConfirm({
    title: 'Exclude tenant',
    message: `Exclude ${group.tenant_name} from this batch? They will not be charged for this period.`,
    confirmLabel: 'Exclude',
    variant: 'danger',
    prompt: true,
    promptLabel: 'Exclusion reason',
    promptRequired: true,
  })
  if (!reason) return
  actionLoading.value = true
  try {
    const response = await excludeChargeBatchTenant(batch.value.id, group.tenant_id, { reason })
    batch.value = response.data
    toast.success('Tenant excluded from batch.')
  } catch (e) {
    const message = e.response?.data?.message || 'Could not exclude tenant.'
    error.value = message
    toast.error(message)
  } finally {
    actionLoading.value = false
  }
}

function confirmApproveAll() {
  approveAllDialogOpen.value = true
}

async function approveAll() {
  if (!batch.value) return
  actionLoading.value = true
  try {
    const response = await approveAllChargeBatch(batch.value.id)
    batch.value = response.data
    approveAllDialogOpen.value = false
    toast.success('Ready tenants approved.')
  } catch (e) {
    const message = e.response?.data?.message || 'Could not approve batch.'
    error.value = message
    toast.error(message)
  } finally {
    actionLoading.value = false
  }
}

onMounted(async () => {
  const response = await fetchBuildings()
  buildings.value = response.data

  if (route.query.building_id) {
    filters.building_id = String(route.query.building_id)
  }
  if (route.query.billing_month) {
    filters.billing_month = Number(route.query.billing_month)
  }
  if (route.query.billing_year) {
    filters.billing_year = Number(route.query.billing_year)
  }

  if (filters.building_id) {
    await load()
  }
})
</script>
