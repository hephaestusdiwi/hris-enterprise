<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import {
  LayoutDashboard, Users, Building2, MapPin, Network, Briefcase, UserRound,
  CalendarDays, TrendingUp, Clock, CalendarClock, Fingerprint, ChevronDown, GitBranch,
} from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'

const props = defineProps<{ open: boolean }>()
const emit = defineEmits<{ 'update:open': [value: boolean] }>()

const route = useRoute()
const authStore = useAuthStore()

interface MenuItem {
  name: string
  label: string
  icon: any
  to: string
  permission: string | null
}

interface MenuGroup {
  name: string
  label: string
  icon: any
  items: MenuItem[]
}

const standaloneItems: MenuItem[] = [
  { name: 'dashboard', label: 'Dashboard', icon: LayoutDashboard, to: '/', permission: null },
]

const groups: MenuGroup[] = [
  {
    name: 'organization',
    label: 'Organization',
    icon: Network,
    items: [
      { name: 'companies', label: 'Company', icon: Building2, to: '/companies', permission: 'view companies' },
      { name: 'branches', label: 'Branch', icon: MapPin, to: '/branches', permission: 'view branches' },
      { name: 'departments', label: 'Department', icon: Network, to: '/departments', permission: 'view departments' },
      { name: 'positions', label: 'Position', icon: Briefcase, to: '/positions', permission: 'view positions' },
      { name: 'job-levels', label: 'Job Level', icon: TrendingUp, to: '/job-levels', permission: 'view job levels' },
    ],
  },
  {
    name: 'employees',
    label: 'Employees',
    icon: UserRound,
    items: [
      { name: 'employees', label: 'Employee', icon: UserRound, to: '/employees', permission: 'view employees' },
    ],
  },
  {
    name: 'workflow',
    label: 'Workflow',
    icon: GitBranch,
    items: [
      { name: 'approval-flows', label: 'Approval Flow', icon: GitBranch, to: '/approval-flows', permission: 'view approval flows' },
    ],
  },
  {
    name: 'time',
    label: 'Attendance',
    icon: Clock,
    items: [
      { name: 'holidays', label: 'Holiday', icon: CalendarDays, to: '/holidays', permission: 'view holidays' },
      { name: 'shifts', label: 'Shift', icon: Clock, to: '/shifts', permission: 'view shifts' },
      { name: 'working-schedules', label: 'Working Schedule', icon: CalendarClock, to: '/working-schedules', permission: 'view working schedules' },
      { name: 'attendances', label: 'Attendances', icon: Fingerprint, to: '/attendances', permission: 'view attendances' },
      { name: 'attendance-devices', label: 'Attendance Device', icon: Fingerprint, to: '/attendance-devices', permission: 'view attendance devices' },
      { name: 'attendance-settings', label: 'Attendance Setting', icon: Fingerprint, to: '/attendance-settings', permission: 'view attendance settings' },
    ],
  },
  {
    name: 'administration',
    label: 'Administration',
    icon: Users,
    items: [
      { name: 'users', label: 'Users', icon: Users, to: '/users', permission: 'view users' },
    ],
  },
]

function canAccess(permission: string | null) {
  if (!permission) return true
  return authStore.permissions.includes(permission)
}

function groupHasAccess(group: MenuGroup) {
  return group.items.some((item) => canAccess(item.permission))
}

function groupIsActive(group: MenuGroup) {
  return group.items.some((item) => item.to === route.path)
}

const openGroups = ref<Record<string, boolean>>(
  Object.fromEntries(groups.map((g) => [g.name, groupIsActive(g)]))
)

function toggleGroup(name: string) {
  openGroups.value[name] = !openGroups.value[name]
}

function handleLinkClick() {
  // di mobile ini nutup drawer pas pindah halaman; di desktop gak ngaruh apa-apa
  emit('update:open', false)
}

function expandGroup(name: string) {
  openGroups.value[name] = true
}

defineExpose({ expandGroup })
</script>

<template>
  <!-- Overlay: cuma render pas drawer mobile kebuka -->
  <div
    v-if="open"
    @click="emit('update:open', false)"
    class="fixed inset-0 z-40 bg-slate-900/30 lg:hidden"
  ></div>

  <aside
    class="fixed inset-y-0 left-0 z-50 w-72 transform overflow-y-auto bg-white transition-transform duration-200 ease-out lg:static lg:z-auto lg:flex lg:h-full lg:w-64 lg:flex-col lg:translate-x-0 lg:transition-none"
    :class="open ? 'translate-x-0' : '-translate-x-full'"
  >
    <nav class="flex-1 space-y-1 px-3 py-4">
      <RouterLink
        v-for="item in standaloneItems"
        v-show="canAccess(item.permission)"
        :key="item.name"
        :to="item.to"
        @click="handleLinkClick"
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

      <div class="mt-2 space-y-0.5">
        <template v-for="group in groups" :key="group.name">
          <div v-if="groupHasAccess(group)">
            <button
              type="button"
              @click="toggleGroup(group.name)"
              class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors duration-150"
              :class="
                groupIsActive(group) && !openGroups[group.name]
                  ? 'bg-primary-soft text-primary-dark'
                  : 'text-slate-500 hover:bg-slate-50 hover:text-slate-700'
              "
            >
              <component :is="group.icon" class="h-[18px] w-[18px]" :stroke-width="1.75" />
              <span class="flex-1 text-left">{{ group.label }}</span>
              <ChevronDown
                class="h-4 w-4 shrink-0 transition-transform duration-150"
                :stroke-width="2"
                :class="openGroups[group.name] ? 'rotate-180' : ''"
              />
            </button>

            <div v-show="openGroups[group.name]" class="mt-0.5 space-y-0.5 pl-[18px]">
              <RouterLink
                v-for="item in group.items"
                v-show="canAccess(item.permission)"
                :key="item.name"
                :to="item.to"
                @click="handleLinkClick"
                class="flex items-center border-l py-2 pl-4 pr-3 text-sm transition-colors duration-150"
                :class="
                  route.path === item.to
                    ? 'border-primary font-medium text-primary-dark'
                    : 'border-slate-100 text-slate-500 hover:text-slate-700'
                "
              >
                {{ item.label }}
              </RouterLink>
            </div>
          </div>
        </template>
      </div>

      <div class="mt-8 px-3 text-[11px] font-semibold tracking-wider text-slate-300 uppercase">
        Segera Hadir
      </div>
    </nav>
  </aside>
</template>