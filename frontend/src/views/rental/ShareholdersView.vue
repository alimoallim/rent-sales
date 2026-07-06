<template>
  <section>
    <PageHeader
      title="Shareholders"
      subtitle="Shareholder registry and building bills."
      :breadcrumbs="[{ label: 'Rental', to: '/rental' }, { label: 'Shareholders' }]"
    >
      <template #actions>
        <button type="button" class="btn-primary w-full sm:w-auto" @click="openCreate">
          {{ tab === 'bills' ? 'Record bill' : 'Add shareholder' }}
        </button>
      </template>
    </PageHeader>

    <FilterBar>
      <div class="segmented-control">
        <button
          type="button"
          class="segmented-option"
          :class="{ 'segmented-option-active': tab === 'shareholders' }"
          @click="setTab('shareholders')"
        >
          Shareholders
        </button>
        <button
          type="button"
          class="segmented-option"
          :class="{ 'segmented-option-active': tab === 'bills' }"
          @click="setTab('bills')"
        >
          Bills
        </button>
      </div>
      <BuildingSearchSelect
        v-if="tab === 'bills'"
        v-model="filters.building_id"
        :buildings="buildings"
        include-all
        placeholder="All buildings"
        @change="loadTable"
      />
      <DateRangeFilter
        v-if="tab === 'bills'"
        v-model:from="filters.from"
        v-model:to="filters.to"
        @change="loadTable"
      />
    </FilterBar>

    <DataTable
      v-if="tab === 'shareholders'"
      v-model:search="shareholderSearch"
      server-side
      :items="shareholderItems"
      :columns="shareholderColumns"
      :loading="shareholderLoading"
      :pagination="shareholderPagination"
      empty-message="No shareholders."
      @search="onShareholderSearchChange"
      @page-change="shareholderGoToPage"
      @per-page-change="setShareholderPerPage"
    >
      <template #cell-phone="{ item }">
        {{ item.phone || '—' }}
      </template>
      <template #cell-address="{ item }">
        {{ item.address || '—' }}
      </template>
      <template #actions="{ item }">
        <RowActionButton icon="edit" label="Edit" @click="openEditShareholder(item)" />
        <RowActionButton icon="delete" label="Delete" variant="danger" @click="removeShareholder(item)" />
      </template>
    </DataTable>

    <DataTable
      v-else
      v-model:search="billSearch"
      server-side
      :items="billItems"
      :columns="billColumns"
      :loading="billLoading"
      :pagination="billPagination"
      money-module="rental"
      empty-message="No bills found."
      @search="onBillSearchChange"
      @page-change="billGoToPage"
      @per-page-change="setBillPerPage"
    >
      <template #cell-amount="{ item }">
        <MoneyCell :amount="item.amount" module="rental" />
      </template>
      <template #cell-bill_date="{ item }">
        <DateCell :value="item.bill_date" />
      </template>
      <template #actions="{ item }">
        <RowActionButton icon="delete" label="Delete" variant="danger" @click="removeBill(item)" />
      </template>
    </DataTable>

    <AppDialog v-model:open="showForm" :title="formTitle" size="sm" :close-on-backdrop="false">
      <div v-if="tab === 'bills'" class="grid gap-4">
        <FormField label="Shareholder" required>
          <ShareholderSearchSelect
            v-model="billForm.shareholder_id"
            :shareholders="shareholderOptions"
            required
          />
        </FormField>
        <FormField label="Building" required>
          <BuildingSearchSelect
            v-model="billForm.rental_building_id"
            :buildings="buildings"
            required
          />
        </FormField>
        <FormField :label="amountLabel('rental')" required>
          <input v-model="billForm.amount" type="number" min="0" step="0.01" class="input-field" required />
        </FormField>
        <FormField label="Bill date" required>
          <input v-model="billForm.bill_date" type="date" class="input-field" required />
        </FormField>
        <FormField label="Remark">
          <input v-model="billForm.remark" class="input-field" />
        </FormField>
      </div>
      <div v-else class="grid gap-4">
        <FormField label="Name" required>
          <input v-model="shareholderForm.name" class="input-field" required />
        </FormField>
        <FormField label="Phone">
          <input v-model="shareholderForm.phone" class="input-field" />
        </FormField>
        <FormField label="Address">
          <input v-model="shareholderForm.address" class="input-field" />
        </FormField>
      </div>
      <p v-if="error" class="mt-3 text-sm text-red-600 dark:text-red-400">{{ error }}</p>
      <template #footer>
        <button type="button" class="btn-secondary w-full sm:w-auto" @click="closeForm">Cancel</button>
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
import ShareholderSearchSelect from '../../components/ui/ShareholderSearchSelect.vue'
import BuildingSearchSelect from '../../components/ui/BuildingSearchSelect.vue'
import FilterBar from '../../components/ui/FilterBar.vue'
import DateRangeFilter from '../../components/ui/DateRangeFilter.vue'
import FormField from '../../components/ui/FormField.vue'
import DataTable from '../../components/data/DataTable.vue'
import RowActionButton from '../../components/ui/RowActionButton.vue'
import DateCell from '../../components/data/DateCell.vue'
import MoneyCell from '../../components/data/MoneyCell.vue'
import { useConfirm } from '../../composables/useConfirm'
import { usePaginatedList } from '../../composables/usePaginatedList'
import { useToast } from '../../composables/useToast'
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

const { confirm } = useConfirm()
const toast = useToast()

const buildings = ref([])
const shareholderOptions = ref([])
const saving = ref(false)
const tab = ref('shareholders')
const showForm = ref(false)
const editingShareholder = ref(null)
const error = ref('')
const filters = reactive({ building_id: '', from: '', to: '' })

const {
  items: shareholderItems,
  loading: shareholderLoading,
  search: shareholderSearch,
  pagination: shareholderPagination,
  load: loadShareholders,
  reload: reloadShareholders,
  goToPage: shareholderGoToPage,
  setPerPage: setShareholderPerPage,
  onSearchChange: onShareholderSearchChange,
} = usePaginatedList((params) => fetchShareholders(params))

const {
  items: billItems,
  loading: billLoading,
  search: billSearch,
  pagination: billPagination,
  load: loadBills,
  reload: reloadBills,
  goToPage: billGoToPage,
  setPerPage: setBillPerPage,
  onSearchChange: onBillSearchChange,
} = usePaginatedList((params) =>
  fetchShareholderBills({
    ...params,
    building_id: filters.building_id || undefined,
    from: filters.from || undefined,
    to: filters.to || undefined,
  }),
)

const shareholderColumns = [
  { key: 'name', label: 'Name', cardTitle: true },
  { key: 'phone', label: 'Phone', mobileCard: true },
  { key: 'address', label: 'Address', tabletCard: true },
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

async function loadShareholderOptions() {
  shareholderOptions.value = (await fetchShareholders({ per_page: 200 })).data
}

async function loadTable() {
  if (tab.value === 'shareholders') {
    await reloadShareholders()
  } else {
    await reloadBills()
  }
}

function setTab(next) {
  tab.value = next
  if (next === 'shareholders') {
    loadShareholders()
  } else {
    loadBills()
  }
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
  saving.value = true
  try {
    if (tab.value === 'bills') {
      await createShareholderBill(billForm)
      toast.success('Bill recorded.')
    } else if (editingShareholder.value) {
      await updateShareholder(editingShareholder.value.id, shareholderForm)
      toast.success('Shareholder updated.')
    } else {
      await createShareholder(shareholderForm)
      toast.success('Shareholder added.')
    }
    closeForm()
    if (tab.value === 'bills') {
      await reloadBills()
    } else {
      await reloadShareholders()
      await loadShareholderOptions()
    }
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not save.'
  } finally {
    saving.value = false
  }
}

async function removeShareholder(shareholder) {
  const ok = await confirm({
    title: 'Delete shareholder',
    message: `Delete ${shareholder.name}?`,
    confirmLabel: 'Delete',
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deleteShareholder(shareholder.id)
    toast.success('Shareholder deleted.')
    await reloadShareholders()
    await loadShareholderOptions()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Could not delete shareholder.')
  }
}

async function removeBill(bill) {
  const ok = await confirm({
    title: 'Delete bill',
    message: `Delete bill for ${bill.shareholder_name}?`,
    confirmLabel: 'Delete',
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deleteShareholderBill(bill.id)
    toast.success('Bill deleted.')
    await reloadBills()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Could not delete bill.')
  }
}

onMounted(async () => {
  await loadBuildings()
  await loadShareholderOptions()
  await loadShareholders()
})
</script>
