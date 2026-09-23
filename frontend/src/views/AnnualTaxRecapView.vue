<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue'
import { FileSpreadsheet, FileDown } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface RecapRow {
  id: number
  employee_id: number
  employee_number: string
  first_name: string
  last_name: string | null
  tax_year: number
  tax_method_applied: string
  total_gross_annual: string
  position_cost_deduction: string
  pension_deduction: string
  net_annual_income: string
  ptkp_amount: string
  pkp: string
  annual_tax_pasal17: string
  total_withheld_prior_months: string
  final_period_adjustment: string
}

const statusLabel: Record<string, string> = {
  kurang_bayar: 'Kurang Bayar',
  lebih_bayar: 'Lebih Bayar',
  nihil: 'Nihil',
}
const statusClass: Record<string, string> = {
  kurang_bayar: 'bg-amber-50 text-amber-700',
  lebih_bayar: 'bg-blue-50 text-blue-700',
  nihil: 'bg-emerald-50 text-emerald-700',
}

function rowStatus(row: RecapRow): string {
  const adj = Number(row.final_period_adjustment)
  if (adj > 0) return 'kurang_bayar'
  if (adj < 0) return 'lebih_bayar'
  return 'nihil'
}

function employeeName(row: { first_name: string; last_name: string | null }) {
  return [row.first_name, row.last_name].filter(Boolean).join(' ')
}
function formatCurrency(value: string) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(Number(value))
}

const currentYear = new Date().getFullYear()
const yearOptions = Array.from({ length: 6 }, (_, i) => currentYear - i)

const filters = reactive({
  tax_year: currentYear,
  company_id: null as number | null,
})

const companies = ref<{ id: number; name: string }[]>([])
const rows = ref<RecapRow[]>([])
const loading = ref(true)
const errorMessage = ref('')
const exporting = ref(false)
const downloadingBpa1 = ref<number | null>(null)

async function loadReferenceData() {
  const response = await apiClient.get('/api/companies')
  companies.value = response.data.data.data
}

function filterParams() {
  return {
    tax_year: filters.tax_year,
    company_id: filters.company_id || undefined,
  }
}

async function loadReport() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/payroll-reports/annual-tax-recap', { params: filterParams() })
    rows.value = response.data.data
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Gagal memuat Annual Tax Recap. Pastikan Anda punya izin.'
  } finally {
    loading.value = false
  }
}

function handleExportExcel() {
  exporting.value = true
  try {
    const params = new URLSearchParams()
    Object.entries(filterParams()).forEach(([key, value]) => {
      if (value !== undefined) params.set(key, String(value))
    })
    const baseUrl = apiClient.defaults.baseURL ?? ''
    window.open(`${baseUrl}/api/payroll-reports/annual-tax-recap/export/excel?${params.toString()}`, '_blank')
  } finally {
    exporting.value = false
  }
}

async function downloadBpa1(row: RecapRow) {
  downloadingBpa1.value = row.employee_id
  try {
    const response = await apiClient.get(`/api/employees/${row.employee_id}/bpa1`, {
      params: { tax_year: row.tax_year },
      responseType: 'blob',
    })
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.download = `bpa1-${row.employee_number}-${row.tax_year}.pdf`
    link.click()
    window.URL.revokeObjectURL(url)
  } catch {
    alert('Gagal generate BPA1 untuk employee ini.')
  } finally {
    downloadingBpa1.value = null
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
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Annual Tax Recap</h1>
        <p class="mt-1 text-sm text-slate-500">Rekonsiliasi PPh 21 tahunan per employee — dasar BPA1 (dulu 1721-A1) & bahan input Coretax e-Bupot.</p>
      </div>
      <button @click="handleExportExcel" :disabled="exporting" class="flex items-center gap-1.5 rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-50">
        <FileSpreadsheet class="h-4 w-4" :stroke-width="1.75" /> Excel
      </button>
    </div>

    <p class="rounded-xl bg-amber-50 p-3 text-xs text-amber-700">
      Dokumen BPA1 yang di-generate di sini adalah data pendukung internal — <strong>bukan</strong> Bukti Potong resmi yang diterbitkan lewat e-Bupot Coretax DJP.
      Tetap perlu diinput/divalidasi manual di Coretax sesuai ketentuan PER-11/PJ/2025.
    </p>

    <div class="flex flex-wrap items-end gap-3 rounded-2xl border border-slate-100 bg-white p-4">
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-500">Tahun Pajak</label>
        <select v-model.number="filters.tax_year" @change="loadReport" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm">
          <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
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
      Belum ada rekonsiliasi pajak tahunan untuk tahun pajak ini. Data baru muncul setelah payroll run periode final (Desember/resign) diproses.
    </div>

    <div v-else class="overflow-x-auto rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <table class="w-full text-left text-sm">
        <thead>
          <tr class="border-b border-slate-100 bg-slate-50/60">
            <th class="px-5 py-3 font-medium text-slate-500">Employee</th>
            <th class="px-5 py-3 text-right font-medium text-slate-500">Bruto Setahun</th>
            <th class="px-5 py-3 text-right font-medium text-slate-500">PTKP</th>
            <th class="px-5 py-3 text-right font-medium text-slate-500">PKP</th>
            <th class="px-5 py-3 text-right font-medium text-slate-500">PPh Setahun</th>
            <th class="px-5 py-3 text-right font-medium text-slate-500">Kurang/(Lebih) Bayar</th>
            <th class="px-5 py-3 font-medium text-slate-500">Status</th>
            <th class="px-5 py-3 text-right font-medium text-slate-500">BPA1</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id" class="border-b border-slate-50 last:border-0 hover:bg-slate-50/50">
            <td class="px-5 py-3.5">
              <p class="font-medium text-slate-800">{{ employeeName(row) }}</p>
              <p class="text-xs text-slate-400">{{ row.employee_number }}</p>
            </td>
            <td class="px-5 py-3.5 text-right text-slate-500">{{ formatCurrency(row.total_gross_annual) }}</td>
            <td class="px-5 py-3.5 text-right text-slate-500">{{ formatCurrency(row.ptkp_amount) }}</td>
            <td class="px-5 py-3.5 text-right text-slate-500">{{ formatCurrency(row.pkp) }}</td>
            <td class="px-5 py-3.5 text-right font-medium text-slate-800">{{ formatCurrency(row.annual_tax_pasal17) }}</td>
            <td class="px-5 py-3.5 text-right font-semibold" :class="Number(row.final_period_adjustment) < 0 ? 'text-blue-600' : 'text-amber-600'">
              {{ formatCurrency(row.final_period_adjustment) }}
            </td>
            <td class="px-5 py-3.5">
              <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="statusClass[rowStatus(row)]">
                {{ statusLabel[rowStatus(row)] }}
              </span>
            </td>
            <td class="px-5 py-3.5 text-right">
              <button
                @click="downloadBpa1(row)"
                :disabled="downloadingBpa1 === row.employee_id"
                class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-50"
              >
                <FileDown class="h-3.5 w-3.5" :stroke-width="1.75" />
                {{ downloadingBpa1 === row.employee_id ? 'Memproses...' : 'BPA1' }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
