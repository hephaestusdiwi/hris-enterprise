<script setup lang="ts">
import { ref, onMounted, reactive, computed } from 'vue'
import {
  Search, Download, X, ChevronRight, ChevronLeft, Loader2,
  CheckCircle2, XCircle, CalendarOff, Timer, BarChart3, TrendingUp, AlertTriangle, Clock,
} from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface Ref { id: number; name: string }

interface SummaryRow {
  employee: { id: number; employee_number: string; name: string }
  present_days: number
  late_days: number
  overtime_minutes: number
  leave_days: number
  absent_days: number
  other_days: number
  expected_working_days: number
  working_hours: number
  attendance_rate: number | null
}

interface ApprovalRequestRow {
  type: string
  status: string
  detected_value: number
  approved_value: number | null
}

interface DailyRow {
  date: string
  day_name: string
  is_holiday: boolean
  shift: { id: number; name: string } | null
  clock_in: string | null
  clock_out: string | null
  late_minutes: number | null
  approved_late_minutes: number | null
  detected_overtime_minutes: number | null
  approved_overtime_minutes: number | null
  working_minutes?: number | null
  status: string | null
  clock_in_method: string | null
  clock_out_method: string | null
  approval_requests: ApprovalRequestRow[]
  notes: string | null
}

interface EmployeeOption {
  id: number
  employee_number: string
  first_name: string
  last_name: string | null
}

function todayStr() {
  return new Date().toISOString().slice(0, 10)
}

function toStr(d: Date) {
  return d.toISOString().slice(0, 10)
}

const filters = reactive({
  date_from: todayStr(),
  date_to: todayStr(),
  company_id: null as number | null,
  branch_id: null as number | null,
  department_id: null as number | null,
  employee_id: null as number | null,
  page: 1,
})

const showFilters = ref(false)

const companies = ref<Ref[]>([])
const branches = ref<Ref[]>([])
const departments = ref<Ref[]>([])
const employeeOptions = ref<EmployeeOption[]>([])

const activeFilterCount = computed(() =>
  [filters.company_id, filters.branch_id, filters.department_id, filters.employee_id].filter((v) => v !== null).length,
)

function employeeOptionLabel(e: EmployeeOption) {
  return `${[e.first_name, e.last_name].filter(Boolean).join(' ')} (${e.employee_number})`
}

const rows = ref<SummaryRow[]>([])
const loading = ref(true)
const errorMessage = ref('')
const meta = ref({ current_page: 1, last_page: 1, total: 0 })
const exporting = ref(false)

function applyPreset(preset: 'today' | 'week' | 'month' | 'last_month' | 'payroll') {
  const now = new Date()

  if (preset === 'today') {
    filters.date_from = todayStr()
    filters.date_to = todayStr()
  } else if (preset === 'week') {
    const day = now.getDay() === 0 ? 7 : now.getDay()
    const start = new Date(now)
    start.setDate(now.getDate() - (day - 1))
    filters.date_from = toStr(start)
    filters.date_to = todayStr()
  } else if (preset === 'month' || preset === 'payroll') {
    // "Payroll Period" sementara disamakan dengan bulan berjalan
    // (belum ada modul Payroll/cutoff date)
    const start = new Date(now.getFullYear(), now.getMonth(), 1)
    filters.date_from = toStr(start)
    filters.date_to = todayStr()
  } else if (preset === 'last_month') {
    const start = new Date(now.getFullYear(), now.getMonth() - 1, 1)
    const end = new Date(now.getFullYear(), now.getMonth(), 0)
    filters.date_from = toStr(start)
    filters.date_to = toStr(end)
  }

  filters.page = 1
  loadReport()
}

async function loadReport() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/attendance-report', {
      params: {
        date_from: filters.date_from,
        date_to: filters.date_to,
        company_id: filters.company_id || undefined,
        branch_id: filters.branch_id || undefined,
        department_id: filters.department_id || undefined,
        employee_id: filters.employee_id || undefined,
        page: filters.page,
      },
    })
    rows.value = response.data.data.data
    meta.value = {
      current_page: response.data.data.current_page,
      last_page: response.data.data.last_page,
      total: response.data.data.total,
    }
  } catch {
    errorMessage.value = 'Gagal memuat attendance report.'
  } finally {
    loading.value = false
  }
}

async function loadReferenceData() {
  const [companyRes, branchRes, departmentRes, employeeRes] = await Promise.all([
    apiClient.get('/api/companies'),
    apiClient.get('/api/branches'),
    apiClient.get('/api/departments'),
    apiClient.get('/api/employees', { params: { per_page: 100 } }),
  ])
  companies.value = companyRes.data.data.data
  branches.value = branchRes.data.data.data
  departments.value = departmentRes.data.data.data
  employeeOptions.value = employeeRes.data.data.data
}

function goToPage(page: number) {
  if (page < 1 || page > meta.value.last_page || page === meta.value.current_page) return
  filters.page = page
  loadReport()
}

const paginationItems = computed(() => {
  const total = meta.value.last_page
  const current = meta.value.current_page
  if (total <= 1) return []

  const items: (number | '...')[] = [1]
  const left = Math.max(2, current - 1)
  const right = Math.min(total - 1, current + 1)

  if (left > 2) items.push('...')
  for (let i = left; i <= right; i++) items.push(i)
  if (right < total - 1) items.push('...')
  items.push(total)

  return items
})

async function handleExport() {
  exporting.value = true
  try {
    const params = new URLSearchParams({
      date_from: filters.date_from,
      date_to: filters.date_to,
      ...(filters.company_id ? { company_id: String(filters.company_id) } : {}),
      ...(filters.branch_id ? { branch_id: String(filters.branch_id) } : {}),
      ...(filters.department_id ? { department_id: String(filters.department_id) } : {}),
      ...(filters.employee_id ? { employee_id: String(filters.employee_id) } : {}),
    })
    const baseUrl = apiClient.defaults.baseURL ?? ''
    window.open(`${baseUrl}/api/attendance-report/export?${params.toString()}`, '_blank')
  } finally {
    exporting.value = false
  }
}

// ---------- KPI SUMMARY (aggregat dari rows yang sedang dimuat) ----------
function formatMinutesAsHours(minutes: number): string {
  if (!minutes) return '0j'
  const h = Math.floor(minutes / 60)
  const m = Math.round(minutes % 60)
  return m ? `${h}j ${m}m` : `${h}j`
}

const kpiPalettes: Record<string, { bg: string; text: string }> = {
  primary: { bg: 'bg-primary-soft', text: 'text-primary-dark' },
  amber: { bg: 'bg-amber-50', text: 'text-amber-600' },
  red: { bg: 'bg-red-50', text: 'text-red-600' },
  violet: { bg: 'bg-violet-50', text: 'text-violet-600' },
  blue: { bg: 'bg-blue-50', text: 'text-blue-600' },
  slate: { bg: 'bg-slate-100', text: 'text-slate-600' },
}

const kpiCards = computed(() => {
  if (rows.value.length === 0) return []

  const sum = (pick: (r: SummaryRow) => number) => rows.value.reduce((acc, r) => acc + (pick(r) || 0), 0)
  const totalPresent = sum((r) => r.present_days)
  const totalLate = sum((r) => r.late_days)
  const totalAbsent = sum((r) => r.absent_days)
  const totalLeave = sum((r) => r.leave_days)
  const totalOvertimeMinutes = sum((r) => r.overtime_minutes)
  const totalWorkingHours = sum((r) => r.working_hours)
  const rates = rows.value.map((r) => r.attendance_rate).filter((v): v is number => v !== null)
  const avgRate = rates.length ? Math.round(rates.reduce((a, b) => a + b, 0) / rates.length) : null

  return [
    { key: 'present', label: 'Present', value: totalPresent, icon: CheckCircle2, palette: 'primary' },
    { key: 'late', label: 'Late', value: totalLate, icon: Clock, palette: 'amber' },
    { key: 'absent', label: 'Absent', value: totalAbsent, icon: XCircle, palette: 'red' },
    { key: 'leave', label: 'Leave', value: totalLeave, icon: CalendarOff, palette: 'violet' },
    { key: 'overtime', label: 'Overtime Hours', value: formatMinutesAsHours(totalOvertimeMinutes), icon: Timer, palette: 'blue' },
    { key: 'working_hours', label: 'Working Hours', value: `${Math.round(totalWorkingHours * 10) / 10}j`, icon: BarChart3, palette: 'slate' },
    { key: 'rate', label: 'Attendance Rate', value: avgRate !== null ? `${avgRate}%` : '-', icon: TrendingUp, palette: 'primary' },
  ]
})

// --- Indikator visual attendance rate per baris ---
const ATTENTION_THRESHOLD = 75 // ambang batas visual doang, gak ngubah data

function rateBarClass(rate: number | null): string {
  if (rate === null) return 'bg-slate-200'
  if (rate >= 90) return 'bg-primary'
  if (rate >= ATTENTION_THRESHOLD) return 'bg-amber-400'
  return 'bg-red-400'
}

function rateTextClass(rate: number | null): string {
  if (rate === null) return 'text-slate-400'
  if (rate >= 90) return 'text-primary-dark'
  if (rate >= ATTENTION_THRESHOLD) return 'text-amber-600'
  return 'text-red-600'
}

function needsAttention(row: SummaryRow): boolean {
  return row.attendance_rate !== null && row.attendance_rate < ATTENTION_THRESHOLD
}

// ---------- DRILL-DOWN (sekarang sebagai drawer) ----------
const showDrilldown = ref(false)
const drilldownEmployee = ref<SummaryRow | null>(null)
const drilldownRows = ref<DailyRow[]>([])
const drilldownLoading = ref(false)

const statusLabels: Record<string, string> = {
  present: 'Present', late: 'Late', absent: 'Absent',
  half_day: 'Half Day', leave: 'Leave', sick: 'Sick', alpha: 'Alpha',
}

const statusDotClass: Record<string, string> = {
  present: 'bg-primary-soft text-primary-dark',
  late: 'bg-amber-50 text-amber-600',
  absent: 'bg-red-50 text-red-600',
  half_day: 'bg-blue-50 text-blue-600',
  leave: 'bg-violet-50 text-violet-600',
  sick: 'bg-orange-50 text-orange-600',
  alpha: 'bg-slate-100 text-slate-500',
}

const approvalTypeLabels: Record<string, string> = {
  late: 'Keterlambatan',
  overtime: 'Lembur',
  correction: 'Koreksi Attendance',
}

const approvalStatusLabels: Record<string, string> = {
  pending: 'Menunggu',
  approved: 'Disetujui',
  rejected: 'Ditolak',
}

const approvalStatusClass: Record<string, string> = {
  pending: 'bg-amber-50 text-amber-600',
  approved: 'bg-primary-soft text-primary-dark',
  rejected: 'bg-red-50 text-red-600',
}

function formatTime(value: string | null) {
  if (!value) return '-'
  return new Date(value.replace(' ', 'T')).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
}

function dayCellClass(day: DailyRow): string {
  if (day.is_holiday) return 'bg-slate-100 text-slate-400'
  if (!day.status) return 'border border-dashed border-slate-200 text-slate-300'
  return statusDotClass[day.status] ?? 'bg-slate-100 text-slate-500'
}

async function openDrilldown(row: SummaryRow) {
  drilldownEmployee.value = row
  showDrilldown.value = true
  drilldownLoading.value = true
  drilldownRows.value = []

  try {
    const response = await apiClient.get(`/api/attendance-report/employees/${row.employee.id}/daily`, {
      params: { date_from: filters.date_from, date_to: filters.date_to },
    })
    drilldownRows.value = response.data.data
  } catch {
    // biarin kosong, gak fatal
  } finally {
    drilldownLoading.value = false
  }
}

function closeDrilldown() {
  showDrilldown.value = false
  drilldownEmployee.value = null
}

onMounted(() => {
  applyPreset('month')
  loadReferenceData()
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Attendance Report</h1>
        <p class="mt-1 text-sm text-slate-500">Ringkasan kehadiran per employee dalam periode yang dipilih.</p>
      </div>
      <button
        @click="handleExport"
        :disabled="exporting"
        class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50"
      >
        <Download class="h-4 w-4" :stroke-width="1.75" />
        Export Excel
      </button>
    </div>

    <!-- KPI Summary -->
    <div v-if="kpiCards.length" class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
      <div
        v-for="card in kpiCards"
        :key="card.key"
        class="rounded-2xl border border-slate-100 bg-white p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)]"
      >
        <div class="flex h-8 w-8 items-center justify-center rounded-lg" :class="kpiPalettes[card.palette].bg">
          <component :is="card.icon" class="h-4 w-4" :class="kpiPalettes[card.palette].text" :stroke-width="1.75" />
        </div>
        <p class="mt-3 text-xl font-semibold tracking-tight text-slate-900">{{ card.value }}</p>
        <p class="mt-0.5 text-xs text-slate-500">{{ card.label }}</p>
      </div>
    </div>

    <!-- Filter -->
    <div class="space-y-3 rounded-2xl border border-slate-100 bg-white p-4">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-2">
          <button @click="applyPreset('today')" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">Today</button>
          <button @click="applyPreset('week')" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">This Week</button>
          <button @click="applyPreset('month')" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">This Month</button>
          <button @click="applyPreset('last_month')" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">Last Month</button>
          <button @click="applyPreset('payroll')" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">Payroll Period</button>
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

      <div class="flex flex-wrap items-end gap-3">
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-500">Dari</label>
          <input v-model="filters.date_from" type="date" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-500">Sampai</label>
          <input v-model="filters.date_to" type="date" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
        </div>
        <button
          @click="filters.page = 1; loadReport()"
          class="flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-slate-900"
        >
          <Search class="h-4 w-4" :stroke-width="1.75" />
          Terapkan
        </button>
      </div>

      <Transition
        enter-active-class="transition-all duration-150 ease-out"
        enter-from-class="opacity-0 -translate-y-1"
        enter-to-class="opacity-100 translate-y-0"
      >
        <div v-if="showFilters" class="flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4">
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
            <label class="mb-1 block text-xs font-medium text-slate-500">Employee</label>
            <select v-model="filters.employee_id" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
              <option :value="null">Semua</option>
              <option v-for="e in employeeOptions" :key="e.id" :value="e.id">{{ employeeOptionLabel(e) }}</option>
            </select>
          </div>
        </div>
      </Transition>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>
    <div v-else-if="rows.length === 0" class="rounded-xl bg-slate-50 p-6 text-center text-sm text-slate-400">
      Tidak ada data untuk filter ini.
    </div>

    <!-- Table -->
    <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead>
            <tr class="border-b border-slate-100 bg-slate-50/60">
              <th class="px-5 py-3 font-medium text-slate-500">Employee</th>
              <th class="px-3 py-3 text-center font-medium text-slate-500">Present</th>
              <th class="px-3 py-3 text-center font-medium text-slate-500">Late</th>
              <th class="px-3 py-3 text-center font-medium text-slate-500">Overtime</th>
              <th class="px-3 py-3 text-center font-medium text-slate-500">Leave</th>
              <th class="px-3 py-3 text-center font-medium text-slate-500">Absent</th>
              <th class="px-3 py-3 text-center font-medium text-slate-500">Working Hours</th>
              <th class="px-3 py-3 font-medium text-slate-500">Attendance Rate</th>
              <th class="px-5 py-3"></th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in rows"
              :key="row.employee.id"
              @click="openDrilldown(row)"
              class="cursor-pointer border-b border-slate-50 last:border-0 hover:bg-slate-50/50"
            >
              <td class="px-5 py-3.5">
                <div class="flex items-center gap-1.5">
                  <p class="font-medium text-slate-800">{{ row.employee.name }}</p>
                  <AlertTriangle v-if="needsAttention(row)" class="h-3.5 w-3.5 text-amber-500" :stroke-width="2" />
                </div>
                <p class="text-xs text-slate-400">{{ row.employee.employee_number }}</p>
              </td>
              <td class="px-3 py-3.5 text-center text-slate-600">{{ row.present_days }}</td>
              <td class="px-3 py-3.5 text-center">
                <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-600">{{ row.late_days }}</span>
              </td>
              <td class="px-3 py-3.5 text-center">
                <span class="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-600">{{ row.overtime_minutes }}m</span>
              </td>
              <td class="px-3 py-3.5 text-center text-slate-600">{{ row.leave_days }}</td>
              <td class="px-3 py-3.5 text-center">
                <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-600">{{ row.absent_days }}</span>
              </td>
              <td class="px-3 py-3.5 text-center text-slate-600">{{ row.working_hours }} jam</td>
              <td class="px-3 py-3.5">
                <div class="flex items-center gap-2">
                  <div class="h-1.5 w-16 overflow-hidden rounded-full bg-slate-100">
                    <div
                      class="h-full rounded-full transition-all"
                      :class="rateBarClass(row.attendance_rate)"
                      :style="{ width: `${Math.min(row.attendance_rate ?? 0, 100)}%` }"
                    ></div>
                  </div>
                  <span class="text-xs font-medium" :class="rateTextClass(row.attendance_rate)">
                    {{ row.attendance_rate !== null ? `${row.attendance_rate}%` : '-' }}
                  </span>
                </div>
              </td>
              <td class="px-5 py-3.5 text-right">
                <ChevronRight class="ml-auto h-4 w-4 text-slate-300" :stroke-width="1.75" />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="meta.last_page > 1" class="flex items-center justify-between text-sm text-slate-500">
      <p>Total {{ meta.total }} employee</p>
      <div class="flex items-center gap-1">
        <button
          type="button"
          @click="goToPage(meta.current_page - 1)"
          :disabled="meta.current_page === 1"
          class="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 disabled:opacity-30 disabled:hover:bg-transparent"
        >
          <ChevronLeft class="h-4 w-4" :stroke-width="2" />
        </button>
        <template v-for="(item, i) in paginationItems" :key="i">
          <span v-if="item === '...'" class="px-2 text-xs text-slate-300">...</span>
          <button
            v-else
            type="button"
            @click="goToPage(item)"
            class="min-w-[32px] rounded-lg px-2.5 py-1.5 text-xs font-medium transition-colors"
            :class="item === meta.current_page ? 'bg-primary text-white' : 'text-slate-500 hover:bg-slate-100'"
          >
            {{ item }}
          </button>
        </template>
        <button
          type="button"
          @click="goToPage(meta.current_page + 1)"
          :disabled="meta.current_page === meta.last_page"
          class="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 disabled:opacity-30 disabled:hover:bg-transparent"
        >
          <ChevronRight class="h-4 w-4" :stroke-width="2" />
        </button>
      </div>
    </div>

    <!-- Drawer: employee detail -->
    <Teleport to="body">
      <div v-if="showDrilldown" class="fixed inset-0 z-50">
        <Transition
          enter-active-class="transition-opacity duration-200"
          enter-from-class="opacity-0"
          enter-to-class="opacity-100"
          leave-active-class="transition-opacity duration-150"
          leave-from-class="opacity-100"
          leave-to-class="opacity-0"
          appear
        >
          <div class="absolute inset-0 bg-slate-900/30" @click="closeDrilldown"></div>
        </Transition>

        <Transition
          enter-active-class="transition-transform duration-200 ease-out"
          enter-from-class="translate-x-full"
          enter-to-class="translate-x-0"
          leave-active-class="transition-transform duration-150 ease-in"
          leave-from-class="translate-x-0"
          leave-to-class="translate-x-full"
          appear
        >
          <aside class="absolute right-0 top-0 flex h-full w-full max-w-5xl flex-col bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
              <div>
                <h2 class="text-lg font-semibold text-slate-900">{{ drilldownEmployee?.employee.name }}</h2>
                <p class="text-sm text-slate-500">
                  {{ drilldownEmployee?.employee.employee_number }} · {{ filters.date_from }} s/d {{ filters.date_to }}
                </p>
              </div>
              <button @click="closeDrilldown" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50">
                <X class="h-5 w-5" />
              </button>
            </div>

            <div class="flex-1 overflow-y-auto px-6 py-5">
              <!-- KPI ringkasan employee -->
              <div v-if="drilldownEmployee" class="grid grid-cols-3 gap-3 sm:grid-cols-6">
                <div class="rounded-xl border border-slate-100 p-3">
                  <p class="text-lg font-semibold text-slate-900">{{ drilldownEmployee.present_days }}</p>
                  <p class="text-[11px] text-slate-500">Present</p>
                </div>
                <div class="rounded-xl border border-slate-100 p-3">
                  <p class="text-lg font-semibold text-amber-600">{{ drilldownEmployee.late_days }}</p>
                  <p class="text-[11px] text-slate-500">Late</p>
                </div>
                <div class="rounded-xl border border-slate-100 p-3">
                  <p class="text-lg font-semibold text-red-600">{{ drilldownEmployee.absent_days }}</p>
                  <p class="text-[11px] text-slate-500">Absent</p>
                </div>
                <div class="rounded-xl border border-slate-100 p-3">
                  <p class="text-lg font-semibold text-violet-600">{{ drilldownEmployee.leave_days }}</p>
                  <p class="text-[11px] text-slate-500">Leave</p>
                </div>
                <div class="rounded-xl border border-slate-100 p-3">
                  <p class="text-lg font-semibold text-blue-600">{{ formatMinutesAsHours(drilldownEmployee.overtime_minutes) }}</p>
                  <p class="text-[11px] text-slate-500">Overtime</p>
                </div>
                <div class="rounded-xl border border-slate-100 p-3">
                  <p class="text-lg font-semibold" :class="rateTextClass(drilldownEmployee.attendance_rate)">
                    {{ drilldownEmployee.attendance_rate !== null ? `${drilldownEmployee.attendance_rate}%` : '-' }}
                  </p>
                  <p class="text-[11px] text-slate-500">Attendance Rate</p>
                </div>
              </div>

              <div v-if="drilldownLoading" class="flex items-center justify-center gap-2 py-10 text-sm text-slate-400">
                <Loader2 class="h-4 w-4 animate-spin" :stroke-width="2" />
                Memuat rekap harian...
              </div>

              <template v-else>
                <!-- Timeline -->
                <div class="mt-6">
                  <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Timeline Kehadiran</p>
                  <div class="flex gap-1.5 overflow-x-auto pb-2">
                    <div
                      v-for="day in drilldownRows"
                      :key="day.date"
                      :title="`${day.date} — ${day.is_holiday ? 'Libur' : statusLabels[day.status ?? ''] ?? 'Belum ada data'}`"
                      class="flex h-9 w-9 shrink-0 flex-col items-center justify-center rounded-lg text-[11px] font-medium"
                      :class="dayCellClass(day)"
                    >
                      {{ day.date.slice(-2) }}
                    </div>
                  </div>
                  <div class="mt-2 flex flex-wrap gap-3 text-[11px] text-slate-500">
                    <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-primary-soft"></span>Present</span>
                    <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-amber-100"></span>Late</span>
                    <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-red-100"></span>Absent</span>
                    <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-violet-100"></span>Leave</span>
                    <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-slate-200"></span>Libur</span>
                  </div>
                </div>

                <!-- Rekap harian -->
                <div class="mt-6">
                  <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Rekap Harian</p>
                  <div class="overflow-x-auto rounded-xl border border-slate-100">
                    <table class="w-full text-left text-sm">
                      <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/60">
                          <th class="px-3 py-2 font-medium text-slate-500">Tanggal</th>
                          <th class="px-3 py-2 font-medium text-slate-500">Shift</th>
                          <th class="px-3 py-2 font-medium text-slate-500">Clock In</th>
                          <th class="px-3 py-2 font-medium text-slate-500">Clock Out</th>
                          <th class="px-3 py-2 font-medium text-slate-500">Late</th>
                          <th class="px-3 py-2 font-medium text-slate-500">Overtime</th>
                          <th class="px-3 py-2 font-medium text-slate-500">Working Hours</th>
                          <th class="px-3 py-2 font-medium text-slate-500">Status</th>
                          <th class="px-3 py-2 font-medium text-slate-500">Approval</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr
                          v-for="day in drilldownRows"
                          :key="day.date"
                          class="border-b border-slate-50 last:border-0"
                          :class="day.is_holiday ? 'bg-slate-50/50' : ''"
                        >
                          <td class="px-3 py-2 text-slate-600">
                            {{ day.date }}
                            <span class="block text-xs text-slate-400">{{ day.day_name }}{{ day.is_holiday ? ' · Libur' : '' }}</span>
                          </td>
                          <td class="px-3 py-2 text-slate-500">{{ day.shift?.name ?? '-' }}</td>
                          <td class="px-3 py-2 text-slate-500">{{ formatTime(day.clock_in) }}</td>
                          <td class="px-3 py-2 text-slate-500">{{ formatTime(day.clock_out) }}</td>
                          <td class="px-3 py-2 text-slate-500">
                            <span v-if="day.late_minutes">{{ day.approved_late_minutes ?? day.late_minutes }}m</span>
                            <span v-else class="text-slate-300">-</span>
                          </td>
                          <td class="px-3 py-2 text-slate-500">
                            <span v-if="day.detected_overtime_minutes">{{ day.approved_overtime_minutes ?? day.detected_overtime_minutes }}m</span>
                            <span v-else class="text-slate-300">-</span>
                          </td>
                          <td class="px-3 py-2 text-slate-500">
                            <span v-if="day.working_minutes != null">{{ formatMinutesAsHours(day.working_minutes) }}</span>
                            <span v-else class="text-slate-300">-</span>
                          </td>
                          <td class="px-3 py-2">
                            <span v-if="day.status" class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusDotClass[day.status] ?? 'bg-slate-100 text-slate-600'">
                              {{ statusLabels[day.status] ?? day.status }}
                            </span>
                            <span v-else class="text-slate-300">-</span>
                          </td>
                          <td class="px-3 py-2">
                            <div v-if="day.approval_requests.length" class="flex flex-wrap gap-1">
                              <span
                                v-for="(req, i) in day.approval_requests"
                                :key="i"
                                class="rounded-full px-2 py-0.5 text-[11px] font-medium"
                                :class="approvalStatusClass[req.status] ?? 'bg-slate-100 text-slate-600'"
                              >
                                {{ approvalTypeLabels[req.type] ?? req.type }} · {{ approvalStatusLabels[req.status] ?? req.status }}
                              </span>
                            </div>
                            <span v-else class="text-slate-300">-</span>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </template>
            </div>
          </aside>
        </Transition>
      </div>
    </Teleport>
  </div>
</template>