<template>
  <section>
    <PageHeader title="Sale units" subtitle="Apartments listed for sale with list price and availability.">
      <template #actions>
        <button type="button" class="btn-primary w-full sm:w-auto" @click="openCreate">Add unit</button>
      </template>
    </PageHeader>

    <div class="filter-bar">
      <select v-model="filters.building_id" class="input-field" @change="load">
        <option value="">All buildings</option>
        <option v-for="building in buildings" :key="building.id" :value="building.id">{{ building.name }}</option>
      </select>
      <select v-model="filters.status" class="input-field" @change="load">
        <option value="">All statuses</option>
        <option value="available">Available</option>
        <option value="sold">Sold</option>
      </select>
    </div>

    <ResponsiveDataList :items="units" :columns="columns" empty-message="No sale units found.">
      <template #cell-status="{ item }">
        <StatusBadge :variant="item.status === 'available' ? 'success' : 'neutral'" :label="item.status" />
      </template>
      <template #actions="{ item }">
        <button
          v-if="item.status === 'available'"
          type="button"
          class="btn-secondary w-full sm:w-auto"
          @click="openEdit(item)"
        >
          Edit
        </button>
        <button
          v-if="item.status === 'available'"
          type="button"
          class="btn-destructive w-full sm:w-auto"
          @click="remove(item)"
        >
          Delete
        </button>
      </template>
    </ResponsiveDataList>

    <AppDialog v-model:open="showForm" :title="editing ? 'Edit unit' : 'Add unit'" size="lg">
      <div class="grid gap-4 sm:grid-cols-2">
        <label class="label-field sm:col-span-2">
          Building
          <select v-model="form.sale_building_id" class="input-field" required>
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
        <label class="label-field sm:col-span-2">
          Description
          <textarea v-model="form.description" rows="2" class="input-field" required />
        </label>
        <label class="label-field">
          List price (KES)
          <input v-model="form.list_price" type="number" min="0" step="0.01" class="input-field" required />
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
import StatusBadge from '../../components/ui/StatusBadge.vue'
import { createUnit, deleteUnit, fetchBuildings, fetchUnits, updateUnit } from '../../api/sales'

const buildings = ref([])
const units = ref([])
const showForm = ref(false)
const editing = ref(null)
const error = ref('')
const filters = reactive({ building_id: '', status: '' })
const form = reactive({
  sale_building_id: '',
  house_number: '',
  floor: '',
  description: '',
  list_price: 0,
})

const columns = [
  { key: 'house_number', label: 'Unit', cardTitle: true },
  { key: 'building_name', label: 'Building', mobileCard: true },
  { key: 'floor', label: 'Floor', tabletCard: true },
  { key: 'list_price', label: 'List price', align: 'right', money: true, mobileCard: true },
  { key: 'status', label: 'Status', tabletCard: true },
]

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
    sale_building_id: filters.building_id || '',
    house_number: '',
    floor: '',
    description: '',
    list_price: 0,
  })
  error.value = ''
  showForm.value = true
}

function openEdit(unit) {
  editing.value = unit
  Object.assign(form, {
    sale_building_id: unit.sale_building_id,
    house_number: unit.house_number,
    floor: unit.floor,
    description: unit.description,
    list_price: unit.list_price,
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
    const payload = { ...form, list_price: Number(form.list_price) }
    if (editing.value) {
      await updateUnit(editing.value.id, payload)
    } else {
      await createUnit(payload)
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
