<template>
  <section>
    <PageHeader
      :title="status === 'active' ? 'Clients' : 'Disabled clients'"
      :subtitle="status === 'active' ? 'Register buyers and track sale balances.' : 'Cancelled or disabled client records.'"
    >
      <template v-if="status === 'active'" #actions>
        <button type="button" class="btn-primary w-full sm:w-auto" @click="openCreate">Register client</button>
      </template>
    </PageHeader>

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
        <input v-model="filters.with_balance" type="checkbox" class="rounded border-zinc-300" @change="load" />
        With balance only
      </label>
    </div>

    <ResponsiveDataList
      :items="clients"
      :columns="clientColumns"
      money-module="sales"
      :empty-message="status === 'active' ? 'No clients found.' : 'No disabled clients.'"
    >
      <template #card-title-name="{ item }">
        <ClientNameLink
          :client-id="item.id"
          :client-name="item.name"
          :building-id="item.sale_building_id"
        />
      </template>
      <template #cell-name="{ item }">
        <ClientNameLink
          :client-id="item.id"
          :client-name="item.name"
          :building-id="item.sale_building_id"
        />
      </template>
      <template #cell-balance="{ item }">
        <span
          v-if="status === 'active'"
          class="font-medium tabular-nums"
          :class="Number(item.balance) > 0 ? 'text-amber-700' : 'text-emerald-700'"
        >
          {{ formatMoney(item.balance, 'sales') }}
        </span>
        <span v-else>—</span>
      </template>
      <template v-if="status === 'active'" #actions="{ item }">
        <button type="button" class="btn-secondary w-full sm:w-auto" @click="openEdit(item)">Edit</button>
        <button type="button" class="btn-destructive w-full sm:w-auto" @click="disableOne(item)">Disable</button>
      </template>
    </ResponsiveDataList>

    <AppDialog
      v-model:open="showForm"
      :title="editing ? 'Edit client' : 'Register client'"
      size="2xl"
      :close-on-backdrop="false"
    >
      <form class="grid gap-4 lg:grid-cols-2" @submit.prevent="save">
        <div class="space-y-3">
          <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Property &amp; contact</p>
          <label class="label-field">
            Building
            <BuildingSearchSelect
              v-model="form.sale_building_id"
              :buildings="buildings"
              required
              @change="onBuildingChange"
            />
          </label>
          <label class="label-field">
            Unit
            <UnitSearchSelect
              v-model="form.sale_unit_id"
              :units="availableUnits"
              module="sales"
              required
            />
          </label>
          <label class="label-field">
            Full name
            <input v-model="form.name" class="input-field" required />
          </label>
          <label class="label-field">
            Phone
            <input v-model="form.phone" class="input-field" required />
          </label>
          <label class="label-field">
            ID / passport
            <input v-model="form.passport_or_id" class="input-field" />
          </label>
        </div>
        <div class="space-y-3">
          <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Sale terms</p>
          <label class="label-field">
            Agreed sale price (USD)
            <input v-model="form.agreed_sale_price" type="number" min="0" step="0.01" class="input-field" required />
          </label>
          <label class="label-field">
            Deposit (USD)
            <input v-model="form.deposit" type="number" min="0" step="0.01" class="input-field" />
          </label>
          <label class="label-field">
            Voucher / reference
            <input v-model="form.voucher_number" class="input-field" />
          </label>
          <label class="label-field">
            Registration date
            <input v-model="form.registration_date" type="date" class="input-field" />
          </label>
        </div>
      </form>
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
import UnitSearchSelect from '../../components/ui/UnitSearchSelect.vue'
import ResponsiveDataList from '../../components/data/ResponsiveDataList.vue'
import ClientNameLink from '../../components/sales/ClientNameLink.vue'
import { formatMoney } from '../../utils/money'
import {
  createClient,
  disableClient,
  fetchBuildings,
  fetchClients,
  fetchUnits,
  updateClient,
} from '../../api/sales'

const buildings = ref([])
const allUnits = ref([])
const clients = ref([])
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
  passport_or_id: '',
  agreed_sale_price: 0,
  deposit: 0,
  voucher_number: '',
  registration_date: new Date().toISOString().slice(0, 10),
})

const clientColumns = computed(() => {
  const cols = [
    { key: 'name', label: 'Client', cardTitle: true },
    { key: 'unit_label', label: 'Unit', mobileCard: true },
    { key: 'building_name', label: 'Building', tabletCard: true },
    { key: 'agreed_sale_price', label: 'Sale price', align: 'right', money: true, mobileCard: true },
  ]
  if (status.value === 'active') {
    cols.push({ key: 'balance', label: 'Balance', align: 'right', mobileCard: true })
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

function setStatus(next) {
  status.value = next
  filters.with_balance = false
  load()
}

async function loadBuildings() {
  const response = await fetchBuildings()
  buildings.value = response.data
}

async function loadUnits() {
  const response = await fetchUnits({ per_page: 200 })
  allUnits.value = response.data
}

async function load() {
  const params = { status: status.value }
  if (filters.building_id) params.building_id = filters.building_id
  if (filters.with_balance) params.with_balance = 1
  const response = await fetchClients(params)
  clients.value = response.data
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
    passport_or_id: '',
    agreed_sale_price: 0,
    deposit: 0,
    voucher_number: '',
    registration_date: new Date().toISOString().slice(0, 10),
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
    passport_or_id: client.passport_or_id || '',
    agreed_sale_price: client.agreed_sale_price,
    deposit: client.deposit,
    voucher_number: client.voucher_number || '',
    registration_date: client.registration_date || '',
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
      ...form,
      agreed_sale_price: Number(form.agreed_sale_price),
      deposit: Number(form.deposit || 0),
    }
    if (editing.value) {
      await updateClient(editing.value.id, payload)
    } else {
      await createClient(payload)
    }
    closeForm()
    await loadUnits()
    await load()
  } catch (e) {
    error.value = e.response?.data?.message || Object.values(e.response?.data?.errors || {})[0]?.[0] || 'Could not save client.'
  }
}

async function disableOne(client) {
  if (!confirm(`Disable ${client.name}? The unit will be marked available.`)) return
  try {
    await disableClient(client.id)
    await loadUnits()
    await load()
  } catch (e) {
    alert(e.response?.data?.message || 'Could not disable client.')
  }
}

onMounted(async () => {
  await loadBuildings()
  await loadUnits()
  await load()
})
</script>
