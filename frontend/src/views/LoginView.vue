<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

async function onSubmit() {
  error.value = ''
  loading.value = true
  try {
    await auth.login(email.value, password.value)
    router.replace(route.query.redirect || '/')
  } catch (e) {
    error.value = e.response?.status === 422
      ? 'Wrong email or password.'
      : 'Could not sign in — check your connection.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="screen" style="display: flex; flex-direction: column; justify-content: center; padding-bottom: 16px;">
    <h1 style="text-align: center; margin-bottom: 4px;">🍼 Baby Monitor</h1>
    <p class="muted" style="text-align: center; margin-bottom: 32px;">Sign in to log and view data</p>

    <form class="card" @submit.prevent="onSubmit">
      <div class="field">
        <label for="email">Email</label>
        <input id="email" v-model="email" type="email" autocomplete="username" required />
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input id="password" v-model="password" type="password" autocomplete="current-password" required />
      </div>
      <p v-if="error" style="color: var(--color-danger); font-size: 14px;">{{ error }}</p>
      <button class="btn btn-primary btn-block" type="submit" :disabled="loading">
        {{ loading ? 'Signing in…' : 'Sign in' }}
      </button>
    </form>
  </div>
</template>
