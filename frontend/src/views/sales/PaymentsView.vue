<template>
  <section>
    <PageHeader
      title="Sales payments"
      subtitle="Record installments against agreed sale prices."
      :breadcrumbs="[{ label: 'Sales', to: '/sales' }, { label: 'Payments' }]"
    >
      <template #actions>
        <button type="button" class="btn-primary w-full sm:w-auto" @click="openCreate">Record payment</button>
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
      <DateRangeFilter
        v-model:from="filters.from"
        v-model:to="filters.to"
        @change="loadTable"
      />
      <select v-model="filters.status" class="input-field" @change="loadTable">
        <option value="">All statuses</option>
        <option value="active">Active</option>
        <option value="cancelled">Cancelled</option>
      </select>
    </FilterBar>

    <DataTable
      v-model:search="search"
      server-side
      :items="items"
      :columns="columns"
      :loading="loading"
      :pagination="pagination"
      money-module="sales"
      empty-message="No payments recorded yet."
      @search="onSearchChange"
      @page-change="goToPage"
      @per-page-change="setPerPage"
    >
      <template #card-title-client_name="{ item }">
        <ClientNameLink
          :client-id="item.client_id"
          :client-name="item.client_name"
          :building-id="item.sale_building_id"
        />
      </template>
      <template #cell-client_name="{ item }">
        <ClientNameLink
          :client-id="item.client_id"
          :client-name="item.client_name"
          :building-id="item.sale_building_id"
        />
      </template>
      <template #cell-paid_at="{ item }">
        <DateCell :value="item.paid_at" />
      </template>
      <template #cell-amount="{ item }">
        <MoneyCell :amount="item.amount" module="sales" />
      </template>
      <template #cell-status="{ item }">
        <StatusBadge :variant="item.status === 'active' ? 'success' : 'neutral'" :label="item.status" />
      </template>
      <template #actions="{ item }">
        <RowActionButton
          v-if="item.status === 'active'"
          icon="print"
          label="Print receipt"
          @click="printReceipt(item)"
        />
        <RowActionButton
          v-if="item.status === 'active'"
          icon="edit"
          label="Edit"
          @click="openEdit(item)"
        />
        <RowActionButton
          v-if="item.status === 'active'"
          icon="cancel"
          label="Cancel payment"
          variant="danger"
          @click="cancelOne(item)"
        />
      </template>
    </DataTable>

    <AppDialog
      v-model:open="showForm"
      :title="editing ? 'Edit payment' : 'Record payment'"
      size="2xl"
      :close-on-backdrop="false"
    >
      <form id="sales-payment-form" class="grid gap-4 lg:grid-cols-2" @submit.prevent="save">
        <div class="space-y-3">
          <FormField label="Building" required>
            <BuildingSearchSelect
              v-model="form.sale_building_id"
              :buildings="buildings"
              required
              @change="onBuildingChange"
            />
          </FormField>
          <FormField label="Client" required>
            <ClientSearchSelect
              v-model="form.client_id"
              :building-id="form.sale_building_id"
              required
              @change="onClientChange"
            />
          </FormField>
          <FormField :label="amountLabel('sales')" required>
            <input v-model="form.amount" type="number" min="0.01" step="0.01" class="input-field" required />
          </FormField>
          <FormField :label="moneyLabel('Discount', 'sales')">
            <input v-model="form.discount" type="number" min="0" step="0.01" class="input-field" />
          </FormField>
        </div>
        <div class="space-y-3">
          <div v-if="summary" class="rounded-md border border-zinc-200 bg-zinc-50 p-3 text-sm dark:border-zinc-700 dark:bg-zinc-900/50">
            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:text-zinc-400">Client balance</p>
            <dl class="mt-2 grid gap-1">
              <div class="flex justify-between">
                <dt>Sale price</dt>
                <dd><MoneyCell :amount="summary.agreed_sale_price" module="sales" /></dd>
              </div>
              <div class="flex justify-between">
                <dt>Paid total</dt>
                <dd><MoneyCell :amount="summary.paid_total" module="sales" /></dd>
              </div>
              <div class="flex justify-between font-medium">
                <dt>Outstanding</dt>
                <dd><MoneyCell :amount="summary.balance" module="sales" /></dd>
              </div>
            </dl>
          </div>
          <FormField label="Paid on" required>
            <input v-model="form.paid_at" type="date" class="input-field" required />
          </FormField>
          <FormField label="Invoice / receipt">
            <input v-model="form.invoice_reference" class="input-field" />
          </FormField>
          <FormField label="Bank">
            <input v-model="form.bank" class="input-field" />
          </FormField>
          <FormField label="Remark">
            <input v-model="form.remark" class="input-field" />
          </FormField>
        </div>

        <div
          v-if="wouldOverpay"
          class="alert-error lg:col-span-2"
          role="alert"
        >
          <p class="font-semibold">Payment exceeds outstanding balance</p>
          <dl class="mt-2 grid gap-1.5">
            <div class="flex items-baseline justify-between gap-4">
              <dt>Total being recorded</dt>
              <dd class="font-medium tabular-nums">{{ formatMoney(paymentTotal, 'sales') }}</dd>
            </div>
            <div class="flex items-baseline justify-between gap-4">
              <dt>Outstanding</dt>
              <dd class="font-medium tabular-nums">{{ formatMoney(amountOwed, 'sales') }}</dd>
            </div>
          </dl>
          <p class="mt-2 font-medium">Reduce the amount or discount before saving.</p>
        </div>

        <p v-if="error" class="alert-error lg:col-span-2">{{ error }}</p>
      </form>
      <template #footer>
        <button type="button" class="btn-secondary w-full sm:w-auto" @click="closeForm">Cancel</button>
        <button type="submit" form="sales-payment-form" class="btn-primary w-full sm:w-auto" :disabled="paymentBlocked || saving">
          {{ saving ? 'Saving…' : 'Save' }}
        </button>
      </template>
    </AppDialog>
  </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import PageHeader from '../../components/PageHeader.vue'
import AppDialog from '../../components/ui/AppDialog.vue'
import BuildingSearchSelect from '../../components/ui/BuildingSearchSelect.vue'
import ClientSearchSelect from '../../components/ui/ClientSearchSelect.vue'
import FilterBar from '../../components/ui/FilterBar.vue'
import DateRangeFilter from '../../components/ui/DateRangeFilter.vue'
import FormField from '../../components/ui/FormField.vue'
import DataTable from '../../components/data/DataTable.vue'
import RowActionButton from '../../components/ui/RowActionButton.vue'
import DateCell from '../../components/data/DateCell.vue'
import MoneyCell from '../../components/data/MoneyCell.vue'
import StatusBadge from '../../components/ui/StatusBadge.vue'
import ClientNameLink from '../../components/sales/ClientNameLink.vue'
import { useConfirm } from '../../composables/useConfirm'
import { usePaginatedList } from '../../composables/usePaginatedList'
import { useToast } from '../../composables/useToast'
import { amountLabel, formatMoney, moneyLabel } from '../../utils/money'
import { printSalesPaymentReceipt } from '../../utils/paymentReceipt'
import {
  cancelPayment,
  createPayment,
  fetchBuildings,
  fetchClientPaymentSummary,
  fetchPayments,
  updatePayment,
} from '../../api/sales'

const route = useRoute()
const { confirm } = useConfirm()
const toast = useToast()

const buildings = ref([])
const saving = ref(false)
const showForm = ref(false)
const editing = ref(null)
const error = ref('')
const summary = ref(null)
const filters = reactive({ building_id: '', status: '', from: '', to: '' })
const form = reactive({
  client_id: '',
  sale_building_id: '',
  amount: '',
  discount: 0,
  invoice_reference: '',
  bank: '',
  remark: '',
  paid_at: new Date().toISOString().slice(0, 10),
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
} = usePaginatedList((params) =>
  fetchPayments({
    ...params,
    building_id: filters.building_id || undefined,
    status: filters.status || undefined,
    from: filters.from || undefined,
    to: filters.to || undefined,
  }),
)

const columns = [
  { key: 'client_name', label: 'Client', cardTitle: true },
  { key: 'unit_label', label: 'Unit', mobileCard: true },
  { key: 'amount', label: 'Amount', align: 'right', money: true, mobileCard: true },
  { key: 'paid_at', label: 'Paid on', tabletCard: true },
  { key: 'status', label: 'Status', tabletCard: true },
]

const paymentTotal = computed(() => Number(form.amount || 0) + Number(form.discount || 0))

const amountOwed = computed(() => {
  if (!summary.value) return 0
  const balance = Number(summary.value.balance || 0)
  return balance > 0 ? balance : 0
})

const wouldOverpay = computed(() => paymentTotal.value > amountOwed.value)

const paymentBlocked = computed(() => Boolean(summary.value) && wouldOverpay.value)

async function loadBuildings() {
  const response = await fetchBuildings()
  buildings.value = response.data
}

async function loadTable() {
  await reload()
}

async function loadSummary() {
  if (!form.client_id) {
    summary.value = null
    return
  }

  try {
    const params = {}
    if (editing.value) {
      params.exclude_payment_id = editing.value.id
    }

    summary.value = await fetchClientPaymentSummary(form.client_id, params)
  } catch {
    summary.value = null
    toast.error('Could not load client balance.')
  }
}

async function onClientChange() {
  await loadSummary()
}

function onBuildingChange() {
  form.client_id = ''
  summary.value = null
}

function openCreate(prefill = {}) {
  editing.value = null
  Object.assign(form, {
    client_id: prefill.client_id ? String(prefill.client_id) : '',
    sale_building_id: prefill.sale_building_id ? String(prefill.sale_building_id) : (filters.building_id || ''),
    amount: '',
    discount: 0,
    invoice_reference: '',
    bank: '',
    remark: '',
    paid_at: new Date().toISOString().slice(0, 10),
  })
  summary.value = null
  error.value = ''
  showForm.value = true
  if (form.client_id) {
    loadSummary()
  }
}

function openEdit(payment) {
  editing.value = payment
  Object.assign(form, {
    client_id: payment.client_id,
    sale_building_id: payment.sale_building_id,
    amount: payment.amount,
    discount: payment.discount || 0,
    invoice_reference: payment.invoice_reference || '',
    bank: payment.bank || '',
    remark: payment.remark || '',
    paid_at: payment.paid_at?.slice(0, 10) || '',
  })
  error.value = ''
  summary.value = null
  showForm.value = true
  loadSummary()
}

function closeForm() {
  showForm.value = false
}

async function save() {
  error.value = ''

  if (paymentTotal.value <= 0) {
    error.value = 'Enter an amount greater than zero.'
    return
  }

  if (paymentBlocked.value) {
    error.value = 'Payment cannot exceed the client outstanding balance.'
    return
  }

  saving.value = true
  try {
    const payload = {
      ...form,
      amount: Number(form.amount),
      discount: Number(form.discount || 0),
    }
    if (editing.value) {
      await updatePayment(editing.value.id, payload)
      toast.success('Payment updated.')
    } else {
      await createPayment(payload)
      toast.success('Payment recorded.')
    }
    closeForm()
    await reload()
  } catch (e) {
    const validation = e.response?.data?.errors
    error.value = validation
      ? Object.values(validation).flat().join(' ')
      : e.response?.data?.message || 'Could not save payment.'
  } finally {
    saving.value = false
  }
}

function printReceipt(payment) {
  printSalesPaymentReceipt(payment)
}

async function cancelOne(payment) {
  const ok = await confirm({
    title: 'Cancel payment',
    message: `Cancel payment of ${formatMoney(payment.amount, 'sales')} for ${payment.client_name}?`,
    confirmLabel: 'Cancel payment',
    variant: 'danger',
  })
  if (!ok) return
  try {
    await cancelPayment(payment.id)
    toast.success('Payment cancelled.')
    await reload()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Could not cancel payment.')
  }
}

watch(
  () => [form.amount, form.discount],
  () => {
    error.value = ''
  },
)

onMounted(async () => {
  await loadBuildings()
  await load()
  if (route.query.action === 'new' && route.query.client_id) {
    openCreate({
      client_id: route.query.client_id,
      sale_building_id: route.query.building_id,
    })
  }
})
</script>
