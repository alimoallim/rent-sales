<template>
  <div class="login-page">
    <div class="login-page-toolbar">
      <ThemeToggle />
    </div>

    <aside class="login-page-brand" aria-hidden="true">
      <div class="login-page-brand-inner">
        <div>
          <div class="login-page-brand-logo">
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5M4.5 21V9.75A2.25 2.25 0 0 1 6.75 7.5h10.5a2.25 2.25 0 0 1 2.25 2.25V21M9.75 21v-4.5a1.5 1.5 0 0 1 1.5-1.5h1.5a1.5 1.5 0 0 1 1.5 1.5V21" />
            </svg>
          </div>
          <h2 class="login-page-brand-title mt-8">Rent & Sales</h2>
          <p class="login-page-brand-tagline">
            Choose a strong new password for your account.
          </p>
        </div>
      </div>
    </aside>

    <div class="login-page-form-panel">
      <div class="login-page-form-card">
        <PasswordResetStepper :current-step="3" />

        <div class="login-page-form-header">
          <h1 class="login-page-form-title">Set new password</h1>
          <p class="login-page-form-subtitle">
            Create a new password for <strong class="font-medium text-zinc-800 dark:text-zinc-200">{{ email }}</strong>.
          </p>
        </div>

        <form class="space-y-4" @submit.prevent="submit">
          <FormField label="New password" required>
            <PasswordInput
              id="password"
              v-model="password"
              v-model:visible="showPasswords"
              autocomplete="new-password"
              :invalid="passwordTouched && !isStrongPassword(password)"
              required
              @update:model-value="passwordTouched = true"
            />
            <PasswordRequirements :password="password" />
          </FormField>

          <FormField label="Confirm password" required>
            <PasswordInput
              id="password_confirmation"
              v-model="passwordConfirmation"
              v-model:visible="showPasswords"
              autocomplete="new-password"
              :invalid="confirmTouched && !passwordsMatch"
              required
              @update:model-value="confirmTouched = true"
            />
          </FormField>

          <p
            v-if="confirmTouched && passwordConfirmation"
            class="flex items-center gap-1.5 text-sm"
            :class="passwordsMatch ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'"
            role="status"
          >
            <svg v-if="passwordsMatch" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
            </svg>
            <svg v-else class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
            {{ passwordsMatch ? 'Passwords match' : 'Passwords do not match' }}
          </p>

          <p v-if="error" class="alert-error">{{ error }}</p>

          <button type="submit" class="btn-primary w-full" :disabled="loading || !canSubmit">
            {{ loading ? 'Saving…' : 'Update password' }}
          </button>
        </form>

        <p class="mt-6 text-center text-sm text-zinc-500 dark:text-zinc-400">
          <RouterLink class="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400" :to="{ name: 'forgot-password' }">
            Start over
          </RouterLink>
          <span class="mx-2">·</span>
          <RouterLink class="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400" :to="{ name: 'login' }">
            Back to sign in
          </RouterLink>
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import FormField from '../components/ui/FormField.vue'
import PasswordInput from '../components/ui/PasswordInput.vue'
import PasswordRequirements from '../components/ui/PasswordRequirements.vue'
import ThemeToggle from '../components/ui/ThemeToggle.vue'
import PasswordResetStepper from '../components/auth/PasswordResetStepper.vue'
import { resetPassword } from '../api/auth'
import { ensureCsrfCookie } from '../api/client'
import { firstFailedPasswordRequirement, isStrongPassword } from '../utils/passwordPolicy'
import { clearResetSession, loadResetSession } from '../utils/passwordReset'

const router = useRouter()

const email = ref('')
const resetCode = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const showPasswords = ref(false)
const passwordTouched = ref(false)
const confirmTouched = ref(false)
const error = ref('')
const loading = ref(false)

const passwordsMatch = computed(() => password.value === passwordConfirmation.value)

const canSubmit = computed(() => (
  password.value.length > 0
  && passwordConfirmation.value.length > 0
  && passwordsMatch.value
  && isStrongPassword(password.value)
))

onMounted(() => {
  const session = loadResetSession()
  if (!session) {
    router.replace({ name: 'forgot-password' })
    return
  }

  email.value = session.email
  resetCode.value = session.code
})

function fieldError(e, field) {
  const messages = e.response?.data?.errors?.[field]
  return Array.isArray(messages) ? messages[0] : null
}

async function submit() {
  if (!resetCode.value) {
    await router.replace({ name: 'forgot-password' })
    return
  }

  if (!canSubmit.value) {
    passwordTouched.value = true
    confirmTouched.value = true
    const failedRule = firstFailedPasswordRequirement(password.value)
    if (failedRule) {
      error.value = `Password must include: ${failedRule.label.toLowerCase()}.`
    } else if (!passwordsMatch.value) {
      error.value = 'Passwords do not match.'
    } else {
      error.value = 'Enter a strong new password.'
    }
    return
  }

  error.value = ''
  loading.value = true

  try {
    await ensureCsrfCookie()
    const response = await resetPassword({
      email: email.value,
      code: resetCode.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })

    clearResetSession()

    await router.push({
      name: 'login',
      query: { reset: '1', message: response.message },
    })
  } catch (e) {
    error.value =
      fieldError(e, 'code')
      || fieldError(e, 'password')
      || e.response?.data?.message
      || 'Unable to reset password. Try again.'

    if (fieldError(e, 'code')) {
      clearResetSession()
      await router.push({ name: 'forgot-password', query: { email: email.value } })
      return
    }
  } finally {
    loading.value = false
  }
}
</script>
