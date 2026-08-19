<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { X, Loader2, AlertTriangle, Wallet, Paperclip, Filter } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

type CaStatus = 'pending_approval' | 'approved' | 'rejected' | 'cancelled' | 'need_settlement' | 'settlement_on_review' | 'completed'

interface Employee { id: number; first_name: string; last_name: string | null }
interface Item { id: number; name: string; amount: string; category: { name: string } }
interface Attachment { id: number; file_name: string; url: string | null }
interface Settlement { id: number; status: string; total_actual_amount: string; total_returned_amount: string }
interface CashAdvanceRow {
  id: number
  employee: Employee
  policy: { name: string }
  purpose: string
  date_of_use: string
  total_amount: string
  status: CaStatus
  submitted_at: string
  disbursed_at: string | null
  disbursement_note: string | null
  items: Item[]
  attachments: Attachment[]
  settlements: Settlement[]
}

const statusLabels: Record<CaStatus, string> = {
  pending_approval: 'Menunggu Approval', approved: 'Approved', rejected: 'Ditolak', cancelled: 'Dibatalkan',
  need_settlement: 'Perlu Settlement', settlement_on_review: 'Settlement Direview', completed: 'Selesai',
}
const statusBadgeClass: Record<CaStatus, string> = {
  pending_approval: 'bg-amber-50 text-amber-600',
  approved: 'bg-primary-soft text-primary-dark',
  rejected: 'bg-red-50 text-red-600',
  cancelled: 'bg-slate-100 text-slate-500',
  need_settlement: 'bg-orange-50 text-orange-600',
  settlement_on_review: 'bg-amber-50 text-amber-600',
  completed: 'bg-emerald-50 text-emerald-600',
}

function employeeName(e: Employee) { return [e.first_name, e.last_name].filter(Boolean).join(' ') }
function formatCurrency(value: string | number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(Number(value))
}
function formatDate(value: string) {
  return new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

const cashAdvances = ref<CashAdvanceRow[]>([])
const loading = ref(true)
const errorMessage = ref('')
const statusFilter = ref('')
const searchQuery = ref('')

async function loadList() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/cash-advances', {
      params: { status: statusFilter.value || undefined, search: searchQuery.value || undefined },
    })
    cashAdvances.value = response.data.data.data
  } catch {
    errorMessage.value = 'Gagal memuat daftar Cash Advance.'
  } finally {
    loading.value = false
  }
}

const showDrawer = ref(false)
const drawerTarget = ref<CashAdvanceRow | null>(null)
const actionProcessing = ref(false)
const actionError = ref('')
const showDisburseForm = ref(false)
const disburseNote = ref('')

async function openDrawer(row: CashAdvanceRow) {
  actionError.value = ''
  showDisburseForm.value = false
  try {
    const response = await apiClient.get(`/api/cash-advances/${row.id}`)
    drawerTarget.value = response.data.data
    showDrawer.value = true
  } catch {
    errorMessage.value = 'Gagal memuat detail Cash Advance.'
  }
}
function closeDrawer() {
  showDrawer.value = false
  drawerTarget.value = null
}

async function submitDisburse(row: CashAdvanceRow) {
  actionProcessing.value = true
  actionError.value = ''
  try {
    await apiClient.post(`/api/cash-advances/${row.id}/disburse`, { disbursement_note: disburseNote.value || null })
    showDisburseForm.value = false
    await Promise.all([loadList(), openDrawer(row)])
  } catch (err: any) {
    actionError.value = err.response?.data?.message || 'Gagal mencatat disbursement.'
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
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Cash Advance</h1>
        <p class="mt-1 text-sm text-slate-500">Semua pengajuan Cash Advance karyawan.</p>
      </div>
      <div class="flex items-center gap-2">
        <input v-model="searchQuery" @keyup.enter="loadList" placeholder="Cari purpose..." class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
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
            <th class="px-5 py-3 font-medium text-slate-500">Purpose</th>
            <th class="px-5 py-3 font-medium text-slate-500">Policy</th>
            <th class="px-5 py-3 font-medium text-slate-500">Tanggal</th>
            <th class="px-5 py-3 text-right font-medium text-slate-500">Amount</th>
            <th class="px-5 py-3 font-medium text-slate-500">Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="cashAdvances.length === 0"><td colspan="6" class="px-5 py-6 text-center text-sm text-slate-400">Tidak ada data.</td></tr>
          <tr v-for="row in cashAdvances" :key="row.id" @click="openDrawer(row)" class="cursor-pointer border-b border-slate-50 last:border-0 hover:bg-slate-50/50">
            <td class="px-5 py-3.5 font-medium text-slate-800">{{ employeeName(row.employee) }}</td>
            <td class="px-5 py-3.5 text-slate-600">{{ row.purpose }}</td>
            <td class="px-5 py-3.5 text-slate-500">{{ row.policy.name }}</td>
            <td class="px-5 py-3.5 text-slate-500">{{ formatDate(row.date_of_use) }}</td>
            <td class="px-5 py-3.5 text-right font-medium text-slate-700">{{ formatCurrency(row.total_amount) }}</td>
            <td class="px-5 py-3.5"><span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusBadgeClass[row.status]">{{ statusLabels[row.status] }}</span></td>
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
              <p class="text-sm text-slate-500">{{ drawerTarget.purpose }} · {{ drawerTarget.policy.name }}</p>
            </div>
            <button @click="closeDrawer" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-50"><X class="h-5 w-5" :stroke-width="1.75" /></button>
          </div>

          <div class="space-y-4 px-6 py-5">
            <span class="inline-block rounded-full px-2 py-0.5 text-xs font-medium" :class="statusBadgeClass[drawerTarget.status]">{{ statusLabels[drawerTarget.status] }}</span>

            <div class="rounded-xl bg-primary-soft p-4">
              <p class="text-xs text-primary-dark">Total</p>
              <p class="text-xl font-semibold text-primary-dark">{{ formatCurrency(drawerTarget.total_amount) }}</p>
              <p class="mt-1 text-xs text-slate-500">Tanggal pakai {{ formatDate(drawerTarget.date_of_use) }}</p>
            </div>

            <div>
              <p class="mb-1.5 text-xs font-medium text-slate-500">Detail</p>
              <ul class="space-y-1.5">
                <li v-for="item in drawerTarget.items" :key="item.id" class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm">
                  <span class="text-slate-700">{{ item.name }} <span class="text-xs text-slate-400">({{ item.category.name }})</span></span>
                  <span class="font-medium text-slate-800">{{ formatCurrency(item.amount) }}</span>
                </li>
              </ul>
            </div>

            <div v-if="drawerTarget.attachments.length > 0">
              <p class="mb-1.5 text-xs font-medium text-slate-500">Attachment</p>
              <div class="flex flex-wrap gap-2">
                <a v-for="att in drawerTarget.attachments" :key="att.id" :href="att.url ?? '#'" target="_blank" class="flex items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs text-slate-600 hover:bg-slate-50">
                  <Paperclip class="h-3.5 w-3.5" :stroke-width="1.75" /> {{ att.file_name }}
                </a>
              </div>
            </div>

            <div v-if="drawerTarget.disbursed_at" class="rounded-xl bg-emerald-50 p-3 text-xs text-emerald-700">
              <p class="font-medium">Sudah Dicairkan</p>
              <p class="mt-1">{{ formatDate(drawerTarget.disbursed_at) }}<span v-if="drawerTarget.disbursement_note"> · {{ drawerTarget.disbursement_note }}</span></p>
            </div>

            <div v-if="drawerTarget.settlements.length > 0" class="rounded-xl border border-slate-200 p-3">
              <p class="mb-2 text-xs font-medium text-slate-500">Settlement</p>
              <div v-for="s in drawerTarget.settlements" :key="s.id" class="mb-1.5 flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-xs">
                <span class="capitalize text-slate-600">{{ s.status }}</span>
                <span class="text-slate-700">Actual {{ formatCurrency(s.total_actual_amount) }} · Returned {{ formatCurrency(s.total_returned_amount) }}</span>
              </div>
            </div>

            <div v-if="actionError" class="flex items-start gap-2 rounded-xl bg-red-50 p-3 text-xs text-red-600">
              <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" :stroke-width="1.75" /><p>{{ actionError }}</p>
            </div>

            <div v-if="showDisburseForm" class="space-y-2 rounded-xl border border-emerald-200 p-3">
              <textarea v-model="disburseNote" rows="2" placeholder="Catatan pembayaran (opsional)..." class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"></textarea>
              <div class="flex gap-2">
                <button @click="showDisburseForm = false" class="flex-1 rounded-lg border border-slate-200 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50">Batal</button>
                <button @click="submitDisburse(drawerTarget)" :disabled="actionProcessing" class="flex-1 rounded-lg bg-emerald-600 py-2 text-xs font-medium text-white hover:bg-emerald-700 disabled:opacity-50">Konfirmasi Bayar</button>
              </div>
            </div>
          </div>

          <div v-if="!showDisburseForm" class="border-t border-slate-100 px-6 py-4">
            <button
              v-if="drawerTarget.status === 'approved'"
              @click="showDisburseForm = true"
              class="flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 py-2.5 text-sm font-medium text-white hover:bg-emerald-700"
            >
              <Wallet class="h-4 w-4" :stroke-width="1.75" /> Disburse
            </button>
            <p v-else-if="drawerTarget.status === 'pending_approval'" class="text-center text-xs text-slate-400">Belum bisa disburse — masih menunggu approval.</p>
            <p v-else-if="drawerTarget.status === 'completed'" class="text-center text-xs text-slate-400">Cash Advance ini sudah selesai (read-only).</p>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>