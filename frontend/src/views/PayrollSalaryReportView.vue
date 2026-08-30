<script setup lang="ts">
import { ref, onMounted, reactive, computed } from 'vue'
import { Search, Download, FileSpreadsheet, FileText, ChevronLeft, ChevronRight } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface Ref { id: number; name: string }
interface EmployeeOption { id: number; employee_number: string; first_name: string; last_name: string | null }

interface SalaryDetailRow {
  employee_id: number
  employee_number: string
  first_name: string
  last_name: string | null
  basic_salary: string
  allowance_total: string
  gross_earning: string
  structural_deduction: string
  manual_deduction_total: string
  bpjs_employee_total: string
  bpjs_employer_total: string
  tax_amount: string
  loan_deduction_total: string
  net_pay: string
}

interface SalarySummary {
  employee_count: number
  basic_salary: string
  allowance_total: string
  gross_earning: string
  structural_deduction: string
  manual_deduction_total: string
  bpjs_employee_total: string
  bpjs_employer_total: string
  tax_amount: string
  loan_deduction_total: string
  net_pay: string
}

const monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']
const currentYear = new Date().getFullYear()
const yearOptions = Array.from({ length: 6 }, (_, i) => currentYear - i)

function employeeName(row: { first_name: string; last_name: string | null }) {
  return [row.first_name, row.last_name].filter(Boolean).join(' ')
}
function employeeOptionLabel(e: EmployeeOption) {
  return `${employeeName(e)} (${e.employee_number})`
}
function formatCurrency(value: string | number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(Number(value))
}

const viewMode = ref<'detail' | 'summary'>('detail')
const showFilters = ref(true)

const filters = reactive({
  period_year: currentYear,
  period_month: new Date().getMonth() + 1,
  company_id: null as number | null,
  branch_id: null as number | null,
  department_id: null as number | null,
  employee_id: null as number | null,
  page: 1,
})

const activeFilterCount = computed(() =>
  [filters.company_id, filters.branch_id, filters.department_id, filters.employee_id].filter((v) => v !== null).length,
)

const companies = ref<Ref[]>([])
const branches = ref<Ref[]>([])
const departments = ref<Ref[]>([])
const employeeOptions = ref<EmployeeOption[]>([])

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

const detailRows = ref<SalaryDetailRow[]>([])
const summary = ref<SalarySummary | null>(null)
const loading = ref(true)
const errorMessage = ref('')
const meta = ref({ current_page: 1, last_page: 1, total: 0 })
const exporting = ref(false)

function filterParams() {
  return {
    period_year: filters.period_year,
    period_month: filters.period_month,
    company_id: filters.company_id || undefined,
    branch_id: filters.branch_id || undefined,
    department_id: filters.department_id || undefined,
    employee_id: filters.employee_id || undefined,
  }
}

async function loadReport() {
  loading.value = true
  errorMessage.value = ''
  try {
    if (viewMode.value === 'detail') {
      const response = await apiClient.get('/api/payroll-reports/salary/detail', { params: { ...filterParams(), page: filters.page } })
      detailRows.value = response.data.data.data
      meta.value = { current_page: response.data.data.current_page, last_page: response.data.data.last_page, total: response.data.data.total }
    } else {
      const response = await apiClient.get('/api/payroll-reports/salary/summary', { params: filterParams() })
      summary.value = response.data.data
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Gagal memuat salary report.'
  } finally {
    loading.value = false
  }
}

function applyFilters() {
  filters.page = 1
  loadReport()
}
function goToPage(page: number) {
  if (page < 1 || page > meta.value.last_page) return
  filters.page = page
  loadReport()
}
function switchMode(mode: 'detail' | 'summary') {
  viewMode.value = mode
  loadReport()
}

function handleExport(format: 'excel' | 'pdf') {
  exporting.value = true
  try {
    const params = new URLSearchParams()
    Object.entries(filterParams()).forEach(([key, value]) => {
      if (value !== undefined) params.set(key, String(value))
    })
    const baseUrl = apiClient.defaults.baseURL ?? ''
    const endpoint = viewMode.value === 'detail' ? 'detail' : 'summary'
    window.open(`${baseUrl}/api/payroll-reports/salary/${endpoint}/export/${format}?${params.toString()}`, '_blank')
  } finally {
    exporting.value = false
  }
}

onMounted(async () => {
  await loadReferenceData()
  await loadReport()
})
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Salary Reports</h1>
      <p class="mt-1 text-sm text-slate-500">Rekap gaji per periode — detail per karyawan atau ringkasan total.</p>
    </div>

    <!-- Toggle Detail / Summary -->
    <div class="flex gap-1 rounded-xl bg-slate-100 p-1 w-fit">
      <button @click="switchMode('detail')" class="rounded-lg px-4 py-1.5 text-sm font-medium" :class="viewMode === 'detail' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500'">
        Salary Detail
      </button>
      <button @click="switchMode('summary')" class="rounded-lg px-4 py-1.5 text-sm font-medium" :class="viewMode === 'summary' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500'">
        Summary / Recapitulation
      </button>
    </div>

    <!-- Filter -->
    <div class="space-y-3 rounded-2xl border border-slate-100 bg-white p-4">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-end gap-3">
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">Tahun</label>
            <select v-model.number="filters.period_year" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
              <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">Bulan</label>
            <select v-model.number="filters.period_month" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
              <option v-for="(m, i) in monthNames" :key="i" :value="i + 1">{{ m }}</option>
            </select>
          </div>
          <button @click="applyFilters" class="flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-dark">
            <Search class="h-4 w-4" :stroke-width="1.75" /> Terapkan
          </button>
        </div>

        <button type="button" @click="showFilters = !showFilters" class="flex items-center gap-1 text-sm font-medium text-primary-dark hover:underline">
          {{ showFilters ? 'Sembunyikan filter' : 'Semua filter' }}
          <span v-if="activeFilterCount > 0" class="rounded-full bg-primary-soft px-1.5 py-0.5 text-[11px] font-semibold text-primary-dark">{{ activeFilterCount }}</span>
        </button>
      </div>

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
          <select v-model="filters.employee_id" class="min-w-[220px] rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
            <option :value="null">Semua</option>
            <option v-for="e in employeeOptions" :key="e.id" :value="e.id">{{ employeeOptionLabel(e) }}</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Export -->
    <div class="flex gap-2">
      <button @click="handleExport('excel')" :disabled="exporting" class="flex items-center gap-1.5 rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-50">
        <FileSpreadsheet class="h-4 w-4" :stroke-width="1.75" /> Export Excel
      </button>
      <button @click="handleExport('pdf')" :disabled="exporting" class="flex items-center gap-1.5 rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-50">
        <FileText class="h-4 w-4" :stroke-width="1.75" /> Export PDF
      </button>
    </div>

    <div v-if="errorMessage" class="rounded-xl bg-red-50 p-3 text-sm text-red-600">{{ errorMessage }}</div>
    <div v-if="loading" class="text-sm text-slate-400">Memuat...</div>

    <!-- SUMMARY MODE -->
    <div v-else-if="viewMode === 'summary' && summary" class="rounded-2xl border border-slate-100 bg-white p-6">
      <p class="mb-4 text-sm text-slate-500">{{ monthNames[filters.period_month - 1] }} {{ filters.period_year }} · {{ summary.employee_count }} karyawan</p>
      <div class="grid grid-cols-2 gap-x-12 gap-y-3 text-sm md:grid-cols-3">
        <div class="flex justify-between gap-4"><span class="text-slate-500">Basic Salary</span><span class="font-medium text-slate-800">{{ formatCurrency(summary.basic_salary) }}</span></div>
        <div class="flex justify-between gap-4"><span class="text-slate-500">Allowance</span><span class="font-medium text-slate-800">{{ formatCurrency(summary.allowance_total) }}</span></div>
        <div class="flex justify-between gap-4"><span class="text-slate-500">Gross Earning</span><span class="font-medium text-slate-800">{{ formatCurrency(summary.gross_earning) }}</span></div>
        <div class="flex justify-between gap-4"><span class="text-slate-500">Structural Deduction</span><span class="font-medium text-slate-800">{{ formatCurrency(summary.structural_deduction) }}</span></div>
        <div class="flex justify-between gap-4"><span class="text-slate-500">Manual Deduction</span><span class="font-medium text-slate-800">{{ formatCurrency(summary.manual_deduction_total) }}</span></div>
        <div class="flex justify-between gap-4"><span class="text-slate-500">BPJS Employee</span><span class="font-medium text-slate-800">{{ formatCurrency(summary.bpjs_employee_total) }}</span></div>
        <div class="flex justify-between gap-4"><span class="text-slate-500">BPJS Company</span><span class="font-medium text-slate-800">{{ formatCurrency(summary.bpjs_employer_total) }}</span></div>
        <div class="flex justify-between gap-4"><span class="text-slate-500">PPh21</span><span class="font-medium text-slate-800">{{ formatCurrency(summary.tax_amount) }}</span></div>
        <div class="flex justify-between gap-4"><span class="text-slate-500">Loan</span><span class="font-medium text-slate-800">{{ formatCurrency(summary.loan_deduction_total) }}</span></div>
      </div>
      <div class="mt-5 flex justify-between border-t border-slate-100 pt-4">
        <span class="text-base font-semibold text-slate-800">Total Net Pay</span>
        <span class="text-base font-semibold text-primary-dark">{{ formatCurrency(summary.net_pay) }}</span>
      </div>
    </div>

    <!-- DETAIL MODE -->
    <div v-else-if="viewMode === 'detail'">
      <div v-if="detailRows.length === 0" class="rounded-xl bg-slate-50 p-6 text-center text-sm text-slate-400">Tidak ada data untuk filter ini.</div>
      <div v-else class="overflow-x-auto rounded-2xl border border-slate-100 bg-white">
        <table class="w-full text-left text-sm">
          <thead>
            <tr class="border-b border-slate-100 bg-slate-50/60">
              <th class="whitespace-nowrap px-4 py-3 font-medium text-slate-500">Employee</th>
              <th class="whitespace-nowrap px-4 py-3 text-right font-medium text-slate-500">Basic Salary</th>
              <th class="whitespace-nowrap px-4 py-3 text-right font-medium text-slate-500">Allowance</th>
              <th class="whitespace-nowrap px-4 py-3 text-right font-medium text-slate-500">Gross Earning</th>
              <th class="whitespace-nowrap px-4 py-3 text-right font-medium text-slate-500">Structural Ded.</th>
              <th class="whitespace-nowrap px-4 py-3 text-right font-medium text-slate-500">Manual Ded.</th>
              <th class="whitespace-nowrap px-4 py-3 text-right font-medium text-slate-500">BPJS Employee</th>
              <th class="whitespace-nowrap px-4 py-3 text-right font-medium text-slate-500">BPJS Company</th>
              <th class="whitespace-nowrap px-4 py-3 text-right font-medium text-slate-500">PPh21</th>
              <th class="whitespace-nowrap px-4 py-3 text-right font-medium text-slate-500">Loan</th>
              <th class="whitespace-nowrap px-4 py-3 text-right font-medium text-slate-500">Net Pay</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in detailRows" :key="row.employee_id" class="border-b border-slate-50 last:border-0 hover:bg-slate-50/50">
              <td class="whitespace-nowrap px-4 py-3">
                <p class="font-medium text-slate-800">{{ employeeName(row) }}</p>
                <p class="text-xs text-slate-400">{{ row.employee_number }}</p>
              </td>
              <td class="px-4 py-3 text-right text-slate-600">{{ formatCurrency(row.basic_salary) }}</td>
              <td class="px-4 py-3 text-right text-slate-600">{{ formatCurrency(row.allowance_total) }}</td>
              <td class="px-4 py-3 text-right text-slate-600">{{ formatCurrency(row.gross_earning) }}</td>
              <td class="px-4 py-3 text-right text-slate-600">{{ formatCurrency(row.structural_deduction) }}</td>
              <td class="px-4 py-3 text-right text-slate-600">{{ formatCurrency(row.manual_deduction_total) }}</td>
              <td class="px-4 py-3 text-right text-slate-600">{{ formatCurrency(row.bpjs_employee_total) }}</td>
              <td class="px-4 py-3 text-right text-slate-600">{{ formatCurrency(row.bpjs_employer_total) }}</td>
              <td class="px-4 py-3 text-right text-slate-600">{{ formatCurrency(row.tax_amount) }}</td>
              <td class="px-4 py-3 text-right text-slate-600">{{ formatCurrency(row.loan_deduction_total) }}</td>
              <td class="px-4 py-3 text-right font-medium text-slate-800">{{ formatCurrency(row.net_pay) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="meta.last_page > 1" class="mt-4 flex items-center justify-between text-sm text-slate-500">
        <span>Halaman {{ meta.current_page }} dari {{ meta.last_page }} · {{ meta.total }} karyawan</span>
        <div class="flex gap-1">
          <button @click="goToPage(meta.current_page - 1)" :disabled="meta.current_page === 1" class="rounded-lg border border-slate-200 p-1.5 disabled:opacity-40">
            <ChevronLeft class="h-4 w-4" />
          </button>
          <button @click="goToPage(meta.current_page + 1)" :disabled="meta.current_page === meta.last_page" class="rounded-lg border border-slate-200 p-1.5 disabled:opacity-40">
            <ChevronRight class="h-4 w-4" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
