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
            Property management for rental operations and sales — charges, payments, utilities, and reporting in one place.
          </p>
          <ul class="login-page-brand-features">
            <li class="login-page-brand-feature">
              <span class="login-page-brand-feature-icon" aria-hidden="true">✓</span>
              Rental billing, meter readings, and tenant ledger
            </li>
            <li class="login-page-brand-feature">
              <span class="login-page-brand-feature-icon" aria-hidden="true">✓</span>
              Sales pipeline, client payments, and inventory
            </li>
            <li class="login-page-brand-feature">
              <span class="login-page-brand-feature-icon" aria-hidden="true">✓</span>
              Role-based access for admin, rental, and sales teams
            </li>
          </ul>
        </div>
        <p class="text-xs text-indigo-200/70">© {{ year }} Rasul Mart</p>
      </div>
    </aside>

    <div class="login-page-form-panel">
      <div class="login-page-form-card">
        <div class="login-page-form-header">
          <div class="mb-6 flex items-center gap-3 lg:hidden">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-sm">
              <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5M4.5 21V9.75A2.25 2.25 0 0 1 6.75 7.5h10.5a2.25 2.25 0 0 1 2.25 2.25V21M9.75 21v-4.5a1.5 1.5 0 0 1 1.5-1.5h1.5a1.5 1.5 0 0 1 1.5 1.5V21" />
              </svg>
            </span>
            <div>
              <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Rent & Sales</p>
              <p class="text-xs text-zinc-500 dark:text-zinc-400">Management platform</p>
            </div>
          </div>
          <h1 class="login-page-form-title">Welcome back</h1>
          <p class="login-page-form-subtitle">Sign in to your account to continue</p>
        </div>

        <form class="space-y-4" @submit.prevent="submit">
          <FormField label="Username" required>
            <input
              id="username"
              v-model="username"
              type="text"
              autocomplete="username"
              class="input-field"
              required
            />
          </FormField>

          <FormField label="Password" required>
            <input
              id="password"
              v-model="password"
              type="password"
              autocomplete="current-password"
              class="input-field"
              required
            />
          </FormField>

          <div class="flex justify-end">
            <RouterLink
              class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
              :to="{ name: 'forgot-password' }"
            >
              Forgot password?
            </RouterLink>
          </div>

          <p v-if="notice" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-200">
            {{ notice }}
          </p>

          <p v-if="error" class="alert-error">{{ error }}</p>

          <button type="submit" class="btn-primary w-full" :disabled="loading">
            {{ loading ? 'Signing in…' : 'Sign in' }}
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import FormField from '../components/ui/FormField.vue'
import ThemeToggle from '../components/ui/ThemeToggle.vue'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const username = ref('')
const password = ref('')
const error = ref('')
const notice = ref('')
const loading = ref(false)
const year = new Date().getFullYear()

onMounted(() => {
  const message = route.query.message
  if (route.query.reset === '1' && typeof message === 'string' && message.length > 0) {
    notice.value = message
  }
})

async function submit() {
  error.value = ''
  loading.value = true

  try {
    await auth.login(username.value, password.value)
    const redirect = route.query.redirect
    if (typeof redirect === 'string' && redirect.length > 0) {
      await router.push(redirect)
      return
    }

    await router.push(auth.defaultRoute())
  } catch (e) {
    error.value = e.response?.data?.message || 'Invalid username or password.'
  } finally {
    loading.value = false
  }
}
</script>
