<template>
  <section>
    <PageHeader title="Users" subtitle="Manage staff accounts and module access.">
      <template #actions>
        <button type="button" class="btn-primary w-full sm:w-auto" @click="openCreate">Add user</button>
      </template>
    </PageHeader>

    <ResponsiveDataList
      :items="users"
      :columns="columns"
      empty-message="No users yet."
    >
      <template #actions="{ item }">
        <button type="button" class="btn-secondary w-full sm:w-auto" @click="openEdit(item)">Edit</button>
        <button
          v-if="item.id !== currentUserId"
          type="button"
          class="btn-destructive w-full sm:w-auto"
          @click="remove(item)"
        >
          Delete
        </button>
      </template>
    </ResponsiveDataList>

    <AppDialog v-model:open="showForm" :title="editing ? 'Edit user' : 'Add user'" size="md">
      <div class="space-y-3">
        <label class="label-field">
          Full name
          <input v-model="form.name" class="input-field" required />
        </label>

        <label class="label-field">
          Username
          <input v-model="form.username" class="input-field" required autocomplete="off" />
        </label>

        <label class="label-field">
          Email
          <input v-model="form.email" type="email" class="input-field" autocomplete="off" />
        </label>

        <label class="label-field">
          Role
          <select v-model="form.role" class="input-field" required>
            <option value="admin">Administrator (rental + sales)</option>
            <option value="rental">Rental staff</option>
            <option value="sales">Sales staff</option>
          </select>
        </label>

        <label v-if="form.role === 'rental'" class="flex items-center gap-2 text-sm text-zinc-700">
          <input v-model="form.is_manager" type="checkbox" class="rounded border-zinc-300" />
          Manager (can approve charge batches)
        </label>

        <label class="label-field">
          Status
          <select v-model="form.status" class="input-field">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </label>

        <label class="label-field">
          {{ editing ? 'New password (leave blank to keep current)' : 'Password' }}
          <input v-model="form.password" type="password" class="input-field" :required="!editing" autocomplete="new-password" />
        </label>

        <label v-if="form.password" class="label-field">
          Confirm password
          <input v-model="form.password_confirmation" type="password" class="input-field" :required="!!form.password" autocomplete="new-password" />
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
import { useAuthStore } from '../../stores/auth'
import { createUser, deleteUser, fetchUsers, updateUser } from '../../api/users'

const auth = useAuthStore()
const users = ref([])
const showForm = ref(false)
const editing = ref(null)
const error = ref('')
const form = reactive({
  name: '',
  username: '',
  email: '',
  role: 'rental',
  status: 'active',
  is_manager: false,
  password: '',
  password_confirmation: '',
})

const currentUserId = computed(() => auth.user?.id)

const roleLabels = {
  admin: 'Administrator',
  rental: 'Rental',
  sales: 'Sales',
}

const columns = [
  { key: 'name', label: 'Name', cardTitle: true },
  { key: 'username', label: 'Username', mobileCard: true },
  {
    key: 'role',
    label: 'Role',
    mobileCard: true,
    format: (row) => roleLabels[row.role] ?? row.role,
  },
  {
    key: 'status',
    label: 'Status',
    format: (row) => (row.status === 'active' ? 'Active' : 'Inactive'),
  },
  {
    key: 'access',
    label: 'Module access',
    format: (row) => {
      const parts = []
      if (row.can_access_rental) parts.push('Rental')
      if (row.can_access_sales) parts.push('Sales')
      return parts.join(', ') || '—'
    },
  },
]

function resetForm() {
  form.name = ''
  form.username = ''
  form.email = ''
  form.role = 'rental'
  form.status = 'active'
  form.is_manager = false
  form.password = ''
  form.password_confirmation = ''
}

async function load() {
  const response = await fetchUsers()
  users.value = response.data
}

function openCreate() {
  editing.value = null
  resetForm()
  error.value = ''
  showForm.value = true
}

function openEdit(user) {
  editing.value = user
  form.name = user.name
  form.username = user.username
  form.email = user.email ?? ''
  form.role = user.role
  form.status = user.status
  form.is_manager = !!user.is_manager
  form.password = ''
  form.password_confirmation = ''
  error.value = ''
  showForm.value = true
}

function closeForm() {
  showForm.value = false
}

function buildPayload() {
  const payload = {
    name: form.name,
    username: form.username,
    email: form.email || null,
    role: form.role,
    status: form.status,
    is_manager: form.role === 'rental' ? form.is_manager : false,
  }

  if (form.password) {
    payload.password = form.password
    payload.password_confirmation = form.password_confirmation
  }

  return payload
}

async function save() {
  error.value = ''
  try {
    const payload = buildPayload()
    if (editing.value) {
      await updateUser(editing.value.id, payload)
    } else {
      await createUser(payload)
    }
    closeForm()
    await load()
  } catch (e) {
    const validation = e.response?.data?.errors
    if (validation) {
      error.value = Object.values(validation).flat().join(' ')
    } else {
      error.value = e.response?.data?.message || 'Could not save user.'
    }
  }
}

async function remove(user) {
  if (!confirm(`Delete ${user.name}?`)) return
  try {
    await deleteUser(user.id)
    await load()
  } catch (e) {
    alert(e.response?.data?.message || 'Could not delete user.')
  }
}

onMounted(load)
</script>
