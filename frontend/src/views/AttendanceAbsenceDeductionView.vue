<script setup lang="ts">
import { ref, reactive, onMounted, watch } from 'vue'
import { CalendarOff, Loader2, X, CheckCircle2, Wallet, RotateCcw, History } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface EmployeeOption {
  id: number
  first_name: string
  last_name: string
}

interface AbsenceRow {
  employee: { id: number; employee_number: string; name: string }
  date: string
  status: 'absent'
}

interface LeaveTypeOption {
  id: number
  name: string
  requires_balance: boolean
}

interface DeductionRow {
  id: number
  start_date: string
  total_days: string
  status: 'approved' | 'reversed' | 'pending' | 'rejected' | 'cancelled'
  source: string
  reversed_at: string | null
  employee: { id: number; first_name: string; last_name: string }
  leave_type: { id: number; name: string } | null
}

function employeeName(e: { first_name: string; last_name: string }): string {
  return `${e.first_name} ${e.last_name}`.trim()
}

function formatDateLabel(value: string): string {
  return new Date(value).toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' })
}

const activeTab = ref<'active' | 'history'>('active')

const employees = ref<EmployeeOption[]>([])
const leaveTypes = ref<LeaveTypeOption[]>([])

const absences = ref<AbsenceRow[]>([])
const deductions = ref<DeductionRow[]>([])
const loading = ref(true)
const errorMessage = ref('')

const filters = reactive({
  date_from: '',
  date_to: '',
  employee_id: null as number | null,
})

// Row key unik (employee.id + date) -- dipakai buat checkbox selection & removal setelah sukses.
function rowKey(row: AbsenceRow): string {
  return `${row.employee.id}:${row.date}`
}

const selected = ref<Set<string>>(new Set())

function toggleSelect(row: AbsenceRow) {
  const key = rowKey(row)
  if (selected.value.has(key)) selected.value.delete(key)
  else selected.value.add(key)
}

async function loadAbsences() {
  if (!filters.date_from || !filters.date_to) return
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/attendance-report/absences', {
      params: {
        date_from: filters.date_from,
        date_to: filters.date_to,
        employee_id: filters.employee_id || undefined,
      },
    })
    absences.value = response.data.data
    selected.value = new Set()
  } catch (err) {
    const status = (err as { response?: { status?: number } })?.response?.status
    errorMessage.value = status === 403
      ? 'Kamu tidak punya akses untuk melihat Absence Deduction.'
      : 'Gagal memuat data absence.'
  } finally {
    loading.value = false
  }
}

async function loadDeductions() {
  if (!filters.date_from || !filters.date_to) return
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/attendance-report/absences/deductions', {
      params: {
        date_from: filters.date_from,
        date_to: filters.date_to,
        employee_id: filters.employee_id || undefined,
      },
    })
    deductions.value = response.data.data
  } catch (err) {
    const status = (err as { response?: { status?: number } })?.response?.status
    errorMessage.value = status === 403
      ? 'Kamu tidak punya akses untuk melihat Absence Deduction.'
      : 'Gagal memuat riwayat deduction.'
  } finally {
    loading.value = false
  }
}

function loadActiveTabData() {
  if (activeTab.value === 'active') loadAbsences()
  else loadDeductions()
}

async function loadEmployees() {
  try {
    const response = await apiClient.get('/api/employees', { params: { per_page: 100 } })
    employees.value = response.data.data.data
  } catch {
    // Filter employee opsional -- gagal load gak menghalangi tabel utama.
  }
}

async function loadLeaveTypes() {
  try {
    const response = await apiClient.get('/api/leave-types')
    leaveTypes.value = response.data.data.data.filter((lt: { is_active?: boolean }) => lt.is_active !== false)
  } catch {
    // ditangani saat modal dibuka (leaveTypes kosong -> pesan di modal)
  }
}

watch(() => [filters.date_from, filters.date_to, filters.employee_id, activeTab.value], () => {
  loadActiveTabData()
})

onMounted(() => {
  const today = new Date()
  const from = new Date(today.getFullYear(), today.getMonth(), 1)
  filters.date_from = from.toISOString().slice(0, 10)
  filters.date_to = today.toISOString().slice(0, 10)

  loadEmployees()
  loadLeaveTypes()
  loadAbsences()
})

// ---------- Mark as Time-Off modal ----------
const modalTarget = ref<AbsenceRow | null>(null)
const modalLeaveTypeId = ref<number | null>(null)
const modalSubmitting = ref(false)
const modalError = ref('')
const modalSuccess = ref(false)

const modalBalanceInfo = ref<{ remaining_days: string } | null>(null)
const modalBalanceLoading = ref(false)

function openModal(row: AbsenceRow) {
  modalTarget.value = row
  modalLeaveTypeId.value = null
  modalError.value = ''
  modalSuccess.value = false
  modalBalanceInfo.value = null
}

function closeModal() {
  modalTarget.value = null
}

async function loadBalanceInfo() {
  if (!modalTarget.value || !modalLeaveTypeId.value) return
  const leaveType = leaveTypes.value.find((lt) => lt.id === modalLeaveTypeId.value)
  if (!leaveType?.requires_balance) {
    modalBalanceInfo.value = null
    return
  }

  modalBalanceLoading.value = true
  try {
    const response = await apiClient.get('/api/leave-balances', {
      params: { employee_id: modalTarget.value.employee.id, leave_type_id: modalLeaveTypeId.value },
    })
    const balance = response.data.data.data?.[0]
    modalBalanceInfo.value = balance ? { remaining_days: balance.remaining_days } : null
  } catch {
    // Opsional -- kalau role gak punya permission 'view leave balances',
    // biarin aja gak nampil, gak menghalangi submit.
    modalBalanceInfo.value = null
  } finally {
    modalBalanceLoading.value = false
  }
}

watch(modalLeaveTypeId, () => {
  loadBalanceInfo()
})

async function submitMarkAsTimeOff() {
  if (!modalTarget.value || !modalLeaveTypeId.value) return
  modalSubmitting.value = true
  modalError.value = ''
  try {
    await apiClient.post('/api/attendance-report/absences/mark-as-time-off', {
      employee_id: modalTarget.value.employee.id,
      date: modalTarget.value.date,
      leave_type_id: modalLeaveTypeId.value,
    })
    modalSuccess.value = true
    const key = rowKey(modalTarget.value)
    absences.value = absences.value.filter((r) => rowKey(r) !== key)
    selected.value.delete(key)
  } catch (err) {
    const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
    modalError.value = message || 'Gagal menandai sebagai Time-Off.'
  } finally {
    modalSubmitting.value = false
  }
}

// ---------- Reverse ("Correction/Clear") confirmation modal ----------
const reverseTarget = ref<DeductionRow | null>(null)
const reverseReason = ref('')
const reverseSubmitting = ref(false)
const reverseError = ref('')
const reverseSuccess = ref(false)

function openReverseModal(row: DeductionRow) {
  reverseTarget.value = row
  reverseReason.value = ''
  reverseError.value = ''
  reverseSuccess.value = false
}

function closeReverseModal() {
  reverseTarget.value = null
}

async function submitReverse() {
  if (!reverseTarget.value) return
  reverseSubmitting.value = true
  reverseError.value = ''
  try {
    const response = await apiClient.post(`/api/attendance-report/absences/${reverseTarget.value.id}/reverse`, {
      reason: reverseReason.value || undefined,
    })
    reverseSuccess.value = true
    // Update baris di list history jadi 'reversed' inline (bukan reload
    // penuh) -- tanggal itu otomatis akan muncul lagi di tab "Active
    // Absences" kalau HR pindah tab / reload (absence tetap computed).
    const updated = deductions.value.find((d) => d.id === response.data.data.id)
    if (updated) {
      updated.status = 'reversed'
      updated.reversed_at = response.data.data.reversed_at
    }
  } catch (err) {
    const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
    reverseError.value = message || 'Gagal melakukan reverse.'
  } finally {
    reverseSubmitting.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Absence Deduction</h1>
      <p class="mt-1 text-sm text-slate-500">Review employee yang absent tanpa attendance, tandai sebagai Time-Off kalau memang cuti, atau koreksi deduction yang sudah dibuat.</p>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 rounded-xl bg-slate-100 p-1 w-fit">
      <button
        type="button"
        @click="activeTab = 'active'"
        class="rounded-lg px-4 py-2 text-sm font-medium transition-colors"
        :class="activeTab === 'active' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
      >
        Active Absences
      </button>
      <button
        type="button"
        @click="activeTab = 'history'"
        class="inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium transition-colors"
        :class="activeTab === 'history' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
      >
        <History class="h-3.5 w-3.5" :stroke-width="1.75" />
        Time-Off / Deduction History
      </button>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-end gap-3 rounded-2xl border border-slate-100 bg-white p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-500">Dari Tanggal</label>
        <input v-model="filters.date_from" type="date" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
      </div>
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-500">Sampai Tanggal</label>
        <input v-model="filters.date_to" type="date" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
      </div>
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-500">Employee</label>
        <select v-model.number="filters.employee_id" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
          <option :value="null">Semua Employee</option>
          <option v-for="e in employees" :key="e.id" :value="e.id">{{ employeeName(e) }}</option>
        </select>
      </div>
    </div>

    <div v-if="loading" class="flex items-center gap-2 py-10 text-sm text-slate-400">
      <Loader2 class="h-4 w-4 animate-spin" :stroke-width="2" />
      Memuat data...
    </div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>

    <!-- ---------- Tab: Active Absences ---------- -->
    <template v-else-if="activeTab === 'active'">
      <div v-if="absences.length === 0" class="rounded-2xl border border-slate-100 bg-white p-10 text-center text-sm text-slate-400">
        Tidak ada absence untuk filter ini.
      </div>
      <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <table class="w-full text-left text-sm">
          <thead class="bg-slate-50 text-xs font-medium text-slate-500">
            <tr>
              <th class="w-10 px-5 py-3"></th>
              <th class="px-3 py-3">Employee</th>
              <th class="px-3 py-3">Tanggal</th>
              <th class="px-3 py-3">Status</th>
              <th class="px-5 py-3 text-right">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-for="row in absences" :key="rowKey(row)" class="transition-colors hover:bg-slate-50/60">
              <td class="px-5 py-3.5">
                <input
                  type="checkbox"
                  :checked="selected.has(rowKey(row))"
                  @change="toggleSelect(row)"
                  class="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary"
                />
              </td>
              <td class="px-3 py-3.5 text-slate-700">{{ row.employee.name }}</td>
              <td class="px-3 py-3.5 whitespace-nowrap text-slate-500">{{ formatDateLabel(row.date) }}</td>
              <td class="px-3 py-3.5">
                <span class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-medium text-red-600">Absent</span>
              </td>
              <td class="px-5 py-3.5 text-right">
                <button
                  type="button"
                  @click="openModal(row)"
                  class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-600 transition-colors hover:bg-slate-50"
                >
                  <CalendarOff class="h-3.5 w-3.5" :stroke-width="1.75" />
                  Mark as Time-Off
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <!-- ---------- Tab: Time-Off / Deduction History ---------- -->
    <template v-else>
      <div v-if="deductions.length === 0" class="rounded-2xl border border-slate-100 bg-white p-10 text-center text-sm text-slate-400">
        Belum ada Time-Off Deduction untuk filter ini.
      </div>
      <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <table class="w-full text-left text-sm">
          <thead class="bg-slate-50 text-xs font-medium text-slate-500">
            <tr>
              <th class="px-5 py-3">Employee</th>
              <th class="px-3 py-3">Tanggal</th>
              <th class="px-3 py-3">Leave Type</th>
              <th class="px-3 py-3">Total Days</th>
              <th class="px-3 py-3">Status</th>
              <th class="px-5 py-3 text-right">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-for="row in deductions" :key="row.id" class="transition-colors hover:bg-slate-50/60">
              <td class="px-5 py-3.5 text-slate-700">{{ employeeName(row.employee) }}</td>
              <td class="px-3 py-3.5 whitespace-nowrap text-slate-500">{{ formatDateLabel(row.start_date) }}</td>
              <td class="px-3 py-3.5 text-slate-500">{{ row.leave_type?.name ?? '-' }}</td>
              <td class="px-3 py-3.5 text-slate-500">{{ row.total_days }}</td>
              <td class="px-3 py-3.5">
                <span
                  class="rounded-full px-2.5 py-1 text-xs font-medium"
                  :class="row.status === 'approved' ? 'bg-primary-soft text-primary-dark' : 'bg-slate-100 text-slate-500'"
                >
                  {{ row.status === 'approved' ? 'Active' : 'Reversed' }}
                </span>
              </td>
              <td class="px-5 py-3.5 text-right">
                <button
                  v-if="row.status === 'approved'"
                  type="button"
                  @click="openReverseModal(row)"
                  class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-600 transition-colors hover:bg-slate-50"
                >
                  <RotateCcw class="h-3.5 w-3.5" :stroke-width="1.75" />
                  Reverse
                </button>
                <span v-else class="text-xs text-slate-300">-</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <!-- Mark as Time-Off modal -->
    <Teleport to="body">
      <div v-if="modalTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8" @click.self="closeModal">
        <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Mark as Time-Off</h2>
            <button @click="closeModal" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50">
              <X class="h-5 w-5" />
            </button>
          </div>

          <div class="space-y-4 px-6 py-5">
            <div v-if="modalSuccess" class="flex flex-col items-center gap-2 py-6 text-center">
              <CheckCircle2 class="h-10 w-10 text-emerald-500" :stroke-width="1.5" />
              <p class="text-sm font-medium text-slate-800">Berhasil ditandai sebagai Time-Off</p>
              <button
                type="button"
                @click="closeModal"
                class="mt-2 rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-dark"
              >
                Tutup
              </button>
            </div>

            <template v-else>
              <div class="text-sm">
                <p class="text-slate-500">Employee</p>
                <p class="font-medium text-slate-800">{{ modalTarget.employee.name }}</p>
              </div>
              <div class="text-sm">
                <p class="text-slate-500">Tanggal</p>
                <p class="font-medium text-slate-800">{{ formatDateLabel(modalTarget.date) }}</p>
              </div>

              <div>
                <label class="mb-1 block text-xs font-medium text-slate-500">Leave Type</label>
                <select
                  v-model.number="modalLeaveTypeId"
                  class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
                >
                  <option :value="null" disabled>Pilih Leave Type</option>
                  <option v-for="lt in leaveTypes" :key="lt.id" :value="lt.id">{{ lt.name }}</option>
                </select>
              </div>

              <div v-if="modalBalanceLoading" class="flex items-center gap-2 text-xs text-slate-400">
                <Loader2 class="h-3.5 w-3.5 animate-spin" :stroke-width="2" />
                Memuat saldo cuti...
              </div>
              <div v-else-if="modalBalanceInfo" class="flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-600">
                <Wallet class="h-3.5 w-3.5 text-slate-400" :stroke-width="1.75" />
                Sisa saldo: <span class="font-medium text-slate-800">{{ modalBalanceInfo.remaining_days }} hari</span>
              </div>

              <p v-if="modalError" class="text-sm text-red-600">{{ modalError }}</p>

              <div class="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  @click="closeModal"
                  class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50"
                >
                  Batal
                </button>
                <button
                  type="button"
                  :disabled="!modalLeaveTypeId || modalSubmitting"
                  @click="submitMarkAsTimeOff"
                  class="inline-flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50"
                >
                  <Loader2 v-if="modalSubmitting" class="h-3.5 w-3.5 animate-spin" :stroke-width="2" />
                  Confirm
                </button>
              </div>
            </template>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Reverse ("Correction/Clear") confirmation modal -->
    <Teleport to="body">
      <div v-if="reverseTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8" @click.self="closeReverseModal">
        <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Reverse Time-Off</h2>
            <button @click="closeReverseModal" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50">
              <X class="h-5 w-5" />
            </button>
          </div>

          <div class="space-y-4 px-6 py-5">
            <div v-if="reverseSuccess" class="flex flex-col items-center gap-2 py-6 text-center">
              <CheckCircle2 class="h-10 w-10 text-emerald-500" :stroke-width="1.5" />
              <p class="text-sm font-medium text-slate-800">Berhasil di-reverse</p>
              <p class="text-xs text-slate-400">Tanggal ini akan kembali muncul di Active Absences.</p>
              <button
                type="button"
                @click="closeReverseModal"
                class="mt-2 rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-dark"
              >
                Tutup
              </button>
            </div>

            <template v-else>
              <p class="text-sm text-slate-600">
                Reverse akan membatalkan Time-Off ini dan mengembalikan saldo cuti (kalau ada). Tanggal ini akan kembali dianggap Absent.
              </p>
              <div class="text-sm">
                <p class="text-slate-500">Employee</p>
                <p class="font-medium text-slate-800">{{ employeeName(reverseTarget.employee) }}</p>
              </div>
              <div class="text-sm">
                <p class="text-slate-500">Tanggal</p>
                <p class="font-medium text-slate-800">{{ formatDateLabel(reverseTarget.start_date) }} &middot; {{ reverseTarget.leave_type?.name ?? '-' }} ({{ reverseTarget.total_days }} hari)</p>
              </div>

              <div>
                <label class="mb-1 block text-xs font-medium text-slate-500">Alasan (opsional)</label>
                <textarea
                  v-model="reverseReason"
                  rows="2"
                  placeholder="Mis. salah tanggal, salah leave type..."
                  class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
                ></textarea>
              </div>

              <p v-if="reverseError" class="text-sm text-red-600">{{ reverseError }}</p>

              <div class="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  @click="closeReverseModal"
                  class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50"
                >
                  Batal
                </button>
                <button
                  type="button"
                  :disabled="reverseSubmitting"
                  @click="submitReverse"
                  class="inline-flex items-center gap-1.5 rounded-xl bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                >
                  <Loader2 v-if="reverseSubmitting" class="h-3.5 w-3.5 animate-spin" :stroke-width="2" />
                  Confirm Reverse
                </button>
              </div>
            </template>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>