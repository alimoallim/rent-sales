<template>
  <section>
    <PageHeader
      title="Charge batches"
      subtitle="Generate, review, and approve monthly charges before they post to tenant balances."
    />

    <div class="filter-bar">
      <BuildingSearchSelect
        v-model="filters.building_id"
        :buildings="buildings"
        placeholder="Select building"
        search-placeholder="Search buildings…"
        @change="load"
      />
      <select v-model="filters.billing_month" class="input-field" @change="load">
        <option v-for="month in months" :key="month.value" :value="month.value">{{ month.label }}</option>
      </select>
      <input v-model="filters.billing_year" type="number" min="2000" class="input-field w-full sm:w-28" @change="load" />
    </div>

    <p v-if="error" class="alert-error mb-3">{{ error }}</p>

    <div v-if="!filters.building_id" class="content-panel p-8 text-center">
      <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Select a building and billing period</p>
      <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Choose the property and month above to view or create a charge batch.</p>
    </div>

    <div v-else-if="loading" class="content-panel p-8 text-center text-sm text-zinc-500 dark:text-zinc-400">
      Loading charge batch…
    </div>

    <div v-else-if="!batch" class="content-panel p-8 text-center">
      <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">No charge batch for this period</p>
      <p class="mx-auto mt-2 max-w-md text-sm text-zinc-600 dark:text-zinc-400">
        Generate a draft batch for <strong>{{ selectedBuildingName }}</strong> ·
        <strong>{{ periodLabel }}</strong>. You can review rent, service, water, and electricity lines per tenant,
        then approve to post charges to tenant balances.
      </p>
      <button type="button" class="btn-primary mt-5" :disabled="actionLoading" @click="generate">
        {{ actionLoading ? 'Generating…' : 'Generate draft batch' }}
      </button>
    </div>

    <div v-else-if="batch" class="space-y-4">
      <div class="content-panel px-4 py-4 sm:px-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Monthly charge batch</p>
            <h2 class="mt-1 text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ batch.building_name }} · {{ batch.period_label }}</h2>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
              Generated {{ formatDateTime(batch.generated_at) }} by {{ batch.generated_by_name }}
            </p>
          </div>
          <div class="text-sm">
            <span class="badge" :class="statusBadgeClass(batch.status, batch.is_complete)">{{ statusLabel(batch.status, batch.is_complete) }}</span>
            <p v-if="batch.is_complete" class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
              All tenants approved or excluded. You can still edit amounts or reopen a tenant to adjust.
            </p>
          </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-2 border-t border-zinc-200 dark:border-zinc-700 pt-4">
          <button type="button" class="btn-primary" :disabled="actionLoading" @click="confirmApproveAll">
            Approve all ready tenants
          </button>
          <button type="button" class="btn-secondary" :disabled="actionLoading" @click="refreshPending">
            Refresh amounts & readings
          </button>
        </div>
      </div>

      <div class="content-panel overflow-hidden">
        <div class="flex items-center justify-between border-b border-zinc-200 bg-zinc-50 dark:bg-zinc-900/50 px-4 py-3 sm:px-5">
          <p class="text-sm text-zinc-600 dark:text-zinc-400">
            <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ batch.tenant_groups?.length || 0 }}</span> tenants
            <span v-if="batchGrandTotal" class="ml-3">
              · Batch total <span class="font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ formatMoney(batchGrandTotal, 'rental') }}</span>
            </span>
          </p>
        </div>

        <div class="max-h-[70vh] overflow-auto">
          <table class="min-w-[56rem] w-full text-sm">
            <thead class="sticky top-0 z-10 bg-white dark:bg-zinc-900 shadow-[0_1px_0_0_rgb(228_228_231)]">
              <tr class="text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                <th class="min-w-[11rem] px-4 py-2.5 sm:px-5">Tenant / apartment</th>
                <th class="w-28 px-4 py-2.5 text-right sm:px-5">Rent amount</th>
                <th class="w-28 px-4 py-2.5 text-right sm:px-5">Service charge</th>
                <th class="w-28 px-4 py-2.5 text-right sm:px-5">Water</th>
                <th class="w-28 px-4 py-2.5 text-right sm:px-5">Electricity</th>
                <th class="w-32 px-4 py-2.5 text-right sm:px-5">Subtotal</th>
                <th class="w-36 px-4 py-2.5 text-right sm:px-5">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(group, index) in batch.tenant_groups"
                :key="group.tenant_id"
                class="border-b border-zinc-100 dark:border-zinc-800 hover:bg-indigo-50/40"
                :class="[
                  index % 2 === 1 ? 'bg-zinc-50/40' : 'bg-white dark:bg-zinc-900',
                  group.tenant_status === 'excluded' ? 'opacity-60' : '',
                ]"
              >
                <td class="px-4 py-2 sm:px-5">
                  <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                    <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ group.tenant_name }}</span>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400">· {{ group.unit_label || 'No unit' }}</span>
                    <span class="badge !py-0 text-[10px]" :class="tenantStatusClass(group.tenant_status)">
                      {{ tenantStatusLabel(group.tenant_status) }}
                    </span>
                  </div>
                  <p v-if="groupExclusionReason(group)" class="mt-0.5 truncate text-xs text-zinc-500 dark:text-zinc-400" :title="groupExclusionReason(group)">
                    {{ groupExclusionReason(group) }}
                  </p>
                </td>
                <td class="px-4 py-2 text-right sm:px-5">
                  <ChargeBatchAmountCell
                    :item="itemForType(group, 'rent')"
                    :editable="true"
                    @edit="openEditItem"
                  />
                </td>
                <td class="px-4 py-2 text-right sm:px-5">
                  <ChargeBatchAmountCell
                    :item="itemForType(group, 'service')"
                    :editable="true"
                    @edit="openEditItem"
                  />
                </td>
                <td class="px-4 py-2 text-right sm:px-5">
                  <ChargeBatchAmountCell
                    :item="itemForType(group, 'water')"
                    :editable="true"
                    @edit="openEditItem"
                  />
                </td>
                <td class="px-4 py-2 text-right sm:px-5">
                  <ChargeBatchAmountCell
                    :item="itemForType(group, 'electricity')"
                    :editable="true"
                    @edit="openEditItem"
                  />
                </td>
                <td class="px-4 py-2 text-right font-semibold tabular-nums text-zinc-900 dark:text-zinc-100 sm:px-5">
                  {{ formatMoney(group.subtotal, 'rental') }}
                </td>
                <td class="px-4 py-2 text-right sm:px-5">
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
                      class="btn-ghost !min-h-8 !px-2 !py-1 text-xs text-amber-800"
                      :disabled="actionLoading"
                      @click="excludeTenant(group)"
                    >
                      Exclude
                    </button>
                  </div>
                  <span v-else class="text-xs text-zinc-400">—</span>
                </td>
              </tr>
            </tbody>
            <tfoot class="sticky bottom-0 z-10 bg-zinc-50 dark:bg-zinc-900/50 shadow-[0_-1px_0_0_rgb(228_228_231)]">
              <tr class="font-semibold">
                <td class="px-4 py-2.5 sm:px-5" colspan="5">Batch total ({{ batch.tenant_groups?.length || 0 }} tenants)</td>
                <td class="px-4 py-2.5 text-right tabular-nums text-zinc-900 dark:text-zinc-100 sm:px-5">
                  {{ formatMoney(batchGrandTotal, 'rental') }}
                </td>
                <td class="sm:px-5" />
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>

    <AppDialog v-model:open="editDialogOpen" title="Adjust line item" size="md">
      <form class="space-y-4" @submit.prevent="saveItemEdit">
        <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ chargeTypeLabel(editingItem?.charge_type) }} for selected tenant.</p>
        <label class="block text-sm">
          <span class="mb-1 block font-medium text-zinc-700 dark:text-zinc-300">{{ amountLabel('rental') }}</span>
          <input v-model="editForm.amount" type="number" min="0" step="0.01" class="input-field" required />
        </label>
        <label class="block text-sm">
          <span class="mb-1 block font-medium text-zinc-700 dark:text-zinc-300">Reason for adjustment</span>
          <textarea v-model="editForm.adjustment_note" rows="2" class="input-field" placeholder="Optional note for audit trail" />
        </label>
        <div class="flex justify-end gap-2">
          <button type="button" class="btn-secondary" @click="editDialogOpen = false">Cancel</button>
          <button type="submit" class="btn-primary" :disabled="actionLoading">Save</button>
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
import { computed, onMounted, reactive, ref } from 'vue'
import { formatMoney, amountLabel, moneyLabel, currencyCode } from '../../utils/money'
import { useRoute } from 'vue-router'
import PageHeader from '../../components/PageHeader.vue'
import AppDialog from '../../components/ui/AppDialog.vue'
import BuildingSearchSelect from '../../components/ui/BuildingSearchSelect.vue'
import ChargeBatchAmountCell from '../../components/rental/ChargeBatchAmountCell.vue'
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

const route = useRoute()
const buildings = ref([])
const batch = ref(null)
const loading = ref(false)
const actionLoading = ref(false)
const error = ref('')
const editDialogOpen = ref(false)
const approveAllDialogOpen = ref(false)
const editingItem = ref(null)
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

const selectedBuildingName = computed(() => {
  const building = buildings.value.find((b) => String(b.id) === String(filters.building_id))
  return building?.name ?? 'this building'
})

const periodLabel = computed(() => {
  const month = months.find((m) => m.value === Number(filters.billing_month))
  return `${month?.label ?? filters.billing_month} ${filters.billing_year}`
})

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
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not load charge batch.'
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
  } catch (e) {
    error.value = e.response?.data?.message || e.response?.data?.errors?.billing_month?.[0] || 'Could not generate batch.'
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
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not refresh pending items.'
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
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not update line item.'
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
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not approve tenant.'
  } finally {
    actionLoading.value = false
  }
}

async function reopenTenant(tenantId) {
  if (!batch.value) return
  if (!window.confirm('Reopen this tenant for editing? Posted charges stay on the ledger until you change amounts or approve again.')) {
    return
  }
  actionLoading.value = true
  try {
    const response = await reopenChargeBatchTenant(batch.value.id, tenantId)
    batch.value = response.data
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not reopen tenant.'
  } finally {
    actionLoading.value = false
  }
}

async function excludeTenant(group) {
  const reason = window.prompt(`Reason for excluding ${group.tenant_name} from this batch:`)
  if (!reason?.trim()) return
  actionLoading.value = true
  try {
    const response = await excludeChargeBatchTenant(batch.value.id, group.tenant_id, { reason: reason.trim() })
    batch.value = response.data
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not exclude tenant.'
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
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not approve batch.'
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
