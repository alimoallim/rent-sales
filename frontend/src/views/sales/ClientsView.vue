<template>
  <section>
    <PageHeader
      :title="status === 'active' ? 'Clients' : 'Disabled clients'"
      :subtitle="status === 'active' ? 'Register buyers and track sale balances.' : 'Cancelled or disabled client records.'"
      :breadcrumbs="[{ label: 'Sales', to: '/sales' }, { label: status === 'active' ? 'Clients' : 'Disabled' }]"
    >
      <template v-if="status === 'active'" #actions>
        <button type="button" class="btn-primary w-full sm:w-auto" @click="openCreate">Register client</button>
      </template>
      <template v-if="summary && status === 'active'" #kpis>
        <KpiCard
          label="Active clients"
          :value="String(summary.total)"
          hint="Matching current filters"
          accent="neutral"
        />
        <KpiCard
          label="With balance"
          :value="String(summary.with_balance)"
          :hint="summary.with_balance === 1 ? 'Client still owes on sale' : 'Clients with outstanding balance'"
          accent="warning"
        />
        <KpiCard
          label="Outstanding"
          :value="formatMoney(summary.total_outstanding, 'sales')"
          hint="Total balance across filtered clients"
          accent="danger"
        />
        <KpiCard
          label="Collected"
          :value="formatMoney(summary.total_collected, 'sales')"
          :hint="`Of ${formatMoney(summary.total_agreed, 'sales')} agreed value`"
          accent="success"
        />
      </template>
      <template v-else-if="summary" #kpis>
        <KpiCard
          label="Disabled clients"
          :value="String(summary.total)"
          hint="Matching current filters"
          accent="neutral"
        />
        <KpiCard
          label="Agreed value"
          :value="formatMoney(summary.total_agreed, 'sales')"
          hint="Historical sale prices"
          accent="info"
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
          :class="{ 'segmented-option-active': status === 'disabled' }"
          @click="setStatus('disabled')"
        >
          Disabled
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
      :columns="clientColumns"
      :loading="loading"
      :pagination="pagination"
      money-module="sales"
      :empty-message="status === 'active' ? 'No clients found.' : 'No disabled clients.'"
      :row-class="clientRowClass"
      @search="onSearchChange"
      @page-change="goToPage"
      @per-page-change="setPerPage"
    >
      <template #card-title-name="{ item }">
        <ClientNameLink
          :client-id="item.id"
          :client-name="item.name"
          :building-id="item.sale_building_id"
        />
        <p v-if="item.phone" class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
          <a :href="`tel:${item.phone}`" class="hover:text-zinc-700 dark:hover:text-zinc-300">{{ item.phone }}</a>
        </p>
      </template>
      <template #cell-name="{ item }">
        <div>
          <ClientNameLink
            :client-id="item.id"
            :client-name="item.name"
            :building-id="item.sale_building_id"
          />
          <p v-if="item.voucher_number" class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">Ref {{ item.voucher_number }}</p>
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
      <template #cell-registration_date="{ item }">
        <DateCell :value="item.registration_date" />
      </template>
      <template #cell-progress="{ item }">
        <div v-if="status === 'active'" class="min-w-[7rem]">
          <div class="payment-progress-track">
            <div class="payment-progress-fill" :style="{ width: `${paymentProgress(item)}%` }" />
          </div>
          <p class="mt-1 text-xs tabular-nums text-zinc-500 dark:text-zinc-400">{{ paymentProgress(item) }}% collected</p>
        </div>
        <span v-else class="text-zinc-400">—</span>
      </template>
      <template #cell-agreed_sale_price="{ item }">
        <MoneyCell :amount="item.agreed_sale_price" module="sales" />
      </template>
      <template #cell-balance="{ item }">
        <div v-if="status === 'active'" class="flex flex-col items-end gap-1 lg:items-start">
          <MoneyCell :amount="item.balance" module="sales" />
          <StatusBadge :variant="balanceVariant(item.balance)" :label="balanceLabel(item.balance)" />
        </div>
        <span v-else class="text-zinc-400">—</span>
      </template>
      <template v-if="status === 'active'" #actions="{ item }">
        <RouterLink
          :to="{ path: '/sales/payments', query: { client_id: item.id, building_id: item.sale_building_id, action: 'new' } }"
          class="btn-secondary w-full sm:w-auto"
        >
          Pay
        </RouterLink>
        <button type="button" class="btn-secondary w-full sm:w-auto" @click="openEdit(item)">Edit</button>
        <button type="button" class="btn-destructive w-full sm:w-auto" @click="disableOne(item)">Disable</button>
      </template>
    </DataTable>

    <AppDialog
      v-model:open="showForm"
      :title="editing ? 'Edit client' : 'Register client'"
      size="2xl"
      :close-on-backdrop="false"
    >
      <form id="client-form" class="grid gap-4 lg:grid-cols-2" @submit.prevent="save">
        <div class="space-y-3">
          <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Property &amp; contact</p>
          <FormField label="Building" required>
            <BuildingSearchSelect
              v-model="form.sale_building_id"
              :buildings="buildings"
              required
              @change="onBuildingChange"
            />
          </FormField>
          <FormField label="Unit" required>
            <UnitSearchSelect
              v-model="form.sale_unit_id"
              :units="availableUnits"
              module="sales"
              required
            />
          </FormField>
          <FormField label="Full name" required>
            <input v-model="form.name" class="input-field" required />
          </FormField>
          <FormField label="Phone" required>
            <input v-model="form.phone" type="tel" class="input-field" required />
          </FormField>
          <FormField label="Email">
            <input v-model="form.email" type="email" class="input-field" autocomplete="off" />
          </FormField>
          <FormField label="ID / passport">
            <input v-model="form.passport_or_id" class="input-field" />
          </FormField>
        </div>
        <div class="space-y-3">
          <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Sale terms</p>
          <FormField :label="moneyLabel('Agreed sale price', 'sales')" required>
            <input v-model="form.agreed_sale_price" type="number" min="0" step="0.01" class="input-field" required />
          </FormField>
          <FormField :label="moneyLabel('Deposit', 'sales')">
            <input v-model="form.deposit" type="number" min="0" step="0.01" class="input-field" />
          </FormField>
          <FormField label="Voucher / reference">
            <input v-model="form.voucher_number" class="input-field" />
          </FormField>
          <FormField label="Registration date">
            <input v-model="form.registration_date" type="date" class="input-field" />
          </FormField>
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
      </form>
      <p v-if="error" class="mt-3 alert-error">{{ error }}</p>
      <template #footer>
        <button type="button" class="btn-secondary w-full sm:w-auto" @click="closeForm">Cancel</button>
        <button type="submit" form="client-form" class="btn-primary w-full sm:w-auto" :disabled="saving">
          {{ saving ? 'Saving…' : 'Save' }}
        </button>
      </template>
    </AppDialog>
  </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
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
import ClientNameLink from '../../components/sales/ClientNameLink.vue'
import { useConfirm } from '../../composables/useConfirm'
import { usePaginatedList } from '../../composables/usePaginatedList'
import { useToast } from '../../composables/useToast'
import {
  createClient,
  disableClient,
  fetchBuildings,
  fetchClients,
  fetchUnits,
  updateClient,
} from '../../api/sales'
import { formatMoney, moneyLabel } from '../../utils/money'

const { confirm } = useConfirm()
const toast = useToast()

const buildings = ref([])
const allUnits = ref([])
const summary = ref(null)
const saving = ref(false)
const showForm = ref(false)
const editing = ref(null)
const error = ref('')
const status = ref('active')
const filters = reactive({ building_id: '', with_balance: false })
const form = reactive({
  sale_building_id: '',
  sale_unit_id: '',
  name: '',
  phone: '',
  email: '',
  passport_or_id: '',
  agreed_sale_price: 0,
  deposit: 0,
  voucher_number: '',
  registration_date: new Date().toISOString().slice(0, 10),
  next_of_kin_name: '',
  next_of_kin_address: '',
  next_of_kin_id: '',
  next_of_kin_phone: '',
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
  const response = await fetchClients({
    ...params,
    status: status.value,
    building_id: filters.building_id || undefined,
    with_balance: filters.with_balance ? 1 : undefined,
  })
  summary.value = response.summary ?? null
  return response
})

const clientColumns = computed(() => {
  const cols = [
    { key: 'name', label: 'Client', cardTitle: true },
    { key: 'phone', label: 'Phone', mobileCard: true, searchable: false },
    { key: 'unit_label', label: 'Unit / building', mobileCard: true },
    { key: 'registration_date', label: 'Registered', tabletCard: true },
    { key: 'agreed_sale_price', label: 'Sale price', align: 'right', money: true, mobileCard: true },
  ]
  if (status.value === 'active') {
    cols.push(
      { key: 'progress', label: 'Progress', mobileCard: true, searchable: false },
      { key: 'balance', label: 'Balance', align: 'right', mobileCard: true },
    )
  }
  return cols
})

const availableUnits = computed(() => {
  if (!form.sale_building_id) return []
  return allUnits.value.filter((unit) => {
    if (Number(unit.sale_building_id) !== Number(form.sale_building_id)) return false
    if (editing.value && unit.id === editing.value.sale_unit_id) return true
    return unit.status === 'available'
  })
})

function paymentProgress(item) {
  const agreed = Number(item.agreed_sale_price ?? 0)
  if (agreed <= 0) return 0
  const balance = Number(item.balance ?? 0)
  const paid = Math.max(agreed - balance, 0)
  return Math.min(100, Math.round((paid / agreed) * 100))
}

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

function clientRowClass(item) {
  if (status.value !== 'active') return ''
  if (Number(item.balance ?? 0) <= 0) return ''
  return 'border-amber-200 bg-amber-50/70 hover:bg-amber-100/80 dark:border-amber-900/60 dark:bg-amber-950/30 dark:hover:bg-amber-900/40'
}

function setStatus(next) {
  status.value = next
  filters.with_balance = false
  reload()
}

async function loadBuildings() {
  const response = await fetchBuildings({ per_page: 200 })
  buildings.value = response.data
}

async function loadUnits() {
  const response = await fetchUnits({ per_page: 200 })
  allUnits.value = response.data
}

async function loadTable() {
  await reload()
}

function onBuildingChange() {
  form.sale_unit_id = ''
}

function openCreate() {
  editing.value = null
  Object.assign(form, {
    sale_building_id: filters.building_id || '',
    sale_unit_id: '',
    name: '',
    phone: '',
    email: '',
    passport_or_id: '',
    agreed_sale_price: 0,
    deposit: 0,
    voucher_number: '',
    registration_date: new Date().toISOString().slice(0, 10),
    next_of_kin_name: '',
    next_of_kin_address: '',
    next_of_kin_id: '',
    next_of_kin_phone: '',
  })
  error.value = ''
  showForm.value = true
}

function openEdit(client) {
  editing.value = client
  Object.assign(form, {
    sale_building_id: client.sale_building_id,
    sale_unit_id: client.sale_unit_id,
    name: client.name,
    phone: client.phone,
    email: client.email || '',
    passport_or_id: client.passport_or_id || '',
    agreed_sale_price: client.agreed_sale_price,
    deposit: client.deposit,
    voucher_number: client.voucher_number || '',
    registration_date: client.registration_date || '',
    next_of_kin_name: client.next_of_kin_name || '',
    next_of_kin_address: client.next_of_kin_address || '',
    next_of_kin_id: client.next_of_kin_id || '',
    next_of_kin_phone: client.next_of_kin_phone || '',
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
    const payload = {
      ...form,
      agreed_sale_price: Number(form.agreed_sale_price),
      deposit: Number(form.deposit || 0),
    }
    if (editing.value) {
      await updateClient(editing.value.id, payload)
      toast.success('Client updated.')
    } else {
      await createClient(payload)
      toast.success('Client registered.')
    }
    closeForm()
    await loadUnits()
    await reload()
  } catch (e) {
    error.value = e.response?.data?.message || Object.values(e.response?.data?.errors || {})[0]?.[0] || 'Could not save client.'
  } finally {
    saving.value = false
  }
}

async function disableOne(client) {
  const ok = await confirm({
    title: 'Disable client',
    message: `Disable ${client.name}? The unit will be marked available.`,
    confirmLabel: 'Disable',
    variant: 'danger',
  })
  if (!ok) return
  try {
    await disableClient(client.id)
    toast.success('Client disabled.')
    await loadUnits()
    await reload()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Could not disable client.')
  }
}

onMounted(async () => {
  await loadBuildings()
  await loadUnits()
  await load()
})
</script>
