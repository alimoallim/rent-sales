<template>
  <section>
    <PageHeader title="Buildings" subtitle="Rent property groupings.">
      <template #actions>
        <button type="button" class="btn-primary w-full sm:w-auto" @click="openCreate">Add building</button>
      </template>
    </PageHeader>

    <ResponsiveDataList
      :items="buildings"
      :columns="columns"
      empty-message="No buildings yet."
    >
      <template #actions="{ item }">
        <button type="button" class="btn-secondary w-full sm:w-auto" @click="openEdit(item)">Edit</button>
        <button type="button" class="btn-destructive w-full sm:w-auto" @click="remove(item)">Delete</button>
      </template>
    </ResponsiveDataList>

    <AppDialog v-model:open="showForm" :title="editing ? 'Edit building' : 'Add building'" size="sm">
      <label class="label-field">
        Name
        <input v-model="form.name" class="input-field" required />
      </label>
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
import { createBuilding, deleteBuilding, fetchBuildings, updateBuilding } from '../../api/rental'

const buildings = ref([])
const showForm = ref(false)
const editing = ref(null)
const error = ref('')
const form = reactive({ name: '' })

const columns = [
  { key: 'name', label: 'Name', cardTitle: true },
  { key: 'units_count', label: 'Units', mobileCard: true, format: (row) => row.units_count ?? 0 },
]

async function load() {
  const response = await fetchBuildings()
  buildings.value = response.data
}

function openCreate() {
  editing.value = null
  form.name = ''
  error.value = ''
  showForm.value = true
}

function openEdit(building) {
  editing.value = building
  form.name = building.name
  error.value = ''
  showForm.value = true
}

function closeForm() {
  showForm.value = false
}

async function save() {
  error.value = ''
  try {
    if (editing.value) {
      await updateBuilding(editing.value.id, { name: form.name })
    } else {
      await createBuilding({ name: form.name })
    }
    closeForm()
    await load()
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not save building.'
  }
}

async function remove(building) {
  if (!confirm(`Delete ${building.name}?`)) return
  try {
    await deleteBuilding(building.id)
    await load()
  } catch (e) {
    alert(e.response?.data?.message || 'Could not delete building.')
  }
}

onMounted(load)
</script>
