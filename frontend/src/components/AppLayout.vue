<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import {
  ChevronDown, Sparkles, Plus, Search, Bell, LayoutGrid, Menu, LogOut,
  LayoutDashboard, UserRound, Fingerprint, Megaphone,
} from 'lucide-vue-next'
import AppSidebar from '@/components/AppSidebar.vue'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const sidebarOpen = ref(false)
const sidebarRef = ref<InstanceType<typeof AppSidebar> | null>(null)

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

// TODO: belum ada route Profile — begitu ada, ganti jadi RouterLink kayak
// item lain di bottom nav (lihat History/Announcement di template).
function goToProfile() {
  console.warn('[BottomNav] Route Profile belum ada.')
}

// TODO: menu Finance, belum ada halamannya. Isi kalau fiturnya udah jadi.
function openFinanceMenu() {
  console.info('[BottomNav] Menu Finance belum tersedia.')
}
</script>

<template>
  <div class="flex h-screen flex-col bg-slate-50">
    <!-- Top bar -->
    <header class="flex h-14 shrink-0 items-center justify-between border-b border-slate-100 bg-white px-4">
      <div class="flex items-center gap-2">
        <button
          type="button"
          @click="sidebarOpen = true"
          class="rounded-lg p-1.5 text-slate-500 transition-colors hover:bg-slate-50 lg:hidden"
        >
          <Menu class="h-5 w-5" :stroke-width="1.75" />
        </button>

        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary text-sm font-bold text-white">
          B
        </div>
        <button class="hidden items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-600 transition-colors hover:bg-slate-50 sm:flex">
          Bakat HRIS
          <ChevronDown class="h-3.5 w-3.5" :stroke-width="2" />
        </button>
      </div>

      <div class="flex items-center gap-1.5">
        <button class="hidden items-center gap-1.5 rounded-full border border-primary/30 bg-primary-soft px-3 py-1.5 text-xs font-medium text-primary-dark transition-colors hover:bg-primary/10 sm:flex">
          <Sparkles class="h-3.5 w-3.5" :stroke-width="2" />
          Ringkas Data
        </button>

        <button class="hidden rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-50 hover:text-slate-600 sm:inline-flex">
          <Plus class="h-[18px] w-[18px]" :stroke-width="1.75" />
        </button>
        <button class="hidden rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-50 hover:text-slate-600 sm:inline-flex">
          <Search class="h-[18px] w-[18px]" :stroke-width="1.75" />
        </button>
        <button class="relative rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-50 hover:text-slate-600">
          <Bell class="h-[18px] w-[18px]" :stroke-width="1.75" />
          <span class="absolute right-1.5 top-1.5 h-1.5 w-1.5 rounded-full bg-red-500"></span>
        </button>
        <button class="hidden rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-50 hover:text-slate-600 sm:inline-flex">
          <LayoutGrid class="h-[18px] w-[18px]" :stroke-width="1.75" />
        </button>

        <div class="mx-1 hidden h-6 w-px bg-slate-100 sm:block"></div>

        <div v-if="authStore.isAuthenticated" class="flex items-center gap-1.5">
          <div
            class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary-soft text-xs font-semibold text-primary-dark"
          >
            <img
              v-if="authStore.user?.photo_url"
              :src="authStore.user.photo_url"
              :alt="authStore.user?.name ?? 'User'"
              class="h-full w-full object-cover"
            />

            <span v-else>
              {{ initials(authStore.user?.name) }}
            </span>
          </div>
          <span class="hidden text-sm font-medium text-slate-700 md:inline">{{ authStore.user?.name }}</span>

          <button
            @click="handleLogout"
            class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-50 hover:text-slate-600 sm:hidden"
          >
            <LogOut class="h-[18px] w-[18px]" :stroke-width="1.75" />
          </button>
          <button
            @click="handleLogout"
            class="hidden rounded-lg px-2.5 py-1.5 text-xs text-slate-500 transition-colors hover:bg-slate-50 hover:text-slate-700 sm:inline-flex"
          >
            Logout
          </button>
        </div>
      </div>
    </header>

    <!-- Body: sidebar + content -->
    <div class="flex flex-1 overflow-hidden">
      <AppSidebar ref="sidebarRef" :open="sidebarOpen" @update:open="sidebarOpen = $event" />

      <main class="flex-1 overflow-y-auto px-4 py-6 pb-24 lg:px-8 lg:py-8 lg:pb-8">
        <slot />
      </main>
    </div>

    <!-- Bottom nav: mobile only. Satu-satunya bottom nav di app — jangan
         ditambah lagi di view lain (DashboardView dkk), nanti numpuk lagi
         kayak kemarin. -->
    <nav
      class="fixed inset-x-0 bottom-0 z-30 flex items-stretch border-t border-slate-100 bg-white lg:hidden"
      style="padding-bottom: env(safe-area-inset-bottom, 0px)"
    >
      <RouterLink
        to="/"
        class="flex flex-1 flex-col items-center justify-center gap-0.5 py-2 text-[11px] font-medium"
        :class="route.path === '/' ? 'text-primary-dark' : 'text-slate-400'"
      >
        <LayoutDashboard class="h-5 w-5" :stroke-width="1.75" />
        Home
      </RouterLink>

      <RouterLink
        to="/my-attendances"
        class="flex flex-1 flex-col items-center justify-center gap-0.5 py-2 text-[11px] font-medium"
        :class="route.path === '/my-attendances' ? 'text-primary-dark' : 'text-slate-400'"
      >
        <Fingerprint class="h-5 w-5" :stroke-width="1.75" />
        History
      </RouterLink>

      <!-- Tombol tengah — Finance, placeholder sampai fiturnya jadi -->
      <div class="flex flex-1 items-start justify-center">
        <button
          type="button"
          @click="openFinanceMenu"
          class="-mt-6 flex h-14 w-14 items-center justify-center rounded-full bg-primary text-white shadow-lg shadow-primary/30 ring-4 ring-white transition hover:bg-primary-dark"
        >
          <Plus class="h-6 w-6" :stroke-width="2.25" />
        </button>
      </div>

      <button
        type="button"
        @click="goToProfile"
        class="flex flex-1 flex-col items-center justify-center gap-0.5 py-2 text-[11px] font-medium text-slate-400"
      >
        <UserRound class="h-5 w-5" :stroke-width="1.75" />
        Profile
      </button>

      <RouterLink
        to="/announcements"
        class="flex flex-1 flex-col items-center justify-center gap-0.5 py-2 text-[11px] font-medium"
        :class="route.path === '/announcements' ? 'text-primary-dark' : 'text-slate-400'"
      >
        <Megaphone class="h-5 w-5" :stroke-width="1.75" />
        Announcement
      </RouterLink>
    </nav>
  </div>
</template>