<template>
  <section>
    <PageHeader
      title="Payroll"
      subtitle="Employees and monthly salary payments."
      :breadcrumbs="[{ label: 'Rental', to: '/rental' }, { label: 'Payroll' }]"
    >
      <template #actions>
        <button type="button" class="btn-primary w-full sm:w-auto" @click="openCreate">
          {{ tab === 'payroll' ? 'Record payroll' : 'Add employee' }}
        </button>
      </template>
    </PageHeader>

    <FilterBar>
      <div class="segmented-control">
        <button
          type="button"
          class="segmented-option"
          :class="{ 'segmented-option-active': tab === 'payroll' }"
          @click="setTab('payroll')"
        >
          Payroll
        </button>
        <button
          type="button"
          class="segmented-option"
          :class="{ 'segmented-option-active': tab === 'employees' }"
          @click="setTab('employees')"
        >
          Employees
        </button>
      </div>
      <BuildingSearchSelect
        v-model="filters.building_id"
        :buildings="buildings"
        include-all
        placeholder="All buildings"
        @change="loadTable"
      />
    </FilterBar>

    <DataTable
      v-if="tab === 'payroll'"
      v-model:search="payrollSearch"
      server-side
      :items="payrollItems"
      :columns="payrollColumns"
      :loading="payrollLoading"
      :pagination="payrollPagination"
      money-module="rental"
      empty-message="No payroll entries."
      @search="onPayrollSearchChange"
      @page-change="payrollGoToPage"
      @per-page-change="setPayrollPerPage"
    >
      <template #cell-period="{ item }">{{ item.billing_month }}/{{ item.billing_year }}</template>
      <template #cell-salary_amount="{ item }">
        <MoneyCell :amount="item.salary_amount" module="rental" />
      </template>
      <template #actions="{ item }">
        <button type="button" class="btn-destructive w-full sm:w-auto" @click="removePayroll(item)">Delete</button>
      </template>
    </DataTable>

    <DataTable
      v-else
      v-model:search="employeeSearch"
      server-side
      :items="employeeItems"
      :columns="employeeColumns"
      :loading="employeeLoading"
      :pagination="employeePagination"
      money-module="rental"
      empty-message="No employees."
      @search="onEmployeeSearchChange"
      @page-change="employeeGoToPage"
      @per-page-change="setEmployeePerPage"
    >
      <template #cell-salary="{ item }">
        <MoneyCell :amount="item.salary" module="rental" />
      </template>
      <template #cell-building_name="{ item }">
        {{ item.building_name || '—' }}
      </template>
      <template #cell-status="{ item }">
        <StatusBadge :variant="item.status === 'current' ? 'success' : 'neutral'" :label="item.status" />
      </template>
      <template #actions="{ item }">
        <button type="button" class="btn-secondary w-full sm:w-auto" @click="openEditEmployee(item)">Edit</button>
        <button type="button" class="btn-destructive w-full sm:w-auto" @click="removeEmployee(item)">Delete</button>
      </template>
    </DataTable>

    <AppDialog v-model:open="showForm" :title="formTitle" size="md" :close-on-backdrop="false">
      <div v-if="tab === 'payroll'" class="grid gap-4">
        <FormField label="Building" required>
          <BuildingSearchSelect
            v-model="payrollForm.rental_building_id"
            :buildings="buildings"
            required
            @change="loadEmployeesForForm"
          />
        </FormField>
        <FormField label="Employee" required>
          <EmployeeSearchSelect
            v-model="payrollForm.employee_id"
            :employees="formEmployees"
            required
          />
        </FormField>
        <FormField label="Month" required>
          <select v-model="payrollForm.billing_month" class="input-field" required>
            <option v-for="month in months" :key="month.value" :value="month.value">{{ month.label }}</option>
          </select>
        </FormField>
        <FormField label="Year" required>
          <input v-model="payrollForm.billing_year" type="number" min="2000" class="input-field" required />
        </FormField>
        <FormField label="Paid on" required>
          <input v-model="payrollForm.paid_at" type="date" class="input-field" required />
        </FormField>
      </div>
      <div v-else class="grid gap-4">
        <FormField label="Building">
          <BuildingSearchSelect
            v-model="employeeForm.rental_building_id"
            :buildings="buildings"
            include-all
            all-label="Unassigned"
            placeholder="Unassigned"
          />
        </FormField>
        <FormField label="Name" required>
          <input v-model="employeeForm.name" class="input-field" required />
        </FormField>
        <FormField label="Position" required>
          <input v-model="employeeForm.position" class="input-field" required />
        </FormField>
        <FormField :label="moneyLabel('Base salary', 'rental')" required>
          <input v-model="employeeForm.salary" type="number" min="0" step="0.01" class="input-field" required />
        </FormField>
        <FormField label="Phone">
          <input v-model="employeeForm.phone" class="input-field" />
        </FormField>
        <FormField v-if="editingEmployee" label="Status">
          <select v-model="employeeForm.status" class="input-field">
            <option value="current">Current</option>
            <option value="former">Former</option>
          </select>
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
import EmployeeSearchSelect from '../../components/ui/EmployeeSearchSelect.vue'
import BuildingSearchSelect from '../../components/ui/BuildingSearchSelect.vue'
import FilterBar from '../../components/ui/FilterBar.vue'
import FormField from '../../components/ui/FormField.vue'
import StatusBadge from '../../components/ui/StatusBadge.vue'
import DataTable from '../../components/data/DataTable.vue'
import MoneyCell from '../../components/data/MoneyCell.vue'
import { useConfirm } from '../../composables/useConfirm'
import { usePaginatedList } from '../../composables/usePaginatedList'
import { useToast } from '../../composables/useToast'
import {
  createEmployee,
  createPayrollEntry,
  deleteEmployee,
  deletePayrollEntry,
  fetchBuildings,
  fetchEmployees,
  fetchPayroll,
  updateEmployee,
} from '../../api/rental'
import { moneyLabel } from '../../utils/money'

const { confirm } = useConfirm()
const toast = useToast()

const buildings = ref([])
const formEmployees = ref([])
const saving = ref(false)
const tab = ref('payroll')
const showForm = ref(false)
const editingEmployee = ref(null)
const error = ref('')
const filters = reactive({ building_id: '' })
const now = new Date()

const {
  items: payrollItems,
  loading: payrollLoading,
  search: payrollSearch,
  pagination: payrollPagination,
  load: loadPayroll,
  reload: reloadPayroll,
  goToPage: payrollGoToPage,
  setPerPage: setPayrollPerPage,
  onSearchChange: onPayrollSearchChange,
} = usePaginatedList((params) =>
  fetchPayroll({
    ...params,
    building_id: filters.building_id || undefined,
  }),
)

const {
  items: employeeItems,
  loading: employeeLoading,
  search: employeeSearch,
  pagination: employeePagination,
  load: loadEmployees,
  reload: reloadEmployees,
  goToPage: employeeGoToPage,
  setPerPage: setEmployeePerPage,
  onSearchChange: onEmployeeSearchChange,
} = usePaginatedList((params) =>
  fetchEmployees({
    ...params,
    building_id: filters.building_id || undefined,
    status: 'current',
  }),
)

const payrollColumns = [
  { key: 'employee_name', label: 'Employee', cardTitle: true },
  { key: 'salary_amount', label: 'Salary', align: 'right', money: true, mobileCard: true },
  { key: 'period', label: 'Period', mobileCard: true },
  { key: 'building_name', label: 'Building', tabletCard: true },
]

const employeeColumns = [
  { key: 'name', label: 'Name', cardTitle: true },
  { key: 'salary', label: 'Salary', align: 'right', money: true, mobileCard: true },
  { key: 'position', label: 'Position', mobileCard: true },
  { key: 'building_name', label: 'Building', tabletCard: true },
  { key: 'status', label: 'Status', mobileCard: true },
]

const payrollForm = reactive({
  employee_id: '',
  rental_building_id: '',
  billing_month: now.getMonth() + 1,
  billing_year: now.getFullYear(),
  paid_at: now.toISOString().slice(0, 10),
})
const employeeForm = reactive({
  rental_building_id: '',
  name: '',
  position: '',
  salary: 0,
  phone: '',
  status: 'current',
})

const months = [
  { value: 1, label: 'January' }, { value: 2, label: 'February' }, { value: 3, label: 'March' },
  { value: 4, label: 'April' }, { value: 5, label: 'May' }, { value: 6, label: 'June' },
  { value: 7, label: 'July' }, { value: 8, label: 'August' }, { value: 9, label: 'September' },
  { value: 10, label: 'October' }, { value: 11, label: 'November' }, { value: 12, label: 'December' },
]

const formTitle = computed(() => {
  if (tab.value === 'payroll') return 'Record payroll'
  return editingEmployee.value ? 'Edit employee' : 'Add employee'
})

async function loadBuildings() {
  const response = await fetchBuildings()
  buildings.value = response.data
}

async function loadTable() {
  if (tab.value === 'payroll') {
    await reloadPayroll()
  } else {
    await reloadEmployees()
  }
}

function setTab(next) {
  tab.value = next
  if (next === 'payroll') {
    loadPayroll()
  } else {
    loadEmployees()
  }
}

async function loadEmployeesForForm() {
  payrollForm.employee_id = ''
  if (!payrollForm.rental_building_id) {
    formEmployees.value = []
    return
  }
  formEmployees.value = (await fetchEmployees({
    building_id: payrollForm.rental_building_id,
    status: 'current',
  })).data
}

function openCreate() {
  editingEmployee.value = null
  error.value = ''
  if (tab.value === 'payroll') {
    Object.assign(payrollForm, {
      employee_id: '',
      rental_building_id: filters.building_id || '',
      billing_month: now.getMonth() + 1,
      billing_year: now.getFullYear(),
      paid_at: now.toISOString().slice(0, 10),
    })
    loadEmployeesForForm()
  } else {
    Object.assign(employeeForm, {
      rental_building_id: filters.building_id || '',
      name: '',
      position: '',
      salary: 0,
      phone: '',
      status: 'current',
    })
  }
  showForm.value = true
}

function openEditEmployee(employee) {
  editingEmployee.value = employee
  Object.assign(employeeForm, {
    rental_building_id: employee.rental_building_id || '',
    name: employee.name,
    position: employee.position,
    salary: employee.salary,
    phone: employee.phone || '',
    status: employee.status,
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
    if (tab.value === 'payroll') {
      await createPayrollEntry(payrollForm)
      toast.success('Payroll recorded.')
    } else if (editingEmployee.value) {
      await updateEmployee(editingEmployee.value.id, employeeForm)
      toast.success('Employee updated.')
    } else {
      await createEmployee(employeeForm)
      toast.success('Employee added.')
    }
    closeForm()
    if (tab.value === 'payroll') {
      await reloadPayroll()
    } else {
      await reloadEmployees()
    }
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not save.'
  } finally {
    saving.value = false
  }
}

async function removePayroll(entry) {
  const ok = await confirm({
    title: 'Delete payroll entry',
    message: `Delete payroll for ${entry.employee_name}?`,
    confirmLabel: 'Delete',
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deletePayrollEntry(entry.id)
    toast.success('Payroll entry deleted.')
    await reloadPayroll()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Could not delete payroll entry.')
  }
}

async function removeEmployee(employee) {
  const ok = await confirm({
    title: 'Delete employee',
    message: `Delete ${employee.name}?`,
    confirmLabel: 'Delete',
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deleteEmployee(employee.id)
    toast.success('Employee deleted.')
    await reloadEmployees()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Could not delete employee.')
  }
}

onMounted(async () => {
  await loadBuildings()
  await loadPayroll()
})
</script>
