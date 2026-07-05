<template>
  <section>
    <PageHeader
      :title="status === 'active' ? 'Tenants' : 'Moved-out tenants'"
      :subtitle="status === 'active' ? 'Register tenants and set rental agreement terms, including metering requirements.' : 'Tenants who have vacated their units.'"
      :breadcrumbs="[{ label: 'Rental', to: '/rental' }, { label: status === 'active' ? 'Tenants' : 'Moved out' }]"
    >
      <template v-if="status === 'active'" #actions>
        <button type="button" class="btn-primary w-full sm:w-auto" @click="openCreate">Register tenant</button>
      </template>
      <template v-if="summary && status === 'active'" #kpis>
        <KpiCard
          label="Active tenants"
          :value="String(summary.total)"
          hint="Matching current filters"
          accent="neutral"
        />
        <KpiCard
          label="With balance"
          :value="String(summary.with_balance)"
          :hint="summary.with_balance === 1 ? 'Tenant owes rent or charges' : 'Tenants with outstanding balance'"
          accent="warning"
        />
        <KpiCard
          label="Outstanding"
          :value="formatMoney(summary.total_outstanding, 'rental')"
          hint="Total balance across filtered tenants"
          accent="danger"
        />
        <KpiCard
          label="Metered"
          :value="String(summary.metered)"
          hint="Water or electricity reading required"
          accent="info"
        />
      </template>
      <template v-else-if="summary" #kpis>
        <KpiCard
          label="Moved-out records"
          :value="String(summary.total)"
          hint="Matching current filters"
          accent="neutral"
        />
      </template>
    </PageHeader>

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
          :class="{ 'segmented-option-active': status === 'active' }"
          @click="setStatus('active')"
        >
          Active
        </button>
        <button
          type="button"
          class="segmented-option"
          :class="{ 'segmented-option-active': status === 'inactive' }"
          @click="setStatus('inactive')"
        >
          Moved out
        </button>
      </div>
      <label v-if="status === 'active'" class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400">
        <input v-model="filters.with_balance" type="checkbox" class="rounded border-zinc-300" @change="loadTable" />
        With balance only
      </label>
    </FilterBar>

    <DataTable
      v-model:search="search"
      server-side
      :items="items"
      :columns="tenantColumns"
      :loading="loading"
      :pagination="pagination"
      money-module="rental"
      :empty-message="status === 'active' ? 'No tenants found for this building yet.' : 'No moved-out tenants found.'"
      :row-class="tenantRowClass"
      @search="onSearchChange"
      @page-change="goToPage"
      @per-page-change="setPerPage"
    >
      <template #card-title-name="{ item }">
        <TenantNameMenu
          :tenant-id="item.id"
          :tenant-name="item.name"
          :building-id="item.rental_building_id"
        />
        <p v-if="item.phone" class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
          <a :href="`tel:${item.phone}`" class="hover:text-zinc-700 dark:hover:text-zinc-300">{{ item.phone }}</a>
        </p>
      </template>
      <template #cell-name="{ item }">
        <div>
          <TenantNameMenu
            :tenant-id="item.id"
            :tenant-name="item.name"
            :building-id="item.rental_building_id"
          />
          <p v-if="item.passport_or_id" class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">ID {{ item.passport_or_id }}</p>
        </div>
      </template>
      <template #cell-phone="{ item }">
        <a v-if="item.phone" :href="`tel:${item.phone}`" class="text-sm text-zinc-700 hover:text-indigo-600 dark:text-zinc-300">{{ item.phone }}</a>
        <span v-else class="text-zinc-400">—</span>
      </template>
      <template #cell-unit_label="{ item }">
        <div>
          <p class="font-medium text-zinc-900 dark:text-zinc-100">Unit {{ item.unit_label || '—' }}</p>
          <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ item.building_name || '—' }}</p>
        </div>
      </template>
      <template #cell-start_date="{ item }">
        <DateCell :value="item.start_date" />
      </template>
      <template #cell-agreement="{ item }">
        <div class="flex flex-wrap justify-end gap-1 lg:justify-start">
          <span v-if="item.requires_water_metering" class="badge badge-info">Water</span>
          <span v-if="item.requires_electricity_metering" class="badge badge-accent">Electricity</span>
          <span
            v-if="!item.requires_water_metering && !item.requires_electricity_metering"
            class="text-xs text-zinc-500 dark:text-zinc-400"
          >
            No metering
          </span>
        </div>
      </template>
      <template #cell-service_amount="{ item }">
        <MoneyCell :amount="item.service_amount" module="rental" />
      </template>
      <template #cell-balance="{ item }">
        <div class="flex flex-col items-end gap-1 lg:items-start">
          <MoneyCell :amount="item.balance" module="rental" />
          <StatusBadge :variant="balanceVariant(item.balance)" :label="balanceLabel(item.balance)" />
        </div>
      </template>
      <template #cell-deposit="{ item }">
        <MoneyCell :amount="item.deposit" module="rental" />
      </template>
      <template v-if="status === 'active'" #actions="{ item }">
        <RouterLink
          :to="{ path: '/rental/payments', query: { tenant_id: item.id, building_id: item.rental_building_id, action: 'new' } }"
          class="btn-secondary w-full sm:w-auto"
        >
          Pay
        </RouterLink>
        <button type="button" class="btn-secondary w-full sm:w-auto" @click="openEdit(item)">Edit</button>
        <button type="button" class="btn-destructive w-full sm:w-auto" @click="openMoveOut(item)">Move out</button>
      </template>
    </DataTable>

    <AppDialog
      v-model:open="showTenantForm"
      :title="editing ? 'Edit tenant' : 'Register tenant'"
      size="2xl"
      :close-on-backdrop="false"
    >
      <form id="tenant-form" class="grid gap-4 lg:grid-cols-2 lg:items-start lg:gap-x-8" @submit.prevent="saveTenant">
        <div class="space-y-3">
          <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Unit &amp; contact</p>

          <div class="grid gap-3 sm:grid-cols-2">
            <FormField label="Building" class="sm:col-span-2" required>
              <BuildingSearchSelect
                v-model="form.rental_building_id"
                :buildings="buildings"
                required
                @change="onBuildingChange"
              />
            </FormField>

            <FormField label="Vacant unit" class="sm:col-span-2" required>
              <UnitSearchSelect
                v-model="form.rental_unit_id"
                :units="vacantUnits"
                required
              />
            </FormField>

            <FormField label="Full name" required>
              <input v-model="form.name" class="input-field" required />
            </FormField>

            <FormField label="Phone" required>
              <input v-model="form.phone" type="tel" class="input-field" required />
            </FormField>

            <FormField label="Email" class="sm:col-span-2">
              <input v-model="form.email" type="email" class="input-field" autocomplete="off" />
            </FormField>

            <FormField label="ID / passport" class="sm:col-span-2">
              <input v-model="form.passport_or_id" class="input-field" />
            </FormField>
          </div>
        </div>

        <div class="space-y-3">
          <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Rental agreement</p>
          <p class="text-xs text-zinc-500 dark:text-zinc-400">Financial terms and metering requirements from the tenant's contract.</p>

          <div class="grid gap-3 sm:grid-cols-2">
            <FormField label="Agreement start date" class="sm:col-span-2">
              <input v-model="form.start_date" type="date" class="input-field" />
            </FormField>

            <FormField :label="moneyLabel('Deposit', 'rental')">
              <input v-model="form.deposit" type="number" min="0" step="0.01" class="input-field" />
            </FormField>

            <FormField :label="moneyLabel('Monthly service charge', 'rental')">
              <input v-model="form.service_amount" type="number" min="0" step="0.01" class="input-field" />
            </FormField>
          </div>

          <div class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-md border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-900/50">
              <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Water meter reading</p>
              <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">Required monthly under this agreement?</p>
              <div class="segmented-control mt-2 w-full">
                <button
                  type="button"
                  class="toggle-option flex-1"
                  :class="form.requires_water_metering ? 'toggle-option-active' : ''"
                  @click="form.requires_water_metering = true"
                >
                  Yes
                </button>
                <button
                  type="button"
                  class="toggle-option flex-1"
                  :class="!form.requires_water_metering ? 'toggle-option-active' : ''"
                  @click="form.requires_water_metering = false"
                >
                  No
                </button>
              </div>
            </div>

            <div class="rounded-md border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-900/50">
              <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Electricity meter reading</p>
              <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">Required monthly under this agreement?</p>
              <div class="segmented-control mt-2 w-full">
                <button
                  type="button"
                  class="toggle-option flex-1"
                  :class="form.requires_electricity_metering ? 'toggle-option-active' : ''"
                  @click="form.requires_electricity_metering = true"
                >
                  Yes
                </button>
                <button
                  type="button"
                  class="toggle-option flex-1"
                  :class="!form.requires_electricity_metering ? 'toggle-option-active' : ''"
                  @click="form.requires_electricity_metering = false"
                >
                  No
                </button>
              </div>
            </div>
          </div>
        </div>

        <div class="space-y-3 lg:col-span-2">
          <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Next of kin</p>
          <div class="grid gap-3 sm:grid-cols-2">
            <FormField label="Name">
              <input v-model="form.next_of_kin_name" class="input-field" />
            </FormField>
            <FormField label="Phone">
              <input v-model="form.next_of_kin_phone" type="tel" class="input-field" />
            </FormField>
            <FormField label="ID number">
              <input v-model="form.next_of_kin_id" class="input-field" />
            </FormField>
            <FormField label="Address" class="sm:col-span-2">
              <input v-model="form.next_of_kin_address" class="input-field" />
            </FormField>
          </div>
        </div>

        <p v-if="error" class="alert-error lg:col-span-2">{{ error }}</p>
      </form>

      <template #footer>
        <button type="button" class="btn-secondary w-full sm:w-auto" @click="closeTenantForm">Cancel</button>
        <button type="submit" form="tenant-form" class="btn-primary w-full sm:w-auto" :disabled="saving">
          {{ saving ? 'Saving…' : 'Save tenant' }}
        </button>
      </template>
    </AppDialog>

    <AppDialog
      v-model:open="showMoveOutForm"
      :title="`Move out: ${moveOutTarget?.name || ''}`"
      size="sm"
      :close-on-backdrop="false"
    >
      <div class="grid gap-4">
        <FormField label="Move-out date" required>
          <input v-model="moveOutForm.moved_out_at" type="date" class="input-field" required />
        </FormField>
        <FormField :label="moneyLabel('Refund amount', 'rental')">
          <input v-model="moveOutForm.refund_amount" type="number" min="0" step="0.01" class="input-field" />
        </FormField>
        <FormField label="Reason" required>
          <textarea v-model="moveOutForm.reason" rows="3" class="input-field" required />
        </FormField>
      </div>
      <p v-if="error" class="mt-3 alert-error">{{ error }}</p>

      <template #footer>
        <button type="button" class="btn-secondary w-full sm:w-auto" @click="closeMoveOutForm">Cancel</button>
        <button type="button" class="btn-destructive w-full sm:w-auto" :disabled="saving" @click="submitMoveOut">
          {{ saving ? 'Processing…' : 'Confirm move out' }}
        </button>
      </template>
    </AppDialog>
  </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import PageHeader from '../../components/PageHeader.vue'
import AppDialog from '../../components/ui/AppDialog.vue'
import BuildingSearchSelect from '../../components/ui/BuildingSearchSelect.vue'
import UnitSearchSelect from '../../components/ui/UnitSearchSelect.vue'
import FilterBar from '../../components/ui/FilterBar.vue'
import FormField from '../../components/ui/FormField.vue'
import KpiCard from '../../components/ui/KpiCard.vue'
import StatusBadge from '../../components/ui/StatusBadge.vue'
import DataTable from '../../components/data/DataTable.vue'
import DateCell from '../../components/data/DateCell.vue'
import MoneyCell from '../../components/data/MoneyCell.vue'
import TenantNameMenu from '../../components/rental/TenantNameMenu.vue'
import { useToast } from '../../composables/useToast'
import { usePaginatedList } from '../../composables/usePaginatedList'
import {
  createTenant,
  fetchBuildings,
  fetchTenants,
  fetchUnits,
  moveOutTenant,
  updateTenant,
} from '../../api/rental'
import { formatMoney, moneyLabel } from '../../utils/money'

const toast = useToast()

const buildings = ref([])
const vacantUnits = ref([])
const summary = ref(null)
const saving = ref(false)
const status = ref('active')
const showTenantForm = ref(false)
const showMoveOutForm = ref(false)
const editing = ref(null)
const moveOutTarget = ref(null)
const error = ref('')
const filters = reactive({ building_id: '', with_balance: false })
const form = reactive({
  rental_building_id: '',
  rental_unit_id: '',
  name: '',
  phone: '',
  email: '',
  deposit: 0,
  service_amount: 0,
  start_date: '',
  passport_or_id: '',
  requires_water_metering: false,
  requires_electricity_metering: false,
  next_of_kin_name: '',
  next_of_kin_address: '',
  next_of_kin_id: '',
  next_of_kin_phone: '',
})
const moveOutForm = reactive({
  moved_out_at: new Date().toISOString().slice(0, 10),
  refund_amount: 0,
  reason: '',
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
  const response = await fetchTenants({
    ...params,
    building_id: filters.building_id || undefined,
    status: status.value,
    with_balance: filters.with_balance ? 1 : undefined,
  })
  summary.value = response.summary ?? null
  return response
})

const tenantColumns = computed(() => {
  const base = [
    { key: 'name', label: 'Tenant', cardTitle: true },
    { key: 'phone', label: 'Phone', mobileCard: true, searchable: false },
    { key: 'unit_label', label: 'Unit / building', mobileCard: true },
    { key: 'start_date', label: 'Since', tabletCard: true },
    { key: 'service_amount', label: 'Service charge', align: 'right', tabletCard: true },
    { key: 'deposit', label: 'Deposit', money: true, tabletCard: true },
  ]

  if (status.value === 'active') {
    return [
      ...base,
      { key: 'agreement', label: 'Metering', mobileCard: true },
      { key: 'balance', label: 'Balance', align: 'right', mobileCard: true },
    ]
  }

  return base
})

function balanceVariant(balance) {
  const amount = Number(balance ?? 0)
  if (amount <= 0) return 'success'
  return 'warning'
}

function balanceLabel(balance) {
  const amount = Number(balance ?? 0)
  if (amount <= 0) return 'Paid up'
  return 'Outstanding'
}

function tenantRowClass(item) {
  if (status.value !== 'active') return ''
  if (Number(item.balance ?? 0) <= 0) return ''
  return 'border-amber-200 bg-amber-50/70 hover:bg-amber-100/80 dark:border-amber-900/60 dark:bg-amber-950/30 dark:hover:bg-amber-900/40'
}

async function loadBuildings() {
  const response = await fetchBuildings({ per_page: 200 })
  buildings.value = response.data
}

async function loadVacantUnits(buildingId, includeUnitId = null) {
  if (!buildingId) {
    vacantUnits.value = []
    return
  }
  const params = { building_id: buildingId, status: 'vacant' }
  const response = await fetchUnits(params)
  vacantUnits.value = response.data
  if (includeUnitId && !vacantUnits.value.find((unit) => unit.id === includeUnitId)) {
    const allUnits = await fetchUnits({ building_id: buildingId, per_page: 200 })
    const current = allUnits.data.find((unit) => unit.id === includeUnitId)
    if (current) vacantUnits.value = [current, ...vacantUnits.value]
  }
}

async function loadTable() {
  await reload()
}

function setStatus(next) {
  status.value = next
  filters.with_balance = false
  reload()
}

function onBuildingChange() {
  form.rental_unit_id = ''
  loadVacantUnits(form.rental_building_id, editing.value?.rental_unit_id)
}

function openCreate() {
  editing.value = null
  Object.assign(form, {
    rental_building_id: filters.building_id || '',
    rental_unit_id: '',
    name: '',
    phone: '',
    email: '',
    deposit: 0,
    service_amount: 0,
    start_date: new Date().toISOString().slice(0, 10),
    passport_or_id: '',
    requires_water_metering: false,
    requires_electricity_metering: false,
    next_of_kin_name: '',
    next_of_kin_address: '',
    next_of_kin_id: '',
    next_of_kin_phone: '',
  })
  error.value = ''
  showTenantForm.value = true
  loadVacantUnits(form.rental_building_id)
}

async function openEdit(tenant) {
  editing.value = tenant
  Object.assign(form, {
    rental_building_id: tenant.rental_building_id,
    rental_unit_id: tenant.rental_unit_id,
    name: tenant.name,
    phone: tenant.phone,
    email: tenant.email || '',
    deposit: tenant.deposit,
    service_amount: tenant.service_amount,
    start_date: tenant.start_date || '',
    passport_or_id: tenant.passport_or_id || '',
    requires_water_metering: Boolean(tenant.requires_water_metering),
    requires_electricity_metering: Boolean(tenant.requires_electricity_metering),
    next_of_kin_name: tenant.next_of_kin_name || '',
    next_of_kin_address: tenant.next_of_kin_address || '',
    next_of_kin_id: tenant.next_of_kin_id || '',
    next_of_kin_phone: tenant.next_of_kin_phone || '',
  })
  error.value = ''
  showTenantForm.value = true
  await loadVacantUnits(tenant.rental_building_id, tenant.rental_unit_id)
}

function closeTenantForm() {
  showTenantForm.value = false
}

async function saveTenant() {
  error.value = ''
  saving.value = true
  try {
    const payload = { ...form }
    if (editing.value) {
      await updateTenant(editing.value.id, payload)
      toast.success('Tenant updated.')
    } else {
      await createTenant(payload)
      toast.success('Tenant registered.')
    }
    closeTenantForm()
    await reload()
  } catch (e) {
    const validation = e.response?.data?.errors
    error.value = validation
      ? Object.values(validation).flat().join(' ')
      : e.response?.data?.message || 'Could not save tenant.'
  } finally {
    saving.value = false
  }
}

function openMoveOut(tenant) {
  moveOutTarget.value = tenant
  Object.assign(moveOutForm, {
    moved_out_at: new Date().toISOString().slice(0, 10),
    refund_amount: tenant.deposit || 0,
    reason: '',
  })
  error.value = ''
  showMoveOutForm.value = true
}

function closeMoveOutForm() {
  showMoveOutForm.value = false
}

async function submitMoveOut() {
  error.value = ''
  saving.value = true
  try {
    await moveOutTenant(moveOutTarget.value.id, moveOutForm)
    toast.success(`${moveOutTarget.value.name} moved out.`)
    closeMoveOutForm()
    await reload()
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not record move-out.'
  } finally {
    saving.value = false
  }
}

watch(() => form.rental_building_id, (buildingId) => {
  if (showTenantForm.value) {
    loadVacantUnits(buildingId, editing.value?.rental_unit_id)
  }
})

onMounted(async () => {
  await loadBuildings()
  await load()
})
</script>
