<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { X } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

const monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']
const lineTypeLabels: Record<string, string> = {
  earning: 'Penambah', deduction: 'Potongan', bpjs_employee: 'BPJS (Karyawan)',
  bpjs_employer: 'BPJS (Company)', tax: 'PPh 21', loan_installment: 'Cicilan Loan',
}

interface PayslipLine { id: number; type: string; label: string; amount: string }
interface PayslipRow {
  id: number
  net_pay: string
  gross_earning: string
  payroll_run: { period_year: number; period_month: number }
  lines?: PayslipLine[]
}

function formatCurrency(value: string | number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(Number(value))
}

const payslips = ref<PayslipRow[]>([])
const loading = ref(true)
const errorMessage = ref('')

async function loadPayslips() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/my-payslips')
    payslips.value = response.data.data.data
  } catch {
    errorMessage.value = 'Gagal memuat payslip.'
  } finally {
    loading.value = false
  }
}

const showDrawer = ref(false)
const drawerTarget = ref<PayslipRow | null>(null)
const drawerLoading = ref(false)

async function openDrawer(payslip: PayslipRow) {
  showDrawer.value = true
  drawerLoading.value = true
  try {
    const response = await apiClient.get(`/api/my-payslips/${payslip.id}`)
    drawerTarget.value = response.data.data
  } finally {
    drawerLoading.value = false
  }
}
function closeDrawer() {
  showDrawer.value = false
  drawerTarget.value = null
}

onMounted(loadPayslips)
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Payslip Saya</h1>
      <p class="mt-1 text-sm text-slate-500">Riwayat slip gaji yang sudah dipublish.</p>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>
    <div v-else-if="payslips.length === 0" class="rounded-xl bg-slate-50 p-6 text-center text-sm text-slate-400">Belum ada payslip yang dipublish.</div>

    <div v-else class="space-y-3">
      <div v-for="row in payslips" :key="row.id" @click="openDrawer(row)" class="flex cursor-pointer items-center justify-between rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)] hover:bg-slate-50/50">
        <div>
          <p class="font-medium text-slate-800">{{ monthNames[row.payroll_run.period_month - 1] }} {{ row.payroll_run.period_year }}</p>
          <p class="mt-0.5 text-xs text-slate-500">Gross {{ formatCurrency(row.gross_earning) }}</p>
        </div>
        <p class="font-semibold text-primary-dark">{{ formatCurrency(row.net_pay) }}</p>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="showDrawer" class="fixed inset-0 z-50 flex justify-end bg-slate-900/30">
        <div class="h-full w-full max-w-md overflow-y-auto bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Detail Payslip</h2>
            <button @click="closeDrawer" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50"><X class="h-5 w-5" /></button>
          </div>

          <div v-if="drawerLoading" class="p-6 text-sm text-slate-400">Memuat...</div>

          <div v-else-if="drawerTarget" class="space-y-5 px-6 py-5">
            <div class="rounded-xl bg-primary-soft p-4">
              <p class="text-xs text-primary-dark">Net Pay</p>
              <p class="text-xl font-semibold text-primary-dark">{{ formatCurrency(drawerTarget.net_pay) }}</p>
              <p class="mt-1 text-xs text-slate-500">{{ monthNames[drawerTarget.payroll_run.period_month - 1] }} {{ drawerTarget.payroll_run.period_year }}</p>
            </div>

            <div class="border-t border-slate-100 pt-4">
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Breakdown</p>
              <div class="space-y-1.5">
                <div v-for="line in drawerTarget.lines" :key="line.id" class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-xs">
                  <div>
                    <span class="text-slate-600">{{ line.label }}</span>
                    <span class="ml-2 text-slate-400">{{ lineTypeLabels[line.type] ?? line.type }}</span>
                  </div>
                  <span class="font-medium text-slate-700">{{ formatCurrency(line.amount) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>