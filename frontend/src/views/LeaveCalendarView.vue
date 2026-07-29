<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue'
import {
  ChevronLeft, ChevronRight, UserRound, X, AlertTriangle, Search,
  Users, Clock, Thermometer, CalendarClock, Loader2, Paperclip, Check, Ban, Printer,
} from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface Ref { id: number; name: string }
interface LeaveTypeRef { id: number; name: string; color: string | null }

interface LeaveEvent {
  id: number
  employee: { id: number; name: string; photo_url: string | null; department: Ref | null }
  leave_type: LeaveTypeRef
  status: 'pending' | 'approved' | 'rejected'
  start_date: string
  end_date: string
  is_half_day: boolean
  total_days: string
}

interface Holiday { id: number; date: string; name: string; type: string }

interface EmployeeOption {
  id: number
  employee_number: string
  first_name: string
  last_name: string | null
}

const statusBadge: Record<string, string> = {
  pending: 'bg-amber-50 text-amber-600',
  approved: 'bg-primary-soft text-primary-dark',
  rejected: 'bg-red-50 text-red-600',
}

const statusDotColor: Record<string, string> = {
  pending: 'bg-amber-400',
  approved: 'bg-primary',
  rejected: 'bg-red-400',
}

function fullEmployeeName(e: { first_name: string; last_name: string | null }) {
  return [e.first_name, e.last_name].filter(Boolean).join(' ')
}

function initialsFromName(name: string): string {
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0]?.toUpperCase())
    .join('')
}

function formatDateRange(start: string, end: string) {
  const s = new Date(start).toLocaleDateString('id-ID', { day: 'numeric', month: 'short' })
  const e = new Date(end).toLocaleDateString('id-ID', { day: 'numeric', month: 'short' })
  return start === end ? s : `${s} - ${e}`
}

// ---------- Summary panel ----------
const summary = ref({ on_leave_today: 0, pending_leave: 0, on_sick_leave_today: 0, upcoming_this_week: 0 })

async function loadSummary() {
  try {
    const response = await apiClient.get('/api/leave-calendar/summary', { params: { company_id: filters.company_id || undefined } })
    summary.value = response.data.data
  } catch {
    // gak fatal, biarin default 0
  }
}

// ---------- Filters ----------
const showFilters = ref(false)
const filters = reactive({
  company_id: null as number | null,
  branch_id: null as number | null,
  department_id: null as number | null,
  position_id: null as number | null,
  leave_type_id: null as number | null,
  employee_id: null as number | null,
  status: { pending: true, approved: true, rejected: false },
})

const employeeSearch = ref('')
const showEmployeeDropdown = ref(false)
const companies = ref<Ref[]>([])
const branches = ref<Ref[]>([])
const departments = ref<Ref[]>([])
const positions = ref<Ref[]>([])
const leaveTypes = ref<LeaveTypeRef[]>([])
const employeeOptions = ref<EmployeeOption[]>([])

const filteredEmployeeOptions = computed(() => {
  if (!employeeSearch.value.trim()) return employeeOptions.value.slice(0, 8)
  const q = employeeSearch.value.toLowerCase()
  return employeeOptions.value.filter((e) => fullEmployeeName(e).toLowerCase().includes(q)).slice(0, 8)
})

const selectedEmployeeLabel = computed(() => {
  const e = employeeOptions.value.find((x) => x.id === filters.employee_id)
  return e ? fullEmployeeName(e) : ''
})

function selectEmployee(e: EmployeeOption) {
  filters.employee_id = e.id
  employeeSearch.value = fullEmployeeName(e)
  showEmployeeDropdown.value = false
}

function clearEmployeeFilter() {
  filters.employee_id = null
  employeeSearch.value = ''
}

async function loadReferenceData() {
  const [companyRes, branchRes, departmentRes, positionRes, leaveTypeRes, employeeRes] = await Promise.all([
    apiClient.get('/api/companies'),
    apiClient.get('/api/branches'),
    apiClient.get('/api/departments'),
    apiClient.get('/api/positions'),
    apiClient.get('/api/leave-types/self-service'),
    apiClient.get('/api/employees', { params: { per_page: 200 } }),
  ])
  companies.value = companyRes.data.data.data
  branches.value = branchRes.data.data.data
  departments.value = departmentRes.data.data.data
  positions.value = positionRes.data.data.data
  leaveTypes.value = leaveTypeRes.data.data
  employeeOptions.value = employeeRes.data.data.data
}

const activeFilterCount = computed(() => {
  let n = 0
  if (filters.company_id) n++
  if (filters.branch_id) n++
  if (filters.department_id) n++
  if (filters.position_id) n++
  if (filters.leave_type_id) n++
  if (filters.employee_id) n++
  return n
})

// ---------- Calendar data ----------
const today = new Date()
const currentYear = ref(today.getFullYear())
const currentMonth = ref(today.getMonth() + 1)

const monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']

const yearOptions = computed(() => {
  const base = today.getFullYear()
  const years = new Set(Array.from({ length: 7 }, (_, i) => base - 3 + i))
  years.add(currentYear.value)
  return Array.from(years).sort((a, b) => a - b)
})

const holidays = ref<Holiday[]>([])
const leaves = ref<LeaveEvent[]>([])
const departmentHeadcount = ref<Record<string, number>>({})
const loading = ref(true)
const errorMessage = ref('')

const weekDays = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab']

interface DayCell {
  date: string
  dayNumber: number
  isCurrentMonth: boolean
  isToday: boolean
  isWeekend: boolean
  holiday: Holiday | null
  leavesToday: LeaveEvent[]
  pendingCount: number
  capacityWarning: { departmentName: string; percent: number } | null
}

function toDateStr(d: Date) {
  return d.toISOString().slice(0, 10)
}

const calendarDays = computed<DayCell[]>(() => {
  const firstOfMonth = new Date(currentYear.value, currentMonth.value - 1, 1)
  const startOffset = firstOfMonth.getDay()
  const gridStart = new Date(firstOfMonth)
  gridStart.setDate(gridStart.getDate() - startOffset)

  const todayStr = toDateStr(today)
  const days: DayCell[] = []

  for (let i = 0; i < 42; i++) {
    const d = new Date(gridStart)
    d.setDate(gridStart.getDate() + i)
    const dateStr = toDateStr(d)
    const dow = d.getDay()

    const holiday = holidays.value.find((h) => h.date === dateStr) ?? null
    const leavesToday = leaves.value.filter((lv) => dateStr >= lv.start_date && dateStr <= lv.end_date)
    const pendingCount = leavesToday.filter((lv) => lv.status === 'pending').length

    // Capacity warning: kelompokkan by department, cek >= 30% dari headcount
    let capacityWarning: DayCell['capacityWarning'] = null
    const byDept = new Map<number, { name: string; count: number }>()
    for (const lv of leavesToday) {
      if (lv.status !== 'approved' || !lv.employee.department) continue
      const entry = byDept.get(lv.employee.department.id) ?? { name: lv.employee.department.name, count: 0 }
      entry.count++
      byDept.set(lv.employee.department.id, entry)
    }
    for (const [deptId, entry] of byDept) {
      const headcount = departmentHeadcount.value[String(deptId)] ?? 0
      if (headcount > 0) {
        const percent = Math.round((entry.count / headcount) * 100)
        if (percent >= 30 && (!capacityWarning || percent > capacityWarning.percent)) {
          capacityWarning = { departmentName: entry.name, percent }
        }
      }
    }

    days.push({
      date: dateStr,
      dayNumber: d.getDate(),
      isCurrentMonth: d.getMonth() === currentMonth.value - 1,
      isToday: dateStr === todayStr,
      isWeekend: dow === 0 || dow === 6,
      holiday,
      leavesToday,
      pendingCount,
      capacityWarning,
    })
  }

  return days
})

// ---------- Upcoming leave panel (dari data bulan yang lagi ditampilin) ----------
const upcomingLeaves = computed(() => {
  const todayStr = toDateStr(today)
  return leaves.value
    .filter((lv) => lv.end_date >= todayStr)
    .sort((a, b) => a.start_date.localeCompare(b.start_date))
    .slice(0, 8)
})

async function loadCalendar() {
  loading.value = true
  errorMessage.value = ''
  try {
    const statusList = Object.entries(filters.status).filter(([, v]) => v).map(([k]) => k)
    const response = await apiClient.get('/api/leave-calendar', {
      params: {
        year: currentYear.value,
        month: currentMonth.value,
        company_id: filters.company_id || undefined,
        branch_id: filters.branch_id || undefined,
        department_id: filters.department_id || undefined,
        position_id: filters.position_id || undefined,
        leave_type_id: filters.leave_type_id || undefined,
        employee_id: filters.employee_id || undefined,
        status: statusList.length ? statusList : undefined,
      },
    })
    holidays.value = response.data.data.holidays
    leaves.value = response.data.data.leaves
    departmentHeadcount.value = response.data.data.department_headcount
  } catch {
    errorMessage.value = 'Gagal memuat leave calendar.'
  } finally {
    loading.value = false
  }
}

function reload() {
  loadCalendar()
  loadSummary()
}

function goToPrevMonth() {
  if (currentMonth.value === 1) { currentMonth.value = 12; currentYear.value-- } else { currentMonth.value-- }
}
function goToNextMonth() {
  if (currentMonth.value === 12) { currentMonth.value = 1; currentYear.value++ } else { currentMonth.value++ }
}
function goToToday() {
  currentYear.value = today.getFullYear()
  currentMonth.value = today.getMonth() + 1
}

function formatDate(dateStr: string) {
  return new Date(dateStr).toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
}

function handlePrint() {
  window.print()
}

// ---------- Legend dinamis ----------
const legendLeaveTypes = computed(() => {
  const map = new Map<number, LeaveTypeRef>()
  for (const lv of leaves.value) map.set(lv.leave_type.id, lv.leave_type)
  return Array.from(map.values())
})

// ---------- Drawer: 'day' | 'event' ----------
const drawerMode = ref<'day' | 'event' | null>(null)
const drawerDay = ref<DayCell | null>(null)
const eventDetail = ref<any>(null)
const eventDetailLoading = ref(false)

function openDayDrawer(day: DayCell) {
  if (day.leavesToday.length === 0 && !day.holiday) return
  drawerDay.value = day
  drawerMode.value = 'day'
}

async function openEventDrawer(eventId: number) {
  drawerMode.value = 'event'
  eventDetailLoading.value = true
  eventDetail.value = null
  try {
    const response = await apiClient.get(`/api/leave-calendar/${eventId}`)
    eventDetail.value = response.data.data
  } catch {
    eventDetail.value = { error: true }
  } finally {
    eventDetailLoading.value = false
  }
}

function closeDrawer() {
  drawerMode.value = null
  drawerDay.value = null
  eventDetail.value = null
}

// ---------- Approve/Reject dari drawer ----------
const deciding = ref(false)
const decideNotes = ref('')
const showRejectInput = ref(false)

async function decide(action: 'approve' | 'reject') {
  if (!eventDetail.value?.current_decision_id) return
  if (action === 'reject' && !decideNotes.value.trim()) {
    showRejectInput.value = true
    return
  }

  deciding.value = true
  try {
    await apiClient.post(`/api/leave-approvals/${eventDetail.value.current_decision_id}/decide`, {
      action,
      notes: decideNotes.value || null,
    })
    closeDrawer()
    reload()
  } catch {
    alert('Gagal memproses keputusan.')
  } finally {
    deciding.value = false
  }
}

// ---------- Tooltip hover ----------
const hoveredEventId = ref<number | null>(null)

watch([currentYear, currentMonth, () => filters.company_id, () => filters.branch_id, () => filters.department_id, () => filters.position_id, () => filters.leave_type_id, () => filters.employee_id, () => filters.status], reload, { deep: true })

onMounted(() => {
  loadReferenceData()
  reload()
})
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Leave Planning Dashboard</h1>
        <p class="mt-1 text-sm text-slate-500">Rencana cuti tim, hari libur, dan potensi kekurangan manpower.</p>
      </div>
      <button
        @click="handlePrint"
        class="flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50"
      >
        <Printer class="h-4 w-4" :stroke-width="1.75" />
        Print
      </button>
    </div>

    <!-- Summary panel -->
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
      <div class="rounded-2xl border border-slate-100 bg-white p-4">
        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary-soft">
          <Users class="h-4 w-4 text-primary-dark" :stroke-width="1.75" />
        </div>
        <p class="mt-3 text-xl font-semibold tracking-tight text-slate-900">{{ summary.on_leave_today }}</p>
        <p class="mt-0.5 text-xs text-slate-500">Employees on Leave Today</p>
      </div>
      <div class="rounded-2xl border border-slate-100 bg-white p-4">
        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50">
          <Clock class="h-4 w-4 text-amber-600" :stroke-width="1.75" />
        </div>
        <p class="mt-3 text-xl font-semibold tracking-tight text-slate-900">{{ summary.pending_leave }}</p>
        <p class="mt-0.5 text-xs text-slate-500">Pending Leave</p>
      </div>
      <div class="rounded-2xl border border-slate-100 bg-white p-4">
        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-50">
          <Thermometer class="h-4 w-4 text-orange-600" :stroke-width="1.75" />
        </div>
        <p class="mt-3 text-xl font-semibold tracking-tight text-slate-900">{{ summary.on_sick_leave_today }}</p>
        <p class="mt-0.5 text-xs text-slate-500">On Sick Leave Today</p>
      </div>
      <div class="rounded-2xl border border-slate-100 bg-white p-4">
        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50">
          <CalendarClock class="h-4 w-4 text-blue-600" :stroke-width="1.75" />
        </div>
        <p class="mt-3 text-xl font-semibold tracking-tight text-slate-900">{{ summary.upcoming_this_week }}</p>
        <p class="mt-0.5 text-xs text-slate-500">Upcoming This Week</p>
      </div>
    </div>

    <!-- Filter bar -->
    <div class="rounded-2xl border border-slate-100 bg-white p-4">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
          <button @click="goToPrevMonth" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
            <ChevronLeft class="h-4 w-4" :stroke-width="2" />
          </button>
          <select
            v-model.number="currentMonth"
            class="rounded-lg border border-slate-200 px-2 py-1.5 text-xs font-medium text-slate-700 focus:border-primary focus:outline-none"
          >
            <option v-for="(name, i) in monthNames" :key="i" :value="i + 1">{{ name }}</option>
          </select>
          <select
            v-model.number="currentYear"
            class="rounded-lg border border-slate-200 px-2 py-1.5 text-xs font-medium text-slate-700 focus:border-primary focus:outline-none"
          >
            <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
          </select>
          <button @click="goToNextMonth" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
            <ChevronRight class="h-4 w-4" :stroke-width="2" />
          </button>
          <button @click="goToToday" class="ml-1 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">
            Hari Ini
          </button>
        </div>

        <button
          type="button"
          @click="showFilters = !showFilters"
          class="flex items-center gap-1 text-sm font-medium text-primary-dark hover:underline"
        >
          {{ showFilters ? 'Sembunyikan filter' : 'Semua filter' }}
          <span v-if="activeFilterCount > 0" class="rounded-full bg-primary-soft px-1.5 py-0.5 text-[11px] font-semibold text-primary-dark">
            {{ activeFilterCount }}
          </span>
        </button>
      </div>

      <Transition
        enter-active-class="transition-all duration-150 ease-out"
        enter-from-class="opacity-0 -translate-y-1"
        enter-to-class="opacity-100 translate-y-0"
      >
        <div v-if="showFilters" class="mt-4 flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4">
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">Company</label>
            <select v-model="filters.company_id" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
              <option :value="null">Semua</option>
              <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">Branch</label>
            <select v-model="filters.branch_id" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
              <option :value="null">Semua</option>
              <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">Department</label>
            <select v-model="filters.department_id" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
              <option :value="null">Semua</option>
              <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">Position</label>
            <select v-model="filters.position_id" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
              <option :value="null">Semua</option>
              <option v-for="p in positions" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">Leave Type</label>
            <select v-model="filters.leave_type_id" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
              <option :value="null">Semua</option>
              <option v-for="lt in leaveTypes" :key="lt.id" :value="lt.id">{{ lt.name }}</option>
            </select>
          </div>
          <div class="relative">
            <label class="mb-1 block text-xs font-medium text-slate-500">Employee</label>
            <div class="relative">
              <Search class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" :stroke-width="1.75" />
              <input
                v-model="employeeSearch"
                @focus="showEmployeeDropdown = true"
                type="text"
                placeholder="Cari employee..."
                class="w-48 rounded-xl border border-slate-200 py-2 pl-8 pr-7 text-sm focus:border-primary focus:outline-none"
              />
              <button v-if="filters.employee_id" @click="clearEmployeeFilter" class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                <X class="h-3.5 w-3.5" />
              </button>
            </div>
            <div
              v-if="showEmployeeDropdown && filteredEmployeeOptions.length > 0"
              @mouseleave="showEmployeeDropdown = false"
              class="absolute z-10 mt-1 w-56 overflow-hidden rounded-xl border border-slate-100 bg-white py-1 shadow-lg"
            >
              <button
                v-for="e in filteredEmployeeOptions"
                :key="e.id"
                @click="selectEmployee(e)"
                class="block w-full px-3 py-2 text-left text-sm text-slate-600 hover:bg-slate-50"
              >
                {{ fullEmployeeName(e) }}
                <span class="block text-xs text-slate-400">{{ e.employee_number }}</span>
              </button>
            </div>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">Status</label>
            <div class="flex gap-1">
              <button
                type="button"
                @click="filters.status.pending = !filters.status.pending"
                class="rounded-lg px-2.5 py-2 text-xs font-medium transition-colors"
                :class="filters.status.pending ? 'bg-amber-50 text-amber-600' : 'bg-slate-50 text-slate-400'"
              >
                Pending
              </button>
              <button
                type="button"
                @click="filters.status.approved = !filters.status.approved"
                class="rounded-lg px-2.5 py-2 text-xs font-medium transition-colors"
                :class="filters.status.approved ? 'bg-primary-soft text-primary-dark' : 'bg-slate-50 text-slate-400'"
              >
                Approved
              </button>
              <button
                type="button"
                @click="filters.status.rejected = !filters.status.rejected"
                class="rounded-lg px-2.5 py-2 text-xs font-medium transition-colors"
                :class="filters.status.rejected ? 'bg-red-50 text-red-600' : 'bg-slate-50 text-slate-400'"
              >
                Rejected
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </div>

    <div v-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>

    <div v-else class="grid grid-cols-1 gap-5 lg:grid-cols-3">
      <!-- Kalender -->
      <div class="lg:col-span-2">
        <!-- Skeleton loading -->
        <div v-if="loading" class="overflow-hidden rounded-2xl border border-slate-100 bg-white">
          <div class="grid grid-cols-7 border-b border-slate-100 bg-slate-50/60">
            <div v-for="wd in weekDays" :key="wd" class="px-2 py-2.5 text-center text-xs font-medium text-slate-500">{{ wd }}</div>
          </div>
          <div class="grid grid-cols-7">
            <div v-for="i in 42" :key="i" class="min-h-[96px] animate-pulse border-b border-r border-slate-50 p-2 [&:nth-child(7n)]:border-r-0">
              <div class="h-6 w-6 rounded-full bg-slate-100"></div>
              <div class="mt-3 h-3 w-3/4 rounded bg-slate-100"></div>
            </div>
          </div>
        </div>

        <!-- Grid kalender -->
        <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
          <div class="grid grid-cols-7 border-b border-slate-100 bg-slate-50/60">
            <div v-for="wd in weekDays" :key="wd" class="px-2 py-2.5 text-center text-xs font-medium text-slate-500">{{ wd }}</div>
          </div>

          <div class="grid grid-cols-7">
            <div
              v-for="day in calendarDays"
              :key="day.date"
              @click="openDayDrawer(day)"
              class="relative min-h-[96px] border-b border-r border-slate-50 p-2 transition-colors [&:nth-child(7n)]:border-r-0"
              :class="[
                day.holiday ? 'bg-red-50/40' : day.isWeekend ? 'bg-slate-50/60' : day.isCurrentMonth ? 'bg-white' : 'bg-slate-50/30',
                (day.leavesToday.length > 0 || day.holiday) ? 'cursor-pointer hover:bg-slate-50' : '',
              ]"
            >
              <div class="flex items-center justify-between">
                <span
                  class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-medium"
                  :class="day.isToday ? 'bg-primary text-white' : day.isCurrentMonth ? 'text-slate-700' : 'text-slate-300'"
                >
                  {{ day.dayNumber }}
                </span>
                <AlertTriangle
                  v-if="day.capacityWarning"
                  class="h-3.5 w-3.5 text-amber-500"
                  :stroke-width="2"
                  :title="`${day.capacityWarning.departmentName}: ${day.capacityWarning.percent}% sedang cuti`"
                />
              </div>

              <div v-if="day.holiday" class="mt-1 truncate rounded-md bg-red-100 px-1.5 py-0.5 text-[10px] font-medium text-red-700">
                {{ day.holiday.name }}
              </div>

              <!-- Avatar stack -->
              <div v-if="day.leavesToday.length > 0" class="mt-1.5 flex items-center gap-1">
                <div class="flex -space-x-1.5">
                  <div
                    v-for="lv in day.leavesToday.slice(0, 4)"
                    :key="lv.id"
                    class="relative h-5 w-5 shrink-0 cursor-pointer"
                    @click.stop="openEventDrawer(lv.id)"
                    @mouseenter="hoveredEventId = lv.id"
                    @mouseleave="hoveredEventId = null"
                  >
                    <img
                      v-if="lv.employee.photo_url"
                      :src="lv.employee.photo_url"
                      alt=""
                      class="h-5 w-5 rounded-full object-cover ring-2 ring-white"
                    />
                    <div
                      v-else
                      class="flex h-5 w-5 items-center justify-center rounded-full bg-primary-soft text-[8px] font-semibold text-primary-dark ring-2 ring-white"
                    >
                      {{ initialsFromName(lv.employee.name) }}
                    </div>
                    <span class="absolute -bottom-0.5 -right-0.5 h-1.5 w-1.5 rounded-full border border-white" :class="statusDotColor[lv.status]"></span>

                    <!-- Tooltip -->
                    <div
                      v-if="hoveredEventId === lv.id"
                      class="absolute left-1/2 top-full z-20 mt-1 w-48 -translate-x-1/2 rounded-lg bg-slate-900 px-3 py-2 text-[11px] text-white shadow-lg"
                    >
                      <p class="font-medium">{{ lv.employee.name }}</p>
                      <p class="text-slate-300">{{ lv.leave_type.name }} · {{ lv.total_days }} hari</p>
                      <p class="capitalize text-slate-400">{{ lv.status }}</p>
                    </div>
                  </div>
                </div>
                <span v-if="day.leavesToday.length > 4" class="text-[10px] font-medium text-primary-dark">
                  +{{ day.leavesToday.length - 4 }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Panel Cuti Mendatang -->
      <div class="rounded-2xl border border-slate-100 bg-white p-4 lg:col-span-1">
        <div class="mb-3 flex items-center justify-between">
          <h3 class="text-sm font-semibold text-slate-800">Cuti Mendatang</h3>
          <span class="text-xs text-slate-400">Bulan ini</span>
        </div>

        <div v-if="loading" class="space-y-2">
          <div v-for="i in 5" :key="i" class="flex animate-pulse items-center gap-3 px-2 py-2">
            <div class="h-8 w-8 rounded-full bg-slate-100"></div>
            <div class="flex-1 space-y-1.5">
              <div class="h-2.5 w-2/3 rounded bg-slate-100"></div>
              <div class="h-2 w-1/2 rounded bg-slate-100"></div>
            </div>
          </div>
        </div>

        <div v-else-if="upcomingLeaves.length === 0" class="py-8 text-center text-sm text-slate-400">
          Tidak ada cuti mendatang di bulan ini.
        </div>

        <div v-else class="space-y-1">
          <button
            v-for="lv in upcomingLeaves"
            :key="lv.id"
            @click="openEventDrawer(lv.id)"
            class="flex w-full items-center gap-3 rounded-xl px-2 py-2 text-left transition-colors hover:bg-slate-50"
          >
            <img v-if="lv.employee.photo_url" :src="lv.employee.photo_url" alt="" class="h-8 w-8 shrink-0 rounded-full object-cover" />
            <div v-else class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-soft text-xs font-semibold text-primary-dark">
              {{ initialsFromName(lv.employee.name) }}
            </div>
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-medium text-slate-800">{{ lv.employee.name }}</p>
              <p class="truncate text-xs text-slate-500">{{ lv.leave_type.name }} · {{ formatDateRange(lv.start_date, lv.end_date) }}</p>
            </div>
            <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium capitalize" :class="statusBadge[lv.status]">{{ lv.status }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Legend -->
    <div class="flex flex-wrap items-center gap-4 text-xs text-slate-500">
      <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-slate-200"></span>Weekend</span>
      <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-red-200"></span>Hari Libur</span>
      <span v-for="lt in legendLeaveTypes" :key="lt.id" class="flex items-center gap-1.5">
        <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: lt.color ?? '#94A3B8' }"></span>{{ lt.name }}
      </span>
      <span class="flex items-center gap-1.5"><AlertTriangle class="h-3 w-3 text-amber-500" :stroke-width="2" />Capacity Warning (&ge;30%)</span>
    </div>

    <!-- ===== DRAWER KANAN ===== -->
    <Teleport to="body">
      <div v-if="drawerMode" class="fixed inset-0 z-50">
        <div class="absolute inset-0 bg-slate-900/30" @click="closeDrawer"></div>

        <Transition
          enter-active-class="transition-transform duration-200 ease-out"
          enter-from-class="translate-x-full"
          enter-to-class="translate-x-0"
          appear
        >
          <aside class="absolute right-0 top-0 flex h-full w-full max-w-md flex-col bg-white shadow-2xl">
            <!-- Mode: DAY -->
            <template v-if="drawerMode === 'day' && drawerDay">
              <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <h2 class="text-base font-semibold text-slate-900">{{ formatDate(drawerDay.date) }}</h2>
                <button @click="closeDrawer" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50"><X class="h-5 w-5" /></button>
              </div>
              <div class="flex-1 space-y-3 overflow-y-auto px-6 py-5">
                <div v-if="drawerDay.holiday" class="rounded-xl bg-red-50 p-3">
                  <p class="text-sm font-medium text-red-700">{{ drawerDay.holiday.name }}</p>
                  <p class="text-xs text-red-500">Hari Libur ({{ drawerDay.holiday.type }})</p>
                </div>
                <div v-if="drawerDay.capacityWarning" class="flex items-start gap-2 rounded-xl bg-amber-50 p-3 text-xs text-amber-700">
                  <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" :stroke-width="1.75" />
                  <p><strong>{{ drawerDay.capacityWarning.departmentName }}</strong>: {{ drawerDay.capacityWarning.percent }}% karyawan sedang cuti pada tanggal ini — potensi kekurangan manpower.</p>
                </div>
                <button
                  v-for="lv in drawerDay.leavesToday"
                  :key="lv.id"
                  @click="openEventDrawer(lv.id)"
                  class="flex w-full items-center gap-3 rounded-xl border border-slate-100 p-3 text-left hover:bg-slate-50"
                >
                  <img v-if="lv.employee.photo_url" :src="lv.employee.photo_url" alt="" class="h-9 w-9 shrink-0 rounded-full object-cover" />
                  <div v-else class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-soft text-primary-dark"><UserRound class="h-4 w-4" :stroke-width="1.75" /></div>
                  <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-slate-800">{{ lv.employee.name }}</p>
                    <p class="truncate text-xs text-slate-500">{{ lv.leave_type.name }} · {{ lv.total_days }} hari</p>
                  </div>
                  <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium" :class="statusBadge[lv.status]">{{ lv.status }}</span>
                </button>
              </div>
            </template>

            <!-- Mode: EVENT DETAIL -->
            <template v-else-if="drawerMode === 'event'">
              <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <h2 class="text-base font-semibold text-slate-900">Leave Request Detail</h2>
                <button @click="closeDrawer" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50"><X class="h-5 w-5" /></button>
              </div>

              <div v-if="eventDetailLoading" class="flex flex-1 items-center justify-center text-sm text-slate-400">
                <Loader2 class="h-4 w-4 animate-spin" :stroke-width="2" />
              </div>
              <div v-else-if="eventDetail?.error" class="flex-1 px-6 py-10 text-center text-sm text-red-500">
                Gagal memuat detail / Anda tidak punya akses.
              </div>

              <div v-else-if="eventDetail" class="flex-1 space-y-5 overflow-y-auto px-6 py-5">
                <div class="flex items-center gap-3">
                  <img v-if="eventDetail.employee.photo_url" :src="eventDetail.employee.photo_url" alt="" class="h-12 w-12 rounded-full object-cover" />
                  <div v-else class="flex h-12 w-12 items-center justify-center rounded-full bg-primary-soft text-primary-dark"><UserRound class="h-5 w-5" :stroke-width="1.75" /></div>
                  <div>
                    <p class="font-semibold text-slate-800">{{ eventDetail.employee.name }}</p>
                    <p class="text-xs text-slate-500">{{ eventDetail.employee.department ?? '-' }}</p>
                  </div>
                </div>

                <dl class="space-y-3 border-t border-slate-100 pt-4 text-sm">
                  <div class="flex justify-between gap-4">
                    <dt class="text-slate-400">Leave Type</dt>
                    <dd class="flex items-center gap-1.5 font-medium text-slate-700">
                      <span class="h-2 w-2 rounded-full" :style="{ backgroundColor: eventDetail.leave_type.color ?? '#94A3B8' }"></span>
                      {{ eventDetail.leave_type.name }}
                    </dd>
                  </div>
                  <div class="flex justify-between gap-4">
                    <dt class="text-slate-400">Status</dt>
                    <dd><span class="rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="statusBadge[eventDetail.status]">{{ eventDetail.status }}</span></dd>
                  </div>
                  <div class="flex justify-between gap-4">
                    <dt class="shrink-0 text-slate-400">Tanggal</dt>
                    <dd class="text-right font-medium text-slate-700">
                      {{ eventDetail.start_date }}<span v-if="eventDetail.start_date !== eventDetail.end_date"> - {{ eventDetail.end_date }}</span>
                      <span class="block text-xs font-normal text-slate-400">{{ eventDetail.total_days }} hari{{ eventDetail.is_half_day ? ' · Setengah hari' : '' }}</span>
                    </dd>
                  </div>
                  <div v-if="eventDetail.balance" class="flex justify-between gap-4">
                    <dt class="text-slate-400">Leave Balance</dt>
                    <dd class="text-right font-medium text-slate-700">
                      {{ eventDetail.balance.remaining }} / {{ eventDetail.balance.initial_quota }} hari tersisa
                    </dd>
                  </div>
                  <div class="flex justify-between gap-4">
                    <dt class="shrink-0 text-slate-400">Reason</dt>
                    <dd class="text-right font-medium text-slate-700">{{ eventDetail.reason }}</dd>
                  </div>
                  <div v-if="eventDetail.attachment_url" class="flex justify-between gap-4">
                    <dt class="text-slate-400">Attachment</dt>
                    <dd>
                      <a :href="eventDetail.attachment_url" target="_blank" class="flex items-center gap-1 text-primary-dark hover:underline">
                        <Paperclip class="h-3.5 w-3.5" :stroke-width="1.75" />Lihat file
                      </a>
                    </dd>
                  </div>
                  <div v-if="eventDetail.waiting_for" class="flex justify-between gap-4">
                    <dt class="text-slate-400">Menunggu</dt>
                    <dd class="font-medium text-slate-700">{{ eventDetail.waiting_for }}</dd>
                  </div>
                </dl>

                <div v-if="eventDetail.approval_history.length" class="border-t border-slate-100 pt-4">
                  <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Approval History</p>
                  <div class="space-y-2">
                    <div v-for="h in eventDetail.approval_history" :key="h.sequence" class="flex items-start justify-between gap-3 rounded-xl border border-slate-100 p-3 text-xs">
                      <div>
                        <p class="font-medium text-slate-700">{{ h.step_name }}</p>
                        <p v-if="h.decided_by" class="text-slate-400">{{ h.decided_by }}</p>
                        <p v-if="h.notes" class="mt-1 text-slate-500">"{{ h.notes }}"</p>
                      </div>
                      <span class="shrink-0 rounded-full px-2 py-0.5 font-medium capitalize" :class="statusBadge[h.status] ?? 'bg-slate-100 text-slate-500'">{{ h.status }}</span>
                    </div>
                  </div>
                </div>

                <div v-if="showRejectInput" class="border-t border-slate-100 pt-4">
                  <label class="mb-1 block text-xs font-medium text-slate-700">Alasan penolakan (wajib)</label>
                  <textarea v-model="decideNotes" rows="2" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"></textarea>
                </div>
              </div>

              <div v-if="eventDetail?.can_decide" class="flex gap-3 border-t border-slate-100 px-6 py-4">
                <button
                  @click="decide('reject')"
                  :disabled="deciding"
                  class="flex flex-1 items-center justify-center gap-1.5 rounded-xl border border-red-200 py-2.5 text-sm font-medium text-red-600 hover:bg-red-50 disabled:opacity-50"
                >
                  <Ban class="h-4 w-4" :stroke-width="1.75" />Reject
                </button>
                <button
                  @click="decide('approve')"
                  :disabled="deciding"
                  class="flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-primary py-2.5 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50"
                >
                  <Check class="h-4 w-4" :stroke-width="1.75" />Approve
                </button>
              </div>
            </template>
          </aside>
        </Transition>
      </div>
    </Teleport>
  </div>
</template>