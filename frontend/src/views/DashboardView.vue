<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import {
  ChevronDown, ChevronLeft, ChevronRight, Fingerprint, CalendarDays, Clock, Wallet,
  HeartPulse, Hourglass, Megaphone, MoreVertical, UserRound, Gift, Flag, ArrowRight,
  Receipt, History, Folder, ShieldCheck, HelpCircle, Timer, CheckCircle2, Loader2,
  FileText, GraduationCap, BarChart3, MoreHorizontal,
} from 'lucide-vue-next'
import apiClient from '@/lib/axios'
import AttendanceSelfServiceCard from '@/views/AttendanceSelfServiceCard.vue'

const router = useRouter()
const authStore = useAuthStore()

interface DashboardData {
  user: { id: number; name: string; email: string }
  roles: string[]
  permissions: string[]
}

const data = ref<DashboardData | null>(null)
const loading = ref(true)
const error = ref('')

const greeting = computed(() => {
  const hour = new Date().getHours()
  if (hour < 11) return 'Selamat Pagi'
  if (hour < 15) return 'Selamat Siang'
  if (hour < 19) return 'Selamat Sore'
  return 'Selamat Malam'
})

const formattedDate = computed(() =>
  new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
)

async function loadDashboard() {
  loading.value = true
  error.value = ''
  try {
    const response = await apiClient.get('/api/dashboard')
    data.value = response.data.data
  } catch {
    error.value = 'Gagal memuat dashboard. Coba refresh halaman.'
  } finally {
    loading.value = false
  }
}

// ---------- Live clock ----------
const now = ref(new Date())
let clockTimer: ReturnType<typeof setInterval> | null = null

const formattedClock = computed(() =>
  now.value.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
)

// ---------- Shortcut actions ----------
const shortcuts = [
  { icon: Fingerprint, title: 'Live Attendance', subtitle: 'Clock in/out' },
  { icon: CalendarDays, title: 'Request Leave', subtitle: 'Ajukan cuti' },
  { icon: Clock, title: 'Request Overtime', subtitle: 'Ajukan lembur' },
  { icon: Wallet, title: 'Request Reimbursement', subtitle: 'Penggantian biaya' },
]

// ---------- Leave Balance ----------
const leaveBalances = ref([
  { icon: CalendarDays, name: 'Annual Leave', used: 12, total: 15, remaining: 12 },
  { icon: HeartPulse, name: 'Sick Leave', used: 6, total: 10, remaining: 6 },
])
const pendingLeaveCount = ref(1)

function balancePercent(b: { used: number; total: number }) {
  return b.total > 0 ? Math.min(100, Math.round((b.used / b.total) * 100)) : 0
}

// ---------- Who's Off Today ----------
interface WhosOffRow {
  id: number
  employee: { name: string; photo_url: string | null }
  leave_type: { name: string; color: string | null }
}

const whosOffToday = ref<WhosOffRow[]>([])
const whosOffLoading = ref(true)

function initialsFromName(name: string): string {
  return name.split(' ').filter(Boolean).slice(0, 2).map((w) => w[0]?.toUpperCase()).join('')
}

async function loadWhosOffToday() {
  whosOffLoading.value = true
  try {
    const today = new Date()
    const todayStr = today.toISOString().slice(0, 10)

    const response = await apiClient.get('/api/leave-calendar', {
      params: {
        year: today.getFullYear(),
        month: today.getMonth() + 1,
        status: ['approved'],
      },
    })

    const leaves = response.data.data.leaves as Array<{
      id: number
      employee: { name: string; photo_url: string | null }
      leave_type: { name: string; color: string | null }
      start_date: string
      end_date: string
    }>

    whosOffToday.value = leaves.filter((lv) => todayStr >= lv.start_date && todayStr <= lv.end_date)
  } catch {
    whosOffToday.value = []
  } finally {
    whosOffLoading.value = false
  }
}

// ---------- Announcement tab ----------
const activeTab = ref<'announcement' | 'contract' | 'tasks'>('announcement')

interface AnnouncementPreviewRow {
  id: number
  read_at: string | null
  announcement: {
    id: number
    title: string
    content: string
    published_at: string | null
    category: { id: number; name: string } | null
  }
}

const announcements = ref<AnnouncementPreviewRow[]>([])
const announcementsLoading = ref(true)
const announcementsError = ref('')

async function loadAnnouncements() {
  announcementsLoading.value = true
  announcementsError.value = ''
  try {
    const response = await apiClient.get('/api/my-announcements')
    announcements.value = (response.data.data.data as AnnouncementPreviewRow[]).slice(0, 5)
  } catch {
    announcementsError.value = 'Gagal memuat announcement.'
  } finally {
    announcementsLoading.value = false
  }
}

function formatRelativeDate(dateStr: string | null) {
  if (!dateStr) return '-'
  const date = new Date(dateStr)
  const diffMin = Math.floor((Date.now() - date.getTime()) / 60000)
  if (diffMin < 1) return 'Baru saja'
  if (diffMin < 60) return `${diffMin} menit lalu`
  const diffHour = Math.floor(diffMin / 60)
  if (diffHour < 24) return `${diffHour} jam lalu`
  const diffDay = Math.floor(diffHour / 24)
  if (diffDay < 7) return `${diffDay} hari lalu`
  return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

// ---------- Contract & Probation ----------
interface ContractProbationPreviewItem {
  type: 'contract' | 'probation'
  end_date: string
  remaining_days: number
  employee: { id: number; employee_number: string; name: string; photo_url: string | null; position: string | null }
}

interface ContractProbationSummary {
  contract_ending_soon: number
  probation_ending_soon: number
  preview: ContractProbationPreviewItem[]
}

const contractProbation = ref<ContractProbationSummary | null>(null)
const contractProbationLoading = ref(true)
const contractProbationForbidden = ref(false)
const contractProbationError = ref('')

async function loadContractProbation() {
  contractProbationLoading.value = true
  contractProbationForbidden.value = false
  contractProbationError.value = ''
  try {
    const response = await apiClient.get('/api/employees/contract-probation/summary')
    contractProbation.value = response.data.data
  } catch (err) {
    const status = (err as { response?: { status?: number } })?.response?.status
    if (status === 403) {
      contractProbationForbidden.value = true
    } else {
      contractProbationError.value = 'Gagal memuat data Contract & Probation.'
    }
  } finally {
    contractProbationLoading.value = false
  }
}

function urgencyClass(days: number) {
  if (days <= 7) return 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-100'
  if (days <= 14) return 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-100'
  return 'bg-slate-50 text-slate-600 ring-1 ring-inset ring-slate-100'
}

function remainingLabel(days: number) {
  if (days === 0) return 'Hari ini'
  return `${days} hari`
}

// ---------- Upcoming & Pending ----------
const upcomingPending = [
  { icon: Clock, palette: 'amber', title: 'Pending Leave', subtitle: 'Pengajuan cuti menunggu persetujuan', count: 1 },
  { icon: Timer, palette: 'amber', title: 'Pending Overtime', subtitle: 'Pengajuan lembur menunggu persetujuan', count: 2 },
  { icon: Wallet, palette: 'violet', title: 'Pending Reimbursement', subtitle: 'Penggantian biaya menunggu persetujuan', count: 1 },
  { icon: CheckCircle2, palette: 'primary', title: 'Approved This Week', subtitle: 'Pengajuan disetujui minggu ini', count: 3 },
]
const palettes: Record<string, { bg: string; text: string }> = {
  amber: { bg: 'bg-amber-50', text: 'text-amber-600' },
  violet: { bg: 'bg-violet-50', text: 'text-violet-600' },
  primary: { bg: 'bg-primary-soft', text: 'text-primary-dark' },
}

// ---------- Mini calendar ----------
const calYear = ref(new Date().getFullYear())
const calMonth = ref(new Date().getMonth() + 1)
const monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']
const weekDaysShort = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']

function toDateStr(d: Date) {
  return d.toISOString().slice(0, 10)
}

const miniCalendarDays = computed(() => {
  const firstOfMonth = new Date(calYear.value, calMonth.value - 1, 1)
  const isoOffset = (firstOfMonth.getDay() + 6) % 7
  const gridStart = new Date(firstOfMonth)
  gridStart.setDate(gridStart.getDate() - isoOffset)

  const todayStr = toDateStr(new Date())
  const days = [] as Array<{ date: string; dayNumber: number; isCurrentMonth: boolean; isToday: boolean }>
  for (let i = 0; i < 42; i++) {
    const d = new Date(gridStart)
    d.setDate(gridStart.getDate() + i)
    days.push({
      date: toDateStr(d),
      dayNumber: d.getDate(),
      isCurrentMonth: d.getMonth() === calMonth.value - 1,
      isToday: toDateStr(d) === todayStr,
    })
  }
  return days
})

function calPrevMonth() {
  if (calMonth.value === 1) { calMonth.value = 12; calYear.value-- } else { calMonth.value-- }
}
function calNextMonth() {
  if (calMonth.value === 12) { calMonth.value = 1; calYear.value++ } else { calMonth.value++ }
}

// ---------- Quick links ----------
const quickLinks: Array<{ icon: any; label: string; to?: string }> = [
  { icon: UserRound, label: 'My Profile' },
  { icon: Receipt, label: 'My Payslip', to: '/my-payslips' },
  { icon: History, label: 'Attendance History' },
  { icon: CalendarDays, label: 'Leave History' },
  { icon: Folder, label: 'Documents' },
  { icon: ShieldCheck, label: 'Company Policy' },
  { icon: HelpCircle, label: 'Help Center' },
]

function goToQuickLink(link: { to?: string }) {
  if (link.to) router.push(link.to)
}

// ---------- Next Holiday ----------
const nextHolidayDate = new Date(2026, 7, 17)
const nextHolidayLabel = 'Hari Kemerdekaan RI'
const daysUntilHoliday = computed(() => {
  const diff = Math.ceil((nextHolidayDate.getTime() - now.value.getTime()) / (1000 * 60 * 60 * 24))
  return Math.max(0, diff)
})

// ---------- Today's Birthday ----------
const todaysBirthday = ref<{ name: string; position: string } | null>({ name: 'Ilham Hadiwijaya', position: 'UI/UX Designer' })

// ---------- Pending Action ----------
const pendingActionCount = ref(2)

// ============================================================================
// MOBILE APP-LIKE UI
// ============================================================================
// Data chart ini masih UI/demo karena file sumber yang diberikan belum
// menyediakan endpoint Working Time Hours. Tinggal replace nilai ini dengan
// response API ketika endpoint working-hours sudah tersedia.
const workingHours = ref([
  { day: 'Mon', hours: 8.0 },
  { day: 'Tue', hours: 7.5 },
  { day: 'Wed', hours: 9.0 },
  { day: 'Thu', hours: 8.0 },
  { day: 'Fri', hours: 6.75 },
])

const workingHoursTotal = computed(() =>
  workingHours.value.reduce((total, item) => total + item.hours, 0)
)

const workingHoursTotalLabel = computed(() => {
  const totalMinutes = Math.round(workingHoursTotal.value * 60)
  const hours = Math.floor(totalMinutes / 60)
  const minutes = totalMinutes % 60
  return `${hours}h ${minutes.toString().padStart(2, '0')}m`
})

const workingHoursChange = computed(() => '+4.2%')
const workingHoursMax = computed(() => Math.max(10, ...workingHours.value.map((item) => item.hours)))
const selectedWorkingDay = ref('Fri')
const mobileMoreOpen = ref(false)

const workingChartPoints = computed(() => {
  const width = 320
  const height = 112
  const left = 8
  const right = 8
  const top = 10
  const bottom = 12
  const usableWidth = width - left - right
  const usableHeight = height - top - bottom
  const count = workingHours.value.length
  const stepX = count > 1 ? usableWidth / (count - 1) : usableWidth

  return workingHours.value.map((item, index) => {
    const x = left + index * stepX
    const y = top + usableHeight - (item.hours / workingHoursMax.value) * usableHeight
    return { ...item, x, y }
  })
})

const workingChartPolyline = computed(() =>
  workingChartPoints.value.map((point) => `${point.x},${point.y}`).join(' ')
)

const workingChartArea = computed(() => {
  const points = workingChartPoints.value
  if (!points.length) return ''
  const baseY = 100
  return `${points[0].x},${baseY} ${points.map((point) => `${point.x},${point.y}`).join(' ')} ${points[points.length - 1].x},${baseY}`
})

const workingChartSelectedPoint = computed(() =>
  workingChartPoints.value.find((point) => point.day === selectedWorkingDay.value) ?? workingChartPoints.value.at(-1)
)

function selectWorkingDay(day: string) {
  selectedWorkingDay.value = day
}

function openMobileMore() {
  mobileMoreOpen.value = true
}

function closeMobileMore() {
  mobileMoreOpen.value = false
}

function goMobilePayslip() {
  router.push('/my-payslips')
}

onMounted(() => {
  loadDashboard()
  loadWhosOffToday()
  loadAnnouncements()
  loadContractProbation()
  clockTimer = setInterval(() => { now.value = new Date() }, 1000)
})

onUnmounted(() => {
  if (clockTimer) clearInterval(clockTimer)
})
</script>

<template>
  <div v-if="loading" class="text-sm text-slate-400">Memuat dashboard...</div>

  <div v-else-if="error" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">
    {{ error }}
  </div>

  <div v-else-if="data" class="space-y-5">
    <!-- ================================================================== -->
    <!-- DESKTOP — intentionally kept in the existing dashboard structure   -->
    <!-- ================================================================== -->
    <div class="hidden lg:block space-y-5">
      <!-- Greeting card -->
      <div class="overflow-hidden rounded-2xl border border-slate-100 bg-white p-6 shadow-[0_1px_3px_rgba(15,23,42,0.04)] sm:p-8">
        <div class="flex flex-col justify-between gap-6 sm:flex-row sm:items-start">
          <div class="flex-1">
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">
              {{ greeting }}, <span class="uppercase text-primary-dark">{{ data.user.name }}</span>! 👋
            </h1>
            <p class="mt-1 text-sm text-slate-500">{{ formattedDate }}</p>

            <div class="mt-4 flex flex-wrap gap-1.5">
              <span
                v-for="role in data.roles"
                :key="role"
                class="rounded-full bg-primary-soft px-2.5 py-1 text-xs font-medium text-primary-dark"
              >
                {{ role }}
              </span>
            </div>

            <div class="mt-5">
              <p class="mb-2.5 text-xs font-medium text-slate-400">Shortcut</p>
              <div class="flex flex-wrap gap-2">
                <button
                  v-for="s in shortcuts"
                  :key="s.title"
                  type="button"
                  class="flex items-center gap-2.5 rounded-xl border border-slate-200 px-3.5 py-2.5 text-left transition-colors hover:border-primary/40 hover:bg-primary-soft/30"
                >
                  <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary-soft text-primary-dark">
                    <component :is="s.icon" class="h-4 w-4" :stroke-width="1.75" />
                  </div>
                  <div>
                    <p class="text-xs font-semibold text-slate-800">{{ s.title }}</p>
                    <p class="text-[11px] text-slate-400">{{ s.subtitle }}</p>
                  </div>
                </button>
                <button
                  type="button"
                  class="flex items-center gap-1 rounded-xl border border-slate-200 px-3.5 py-2.5 text-xs font-medium text-slate-500 hover:bg-slate-50"
                >
                  More
                  <ChevronDown class="h-3.5 w-3.5" :stroke-width="2" />
                </button>
              </div>
            </div>
          </div>

          <!-- Illustration -->
          <svg viewBox="0 0 180 160" class="hidden h-32 w-40 shrink-0 sm:block">
            <rect x="15" y="128" width="150" height="8" rx="4" class="fill-slate-200" />
            <rect x="30" y="136" width="6" height="18" class="fill-slate-200" />
            <rect x="144" y="136" width="6" height="18" class="fill-slate-200" />
            <rect x="65" y="100" width="50" height="30" rx="3" class="fill-slate-700" />
            <rect x="69" y="103" width="42" height="22" rx="2" class="fill-primary/20" />
            <rect x="58" y="128" width="64" height="5" rx="2.5" class="fill-slate-300" />
            <path d="M52 128 C52 100 68 88 90 88 C112 88 128 100 128 128 Z" class="fill-primary-soft" />
            <circle cx="90" cy="60" r="24" class="fill-primary-dark" />
            <g transform="translate(128,20)">
              <rect width="34" height="26" rx="10" class="fill-emerald-500" />
              <path d="M9 13 L14.5 18.5 L25 7" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
              <path d="M14 26 L8 34 L18 26 Z" class="fill-emerald-500" />
            </g>
          </svg>
        </div>
      </div>

      <!-- Row: Attendance / Leave Balance / Who's Off Today -->
      <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <AttendanceSelfServiceCard />

        <div class="min-w-0 rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
          <div class="mb-4 flex items-center justify-between">
            <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Leave Balance</h3>
            <button type="button" class="text-xs font-medium text-primary-dark hover:underline">View detail ›</button>
          </div>

          <div class="space-y-4">
            <div v-for="b in leaveBalances" :key="b.name">
              <div class="flex items-center justify-between text-sm">
                <span class="flex items-center gap-2 font-medium text-slate-700">
                  <component :is="b.icon" class="h-4 w-4 text-primary-dark" :stroke-width="1.75" />
                  {{ b.name }}
                </span>
                <span class="text-xs font-medium text-slate-500">{{ b.remaining }} Days Left</span>
              </div>
              <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-primary" :style="{ width: `${balancePercent(b)}%` }"></div>
              </div>
              <p class="mt-1 text-[11px] text-slate-400">{{ b.used }} / {{ b.total }} Days</p>
            </div>

            <div class="flex items-center gap-2 border-t border-slate-100 pt-3 text-sm">
              <Hourglass class="h-4 w-4 text-amber-500" :stroke-width="1.75" />
              <div>
                <p class="font-medium text-slate-700">Pending Request <span class="text-slate-400">({{ pendingLeaveCount }} Request(s))</span></p>
                <p class="text-[11px] text-slate-400">Menunggu persetujuan</p>
              </div>
            </div>
          </div>
        </div>

        <div class="min-w-0 rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
          <div class="mb-4 flex items-center justify-between">
            <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Who's Off Today</h3>
            <button type="button" class="text-xs font-medium text-primary-dark hover:underline">View all ›</button>
          </div>

          <div v-if="whosOffLoading" class="flex items-center gap-2 py-6 text-xs text-slate-400">
            <Loader2 class="h-4 w-4 animate-spin" :stroke-width="2" />
            Memuat...
          </div>
          <div v-else-if="whosOffToday.length === 0" class="py-6 text-center text-xs text-slate-400">
            Semua karyawan masuk hari ini.
          </div>
          <div v-else class="space-y-3">
            <div v-for="row in whosOffToday.slice(0, 3)" :key="row.id" class="flex items-center gap-3">
              <img v-if="row.employee.photo_url" :src="row.employee.photo_url" alt="" class="h-9 w-9 shrink-0 rounded-full object-cover" />
              <div v-else class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-soft text-xs font-semibold text-primary-dark">
                {{ initialsFromName(row.employee.name) }}
              </div>
              <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium text-slate-800">{{ row.employee.name }}</p>
                <p class="truncate text-xs font-medium" :style="{ color: row.leave_type.color ?? '#64748B' }">{{ row.leave_type.name }}</p>
              </div>
            </div>
            <button v-if="whosOffToday.length > 3" type="button" class="text-xs font-medium text-primary-dark hover:underline">
              + {{ whosOffToday.length - 3 }} more
            </button>
          </div>
        </div>
      </div>

      <!-- Row: Announcement / Upcoming & Pending / Calendar -->
      <div class="grid grid-cols-1 gap-4 lg:grid-cols-[2fr_1.3fr_1.3fr]">
        <div class="min-w-0 rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
          <div class="mb-4 flex gap-4 border-b border-slate-100 text-sm">
            <button type="button" @click="activeTab = 'announcement'" class="border-b-2 pb-2 font-medium transition-colors" :class="activeTab === 'announcement' ? 'border-primary text-primary-dark' : 'border-transparent text-slate-400 hover:text-slate-600'">Announcement</button>
            <button type="button" @click="activeTab = 'contract'" class="border-b-2 pb-2 font-medium transition-colors" :class="activeTab === 'contract' ? 'border-primary text-primary-dark' : 'border-transparent text-slate-400 hover:text-slate-600'">Contract & Probation</button>
            <button type="button" @click="activeTab = 'tasks'" class="border-b-2 pb-2 font-medium transition-colors" :class="activeTab === 'tasks' ? 'border-primary text-primary-dark' : 'border-transparent text-slate-400 hover:text-slate-600'">Tasks</button>
          </div>

          <template v-if="activeTab === 'announcement'">
            <div class="mb-4 flex items-center gap-2.5">
              <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-soft text-xs font-semibold text-primary-dark">{{ initialsFromName(data.user.name) }}</div>
              <input type="text" placeholder="Apa yang ingin Anda umumkan?" class="flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              <button type="button" class="rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-dark">Posting</button>
            </div>

            <div v-if="announcementsLoading" class="flex items-center gap-2 py-6 text-xs text-slate-400"><Loader2 class="h-4 w-4 animate-spin" :stroke-width="2" />Memuat...</div>
            <div v-else-if="announcementsError" class="py-6 text-center text-xs text-slate-400">{{ announcementsError }}</div>
            <div v-else-if="announcements.length === 0" class="py-6 text-center text-xs text-slate-400">Belum ada announcement untuk kamu.</div>
            <div v-else class="space-y-4">
              <div v-for="row in announcements" :key="row.id" class="flex min-w-0 items-start gap-3">
                <div class="relative flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-soft text-primary-dark">
                  <Megaphone class="h-4 w-4" :stroke-width="1.75" />
                  <span v-if="!row.read_at" class="absolute -right-0.5 -top-0.5 h-2 w-2 rounded-full bg-red-500 ring-2 ring-white"></span>
                </div>
                <div class="min-w-0 flex-1">
                  <p class="truncate text-sm font-medium text-slate-800">{{ row.announcement.title }}</p>
                  <p class="truncate text-xs text-slate-400">{{ row.announcement.content }}</p>
                </div>
                <span class="shrink-0 text-[11px] text-slate-300">{{ formatRelativeDate(row.announcement.published_at) }}</span>
              </div>
            </div>
            <button type="button" @click="router.push({ name: 'announcements.inbox' })" class="mt-4 text-xs font-medium text-primary-dark hover:underline">View all announcement ›</button>
          </template>

          <template v-else-if="activeTab === 'contract'">
            <div v-if="contractProbationLoading" class="flex items-center gap-2 py-6 text-xs text-slate-400"><Loader2 class="h-4 w-4 animate-spin" :stroke-width="2" />Memuat...</div>
            <div v-else-if="contractProbationForbidden" class="py-10 text-center text-sm text-slate-400">Kamu tidak punya akses untuk melihat data ini.</div>
            <div v-else-if="contractProbationError" class="py-10 text-center text-sm text-slate-400">{{ contractProbationError }}</div>
            <template v-else-if="contractProbation">
              <div class="mb-4 flex gap-3">
                <div class="flex-1 rounded-xl bg-slate-50 px-3 py-2"><p class="text-lg font-semibold text-slate-800">{{ contractProbation.contract_ending_soon }}</p><p class="text-[11px] text-slate-400">Contract Ending Soon</p></div>
                <div class="flex-1 rounded-xl bg-slate-50 px-3 py-2"><p class="text-lg font-semibold text-slate-800">{{ contractProbation.probation_ending_soon }}</p><p class="text-[11px] text-slate-400">Probation Ending Soon</p></div>
              </div>
              <div v-if="contractProbation.preview.length === 0" class="py-6 text-center text-xs text-slate-400">Tidak ada kontrak/probation yang akan berakhir dalam waktu dekat.</div>
              <div v-else class="space-y-4">
                <div v-for="item in contractProbation.preview" :key="`${item.type}-${item.employee.id}`" class="flex min-w-0 items-center gap-3">
                  <img v-if="item.employee.photo_url" :src="item.employee.photo_url" alt="" class="h-8 w-8 shrink-0 rounded-full object-cover" />
                  <div v-else class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-soft text-xs font-semibold text-primary-dark">{{ initialsFromName(item.employee.name) }}</div>
                  <div class="min-w-0 flex-1"><p class="truncate text-sm font-medium text-slate-800">{{ item.employee.name }}</p><p class="truncate text-xs text-slate-400">{{ item.employee.position ?? '-' }}</p></div>
                  <component :is="item.type === 'contract' ? FileText : GraduationCap" class="h-3.5 w-3.5 shrink-0 text-slate-400" :stroke-width="1.75" />
                  <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium" :class="urgencyClass(item.remaining_days)">{{ remainingLabel(item.remaining_days) }}</span>
                </div>
              </div>
              <button type="button" @click="router.push({ name: 'contract-probation' })" class="mt-4 text-xs font-medium text-primary-dark hover:underline">View all ›</button>
            </template>
            <div v-else class="py-10 text-center text-sm text-slate-400">Belum ada data.</div>
          </template>

          <div v-else class="py-10 text-center text-sm text-slate-400">Belum ada data.</div>
        </div>

        <div class="min-w-0 rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
          <div class="mb-4 flex items-center justify-between"><h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Upcoming & Pending</h3><button type="button" class="text-xs font-medium text-primary-dark hover:underline">View all ›</button></div>
          <div class="space-y-3">
            <div v-for="(item, i) in upcomingPending" :key="i" class="flex min-w-0 items-center gap-3">
              <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl" :class="palettes[item.palette].bg"><component :is="item.icon" class="h-4 w-4" :class="palettes[item.palette].text" :stroke-width="1.75" /></div>
              <div class="min-w-0 flex-1"><p class="text-sm font-medium" :class="palettes[item.palette].text">{{ item.title }}</p><p class="truncate text-xs text-slate-400">{{ item.subtitle }}</p></div>
              <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">{{ item.count }}</span>
            </div>
          </div>
        </div>

        <div class="min-w-0 rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
          <div class="mb-3 flex items-center justify-between"><h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Calendar</h3><div class="flex items-center gap-1"><button @click="calPrevMonth" class="rounded p-1 text-slate-400 hover:bg-slate-100"><ChevronLeft class="h-3.5 w-3.5" :stroke-width="2" /></button><span class="w-24 text-center text-xs font-medium text-slate-700">{{ monthNames[calMonth - 1] }} {{ calYear }}</span><button @click="calNextMonth" class="rounded p-1 text-slate-400 hover:bg-slate-100"><ChevronRight class="h-3.5 w-3.5" :stroke-width="2" /></button></div></div>
          <div class="grid grid-cols-7 gap-y-1 text-center"><span v-for="wd in weekDaysShort" :key="wd" class="text-[10px] font-medium text-slate-400">{{ wd }}</span><span v-for="day in miniCalendarDays" :key="day.date" class="flex h-6 items-center justify-center rounded-full text-[11px]" :class="[day.isToday ? 'bg-primary font-semibold text-white' : day.isCurrentMonth ? 'text-slate-600' : 'text-slate-300']">{{ day.dayNumber }}</span></div>
          <div class="mt-3 flex flex-wrap gap-3 border-t border-slate-100 pt-3 text-[11px] text-slate-500"><span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-primary"></span>Today</span><span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-red-400"></span>Holiday</span><span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-amber-400"></span>Upcoming Leave</span></div>
        </div>
      </div>

      <!-- Bottom row -->
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <div class="min-w-0 rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)] lg:col-span-2">
          <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Quick Links</h3>
          <div class="grid grid-cols-4 gap-3"><button v-for="link in quickLinks" :key="link.label" type="button" class="flex flex-col items-center gap-1.5 rounded-xl p-2 text-center transition-colors hover:bg-slate-50" @click="goToQuickLink(link)"><div class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary-soft text-primary-dark"><component :is="link.icon" class="h-4 w-4" :stroke-width="1.75" /></div><span class="text-[11px] leading-tight text-slate-500">{{ link.label }}</span></button></div>
        </div>
        <div class="min-w-0 rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]"><h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Next Holiday</h3><div class="flex items-center gap-2 text-red-500"><Flag class="h-4 w-4" :stroke-width="1.75" /><p class="text-sm font-semibold text-slate-900">{{ nextHolidayDate.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) }}</p></div><p class="mt-1 text-xs font-medium text-slate-600">{{ nextHolidayLabel }}</p><p class="mt-0.5 text-xs text-slate-400">{{ daysUntilHoliday }} hari lagi</p></div>
        <div class="min-w-0 rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]"><h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Today's Birthday</h3><div v-if="todaysBirthday" class="flex min-w-0 items-center gap-3"><div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-soft text-xs font-semibold text-primary-dark">{{ initialsFromName(todaysBirthday.name) }}</div><div class="min-w-0"><p class="truncate text-sm font-medium text-slate-800">{{ todaysBirthday.name }}</p><p class="truncate text-xs text-slate-400">{{ todaysBirthday.position }}</p></div><Gift class="ml-auto h-4 w-4 shrink-0 text-pink-400" :stroke-width="1.75" /></div><p v-else class="py-4 text-center text-xs text-slate-400">Tidak ada ulang tahun hari ini.</p></div>
        <div class="min-w-0 rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]"><h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Pending Action</h3><div class="flex items-center justify-between"><div><p class="text-2xl font-bold text-slate-900">{{ pendingActionCount }}</p><p class="text-xs text-slate-400">Actions require your attention</p></div><button type="button" class="flex h-9 w-9 items-center justify-center rounded-full bg-primary-soft text-primary-dark hover:bg-primary/20"><ArrowRight class="h-4 w-4" :stroke-width="2" /></button></div></div>
      </div>
    </div>

    <!-- ================================================================== -->
    <!-- MOBILE — intentionally separate, app-like experience              -->
    <!-- ================================================================== -->
    <div class="lg:hidden -mx-4 min-h-full bg-slate-50 px-4 pb-6 pt-2 sm:mx-0 sm:px-0">

      <!-- Greeting -->
      <section class="mb-4 rounded-[28px] bg-gradient-to-br from-primary-soft via-white to-white p-5 shadow-[0_12px_35px_rgba(15,23,42,0.05)] ring-1 ring-white">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="text-xs font-medium text-slate-500">{{ formattedDate }}</p>
            <h1 class="mt-1 text-[25px] font-semibold leading-tight tracking-tight text-slate-900">
              {{ greeting }},<br />
              <span class="text-primary-dark">{{ data.user.name }}</span> 👋
            </h1>
            <div class="mt-3 flex flex-wrap gap-1.5">
              <span v-for="role in data.roles" :key="role" class="rounded-full bg-white/80 px-2.5 py-1 text-[10px] font-semibold text-primary-dark ring-1 ring-slate-100">
                {{ role }}
              </span>
            </div>
          </div>

          <div class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-primary text-sm font-semibold text-white shadow-lg shadow-primary/20">
            <img
              v-if="authStore.user?.photo_url"
              :src="authStore.user.photo_url"
              :alt="authStore.user?.name ?? data.user.name"
              class="h-full w-full object-cover"
            />
            <span v-else>{{ initialsFromName(data.user.name) }}</span>
          </div>
        </div>
      </section>

      <!-- Attendance card: keep the existing functional component on mobile. -->
      <!-- It already contains the Working Time percentage/chart, Clock In/Out,
           status, live clock, verification notice, and Shift information. -->
      <section class="mb-4">
        <AttendanceSelfServiceCard />
      </section>

      <!-- Quick actions -->
      <section class="mb-4">
        <div class="mb-2.5 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-slate-900">Quick Actions</h2>
          <button type="button" @click="openMobileMore" class="text-xs font-medium text-primary-dark">See all</button>
        </div>

        <div class="-mx-1 flex snap-x gap-2 overflow-x-auto px-1 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
          <button
            v-for="s in shortcuts"
            :key="s.title"
            type="button"
            class="min-w-[112px] snap-start rounded-2xl bg-white p-3.5 text-left shadow-[0_5px_18px_rgba(15,23,42,0.04)] ring-1 ring-slate-100 transition active:scale-[0.98]"
          >
            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary-soft text-primary-dark">
              <component :is="s.icon" class="h-4 w-4" :stroke-width="1.8" />
            </div>
            <p class="mt-3 text-[11px] font-semibold leading-tight text-slate-800">{{ s.title.replace('Request ', '') }}</p>
            <p class="mt-0.5 text-[10px] text-slate-400">{{ s.subtitle }}</p>
          </button>

          <button type="button" @click="openMobileMore" class="min-w-[86px] snap-start rounded-2xl bg-white p-3.5 text-left shadow-[0_5px_18px_rgba(15,23,42,0.04)] ring-1 ring-slate-100">
            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-slate-500">
              <MoreHorizontal class="h-4 w-4" :stroke-width="1.8" />
            </div>
            <p class="mt-3 text-[11px] font-semibold text-slate-700">More</p>
            <p class="mt-0.5 text-[10px] text-slate-400">Other actions</p>
          </button>
        </div>
      </section>

      <!-- Working Hours chart -->
      <section class="mb-4 rounded-[28px] bg-white p-5 shadow-[0_8px_24px_rgba(15,23,42,0.05)] ring-1 ring-slate-100">
        <div class="flex items-start justify-between gap-3">
          <div>
            <div class="flex items-center gap-2">
              <h2 class="text-sm font-semibold text-slate-900">Working Hours</h2>
              <BarChart3 class="h-4 w-4 text-primary-dark" :stroke-width="1.8" />
            </div>
            <p class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">{{ workingHoursTotalLabel }}</p>
            <p class="mt-0.5 text-[10px] font-medium text-emerald-600">{{ workingHoursChange }} from last week</p>
          </div>

          <button type="button" class="flex items-center gap-1 rounded-xl bg-slate-50 px-2.5 py-2 text-[10px] font-semibold text-slate-500">
            This Week
            <ChevronDown class="h-3 w-3" :stroke-width="2" />
          </button>
        </div>

        <div class="mt-4 overflow-hidden rounded-2xl bg-gradient-to-b from-primary-soft/30 to-white px-1.5 pt-2">
          <svg viewBox="0 0 320 112" class="h-36 w-full overflow-visible" preserveAspectRatio="none" aria-label="Working hours chart">
            <defs>
              <linearGradient id="workingHoursAreaGradient" x1="0" x2="0" y1="0" y2="1">
                <stop offset="0%" class="stop-primary-soft" stop-opacity="0.7" />
                <stop offset="100%" class="stop-primary-soft" stop-opacity="0.05" />
              </linearGradient>
            </defs>

            <line x1="8" y1="100" x2="312" y2="100" stroke="currentColor" class="text-slate-200" stroke-width="1" />
            <line x1="8" y1="56" x2="312" y2="56" stroke="currentColor" class="text-slate-100" stroke-width="1" stroke-dasharray="3 5" />
            <line x1="8" y1="12" x2="312" y2="12" stroke="currentColor" class="text-slate-100" stroke-width="1" stroke-dasharray="3 5" />

            <polygon :points="workingChartArea" fill="url(#workingHoursAreaGradient)" />
            <polyline :points="workingChartPolyline" fill="none" stroke="currentColor" class="text-primary" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />

            <g v-for="point in workingChartPoints" :key="point.day">
              <circle :cx="point.x" :cy="point.y" r="5.5" fill="white" :class="point.day === selectedWorkingDay ? 'stroke-primary' : 'stroke-transparent'" stroke-width="3" />
              <circle :cx="point.x" :cy="point.y" r="2.75" class="fill-primary" @click="selectWorkingDay(point.day)" />
            </g>

            <g v-if="workingChartSelectedPoint">
              <rect :x="Math.max(6, Math.min(workingChartSelectedPoint.x - 29, 255))" :y="Math.max(2, workingChartSelectedPoint.y - 33)" width="58" height="22" rx="8" class="fill-slate-900" />
              <text :x="Math.max(6, Math.min(workingChartSelectedPoint.x, 284))" :y="Math.max(16, workingChartSelectedPoint.y - 18)" text-anchor="middle" class="fill-white text-[9px] font-semibold">{{ workingChartSelectedPoint.hours }}h</text>
            </g>
          </svg>

          <div class="grid grid-cols-5 px-1 pb-1 pt-1 text-center">
            <button v-for="item in workingHours" :key="item.day" type="button" @click="selectWorkingDay(item.day)" class="text-[10px] font-medium transition-colors" :class="item.day === selectedWorkingDay ? 'text-primary-dark' : 'text-slate-400'">
              {{ item.day }}
            </button>
          </div>
        </div>
      </section>

      <!-- Leave balance -->
      <section class="mb-4">
        <div class="mb-2.5 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-slate-900">Leave Balance</h2>
          <button type="button" class="text-xs font-medium text-primary-dark">View detail</button>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div v-for="b in leaveBalances" :key="b.name" class="rounded-2xl bg-white p-4 shadow-[0_5px_18px_rgba(15,23,42,0.04)] ring-1 ring-slate-100">
            <div class="flex items-center justify-between gap-2">
              <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-primary-soft text-primary-dark">
                <component :is="b.icon" class="h-4 w-4" :stroke-width="1.75" />
              </div>
              <span class="text-[10px] font-medium text-slate-400">{{ b.remaining }} left</span>
            </div>
            <p class="mt-3 text-xs font-semibold text-slate-800">{{ b.name }}</p>
            <p class="mt-1 text-lg font-semibold tracking-tight text-slate-900">{{ b.remaining }} <span class="text-xs font-medium text-slate-400">days</span></p>
            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-primary" :style="{ width: `${balancePercent(b)}%` }"></div></div>
          </div>
        </div>
      </section>

      <!-- Who's off today -->
      <section class="mb-4 rounded-[24px] bg-white p-4 shadow-[0_5px_18px_rgba(15,23,42,0.04)] ring-1 ring-slate-100">
        <div class="mb-3 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-slate-900">Who's Off Today</h2>
          <button type="button" class="text-xs font-medium text-primary-dark">View all</button>
        </div>

        <div v-if="whosOffLoading" class="flex items-center gap-2 py-4 text-xs text-slate-400"><Loader2 class="h-4 w-4 animate-spin" :stroke-width="2" />Memuat...</div>
        <div v-else-if="whosOffToday.length === 0" class="py-4 text-center text-xs text-slate-400">Semua karyawan masuk hari ini.</div>
        <div v-else class="space-y-2.5">
          <div v-for="row in whosOffToday.slice(0, 3)" :key="row.id" class="flex items-center gap-3 rounded-2xl bg-slate-50 p-2.5">
            <img v-if="row.employee.photo_url" :src="row.employee.photo_url" alt="" class="h-9 w-9 shrink-0 rounded-xl object-cover" />
            <div v-else class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-xs font-semibold text-primary-dark">{{ initialsFromName(row.employee.name) }}</div>
            <div class="min-w-0 flex-1"><p class="truncate text-xs font-semibold text-slate-800">{{ row.employee.name }}</p><p class="truncate text-[10px] text-slate-400">{{ row.leave_type.name }}</p></div>
            <ArrowRight class="h-4 w-4 text-slate-300" :stroke-width="1.8" />
          </div>
        </div>
      </section>

      <!-- Announcements -->
      <section class="mb-4 rounded-[24px] bg-white p-4 shadow-[0_5px_18px_rgba(15,23,42,0.04)] ring-1 ring-slate-100">
        <div class="mb-3 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-primary-soft text-primary-dark"><Megaphone class="h-4 w-4" :stroke-width="1.8" /></div>
            <h2 class="text-sm font-semibold text-slate-900">Announcements</h2>
          </div>
          <button type="button" @click="router.push({ name: 'announcements.inbox' })" class="text-xs font-medium text-primary-dark">See all</button>
        </div>

        <div v-if="announcementsLoading" class="flex items-center gap-2 py-5 text-xs text-slate-400"><Loader2 class="h-4 w-4 animate-spin" :stroke-width="2" />Memuat...</div>
        <div v-else-if="announcementsError" class="py-5 text-center text-xs text-slate-400">{{ announcementsError }}</div>
        <div v-else-if="announcements.length === 0" class="py-5 text-center text-xs text-slate-400">Belum ada announcement.</div>
        <div v-else class="space-y-2.5">
          <button v-for="row in announcements.slice(0, 3)" :key="row.id" type="button" class="flex w-full items-center gap-3 rounded-2xl bg-slate-50 p-3 text-left">
            <span class="h-2 w-2 shrink-0 rounded-full" :class="row.read_at ? 'bg-slate-200' : 'bg-red-500'"></span>
            <span class="min-w-0 flex-1"><span class="block truncate text-xs font-semibold text-slate-800">{{ row.announcement.title }}</span><span class="mt-0.5 block truncate text-[10px] text-slate-400">{{ row.announcement.content }}</span></span>
            <span class="shrink-0 text-[10px] text-slate-300">{{ formatRelativeDate(row.announcement.published_at) }}</span>
          </button>
        </div>
      </section>

      <!-- Mobile action sheet -->
      <Transition name="fade">
        <div v-if="mobileMoreOpen" class="fixed inset-0 z-[60] bg-slate-900/25 backdrop-blur-[2px] lg:hidden" @click="closeMobileMore">
          <Transition name="sheet" appear>
            <div class="absolute inset-x-0 bottom-0 rounded-t-[28px] bg-white p-5 pb-[calc(1.25rem+env(safe-area-inset-bottom))] shadow-2xl" @click.stop>
              <div class="mx-auto mb-4 h-1.5 w-12 rounded-full bg-slate-200"></div>
              <div class="mb-4 flex items-center justify-between">
                <div><p class="text-sm font-semibold text-slate-900">Quick Actions</p><p class="mt-0.5 text-[11px] text-slate-400">Pilih menu yang ingin dibuka</p></div>
                <button type="button" @click="closeMobileMore" class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-slate-500"><MoreVertical class="h-4 w-4" :stroke-width="2" /></button>
              </div>

              <div class="grid grid-cols-2 gap-2.5">
                <button v-for="s in shortcuts" :key="`sheet-${s.title}`" type="button" class="flex items-center gap-3 rounded-2xl border border-slate-100 p-3 text-left hover:bg-slate-50" @click="closeMobileMore">
                  <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-primary-dark"><component :is="s.icon" class="h-4 w-4" :stroke-width="1.8" /></div>
                  <div class="min-w-0"><p class="truncate text-[11px] font-semibold text-slate-800">{{ s.title }}</p><p class="truncate text-[10px] text-slate-400">{{ s.subtitle }}</p></div>
                </button>
              </div>

              <button type="button" @click="goMobilePayslip(); closeMobileMore()" class="mt-2.5 flex w-full items-center gap-3 rounded-2xl border border-slate-100 p-3 text-left hover:bg-slate-50">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary-soft text-primary-dark"><Receipt class="h-4 w-4" :stroke-width="1.8" /></div>
                <div><p class="text-[11px] font-semibold text-slate-800">My Payslip</p><p class="text-[10px] text-slate-400">Lihat slip gaji</p></div>
              </button>
            </div>
          </Transition>
        </div>
      </Transition>

    </div>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.sheet-enter-active,
.sheet-leave-active {
  transition: transform 0.24s ease, opacity 0.24s ease;
}

.sheet-enter-from,
.sheet-leave-to {
  opacity: 0;
  transform: translateY(18px);
}

.stop-primary-soft {
  stop-color: rgb(124 58 237 / 0.28);
}
</style>
