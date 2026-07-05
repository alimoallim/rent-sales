<template>
  <section>
    <PageHeader
      title="Settings"
      subtitle="Manage your account, password, and system email configuration."
      :breadcrumbs="[{ label: 'Account' }, { label: 'Settings' }]"
    />

    <div class="mb-6 flex flex-wrap gap-2 border-b border-zinc-200 pb-3 dark:border-zinc-700">
      <button
        v-for="tab in visibleTabs"
        :key="tab.id"
        type="button"
        class="rounded-lg px-3 py-1.5 text-sm font-medium transition-colors"
        :class="activeTab === tab.id
          ? 'bg-indigo-600 text-white shadow-sm'
          : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800'"
        @click="activeTab = tab.id"
      >
        {{ tab.label }}
      </button>
    </div>

    <div v-if="activeTab === 'profile'" class="card-surface max-w-2xl p-6">
      <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Profile</h2>
      <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
        Update your display name and recovery email address.
      </p>

      <form class="mt-6 space-y-4" @submit.prevent="saveProfile">
        <FormField label="Full name" required>
          <input v-model="profileForm.name" class="input-field" required />
        </FormField>

        <FormField label="Username">
          <input :value="auth.user?.username ?? ''" class="input-field bg-zinc-50 dark:bg-zinc-800/60" disabled />
        </FormField>

        <FormField label="Email address" hint="Used for password recovery notifications.">
          <input v-model="profileForm.email" type="email" class="input-field" autocomplete="email" />
        </FormField>

        <p v-if="profileError" class="alert-error">{{ profileError }}</p>

        <div class="flex justify-end">
          <button type="submit" class="btn-primary" :disabled="profileSaving">
            {{ profileSaving ? 'Saving…' : 'Save profile' }}
          </button>
        </div>
      </form>
    </div>

    <div v-else-if="activeTab === 'password'" class="card-surface max-w-2xl p-6">
      <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Change password</h2>
      <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
        Choose a strong password that you do not use elsewhere.
      </p>

      <form class="mt-6 space-y-4" @submit.prevent="savePassword">
        <FormField label="Current password" required>
          <PasswordInput
            v-model="passwordForm.current_password"
            autocomplete="current-password"
            required
          />
        </FormField>

        <FormField label="New password" required>
          <PasswordInput
            v-model="passwordForm.password"
            v-model:visible="showPasswords"
            autocomplete="new-password"
            :invalid="passwordTouched && !isStrongPassword(passwordForm.password)"
            required
            @update:model-value="passwordTouched = true"
          />
          <PasswordRequirements :password="passwordForm.password" />
        </FormField>

        <FormField label="Confirm new password" required>
          <PasswordInput
            v-model="passwordForm.password_confirmation"
            v-model:visible="showPasswords"
            autocomplete="new-password"
            :invalid="confirmTouched && !passwordsMatch"
            required
            @update:model-value="confirmTouched = true"
          />
        </FormField>

        <p
          v-if="confirmTouched && passwordForm.password_confirmation"
          class="flex items-center gap-1.5 text-sm"
          :class="passwordsMatch ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'"
          role="status"
        >
          {{ passwordsMatch ? 'Passwords match' : 'Passwords do not match' }}
        </p>

        <p v-if="passwordError" class="alert-error">{{ passwordError }}</p>

        <div class="flex justify-end">
          <button type="submit" class="btn-primary" :disabled="passwordSaving || !canSavePassword">
            {{ passwordSaving ? 'Updating…' : 'Update password' }}
          </button>
        </div>
      </form>
    </div>

    <div v-else-if="activeTab === 'system'" class="space-y-6">
      <div class="card-surface max-w-3xl p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Email delivery</h2>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
              SMTP settings are managed in the server environment (.env). Use this panel to verify configuration.
            </p>
          </div>
          <StatusBadge
            :variant="systemSettings?.mail?.is_configured ? 'success' : 'warning'"
            :label="systemSettings?.mail?.is_configured ? 'Configured' : 'Not configured'"
          />
        </div>

        <dl v-if="systemSettings" class="mt-6 grid gap-4 sm:grid-cols-2">
          <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">Mail driver</dt>
            <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ systemSettings.mail.driver }}</dd>
          </div>
          <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">From address</dt>
            <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ systemSettings.mail.from_address || '—' }}</dd>
          </div>
          <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">SMTP host</dt>
            <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ systemSettings.mail.host || '—' }}</dd>
          </div>
          <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">SMTP port</dt>
            <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ systemSettings.mail.port || '—' }}</dd>
          </div>
          <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">SMTP username</dt>
            <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ systemSettings.mail.username || '—' }}</dd>
          </div>
          <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">Reset code TTL</dt>
            <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ systemSettings.password_reset.code_ttl_minutes }} minutes</dd>
          </div>
        </dl>

        <p v-else-if="systemLoading" class="mt-6 text-sm text-zinc-500">Loading system settings…</p>
        <p v-else-if="systemError" class="alert-error mt-6">{{ systemError }}</p>

        <p v-if="systemSettings" class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
          If test email fails with <strong>535 Incorrect authentication data</strong>, the SMTP username/password in server <code class="text-xs">.env</code> is wrong for that host.
          cPanel mail uses <code class="text-xs">mail.rasulmart.com</code>; Google Workspace uses <code class="text-xs">smtp.gmail.com</code> with an app password.
          After editing <code class="text-xs">.env</code>, run <code class="text-xs">php artisan config:clear</code> on the server.
        </p>
      </div>

      <div class="card-surface max-w-3xl p-6">
        <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Send test email</h3>
        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
          Confirm that outgoing mail works before users rely on password recovery.
        </p>

        <form class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="submitTestEmail">
          <FormField label="Recipient email" class="flex-1" required>
            <input v-model="testEmail" type="email" class="input-field" required />
          </FormField>
          <button type="submit" class="btn-secondary shrink-0" :disabled="testSending">
            {{ testSending ? 'Sending…' : 'Send test' }}
          </button>
        </form>

        <p v-if="testError" class="alert-error mt-4">{{ testError }}</p>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import PageHeader from '../components/PageHeader.vue'
import FormField from '../components/ui/FormField.vue'
import PasswordInput from '../components/ui/PasswordInput.vue'
import PasswordRequirements from '../components/ui/PasswordRequirements.vue'
import StatusBadge from '../components/ui/StatusBadge.vue'
import { fetchSystemSettings, sendTestEmail, updatePassword, updateProfile } from '../api/auth'
import { useToast } from '../composables/useToast'
import { firstFailedPasswordRequirement, isStrongPassword } from '../utils/passwordPolicy'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const toast = useToast()

const activeTab = ref('profile')
const profileSaving = ref(false)
const passwordSaving = ref(false)
const profileError = ref('')
const passwordError = ref('')
const passwordTouched = ref(false)
const confirmTouched = ref(false)
const showPasswords = ref(false)

const profileForm = reactive({
  name: '',
  email: '',
})

const passwordForm = reactive({
  current_password: '',
  password: '',
  password_confirmation: '',
})

const systemSettings = ref(null)
const systemLoading = ref(false)
const systemError = ref('')
const testEmail = ref('')
const testSending = ref(false)
const testError = ref('')

const visibleTabs = computed(() => {
  const tabs = [
    { id: 'profile', label: 'Profile' },
    { id: 'password', label: 'Password' },
  ]

  if (auth.isAdmin) {
    tabs.push({ id: 'system', label: 'Email system' })
  }

  return tabs
})

const passwordsMatch = computed(() => passwordForm.password === passwordForm.password_confirmation)

const canSavePassword = computed(() => (
  passwordForm.current_password.length > 0
  && passwordForm.password.length > 0
  && passwordForm.password_confirmation.length > 0
  && passwordsMatch.value
  && isStrongPassword(passwordForm.password)
))

watch(
  () => auth.user,
  (user) => {
    if (!user) return
    profileForm.name = user.name ?? ''
    profileForm.email = user.email ?? ''
    if (!testEmail.value && user.email) {
      testEmail.value = user.email
    }
  },
  { immediate: true },
)

function validationMessage(error, fallback) {
  const errors = error.response?.data?.errors
  if (errors) {
    const first = Object.values(errors).flat()[0]
    if (typeof first === 'string') return first
  }

  return error.response?.data?.message || fallback
}

async function saveProfile() {
  profileError.value = ''
  profileSaving.value = true

  try {
    const profile = await updateProfile({
      name: profileForm.name.trim(),
      email: profileForm.email.trim() || null,
    })
    auth.user = profile
    toast.success('Profile updated.')
  } catch (error) {
    profileError.value = validationMessage(error, 'Unable to update profile.')
  } finally {
    profileSaving.value = false
  }
}

async function savePassword() {
  passwordError.value = ''

  if (!canSavePassword.value) {
    passwordTouched.value = true
    confirmTouched.value = true
    const failedRule = firstFailedPasswordRequirement(passwordForm.password)
    if (failedRule) {
      passwordError.value = `Password must include: ${failedRule.label.toLowerCase()}.`
    } else if (!passwordsMatch.value) {
      passwordError.value = 'Passwords do not match.'
    } else {
      passwordError.value = 'Complete all password fields with a strong password.'
    }
    return
  }

  passwordSaving.value = true

  try {
    await updatePassword({ ...passwordForm })
    passwordForm.current_password = ''
    passwordForm.password = ''
    passwordForm.password_confirmation = ''
    passwordTouched.value = false
    confirmTouched.value = false
    toast.success('Password updated.')
  } catch (error) {
    passwordError.value = validationMessage(error, 'Unable to update password.')
  } finally {
    passwordSaving.value = false
  }
}

async function loadSystemSettings() {
  if (!auth.isAdmin) return

  systemLoading.value = true
  systemError.value = ''

  try {
    systemSettings.value = await fetchSystemSettings()
  } catch (error) {
    systemError.value = validationMessage(error, 'Unable to load system settings.')
  } finally {
    systemLoading.value = false
  }
}

async function submitTestEmail() {
  testError.value = ''
  testSending.value = true

  try {
    const response = await sendTestEmail(testEmail.value.trim())
    toast.success(response.message || 'Test email sent.')
  } catch (error) {
    testError.value = validationMessage(error, 'Unable to send test email.')
  } finally {
    testSending.value = false
  }
}

watch(activeTab, (tab) => {
  if (tab === 'system' && !systemSettings.value && !systemLoading.value) {
    loadSystemSettings()
  }
})

onMounted(() => {
  if (auth.isAdmin && activeTab.value === 'system') {
    loadSystemSettings()
  }
})
</script>
