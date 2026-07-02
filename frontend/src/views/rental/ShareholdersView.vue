<template>
  <section>
    <PageHeader title="Shareholders" subtitle="Shareholder registry and building bills.">
      <template #actions>
        <button type="button" class="btn-primary w-full sm:w-auto" @click="openCreate">
          {{ tab === 'bills' ? 'Record bill' : 'Add shareholder' }}
        </button>
      </template>
    </PageHeader>

    <div class="filter-bar">
      <div class="segmented-control">
        <button type="button" class="segmented-option" :class="tab === 'shareholders' ? 'segmented-option-active' : 'text-slate-700'" @click="setTab('shareholders')">Shareholders</button>
        <button type="button" class="segmented-option" :class="tab === 'bills' ? 'segmented-option-active' : 'text-slate-700'" @click="setTab('bills')">Bills</button>
      </div>
      <BuildingSearchSelect
        v-model="filters.building_id"
        :buildings="buildings"
        include-all
        placeholder="All buildings"
        @change="load"
      />
    </div>

    <ResponsiveDataList
      v-if="tab === 'shareholders'"
      :items="shareholders"
      :columns="shareholderColumns"
      empty-message="No shareholders."
    >
      <template #actions="{ item }">
        <button type="button" class="btn-secondary w-full sm:w-auto" @click="openEditShareholder(item)">Edit</button>
        <button type="button" class="btn-destructive w-full sm:w-auto" @click="removeShareholder(item)">Delete</button>
      </template>
    </ResponsiveDataList>

    <ResponsiveDataList
      v-else
      :items="bills"
      :columns="billColumns"
      empty-message="No bills found."
    >
      <template #actions="{ item }">
        <button type="button" class="btn-destructive w-full sm:w-auto" @click="removeBill(item)">Delete</button>
      </template>
    </ResponsiveDataList>

    <AppDialog v-model:open="showForm" :title="formTitle" size="sm" :close-on-backdrop="false">
      <div v-if="tab === 'bills'" class="grid gap-4">
        <label class="label-field">
          Shareholder
          <ShareholderSearchSelect
            v-model="billForm.shareholder_id"
            :shareholders="shareholders"
            required
          />
        </label>
        <label class="label-field">
          Building
          <BuildingSearchSelect
            v-model="billForm.rental_building_id"
            :buildings="buildings"
            required
          />
        </label>
        <label class="label-field">
          {{ amountLabel('rental') }}
          <input v-model="billForm.amount" type="number" min="0" step="0.01" class="input-field" required />
        </label>
        <label class="label-field">
          Bill date
          <input v-model="billForm.bill_date" type="date" class="input-field" required />
        </label>
        <label class="label-field">
          Remark
          <input v-model="billForm.remark" class="input-field" />
        </label>
      </div>
      <div v-else class="grid gap-4">
        <label class="label-field">
          Name
          <input v-model="shareholderForm.name" class="input-field" required />
        </label>
        <label class="label-field">
          Phone
          <input v-model="shareholderForm.phone" class="input-field" />
        </label>
        <label class="label-field">
          Address
          <input v-model="shareholderForm.address" class="input-field" />
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
import ShareholderSearchSelect from '../../components/ui/ShareholderSearchSelect.vue'
import BuildingSearchSelect from '../../components/ui/BuildingSearchSelect.vue'
import ResponsiveDataList from '../../components/data/ResponsiveDataList.vue'
import {
  createShareholder,
  createShareholderBill,
  deleteShareholder,
  deleteShareholderBill,
  fetchBuildings,
  fetchShareholderBills,
  fetchShareholders,
  updateShareholder,
} from '../../api/rental'
import { amountLabel } from '../../utils/money'

const buildings = ref([])
const shareholders = ref([])
const bills = ref([])
const tab = ref('shareholders')
const showForm = ref(false)
const editingShareholder = ref(null)
const error = ref('')
const filters = reactive({ building_id: '' })

const shareholderColumns = [
  { key: 'name', label: 'Name', cardTitle: true },
  { key: 'phone', label: 'Phone', mobileCard: true, format: (row) => row.phone || '—' },
  { key: 'address', label: 'Address', tabletCard: true, format: (row) => row.address || '—' },
]

const billColumns = [
  { key: 'shareholder_name', label: 'Shareholder', cardTitle: true },
  { key: 'amount', label: 'Amount', align: 'right', money: true, mobileCard: true },
  { key: 'bill_date', label: 'Date', mobileCard: true },
  { key: 'building_name', label: 'Building', tabletCard: true },
]

const shareholderForm = reactive({ name: '', phone: '', address: '' })
const billForm = reactive({
  shareholder_id: '',
  rental_building_id: '',
  amount: 0,
  bill_date: new Date().toISOString().slice(0, 10),
  remark: '',
})

const formTitle = computed(() => {
  if (tab.value === 'bills') return 'Record shareholder bill'
  return editingShareholder.value ? 'Edit shareholder' : 'Add shareholder'
})



async function loadBuildings() {
  buildings.value = (await fetchBuildings()).data
}

async function loadShareholders() {
  shareholders.value = (await fetchShareholders()).data
}

async function load() {
  await loadShareholders()
  if (tab.value === 'bills') {
    const params = {}
    if (filters.building_id) params.building_id = filters.building_id
    bills.value = (await fetchShareholderBills(params)).data
  }
}

function setTab(next) {
  tab.value = next
  load()
}

function openCreate() {
  editingShareholder.value = null
  error.value = ''
  if (tab.value === 'bills') {
    Object.assign(billForm, {
      shareholder_id: '',
      rental_building_id: filters.building_id || '',
      amount: 0,
      bill_date: new Date().toISOString().slice(0, 10),
      remark: '',
    })
  } else {
    Object.assign(shareholderForm, { name: '', phone: '', address: '' })
  }
  showForm.value = true
}

function openEditShareholder(shareholder) {
  editingShareholder.value = shareholder
  Object.assign(shareholderForm, {
    name: shareholder.name,
    phone: shareholder.phone || '',
    address: shareholder.address || '',
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
    if (tab.value === 'bills') {
      await createShareholderBill(billForm)
    } else if (editingShareholder.value) {
      await updateShareholder(editingShareholder.value.id, shareholderForm)
    } else {
      await createShareholder(shareholderForm)
    }
    closeForm()
    await load()
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not save.'
  }
}

async function removeShareholder(shareholder) {
  if (!window.confirm(`Delete ${shareholder.name}?`)) return
  try {
    await deleteShareholder(shareholder.id)
    await load()
  } catch (e) {
    window.alert(e.response?.data?.message || 'Could not delete shareholder.')
  }
}

async function removeBill(bill) {
  if (!window.confirm(`Delete bill for ${bill.shareholder_name}?`)) return
  await deleteShareholderBill(bill.id)
  await load()
}

onMounted(async () => {
  await loadBuildings()
  await load()
})
</script>
