<template>
  <section>
    <PageHeader title="Units" subtitle="Apartments and rooms within a building.">
      <template #actions>
        <button type="button" class="btn-primary w-full sm:w-auto" @click="openCreate">
          Add unit
        </button>
      </template>
    </PageHeader>

    <div class="filter-bar">
      <select v-model="filters.building_id" class="input-field" @change="load">
        <option value="">All buildings</option>
        <option v-for="building in buildings" :key="building.id" :value="building.id">{{ building.name }}</option>
      </select>
      <select v-model="filters.status" class="input-field" @change="load">
        <option value="">All statuses</option>
        <option value="vacant">Vacant</option>
        <option value="occupied">Occupied</option>
      </select>
    </div>

    <ResponsiveDataList :items="units" :columns="columns" empty-message="No units found.">
      <template #cell-status="{ item }">
        <span class="capitalize">{{ item.status }}</span>
      </template>
      <template #actions="{ item }">
        <button type="button" class="btn-secondary w-full sm:w-auto" @click="openEdit(item)">Edit</button>
        <button type="button" class="btn-destructive w-full sm:w-auto" @click="remove(item)">Delete</button>
      </template>
    </ResponsiveDataList>

    <AppDialog v-model:open="showForm" :title="editing ? 'Edit unit' : 'Add unit'" size="md">
      <div class="grid gap-4">
        <label v-if="!editing" class="label-field">
          Building
          <select v-model="form.rental_building_id" class="input-field" required>
            <option disabled value="">Select building</option>
            <option v-for="building in buildings" :key="building.id" :value="building.id">{{ building.name }}</option>
          </select>
        </label>
        <label class="label-field">
          Unit number
          <input v-model="form.house_number" class="input-field" required />
        </label>
        <label class="label-field">
          Floor
          <input v-model="form.floor" class="input-field" required />
        </label>
        <label class="label-field">
          Description
          <input v-model="form.description" class="input-field" required />
        </label>
        <label class="label-field">
          Monthly rent (KES)
          <input v-model="form.monthly_rent" type="number" min="0" step="0.01" class="input-field" required />
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
import { onMounted, reactive, ref } from 'vue'
import PageHeader from '../../components/PageHeader.vue'
import AppDialog from '../../components/ui/AppDialog.vue'
import ResponsiveDataList from '../../components/data/ResponsiveDataList.vue'
import { createUnit, deleteUnit, fetchBuildings, fetchUnits, updateUnit } from '../../api/rental'

const buildings = ref([])
const units = ref([])
const showForm = ref(false)
const editing = ref(null)
const error = ref('')
const filters = reactive({ building_id: '', status: '' })
const form = reactive({
  rental_building_id: '',
  house_number: '',
  floor: '',
  description: '',
  monthly_rent: '',
})

const columns = [
  { key: 'house_number', label: 'Unit', cardTitle: true },
  { key: 'building_name', label: 'Building', mobileCard: true },
  { key: 'monthly_rent', label: 'Rent', money: true, mobileCard: true },
  { key: 'floor', label: 'Floor', tabletCard: true },
  { key: 'status', label: 'Status', mobileCard: true },
]

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
  if (filters.status) params.status = filters.status
  const response = await fetchUnits(params)
  units.value = response.data
}

function openCreate() {
  editing.value = null
  Object.assign(form, {
    rental_building_id: filters.building_id || '',
    house_number: '',
    floor: '',
    description: '',
    monthly_rent: '',
  })
  error.value = ''
  showForm.value = true
}

function openEdit(unit) {
  editing.value = unit
  Object.assign(form, {
    rental_building_id: unit.rental_building_id,
    house_number: unit.house_number,
    floor: unit.floor,
    description: unit.description,
    monthly_rent: unit.monthly_rent,
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
      house_number: form.house_number,
      floor: form.floor,
      description: form.description,
      monthly_rent: form.monthly_rent,
    }
    if (editing.value) {
      await updateUnit(editing.value.id, payload)
    } else {
      await createUnit({ ...payload, rental_building_id: form.rental_building_id })
    }
    closeForm()
    await load()
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not save unit.'
  }
}

async function remove(unit) {
  if (!confirm(`Delete unit ${unit.house_number}?`)) return
  try {
    await deleteUnit(unit.id)
    await load()
  } catch (e) {
    alert(e.response?.data?.message || 'Could not delete unit.')
  }
}

onMounted(async () => {
  await loadBuildings()
  await load()
})
</script>
