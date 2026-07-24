<script setup lang="ts">
import { ref, onMounted, reactive, computed } from 'vue'
import { Check, X, Clock, Timer, AlertTriangle, Loader2, FileEdit, Search, CheckCheck } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

type RequestType = 'late' | 'overtime' | 'correction'

interface PendingDecision {
  id: number
  sequence: number
  status: string
  approval_step: {
    id: number
    name: string | null
    sequence: number
    approver_type: string
  }
  request: {
    id: number
    type: RequestType
    status: string
    detected_value: number
    working_value: number
    employee: {
      id: number
      first_name: string
      last_name: string | null
    }
    attendance: {
      id: number
      attendance_date: string
      clock_in: string | null
      clock_out: string | null
    }
  }
}

const typeLabels: Record<RequestType, string> = {
  late: 'Keterlambatan',
  overtime: 'Lembur',
  correction: 'Koreksi Attendance',
}

const typeBadgeClass: Record<RequestType, string> = {
  late: 'bg-amber-50 text-amber-600',
  overtime: 'bg-blue-50 text-blue-600',
  correction: 'bg-violet-50 text-violet-600',
}

function employeeName(employee: { first_name: string; last_name: string | null }): string {
  return [employee.first_name, employee.last_name].filter(Boolean).join(' ')
}

function employeeInitials(employee: { first_name: string; last_name: string | null }): string {
  return `${employee.first_name?.[0] ?? ''}${employee.last_name?.[0] ?? ''}`.toUpperCase()
}

function formatTime(value: string | null): string {
  if (!value) return '-'
  return new Date(value.replace(' ', 'T')).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
}

function formatDateLabel(value: string): string {
  return new Date(value).toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' })
}

const decisions = ref<PendingDecision[]>([])
const loading = ref(true)
const errorMessage = ref('')

// --- Filter & search (client-side) ---
const typeFilter = ref<RequestType | 'all'>('all')
const searchQuery = ref('')

const filteredDecisions = computed(() => {
  return decisions.value.filter((d) => {
    if (typeFilter.value !== 'all' && d.request.type !== typeFilter.value) return false
    if (searchQuery.value.trim()) {
      const q = searchQuery.value.toLowerCase()
      if (!employeeName(d.request.employee).toLowerCase().includes(q)) return false
    }
    return true
  })
})

const typeCounts = computed(() => {
  const counts: Record<RequestType, number> = { late: 0, overtime: 0, correction: 0 }
  for (const d of decisions.value) counts[d.request.type]++
  return counts
})

// --- Bulk selection ---
const selectedIds = ref<number[]>([])
const allVisibleSelected = computed(
  () => filteredDecisions.value.length > 0 && filteredDecisions.value.every((d) => selectedIds.value.includes(d.id))
)
function toggleSelectAll() {
  if (allVisibleSelected.value) {
    const ids = new Set(filteredDecisions.value.map((d) => d.id))
    selectedIds.value = selectedIds.value.filter((id) => !ids.has(id))
  } else {
    const newIds = filteredDecisions.value.map((d) => d.id)
    selectedIds.value = Array.from(new Set([...selectedIds.value, ...newIds]))
  }
}
function toggleSelectRow(id: number) {
  selectedIds.value = selectedIds.value.includes(id)
    ? selectedIds.value.filter((x) => x !== id)
    : [...selectedIds.value, id]
}
function clearSelection() {
  selectedIds.value = []
}

const bulkApproving = ref(false)

async function bulkApprove() {
  if (selectedIds.value.length === 0) return
  if (!confirm(`Setujui ${selectedIds.value.length} approval terpilih dengan nilai default (tanpa penyesuaian)?`)) return

  bulkApproving.value = true
  let successCount = 0
  const failed: string[] = []

  for (const id of selectedIds.value) {
    const decision = decisions.value.find((d) => d.id === id)
    if (!decision) continue
    try {
      await apiClient.post(`/api/attendance-approvals/${id}/decide`, {
        action: 'approve',
        adjusted_value: decision.request.working_value,
        notes: null,
      })
      successCount++
    } catch {
      failed.push(employeeName(decision.request.employee))
    }
  }

  bulkApproving.value = false
  clearSelection()
  await loadDecisions()

  if (failed.length > 0) {
    alert(`${successCount} berhasil disetujui. Gagal untuk: ${failed.join(', ')}.`)
  }
}

async function loadDecisions() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/attendance-approvals')
    decisions.value = response.data.data
  } catch {
    errorMessage.value = 'Gagal memuat daftar approval.'
  } finally {
    loading.value = false
  }
}

// ---------- DECIDE MODAL ----------
const showModal = ref(false)
const modalMode = ref<'approve' | 'reject'>('approve')
const target = ref<PendingDecision | null>(null)
const submitting = ref(false)
const modalError = ref('')

const form = reactive({
  adjusted_value: null as number | null,
  notes: '',
})

function openApprove(decision: PendingDecision) {
  modalMode.value = 'approve'
  target.value = decision
  modalError.value = ''
  form.adjusted_value = decision.request.working_value
  form.notes = ''
  showModal.value = true
}

function openReject(decision: PendingDecision) {
  modalMode.value = 'reject'
  target.value = decision
  modalError.value = ''
  form.adjusted_value = null
  form.notes = ''
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  target.value = null
}

async function submitDecision() {
  if (!target.value) return

  if (modalMode.value === 'reject' && !form.notes.trim()) {
    modalError.value = 'Alasan penolakan wajib diisi.'
    return
  }

  modalError.value = ''
  submitting.value = true

  const payload = {
    action: modalMode.value,
    adjusted_value: modalMode.value === 'approve' ? form.adjusted_value : null,
    notes: form.notes || null,
  }

  try {
    await apiClient.post(`/api/attendance-approvals/${target.value.id}/decide`, payload)
    showModal.value = false
    target.value = null
    await loadDecisions()
  } catch (err: any) {
    modalError.value = err.response?.data?.message || 'Gagal memproses keputusan.'
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  loadDecisions()
})
</script>

<template>
  <div class="space-y-5">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Approval Attendance</h1>
      <p class="mt-1 text-sm text-slate-500">Persetujuan keterlambatan dan lembur yang menunggu keputusan Anda.</p>
    </div>

    <!-- Filter bar -->
    <div class="rounded-2xl border border-slate-100 bg-white p-4">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-1.5">
          <button
            type="button"
            @click="typeFilter = 'all'"
            class="rounded-full px-3 py-1.5 text-xs font-medium transition-colors"
            :class="typeFilter === 'all' ? 'bg-primary-soft text-primary-dark' : 'text-slate-500 hover:bg-slate-50'"
          >
            Semua ({{ decisions.length }})
          </button>
          <button
            v-for="(label, type) in typeLabels"
            :key="type"
            type="button"
            @click="typeFilter = type"
            class="rounded-full px-3 py-1.5 text-xs font-medium transition-colors"
            :class="typeFilter === type ? 'bg-primary-soft text-primary-dark' : 'text-slate-500 hover:bg-slate-50'"
          >
            {{ label }} ({{ typeCounts[type] }})
          </button>
        </div>

        <div class="relative">
          <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" :stroke-width="1.75" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Cari nama karyawan"
            class="w-56 rounded-xl border border-slate-200 py-2 pl-9 pr-3 text-sm focus:border-primary focus:outline-none"
          />
        </div>
      </div>
    </div>

    <!-- Summary stat strip -->
    <div v-if="!loading && !errorMessage" class="flex flex-wrap divide-x divide-slate-100 overflow-hidden rounded-2xl border border-slate-100 bg-white">
      <div class="min-w-[110px] flex-1 px-5 py-4">
        <p class="text-xl font-semibold tracking-tight text-slate-900">{{ decisions.length }}</p>
        <p class="mt-0.5 text-xs text-slate-500">Total Menunggu</p>
      </div>
      <div v-for="(label, type) in typeLabels" :key="type" class="min-w-[130px] flex-1 px-5 py-4">
        <p class="text-xl font-semibold tracking-tight" :class="typeCounts[type] > 0 ? 'text-slate-900' : 'text-slate-300'">
          {{ typeCounts[type] }}
        </p>
        <p class="mt-0.5 text-xs text-slate-500">{{ label }}</p>
      </div>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>
    <div v-else-if="filteredDecisions.length === 0" class="rounded-xl bg-slate-50 p-6 text-center text-sm text-slate-400">
      Tidak ada approval yang cocok dengan filter ini.
    </div>

    <!-- Bulk action bar -->
    <div
      v-if="selectedIds.length > 0"
      class="flex items-center justify-between rounded-2xl border border-primary/30 bg-primary-soft px-4 py-3"
    >
      <p class="text-sm font-medium text-primary-dark">{{ selectedIds.length }} approval dipilih</p>
      <div class="flex items-center gap-2">
        <button
          type="button"
          @click="clearSelection"
          class="rounded-lg px-3 py-1.5 text-xs font-medium text-primary-dark hover:bg-primary/10"
        >
          Batal Pilih
        </button>
        <button
          type="button"
          @click="bulkApprove"
          :disabled="bulkApproving"
          class="flex items-center gap-1.5 rounded-lg bg-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-primary-dark disabled:opacity-50"
        >
          <Loader2 v-if="bulkApproving" class="h-3.5 w-3.5 animate-spin" :stroke-width="2" />
          <CheckCheck v-else class="h-3.5 w-3.5" :stroke-width="2" />
          {{ bulkApproving ? 'Memproses...' : 'Setujui Semua' }}
        </button>
      </div>
    </div>

    <!-- Table -->
    <div v-if="!loading && !errorMessage && filteredDecisions.length > 0" class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead>
            <tr class="border-b border-slate-100 bg-slate-50/60">
              <th class="w-10 px-5 py-3">
                <input type="checkbox" :checked="allVisibleSelected" @change="toggleSelectAll" class="rounded border-slate-300 text-primary focus:ring-primary" />
              </th>
              <th class="px-3 py-3 font-medium text-slate-500">Employee</th>
              <th class="px-3 py-3 font-medium text-slate-500">Tipe</th>
              <th class="px-3 py-3 font-medium text-slate-500">Tanggal</th>
              <th class="px-3 py-3 font-medium text-slate-500">Jam Kerja</th>
              <th class="px-3 py-3 font-medium text-slate-500">Nilai</th>
              <th class="px-3 py-3 font-medium text-slate-500">Step</th>
              <th class="px-5 py-3 text-right font-medium text-slate-500">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="decision in filteredDecisions"
              :key="decision.id"
              class="border-b border-slate-50 last:border-0 hover:bg-slate-50/50"
            >
              <td class="px-5 py-3.5">
                <input
                  type="checkbox"
                  :checked="selectedIds.includes(decision.id)"
                  @change="toggleSelectRow(decision.id)"
                  class="rounded border-slate-300 text-primary focus:ring-primary"
                />
              </td>
              <td class="px-3 py-3.5">
                <div class="flex items-center gap-2.5">
                  <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-soft text-xs font-semibold text-primary-dark">
                    {{ employeeInitials(decision.request.employee) }}
                  </div>
                  <p class="font-medium text-slate-800">{{ employeeName(decision.request.employee) }}</p>
                </div>
              </td>
              <td class="px-3 py-3.5">
                <span class="flex w-fit items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium" :class="typeBadgeClass[decision.request.type]">
                  <Timer v-if="decision.request.type === 'overtime'" class="h-3 w-3" :stroke-width="2" />
                  <FileEdit v-else-if="decision.request.type === 'correction'" class="h-3 w-3" :stroke-width="2" />
                  <Clock v-else class="h-3 w-3" :stroke-width="2" />
                  {{ typeLabels[decision.request.type] }}
                </span>
              </td>
              <td class="px-3 py-3.5 whitespace-nowrap text-slate-500">{{ formatDateLabel(decision.request.attendance.attendance_date) }}</td>
              <td class="px-3 py-3.5 whitespace-nowrap text-slate-500">
                {{ formatTime(decision.request.attendance.clock_in) }} - {{ formatTime(decision.request.attendance.clock_out) }}
              </td>
              <td class="px-3 py-3.5 text-slate-500">{{ decision.request.working_value }} mnt</td>
              <td class="px-3 py-3.5 text-slate-500">{{ decision.approval_step.name ?? `Step ${decision.approval_step.sequence}` }}</td>
              <td class="px-5 py-3.5">
                <div class="flex items-center justify-end gap-1.5">
                  <button
                    @click="openReject(decision)"
                    class="flex items-center gap-1 rounded-lg border border-red-200 px-2.5 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50"
                  >
                    <X class="h-3.5 w-3.5" :stroke-width="2" />
                    Tolak
                  </button>
                  <button
                    @click="openApprove(decision)"
                    class="flex items-center gap-1 rounded-lg bg-primary px-2.5 py-1.5 text-xs font-medium text-white hover:bg-primary-dark"
                  >
                    <Check class="h-3.5 w-3.5" :stroke-width="2" />
                    Setujui
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="showModal && target" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8">
        <div class="w-full max-w-md rounded-2xl bg-white shadow-xl">
          <div class="border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">
              {{ modalMode === 'approve' ? 'Setujui' : 'Tolak' }} {{ typeLabels[target.request.type] }}
            </h2>
            <p class="mt-1 text-sm text-slate-500">{{ employeeName(target.request.employee) }} · {{ target.request.attendance.attendance_date }}</p>
          </div>

          <div class="space-y-4 px-6 py-5">
            <div class="rounded-xl bg-slate-50 p-3 text-sm text-slate-600">
              Terdeteksi sistem: <span class="font-medium">{{ target.request.detected_value }} menit</span>
            </div>

            <div v-if="modalMode === 'approve'">
              <label class="mb-1 block text-sm font-medium text-slate-700">Nilai yang disetujui (menit)</label>
              <input
                v-model.number="form.adjusted_value"
                type="number"
                min="0"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
              />
              <p class="mt-1 text-xs text-slate-400">Bisa disesuaikan dari nilai terdeteksi kalau diperlukan.</p>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">
                Catatan {{ modalMode === 'reject' ? '(wajib)' : '(opsional)' }}
              </label>
              <textarea
                v-model="form.notes"
                rows="3"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
              ></textarea>
            </div>

            <div v-if="modalError" class="flex items-start gap-2 rounded-xl bg-red-50 p-3 text-xs text-red-600">
              <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" :stroke-width="1.75" />
              <p>{{ modalError }}</p>
            </div>
          </div>

          <div class="flex gap-3 border-t border-slate-100 px-6 py-4">
            <button
              @click="closeModal"
              class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50"
            >
              Batal
            </button>
            <button
              @click="submitDecision"
              :disabled="submitting"
              class="flex flex-1 items-center justify-center gap-2 rounded-xl py-2.5 text-sm font-medium text-white disabled:opacity-50"
              :class="modalMode === 'approve' ? 'bg-primary hover:bg-primary-dark' : 'bg-red-600 hover:bg-red-700'"
            >
              <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" :stroke-width="2" />
              {{ submitting ? 'Memproses...' : modalMode === 'approve' ? 'Setujui' : 'Tolak' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>