<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { X, Loader2, AlertTriangle, Wallet, Paperclip, Filter, Receipt } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

type ClaimStatus = 'pending' | 'approved' | 'rejected' | 'cancelled'

interface Employee { id: number; first_name: string; last_name: string | null }
interface Attachment { id: number; file_name: string; url: string | null }
interface StepDecision { id: number; sequence: number; status: string; notes: string | null; approval_step: { name: string | null; sequence: number } }
interface ClaimRow {
  id: number
  employee: Employee
  category: { id: number; name: string }
  subcategory: { id: number; name: string } | null
  expense_date: string
  amount: string
  description: string | null
  status: ClaimStatus
  cancel_reason: string | null
  paid_at: string | null
  paid_by?: Employee | null
  payment_note: string | null
  attachments: Attachment[]
  policy_assignment?: { policy: { name: string } }
  approval_request?: { step_decisions: StepDecision[] } | null
}

const statusLabels: Record<ClaimStatus, string> = {
  pending: 'Menunggu Approval', approved: 'Approved', rejected: 'Ditolak', cancelled: 'Dibatalkan',
}
const statusBadgeClass: Record<ClaimStatus, string> = {
  pending: 'bg-amber-50 text-amber-600',
  approved: 'bg-primary-soft text-primary-dark',
  rejected: 'bg-red-50 text-red-600',
  cancelled: 'bg-slate-100 text-slate-500',
}

function employeeName(e: Employee) { return [e.first_name, e.last_name].filter(Boolean).join(' ') }
function formatCurrency(value: string | number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(Number(value))
}
function formatDate(value: string) {
  return new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

const claims = ref<ClaimRow[]>([])
const loading = ref(true)
const errorMessage = ref('')
const statusFilter = ref('')

async function loadList() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/expense-claims', {
      params: { status: statusFilter.value || undefined },
    })
    claims.value = response.data.data.data
  } catch {
    errorMessage.value = 'Gagal memuat daftar Expense Claim.'
  } finally {
    loading.value = false
  }
}

const showDrawer = ref(false)
const drawerTarget = ref<ClaimRow | null>(null)
const actionProcessing = ref(false)
const actionError = ref('')
const showPayForm = ref(false)
const paymentNote = ref('')

async function openDrawer(row: ClaimRow) {
  actionError.value = ''
  showPayForm.value = false
  try {
    const response = await apiClient.get(`/api/expense-claims/${row.id}`)
    drawerTarget.value = response.data.data
    showDrawer.value = true
  } catch {
    errorMessage.value = 'Gagal memuat detail Expense Claim.'
  }
}
function closeDrawer() {
  showDrawer.value = false
  drawerTarget.value = null
}

async function submitPay(row: ClaimRow) {
  actionProcessing.value = true
  actionError.value = ''
  try {
    await apiClient.post(`/api/expense-claims/${row.id}/pay`, { payment_note: paymentNote.value || null })
    showPayForm.value = false
    paymentNote.value = ''
    await Promise.all([loadList(), openDrawer(row)])
  } catch (err: any) {
    actionError.value = err.response?.data?.message || 'Gagal menandai claim sebagai dibayar.'
  } finally {
    actionProcessing.value = false
  }
}

onMounted(loadList)
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Expense Claims</h1>
        <p class="mt-1 text-sm text-slate-500">Semua pengajuan Expense Claim karyawan.</p>
      </div>
      <div class="flex items-center gap-2">
        <Filter class="h-4 w-4 text-slate-400" :stroke-width="1.75" />
        <select v-model="statusFilter" @change="loadList" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
          <option value="">Semua status</option>
          <option v-for="(label, value) in statusLabels" :key="value" :value="value">{{ label }}</option>
        </select>
      </div>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>

    <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <table class="w-full text-left text-sm">
        <thead>
          <tr class="border-b border-slate-100 bg-slate-50/60">
            <th class="px-5 py-3 font-medium text-slate-500">Employee</th>
            <th class="px-5 py-3 font-medium text-slate-500">Category</th>
            <th class="px-5 py-3 font-medium text-slate-500">Tanggal</th>
            <th class="px-5 py-3 text-right font-medium text-slate-500">Amount</th>
            <th class="px-5 py-3 font-medium text-slate-500">Status</th>
            <th class="px-5 py-3 font-medium text-slate-500">Pembayaran</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="claims.length === 0"><td colspan="6" class="px-5 py-6 text-center text-sm text-slate-400">Tidak ada data.</td></tr>
          <tr v-for="row in claims" :key="row.id" @click="openDrawer(row)" class="cursor-pointer border-b border-slate-50 last:border-0 hover:bg-slate-50/50">
            <td class="px-5 py-3.5 font-medium text-slate-800">{{ employeeName(row.employee) }}</td>
            <td class="px-5 py-3.5 text-slate-600">{{ row.category.name }}<span v-if="row.subcategory" class="text-xs text-slate-400"> / {{ row.subcategory.name }}</span></td>
            <td class="px-5 py-3.5 text-slate-500">{{ formatDate(row.expense_date) }}</td>
            <td class="px-5 py-3.5 text-right font-medium text-slate-700">{{ formatCurrency(row.amount) }}</td>
            <td class="px-5 py-3.5"><span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusBadgeClass[row.status]">{{ statusLabels[row.status] }}</span></td>
            <td class="px-5 py-3.5">
              <span v-if="row.paid_at" class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-600">Sudah dibayar</span>
              <span v-else-if="row.status === 'approved'" class="text-xs text-slate-400">Belum dibayar</span>
              <span v-else class="text-xs text-slate-300">-</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <Teleport to="body">
      <div v-if="showDrawer && drawerTarget" class="fixed inset-0 z-50 flex justify-end bg-slate-900/30">
        <div class="h-full w-full max-w-lg overflow-y-auto bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <div>
              <h2 class="text-lg font-semibold text-slate-900">{{ employeeName(drawerTarget.employee) }}</h2>
              <p class="text-sm text-slate-500">
                {{ drawerTarget.category.name }}<span v-if="drawerTarget.subcategory"> / {{ drawerTarget.subcategory.name }}</span>
                <span v-if="drawerTarget.policy_assignment"> · {{ drawerTarget.policy_assignment.policy.name }}</span>
              </p>
            </div>
            <button @click="closeDrawer" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-50"><X class="h-5 w-5" :stroke-width="1.75" /></button>
          </div>

          <div class="space-y-4 px-6 py-5">
            <span class="inline-block rounded-full px-2 py-0.5 text-xs font-medium" :class="statusBadgeClass[drawerTarget.status]">{{ statusLabels[drawerTarget.status] }}</span>

            <div class="rounded-xl bg-primary-soft p-4">
              <p class="text-xs text-primary-dark">Amount</p>
              <p class="text-xl font-semibold text-primary-dark">{{ formatCurrency(drawerTarget.amount) }}</p>
              <p class="mt-1 text-xs text-slate-500">Expense date {{ formatDate(drawerTarget.expense_date) }}</p>
            </div>

            <div v-if="drawerTarget.description">
              <p class="mb-1 text-xs font-medium text-slate-500">Deskripsi</p>
              <p class="text-sm text-slate-700">{{ drawerTarget.description }}</p>
            </div>

            <div v-if="drawerTarget.attachments.length > 0">
              <p class="mb-1.5 text-xs font-medium text-slate-500">Attachment</p>
              <div class="flex flex-wrap gap-2">
                <a v-for="att in drawerTarget.attachments" :key="att.id" :href="att.url ?? '#'" target="_blank" class="flex items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs text-slate-600 hover:bg-slate-50">
                  <Paperclip class="h-3.5 w-3.5" :stroke-width="1.75" /> {{ att.file_name }}
                </a>
              </div>
            </div>

            <div v-if="drawerTarget.approval_request && drawerTarget.approval_request.step_decisions.length > 0" class="rounded-xl border border-slate-200 p-3">
              <p class="mb-2 text-xs font-medium text-slate-500">Approval Trail</p>
              <div v-for="d in drawerTarget.approval_request.step_decisions" :key="d.id" class="mb-1.5 flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-xs last:mb-0">
                <span class="text-slate-600">{{ d.approval_step.name ?? `Step ${d.approval_step.sequence}` }}</span>
                <span class="capitalize text-slate-700">{{ d.status }}</span>
              </div>
            </div>

            <div v-if="drawerTarget.status === 'cancelled' && drawerTarget.cancel_reason" class="rounded-xl bg-slate-50 p-3 text-xs text-slate-500">
              <p class="font-medium text-slate-600">Alasan dibatalkan</p>
              <p class="mt-1">{{ drawerTarget.cancel_reason }}</p>
            </div>

            <div v-if="drawerTarget.paid_at" class="rounded-xl bg-emerald-50 p-3 text-xs text-emerald-700">
              <p class="font-medium">Sudah Dibayar</p>
              <p class="mt-1">{{ formatDate(drawerTarget.paid_at) }}<span v-if="drawerTarget.payment_note"> · {{ drawerTarget.payment_note }}</span></p>
            </div>

            <div v-if="actionError" class="flex items-start gap-2 rounded-xl bg-red-50 p-3 text-xs text-red-600">
              <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" :stroke-width="1.75" /><p>{{ actionError }}</p>
            </div>

            <div v-if="showPayForm" class="space-y-2 rounded-xl border border-emerald-200 p-3">
              <textarea v-model="paymentNote" rows="2" placeholder="Catatan pembayaran (opsional)..." class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"></textarea>
              <div class="flex gap-2">
                <button @click="showPayForm = false" class="flex-1 rounded-lg border border-slate-200 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50">Batal</button>
                <button @click="submitPay(drawerTarget)" :disabled="actionProcessing" class="flex-1 rounded-lg bg-emerald-600 py-2 text-xs font-medium text-white hover:bg-emerald-700 disabled:opacity-50">Konfirmasi Bayar</button>
              </div>
            </div>
          </div>

          <div v-if="!showPayForm" class="border-t border-slate-100 px-6 py-4">
            <button
              v-if="drawerTarget.status === 'approved' && !drawerTarget.paid_at"
              @click="showPayForm = true"
              class="flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 py-2.5 text-sm font-medium text-white hover:bg-emerald-700"
            >
              <Wallet class="h-4 w-4" :stroke-width="1.75" /> Mark as Paid
            </button>
            <p v-else-if="drawerTarget.status === 'pending'" class="text-center text-xs text-slate-400">Belum bisa dibayar — masih menunggu approval.</p>
            <p v-else-if="drawerTarget.paid_at" class="text-center text-xs text-slate-400">Claim ini sudah dibayar (read-only).</p>
            <p v-else class="text-center text-xs text-slate-400">Claim ini {{ statusLabels[drawerTarget.status].toLowerCase() }}, tidak bisa diproses lagi.</p>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>