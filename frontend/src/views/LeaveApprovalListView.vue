<script setup lang="ts">
import { ref, onMounted, reactive, computed } from 'vue'
import { Check, X, Palmtree, AlertTriangle, Loader2, Search, ChevronDown, Eye, CheckCheck, Clock } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface PendingDecision {
  id: number
  sequence: number
  approval_step: { id: number; name: string | null; sequence: number }
  request: {
    id: number
    status: string
    employee: {
        id: number
        first_name: string
        last_name: string | null
        photo_url: string | null
    }
    leave_request: {
      id: number
      start_date: string
      end_date: string
      total_days: string
      reason: string
      is_half_day: boolean
      leave_type: { name: string; color: string | null }
    }
  }
}

function employeeName(e: { first_name: string; last_name: string | null }) {
  return [e.first_name, e.last_name].filter(Boolean).join(' ')
}

function employeeInitials(e: { first_name: string; last_name: string | null }) {
  return `${e.first_name?.[0] ?? ''}${e.last_name?.[0] ?? ''}`.toUpperCase()
}

function formatDate(value: string) {
  return new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

function formatDateRange(start: string, end: string) {
  return start === end ? formatDate(start) : `${formatDate(start)} - ${formatDate(end)}`
}

function leaveTypeBadgeStyle(color: string | null) {
  return {
    backgroundColor: (color ?? '#94A3B8') + '20',
    color: color ?? '#64748B',
  }
}

const decisions = ref<PendingDecision[]>([])
const loading = ref(true)
const errorMessage = ref('')

// --- Search & filter (client-side) ---
const searchQuery = ref('')
const leaveTypeFilter = ref<string>('all')

const leaveTypeOptions = computed(() => {
  const names = new Set(decisions.value.map((d) => d.request.leave_request.leave_type.name))
  return Array.from(names)
})

const leaveTypeCounts = computed(() => {
  const counts: Record<string, number> = {}
  for (const d of decisions.value) {
    const name = d.request.leave_request.leave_type.name
    counts[name] = (counts[name] ?? 0) + 1
  }
  return counts
})

const filteredDecisions = computed(() => {
  return decisions.value.filter((d) => {
    if (leaveTypeFilter.value !== 'all' && d.request.leave_request.leave_type.name !== leaveTypeFilter.value) return false
    if (searchQuery.value.trim()) {
      const q = searchQuery.value.toLowerCase()
      if (!employeeName(d.request.employee).toLowerCase().includes(q)) return false
    }
    return true
  })
})

const totalDaysRequested = computed(() =>
  decisions.value.reduce((sum, d) => sum + Number(d.request.leave_request.total_days || 0), 0),
)

// --- Bulk selection ---
const selectedIds = ref<number[]>([])
const allVisibleSelected = computed(
  () => filteredDecisions.value.length > 0 && filteredDecisions.value.every((d) => selectedIds.value.includes(d.id)),
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
function toggleSelectRow(id: number, event: Event) {
  event.stopPropagation()
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
  if (!confirm(`Setujui ${selectedIds.value.length} leave request terpilih tanpa catatan tambahan?`)) return

  bulkApproving.value = true
  let successCount = 0
  const failed: string[] = []

  for (const id of selectedIds.value) {
    const decision = decisions.value.find((d) => d.id === id)
    if (!decision) continue
    try {
      await apiClient.post(`/api/leave-approvals/${id}/decide`, { action: 'approve', notes: null })
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

// --- Actions dropdown (teleported) ---
const openActionsDecision = ref<PendingDecision | null>(null)
const actionsMenuStyle = ref({ top: '0px', left: '0px' })

function toggleActions(decision: PendingDecision, event: Event) {
  event.stopPropagation()
  if (openActionsDecision.value?.id === decision.id) {
    openActionsDecision.value = null
    return
  }
  const rect = (event.currentTarget as HTMLElement).getBoundingClientRect()
  actionsMenuStyle.value = {
    top: `${rect.bottom + window.scrollY + 4}px`,
    left: `${rect.right + window.scrollX - 176}px`,
  }
  openActionsDecision.value = decision
}
function closeActions() {
  openActionsDecision.value = null
}
window.addEventListener('click', closeActions)

async function loadDecisions() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/leave-approvals')
    decisions.value = response.data.data
  } catch {
    errorMessage.value = 'Gagal memuat daftar approval.'
  } finally {
    loading.value = false
  }
}

// ---------- DETAIL DRAWER ----------
const showDrawer = ref(false)
const drawerTarget = ref<PendingDecision | null>(null)

function openDrawer(decision: PendingDecision) {
  drawerTarget.value = decision
  showDrawer.value = true
}
function closeDrawer() {
  showDrawer.value = false
  drawerTarget.value = null
}

// ---------- DECIDE MODAL ----------
const showModal = ref(false)
const modalMode = ref<'approve' | 'reject'>('approve')
const target = ref<PendingDecision | null>(null)
const submitting = ref(false)
const modalError = ref('')

const form = reactive({ notes: '' })

function openDecision(decision: PendingDecision, mode: 'approve' | 'reject') {
  modalMode.value = mode
  target.value = decision
  modalError.value = ''
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

  // Simpan id sebelum target di-reset
  const targetId = target.value.id

  try {
    await apiClient.post(`/api/leave-approvals/${targetId}/decide`, {
      action: modalMode.value,
      notes: form.notes || null,
    })

    showModal.value = false

    if (drawerTarget.value?.id === targetId) {
      closeDrawer()
    }

    target.value = null

    await loadDecisions()
  } catch (err: any) {
    modalError.value =
      err.response?.data?.message || 'Gagal memproses keputusan.'
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
      <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Approval Leave Request</h1>
      <p class="mt-1 text-sm text-slate-500">Pengajuan cuti yang menunggu keputusan Anda.</p>
    </div>

    <!-- Filter bar -->
    <div class="rounded-2xl border border-slate-100 bg-white p-4">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-1.5">
          <button
            type="button"
            @click="leaveTypeFilter = 'all'"
            class="rounded-full px-3 py-1.5 text-xs font-medium transition-colors"
            :class="leaveTypeFilter === 'all' ? 'bg-primary-soft text-primary-dark' : 'text-slate-500 hover:bg-slate-50'"
          >
            Semua ({{ decisions.length }})
          </button>
          <button
            v-for="name in leaveTypeOptions"
            :key="name"
            type="button"
            @click="leaveTypeFilter = name"
            class="rounded-full px-3 py-1.5 text-xs font-medium transition-colors"
            :class="leaveTypeFilter === name ? 'bg-primary-soft text-primary-dark' : 'text-slate-500 hover:bg-slate-50'"
          >
            {{ name }} ({{ leaveTypeCounts[name] }})
          </button>
        </div>

        <div class="relative">
          <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" :stroke-width="1.75" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Cari nama employee"
            class="w-56 rounded-xl border border-slate-200 py-2 pl-9 pr-3 text-sm focus:border-primary focus:outline-none"
          />
        </div>
      </div>
    </div>

    <!-- Summary stat strip -->
    <div v-if="!loading && !errorMessage" class="flex flex-wrap divide-x divide-slate-100 overflow-hidden rounded-2xl border border-slate-100 bg-white">
      <div class="min-w-[120px] flex-1 px-5 py-4">
        <p class="text-xl font-semibold tracking-tight text-slate-900">{{ decisions.length }}</p>
        <p class="mt-0.5 text-xs text-slate-500">Total Menunggu</p>
      </div>
      <div class="min-w-[120px] flex-1 px-5 py-4">
        <p class="text-xl font-semibold tracking-tight text-slate-900">{{ totalDaysRequested }}</p>
        <p class="mt-0.5 text-xs text-slate-500">Total Hari Diajukan</p>
      </div>
      <div v-for="name in leaveTypeOptions" :key="name" class="min-w-[130px] flex-1 px-5 py-4">
        <p class="text-xl font-semibold tracking-tight text-slate-900">{{ leaveTypeCounts[name] }}</p>
        <p class="mt-0.5 truncate text-xs text-slate-500">{{ name }}</p>
      </div>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>
    <div v-else-if="filteredDecisions.length === 0" class="rounded-xl bg-slate-50 p-6 text-center text-sm text-slate-400">
      Tidak ada leave request yang cocok dengan filter ini.
    </div>

    <!-- Bulk action bar -->
    <div
      v-if="selectedIds.length > 0"
      class="flex items-center justify-between rounded-2xl border border-primary/30 bg-primary-soft px-4 py-3"
    >
      <p class="text-sm font-medium text-primary-dark">{{ selectedIds.length }} request dipilih</p>
      <div class="flex items-center gap-2">
        <button type="button" @click="clearSelection" class="rounded-lg px-3 py-1.5 text-xs font-medium text-primary-dark hover:bg-primary/10">
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
              <th class="px-3 py-3 font-medium text-slate-500">Policy</th>
              <th class="px-3 py-3 font-medium text-slate-500">Time Off Date</th>
              <th class="px-3 py-3 font-medium text-slate-500">Reason</th>
              <th class="px-3 py-3 font-medium text-slate-500">Status</th>
              <th class="px-5 py-3 text-right font-medium text-slate-500">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="decision in filteredDecisions"
              :key="decision.id"
              @click="openDrawer(decision)"
              class="cursor-pointer border-b border-slate-50 last:border-0 hover:bg-slate-50/50"
            >
              <td class="px-5 py-3.5" @click.stop>
                <input
                  type="checkbox"
                  :checked="selectedIds.includes(decision.id)"
                  @change="toggleSelectRow(decision.id, $event)"
                  class="rounded border-slate-300 text-primary focus:ring-primary"
                />
              </td>
              <td class="px-3 py-3.5">
                <div class="flex items-center gap-2.5">
                  <img
                      v-if="decision.request.employee.photo_url"
                      :src="decision.request.employee.photo_url"
                      alt=""
                      class="h-8 w-8 shrink-0 rounded-full object-cover"
                  />

                  <div
                      v-else
                      class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-soft text-xs font-semibold text-primary-dark"
                  >
                      {{ employeeInitials(decision.request.employee) }}
                  </div>
                  <p class="font-medium text-slate-800">{{ employeeName(decision.request.employee) }}</p>
                </div>
              </td>
              <td class="px-3 py-3.5">
                <span class="rounded-full px-2.5 py-1 text-xs font-medium" :style="leaveTypeBadgeStyle(decision.request.leave_request.leave_type.color)">
                  {{ decision.request.leave_request.leave_type.name }}
                </span>
              </td>
              <td class="px-3 py-3.5 whitespace-nowrap text-slate-500">
                {{ formatDateRange(decision.request.leave_request.start_date, decision.request.leave_request.end_date) }}
                <span class="block text-xs text-slate-400">
                  {{ decision.request.leave_request.total_days }} hari{{ decision.request.leave_request.is_half_day ? ' · Setengah hari' : '' }}
                </span>
              </td>
              <td class="max-w-[200px] truncate px-3 py-3.5 text-slate-500">{{ decision.request.leave_request.reason }}</td>
              <td class="px-3 py-3.5">
                <span class="flex w-fit items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-600">
                  <Clock class="h-3 w-3" :stroke-width="2" />
                  Menunggu {{ decision.approval_step.name ?? `Step ${decision.approval_step.sequence}` }}
                </span>
              </td>
              <td class="px-5 py-3.5 text-right" @click.stop>
                <button
                  type="button"
                  @click="toggleActions(decision, $event)"
                  class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-600 transition-colors hover:bg-slate-50"
                  :class="openActionsDecision?.id === decision.id ? 'border-primary/40 text-primary-dark' : ''"
                >
                  Actions
                  <ChevronDown class="h-3.5 w-3.5" :stroke-width="2" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Actions dropdown, teleported -->
    <Teleport to="body">
      <div
        v-if="openActionsDecision"
        @click.stop
        class="fixed z-50 w-44 overflow-hidden rounded-xl border border-slate-100 bg-white py-1 shadow-lg"
        :style="actionsMenuStyle"
      >
        <button
          @click="openDrawer(openActionsDecision!); closeActions()"
          class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-600 hover:bg-slate-50"
        >
          <Eye class="h-3.5 w-3.5" :stroke-width="1.75" />
          Lihat Detail
        </button>
        <button
          @click="openDecision(openActionsDecision!, 'approve'); closeActions()"
          class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-600 hover:bg-slate-50"
        >
          <Check class="h-3.5 w-3.5" :stroke-width="1.75" />
          Setujui
        </button>
        <button
          @click="openDecision(openActionsDecision!, 'reject'); closeActions()"
          class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-red-500 hover:bg-red-50"
        >
          <X class="h-3.5 w-3.5" :stroke-width="1.75" />
          Tolak
        </button>
      </div>
    </Teleport>

    <!-- Detail drawer -->
    <Teleport to="body">
      <div v-if="showDrawer && drawerTarget" class="fixed inset-0 z-40">
        <div class="absolute inset-0 bg-slate-900/30" @click="closeDrawer"></div>

        <Transition
          enter-active-class="transition-transform duration-200 ease-out"
          enter-from-class="translate-x-full"
          enter-to-class="translate-x-0"
          appear
        >
          <aside class="absolute right-0 top-0 flex h-full w-full max-w-md flex-col bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
              <h2 class="text-lg font-semibold text-slate-900">Time Off Request</h2>
              <button @click="closeDrawer" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50">
                <X class="h-5 w-5" />
              </button>
            </div>

            <div class="flex-1 space-y-5 overflow-y-auto px-6 py-5">
              <!-- Employee header -->
              <div class="flex items-center gap-3">
                <img
                    v-if="drawerTarget.request.employee.photo_url"
                    :src="drawerTarget.request.employee.photo_url"
                    alt=""
                    class="h-12 w-12 rounded-full object-cover"
                />

                <div
                    v-else
                    class="flex h-12 w-12 items-center justify-center rounded-full bg-primary-soft text-sm font-semibold text-primary-dark"
                >
                    {{ employeeInitials(drawerTarget.request.employee) }}
                </div>
                <div>
                  <p class="font-semibold text-slate-800">{{ employeeName(drawerTarget.request.employee) }}</p>
                  <div class="mt-1 flex items-center gap-1.5">
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium" :style="leaveTypeBadgeStyle(drawerTarget.request.leave_request.leave_type.color)">
                      {{ drawerTarget.request.leave_request.leave_type.name }}
                    </span>
                    <span class="flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-600">
                      <Clock class="h-3 w-3" :stroke-width="2" />
                      Awaiting Approval
                    </span>
                  </div>
                </div>
              </div>

              <!-- Detail fields -->
              <dl class="space-y-3 border-t border-slate-100 pt-4 text-sm">
                <div class="flex justify-between gap-4">
                  <dt class="text-slate-400">Request ID</dt>
                  <dd class="text-right font-medium text-slate-700">#{{ drawerTarget.request.id }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                  <dt class="text-slate-400">Time Off Policy</dt>
                  <dd class="text-right font-medium text-slate-700">{{ drawerTarget.request.leave_request.leave_type.name }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                  <dt class="shrink-0 text-slate-400">Time Off Date</dt>
                  <dd class="text-right font-medium text-slate-700">
                    {{ formatDateRange(drawerTarget.request.leave_request.start_date, drawerTarget.request.leave_request.end_date) }}
                    <span class="block text-xs font-normal text-slate-400">
                      {{ drawerTarget.request.leave_request.total_days }} hari{{ drawerTarget.request.leave_request.is_half_day ? ' · Setengah hari' : '' }}
                    </span>
                  </dd>
                </div>
                <div class="flex justify-between gap-4">
                  <dt class="shrink-0 text-slate-400">Reason</dt>
                  <dd class="text-right font-medium text-slate-700">{{ drawerTarget.request.leave_request.reason }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                  <dt class="shrink-0 text-slate-400">Request Status</dt>
                  <dd class="text-right font-medium text-slate-700">
                    Menunggu persetujuan dari {{ drawerTarget.approval_step.name ?? `Step ${drawerTarget.approval_step.sequence}` }}
                  </dd>
                </div>
              </dl>
            </div>

            <div class="flex gap-3 border-t border-slate-100 px-6 py-4">
              <button
                @click="openDecision(drawerTarget, 'reject')"
                class="flex-1 rounded-xl border border-red-200 py-2.5 text-sm font-medium text-red-600 hover:bg-red-50"
              >
                Reject
              </button>
              <button
                @click="openDecision(drawerTarget, 'approve')"
                class="flex-1 rounded-xl bg-primary py-2.5 text-sm font-medium text-white hover:bg-primary-dark"
              >
                Approve
              </button>
            </div>
          </aside>
        </Transition>
      </div>
    </Teleport>

    <!-- Decide modal (approve/reject dengan notes) -->
    <Teleport to="body">
      <div v-if="showModal && target" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8">
        <div class="w-full max-w-md rounded-2xl bg-white shadow-xl">
          <div class="border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">
              {{ modalMode === 'approve' ? 'Setujui' : 'Tolak' }} Leave Request
            </h2>
            <p class="mt-1 text-sm text-slate-500">{{ employeeName(target.request.employee) }}</p>
          </div>

          <div class="space-y-4 px-6 py-5">
            <div class="rounded-xl bg-slate-50 p-3 text-sm text-slate-600">
              {{ target.request.leave_request.leave_type.name }} · {{ target.request.leave_request.total_days }} hari
              <p class="mt-1 text-xs text-slate-400">{{ target.request.leave_request.reason }}</p>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">
                Catatan {{ modalMode === 'reject' ? '(wajib)' : '(opsional)' }}
              </label>
              <textarea v-model="form.notes" rows="3" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"></textarea>
            </div>

            <div v-if="modalError" class="flex items-start gap-2 rounded-xl bg-red-50 p-3 text-xs text-red-600">
              <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" :stroke-width="1.75" />
              <p>{{ modalError }}</p>
            </div>
          </div>

          <div class="flex gap-3 border-t border-slate-100 px-6 py-4">
            <button @click="closeModal" class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50">
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