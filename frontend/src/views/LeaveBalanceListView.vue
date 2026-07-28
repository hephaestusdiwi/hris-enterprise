<script setup lang="ts">
import { ref, onMounted, reactive, computed } from 'vue'
import { Filter, History, X, Loader2, ChevronDown, ChevronLeft, ChevronRight, AlertTriangle } from 'lucide-vue-next'
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

function employeeInitials(e: Employee) {
  return `${e.first_name?.[0] ?? ''}${e.last_name?.[0] ?? ''}`.toUpperCase()
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

const showFilters = ref(false)
const activeFilterCount = computed(() => [filters.employee_id, filters.leave_type_id].filter((v) => v !== null).length)

// --- Ringkasan visual (dari data yang lagi ke-load) ---
const LOW_BALANCE_THRESHOLD = 2

function totalAvailable(row: LeaveBalanceRow): number | null {
  if (row.initial_quota === null) return null
  return Number(row.initial_quota) + Number(row.carry_over_days)
}

function usagePercent(row: LeaveBalanceRow): number {
  const total = totalAvailable(row)
  if (!total || total <= 0) return 0
  return Math.min(100, Math.round((Number(row.used_days) / total) * 100))
}

function isLowBalance(row: LeaveBalanceRow): boolean {
  return row.remaining_days !== null && row.remaining_days <= LOW_BALANCE_THRESHOLD
}

const lowBalanceCount = computed(() => balances.value.filter(isLowBalance).length)
const totalUsedDays = computed(() => balances.value.reduce((sum, r) => sum + Number(r.used_days || 0), 0))
const withAdjustmentCount = computed(() => balances.value.filter((r) => r.adjustments.length > 0).length)

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
  if (page < 1 || page > meta.value.last_page || page === meta.value.current_page) return
  filters.page = page
  loadBalances()
}

const paginationItems = computed(() => {
  const total = meta.value.last_page
  const current = meta.value.current_page
  if (total <= 1) return []

  const items: (number | '...')[] = [1]
  const left = Math.max(2, current - 1)
  const right = Math.min(total - 1, current + 1)

  if (left > 2) items.push('...')
  for (let i = left; i <= right; i++) items.push(i)
  if (right < total - 1) items.push('...')
  items.push(total)

  return items
})

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
  <div class="space-y-5">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Leave Balance</h1>
      <p class="mt-1 text-sm text-slate-500">Saldo cuti employee, digenerate otomatis oleh sistem berdasarkan Leave Type.</p>
    </div>

    <!-- Filter bar -->
    <div class="rounded-2xl border border-slate-100 bg-white p-4">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <button
          type="button"
          @click="showFilters = !showFilters"
          class="flex items-center gap-1.5 text-sm font-medium text-primary-dark hover:underline"
        >
          <Filter class="h-3.5 w-3.5" :stroke-width="1.75" />
          {{ showFilters ? 'Sembunyikan filter' : 'Semua filter' }}
          <span v-if="activeFilterCount > 0" class="rounded-full bg-primary-soft px-1.5 py-0.5 text-[11px] font-semibold text-primary-dark">
            {{ activeFilterCount }}
          </span>
        </button>

        <div class="flex items-center gap-3">
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">Tahun</label>
            <input v-model.number="filters.year" type="number" class="w-24 rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
          </div>
          <button @click="applyFilters" class="mt-5 rounded-xl bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900">
            Terapkan
          </button>
        </div>
      </div>

      <Transition
        enter-active-class="transition-all duration-150 ease-out"
        enter-from-class="opacity-0 -translate-y-1"
        enter-to-class="opacity-100 translate-y-0"
      >
        <div v-if="showFilters" class="mt-4 flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4">
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
        </div>
      </Transition>
    </div>

    <!-- Summary stat strip -->
    <div v-if="!loading && !errorMessage" class="flex flex-wrap divide-x divide-slate-100 overflow-hidden rounded-2xl border border-slate-100 bg-white">
      <div class="min-w-[120px] flex-1 px-5 py-4">
        <p class="text-xl font-semibold tracking-tight text-slate-900">{{ meta.total }}</p>
        <p class="mt-0.5 text-xs text-slate-500">Total Balance</p>
      </div>
      <div class="min-w-[140px] flex-1 px-5 py-4">
        <p class="text-xl font-semibold tracking-tight" :class="lowBalanceCount > 0 ? 'text-red-600' : 'text-slate-300'">
          {{ lowBalanceCount }}
        </p>
        <p class="mt-0.5 text-xs text-slate-500">Saldo Menipis (halaman ini)</p>
      </div>
      <div class="min-w-[140px] flex-1 px-5 py-4">
        <p class="text-xl font-semibold tracking-tight text-slate-900">{{ totalUsedDays }}</p>
        <p class="mt-0.5 text-xs text-slate-500">Total Hari Terpakai</p>
      </div>
      <div class="min-w-[140px] flex-1 px-5 py-4">
        <p class="text-xl font-semibold tracking-tight" :class="withAdjustmentCount > 0 ? 'text-primary-dark' : 'text-slate-300'">
          {{ withAdjustmentCount }}
        </p>
        <p class="mt-0.5 text-xs text-slate-500">Ada Adjustment</p>
      </div>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>
    <div v-else-if="balances.length === 0" class="rounded-xl bg-slate-50 p-6 text-center text-sm text-slate-400">
      Belum ada leave balance untuk filter ini.
    </div>

    <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead>
            <tr class="border-b border-slate-100 bg-slate-50/60">
              <th class="px-5 py-3 font-medium text-slate-500">Employee</th>
              <th class="px-3 py-3 font-medium text-slate-500">Leave Type</th>
              <th class="px-3 py-3 font-medium text-slate-500">Periode</th>
              <th class="px-3 py-3 text-center font-medium text-slate-500">Kuota Awal</th>
              <th class="px-3 py-3 text-center font-medium text-slate-500">Carry Over</th>
              <th class="px-3 py-3 text-center font-medium text-slate-500">Terpakai</th>
              <th class="px-3 py-3 font-medium text-slate-500">Sisa</th>
              <th class="px-5 py-3 text-right font-medium text-slate-500">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in balances" :key="row.id" class="border-b border-slate-50 last:border-0 hover:bg-slate-50/50">
              <td class="px-5 py-3.5">
                <div class="flex items-center gap-2.5">
                  <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-soft text-xs font-semibold text-primary-dark">
                    {{ employeeInitials(row.employee) }}
                  </div>
                  <div>
                    <div class="flex items-center gap-1.5">
                      <p class="font-medium text-slate-800">{{ employeeName(row.employee) }}</p>
                      <AlertTriangle v-if="isLowBalance(row)" class="h-3.5 w-3.5 text-red-500" :stroke-width="2" />
                    </div>
                  </div>
                </div>
              </td>
              <td class="px-3 py-3.5">
                <div class="flex items-center gap-2">
                  <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: row.leave_type.color ?? '#94A3B8' }"></span>
                  {{ row.leave_type.name }}
                </div>
              </td>
              <td class="px-3 py-3.5 text-xs text-slate-500">{{ formatDate(row.period_start) }} - {{ formatDate(row.period_end) }}</td>
              <td class="px-3 py-3.5 text-center text-slate-600">
                {{ row.initial_quota !== null ? row.initial_quota : 'Unlimited' }}
              </td>
              <td class="px-3 py-3.5 text-center text-slate-600">
                {{ row.carry_over_days }}
                <span v-if="row.carry_over_expiry_date" class="block text-xs text-slate-400">exp. {{ formatDate(row.carry_over_expiry_date) }}</span>
              </td>
              <td class="px-3 py-3.5 text-center text-slate-600">{{ row.used_days }}</td>
              <td class="px-3 py-3.5">
                <div v-if="row.initial_quota !== null" class="flex items-center gap-2">
                  <div class="h-1.5 w-16 overflow-hidden rounded-full bg-slate-100">
                    <div
                      class="h-full rounded-full transition-all"
                      :class="isLowBalance(row) ? 'bg-red-400' : 'bg-primary'"
                      :style="{ width: `${usagePercent(row)}%` }"
                    ></div>
                  </div>
                  <span
                    class="text-xs font-medium"
                    :class="row.remaining_days !== null && row.remaining_days > 0 ? (isLowBalance(row) ? 'text-red-600' : 'text-primary-dark') : 'text-red-600'"
                  >
                    {{ row.remaining_days ?? '-' }}
                  </span>
                </div>
                <span v-else class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-500">Unlimited</span>
              </td>
              <td class="px-5 py-3.5">
                <div class="flex items-center justify-end gap-2">
                  <span v-if="row.adjustments.length > 0" class="flex items-center gap-1 text-xs text-slate-400" :title="`${row.adjustments.length} adjustment`">
                    <History class="h-3.5 w-3.5" :stroke-width="1.75" />
                    {{ row.adjustments.length }}
                  </span>
                  <button
                    @click="openAdjustment(row)"
                    class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 transition-colors hover:border-primary/40 hover:text-primary-dark"
                  >
                    Adjustment
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="meta.last_page > 1" class="flex items-center justify-between text-sm text-slate-500">
      <p>Total {{ meta.total }} balance</p>
      <div class="flex items-center gap-1">
        <button
          type="button"
          @click="goToPage(meta.current_page - 1)"
          :disabled="meta.current_page === 1"
          class="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 disabled:opacity-30 disabled:hover:bg-transparent"
        >
          <ChevronLeft class="h-4 w-4" :stroke-width="2" />
        </button>
        <template v-for="(item, i) in paginationItems" :key="i">
          <span v-if="item === '...'" class="px-2 text-xs text-slate-300">...</span>
          <button
            v-else
            type="button"
            @click="goToPage(item)"
            class="min-w-[32px] rounded-lg px-2.5 py-1.5 text-xs font-medium transition-colors"
            :class="item === meta.current_page ? 'bg-primary text-white' : 'text-slate-500 hover:bg-slate-100'"
          >
            {{ item }}
          </button>
        </template>
        <button
          type="button"
          @click="goToPage(meta.current_page + 1)"
          :disabled="meta.current_page === meta.last_page"
          class="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 disabled:opacity-30 disabled:hover:bg-transparent"
        >
          <ChevronRight class="h-4 w-4" :stroke-width="2" />
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