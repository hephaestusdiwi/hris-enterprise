<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue'
import { Plus, X, Loader2, AlertTriangle, Calculator, Ban, Send, Wallet, Pencil } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface Employee { id: number; first_name: string; last_name: string | null; department?: { id: number; name: string } | null }

type LoanStatus = 'draft' | 'pending' | 'approved' | 'rejected' | 'active' | 'completed' | 'cancelled'
type InstallmentStatus = 'scheduled' | 'paid' | 'skipped' | 'cancelled'

interface LoanInstallmentRow {
  id: number
  installment_number: number
  payroll_period_year: number
  payroll_period_month: number
  principal_portion: string
  interest_portion: string
  amount: string
  status: InstallmentStatus
  paid_at: string | null
}

interface StepDecision {
  id: number
  sequence: number
  status: string
  approval_step: { id: number; name: string | null; sequence: number }
}

interface LoanRow {
  id: number
  employee_id: number
  employee: Employee
  principal: string
  interest_rate: string | null
  tenor: number
  installment_amount: string
  total_repayment: string
  first_deduction_period_year: number
  first_deduction_period_month: number
  purpose: string | null
  status: LoanStatus
  cancel_reason: string | null
  installments?: LoanInstallmentRow[]
  approval_request?: { status: string; step_decisions: StepDecision[] } | null
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

function employeeName(e: { first_name: string; last_name: string | null }) {
  return [e.first_name, e.last_name].filter(Boolean).join(' ')
}
function formatCurrency(value: string | number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(Number(value))
}

// ---------- DATA ----------
const loans = ref<LoanRow[]>([])
const employees = ref<Employee[]>([])
const loading = ref(true)
const errorMessage = ref('')
const meta = ref({ current_page: 1, last_page: 1, total: 0 })

const filters = reactive({
  employee_id: null as number | null,
  status: null as LoanStatus | null,
  page: 1,
})

function buildParams() {
  return {
    employee_id: filters.employee_id || undefined,
    status: filters.status || undefined,
    page: filters.page,
  }
}

async function loadLoans() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/loans', { params: buildParams() })
    loans.value = response.data.data.data
    meta.value = {
      current_page: response.data.data.current_page,
      last_page: response.data.data.last_page,
      total: response.data.data.total,
    }
  } catch {
    errorMessage.value = 'Gagal memuat daftar loan.'
  } finally {
    loading.value = false
  }
}

async function loadEmployees() {
  const response = await apiClient.get('/api/employees', { params: { per_page: 100 } })
  employees.value = response.data.data.data
}

function applyFilters() {
  filters.page = 1
  loadLoans()
}
function goToPage(page: number) {
  filters.page = page
  loadLoans()
}

// ---------- CREATE / EDIT FORM ----------
const showFormModal = ref(false)
const formMode = ref<'create' | 'edit'>('create')
const saving = ref(false)
const formError = ref('')
const editingLoanId = ref<number | null>(null)

const form = reactive({
  employee_id: null as number | null,
  principal: null as number | null,
  interest_rate: null as number | null,
  tenor: 12,
  first_deduction_date: '',
  purpose: '',
})

const previewResult = ref<{ installment_amount: string; total_repayment: string; rows: any[] } | null>(null)
const previewing = ref(false)
const previewError = ref('')

async function runPreview() {
  if (!form.principal || !form.tenor) return
  previewing.value = true
  previewError.value = ''
  try {
    const response = await apiClient.post('/api/loans/preview', {
      principal: form.principal,
      interest_rate: form.interest_rate,
      tenor: form.tenor,
    })
    previewResult.value = response.data.data
  } catch (err: any) {
    previewError.value = err.response?.data?.message || 'Gagal menghitung preview.'
  } finally {
    previewing.value = false
  }
}

function openCreateModal() {
  formMode.value = 'create'
  formError.value = ''
  previewResult.value = null
  editingLoanId.value = null
  form.employee_id = employees.value[0]?.id ?? null
  form.principal = null
  form.interest_rate = null
  form.tenor = 12
  form.first_deduction_date = new Date().toISOString().slice(0, 10)
  form.purpose = ''
  showFormModal.value = true
}

function openEditForm(loan: LoanRow) {
  formMode.value = 'edit'
  formError.value = ''
  previewResult.value = null
  editingLoanId.value = loan.id
  form.employee_id = loan.employee_id
  form.principal = Number(loan.principal)
  form.interest_rate = loan.interest_rate ? Number(loan.interest_rate) : null
  form.tenor = loan.tenor
  form.first_deduction_date = `${loan.first_deduction_period_year}-${String(loan.first_deduction_period_month).padStart(2, '0')}-01`
  form.purpose = loan.purpose ?? ''
  showFormModal.value = true
}

async function submitForm() {
  formError.value = ''
  saving.value = true
  try {
    if (formMode.value === 'create') {
      await apiClient.post('/api/loans', form)
    } else {
      await apiClient.put(`/api/loans/${editingLoanId.value}`, form)
    }
    showFormModal.value = false
    await loadLoans()
    if (drawerTarget.value && formMode.value === 'edit') await openDrawer({ id: editingLoanId.value } as LoanRow)
  } catch (err: any) {
    formError.value = err.response?.data?.message || 'Gagal menyimpan loan.'
  } finally {
    saving.value = false
  }
}

// ---------- DRAWER DETAIL ----------
const showDrawer = ref(false)
const drawerTarget = ref<LoanRow | null>(null)
const drawerLoading = ref(false)
const actionError = ref('')
const actionProcessing = ref(false)

async function openDrawer(loan: LoanRow) {
  showDrawer.value = true
  drawerLoading.value = true
  actionError.value = ''
  showCancelForm.value = false
  cancelReason.value = ''
  try {
    const response = await apiClient.get(`/api/loans/${loan.id}`)
    drawerTarget.value = response.data.data
  } catch {
    actionError.value = 'Gagal memuat detail loan.'
  } finally {
    drawerLoading.value = false
  }
}
function closeDrawer() {
  showDrawer.value = false
  drawerTarget.value = null
}

async function submitLoan(loan: LoanRow) {
  actionProcessing.value = true
  actionError.value = ''
  try {
    await apiClient.post(`/api/loans/${loan.id}/submit`)
    await Promise.all([loadLoans(), openDrawer(loan)])
  } catch (err: any) {
    actionError.value = err.response?.data?.message || 'Gagal submit loan.'
  } finally {
    actionProcessing.value = false
  }
}

async function disburseLoan(loan: LoanRow) {
  if (!confirm('Cairkan loan ini? Jadwal cicilan akan otomatis dibuat dan tidak bisa diubah strukturnya lagi.')) return
  actionProcessing.value = true
  actionError.value = ''
  try {
    await apiClient.post(`/api/loans/${loan.id}/disburse`)
    await Promise.all([loadLoans(), openDrawer(loan)])
  } catch (err: any) {
    actionError.value = err.response?.data?.message || 'Gagal mencairkan loan.'
  } finally {
    actionProcessing.value = false
  }
}

const showCancelForm = ref(false)
const cancelReason = ref('')

async function submitCancel(loan: LoanRow) {
  if (!cancelReason.value.trim()) return
  actionProcessing.value = true
  actionError.value = ''
  try {
    await apiClient.post(`/api/loans/${loan.id}/cancel`, { reason: cancelReason.value })
    showCancelForm.value = false
    await Promise.all([loadLoans(), openDrawer(loan)])
  } catch (err: any) {
    actionError.value = err.response?.data?.message || 'Gagal membatalkan loan.'
  } finally {
    actionProcessing.value = false
  }
}

onMounted(() => {
  loadLoans()
  loadEmployees()
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Payroll Loan</h1>
        <p class="mt-1 text-sm text-slate-500">Pinjaman karyawan yang dipotong bertahap lewat payroll.</p>
      </div>
      <button @click="openCreateModal" :disabled="employees.length === 0" class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50">
        <Plus class="h-4 w-4" :stroke-width="2" />
        Buat Loan
      </button>
    </div>

    <!-- FILTERS -->
    <div class="flex flex-wrap items-end gap-3 rounded-2xl border border-slate-100 bg-white p-4">
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-500">Employee</label>
        <select v-model="filters.employee_id" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
          <option :value="null">Semua Employee</option>
          <option v-for="e in employees" :key="e.id" :value="e.id">{{ employeeName(e) }}</option>
        </select>
      </div>
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-500">Status</label>
        <select v-model="filters.status" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
          <option :value="null">Semua Status</option>
          <option v-for="(label, value) in statusLabels" :key="value" :value="value">{{ label }}</option>
        </select>
      </div>
      <button @click="applyFilters" class="rounded-xl bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900">
        Terapkan
      </button>
    </div>

    <!-- TABLE -->
    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>
    <div v-else-if="loans.length === 0" class="rounded-xl bg-slate-50 p-6 text-center text-sm text-slate-400">
      Belum ada loan untuk filter ini.
    </div>

    <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <table class="w-full text-left text-sm">
        <thead>
          <tr class="border-b border-slate-100 bg-slate-50/60">
            <th class="px-5 py-3 font-medium text-slate-500">Employee</th>
            <th class="px-5 py-3 text-right font-medium text-slate-500">Principal</th>
            <th class="px-5 py-3 text-center font-medium text-slate-500">Tenor</th>
            <th class="px-5 py-3 text-right font-medium text-slate-500">Cicilan/Bulan</th>
            <th class="px-5 py-3 font-medium text-slate-500">Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in loans" :key="row.id" class="cursor-pointer border-b border-slate-50 last:border-0 hover:bg-slate-50/50" @click="openDrawer(row)">
            <td class="px-5 py-3.5">
              <p class="font-medium text-slate-800">{{ employeeName(row.employee) }}</p>
              <p class="text-xs text-slate-400">{{ row.employee.department?.name ?? '-' }}</p>
            </td>
            <td class="px-5 py-3.5 text-right font-medium text-slate-700">{{ formatCurrency(row.principal) }}</td>
            <td class="px-5 py-3.5 text-center text-slate-500">{{ row.tenor }}x</td>
            <td class="px-5 py-3.5 text-right text-slate-500">{{ formatCurrency(row.installment_amount) }}</td>
            <td class="px-5 py-3.5">
              <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="statusBadgeClass[row.status]">{{ statusLabels[row.status] }}</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="meta.last_page > 1" class="flex items-center justify-between text-sm text-slate-500">
      <p>Total {{ meta.total }} loan</p>
      <div class="flex gap-1">
        <button v-for="page in meta.last_page" :key="page" @click="goToPage(page)" class="rounded-lg px-3 py-1.5 text-xs font-medium" :class="page === meta.current_page ? 'bg-primary text-white' : 'text-slate-500 hover:bg-slate-100'">
          {{ page }}
        </button>
      </div>
    </div>

    <!-- CREATE / EDIT MODAL -->
    <Teleport to="body">
      <div v-if="showFormModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8">
        <div class="flex max-h-full w-full max-w-lg flex-col rounded-2xl bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">{{ formMode === 'create' ? 'Buat Loan Baru' : 'Edit Loan (Draft)' }}</h2>
            <button @click="showFormModal = false" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50"><X class="h-5 w-5" /></button>
          </div>

          <form @submit.prevent="submitForm" class="flex-1 space-y-4 overflow-y-auto px-6 py-5">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Employee</label>
              <select v-model.number="form.employee_id" :disabled="formMode === 'edit'" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none disabled:bg-slate-50">
                <option v-for="e in employees" :key="e.id" :value="e.id">{{ employeeName(e) }}</option>
              </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Principal (Rp)</label>
                <input v-model.number="form.principal" type="number" min="1" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Tenor (kali cicilan)</label>
                <input v-model.number="form.tenor" type="number" min="1" max="60" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Bunga % (opsional)</label>
                <input v-model.number="form.interest_rate" type="number" min="0" max="100" step="0.01" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Mulai Potong</label>
                <input v-model="form.first_deduction_date" type="date" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Tujuan / Keterangan</label>
              <textarea v-model="form.purpose" rows="2" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"></textarea>
            </div>

            <button type="button" @click="runPreview" :disabled="previewing || !form.principal || !form.tenor" class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-50">
              <Loader2 v-if="previewing" class="h-4 w-4 animate-spin" :stroke-width="2" />
              <Calculator v-else class="h-4 w-4" :stroke-width="1.75" />
              Preview Cicilan
            </button>

            <div v-if="previewError" class="text-xs text-red-600">{{ previewError }}</div>

            <div v-if="previewResult" class="space-y-2 rounded-xl bg-slate-50 p-3">
              <div class="flex justify-between text-sm">
                <span class="text-slate-500">Cicilan per bulan</span>
                <span class="font-medium text-slate-700">{{ formatCurrency(previewResult.installment_amount) }}</span>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-slate-500">Total dikembalikan</span>
                <span class="font-medium text-slate-700">{{ formatCurrency(previewResult.total_repayment) }}</span>
              </div>
              <p class="text-xs text-slate-400">Cicilan terakhir bisa beda tipis karena pembulatan.</p>
            </div>

            <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>
          </form>

          <div class="border-t border-slate-100 px-6 py-4">
            <button @click="submitForm" :disabled="saving" class="w-full rounded-xl bg-primary py-2.5 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50">
              {{ saving ? 'Menyimpan...' : formMode === 'create' ? 'Simpan sebagai Draft' : 'Simpan Perubahan' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- DETAIL DRAWER -->
    <Teleport to="body">
      <div v-if="showDrawer" class="fixed inset-0 z-50 flex justify-end bg-slate-900/30">
        <div class="h-full w-full max-w-md overflow-y-auto bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Detail Loan</h2>
            <button @click="closeDrawer" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50"><X class="h-5 w-5" /></button>
          </div>

          <div v-if="drawerLoading" class="p-6 text-sm text-slate-400">Memuat...</div>

          <div v-else-if="drawerTarget" class="space-y-5 px-6 py-5">
            <div class="flex items-center justify-between">
              <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="statusBadgeClass[drawerTarget.status]">
                {{ statusLabels[drawerTarget.status] }}
              </span>
              <button v-if="drawerTarget.status === 'draft'" @click="openEditForm(drawerTarget)" class="flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-primary">
                <Pencil class="h-3.5 w-3.5" :stroke-width="1.75" /> Edit
              </button>
            </div>

            <div>
              <p class="text-sm font-medium text-slate-800">{{ employeeName(drawerTarget.employee) }}</p>
              <p class="text-xs text-slate-400">{{ drawerTarget.employee.department?.name ?? '-' }}</p>
            </div>

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

            <!-- Approval steps -->
            <div v-if="drawerTarget.approval_request" class="border-t border-slate-100 pt-4">
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Approval</p>
              <div class="space-y-1.5">
                <div v-for="d in drawerTarget.approval_request.step_decisions" :key="d.id" class="flex items-center justify-between text-xs">
                  <span class="text-slate-500">{{ d.approval_step.name ?? `Step ${d.approval_step.sequence}` }}</span>
                  <span class="font-medium" :class="{ 'text-emerald-600': d.status === 'approved', 'text-red-600': d.status === 'rejected', 'text-slate-400': d.status === 'pending' }">
                    {{ d.status === 'approved' ? 'Disetujui' : d.status === 'rejected' ? 'Ditolak' : 'Menunggu' }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Installments -->
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

            <p v-if="drawerTarget.status === 'cancelled' && drawerTarget.cancel_reason" class="rounded-xl bg-slate-50 p-3 text-xs text-slate-500">
              Alasan dibatalkan: {{ drawerTarget.cancel_reason }}
            </p>

            <div v-if="actionError" class="flex items-start gap-2 rounded-xl bg-red-50 p-3 text-xs text-red-600">
              <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" :stroke-width="1.75" />
              <p>{{ actionError }}</p>
            </div>

            <!-- ACTIONS -->
            <div class="space-y-2 border-t border-slate-100 pt-4">
              <button v-if="drawerTarget.status === 'draft'" @click="submitLoan(drawerTarget)" :disabled="actionProcessing" class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary py-2.5 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50">
                <Send class="h-4 w-4" :stroke-width="1.75" /> Submit untuk Approval
              </button>

              <button v-if="drawerTarget.status === 'approved'" @click="disburseLoan(drawerTarget)" :disabled="actionProcessing" class="flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 py-2.5 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
                <Wallet class="h-4 w-4" :stroke-width="1.75" /> Cairkan (Disburse)
              </button>

              <div v-if="['draft', 'pending', 'approved'].includes(drawerTarget.status)">
                <button v-if="!showCancelForm" @click="showCancelForm = true" class="flex w-full items-center justify-center gap-2 rounded-xl border border-red-200 py-2.5 text-sm font-medium text-red-600 hover:bg-red-50">
                  <Ban class="h-4 w-4" :stroke-width="1.75" /> Batalkan Loan
                </button>
                <div v-else class="space-y-2">
                  <textarea v-model="cancelReason" rows="2" placeholder="Alasan pembatalan (wajib)" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"></textarea>
                  <div class="flex gap-2">
                    <button @click="showCancelForm = false" class="flex-1 rounded-xl border border-slate-200 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Batal</button>
                    <button @click="submitCancel(drawerTarget)" :disabled="!cancelReason.trim() || actionProcessing" class="flex-1 rounded-xl bg-red-600 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50">Konfirmasi</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>