<script setup lang="ts">
import {
  ref,
  onMounted,
  reactive,
  computed,
  watch,
} from 'vue'
import { useRouter } from 'vue-router'
import {
  Plus,
  X,
  AlertCircle,
  Landmark,
  Search,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  CalendarDays,
  Users,
  WalletCards,
  CheckCircle2,
  Clock3,
  LockKeyhole,
  MoreHorizontal,
  Eye,
  RefreshCw,
  SlidersHorizontal,
  Building2,
  Banknote,
} from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface Company {
  id: number
  name: string
}

interface Employee {
  id: number
  company_id: number
  first_name: string
  last_name: string | null
}

type RunStatus =
  | 'draft'
  | 'pending_approval'
  | 'approved'
  | 'processed'
  | 'locked'
  | 'cancelled'

interface PayrollRunRow {
  id: number
  company: Company
  period_year: number
  period_month: number
  status: RunStatus
  current_revision: number
  published_at: string | null
  participants_count: number
  total_net_payroll: string | number
}

/* -------------------------------------------------------------------------- */
/* CONSTANTS                                                                  */
/* -------------------------------------------------------------------------- */

const PRIMARY = '#BD2028'
const PRIMARY_DARK = '#9F1B22'
const PRIMARY_SOFT = '#FCEBED'

const monthNames = [
  'Januari',
  'Februari',
  'Maret',
  'April',
  'Mei',
  'Juni',
  'Juli',
  'Agustus',
  'September',
  'Oktober',
  'November',
  'Desember',
]

const statusLabels: Record<RunStatus, string> = {
  draft: 'Draft',
  pending_approval: 'Menunggu Approval',
  approved: 'Approved',
  processed: 'Processed',
  locked: 'Locked',
  cancelled: 'Dibatalkan',
}

const statusBadgeClass: Record<RunStatus, string> = {
  draft: 'bg-slate-100 text-slate-600',
  pending_approval: 'bg-amber-50 text-amber-700',
  approved: 'bg-blue-50 text-blue-700',
  processed: 'bg-[#FCEBED] text-[#BD2028]',
  locked: 'bg-emerald-50 text-emerald-700',
  cancelled: 'bg-red-50 text-red-700',
}

const statusDotClass: Record<RunStatus, string> = {
  draft: 'bg-slate-400',
  pending_approval: 'bg-amber-500',
  approved: 'bg-blue-500',
  processed: 'bg-[#BD2028]',
  locked: 'bg-emerald-500',
  cancelled: 'bg-red-500',
}

// Status yang masih membutuhkan perhatian HR.
// Ini hanya indikator UI, tidak mengubah state machine backend.
const actionNeededStatuses: RunStatus[] = [
  'draft',
  'pending_approval',
  'processed',
]

/* -------------------------------------------------------------------------- */
/* HELPERS                                                                    */
/* -------------------------------------------------------------------------- */

function employeeName(employee: {
  first_name: string
  last_name: string | null
}) {
  return [employee.first_name, employee.last_name]
    .filter(Boolean)
    .join(' ')
}

function formatCurrency(value: string | number) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
  }).format(Number(value))
}

function formatDate(value: string | null) {
  if (!value) return '-'

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return value
  }

  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(date)
}

function getPeriodLabel(row: PayrollRunRow) {
  return `${monthNames[row.period_month - 1]} ${row.period_year}`
}

function getStatusLabel(status: RunStatus) {
  return statusLabels[status]
}

function getStatusBadge(status: RunStatus) {
  return statusBadgeClass[status]
}

function getStatusDot(status: RunStatus) {
  return statusDotClass[status]
}

/* -------------------------------------------------------------------------- */
/* MAIN STATE                                                                 */
/* -------------------------------------------------------------------------- */

const router = useRouter()

const runs = ref<PayrollRunRow[]>([])
const companies = ref<Company[]>([])
const employees = ref<Employee[]>([])

const loading = ref(true)
const refreshing = ref(false)
const errorMessage = ref('')

const meta = ref({
  current_page: 1,
  last_page: 1,
  total: 0,
})

const currentYear = new Date().getFullYear()

const yearOptions = Array.from(
  { length: 6 },
  (_, index) => currentYear - index,
)

const filters = reactive({
  company_id: null as number | null,
  status: null as RunStatus | null,
  period_year: null as number | null,
  period_month: null as number | null,
  page: 1,
})

const searchQuery = ref('')
const showFilterPanel = ref(true)
const openActionId = ref<number | null>(null)

/* -------------------------------------------------------------------------- */
/* FETCH                                                                      */
/* -------------------------------------------------------------------------- */

async function loadRuns(showLoader = true) {
  if (showLoader) {
    loading.value = true
  } else {
    refreshing.value = true
  }

  errorMessage.value = ''

  try {
    const response = await apiClient.get('/api/payroll-runs', {
      params: {
        company_id: filters.company_id || undefined,
        status: filters.status || undefined,
        period_year: filters.period_year || undefined,
        period_month: filters.period_month || undefined,
        page: filters.page,
      },
    })

    const payload = response.data.data

    runs.value = payload.data

    meta.value = {
      current_page: payload.current_page,
      last_page: payload.last_page,
      total: payload.total,
    }
  } catch (error: any) {
    errorMessage.value =
      error.response?.data?.message ||
      'Gagal memuat data payroll history.'
  } finally {
    loading.value = false
    refreshing.value = false
  }
}

async function loadCompanies() {
  try {
    const response = await apiClient.get('/api/companies')

    companies.value =
      response.data.data?.data ??
      response.data.data ??
      []
  } catch {
    companies.value = []
  }
}

async function loadEmployees() {
  try {
    const response = await apiClient.get('/api/employees', {
      params: {
        per_page: 200,
      },
    })

    employees.value = response.data.data.data ?? []
  } catch {
    employees.value = []
  }
}

/* -------------------------------------------------------------------------- */
/* FILTER                                                                     */
/* -------------------------------------------------------------------------- */

const filteredRuns = computed(() => {
  const keyword = searchQuery.value.trim().toLowerCase()

  if (!keyword) {
    return runs.value
  }

  return runs.value.filter((row) => {
    const period = getPeriodLabel(row).toLowerCase()
    const company = row.company.name.toLowerCase()
    const status = getStatusLabel(row.status).toLowerCase()

    return (
      period.includes(keyword) ||
      company.includes(keyword) ||
      status.includes(keyword)
    )
  })
})

const activeFilterCount = computed(() => {
  let count = 0

  if (filters.company_id) count++
  if (filters.status) count++
  if (filters.period_year) count++
  if (filters.period_month) count++

  return count
})

const currentPageSummary = computed(() => {
  const totalNet = runs.value.reduce((total, row) => {
    return total + Number(row.total_net_payroll || 0)
  }, 0)

  const employeeTotal = runs.value.reduce((total, row) => {
    return total + Number(row.participants_count || 0)
  }, 0)

  const actionNeeded = runs.value.filter((row) =>
    actionNeededStatuses.includes(row.status),
  ).length

  const locked = runs.value.filter(
    (row) => row.status === 'locked',
  ).length

  return {
    totalNet,
    employeeTotal,
    actionNeeded,
    locked,
  }
})

const pageNumbers = computed(() => {
  const totalPages = meta.value.last_page
  const current = meta.value.current_page

  if (totalPages <= 7) {
    return Array.from(
      { length: totalPages },
      (_, index) => index + 1,
    )
  }

  const pages = new Set<number>()

  pages.add(1)
  pages.add(totalPages)
  pages.add(current)

  if (current > 1) pages.add(current - 1)
  if (current > 2) pages.add(current - 2)
  if (current < totalPages) pages.add(current + 1)
  if (current < totalPages - 1) pages.add(current + 2)

  return Array.from(pages).sort((a, b) => a - b)
})

function applyFilters() {
  filters.page = 1
  openActionId.value = null
  loadRuns()
}

function resetFilters() {
  filters.company_id = null
  filters.status = null
  filters.period_year = null
  filters.period_month = null
  filters.page = 1
  searchQuery.value = ''

  loadRuns()
}

function goToPage(page: number) {
  if (
    page < 1 ||
    page > meta.value.last_page ||
    page === meta.value.current_page
  ) {
    return
  }

  filters.page = page
  openActionId.value = null

  loadRuns(false)
}

function refreshRuns() {
  loadRuns(false)
}

/* -------------------------------------------------------------------------- */
/* NAVIGATION                                                                 */
/* -------------------------------------------------------------------------- */

function openPayroll(id: number) {
  openActionId.value = null
  router.push(`/payroll-runs/${id}`)
}

function openPayrollFromRow(id: number) {
  router.push(`/payroll-runs/${id}`)
}

/* -------------------------------------------------------------------------- */
/* CREATE PAYROLL MODAL                                                       */
/* -------------------------------------------------------------------------- */

const showModal = ref(false)
const saving = ref(false)
const formError = ref('')

const form = reactive({
  company_id: null as number | null,
  period_year: new Date().getFullYear(),
  period_month: new Date().getMonth() + 1,
  cutoff_date: '',
  payment_date: '',
  employee_ids: [] as number[],
})

const availableEmployees = computed(() =>
  employees.value.filter(
    (employee) => employee.company_id === form.company_id,
  ),
)

const allEmployeesSelected = computed(() => {
  if (availableEmployees.value.length === 0) {
    return false
  }

  return (
    form.employee_ids.length ===
    availableEmployees.value.length
  )
})

watch(
  () => form.company_id,
  () => {
    form.employee_ids = []
  },
)

function openCreateModal() {
  form.company_id = companies.value[0]?.id ?? null
  form.period_year = new Date().getFullYear()
  form.period_month = new Date().getMonth() + 1
  form.cutoff_date = ''
  form.payment_date = ''
  form.employee_ids = []

  formError.value = ''
  showModal.value = true
}

function closeCreateModal() {
  if (saving.value) return

  showModal.value = false
}

function toggleEmployee(id: number) {
  const index = form.employee_ids.indexOf(id)

  if (index >= 0) {
    form.employee_ids.splice(index, 1)
  } else {
    form.employee_ids.push(id)
  }
}

function selectAllEmployees() {
  if (allEmployeesSelected.value) {
    form.employee_ids = []
    return
  }

  form.employee_ids = availableEmployees.value.map(
    (employee) => employee.id,
  )
}

async function submitForm() {
  if (
    !form.company_id ||
    form.employee_ids.length === 0
  ) {
    formError.value =
      'Pilih company dan minimal satu employee.'
    return
  }

  saving.value = true
  formError.value = ''

  try {
    const response = await apiClient.post(
      '/api/payroll-runs',
      form,
    )

    showModal.value = false

    router.push(
      `/payroll-runs/${response.data.data.id}`,
    )
  } catch (error: any) {
    formError.value =
      error.response?.data?.message ||
      'Gagal membuat payroll run.'
  } finally {
    saving.value = false
  }
}

/* -------------------------------------------------------------------------- */
/* BANK SETTING MODAL                                                         */
/* -------------------------------------------------------------------------- */

const showBankModal = ref(false)
const bankSaving = ref(false)
const bankError = ref('')

const bankForm = reactive({
  company_id: null as number | null,
  bank_name: '',
  account_number: '',
  account_holder_name: '',
})

async function openBankModal() {
  bankForm.company_id = companies.value[0]?.id ?? null

  bankError.value = ''
  showBankModal.value = true

  await loadBankSetting()
}

function closeBankModal() {
  if (bankSaving.value) return

  showBankModal.value = false
}

watch(
  () => bankForm.company_id,
  async () => {
    if (showBankModal.value) {
      await loadBankSetting()
    }
  },
)

async function loadBankSetting() {
  if (!bankForm.company_id) {
    return
  }

  try {
    const response = await apiClient.get(
      '/api/payroll-bank-setting',
      {
        params: {
          company_id: bankForm.company_id,
        },
      },
    )

    const data = response.data.data

    bankForm.bank_name = data?.bank_name ?? ''
    bankForm.account_number =
      data?.account_number ?? ''
    bankForm.account_holder_name =
      data?.account_holder_name ?? ''
  } catch {
    bankForm.bank_name = ''
    bankForm.account_number = ''
    bankForm.account_holder_name = ''
  }
}

async function submitBankForm() {
  bankSaving.value = true
  bankError.value = ''

  try {
    await apiClient.put(
      '/api/payroll-bank-setting',
      bankForm,
    )

    showBankModal.value = false
  } catch (error: any) {
    bankError.value =
      error.response?.data?.message ||
      'Gagal menyimpan rekening.'
  } finally {
    bankSaving.value = false
  }
}

/* -------------------------------------------------------------------------- */
/* LIFECYCLE                                                                  */
/* -------------------------------------------------------------------------- */

onMounted(async () => {
  await Promise.all([
    loadRuns(),
    loadCompanies(),
    loadEmployees(),
  ])
})
</script>

<template>
  <div
    class="min-h-full space-y-6 pb-10"
    @click="openActionId = null"
  >
    <!-- ================================================================== -->
    <!-- PAGE HEADER                                                        -->
    <!-- ================================================================== -->

    <section
      class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
    >
      <div>
        <div class="mb-1 flex items-center gap-2 text-xs font-medium text-slate-400">
          <span>Payroll</span>
          <ChevronRight class="h-3.5 w-3.5" />
          <span class="text-slate-500">Payroll History</span>
        </div>

        <h1
          class="text-2xl font-semibold tracking-tight text-slate-900"
        >
          Payroll History
        </h1>

        <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-500">
          Kelola seluruh payroll berdasarkan periode, review
          status proses, approval, publish, dan payroll lock.
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-600 shadow-sm transition hover:border-slate-300 hover:bg-slate-50"
          @click.stop="openBankModal"
        >
          <Landmark class="h-4 w-4" :stroke-width="1.8" />
          Pengaturan Bank
        </button>

        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-xl bg-[#BD2028] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#9F1B22] focus:outline-none focus:ring-4 focus:ring-[#BD2028]/10"
          @click.stop="openCreateModal"
        >
          <Plus class="h-4 w-4" :stroke-width="2" />
          Run Payroll Baru
        </button>
      </div>
    </section>

    <!-- ================================================================== -->
    <!-- SUMMARY CARDS                                                      -->
    <!-- ================================================================== -->

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <!-- Total Run -->
      <div
        class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]"
      >
        <div class="flex items-start justify-between">
          <div>
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">
              Total Payroll Run
            </p>

            <div
              v-if="loading"
              class="mt-3 h-8 w-20 animate-pulse rounded-lg bg-slate-100"
            />

            <p
              v-else
              class="mt-2 text-2xl font-semibold tracking-tight text-slate-900"
            >
              {{ meta.total }}
            </p>
          </div>

          <div
            class="flex h-10 w-10 items-center justify-center rounded-xl"
            :style="{ backgroundColor: PRIMARY_SOFT, color: PRIMARY }"
          >
            <CalendarDays class="h-5 w-5" />
          </div>
        </div>

        <p class="mt-3 text-xs text-slate-400">
          Seluruh payroll run berdasarkan filter aktif
        </p>
      </div>

      <!-- Action Needed -->
      <div
        class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]"
      >
        <div class="flex items-start justify-between">
          <div>
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">
              Perlu Tindakan
            </p>

            <div
              v-if="loading"
              class="mt-3 h-8 w-16 animate-pulse rounded-lg bg-slate-100"
            />

            <p
              v-else
              class="mt-2 text-2xl font-semibold tracking-tight text-slate-900"
            >
              {{ currentPageSummary.actionNeeded }}
            </p>
          </div>

          <div
            class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600"
          >
            <AlertCircle class="h-5 w-5" />
          </div>
        </div>

        <p class="mt-3 text-xs text-slate-400">
          Dari payroll yang tampil di halaman ini
        </p>
      </div>

      <!-- Employee -->
      <div
        class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]"
      >
        <div class="flex items-start justify-between">
          <div>
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">
              Employee Terproses
            </p>

            <div
              v-if="loading"
              class="mt-3 h-8 w-20 animate-pulse rounded-lg bg-slate-100"
            />

            <p
              v-else
              class="mt-2 text-2xl font-semibold tracking-tight text-slate-900"
            >
              {{ currentPageSummary.employeeTotal.toLocaleString('id-ID') }}
            </p>
          </div>

          <div
            class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600"
          >
            <Users class="h-5 w-5" />
          </div>
        </div>

        <p class="mt-3 text-xs text-slate-400">
          Akumulasi employee pada payroll di halaman ini
        </p>
      </div>

      <!-- Net Payroll -->
      <div
        class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]"
      >
        <div class="flex items-start justify-between">
          <div class="min-w-0">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">
              Net Payroll
            </p>

            <div
              v-if="loading"
              class="mt-3 h-8 w-32 animate-pulse rounded-lg bg-slate-100"
            />

            <p
              v-else
              class="mt-2 truncate text-xl font-semibold tracking-tight text-slate-900"
              :title="formatCurrency(currentPageSummary.totalNet)"
            >
              {{
                currentPageSummary.totalNet > 0
                  ? formatCurrency(currentPageSummary.totalNet)
                  : 'Rp 0'
              }}
            </p>
          </div>

          <div
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"
          >
            <WalletCards class="h-5 w-5" />
          </div>
        </div>

        <p class="mt-3 text-xs text-slate-400">
          Nilai net payroll pada halaman ini
        </p>
      </div>
    </section>

    <!-- ================================================================== -->
    <!-- TOOLBAR                                                            -->
    <!-- ================================================================== -->

    <section
      class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]"
    >
      <!-- Search / Toolbar -->
      <div
        class="flex flex-col gap-3 border-b border-slate-100 p-4 xl:flex-row xl:items-center xl:justify-between"
      >
        <div class="relative w-full xl:max-w-md">
          <Search
            class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
          />

          <input
            v-model="searchQuery"
            type="search"
            placeholder="Cari periode, company, atau status..."
            class="h-10 w-full rounded-xl border border-slate-200 bg-white pl-10 pr-4 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-[#BD2028] focus:ring-4 focus:ring-[#BD2028]/10"
          />
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <button
            type="button"
            class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50"
            @click.stop="showFilterPanel = !showFilterPanel"
          >
            <SlidersHorizontal class="h-4 w-4" />
            Filter

            <span
              v-if="activeFilterCount > 0"
              class="inline-flex min-w-5 items-center justify-center rounded-full bg-[#FCEBED] px-1.5 py-0.5 text-[10px] font-bold text-[#BD2028]"
            >
              {{ activeFilterCount }}
            </span>

            <ChevronDown
              class="h-4 w-4 transition-transform"
              :class="showFilterPanel ? 'rotate-180' : ''"
            />
          </button>

          <button
            type="button"
            class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="refreshing"
            @click.stop="refreshRuns"
          >
            <RefreshCw
              class="h-4 w-4"
              :class="refreshing ? 'animate-spin' : ''"
            />
            Refresh
          </button>
        </div>
      </div>

      <!-- Filters -->
      <div
        v-if="showFilterPanel"
        class="border-b border-slate-100 bg-slate-50/50 p-4"
      >
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
          <!-- Company -->
          <div>
            <label
              class="mb-1.5 block text-xs font-semibold text-slate-600"
            >
              Company
            </label>

            <select
              v-model="filters.company_id"
              class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#BD2028] focus:ring-4 focus:ring-[#BD2028]/10"
            >
              <option :value="null">Semua Company</option>

              <option
                v-for="company in companies"
                :key="company.id"
                :value="company.id"
              >
                {{ company.name }}
              </option>
            </select>
          </div>

          <!-- Status -->
          <div>
            <label
              class="mb-1.5 block text-xs font-semibold text-slate-600"
            >
              Status
            </label>

            <select
              v-model="filters.status"
              class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#BD2028] focus:ring-4 focus:ring-[#BD2028]/10"
            >
              <option :value="null">Semua Status</option>

              <option
                v-for="(label, value) in statusLabels"
                :key="value"
                :value="value"
              >
                {{ label }}
              </option>
            </select>
          </div>

          <!-- Year -->
          <div>
            <label
              class="mb-1.5 block text-xs font-semibold text-slate-600"
            >
              Tahun
            </label>

            <select
              v-model="filters.period_year"
              class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#BD2028] focus:ring-4 focus:ring-[#BD2028]/10"
            >
              <option :value="null">Semua Tahun</option>

              <option
                v-for="year in yearOptions"
                :key="year"
                :value="year"
              >
                {{ year }}
              </option>
            </select>
          </div>

          <!-- Month -->
          <div>
            <label
              class="mb-1.5 block text-xs font-semibold text-slate-600"
            >
              Bulan
            </label>

            <select
              v-model="filters.period_month"
              class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#BD2028] focus:ring-4 focus:ring-[#BD2028]/10"
            >
              <option :value="null">Semua Bulan</option>

              <option
                v-for="(month, index) in monthNames"
                :key="index"
                :value="index + 1"
              >
                {{ month }}
              </option>
            </select>
          </div>

          <!-- Actions -->
          <div class="flex items-end gap-2">
            <button
              type="button"
              class="h-10 flex-1 rounded-xl bg-[#BD2028] px-4 text-sm font-semibold text-white transition hover:bg-[#9F1B22] focus:outline-none focus:ring-4 focus:ring-[#BD2028]/10"
              @click.stop="applyFilters"
            >
              Terapkan
            </button>

            <button
              type="button"
              class="h-10 rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-600 transition hover:bg-slate-50"
              title="Reset filter"
              @click.stop="resetFilters"
            >
              Reset
            </button>
          </div>
        </div>
      </div>

      <!-- Applied filter indicator -->
      <div
        v-if="activeFilterCount > 0 || searchQuery"
        class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-4 py-3"
      >
        <span class="text-xs font-medium text-slate-400">
          Filter aktif:
        </span>

        <span
          v-if="searchQuery"
          class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600"
        >
          Search: "{{ searchQuery }}"
        </span>

        <span
          v-if="filters.company_id"
          class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600"
        >
          {{
            companies.find(
              (company) => company.id === filters.company_id,
            )?.name ?? 'Company'
          }}
        </span>

        <span
          v-if="filters.status"
          class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600"
        >
          {{ getStatusLabel(filters.status) }}
        </span>

        <span
          v-if="filters.period_year"
          class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600"
        >
          {{ filters.period_year }}
        </span>

        <span
          v-if="filters.period_month"
          class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600"
        >
          {{ monthNames[filters.period_month - 1] }}
        </span>
      </div>

      <!-- ================================================================== -->
      <!-- ERROR                                                              -->
      <!-- ================================================================== -->

      <div
        v-if="errorMessage"
        class="border-b border-red-100 bg-red-50 px-5 py-4"
      >
        <div class="flex items-start gap-3">
          <AlertCircle class="mt-0.5 h-5 w-5 shrink-0 text-red-600" />

          <div>
            <p class="text-sm font-semibold text-red-800">
              Gagal memuat payroll
            </p>

            <p class="mt-1 text-sm text-red-700">
              {{ errorMessage }}
            </p>

            <button
              type="button"
              class="mt-2 text-xs font-semibold text-red-700 underline underline-offset-2"
              @click.stop="loadRuns()"
            >
              Coba lagi
            </button>
          </div>
        </div>
      </div>

      <!-- ================================================================== -->
      <!-- TABLE HEADER                                                       -->
      <!-- ================================================================== -->

      <div
        class="flex flex-col gap-2 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
      >
        <div>
          <h2 class="text-sm font-semibold text-slate-800">
            Riwayat Payroll
          </h2>

          <p class="mt-0.5 text-xs text-slate-400">
            Menampilkan {{ filteredRuns.length }} payroll dari
            {{ meta.total }} total run.
          </p>
        </div>

        <div
          v-if="currentPageSummary.locked > 0"
          class="inline-flex items-center gap-1.5 self-start rounded-lg bg-emerald-50 px-2.5 py-1.5 text-xs font-medium text-emerald-700 sm:self-auto"
        >
          <LockKeyhole class="h-3.5 w-3.5" />
          {{ currentPageSummary.locked }} payroll locked
        </div>
      </div>

      <!-- ================================================================== -->
      <!-- SKELETON                                                           -->
      <!-- ================================================================== -->

      <div
        v-if="loading"
        class="divide-y divide-slate-100"
      >
        <div
          v-for="index in 6"
          :key="index"
          class="grid grid-cols-1 gap-4 px-5 py-5 xl:grid-cols-[1.2fr_1.3fr_0.7fr_1.2fr_0.9fr_1.2fr_1fr_40px]"
        >
          <div class="h-4 w-28 animate-pulse rounded bg-slate-100" />
          <div class="h-4 w-32 animate-pulse rounded bg-slate-100" />
          <div class="h-4 w-12 animate-pulse rounded bg-slate-100" />
          <div class="h-4 w-36 animate-pulse rounded bg-slate-100" />
          <div class="h-4 w-24 animate-pulse rounded bg-slate-100" />
          <div class="h-6 w-28 animate-pulse rounded-full bg-slate-100" />
          <div class="h-4 w-20 animate-pulse rounded bg-slate-100" />
          <div class="h-8 w-8 animate-pulse rounded-lg bg-slate-100" />
        </div>
      </div>

      <!-- ================================================================== -->
      <!-- EMPTY STATE                                                        -->
      <!-- ================================================================== -->

      <div
        v-else-if="filteredRuns.length === 0"
        class="px-5 py-16 text-center"
      >
        <div
          class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"
        >
          <Banknote class="h-6 w-6" />
        </div>

        <h3 class="mt-4 text-sm font-semibold text-slate-800">
          {{
            searchQuery ||
            activeFilterCount > 0
              ? 'Payroll tidak ditemukan'
              : 'Belum ada payroll run'
          }}
        </h3>

        <p class="mx-auto mt-1 max-w-sm text-sm leading-6 text-slate-500">
          {{
            searchQuery ||
            activeFilterCount > 0
              ? 'Coba ubah kata pencarian atau reset filter untuk melihat data lainnya.'
              : 'Buat payroll run pertama untuk mulai memproses payroll employee.'
          }}
        </p>

        <button
          v-if="searchQuery || activeFilterCount > 0"
          type="button"
          class="mt-5 inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-600 shadow-sm transition hover:bg-slate-50"
          @click.stop="resetFilters"
        >
          Reset Filter
        </button>

        <button
          v-else
          type="button"
          class="mt-5 inline-flex items-center gap-2 rounded-xl bg-[#BD2028] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#9F1B22]"
          @click.stop="openCreateModal"
        >
          <Plus class="h-4 w-4" />
          Run Payroll Baru
        </button>
      </div>

      <!-- ================================================================== -->
      <!-- DESKTOP TABLE                                                      -->
      <!-- ================================================================== -->

      <div
        v-else
        class="hidden overflow-x-auto xl:block"
      >
        <table class="w-full min-w-[1180px] text-left text-sm">
          <thead>
            <tr class="border-b border-slate-100 bg-slate-50/70">
              <th
                class="whitespace-nowrap px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400"
              >
                Periode
              </th>

              <th
                class="whitespace-nowrap px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400"
              >
                Company
              </th>

              <th
                class="whitespace-nowrap px-5 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-400"
              >
                Karyawan
              </th>

              <th
                class="whitespace-nowrap px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-400"
              >
                Total Net Payroll
              </th>

              <th
                class="whitespace-nowrap px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400"
              >
                Revisi
              </th>

              <th
                class="whitespace-nowrap px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400"
              >
                Status
              </th>

              <th
                class="whitespace-nowrap px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400"
              >
                Publish
              </th>

              <th
                class="w-[60px] px-5 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-400"
              >
                &nbsp;
              </th>
            </tr>
          </thead>

          <tbody class="divide-y divide-slate-100">
            <tr
              v-for="row in filteredRuns"
              :key="row.id"
              class="group cursor-pointer transition hover:bg-slate-50/70"
              @click="openPayrollFromRow(row.id)"
            >
              <!-- Period -->
              <td class="px-5 py-4">
                <div class="flex items-center gap-3">
                  <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#FCEBED] text-[#BD2028]"
                  >
                    <CalendarDays class="h-4.5 w-4.5" />
                  </div>

                  <div>
                    <p class="font-semibold text-slate-800">
                      {{ getPeriodLabel(row) }}
                    </p>

                    <p class="mt-0.5 text-xs text-slate-400">
                      Payroll Run #{{ row.id }}
                    </p>
                  </div>
                </div>
              </td>

              <!-- Company -->
              <td class="px-5 py-4">
                <div class="flex items-center gap-2.5">
                  <div
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500"
                  >
                    <Building2 class="h-4 w-4" />
                  </div>

                  <div class="min-w-0">
                    <p
                      class="truncate font-medium text-slate-700"
                      :title="row.company.name"
                    >
                      {{ row.company.name }}
                    </p>
                  </div>
                </div>
              </td>

              <!-- Employees -->
              <td class="px-5 py-4 text-center">
                <span
                  class="inline-flex items-center gap-1.5 font-medium text-slate-600"
                >
                  <Users class="h-4 w-4 text-slate-400" />
                  {{ row.participants_count }}
                </span>
              </td>

              <!-- Net -->
              <td class="px-5 py-4 text-right">
                <p class="font-semibold text-slate-800">
                  {{
                    Number(row.total_net_payroll) > 0
                      ? formatCurrency(row.total_net_payroll)
                      : '-'
                  }}
                </p>

                <p class="mt-0.5 text-xs text-slate-400">
                  Take home total
                </p>
              </td>

              <!-- Revision -->
              <td class="px-5 py-4">
                <span
                  v-if="row.current_revision"
                  class="inline-flex items-center rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-600"
                >
                  Revisi {{ row.current_revision }}
                </span>

                <span
                  v-else
                  class="text-xs text-slate-400"
                >
                  -
                </span>
              </td>

              <!-- Status -->
              <td class="px-5 py-4">
                <div class="flex items-center gap-2">
                  <span
                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1.5 text-xs font-semibold"
                    :class="getStatusBadge(row.status)"
                  >
                    <span
                      class="h-1.5 w-1.5 rounded-full"
                      :class="getStatusDot(row.status)"
                    />
                    {{ getStatusLabel(row.status) }}
                  </span>

                  <span
                    v-if="actionNeededStatuses.includes(row.status)"
                    title="Perlu tindakan"
                    class="flex h-6 w-6 items-center justify-center rounded-full bg-amber-50 text-amber-600"
                  >
                    <AlertCircle class="h-3.5 w-3.5" />
                  </span>
                </div>
              </td>

              <!-- Publish -->
              <td class="px-5 py-4">
                <div
                  v-if="row.published_at"
                  class="flex items-center gap-2"
                >
                  <span
                    class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-50 text-emerald-600"
                  >
                    <CheckCircle2 class="h-4 w-4" />
                  </span>

                  <div>
                    <p class="text-xs font-semibold text-emerald-700">
                      Published
                    </p>

                    <p class="mt-0.5 text-[11px] text-slate-400">
                      {{ formatDate(row.published_at) }}
                    </p>
                  </div>
                </div>

                <div
                  v-else
                  class="flex items-center gap-2"
                >
                  <span
                    class="flex h-7 w-7 items-center justify-center rounded-full bg-slate-100 text-slate-400"
                  >
                    <Clock3 class="h-4 w-4" />
                  </span>

                  <span class="text-xs text-slate-400">
                    Belum publish
                  </span>
                </div>
              </td>

              <!-- Action -->
              <td
                class="px-5 py-4 text-center"
                @click.stop
              >
                <div class="relative inline-block">
                  <button
                    type="button"
                    class="flex h-9 w-9 items-center justify-center rounded-xl text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                    @click.stop="
                      openActionId =
                        openActionId === row.id
                          ? null
                          : row.id
                    "
                  >
                    <MoreHorizontal class="h-5 w-5" />
                  </button>

                  <div
                    v-if="openActionId === row.id"
                    class="absolute right-0 top-11 z-30 w-44 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 text-left shadow-xl shadow-slate-900/10"
                    @click.stop
                  >
                    <button
                      type="button"
                      class="flex w-full items-center gap-2 px-3 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50"
                      @click="openPayroll(row.id)"
                    >
                      <Eye class="h-4 w-4 text-slate-400" />
                      Lihat Detail
                    </button>
                  </div>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- ================================================================== -->
      <!-- MOBILE / TABLET CARDS                                              -->
      <!-- ================================================================== -->

      <div
        v-if="!loading && filteredRuns.length > 0"
        class="divide-y divide-slate-100 xl:hidden"
      >
        <button
          v-for="row in filteredRuns"
          :key="row.id"
          type="button"
          class="block w-full px-4 py-4 text-left transition hover:bg-slate-50"
          @click="openPayrollFromRow(row.id)"
        >
          <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
              <div class="flex items-center gap-3">
                <div
                  class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#FCEBED] text-[#BD2028]"
                >
                  <CalendarDays class="h-4 w-4" />
                </div>

                <div class="min-w-0">
                  <p class="truncate font-semibold text-slate-800">
                    {{ getPeriodLabel(row) }}
                  </p>

                  <p class="mt-0.5 truncate text-xs text-slate-400">
                    {{ row.company.name }}
                  </p>
                </div>
              </div>
            </div>

            <span
              class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1.5 text-xs font-semibold"
              :class="getStatusBadge(row.status)"
            >
              <span
                class="h-1.5 w-1.5 rounded-full"
                :class="getStatusDot(row.status)"
              />
              {{ getStatusLabel(row.status) }}
            </span>
          </div>

          <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div>
              <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">
                Karyawan
              </p>

              <p class="mt-1 text-sm font-semibold text-slate-700">
                {{ row.participants_count }}
              </p>
            </div>

            <div>
              <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">
                Net Payroll
              </p>

              <p class="mt-1 truncate text-sm font-semibold text-slate-700">
                {{
                  Number(row.total_net_payroll) > 0
                    ? formatCurrency(row.total_net_payroll)
                    : '-'
                }}
              </p>
            </div>

            <div>
              <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">
                Revisi
              </p>

              <p class="mt-1 text-sm font-semibold text-slate-700">
                {{
                  row.current_revision
                    ? `Revisi ${row.current_revision}`
                    : '-'
                }}
              </p>
            </div>

            <div>
              <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">
                Publish
              </p>

              <p class="mt-1 text-sm font-semibold text-slate-700">
                {{ row.published_at ? 'Published' : 'Belum publish' }}
              </p>
            </div>
          </div>
        </button>
      </div>

      <!-- ================================================================== -->
      <!-- PAGINATION                                                         -->
      <!-- ================================================================== -->

      <div
        v-if="!loading && meta.last_page > 1"
        class="flex flex-col gap-3 border-t border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
      >
        <p class="text-xs text-slate-400">
          Menampilkan halaman {{ meta.current_page }} dari
          {{ meta.last_page }} · Total {{ meta.total }} payroll run
        </p>

        <div class="flex items-center gap-1">
          <button
            type="button"
            class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
            :disabled="meta.current_page === 1"
            @click.stop="goToPage(meta.current_page - 1)"
          >
            <ChevronLeft class="h-4 w-4" />
          </button>

          <template
            v-for="(page, index) in pageNumbers"
            :key="page"
          >
            <span
              v-if="
                index > 0 &&
                page - pageNumbers[index - 1] > 1
              "
              class="flex h-9 w-9 items-center justify-center text-xs text-slate-400"
            >
              ...
            </span>

            <button
              type="button"
              class="flex h-9 min-w-9 items-center justify-center rounded-lg px-2 text-xs font-semibold transition"
              :class="
                page === meta.current_page
                  ? 'bg-[#BD2028] text-white shadow-sm'
                  : 'text-slate-500 hover:bg-slate-100'
              "
              @click.stop="goToPage(page)"
            >
              {{ page }}
            </button>
          </template>

          <button
            type="button"
            class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
            :disabled="
              meta.current_page === meta.last_page
            "
            @click.stop="goToPage(meta.current_page + 1)"
          >
            <ChevronRight class="h-4 w-4" />
          </button>
        </div>
      </div>
    </section>

    <!-- ==================================================================== -->
    <!-- CREATE PAYROLL MODAL                                                 -->
    <!-- ==================================================================== -->

    <Teleport to="body">
      <div
        v-if="showModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/35 px-4 py-6 backdrop-blur-[2px]"
        @click.self="closeCreateModal"
      >
        <div
          class="flex max-h-[calc(100vh-48px)] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl shadow-slate-950/20"
          @click.stop
        >
          <!-- Modal header -->
          <div
            class="flex items-start justify-between border-b border-slate-100 px-6 py-5"
          >
            <div>
              <p class="text-xs font-semibold uppercase tracking-wide text-[#BD2028]">
                Payroll Processing
              </p>

              <h2 class="mt-1 text-lg font-semibold text-slate-900">
                Run Payroll Baru
              </h2>

              <p class="mt-1 text-sm text-slate-500">
                Buat payroll run sebagai draft sebelum proses
                perhitungan dan approval.
              </p>
            </div>

            <button
              type="button"
              class="rounded-xl p-2 text-slate-400 transition hover:bg-slate-50 hover:text-slate-600"
              @click="closeCreateModal"
            >
              <X class="h-5 w-5" />
            </button>
          </div>

          <!-- Progress visual -->
          <div class="border-b border-slate-100 bg-slate-50/60 px-6 py-4">
            <div class="flex items-center gap-2">
              <div
                class="flex h-7 w-7 items-center justify-center rounded-full bg-[#BD2028] text-xs font-bold text-white"
              >
                1
              </div>

              <span class="text-xs font-semibold text-slate-700">
                Setup Payroll
              </span>

              <div class="h-px flex-1 bg-slate-200" />

              <div
                class="flex h-7 w-7 items-center justify-center rounded-full border border-slate-200 bg-white text-xs font-semibold text-slate-400"
              >
                2
              </div>

              <span class="text-xs font-medium text-slate-400">
                Review
              </span>

              <div class="h-px flex-1 bg-slate-200" />

              <div
                class="flex h-7 w-7 items-center justify-center rounded-full border border-slate-200 bg-white text-xs font-semibold text-slate-400"
              >
                3
              </div>

              <span class="text-xs font-medium text-slate-400">
                Process
              </span>
            </div>
          </div>

          <!-- Modal body -->
          <form
            class="flex-1 overflow-y-auto px-6 py-6"
            @submit.prevent="submitForm"
          >
            <div class="space-y-5">
              <!-- Company -->
              <div>
                <label class="mb-1.5 block text-sm font-semibold text-slate-700">
                  Company
                </label>

                <select
                  v-model.number="form.company_id"
                  required
                  class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3.5 text-sm text-slate-700 outline-none transition focus:border-[#BD2028] focus:ring-4 focus:ring-[#BD2028]/10"
                >
                  <option
                    v-for="company in companies"
                    :key="company.id"
                    :value="company.id"
                  >
                    {{ company.name }}
                  </option>
                </select>
              </div>

              <!-- Period -->
              <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                  <label class="mb-1.5 block text-sm font-semibold text-slate-700">
                    Bulan Payroll
                  </label>

                  <select
                    v-model.number="form.period_month"
                    class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3.5 text-sm text-slate-700 outline-none transition focus:border-[#BD2028] focus:ring-4 focus:ring-[#BD2028]/10"
                  >
                    <option
                      v-for="(month, index) in monthNames"
                      :key="index"
                      :value="index + 1"
                    >
                      {{ month }}
                    </option>
                  </select>
                </div>

                <div>
                  <label class="mb-1.5 block text-sm font-semibold text-slate-700">
                    Tahun
                  </label>

                  <input
                    v-model.number="form.period_year"
                    type="number"
                    min="2020"
                    max="2100"
                    class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3.5 text-sm text-slate-700 outline-none transition focus:border-[#BD2028] focus:ring-4 focus:ring-[#BD2028]/10"
                  />
                </div>
              </div>

              <!-- Dates -->
              <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                  <label class="mb-1.5 block text-sm font-semibold text-slate-700">
                    Cutoff Date
                  </label>

                  <input
                    v-model="form.cutoff_date"
                    type="date"
                    class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3.5 text-sm text-slate-700 outline-none transition focus:border-[#BD2028] focus:ring-4 focus:ring-[#BD2028]/10"
                  />
                </div>

                <div>
                  <label class="mb-1.5 block text-sm font-semibold text-slate-700">
                    Payment Date
                  </label>

                  <input
                    v-model="form.payment_date"
                    type="date"
                    class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3.5 text-sm text-slate-700 outline-none transition focus:border-[#BD2028] focus:ring-4 focus:ring-[#BD2028]/10"
                  />
                </div>
              </div>

              <!-- Employees -->
              <div>
                <div class="mb-2 flex items-center justify-between gap-3">
                  <div>
                    <label class="block text-sm font-semibold text-slate-700">
                      Employee
                    </label>

                    <p class="mt-0.5 text-xs text-slate-400">
                      {{ form.employee_ids.length }}
                      employee dipilih
                    </p>
                  </div>

                  <button
                    type="button"
                    class="text-xs font-semibold text-[#BD2028] hover:text-[#9F1B22]"
                    @click="selectAllEmployees"
                  >
                    {{
                      allEmployeesSelected
                        ? 'Batalkan Semua'
                        : 'Pilih Semua'
                    }}
                  </button>
                </div>

                <p
                  v-if="
                    form.company_id &&
                    availableEmployees.length === 0
                  "
                  class="rounded-xl bg-amber-50 px-3.5 py-3 text-xs leading-5 text-amber-700"
                >
                  Company ini belum memiliki employee yang
                  tersedia untuk payroll.
                </p>

                <div
                  v-else
                  class="max-h-60 overflow-y-auto rounded-xl border border-slate-200 bg-white p-2"
                >
                  <label
                    v-for="employee in availableEmployees"
                    :key="employee.id"
                    class="flex cursor-pointer items-center gap-3 rounded-xl px-3 py-2.5 transition hover:bg-slate-50"
                  >
                    <input
                      type="checkbox"
                      :checked="
                        form.employee_ids.includes(
                          employee.id,
                        )
                      "
                      class="h-4 w-4 rounded border-slate-300 text-[#BD2028] accent-[#BD2028] focus:ring-[#BD2028]"
                      @change="
                        toggleEmployee(employee.id)
                      "
                    />

                    <div class="min-w-0">
                      <p class="truncate text-sm font-medium text-slate-700">
                        {{ employeeName(employee) }}
                      </p>

                      <p class="text-[11px] text-slate-400">
                        Employee ID #{{ employee.id }}
                      </p>
                    </div>
                  </label>
                </div>
              </div>

              <!-- Info -->
              <div
                class="rounded-xl border border-[#F1D1D4] bg-[#FDF5F5] p-4"
              >
                <div class="flex items-start gap-3">
                  <div
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#FCEBED] text-[#BD2028]"
                  >
                    <AlertCircle class="h-4 w-4" />
                  </div>

                  <div>
                    <p class="text-sm font-semibold text-slate-700">
                      Payroll akan dibuat sebagai Draft
                    </p>

                    <p class="mt-1 text-xs leading-5 text-slate-500">
                      Setelah disimpan, payroll dapat dibuka
                      untuk review, kalkulasi, approval, lock,
                      dan publish sesuai workflow yang tersedia.
                    </p>
                  </div>
                </div>
              </div>

              <!-- Error -->
              <p
                v-if="formError"
                class="rounded-xl bg-red-50 px-4 py-3 text-sm font-medium text-red-700"
              >
                {{ formError }}
              </p>
            </div>
          </form>

          <!-- Modal footer -->
          <div
            class="flex flex-col-reverse gap-2 border-t border-slate-100 bg-white px-6 py-4 sm:flex-row sm:items-center sm:justify-end"
          >
            <button
              type="button"
              class="rounded-xl px-4 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50"
              @click="closeCreateModal"
            >
              Batal
            </button>

            <button
              type="button"
              :disabled="
                saving ||
                !form.company_id ||
                form.employee_ids.length === 0
              "
              class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#BD2028] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#9F1B22] focus:outline-none focus:ring-4 focus:ring-[#BD2028]/10 disabled:cursor-not-allowed disabled:opacity-50"
              @click="submitForm"
            >
              <RefreshCw
                v-if="saving"
                class="h-4 w-4 animate-spin"
              />

              {{
                saving
                  ? 'Menyimpan...'
                  : 'Simpan sebagai Draft'
              }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ==================================================================== -->
    <!-- BANK SETTING MODAL                                                   -->
    <!-- ==================================================================== -->

    <Teleport to="body">
      <div
        v-if="showBankModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/35 px-4 py-6 backdrop-blur-[2px]"
        @click.self="closeBankModal"
      >
        <div
          class="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl shadow-slate-950/20"
          @click.stop
        >
          <div
            class="flex items-start justify-between border-b border-slate-100 px-6 py-5"
          >
            <div>
              <p class="text-xs font-semibold uppercase tracking-wide text-[#BD2028]">
                Payroll Configuration
              </p>

              <h2 class="mt-1 text-lg font-semibold text-slate-900">
                Pengaturan Rekening Bank
              </h2>

              <p class="mt-1 text-sm leading-5 text-slate-500">
                Rekening sumber pengirim gaji berdasarkan company.
              </p>
            </div>

            <button
              type="button"
              class="rounded-xl p-2 text-slate-400 transition hover:bg-slate-50 hover:text-slate-600"
              @click="closeBankModal"
            >
              <X class="h-5 w-5" />
            </button>
          </div>

          <form
            class="space-y-5 px-6 py-6"
            @submit.prevent="submitBankForm"
          >
            <div>
              <label class="mb-1.5 block text-sm font-semibold text-slate-700">
                Company
              </label>

              <select
                v-model.number="bankForm.company_id"
                class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3.5 text-sm text-slate-700 outline-none transition focus:border-[#BD2028] focus:ring-4 focus:ring-[#BD2028]/10"
              >
                <option
                  v-for="company in companies"
                  :key="company.id"
                  :value="company.id"
                >
                  {{ company.name }}
                </option>
              </select>
            </div>

            <div>
              <label class="mb-1.5 block text-sm font-semibold text-slate-700">
                Nama Bank
              </label>

              <input
                v-model="bankForm.bank_name"
                required
                placeholder="Contoh: Bank Central Asia"
                class="h-11 w-full rounded-xl border border-slate-200 px-3.5 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-[#BD2028] focus:ring-4 focus:ring-[#BD2028]/10"
              />
            </div>

            <div>
              <label class="mb-1.5 block text-sm font-semibold text-slate-700">
                Nomor Rekening
              </label>

              <input
                v-model="bankForm.account_number"
                required
                class="h-11 w-full rounded-xl border border-slate-200 px-3.5 text-sm text-slate-700 outline-none transition focus:border-[#BD2028] focus:ring-4 focus:ring-[#BD2028]/10"
              />
            </div>

            <div>
              <label class="mb-1.5 block text-sm font-semibold text-slate-700">
                Nama Pemilik Rekening
              </label>

              <input
                v-model="bankForm.account_holder_name"
                required
                class="h-11 w-full rounded-xl border border-slate-200 px-3.5 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-[#BD2028] focus:ring-4 focus:ring-[#BD2028]/10"
              />
            </div>

            <div
              v-if="bankError"
              class="rounded-xl bg-red-50 px-4 py-3 text-sm font-medium text-red-700"
            >
              {{ bankError }}
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
              <button
                type="button"
                class="rounded-xl px-4 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50"
                @click="closeBankModal"
              >
                Batal
              </button>

              <button
                type="submit"
                :disabled="bankSaving"
                class="inline-flex items-center gap-2 rounded-xl bg-[#BD2028] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#9F1B22] focus:outline-none focus:ring-4 focus:ring-[#BD2028]/10 disabled:cursor-not-allowed disabled:opacity-50"
              >
                <RefreshCw
                  v-if="bankSaving"
                  class="h-4 w-4 animate-spin"
                />

                {{
                  bankSaving
                    ? 'Menyimpan...'
                    : 'Simpan Pengaturan'
                }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>