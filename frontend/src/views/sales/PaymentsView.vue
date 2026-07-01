<template>
  <section>
    <PageHeader title="Sales payments" subtitle="Record installments against agreed sale prices.">
      <template #actions>
        <button type="button" class="btn-primary w-full sm:w-auto" @click="openCreate">Record payment</button>
      </template>
    </PageHeader>

    <div class="filter-bar">
      <select v-model="filters.building_id" class="input-field" @change="load">
        <option value="">All buildings</option>
        <option v-for="building in buildings" :key="building.id" :value="building.id">{{ building.name }}</option>
      </select>
      <select v-model="filters.status" class="input-field" @change="load">
        <option value="">All statuses</option>
        <option value="active">Active</option>
        <option value="cancelled">Cancelled</option>
      </select>
    </div>

    <ResponsiveDataList :items="payments" :columns="columns" empty-message="No payments recorded yet.">
      <template #cell-status="{ item }">
        <StatusBadge :variant="item.status === 'active' ? 'success' : 'neutral'" :label="item.status" />
      </template>
      <template #actions="{ item }">
        <button
          v-if="item.status === 'active'"
          type="button"
          class="btn-secondary w-full sm:w-auto"
          @click="openEdit(item)"
        >
          Edit
        </button>
        <button
          v-if="item.status === 'active'"
          type="button"
          class="btn-destructive w-full sm:w-auto"
          @click="cancelOne(item)"
        >
          Cancel
        </button>
      </template>
    </ResponsiveDataList>

    <AppDialog
      v-model:open="showForm"
      :title="editing ? 'Edit payment' : 'Record payment'"
      size="2xl"
      :close-on-backdrop="false"
    >
      <div class="grid gap-4 lg:grid-cols-2">
        <div class="space-y-3">
          <label class="label-field">
            Building
            <select v-model="form.sale_building_id" class="input-field" required @change="onBuildingChange">
              <option disabled value="">Select building</option>
              <option v-for="building in buildings" :key="building.id" :value="building.id">{{ building.name }}</option>
            </select>
          </label>
          <label class="label-field">
            Client
            <select v-model="form.client_id" class="input-field" required @change="onClientChange">
              <option disabled value="">Select client</option>
              <option v-for="client in activeClients" :key="client.id" :value="client.id">
                {{ client.name }} — {{ client.unit_label }}
              </option>
            </select>
          </label>
          <label class="label-field">
            Amount (KES)
            <input v-model="form.amount" type="number" min="0.01" step="0.01" class="input-field" required />
          </label>
          <label class="label-field">
            Discount (KES)
            <input v-model="form.discount" type="number" min="0" step="0.01" class="input-field" />
          </label>
        </div>
        <div class="space-y-3">
          <div v-if="summary" class="rounded-md border border-zinc-200 bg-zinc-50 p-3 text-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-600">Client balance</p>
            <dl class="mt-2 grid gap-1">
              <div class="flex justify-between"><dt>Sale price</dt><dd class="tabular-nums">{{ formatMoney(summary.agreed_sale_price) }}</dd></div>
              <div class="flex justify-between"><dt>Paid total</dt><dd class="tabular-nums">{{ formatMoney(summary.paid_total) }}</dd></div>
              <div class="flex justify-between font-medium"><dt>Outstanding</dt><dd class="tabular-nums text-amber-700">{{ formatMoney(summary.balance) }}</dd></div>
            </dl>
          </div>
          <label class="label-field">
            Paid on
            <input v-model="form.paid_at" type="date" class="input-field" required />
          </label>
          <label class="label-field">
            Invoice / receipt
            <input v-model="form.invoice_reference" class="input-field" />
          </label>
          <label class="label-field">
            Bank
            <input v-model="form.bank" class="input-field" />
          </label>
          <label class="label-field">
            Remark
            <input v-model="form.remark" class="input-field" />
          </label>
        </div>
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
import ResponsiveDataList from '../../components/data/ResponsiveDataList.vue'
import StatusBadge from '../../components/ui/StatusBadge.vue'
import {
  cancelPayment,
  createPayment,
  fetchBuildings,
  fetchClientPaymentSummary,
  fetchClients,
  fetchPayments,
  updatePayment,
} from '../../api/sales'

const buildings = ref([])
const clients = ref([])
const payments = ref([])
const showForm = ref(false)
const editing = ref(null)
const error = ref('')
const summary = ref(null)
const filters = reactive({ building_id: '', status: '' })
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

const activeClients = computed(() =>
  clients.value.filter((c) => c.status === 'active' && (!form.sale_building_id || c.sale_building_id === form.sale_building_id)),
)

const columns = [
  { key: 'client_name', label: 'Client', cardTitle: true },
  { key: 'unit_label', label: 'Unit', mobileCard: true },
  { key: 'amount', label: 'Amount', align: 'right', money: true, mobileCard: true },
  { key: 'paid_at', label: 'Paid on', format: (row) => formatDate(row.paid_at), tabletCard: true },
  { key: 'status', label: 'Status', tabletCard: true },
]

function formatMoney(value) {
  return new Intl.NumberFormat('en-KE', { minimumFractionDigits: 2 }).format(Number(value || 0))
}

function formatDate(value) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('en-KE')
}

async function loadBuildings() {
  const response = await fetchBuildings()
  buildings.value = response.data
}

async function loadClients() {
  const response = await fetchClients({ status: 'active', per_page: 200 })
  clients.value = response.data
}

async function load() {
  const params = {}
  if (filters.building_id) params.building_id = filters.building_id
  if (filters.status) params.status = filters.status
  const response = await fetchPayments(params)
  payments.value = response.data
}

async function onClientChange() {
  summary.value = null
  if (!form.client_id) return
  summary.value = await fetchClientPaymentSummary(form.client_id)
}

function onBuildingChange() {
  form.client_id = ''
  summary.value = null
}

function openCreate() {
  editing.value = null
  Object.assign(form, {
    client_id: '',
    sale_building_id: filters.building_id || '',
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
  onClientChange()
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
      ...form,
      amount: Number(form.amount),
      discount: Number(form.discount || 0),
    }
    if (editing.value) {
      await updatePayment(editing.value.id, payload)
    } else {
      await createPayment(payload)
    }
    closeForm()
    await load()
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not save payment.'
  }
}

async function cancelOne(payment) {
  if (!confirm(`Cancel payment of ${formatMoney(payment.amount)} for ${payment.client_name}?`)) return
  try {
    await cancelPayment(payment.id)
    await load()
  } catch (e) {
    alert(e.response?.data?.message || 'Could not cancel payment.')
  }
}

onMounted(async () => {
  await loadBuildings()
  await loadClients()
  await load()
})
</script>
