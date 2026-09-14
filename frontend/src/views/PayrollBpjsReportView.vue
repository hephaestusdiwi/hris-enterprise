<script setup lang="ts">
import { ref, onMounted, reactive, computed } from 'vue'
import { Search, FileSpreadsheet, FileText, ChevronLeft, ChevronRight } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface Ref { id: number; name: string }

interface BpjsDetailRow {
  employee_id: number
  employee_number: string
  first_name: string
  last_name: string | null
  npp_number: string | null
  kesehatan_employee: string
  kesehatan_employer: string
  jht_employee: string
  jht_employer: string
  jkk_employer: string
  jkm_employer: string
  bpjs_employee_total: string
  bpjs_employer_total: string
}

interface BpjsSummary {
  employee_count: number
  kesehatan_employee: string
  kesehatan_employer: string
  jht_employee: string
  jht_employer: string
  jkk_employer: string
  jkm_employer: string
  bpjs_employee_total: string
  bpjs_employer_total: string
}

const monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']
const currentYear = new Date().getFullYear()
const yearOptions = Array.from({ length: 6 }, (_, i) => currentYear - i)

function employeeName(row: { first_name: string; last_name: string | null }) {
  return [row.first_name, row.last_name].filter(Boolean).join(' ')
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
  page: 1,
})

const activeFilterCount = computed(() => [filters.company_id, filters.branch_id].filter((v) => v !== null).length)

const companies = ref<Ref[]>([])
const branches = ref<Ref[]>([])

async function loadReferenceData() {
  const [companyRes, branchRes] = await Promise.all([
    apiClient.get('/api/companies'),
    apiClient.get('/api/branches'),
  ])
  companies.value = companyRes.data.data.data
  branches.value = branchRes.data.data.data
}

const detailRows = ref<BpjsDetailRow[]>([])
const summary = ref<BpjsSummary | null>(null)
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
  }
}

async function loadReport() {
  loading.value = true
  errorMessage.value = ''
  try {
    if (viewMode.value === 'detail') {
      const response = await apiClient.get('/api/payroll-reports/bpjs/detail', { params: { ...filterParams(), page: filters.page } })
      detailRows.value = response.data.data.data
      meta.value = { current_page: response.data.data.current_page, last_page: response.data.data.last_page, total: response.data.data.total }
    } else {
      const response = await apiClient.get('/api/payroll-reports/bpjs/summary', { params: filterParams() })
      summary.value = response.data.data
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Gagal memuat BPJS report.'
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
    window.open(`${baseUrl}/api/payroll-reports/bpjs/${endpoint}/export/${format}?${params.toString()}`, '_blank')
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
      <h1 class="text-2xl font-semibold tracking-tight text-slate-900">BPJS Reports</h1>
      <p class="mt-1 text-sm text-slate-500">Kontribusi BPJS Kesehatan, JHT, JKK, JKM per periode — karyawan & company.</p>
    </div>

    <div class="flex gap-1 rounded-xl bg-slate-100 p-1 w-fit">
      <button @click="switchMode('detail')" class="rounded-lg px-4 py-1.5 text-sm font-medium" :class="viewMode === 'detail' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500'">
        BPJS Detail
      </button>
      <button @click="switchMode('summary')" class="rounded-lg px-4 py-1.5 text-sm font-medium" :class="viewMode === 'summary' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500'">
        Summary
      </button>
    </div>

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
      </div>
    </div>

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
        <div class="flex justify-between gap-4"><span class="text-slate-500">Kesehatan (Karyawan)</span><span class="font-medium text-slate-800">{{ formatCurrency(summary.kesehatan_employee) }}</span></div>
        <div class="flex justify-between gap-4"><span class="text-slate-500">Kesehatan (Company)</span><span class="font-medium text-slate-800">{{ formatCurrency(summary.kesehatan_employer) }}</span></div>
        <div class="flex justify-between gap-4"><span class="text-slate-500">JHT (Karyawan)</span><span class="font-medium text-slate-800">{{ formatCurrency(summary.jht_employee) }}</span></div>
        <div class="flex justify-between gap-4"><span class="text-slate-500">JHT (Company)</span><span class="font-medium text-slate-800">{{ formatCurrency(summary.jht_employer) }}</span></div>
        <div class="flex justify-between gap-4"><span class="text-slate-500">JKK (Company)</span><span class="font-medium text-slate-800">{{ formatCurrency(summary.jkk_employer) }}</span></div>
        <div class="flex justify-between gap-4"><span class="text-slate-500">JKM (Company)</span><span class="font-medium text-slate-800">{{ formatCurrency(summary.jkm_employer) }}</span></div>
      </div>
      <p class="mt-3 text-xs text-slate-400">JP belum tersedia — belum diaktifkan di engine kalkulasi BPJS.</p>
      <div class="mt-5 grid grid-cols-2 gap-4 border-t border-slate-100 pt-4">
        <div class="flex justify-between"><span class="text-base font-semibold text-slate-800">Total Karyawan</span><span class="text-base font-semibold text-primary-dark">{{ formatCurrency(summary.bpjs_employee_total) }}</span></div>
        <div class="flex justify-between"><span class="text-base font-semibold text-slate-800">Total Company</span><span class="text-base font-semibold text-primary-dark">{{ formatCurrency(summary.bpjs_employer_total) }}</span></div>
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
              <th class="whitespace-nowrap px-4 py-3 font-medium text-slate-500">NPP</th>
              <th class="whitespace-nowrap px-4 py-3 text-right font-medium text-slate-500">Kesehatan (Karyawan)</th>
              <th class="whitespace-nowrap px-4 py-3 text-right font-medium text-slate-500">Kesehatan (Company)</th>
              <th class="whitespace-nowrap px-4 py-3 text-right font-medium text-slate-500">JHT (Karyawan)</th>
              <th class="whitespace-nowrap px-4 py-3 text-right font-medium text-slate-500">JHT (Company)</th>
              <th class="whitespace-nowrap px-4 py-3 text-right font-medium text-slate-500">JKK (Company)</th>
              <th class="whitespace-nowrap px-4 py-3 text-right font-medium text-slate-500">JKM (Company)</th>
              <th class="whitespace-nowrap px-4 py-3 text-right font-medium text-slate-500">Total Karyawan</th>
              <th class="whitespace-nowrap px-4 py-3 text-right font-medium text-slate-500">Total Company</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in detailRows" :key="row.employee_id" class="border-b border-slate-50 last:border-0 hover:bg-slate-50/50">
              <td class="whitespace-nowrap px-4 py-3">
                <p class="font-medium text-slate-800">{{ employeeName(row) }}</p>
                <p class="text-xs text-slate-400">{{ row.employee_number }}</p>
              </td>
              <td class="whitespace-nowrap px-4 py-3 text-slate-500">{{ row.npp_number ?? '-' }}</td>
              <td class="px-4 py-3 text-right text-slate-600">{{ formatCurrency(row.kesehatan_employee) }}</td>
              <td class="px-4 py-3 text-right text-slate-600">{{ formatCurrency(row.kesehatan_employer) }}</td>
              <td class="px-4 py-3 text-right text-slate-600">{{ formatCurrency(row.jht_employee) }}</td>
              <td class="px-4 py-3 text-right text-slate-600">{{ formatCurrency(row.jht_employer) }}</td>
              <td class="px-4 py-3 text-right text-slate-600">{{ formatCurrency(row.jkk_employer) }}</td>
              <td class="px-4 py-3 text-right text-slate-600">{{ formatCurrency(row.jkm_employer) }}</td>
              <td class="px-4 py-3 text-right font-medium text-slate-800">{{ formatCurrency(row.bpjs_employee_total) }}</td>
              <td class="px-4 py-3 text-right font-medium text-slate-800">{{ formatCurrency(row.bpjs_employer_total) }}</td>
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
