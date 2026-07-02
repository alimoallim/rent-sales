<template>
  <div class="login-page">
    <div class="login-page-toolbar">
      <ThemeToggle />
    </div>
    <div class="w-full max-w-sm card-surface p-6 shadow-md">
      <div class="mb-6 border-b border-zinc-200 pb-4 dark:border-zinc-700">
        <h1 class="page-title">Sign in</h1>
        <p class="page-subtitle">Rent & Sales Management Platform</p>
      </div>

      <form class="space-y-3" @submit.prevent="submit">
        <div>
          <label class="label-field" for="username">Username</label>
          <input
            id="username"
            v-model="username"
            type="text"
            autocomplete="username"
            class="input-field"
            required
          />
        </div>

        <div>
          <label class="label-field" for="password">Password</label>
          <input
            id="password"
            v-model="password"
            type="password"
            autocomplete="current-password"
            class="input-field"
            required
          />
        </div>

        <p v-if="error" class="alert-error">{{ error }}</p>

        <button type="submit" class="btn-primary w-full" :disabled="loading">
          {{ loading ? 'Signing in…' : 'Sign in' }}
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import ThemeToggle from '../components/ui/ThemeToggle.vue'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const username = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

async function submit() {
  error.value = ''
  loading.value = true

  try {
    const user = await auth.login(username.value, password.value)
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
