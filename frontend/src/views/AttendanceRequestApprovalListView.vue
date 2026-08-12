<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue'
import { Check, X, Clock, AlertTriangle, Loader2, FileText } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface Attachment {
  id: number
  file_name: string
  url: string | null
}

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
    attendance_request: {
      id: number
      attendance_date: string
      requested_clock_in: string | null
      requested_clock_out: string | null
      reason: string
      attachments: Attachment[]
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

function formatTime(value: string | null) {
  if (!value) return null
  return new Date(value).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
}

const decisions = ref<PendingDecision[]>([])
const loading = ref(true)
const errorMessage = ref('')

async function loadDecisions() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/attendance-request-approvals')
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

const form = reactive({ notes: '' })

function openDecision(decision: PendingDecision, mode: 'approve' | 'reject') {
  target.value = decision
  modalMode.value = mode
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

  const targetId = target.value.id

  try {
    await apiClient.post(`/api/attendance-request-approvals/${targetId}/decide`, {
      action: modalMode.value,
      notes: form.notes || null,
    })

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
      <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Approval Attendance Request</h1>
      <p class="mt-1 text-sm text-slate-500">Koreksi/laporan clock-in/out dari employee yang menunggu keputusan Anda.</p>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>
    <div v-else-if="decisions.length === 0" class="rounded-xl bg-slate-50 p-6 text-center text-sm text-slate-400">
      Tidak ada attendance request yang menunggu approval Anda.
    </div>

    <div v-else class="space-y-3">
      <div
        v-for="decision in decisions"
        :key="decision.id"
        class="rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]"
      >
        <div class="flex items-start justify-between gap-4">
          <div class="flex items-center gap-3">
            <img
              v-if="decision.request.employee.photo_url"
              :src="decision.request.employee.photo_url"
              alt=""
              class="h-10 w-10 shrink-0 rounded-full object-cover"
            />
            <div
              v-else
              class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-soft text-sm font-semibold text-primary-dark"
            >
              {{ employeeInitials(decision.request.employee) }}
            </div>
            <div>
              <p class="font-medium text-slate-800">{{ employeeName(decision.request.employee) }}</p>
              <p class="mt-0.5 text-xs text-slate-500">
                {{ formatDate(decision.request.attendance_request.attendance_date) }}
                <span v-if="decision.request.attendance_request.requested_clock_in">
                  · Clock In {{ formatTime(decision.request.attendance_request.requested_clock_in) }}
                </span>
                <span v-if="decision.request.attendance_request.requested_clock_out">
                  · Clock Out {{ formatTime(decision.request.attendance_request.requested_clock_out) }}
                </span>
              </p>
              <p class="mt-1 text-xs text-slate-400">{{ decision.request.attendance_request.reason }}</p>
              <div v-if="decision.request.attendance_request.attachments.length > 0" class="mt-2 flex flex-wrap gap-2">
                <a
                  v-for="att in decision.request.attendance_request.attachments"
                  :key="att.id"
                  :href="att.url ?? '#'"
                  target="_blank"
                  rel="noopener"
                  class="flex items-center gap-1 rounded-lg bg-slate-50 px-2 py-1 text-xs text-slate-500 hover:bg-slate-100"
                >
                  <FileText class="h-3 w-3" :stroke-width="1.75" />
                  {{ att.file_name }}
                </a>
              </div>
            </div>
          </div>

          <span class="flex shrink-0 items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-600">
            <Clock class="h-3 w-3" :stroke-width="2" />
            Menunggu {{ decision.approval_step.name ?? `Step ${decision.approval_step.sequence}` }}
          </span>
        </div>

        <div class="mt-4 flex justify-end gap-2 border-t border-slate-50 pt-4">
          <button
            @click="openDecision(decision, 'reject')"
            class="flex items-center gap-1.5 rounded-xl border border-red-200 px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50"
          >
            <X class="h-3.5 w-3.5" :stroke-width="2" />
            Tolak
          </button>
          <button
            @click="openDecision(decision, 'approve')"
            class="flex items-center gap-1.5 rounded-xl bg-primary px-3 py-2 text-sm font-medium text-white hover:bg-primary-dark"
          >
            <Check class="h-3.5 w-3.5" :stroke-width="2" />
            Setujui
          </button>
        </div>
      </div>
    </div>

    <!-- Decide modal (approve/reject dengan notes) -->
    <Teleport to="body">
      <div v-if="showModal && target" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8">
        <div class="w-full max-w-md rounded-2xl bg-white shadow-xl">
          <div class="border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">
              {{ modalMode === 'approve' ? 'Setujui' : 'Tolak' }} Attendance Request
            </h2>
            <p class="mt-1 text-sm text-slate-500">{{ employeeName(target.request.employee) }}</p>
          </div>

          <div class="space-y-4 px-6 py-5">
            <div class="rounded-xl bg-slate-50 p-3 text-sm text-slate-600">
              {{ formatDate(target.request.attendance_request.attendance_date) }}
              <span v-if="target.request.attendance_request.requested_clock_in">
                · Clock In {{ formatTime(target.request.attendance_request.requested_clock_in) }}
              </span>
              <span v-if="target.request.attendance_request.requested_clock_out">
                · Clock Out {{ formatTime(target.request.attendance_request.requested_clock_out) }}
              </span>
              <p class="mt-1 text-xs text-slate-400">{{ target.request.attendance_request.reason }}</p>
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
