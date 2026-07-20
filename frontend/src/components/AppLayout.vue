<script setup lang="ts">
import { useRouter } from 'vue-router'
import { ChevronDown, Sparkles, Plus, Search, Bell, LayoutGrid } from 'lucide-vue-next'
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
  <div class="flex h-screen flex-col bg-slate-50">
    <!-- Top bar: full width, di atas sidebar & konten -->
    <header class="flex h-14 shrink-0 items-center justify-between border-b border-slate-100 bg-white px-4">
      <div class="flex items-center gap-2">
        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary text-sm font-bold text-white">
          M
        </div>
        <button class="flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-600 transition-colors hover:bg-slate-50">
          Mindway HRIS
          <ChevronDown class="h-3.5 w-3.5" :stroke-width="2" />
        </button>
      </div>

      <div class="flex items-center gap-1.5">
        <button class="hidden items-center gap-1.5 rounded-full border border-primary/30 bg-primary-soft px-3 py-1.5 text-xs font-medium text-primary-dark transition-colors hover:bg-primary/10 sm:flex">
          <Sparkles class="h-3.5 w-3.5" :stroke-width="2" />
          Ringkas Data
        </button>

        <button class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-50 hover:text-slate-600">
          <Plus class="h-[18px] w-[18px]" :stroke-width="1.75" />
        </button>
        <button class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-50 hover:text-slate-600">
          <Search class="h-[18px] w-[18px]" :stroke-width="1.75" />
        </button>
        <button class="relative rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-50 hover:text-slate-600">
          <Bell class="h-[18px] w-[18px]" :stroke-width="1.75" />
          <span class="absolute right-1.5 top-1.5 h-1.5 w-1.5 rounded-full bg-red-500"></span>
        </button>
        <button class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-50 hover:text-slate-600">
          <LayoutGrid class="h-[18px] w-[18px]" :stroke-width="1.75" />
        </button>

        <div class="mx-1 h-6 w-px bg-slate-100"></div>

        <div v-if="authStore.isAuthenticated" class="flex items-center gap-2">
          <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary-soft text-xs font-semibold text-primary-dark">
            {{ initials(authStore.user?.name) }}
          </div>
          <span class="hidden text-sm font-medium text-slate-700 md:inline">{{ authStore.user?.name }}</span>
          <button
            @click="handleLogout"
            class="rounded-lg px-2.5 py-1.5 text-xs text-slate-500 transition-colors hover:bg-slate-50 hover:text-slate-700"
          >
            Logout
          </button>
        </div>
      </div>
    </header>

    <!-- Body: sidebar + content -->
    <div class="flex flex-1 overflow-hidden">
      <AppSidebar />

      <main class="flex-1 overflow-y-auto px-8 py-8">
        <slot />
      </main>
    </div>
  </div>
</template>