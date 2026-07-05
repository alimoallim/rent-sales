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
            {{ step === 1
              ? 'Enter your registered email and we will send a verification code.'
              : 'Enter the 6-digit code we sent to your email address.' }}
          </p>
        </div>
      </div>
    </aside>

    <div class="login-page-form-panel">
      <div class="login-page-form-card">
        <PasswordResetStepper :current-step="step" />

        <div class="login-page-form-header">
          <h1 class="login-page-form-title">
            {{ step === 1 ? 'Forgot password' : 'Verify code' }}
          </h1>
          <p class="login-page-form-subtitle">
            <template v-if="step === 1">
              Enter the email linked to your account.
            </template>
            <template v-else>
              We sent a code to <strong class="font-medium text-zinc-800 dark:text-zinc-200">{{ email }}</strong>.
              Codes expire after 15 minutes.
            </template>
          </p>
        </div>

        <form v-if="step === 1" class="space-y-4" @submit.prevent="submitEmail">
          <FormField label="Email address" required>
            <input
              id="email"
              v-model="email"
              type="email"
              autocomplete="email"
              class="input-field"
              required
            />
          </FormField>

          <p v-if="error" class="alert-error">{{ error }}</p>

          <button type="submit" class="btn-primary w-full" :disabled="loading">
            {{ loading ? 'Sending…' : 'Continue' }}
          </button>
        </form>

        <form v-else class="space-y-5" @submit.prevent="submitCode">
          <OtpCodeInput
            ref="otpInput"
            v-model="code"
            label="Verification code"
            hint="You can paste the full code from your email."
            :error="codeError"
            :disabled="loading"
          />

          <p v-if="error && !codeError" class="alert-error">{{ error }}</p>

          <button type="submit" class="btn-primary w-full" :disabled="loading || code.length !== 6">
            {{ loading ? 'Verifying…' : 'Verify and continue' }}
          </button>

          <div class="flex flex-col gap-2 text-center text-sm text-zinc-500 dark:text-zinc-400">
            <button
              type="button"
              class="font-medium text-indigo-600 hover:text-indigo-500 disabled:opacity-50 dark:text-indigo-400"
              :disabled="resendLoading || resendCooldown > 0"
              @click="resendCode"
            >
              {{ resendCooldown > 0 ? `Resend code in ${resendCooldown}s` : (resendLoading ? 'Sending…' : 'Resend code') }}
            </button>
            <button
              type="button"
              class="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
              @click="goBackToEmail"
            >
              Use a different email
            </button>
          </div>
        </form>

        <p class="mt-6 text-center text-sm text-zinc-500 dark:text-zinc-400">
          <RouterLink class="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400" :to="{ name: 'login' }">
            Back to sign in
          </RouterLink>
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import FormField from '../components/ui/FormField.vue'
import ThemeToggle from '../components/ui/ThemeToggle.vue'
import OtpCodeInput from '../components/auth/OtpCodeInput.vue'
import PasswordResetStepper from '../components/auth/PasswordResetStepper.vue'
import { forgotPassword, verifyResetCode } from '../api/auth'
import { ensureCsrfCookie } from '../api/client'
import { saveResetSession } from '../utils/passwordReset'

const route = useRoute()
const router = useRouter()

const step = ref(1)
const email = ref('')
const code = ref('')
const error = ref('')
const codeError = ref('')
const loading = ref(false)
const resendLoading = ref(false)
const resendCooldown = ref(0)
const otpInput = ref(null)

let cooldownTimer = null

onMounted(() => {
  const queryEmail = route.query.email
  if (typeof queryEmail === 'string' && queryEmail.length > 0) {
    email.value = queryEmail
  }
})

onBeforeUnmount(() => {
  if (cooldownTimer) window.clearInterval(cooldownTimer)
})

function fieldError(e, field) {
  const messages = e.response?.data?.errors?.[field]
  return Array.isArray(messages) ? messages[0] : null
}

function startResendCooldown(seconds = 60) {
  resendCooldown.value = seconds
  if (cooldownTimer) window.clearInterval(cooldownTimer)
  cooldownTimer = window.setInterval(() => {
    resendCooldown.value -= 1
    if (resendCooldown.value <= 0) {
      window.clearInterval(cooldownTimer)
      cooldownTimer = null
    }
  }, 1000)
}

async function submitEmail() {
  error.value = ''
  loading.value = true

  try {
    await ensureCsrfCookie()
    await forgotPassword(email.value.trim())
    step.value = 2
    code.value = ''
    codeError.value = ''
    startResendCooldown()
    await nextTick()
    otpInput.value?.focusFirst()
  } catch (e) {
    error.value = e.response?.data?.message || 'Unable to send verification code. Try again.'
  } finally {
    loading.value = false
  }
}

async function submitCode() {
  if (code.value.length !== 6) {
    codeError.value = 'Enter the full 6-digit code.'
    return
  }

  error.value = ''
  codeError.value = ''
  loading.value = true

  try {
    await ensureCsrfCookie()
    await verifyResetCode({
      email: email.value.trim(),
      code: code.value.trim(),
    })

    saveResetSession({
      email: email.value.trim(),
      code: code.value.trim(),
    })

    await router.push({ name: 'reset-password' })
  } catch (e) {
    const message = fieldError(e, 'code') || e.response?.data?.message || 'Unable to verify code. Try again.'
    codeError.value = message
  } finally {
    loading.value = false
  }
}

async function resendCode() {
  if (resendCooldown.value > 0 || resendLoading.value) return

  error.value = ''
  codeError.value = ''
  resendLoading.value = true

  try {
    await ensureCsrfCookie()
    await forgotPassword(email.value.trim())
    code.value = ''
    startResendCooldown()
    await nextTick()
    otpInput.value?.focusFirst()
  } catch (e) {
    error.value = e.response?.data?.message || 'Unable to resend code. Try again.'
  } finally {
    resendLoading.value = false
  }
}

function goBackToEmail() {
  step.value = 1
  code.value = ''
  error.value = ''
  codeError.value = ''
}
</script>
