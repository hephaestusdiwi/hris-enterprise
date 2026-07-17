<script setup lang="ts">
import { useRouter } from 'vue-router'
import AppSidebar from '@/components/AppSidebar.vue'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const authStore = useAuthStore()

function initials(name: string | undefined) {
  if (!name) return ''
  return name
    .split(' ')
    .map((w) => w[0])
    .slice(0, 2)
    .join('')
    .toUpperCase()
}

async function handleLogout() {
  await authStore.logout()
  router.push('/login')
}
</script>

<template>
  <div class="flex h-screen bg-slate-50">
    <AppSidebar />

    <div class="flex flex-1 flex-col overflow-hidden">
      <header class="flex items-center justify-end border-b border-slate-100 bg-white px-8 py-3.5">
        <div v-if="authStore.isAuthenticated" class="flex items-center gap-3">
          <div
            class="flex h-8 w-8 items-center justify-center rounded-full bg-primary-soft text-xs font-semibold text-primary-dark"
          >
            {{ initials(authStore.user?.name) }}
          </div>
          <span class="text-sm font-medium text-slate-700">{{ authStore.user?.name }}</span>
          <button
            @click="handleLogout"
            class="rounded-lg px-3 py-1.5 text-sm text-slate-500 transition-colors hover:bg-slate-50 hover:text-slate-700"
          >
            Logout
          </button>
        </div>
      </header>

      <main class="flex-1 overflow-y-auto px-8 py-8">
        <slot />
      </main>
    </div>
  </div>
</template>