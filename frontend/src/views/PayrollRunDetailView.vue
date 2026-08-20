<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft, Send, RotateCcw, Play, Lock, Eye, EyeOff, Ban, X } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

type RunStatus = 'draft' | 'pending_approval' | 'approved' | 'processed' | 'locked' | 'cancelled'

interface Employee { id: number; first_name: string; last_name: string | null }
interface PayslipLine { id: number; type: string; source: string; label: string; amount: string }
interface Payslip {
  id: number
  employee: Employee
  gross_earning: string
  structural_deduction: string
  manual_deduction_total: string
  bpjs_employee_total: string
  bpjs_employer_total: string
  tax_amount: string
  loan_deduction_total: string
  net_pay: string
  is_published: boolean
  lines: PayslipLine[]
}
interface StepDecision { id: number; sequence: number; status: string; approval_step: { name: string | null; sequence: number } }
interface RunDetail {
  id: number
  period_year: number
  period_month: number
  status: RunStatus
  current_revision: number
  published_at: string | null
  participants: Employee[]
  current_revision_data?: { payslips: Payslip[] }
  currentRevision?: { payslips: Payslip[] }
  approval_request?: { step_decisions: StepDecision[] } | null
}

const monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']
const statusLabels: Record<RunStatus, string> = {
  draft: 'Draft', pending_approval: 'Menunggu Approval', approved: 'Approved',
  processed: 'Processed', locked: 'Locked', cancelled: 'Dibatalkan',
}
const statusBadgeClass: Record<RunStatus, string> = {
  draft: 'bg-slate-100 text-slate-500',
  pending_approval: 'bg-amber-50 text-amber-600',
  approved: 'bg-blue-50 text-blue-600',
  processed: 'bg-primary-soft text-primary-dark',
  locked: 'bg-emerald-50 text-emerald-600',
  cancelled: 'bg-red-50 text-red-600',
}
const lineTypeLabels: Record<string, string> = {
  earning: 'Penambah', deduction: 'Potongan', bpjs_employee: 'BPJS (Karyawan)',
  bpjs_employer: 'BPJS (Company)', tax: 'PPh 21', loan_installment: 'Cicilan Loan',
}

function employeeName(e: { first_name: string; last_name: string | null }) {
  return [e.first_name, e.last_name].filter(Boolean).join(' ')
}
function formatCurrency(value: string | number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(Number(value))
}

const route = useRoute()
const router = useRouter()
const runId = route.params.id as string

const run = ref<RunDetail | null>(null)
const loading = ref(true)
const actionError = ref('')
const actionProcessing = ref(false)
const viewMode = ref<'overview' | 'detail'>('overview')
const selectedPayslip = ref<Payslip | null>(null)

const payslips = computed(() => run.value?.currentRevision?.payslips ?? [])
const totalNetPay = computed(() => payslips.value.reduce((sum, p) => sum + Number(p.net_pay), 0))

async function loadRun() {
  loading.value = true
  actionError.value = ''
  try {
    const response = await apiClient.get(`/api/payroll-runs/${runId}`)
    run.value = response.data.data
  } catch {
    actionError.value = 'Gagal memuat payroll run.'
  } finally {
    loading.value = false
  }
}

function openDetail(payslip: Payslip) {
  selectedPayslip.value = payslip
  viewMode.value = 'detail'
}
function backToOverview() {
  viewMode.value = 'overview'
  selectedPayslip.value = null
}

async function proceedPayslip() {
  if (!confirm('Generate payslip untuk semua peserta? Ini akan membuat revisi baru.')) return
  actionProcessing.value = true
  actionError.value = ''
  try {
    await apiClient.post(`/api/payroll-runs/${runId}/proceed-payslip`)
    await loadRun()
  } catch (err: any) {
    actionError.value = err.response?.data?.message || 'Gagal generate payslip.'
  } finally {
    actionProcessing.value = false
  }
}

async function requestApproval() {
  if (!confirm('Ajukan payroll run ini untuk approval Lock?')) return
  actionProcessing.value = true
  actionError.value = ''
  try {
    await apiClient.post(`/api/payroll-runs/${runId}/request-approval`)
    await loadRun()
  } catch (err: any) {
    actionError.value = err.response?.data?.message || 'Gagal mengajukan approval.'
  } finally {
    actionProcessing.value = false
  }
}

const showRecalcForm = ref(false)
const recalcReason = ref('')

async function submitRecalculate() {
  if (!recalcReason.value.trim()) return
  actionProcessing.value = true
  actionError.value = ''
  try {
    await apiClient.post(`/api/payroll-runs/${runId}/proceed-payslip`, { note: recalcReason.value })
    showRecalcForm.value = false
    recalcReason.value = ''
    await loadRun()
  } catch (err: any) {
    actionError.value = err.response?.data?.message || 'Gagal recalculate.'
  } finally {
    actionProcessing.value = false
  }
}

async function lockRun() {
  if (!confirm('Lock payroll run ini? Setelah Lock, data TIDAK BISA diubah lagi lewat flow normal.')) return
  actionProcessing.value = true
  actionError.value = ''
  try {
    await apiClient.post(`/api/payroll-runs/${runId}/lock`)
    await loadRun()
  } catch (err: any) {
    actionError.value = err.response?.data?.message || 'Gagal lock.'
  } finally {
    actionProcessing.value = false
  }
}

async function publishRun() {
  actionProcessing.value = true
  actionError.value = ''
  try {
    await apiClient.post(`/api/payroll-runs/${runId}/publish`)
    await loadRun()
  } catch (err: any) {
    actionError.value = err.response?.data?.message || 'Gagal publish.'
  } finally {
    actionProcessing.value = false
  }
}

async function unpublishRun() {
  actionProcessing.value = true
  try {
    await apiClient.post(`/api/payroll-runs/${runId}/unpublish`)
    await loadRun()
  } finally {
    actionProcessing.value = false
  }
}

const showCancelForm = ref(false)
const cancelReason = ref('')

async function submitCancel() {
  if (!cancelReason.value.trim()) return
  actionProcessing.value = true
  actionError.value = ''
  try {
    await apiClient.post(`/api/payroll-runs/${runId}/cancel`, { reason: cancelReason.value })
    showCancelForm.value = false
    await loadRun()
  } catch (err: any) {
    actionError.value = err.response?.data?.message || 'Gagal membatalkan.'
  } finally {
    actionProcessing.value = false
  }
}

onMounted(loadRun)
</script>

<template>
  <div class="space-y-6">
    <button @click="router.push('/payroll')" class="flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-700">
      <ArrowLeft class="h-4 w-4" :stroke-width="1.75" /> Kembali ke Payroll History
    </button>

    <div v-if="loading" class="text-sm text-slate-400">Memuat...</div>

    <template v-else-if="run">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold tracking-tight text-slate-900">{{ monthNames[run.period_month - 1] }} {{ run.period_year }}</h1>
          <div class="mt-1 flex items-center gap-2">
            <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="statusBadgeClass[run.status]">{{ statusLabels[run.status] }}</span>
            <span v-if="run.current_revision" class="text-xs text-slate-400">Revisi ke-{{ run.current_revision }}</span>
            <span v-if="run.published_at" class="text-xs text-emerald-600">Published</span>
          </div>
        </div>

        <div v-if="viewMode === 'detail'">
          <button @click="backToOverview" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">
            Kembali ke Overview
          </button>
        </div>
      </div>

      <div v-if="actionError" class="rounded-xl bg-red-50 p-3 text-sm text-red-600">{{ actionError }}</div>

      <!-- ACTIONS -->
      <div class="flex flex-wrap gap-2">
        <button v-if="run.status === 'draft'" @click="proceedPayslip" :disabled="actionProcessing" class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50">
          <Play class="h-4 w-4" :stroke-width="1.75" /> Proses Payroll
        </button>

        <button v-if="run.status === 'processed'" @click="requestApproval" :disabled="actionProcessing" class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50">
          <Send class="h-4 w-4" :stroke-width="1.75" /> Request Approval
        </button>

        <button v-if="['processed', 'pending_approval', 'approved'].includes(run.status)" @click="showRecalcForm = !showRecalcForm" class="flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50">
          <RotateCcw class="h-4 w-4" :stroke-width="1.75" /> Recalculate
        </button>

        <button v-if="run.status === 'approved'" @click="lockRun" :disabled="actionProcessing" class="flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
          <Lock class="h-4 w-4" :stroke-width="1.75" /> Lock Payroll
        </button>

        <button v-if="run.status === 'locked' && !run.published_at" @click="publishRun" :disabled="actionProcessing" class="flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
          <Eye class="h-4 w-4" :stroke-width="1.75" /> Publish Payslip
        </button>
        <button v-if="run.status === 'locked' && run.published_at" @click="unpublishRun" :disabled="actionProcessing" class="flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50">
          <EyeOff class="h-4 w-4" :stroke-width="1.75" /> Unpublish
        </button>

        <button v-if="!['locked', 'cancelled'].includes(run.status)" @click="showCancelForm = !showCancelForm" class="flex items-center gap-2 rounded-xl border border-red-200 px-4 py-2.5 text-sm font-medium text-red-600 hover:bg-red-50">
          <Ban class="h-4 w-4" :stroke-width="1.75" /> Batalkan
        </button>
      </div>

      <div v-if="showRecalcForm" class="space-y-2 rounded-xl border border-slate-200 bg-white p-4">
        <p class="text-xs text-slate-500">
          Menghitung ulang payslip akan membuat revisi baru; payslip revisi sebelumnya tetap tersimpan sebagai histori.
          <template v-if="['pending_approval', 'approved'].includes(run.status)"> Approval yang sedang berjalan akan otomatis dibatalkan dan status turun ke Processed — perlu Request Approval ulang sebelum bisa Lock.</template>
        </p>
        <textarea v-model="recalcReason" rows="2" placeholder="Alasan recalculate (wajib)" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"></textarea>
        <button @click="submitRecalculate" :disabled="!recalcReason.trim() || actionProcessing" class="rounded-xl bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900 disabled:opacity-50">Konfirmasi Recalculate</button>
      </div>

      <div v-if="showCancelForm" class="space-y-2 rounded-xl border border-red-200 bg-white p-4">
        <textarea v-model="cancelReason" rows="2" placeholder="Alasan pembatalan (wajib)" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"></textarea>
        <button @click="submitCancel" :disabled="!cancelReason.trim() || actionProcessing" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50">Konfirmasi Batalkan</button>
      </div>

      <!-- Approval steps -->
      <div v-if="run.approval_request" class="rounded-2xl border border-slate-100 bg-white p-4">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Approval</p>
        <div class="space-y-1.5">
          <div v-for="d in run.approval_request.step_decisions" :key="d.id" class="flex items-center justify-between text-sm">
            <span class="text-slate-500">{{ d.approval_step.name ?? `Step ${d.approval_step.sequence}` }}</span>
            <span class="font-medium" :class="{ 'text-emerald-600': d.status === 'approved', 'text-red-600': d.status === 'rejected', 'text-slate-400': d.status === 'pending' }">
              {{ d.status === 'approved' ? 'Disetujui' : d.status === 'rejected' ? 'Ditolak' : 'Menunggu' }}
            </span>
          </div>
        </div>
      </div>

      <!-- Peserta (Draft only) -->
      <div v-if="run.status === 'draft'" class="rounded-2xl border border-slate-100 bg-white p-4">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Peserta ({{ run.participants.length }})</p>
        <div class="flex flex-wrap gap-2">
          <span v-for="p in run.participants" :key="p.id" class="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-600">{{ employeeName(p) }}</span>
        </div>
      </div>

      <!-- OVERVIEW MODE -->
      <div v-if="viewMode === 'overview' && payslips.length > 0" class="space-y-4">
        <div class="rounded-2xl bg-primary-soft p-4">
          <p class="text-xs text-primary-dark">Total Net Pay ({{ payslips.length }} employee)</p>
          <p class="text-2xl font-semibold text-primary-dark">{{ formatCurrency(totalNetPay) }}</p>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-100 bg-white">
          <table class="w-full text-left text-sm">
            <thead>
              <tr class="border-b border-slate-100 bg-slate-50/60">
                <th class="px-5 py-3 font-medium text-slate-500">Employee</th>
                <th class="px-5 py-3 text-right font-medium text-slate-500">Gross</th>
                <th class="px-5 py-3 text-right font-medium text-slate-500">BPJS (Karyawan)</th>
                <th class="px-5 py-3 text-right font-medium text-slate-500">PPh21</th>
                <th class="px-5 py-3 text-right font-medium text-slate-500">Loan</th>
                <th class="px-5 py-3 text-right font-medium text-slate-500">Net Pay</th>
                <th class="px-5 py-3"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="p in payslips" :key="p.id" class="cursor-pointer border-b border-slate-50 last:border-0 hover:bg-slate-50/50" @click="openDetail(p)">
                <td class="px-5 py-3.5 font-medium text-slate-800">{{ employeeName(p.employee) }}</td>
                <td class="px-5 py-3.5 text-right text-slate-600">{{ formatCurrency(p.gross_earning) }}</td>
                <td class="px-5 py-3.5 text-right text-slate-600">{{ formatCurrency(p.bpjs_employee_total) }}</td>
                <td class="px-5 py-3.5 text-right text-slate-600">{{ formatCurrency(p.tax_amount) }}</td>
                <td class="px-5 py-3.5 text-right text-slate-600">{{ formatCurrency(p.loan_deduction_total) }}</td>
                <td class="px-5 py-3.5 text-right font-medium text-slate-800">{{ formatCurrency(p.net_pay) }}</td>
                <td class="px-5 py-3.5 text-right text-xs font-medium text-primary-dark">Details</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-else-if="viewMode === 'overview' && payslips.length === 0" class="rounded-xl bg-slate-50 p-6 text-center text-sm text-slate-400">
        Payslip belum di-generate — klik "Proses Payroll".
      </div>

      <!-- DETAIL MODE -->
      <div v-if="viewMode === 'detail' && selectedPayslip" class="space-y-4">
        <div class="rounded-2xl border border-slate-100 bg-white p-5">
          <p class="text-sm font-medium text-slate-800">{{ employeeName(selectedPayslip.employee) }}</p>
          <div class="mt-4 grid grid-cols-2 gap-x-8 gap-y-2 text-sm">
            <div class="flex justify-between"><span class="text-slate-500">Gross Earning</span><span class="font-medium text-slate-700">{{ formatCurrency(selectedPayslip.gross_earning) }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Structural Deduction</span><span class="font-medium text-slate-700">{{ formatCurrency(selectedPayslip.structural_deduction) }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Manual Deduction</span><span class="font-medium text-slate-700">{{ formatCurrency(selectedPayslip.manual_deduction_total) }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">BPJS (Karyawan)</span><span class="font-medium text-slate-700">{{ formatCurrency(selectedPayslip.bpjs_employee_total) }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">BPJS (Company)</span><span class="font-medium text-slate-400">{{ formatCurrency(selectedPayslip.bpjs_employer_total) }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">PPh 21</span><span class="font-medium text-slate-700">{{ formatCurrency(selectedPayslip.tax_amount) }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Cicilan Loan</span><span class="font-medium text-slate-700">{{ formatCurrency(selectedPayslip.loan_deduction_total) }}</span></div>
          </div>
          <div class="mt-4 border-t border-slate-100 pt-4">
            <div class="flex justify-between text-base">
              <span class="font-semibold text-slate-800">Net Pay</span>
              <span class="font-semibold text-primary-dark">{{ formatCurrency(selectedPayslip.net_pay) }}</span>
            </div>
          </div>
        </div>

        <div class="rounded-2xl border border-slate-100 bg-white p-5">
          <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Breakdown Komponen</p>
          <div class="space-y-1.5">
            <div v-for="line in selectedPayslip.lines" :key="line.id" class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm">
              <div>
                <span class="text-slate-700">{{ line.label }}</span>
                <span class="ml-2 text-xs text-slate-400">{{ lineTypeLabels[line.type] ?? line.type }}</span>
              </div>
              <span class="font-medium text-slate-700">{{ formatCurrency(line.amount) }}</span>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>