<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue'
import { Filter, Plus, History, X, Loader2 } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface Ref { id: number; name: string }

interface Employee {
  id: number
  first_name: string
  last_name: string | null
}

interface LeaveType {
  id: number
  name: string
  color: string | null
}

interface Adjustment {
  id: number
  adjustment_days: string
  reason: string
  created_by: { id: number; name: string } | null
  created_at: string
}

interface LeaveBalanceRow {
  id: number
  period_start: string
  period_end: string
  eligible_from: string
  initial_quota: string | null
  carry_over_days: string
  carry_over_expiry_date: string | null
  used_days: string
  remaining_days: number | null
  employee: Employee
  leave_type: LeaveType
  adjustments: Adjustment[]
}

function employeeName(e: Employee) {
  return [e.first_name, e.last_name].filter(Boolean).join(' ')
}

function formatDate(value: string | null) {
  if (!value) return '-'
  return new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

const balances = ref<LeaveBalanceRow[]>([])
const employees = ref<Employee[]>([])
const leaveTypes = ref<LeaveType[]>([])
const loading = ref(true)
const errorMessage = ref('')
const meta = ref({ current_page: 1, last_page: 1, total: 0 })

const currentYear = new Date().getFullYear()
const filters = reactive({
  employee_id: null as number | null,
  leave_type_id: null as number | null,
  year: currentYear,
  page: 1,
})

async function loadBalances() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/leave-balances', {
      params: {
        employee_id: filters.employee_id || undefined,
        leave_type_id: filters.leave_type_id || undefined,
        year: filters.year || undefined,
        page: filters.page,
      },
    })
    balances.value = response.data.data.data
    meta.value = {
      current_page: response.data.data.current_page,
      last_page: response.data.data.last_page,
      total: response.data.data.total,
    }
  } catch {
    errorMessage.value = 'Gagal memuat daftar leave balance.'
  } finally {
    loading.value = false
  }
}

async function loadReferenceData() {
  const [employeeRes, leaveTypeRes] = await Promise.all([
    apiClient.get('/api/employees', { params: { per_page: 100 } }),
    apiClient.get('/api/leave-types'),
  ])
  employees.value = employeeRes.data.data.data
  leaveTypes.value = leaveTypeRes.data.data.data
}

function applyFilters() {
  filters.page = 1
  loadBalances()
}

function goToPage(page: number) {
  filters.page = page
  loadBalances()
}

// ---------- ADJUSTMENT MODAL ----------
const showModal = ref(false)
const target = ref<LeaveBalanceRow | null>(null)
const saving = ref(false)
const modalError = ref('')

const form = reactive({
  adjustment_days: null as number | null,
  reason: '',
})

function openAdjustment(row: LeaveBalanceRow) {
  target.value = row
  modalError.value = ''
  form.adjustment_days = null
  form.reason = ''
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  target.value = null
}

async function submitAdjustment() {
  if (!target.value) return

  if (!form.adjustment_days) {
    modalError.value = 'Isi jumlah hari penyesuaian (boleh negatif untuk mengurangi).'
    return
  }
  if (!form.reason.trim()) {
    modalError.value = 'Alasan wajib diisi.'
    return
  }

  modalError.value = ''
  saving.value = true

  try {
    await apiClient.post(`/api/leave-balances/${target.value.id}/adjustments`, {
      adjustment_days: form.adjustment_days,
      reason: form.reason,
    })
    showModal.value = false
    target.value = null
    await loadBalances()
  } catch (err: any) {
    modalError.value = err.response?.data?.message || 'Gagal menyimpan adjustment.'
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  loadBalances()
  loadReferenceData()
})
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Leave Balance</h1>
      <p class="mt-1 text-sm text-slate-500">Saldo cuti employee, digenerate otomatis oleh sistem berdasarkan Leave Type.</p>
    </div>

    <div class="flex flex-wrap items-end gap-3 rounded-2xl border border-slate-100 bg-white p-4">
      <div class="flex items-center gap-1.5 text-xs font-medium text-slate-400">
        <Filter class="h-3.5 w-3.5" :stroke-width="1.75" />
        Filter
      </div>
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-500">Employee</label>
        <select v-model="filters.employee_id" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
          <option :value="null">Semua Employee</option>
          <option v-for="e in employees" :key="e.id" :value="e.id">{{ employeeName(e) }}</option>
        </select>
      </div>
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-500">Leave Type</label>
        <select v-model="filters.leave_type_id" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
          <option :value="null">Semua Leave Type</option>
          <option v-for="lt in leaveTypes" :key="lt.id" :value="lt.id">{{ lt.name }}</option>
        </select>
      </div>
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-500">Tahun</label>
        <input v-model.number="filters.year" type="number" class="w-28 rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
      </div>
      <button @click="applyFilters" class="rounded-xl bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900">
        Terapkan
      </button>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>
    <div v-else-if="balances.length === 0" class="rounded-xl bg-slate-50 p-6 text-center text-sm text-slate-400">
      Belum ada leave balance untuk filter ini.
    </div>

    <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <table class="w-full text-left text-sm">
        <thead>
          <tr class="border-b border-slate-100 bg-slate-50/60">
            <th class="px-5 py-3 font-medium text-slate-500">Employee</th>
            <th class="px-5 py-3 font-medium text-slate-500">Leave Type</th>
            <th class="px-5 py-3 font-medium text-slate-500">Periode</th>
            <th class="px-5 py-3 text-center font-medium text-slate-500">Kuota Awal</th>
            <th class="px-5 py-3 text-center font-medium text-slate-500">Carry Over</th>
            <th class="px-5 py-3 text-center font-medium text-slate-500">Terpakai</th>
            <th class="px-5 py-3 text-center font-medium text-slate-500">Sisa</th>
            <th class="px-5 py-3 text-right font-medium text-slate-500">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in balances" :key="row.id" class="border-b border-slate-50 last:border-0 hover:bg-slate-50/50">
            <td class="px-5 py-3.5 font-medium text-slate-800">{{ employeeName(row.employee) }}</td>
            <td class="px-5 py-3.5">
              <div class="flex items-center gap-2">
                <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: row.leave_type.color ?? '#94A3B8' }"></span>
                {{ row.leave_type.name }}
              </div>
            </td>
            <td class="px-5 py-3.5 text-xs text-slate-500">{{ formatDate(row.period_start) }} - {{ formatDate(row.period_end) }}</td>
            <td class="px-5 py-3.5 text-center text-slate-600">
              {{ row.initial_quota !== null ? row.initial_quota : 'Unlimited' }}
            </td>
            <td class="px-5 py-3.5 text-center text-slate-600">
              {{ row.carry_over_days }}
              <span v-if="row.carry_over_expiry_date" class="block text-xs text-slate-400">exp. {{ formatDate(row.carry_over_expiry_date) }}</span>
            </td>
            <td class="px-5 py-3.5 text-center text-slate-600">{{ row.used_days }}</td>
            <td class="px-5 py-3.5 text-center">
              <span
                v-if="row.remaining_days !== null"
                class="rounded-full px-2.5 py-1 text-xs font-medium"
                :class="row.remaining_days > 0 ? 'bg-primary-soft text-primary-dark' : 'bg-red-50 text-red-600'"
              >
                {{ row.remaining_days }}
              </span>
              <span v-else class="text-slate-400">-</span>
            </td>
            <td class="px-5 py-3.5">
              <div class="flex items-center justify-end gap-1">
                <span v-if="row.adjustments.length > 0" class="flex items-center gap-1 text-xs text-slate-400" :title="`${row.adjustments.length} adjustment`">
                  <History class="h-3.5 w-3.5" :stroke-width="1.75" />
                  {{ row.adjustments.length }}
                </span>
                <button
                  @click="openAdjustment(row)"
                  class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50"
                >
                  Adjustment
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="meta.last_page > 1" class="flex items-center justify-between text-sm text-slate-500">
      <p>Total {{ meta.total }} balance</p>
      <div class="flex gap-1">
        <button
          v-for="page in meta.last_page"
          :key="page"
          @click="goToPage(page)"
          class="rounded-lg px-3 py-1.5 text-xs font-medium"
          :class="page === meta.current_page ? 'bg-primary text-white' : 'text-slate-500 hover:bg-slate-100'"
        >
          {{ page }}
        </button>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="showModal && target" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8">
        <div class="w-full max-w-md rounded-2xl bg-white shadow-xl">
          <div class="border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Adjustment Saldo</h2>
            <p class="mt-1 text-sm text-slate-500">
              {{ employeeName(target.employee) }} · {{ target.leave_type.name }}
            </p>
          </div>

          <div class="space-y-4 px-6 py-5">
            <div class="rounded-xl bg-slate-50 p-3 text-sm text-slate-600">
              Sisa saldo saat ini: <span class="font-medium">{{ target.remaining_days ?? 'Unlimited' }}</span>
            </div>

            <div v-if="target.adjustments.length > 0" class="space-y-1.5">
              <p class="text-xs font-medium uppercase tracking-wider text-slate-400">Histori Adjustment</p>
              <div v-for="adj in target.adjustments" :key="adj.id" class="rounded-lg border border-slate-100 px-3 py-2 text-xs">
                <div class="flex items-center justify-between">
                  <span :class="Number(adj.adjustment_days) >= 0 ? 'text-primary-dark' : 'text-red-600'" class="font-medium">
                    {{ Number(adj.adjustment_days) >= 0 ? '+' : '' }}{{ adj.adjustment_days }} hari
                  </span>
                  <span class="text-slate-400">{{ formatDate(adj.created_at) }}</span>
                </div>
                <p class="mt-0.5 text-slate-500">{{ adj.reason }}</p>
                <p v-if="adj.created_by" class="mt-0.5 text-slate-400">oleh {{ adj.created_by.name }}</p>
              </div>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Jumlah Hari (boleh negatif)</label>
              <input
                v-model.number="form.adjustment_days"
                type="number"
                step="0.5"
                placeholder="mis. 2 atau -1.5"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
              />
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Alasan</label>
              <textarea
                v-model="form.reason"
                rows="2"
                placeholder="mis. Kompensasi lembur hari libur"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
              ></textarea>
            </div>

            <p v-if="modalError" class="text-sm text-red-600">{{ modalError }}</p>
          </div>

          <div class="flex gap-3 border-t border-slate-100 px-6 py-4">
            <button @click="closeModal" class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50">
              Batal
            </button>
            <button
              @click="submitAdjustment"
              :disabled="saving"
              class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-primary py-2.5 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50"
            >
              <Loader2 v-if="saving" class="h-4 w-4 animate-spin" :stroke-width="2" />
              {{ saving ? 'Menyimpan...' : 'Simpan' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>