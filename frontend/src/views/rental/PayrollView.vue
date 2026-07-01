<template>
  <section>
    <PageHeader title="Payroll" subtitle="Employees and monthly salary payments.">
      <template #actions>
        <button type="button" class="btn-primary w-full sm:w-auto" @click="openCreate">
          {{ tab === 'payroll' ? 'Record payroll' : 'Add employee' }}
        </button>
      </template>
    </PageHeader>

    <div class="filter-bar">
      <div class="segmented-control">
        <button type="button" class="segmented-option" :class="tab === 'payroll' ? 'segmented-option-active' : 'text-slate-700'" @click="setTab('payroll')">Payroll</button>
        <button type="button" class="segmented-option" :class="tab === 'employees' ? 'segmented-option-active' : 'text-slate-700'" @click="setTab('employees')">Employees</button>
      </div>
      <select v-model="filters.building_id" class="input-field" @change="load">
        <option value="">All buildings</option>
        <option v-for="building in buildings" :key="building.id" :value="building.id">{{ building.name }}</option>
      </select>
    </div>

    <ResponsiveDataList
      v-if="tab === 'payroll'"
      :items="payroll"
      :columns="payrollColumns"
      empty-message="No payroll entries."
    >
      <template #cell-period="{ item }">{{ item.billing_month }}/{{ item.billing_year }}</template>
      <template #actions="{ item }">
        <button type="button" class="btn-destructive w-full sm:w-auto" @click="removePayroll(item)">Delete</button>
      </template>
    </ResponsiveDataList>

    <ResponsiveDataList
      v-else
      :items="employees"
      :columns="employeeColumns"
      empty-message="No employees."
    >
      <template #actions="{ item }">
        <button type="button" class="btn-secondary w-full sm:w-auto" @click="openEditEmployee(item)">Edit</button>
        <button type="button" class="btn-destructive w-full sm:w-auto" @click="removeEmployee(item)">Delete</button>
      </template>
    </ResponsiveDataList>

    <AppDialog v-model:open="showForm" :title="formTitle" size="md" :close-on-backdrop="false">
      <div v-if="tab === 'payroll'" class="grid gap-4">
        <label class="label-field">
          Building
          <select v-model="payrollForm.rental_building_id" class="input-field" required @change="loadEmployeesForForm">
            <option disabled value="">Select building</option>
            <option v-for="building in buildings" :key="building.id" :value="building.id">{{ building.name }}</option>
          </select>
        </label>
        <label class="label-field">
          Employee
          <select v-model="payrollForm.employee_id" class="input-field" required>
            <option disabled value="">Select employee</option>
            <option v-for="employee in formEmployees" :key="employee.id" :value="employee.id">{{ employee.name }} — {{ formatMoney(employee.salary) }}</option>
          </select>
        </label>
        <label class="label-field">
          Month
          <select v-model="payrollForm.billing_month" class="input-field" required>
            <option v-for="month in months" :key="month.value" :value="month.value">{{ month.label }}</option>
          </select>
        </label>
        <label class="label-field">
          Year
          <input v-model="payrollForm.billing_year" type="number" min="2000" class="input-field" required />
        </label>
        <label class="label-field">
          Paid on
          <input v-model="payrollForm.paid_at" type="date" class="input-field" required />
        </label>
      </div>
      <div v-else class="grid gap-4">
        <label class="label-field">
          Building
          <select v-model="employeeForm.rental_building_id" class="input-field">
            <option value="">Unassigned</option>
            <option v-for="building in buildings" :key="building.id" :value="building.id">{{ building.name }}</option>
          </select>
        </label>
        <label class="label-field">
          Name
          <input v-model="employeeForm.name" class="input-field" required />
        </label>
        <label class="label-field">
          Position
          <input v-model="employeeForm.position" class="input-field" required />
        </label>
        <label class="label-field">
          Base salary (KES)
          <input v-model="employeeForm.salary" type="number" min="0" step="0.01" class="input-field" required />
        </label>
        <label class="label-field">
          Phone
          <input v-model="employeeForm.phone" class="input-field" />
        </label>
        <label v-if="editingEmployee" class="label-field">
          Status
          <select v-model="employeeForm.status" class="input-field">
            <option value="current">Current</option>
            <option value="former">Former</option>
          </select>
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
import ResponsiveDataList from '../../components/data/ResponsiveDataList.vue'
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

const buildings = ref([])
const payroll = ref([])
const employees = ref([])
const formEmployees = ref([])
const tab = ref('payroll')
const showForm = ref(false)
const editingEmployee = ref(null)
const error = ref('')
const filters = reactive({ building_id: '' })
const now = new Date()

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
  { key: 'building_name', label: 'Building', tabletCard: true, format: (row) => row.building_name || '—' },
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

function formatMoney(value) {
  return new Intl.NumberFormat('en-KE').format(Number(value || 0))
}

async function loadBuildings() {
  const response = await fetchBuildings()
  buildings.value = response.data
}

async function load() {
  const params = {}
  if (filters.building_id) params.building_id = filters.building_id
  if (tab.value === 'payroll') {
    payroll.value = (await fetchPayroll(params)).data
  } else {
    employees.value = (await fetchEmployees({ ...params, status: 'current' })).data
  }
}

function setTab(next) {
  tab.value = next
  load()
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
  try {
    if (tab.value === 'payroll') {
      await createPayrollEntry(payrollForm)
    } else if (editingEmployee.value) {
      await updateEmployee(editingEmployee.value.id, employeeForm)
    } else {
      await createEmployee(employeeForm)
    }
    closeForm()
    await load()
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not save.'
  }
}

async function removePayroll(entry) {
  if (!window.confirm(`Delete payroll for ${entry.employee_name}?`)) return
  await deletePayrollEntry(entry.id)
  await load()
}

async function removeEmployee(employee) {
  if (!window.confirm(`Delete ${employee.name}?`)) return
  try {
    await deleteEmployee(employee.id)
    await load()
  } catch (e) {
    window.alert(e.response?.data?.message || 'Could not delete employee.')
  }
}

onMounted(async () => {
  await loadBuildings()
  await load()
})
</script>
