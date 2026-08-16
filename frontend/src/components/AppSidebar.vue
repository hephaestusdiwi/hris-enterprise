<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import {
  LayoutDashboard, Users, Building2, MapPin, Network, Briefcase, UserRound,
  CalendarDays, TrendingUp, Clock, CalendarClock, Fingerprint, ChevronDown, GitBranch, CheckCircle2, Layers, CalendarRange, BarChart3,
  Wallet, Palmtree, KeyRound, Send, ClipboardCheck, UserCircle, Gift, ScanFace, MinusCircle, HandCoins, Receipt,
  ShieldCheck,
  WalletCards,
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
      {
        name: 'employees',
        label: 'Employee',
        icon: UserRound,
        to: '/employees',
        permission: 'view employees',
      },
      {
        name: 'contract-probation',
        label: 'Contract & Probation',
        icon: CalendarClock,
        to: '/employees/contract-probation',
        permission: 'view employees',
      },
      { name: 'employee-movements', label: 'Employee Movement', icon: GitBranch, to: '/employee-movements', permission: 'view employee movements' },
    ],
  },
  {
    name: 'workflow',
    label: 'Workflow',
    icon: GitBranch,
    items: [
      { name: 'holidays', label: 'Holiday', icon: CalendarDays, to: '/holidays', permission: 'view holidays' },
      { name: 'shifts', label: 'Shift', icon: Clock, to: '/shifts', permission: 'view shifts' },
      { name: 'approval-flows', label: 'Approval Flow', icon: GitBranch, to: '/approval-flows', permission: 'view approval flows' },
      { name: 'scheduler', label: 'Scheduler', icon: CalendarRange, to: '/scheduler', permission: 'view working schedules' },
      { name: 'working-schedules', label: 'Working Schedule', icon: CalendarClock, to: '/working-schedules', permission: 'view working schedules' },
      { name: 'working-schedule-assignments', label: 'Schedule Assignment', icon: Layers, to: '/working-schedule-assignments', permission: 'view working schedules' },
    ],
  },
  {
    name: 'leave',
    label: 'Leave',
    icon: Palmtree,
    items: [
      { name: 'leave-types', label: 'Leave Type', icon: Palmtree, to: '/leave-types', permission: 'view leave types' },
      { name: 'leave-balances', label: 'Leave Balance', icon: Wallet, to: '/leave-balances', permission: 'view leave balances' },
      { name: 'my-leave-requests', label: 'Leave Request', icon: Send, to: '/my-leave-requests', permission: null },
      { name: 'leave-approvals', label: 'Leave Approval', icon: ClipboardCheck, to: '/leave-approvals', permission: null },
      { name: 'leave-calendar', label: 'Leave Calendar', icon: ClipboardCheck, to: '/leave-calendar', permission: null },
    ],
  },
  {
    name: 'time',
    label: 'Attendance',
    icon: Clock,
    items: [
      { name: 'attendances', label: 'Attendances', icon: Fingerprint, to: '/attendances', permission: 'view attendances' },
      { name: 'attendance-devices', label: 'Attendance Device', icon: Fingerprint, to: '/attendance-devices', permission: 'view attendance devices' },
      { name: 'attendance-settings', label: 'Attendance Setting', icon: Fingerprint, to: '/attendance-settings', permission: 'view attendance settings' },
      { name: 'attendance-face-recognition-test', label: 'Face Recognition Test', icon: ScanFace, to: '/attendance-settings/face-recognition-test', permission: 'view attendance settings', },
      { name: 'attendance-face-checkin', label: 'Face Check In', icon: ScanFace, to: '/attendance/face-checkin', permission: null },
      { name: 'attendance-approvals', label: 'Approval Attendance', icon: CheckCircle2, to: '/attendance-approvals', permission: null },
      { name: 'my-attendance-requests', label: 'Attendance Request', icon: Send, to: '/my-attendance-requests', permission: 'create attendance requests' },
      { name: 'attendance-request-approvals', label: 'Approval Attendance Request', icon: ClipboardCheck, to: '/attendance-request-approvals', permission: null },
      { name: 'attendance-report', label: 'Attendance Report', icon: BarChart3, to: '/attendance-report', permission: 'view attendances' },
    ],
  },
  {
    name: 'finance',
    label: 'Finance',
    icon: WalletCards,
    items: [
      { name: 'salary-components', label: 'Salary Component', icon: Wallet, to: '/salary-components', permission: 'view salary components' },
      { name: 'salary-structures', label: 'Salary Structure', icon: Layers, to: '/salary-structures', permission: 'view salary structures' },
      { name: 'employee-salaries', label: 'Employee Salary', icon: UserCircle, to: '/employee-salaries', permission: 'view employee salaries' },
      { name: 'employee-allowances', label: 'Allowance', icon: Gift, to: '/employee-allowances', permission: 'view employee allowances' },
      { name: 'employee-deductions', label: 'Deduction', icon: MinusCircle, to: '/employee-deductions', permission: 'view employee deductions' },
      { name: 'loans', label: 'Loan', icon: HandCoins, to: '/loans', permission: 'view loans' },
      { name: 'my-loans', label: 'My Loans', icon: HandCoins, to: '/my-loans', permission: null },
      { name: 'loan-approvals', label: 'Loan Approval', icon: ClipboardCheck, to: '/loan-approvals', permission: null },
      { name: 'reimbursement-policies', label: 'Reimbursement Policy', icon: Receipt, to: '/reimbursement-policies', permission: 'manage reimbursement policies' },
      { name: 'reimbursements', label: 'Reimbursement', icon: Receipt, to: '/reimbursements', permission: 'view reimbursements' },
      { name: 'my-reimbursements', label: 'My Reimbursement', icon: Receipt, to: '/my-reimbursements', permission: null },
      { name: 'reimbursement-approvals', label: 'Reimbursement Approval', icon: ClipboardCheck, to: '/reimbursement-approvals', permission: null },
    ],
  },
  {
    name: 'payroll',
    label: 'Payroll',
    icon: Wallet,
    items: [
      { name: 'bpjs-settings', label: 'BPJS Settings', icon: ShieldCheck, to: '/bpjs/settings', permission: 'view bpjs settings' },
      { name: 'bpjs-employee-participations', label: 'Employee BPJS', icon: ShieldCheck, to: '/bpjs/employee-participations', permission: 'view bpjs settings' },
      { name: 'tax-settings', label: 'Tax Settings', icon: ShieldCheck, to: '/tax-settings', permission: 'view tax settings' },
      { name: 'employee-tax', label: 'Employee Tax', icon: ShieldCheck, to: '/employee-tax', permission: 'view tax settings' },
      { name: 'payroll-history', label: 'Payroll History', icon: WalletCards, to: '/payroll', permission: 'view payroll runs' },
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