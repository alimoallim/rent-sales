<template>
  <section>
    <PageHeader
      title="Users"
      subtitle="Manage staff accounts and module access."
      :breadcrumbs="[{ label: 'Administration' }, { label: 'Users' }]"
    >
      <template #actions>
        <button type="button" class="btn-primary w-full sm:w-auto" @click="openCreate">Add user</button>
      </template>
    </PageHeader>

    <DataTable
      v-model:search="search"
      server-side
      :items="items"
      :columns="columns"
      :loading="loading"
      :pagination="pagination"
      empty-message="No users yet."
      @search="onSearchChange"
      @page-change="goToPage"
      @per-page-change="setPerPage"
    >
      <template #cell-role="{ item }">
        {{ roleLabels[item.role] ?? item.role }}
      </template>
      <template #cell-status="{ item }">
        <StatusBadge :variant="item.status === 'active' ? 'success' : 'neutral'" :label="item.status === 'active' ? 'Active' : 'Inactive'" />
      </template>
      <template #cell-access="{ item }">
        {{ formatAccess(item) }}
      </template>
      <template #actions="{ item }">
        <RowActionButton icon="edit" label="Edit" @click="openEdit(item)" />
        <RowActionButton
          v-if="item.id !== currentUserId"
          icon="delete"
          label="Delete"
          variant="danger"
          @click="remove(item)"
        />
      </template>
    </DataTable>

    <AppDialog
      v-model:open="showForm"
      :title="editing ? 'Edit user' : 'Create user'"
      :subtitle="editing ? 'Update account details, access, or password.' : 'Add a staff account with role-based module access.'"
      size="lg"
      :close-on-backdrop="false"
    >
      <form class="mx-auto w-full max-w-[560px] space-y-8 py-1" novalidate @submit.prevent="save">
        <section>
          <h4 class="text-[11px] font-semibold uppercase tracking-[0.08em] text-zinc-400 dark:text-zinc-500">
            Account details
          </h4>

          <div class="mt-3 space-y-4">
            <FormField label="Full name" required>
              <input v-model="form.name" class="input-field" required autocomplete="name" placeholder="e.g. Ali Hassan" />
            </FormField>

            <FormField label="Username" required>
              <input
                v-model="form.username"
                class="input-field"
                required
                autocomplete="off"
                autocapitalize="off"
                spellcheck="false"
                placeholder="Used to sign in"
              />
            </FormField>

            <FormField label="Email address" hint="Used for password recovery.">
              <input v-model="form.email" type="email" class="input-field" autocomplete="off" placeholder="name@example.com" />
            </FormField>
          </div>
        </section>

        <section>
          <h4 class="text-[11px] font-semibold uppercase tracking-[0.08em] text-zinc-400 dark:text-zinc-500">
            Access &amp; status
          </h4>

          <div class="mt-3 space-y-4">
            <div>
              <FormField label="Role" required>
                <select v-model="form.role" class="input-field" required>
                  <option value="admin">Administrator</option>
                  <option value="rental">Rental staff</option>
                  <option value="sales">Sales staff</option>
                </select>
              </FormField>

              <p class="mt-2 rounded-lg bg-zinc-50 px-3 py-2 text-xs leading-relaxed text-zinc-500 ring-1 ring-inset ring-zinc-200/70 dark:bg-zinc-800/50 dark:text-zinc-400 dark:ring-zinc-700/60">
                {{ roleAccessHint }}
              </p>

              <label
                v-if="form.role === 'rental'"
                class="mt-3 flex cursor-pointer items-start gap-2.5"
              >
                <input
                  v-model="form.is_manager"
                  type="checkbox"
                  class="mt-0.5 h-4 w-4 rounded border-zinc-300 text-indigo-600 focus:ring-2 focus:ring-indigo-500/40 dark:border-zinc-500 dark:bg-zinc-900"
                />
                <span class="min-w-0">
                  <span class="block text-sm font-medium text-zinc-900 dark:text-zinc-100">Rental manager</span>
                  <span class="mt-0.5 block text-xs text-zinc-500 dark:text-zinc-400">Can approve charge batches on behalf of the rental team.</span>
                </span>
              </label>
            </div>

            <FormField label="Status">
              <select v-model="form.status" class="input-field">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </FormField>
          </div>
        </section>

        <section>
          <h4 class="text-[11px] font-semibold uppercase tracking-[0.08em] text-zinc-400 dark:text-zinc-500">
            Security
          </h4>

          <div class="mt-3">
            <button
              v-if="editing && !showPasswordFields"
              type="button"
              class="text-sm font-medium text-indigo-600 transition-colors hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
              @click="showPasswordFields = true"
            >
              Change password…
            </button>

            <template v-else>
              <FormField :label="editing ? 'New password' : 'Password'" :required="!editing">
                <div class="flex items-start gap-2">
                  <PasswordInput
                    v-model="form.password"
                    v-model:visible="showPasswords"
                    class="min-w-0 flex-1"
                    autocomplete="new-password"
                    :required="!editing"
                    :invalid="passwordTouched && !!form.password && !isStrongPassword(form.password)"
                    @update:model-value="passwordTouched = true"
                  />
                  <button
                    type="button"
                    class="btn-secondary mt-1 shrink-0 whitespace-nowrap"
                    title="Generate a secure password"
                    @click="generatePassword"
                  >
                    Generate
                  </button>
                </div>
              </FormField>

              <ul class="mt-2.5 space-y-1" aria-label="Password requirements">
                <li
                  v-for="requirement in passwordChecklist"
                  :key="requirement.id"
                  class="flex items-center gap-2 text-xs transition-colors"
                  :class="requirement.passed ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-400 dark:text-zinc-500'"
                >
                  <svg v-if="requirement.passed" class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                  </svg>
                  <span v-else class="flex h-3 w-3 shrink-0 items-center justify-center" aria-hidden="true">
                    <span class="h-1 w-1 rounded-full bg-current" />
                  </span>
                  <span>{{ requirement.label }}</span>
                </li>
              </ul>
            </template>
          </div>
        </section>

        <p
          v-if="error"
          class="rounded-lg bg-red-50 px-3 py-2.5 text-sm text-red-700 ring-1 ring-inset ring-red-200 dark:bg-red-950/40 dark:text-red-300 dark:ring-red-900/50"
          role="alert"
        >
          {{ error }}
        </p>
      </form>

      <template #footer>
        <button type="button" class="btn-secondary w-full sm:w-auto" :disabled="saving" @click="closeForm">Cancel</button>
        <button
          type="button"
          class="btn-primary inline-flex w-full items-center justify-center gap-2 sm:w-auto"
          :disabled="saving || !canSaveUser"
          @click="save"
        >
          <svg
            v-if="saving"
            class="h-4 w-4 animate-spin"
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
          >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z" />
          </svg>
          {{ saving ? 'Saving…' : (editing ? 'Save changes' : 'Create user') }}
        </button>
      </template>
    </AppDialog>
  </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import PageHeader from '../../components/PageHeader.vue'
import AppDialog from '../../components/ui/AppDialog.vue'
import FormField from '../../components/ui/FormField.vue'
import PasswordInput from '../../components/ui/PasswordInput.vue'
import StatusBadge from '../../components/ui/StatusBadge.vue'
import DataTable from '../../components/data/DataTable.vue'
import RowActionButton from '../../components/ui/RowActionButton.vue'
import { useConfirm } from '../../composables/useConfirm'
import { usePaginatedList } from '../../composables/usePaginatedList'
import { useToast } from '../../composables/useToast'
import {
  evaluatePasswordRequirements,
  firstFailedPasswordRequirement,
  isStrongPassword,
  passwordRequirements,
} from '../../utils/passwordPolicy'
import { useAuthStore } from '../../stores/auth'
import { createUser, deleteUser, fetchUsers, updateUser } from '../../api/users'

const { confirm } = useConfirm()
const toast = useToast()
const auth = useAuthStore()

const { items, loading, search, pagination, load, reload, goToPage, setPerPage, onSearchChange } = usePaginatedList(
  (params) => fetchUsers(params),
)
const saving = ref(false)
const showForm = ref(false)
const editing = ref(null)
const error = ref('')
const passwordTouched = ref(false)
const showPasswords = ref(false)
const showPasswordFields = ref(false)
const form = reactive({
  name: '',
  username: '',
  email: '',
  role: 'rental',
  status: 'active',
  is_manager: false,
  password: '',
})

const currentUserId = computed(() => auth.user?.id)

const passwordChecklist = computed(() => {
  if (!form.password) {
    return passwordRequirements.map((requirement) => ({ ...requirement, passed: false }))
  }
  return evaluatePasswordRequirements(form.password)
})

const canSaveUser = computed(() => {
  if (!form.name.trim() || !form.username.trim()) return false
  if (!editing.value && !form.password) return false
  if (form.password) {
    return isStrongPassword(form.password)
  }
  return true
})

const roleAccessHint = computed(() => {
  if (form.role === 'admin') return 'Full access to rental, sales, and administration.'
  if (form.role === 'sales') return 'Sales module only — buildings, clients, payments, and reports.'
  return 'Rental module only — tenants, charges, utilities, and reports.'
})

const roleLabels = {
  admin: 'Administrator',
  rental: 'Rental',
  sales: 'Sales',
}

const columns = [
  { key: 'name', label: 'Name', cardTitle: true },
  { key: 'username', label: 'Username', mobileCard: true },
  { key: 'role', label: 'Role', mobileCard: true },
  { key: 'status', label: 'Status' },
  { key: 'access', label: 'Module access' },
]

function formatAccess(row) {
  const parts = []
  if (row.can_access_rental) parts.push('Rental')
  if (row.can_access_sales) parts.push('Sales')
  return parts.join(', ') || '—'
}

function resetForm() {
  form.name = ''
  form.username = ''
  form.email = ''
  form.role = 'rental'
  form.status = 'active'
  form.is_manager = false
  form.password = ''
  passwordTouched.value = false
  showPasswords.value = false
  showPasswordFields.value = false
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
  passwordTouched.value = false
  showPasswords.value = false
  showPasswordFields.value = false
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
    payload.password_confirmation = form.password
  }

  return payload
}

function generatePassword() {
  const uppercase = 'ABCDEFGHJKLMNPQRSTUVWXYZ'
  const lowercase = 'abcdefghijkmnopqrstuvwxyz'
  const numbers = '23456789'
  const symbols = '!@#$%&*?'
  const all = uppercase + lowercase + numbers + symbols

  const randomFrom = (chars) => chars[crypto.getRandomValues(new Uint32Array(1))[0] % chars.length]

  const chars = [
    randomFrom(uppercase),
    randomFrom(lowercase),
    randomFrom(numbers),
    randomFrom(symbols),
  ]
  while (chars.length < 14) {
    chars.push(randomFrom(all))
  }

  for (let i = chars.length - 1; i > 0; i -= 1) {
    const j = crypto.getRandomValues(new Uint32Array(1))[0] % (i + 1)
    ;[chars[i], chars[j]] = [chars[j], chars[i]]
  }

  form.password = chars.join('')
  passwordTouched.value = true
  showPasswords.value = true
}

async function save() {
  error.value = ''

  if (form.password) {
    passwordTouched.value = true
    const failedRule = firstFailedPasswordRequirement(form.password)
    if (failedRule) {
      error.value = `Password must include: ${failedRule.label.toLowerCase()}.`
      return
    }
  } else if (!editing.value) {
    error.value = 'Password is required for new users.'
    return
  }

  saving.value = true
  try {
    const payload = buildPayload()
    if (editing.value) {
      await updateUser(editing.value.id, payload)
      toast.success('User updated.')
    } else {
      await createUser(payload)
      toast.success('User created.')
    }
    closeForm()
    await reload()
  } catch (e) {
    const validation = e.response?.data?.errors
    if (validation) {
      error.value = Object.values(validation).flat().join(' ')
    } else {
      error.value = e.response?.data?.message || 'Could not save user.'
    }
  } finally {
    saving.value = false
  }
}

async function remove(user) {
  const ok = await confirm({
    title: 'Delete user',
    message: `Delete ${user.name}?`,
    confirmLabel: 'Delete',
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deleteUser(user.id)
    toast.success('User deleted.')
    await reload()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Could not delete user.')
  }
}

onMounted(load)
</script>
