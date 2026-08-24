<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { CalendarOff, Loader2, X, CheckCircle2, Wallet } from 'lucide-vue-next'
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

function employeeName(e: EmployeeOption): string {
  return `${e.first_name} ${e.last_name}`.trim()
}

function formatDateLabel(value: string): string {
  return new Date(value).toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' })
}

const employees = ref<EmployeeOption[]>([])
const leaveTypes = ref<LeaveTypeOption[]>([])

const absences = ref<AbsenceRow[]>([])
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

watch(() => [filters.date_from, filters.date_to, filters.employee_id], () => {
  loadAbsences()
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
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Absence Deduction</h1>
      <p class="mt-1 text-sm text-slate-500">Review employee yang absent tanpa attendance, lalu tandai sebagai Time-Off kalau memang cuti.</p>
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
      Memuat data absence...
    </div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>
    <div v-else-if="absences.length === 0" class="rounded-2xl border border-slate-100 bg-white p-10 text-center text-sm text-slate-400">
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
  </div>
</template>