<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue'
import { Plus, X, Loader2, Clock3, Ban, CheckCircle2 } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

type OvertimeRequestStatus = 'pending' | 'approved' | 'rejected' | 'cancelled' | 'claimed'

interface OvertimeRequestRow {
  id: number
  attendance_date: string
  planned_minutes: number
  actual_overtime_minutes: number | null
  reason: string
  status: OvertimeRequestStatus
  requested_at: string
  decided_at: string | null
  claimed_at: string | null
}

const statusLabels: Record<OvertimeRequestStatus, string> = {
  pending: 'Menunggu Persetujuan',
  approved: 'Disetujui',
  rejected: 'Ditolak',
  cancelled: 'Dibatalkan',
  claimed: 'Sudah Di-claim',
}

const statusBadgeClass: Record<OvertimeRequestStatus, string> = {
  pending: 'bg-amber-50 text-amber-600',
  approved: 'bg-primary-soft text-primary-dark',
  rejected: 'bg-red-50 text-red-600',
  cancelled: 'bg-slate-100 text-slate-500',
  claimed: 'bg-emerald-50 text-emerald-600',
}

function formatDate(value: string) {
  return new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

function formatMinutes(minutes: number) {
  const h = Math.floor(minutes / 60)
  const m = minutes % 60
  return h > 0 ? `${h}j ${m > 0 ? `${m}m` : ''}`.trim() : `${m}m`
}

const requests = ref<OvertimeRequestRow[]>([])
const loading = ref(true)
const errorMessage = ref('')

async function loadRequests() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/my-overtime-requests')
    requests.value = response.data.data.data
  } catch {
    errorMessage.value = 'Gagal memuat riwayat overtime request.'
  } finally {
    loading.value = false
  }
}

onMounted(loadRequests)

// ---------- Submit modal ----------
const showSubmitModal = ref(false)
const submitting = ref(false)
const submitError = ref('')
const form = reactive({
  attendance_date: '',
  planned_hours: 1,
  reason: '',
})

function openSubmitModal() {
  form.attendance_date = ''
  form.planned_hours = 1
  form.reason = ''
  submitError.value = ''
  showSubmitModal.value = true
}

async function submitRequest() {
  submitting.value = true
  submitError.value = ''
  try {
    await apiClient.post('/api/overtime-requests', {
      attendance_date: form.attendance_date,
      planned_minutes: Math.round(form.planned_hours * 60),
      reason: form.reason,
    })
    showSubmitModal.value = false
    loadRequests()
  } catch (err) {
    const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
    submitError.value = message || 'Gagal mengajukan overtime request.'
  } finally {
    submitting.value = false
  }
}

// ---------- Cancel / Claim actions ----------
const actingOnId = ref<number | null>(null)

async function cancelRequest(row: OvertimeRequestRow) {
  actingOnId.value = row.id
  try {
    await apiClient.post(`/api/overtime-requests/${row.id}/cancel`)
    loadRequests()
  } catch {
    // gagal cancel -- biarin row apa adanya, user bisa coba lagi
  } finally {
    actingOnId.value = null
  }
}

async function claimRequest(row: OvertimeRequestRow) {
  actingOnId.value = row.id
  try {
    await apiClient.post(`/api/overtime-requests/${row.id}/claim`)
    loadRequests()
  } catch {
    // gagal claim (mis. belum ada clock-out) -- biarin row apa adanya
  } finally {
    actingOnId.value = null
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Overtime Request</h1>
        <p class="mt-1 text-sm text-slate-500">Ajukan rencana lembur, lalu claim setelah beneran dikerjain.</p>
      </div>
      <button
        type="button"
        @click="openSubmitModal"
        class="inline-flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-dark"
      >
        <Plus class="h-4 w-4" :stroke-width="2" />
        Ajukan Overtime
      </button>
    </div>

    <div v-if="loading" class="flex items-center gap-2 py-10 text-sm text-slate-400">
      <Loader2 class="h-4 w-4 animate-spin" :stroke-width="2" />
      Memuat...
    </div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>
    <div v-else-if="requests.length === 0" class="rounded-2xl border border-slate-100 bg-white p-10 text-center text-sm text-slate-400">
      Belum ada overtime request.
    </div>

    <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs font-medium text-slate-500">
          <tr>
            <th class="px-5 py-3">Tanggal</th>
            <th class="px-3 py-3">Rencana</th>
            <th class="px-3 py-3">Aktual</th>
            <th class="px-3 py-3">Status</th>
            <th class="px-5 py-3 text-right">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <tr v-for="row in requests" :key="row.id" class="hover:bg-slate-50/60">
            <td class="px-5 py-3.5 whitespace-nowrap text-slate-700">{{ formatDate(row.attendance_date) }}</td>
            <td class="px-3 py-3.5 text-slate-500">
              <span class="inline-flex items-center gap-1">
                <Clock3 class="h-3.5 w-3.5 text-slate-300" :stroke-width="1.75" />
                {{ formatMinutes(row.planned_minutes) }}
              </span>
            </td>
            <td class="px-3 py-3.5 text-slate-500">
              {{ row.actual_overtime_minutes !== null ? formatMinutes(row.actual_overtime_minutes) : '-' }}
            </td>
            <td class="px-3 py-3.5">
              <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="statusBadgeClass[row.status]">
                {{ statusLabels[row.status] }}
              </span>
            </td>
            <td class="px-5 py-3.5 text-right">
              <button
                v-if="row.status === 'pending'"
                type="button"
                :disabled="actingOnId === row.id"
                @click="cancelRequest(row)"
                class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-50"
              >
                <Ban class="h-3.5 w-3.5" :stroke-width="1.75" />
                Batalkan
              </button>
              <button
                v-else-if="row.status === 'approved'"
                type="button"
                :disabled="actingOnId === row.id"
                @click="claimRequest(row)"
                class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-primary-dark hover:bg-primary-soft disabled:opacity-50"
              >
                <CheckCircle2 class="h-3.5 w-3.5" :stroke-width="1.75" />
                Claim
              </button>
              <span v-else class="text-xs text-slate-300">-</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Submit modal -->
    <Teleport to="body">
      <div v-if="showSubmitModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8" @click.self="showSubmitModal = false">
        <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Ajukan Overtime</h2>
            <button @click="showSubmitModal = false" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50">
              <X class="h-5 w-5" />
            </button>
          </div>

          <div class="space-y-4 px-6 py-5">
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-500">Tanggal</label>
              <input v-model="form.attendance_date" type="date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-500">Rencana Durasi (jam)</label>
              <input v-model.number="form.planned_hours" type="number" min="0.5" step="0.5" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-500">Alasan</label>
              <textarea v-model="form.reason" rows="3" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"></textarea>
            </div>

            <p v-if="submitError" class="text-sm text-red-600">{{ submitError }}</p>

            <div class="flex justify-end gap-2 pt-2">
              <button type="button" @click="showSubmitModal = false" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">
                Batal
              </button>
              <button
                type="button"
                :disabled="!form.attendance_date || !form.reason || submitting"
                @click="submitRequest"
                class="inline-flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50"
              >
                <Loader2 v-if="submitting" class="h-3.5 w-3.5 animate-spin" :stroke-width="2" />
                Ajukan
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>