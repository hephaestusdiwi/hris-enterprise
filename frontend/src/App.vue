<script setup lang="ts">
import { RouterLink, RouterView, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const authStore = useAuthStore()

async function handleLogout() {
  await authStore.logout()
  router.push('/login')
}
</script>

<template>
  <header class="bg-slate-900 text-white shadow-md">
    <nav class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
      <div class="flex items-center gap-6">
        <span class="text-lg font-bold">HRIS Enterprise</span>
        <RouterLink to="/" class="text-sm text-slate-300 hover:text-white">Home</RouterLink>
        <RouterLink to="/about" class="text-sm text-slate-300 hover:text-white">About</RouterLink>
      </div>

      <div v-if="authStore.isAuthenticated" class="flex items-center gap-4">
        <span class="text-sm text-slate-300">{{ authStore.user?.name }}</span>
        <button
          @click="handleLogout"
          class="rounded-md bg-slate-700 px-3 py-1.5 text-sm hover:bg-slate-600"
        >
          Logout
        </button>
      </div>
    </nav>
  </header>
  <main class="mx-auto max-w-7xl px-6 py-8">
    <RouterView />
  </main>
</template>