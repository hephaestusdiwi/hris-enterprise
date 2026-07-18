<script setup lang="ts">
import { RouterLink, useRoute } from 'vue-router'
import { LayoutDashboard, Users, Building2, MapPin, Network, Briefcase, UserRound } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const authStore = useAuthStore()

const menuItems = [
  { name: 'dashboard', label: 'Dashboard', icon: LayoutDashboard, to: '/', permission: null as string | null },
  { name: 'users', label: 'Users', icon: Users, to: '/users', permission: 'view users' },
  { name: 'companies', label: 'Company', icon: Building2, to: '/companies', permission: 'view companies' },
]

const upcomingItems = [
  { label: 'Branch', icon: MapPin },
  { label: 'Department', icon: Network },
  { label: 'Position', icon: Briefcase },
  { label: 'Employee', icon: UserRound },
]

function canAccess(permission: string | null) {
  if (!permission) return true
  return authStore.permissions.includes(permission)
}
</script>

<template>
  <aside class="flex h-screen w-64 flex-col bg-white">
    <div class="flex items-center gap-2.5 px-5 py-6">
      <div
        class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary text-sm font-bold text-white shadow-[0_4px_12px_rgba(51,204,204,0.35)]"
      >
        M
      </div>
      <span class="text-sm font-semibold tracking-tight text-slate-800">Mindway HRIS</span>
    </div>

    <nav class="flex-1 space-y-1 px-3">
      <RouterLink
        v-for="item in menuItems"
        v-show="canAccess(item.permission)"
        :key="item.name"
        :to="item.to"
        class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors duration-150"
        :class="
          route.path === item.to
            ? 'bg-primary-soft text-primary-dark'
            : 'text-slate-500 hover:bg-slate-50 hover:text-slate-700'
        "
      >
        <component :is="item.icon" class="h-[18px] w-[18px]" :stroke-width="1.75" />
        {{ item.label }}
      </RouterLink>

      <div class="mt-8 px-3 text-[11px] font-semibold tracking-wider text-slate-300 uppercase">
        Segera Hadir
      </div>
      <div
        v-for="item in upcomingItems"
        :key="item.label"
        class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-slate-300"
      >
        <component :is="item.icon" class="h-[18px] w-[18px]" :stroke-width="1.75" />
        {{ item.label }}
      </div>
    </nav>
  </aside>
</template>