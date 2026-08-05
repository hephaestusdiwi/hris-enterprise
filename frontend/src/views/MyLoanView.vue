<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { X } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

type LoanStatus = 'draft' | 'pending' | 'approved' | 'rejected' | 'active' | 'completed' | 'cancelled'
type InstallmentStatus = 'scheduled' | 'paid' | 'skipped' | 'cancelled'

interface LoanInstallmentRow {
  id: number
  installment_number: number
  payroll_period_year: number
  payroll_period_month: number
  amount: string
  status: InstallmentStatus
}

interface LoanRow {
  id: number
  principal: string
  interest_rate: string | null
  tenor: number
  installment_amount: string
  total_repayment: string
  purpose: string | null
  status: LoanStatus
  installments?: LoanInstallmentRow[]
}

const monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']

const statusLabels: Record<LoanStatus, string> = {
  draft: 'Draft', pending: 'Menunggu Approval', approved: 'Approved', rejected: 'Ditolak',
  active: 'Active', completed: 'Lunas', cancelled: 'Dibatalkan',
}
const statusBadgeClass: Record<LoanStatus, string> = {
  draft: 'bg-slate-100 text-slate-500',
  pending: 'bg-amber-50 text-amber-600',
  approved: 'bg-blue-50 text-blue-600',
  rejected: 'bg-red-50 text-red-600',
  active: 'bg-primary-soft text-primary-dark',
  completed: 'bg-emerald-50 text-emerald-600',
  cancelled: 'bg-slate-100 text-slate-500',
}
const installmentStatusLabels: Record<InstallmentStatus, string> = {
  scheduled: 'Terjadwal', paid: 'Terbayar', skipped: 'Dilewati', cancelled: 'Dibatalkan',
}
const installmentStatusClass: Record<InstallmentStatus, string> = {
  scheduled: 'bg-slate-100 text-slate-500',
  paid: 'bg-emerald-50 text-emerald-600',
  skipped: 'bg-amber-50 text-amber-600',
  cancelled: 'bg-red-50 text-red-600',
}

function formatCurrency(value: string | number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(Number(value))
}

const loans = ref<LoanRow[]>([])
const loading = ref(true)
const errorMessage = ref('')

async function loadLoans() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/my-loans')
    loans.value = response.data.data.data
  } catch {
    errorMessage.value = 'Gagal memuat riwayat loan.'
  } finally {
    loading.value = false
  }
}

const showDrawer = ref(false)
const drawerTarget = ref<LoanRow | null>(null)
const drawerLoading = ref(false)

async function openDrawer(loan: LoanRow) {
  showDrawer.value = true
  drawerLoading.value = true
  try {
    const response = await apiClient.get(`/api/my-loans/${loan.id}`)
    drawerTarget.value = response.data.data
  } catch {
    drawerTarget.value = null
  } finally {
    drawerLoading.value = false
  }
}
function closeDrawer() {
  showDrawer.value = false
  drawerTarget.value = null
}

onMounted(loadLoans)
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Loan Saya</h1>
      <p class="mt-1 text-sm text-slate-500">Riwayat pinjaman dan jadwal potong payroll Anda.</p>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>
    <div v-else-if="loans.length === 0" class="rounded-xl bg-slate-50 p-6 text-center text-sm text-slate-400">
      Belum ada loan.
    </div>

    <div v-else class="space-y-3">
      <div
        v-for="row in loans"
        :key="row.id"
        @click="openDrawer(row)"
        class="flex cursor-pointer items-center justify-between rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)] hover:bg-slate-50/50"
      >
        <div>
          <p class="font-medium text-slate-800">{{ formatCurrency(row.principal) }}</p>
          <p class="mt-0.5 text-xs text-slate-500">{{ row.tenor }}x cicilan · {{ formatCurrency(row.installment_amount) }}/bulan</p>
        </div>
        <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="statusBadgeClass[row.status]">
          {{ statusLabels[row.status] }}
        </span>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="showDrawer" class="fixed inset-0 z-50 flex justify-end bg-slate-900/30">
        <div class="h-full w-full max-w-md overflow-y-auto bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Detail Loan</h2>
            <button @click="closeDrawer" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50"><X class="h-5 w-5" /></button>
          </div>

          <div v-if="drawerLoading" class="p-6 text-sm text-slate-400">Memuat...</div>

          <div v-else-if="drawerTarget" class="space-y-5 px-6 py-5">
            <span class="inline-block rounded-full px-2.5 py-1 text-xs font-medium" :class="statusBadgeClass[drawerTarget.status]">
              {{ statusLabels[drawerTarget.status] }}
            </span>

            <div class="rounded-xl bg-primary-soft p-4">
              <p class="text-xs text-primary-dark">Principal</p>
              <p class="text-xl font-semibold text-primary-dark">{{ formatCurrency(drawerTarget.principal) }}</p>
              <p class="mt-1 text-xs text-slate-500">
                {{ drawerTarget.tenor }}x cicilan · {{ formatCurrency(drawerTarget.installment_amount) }}/bulan
                <span v-if="drawerTarget.interest_rate">· bunga {{ drawerTarget.interest_rate }}%</span>
              </p>
            </div>

            <div v-if="drawerTarget.purpose">
              <p class="text-xs text-slate-400">Tujuan</p>
              <p class="text-sm text-slate-600">{{ drawerTarget.purpose }}</p>
            </div>

            <div v-if="drawerTarget.installments && drawerTarget.installments.length > 0" class="border-t border-slate-100 pt-4">
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Jadwal Cicilan</p>
              <div class="space-y-1.5">
                <div v-for="i in drawerTarget.installments" :key="i.id" class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-xs">
                  <span class="text-slate-500">#{{ i.installment_number }} · {{ monthNames[i.payroll_period_month - 1] }} {{ i.payroll_period_year }}</span>
                  <span class="font-medium text-slate-700">{{ formatCurrency(i.amount) }}</span>
                  <span class="rounded-full px-2 py-0.5 font-medium" :class="installmentStatusClass[i.status]">{{ installmentStatusLabels[i.status] }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>