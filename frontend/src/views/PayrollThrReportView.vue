<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue'
import { FileSpreadsheet, FileText } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface ThrRow {
  id: number
  employee_number: string
  first_name: string
  last_name: string | null
  service_months: number
  basic_salary: string
  thr_amount: string
  deduction: string
  tax_amount: string
  net_pay: string
  status: string
}

const monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']
const currentYear = new Date().getFullYear()
const yearOptions = Array.from({ length: 6 }, (_, i) => currentYear - i)

function employeeName(row: { first_name: string; last_name: string | null }) {
  return [row.first_name, row.last_name].filter(Boolean).join(' ')
}
function formatCurrency(value: string) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(Number(value))
}

const filters = reactive({
  period_year: currentYear,
  period_month: new Date().getMonth() + 1,
  company_id: null as number | null,
})

const companies = ref<{ id: number; name: string }[]>([])
const rows = ref<ThrRow[]>([])
const loading = ref(true)
const errorMessage = ref('')
const exporting = ref(false)

async function loadReferenceData() {
  const response = await apiClient.get('/api/companies')
  companies.value = response.data.data.data
}

function filterParams() {
  return {
    period_year: filters.period_year,
    period_month: filters.period_month,
    company_id: filters.company_id || undefined,
  }
}

async function loadReport() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/payroll-reports/thr/detail', { params: filterParams() })
    rows.value = response.data.data
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Gagal memuat THR Report. Pastikan Anda punya izin.'
  } finally {
    loading.value = false
  }
}

function handleExport(format: 'excel' | 'pdf') {
  exporting.value = true
  try {
    const params = new URLSearchParams()
    Object.entries(filterParams()).forEach(([key, value]) => {
      if (value !== undefined) params.set(key, String(value))
    })
    const baseUrl = apiClient.defaults.baseURL ?? ''
    window.open(`${baseUrl}/api/payroll-reports/thr/detail/export/${format}?${params.toString()}`, '_blank')
  } finally {
    exporting.value = false
  }
}

onMounted(() => {
  loadReport()
  loadReferenceData()
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">THR Report</h1>
        <p class="mt-1 text-sm text-slate-500">Detail THR per employee: masa kerja, basic salary, prorata, pajak, net THR.</p>
      </div>
      <div class="flex gap-2">
        <button @click="handleExport('excel')" :disabled="exporting" class="flex items-center gap-1.5 rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-50">
          <FileSpreadsheet class="h-4 w-4" :stroke-width="1.75" /> Excel
        </button>
        <button @click="handleExport('pdf')" :disabled="exporting" class="flex items-center gap-1.5 rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-50">
          <FileText class="h-4 w-4" :stroke-width="1.75" /> PDF
        </button>
      </div>
    </div>

    <div class="flex flex-wrap items-end gap-3 rounded-2xl border border-slate-100 bg-white p-4">
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-500">Tahun</label>
        <select v-model.number="filters.period_year" @change="loadReport" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm">
          <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
        </select>
      </div>
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-500">Bulan</label>
        <select v-model.number="filters.period_month" @change="loadReport" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm">
          <option v-for="(m, i) in monthNames" :key="i" :value="i + 1">{{ m }}</option>
        </select>
      </div>
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-500">Company</label>
        <select v-model="filters.company_id" @change="loadReport" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm">
          <option :value="null">Semua</option>
          <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
      </div>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>
    <div v-else-if="rows.length === 0" class="rounded-xl bg-slate-50 p-4 text-sm text-slate-400">
      Tidak ada data THR untuk periode ini.
    </div>

    <div v-else class="overflow-x-auto rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <table class="w-full text-left text-sm">
        <thead>
          <tr class="border-b border-slate-100 bg-slate-50/60">
            <th class="px-5 py-3 font-medium text-slate-500">Employee</th>
            <th class="px-5 py-3 font-medium text-slate-500">Masa Kerja</th>
            <th class="px-5 py-3 text-right font-medium text-slate-500">Basic Salary</th>
            <th class="px-5 py-3 text-right font-medium text-slate-500">THR Amount</th>
            <th class="px-5 py-3 text-right font-medium text-slate-500">Deduction</th>
            <th class="px-5 py-3 text-right font-medium text-slate-500">PPh21</th>
            <th class="px-5 py-3 text-right font-medium text-slate-500">Net THR</th>
            <th class="px-5 py-3 font-medium text-slate-500">Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id" class="border-b border-slate-50 last:border-0 hover:bg-slate-50/50">
            <td class="px-5 py-3.5">
              <p class="font-medium text-slate-800">{{ employeeName(row) }}</p>
              <p class="text-xs text-slate-400">{{ row.employee_number }}</p>
            </td>
            <td class="px-5 py-3.5 text-slate-500">{{ row.service_months }} bulan</td>
            <td class="px-5 py-3.5 text-right text-slate-500">{{ formatCurrency(row.basic_salary) }}</td>
            <td class="px-5 py-3.5 text-right font-medium text-slate-800">{{ formatCurrency(row.thr_amount) }}</td>
            <td class="px-5 py-3.5 text-right text-slate-500">{{ formatCurrency(row.deduction) }}</td>
            <td class="px-5 py-3.5 text-right text-slate-500">{{ formatCurrency(row.tax_amount) }}</td>
            <td class="px-5 py-3.5 text-right font-semibold text-primary-dark">{{ formatCurrency(row.net_pay) }}</td>
            <td class="px-5 py-3.5">
              <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium capitalize text-slate-600">{{ row.status }}</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>