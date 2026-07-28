<script setup lang="ts">
import { ref, onMounted, reactive, computed } from 'vue'
import { Plus, X, Loader2, AlertTriangle, Paperclip, Ban } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface LeaveType {
  id: number
  name: string
  color: string | null
  allow_half_day: boolean
  allow_hourly: boolean
  requires_attachment: boolean
}

type LeaveRequestStatus = 'pending' | 'approved' | 'rejected' | 'cancelled'

interface LeaveRequestRow {
  id: number
  start_date: string
  end_date: string
  is_half_day: boolean
  half_day_session: string | null
  start_time: string | null
  end_time: string | null
  total_days: string
  reason: string
  attachment_path: string | null
  status: LeaveRequestStatus
  leave_type: LeaveType
}

const statusLabels: Record<LeaveRequestStatus, string> = {
  pending: 'Menunggu Persetujuan',
  approved: 'Disetujui',
  rejected: 'Ditolak',
  cancelled: 'Dibatalkan',
}

const statusBadgeClass: Record<LeaveRequestStatus, string> = {
  pending: 'bg-amber-50 text-amber-600',
  approved: 'bg-primary-soft text-primary-dark',
  rejected: 'bg-red-50 text-red-600',
  cancelled: 'bg-slate-100 text-slate-500',
}

function formatDate(value: string) {
  return new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

const requests = ref<LeaveRequestRow[]>([])
const leaveTypes = ref<LeaveType[]>([])
const loading = ref(true)
const errorMessage = ref('')

async function loadRequests() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/my-leave-requests')
    requests.value = response.data.data.data
  } catch {
    errorMessage.value = 'Gagal memuat riwayat leave request.'
  } finally {
    loading.value = false
  }
}

async function loadLeaveTypes() {
  try {
    const response = await apiClient.get('/api/leave-types/self-service')
    leaveTypes.value = response.data.data
  } catch (err: any) {
    console.error('Gagal memuat leave types:', err)
    errorMessage.value = err.response?.data?.message || 'Gagal memuat jenis cuti. Coba refresh halaman.'
  }
}

// ---------- SUBMIT MODAL ----------
const showModal = ref(false)
const saving = ref(false)
const uploading = ref(false)
const formError = ref('')

const form = reactive({
  leave_type_id: null as number | null,
  start_date: '',
  end_date: '',
  mode: 'full' as 'full' | 'half' | 'hourly',
  half_day_session: 'morning' as 'morning' | 'afternoon',
  start_time: '',
  end_time: '',
  reason: '',
  attachment_path: '',
  attachment_filename: '',
})

const selectedLeaveType = computed(() => leaveTypes.value.find((lt) => lt.id === form.leave_type_id) ?? null)

function openCreateModal() {
  formError.value = ''
  form.leave_type_id = leaveTypes.value[0]?.id ?? null
  form.start_date = new Date().toISOString().slice(0, 10)
  form.end_date = new Date().toISOString().slice(0, 10)
  form.mode = 'full'
  form.half_day_session = 'morning'
  form.start_time = ''
  form.end_time = ''
  form.reason = ''
  form.attachment_path = ''
  form.attachment_filename = ''
  showModal.value = true
}

function closeModal() {
  showModal.value = false
}

async function handleFileChange(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (!file) return

  uploading.value = true
  formError.value = ''

  try {
    const formData = new FormData()
    formData.append('file', file)
    const response = await apiClient.post('/api/leave-requests/attachments', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    form.attachment_path = response.data.data.path
    form.attachment_filename = file.name
  } catch {
    formError.value = 'Gagal upload lampiran.'
  } finally {
    uploading.value = false
  }
}

async function submitRequest() {
  formError.value = ''

  if (selectedLeaveType.value?.requires_attachment && !form.attachment_path) {
    formError.value = 'Leave type ini mewajibkan lampiran.'
    return
  }

  saving.value = true

  const payload: Record<string, unknown> = {
    leave_type_id: form.leave_type_id,
    start_date: form.start_date,
    end_date: form.mode === 'full' ? form.end_date : form.start_date,
    reason: form.reason,
    attachment_path: form.attachment_path || null,
  }

  if (form.mode === 'half') {
    payload.is_half_day = true
    payload.half_day_session = form.half_day_session
  } else if (form.mode === 'hourly') {
    payload.start_time = form.start_time
    payload.end_time = form.end_time
  }

  try {
    await apiClient.post('/api/leave-requests', payload)
    showModal.value = false
    await loadRequests()
  } catch (err: any) {
    formError.value = err.response?.data?.message || 'Gagal mengajukan leave request.'
  } finally {
    saving.value = false
  }
}

async function cancelRequest(row: LeaveRequestRow) {
  if (!confirm('Batalkan leave request ini?')) return

  try {
    await apiClient.post(`/api/leave-requests/${row.id}/cancel`)
    await loadRequests()
  } catch {
    alert('Gagal membatalkan leave request.')
  }
}

onMounted(() => {
  loadRequests()
  loadLeaveTypes()
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Leave Request Saya</h1>
        <p class="mt-1 text-sm text-slate-500">Riwayat dan pengajuan cuti Anda.</p>
      </div>
      <button
        @click="openCreateModal"
        :disabled="leaveTypes.length === 0"
        class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50"
      >
        <Plus class="h-4 w-4" :stroke-width="2" />
        Ajukan Cuti
      </button>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>
    <div v-else-if="requests.length === 0" class="rounded-xl bg-slate-50 p-6 text-center text-sm text-slate-400">
      Belum ada leave request.
    </div>

    <div v-else class="space-y-3">
      <div
        v-for="row in requests"
        :key="row.id"
        class="flex items-center justify-between rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]"
      >
        <div class="flex items-center gap-4">
          <span class="h-10 w-1.5 shrink-0 rounded-full" :style="{ backgroundColor: row.leave_type.color ?? '#94A3B8' }"></span>
          <div>
            <p class="font-medium text-slate-800">{{ row.leave_type.name }}</p>
            <p class="mt-0.5 text-xs text-slate-500">
              {{ formatDate(row.start_date) }}
              <span v-if="row.start_date !== row.end_date"> - {{ formatDate(row.end_date) }}</span>
              <span v-if="row.is_half_day"> · Setengah hari ({{ row.half_day_session === 'morning' ? 'Pagi' : 'Siang' }})</span>
              <span v-else-if="row.start_time"> · {{ row.start_time }} - {{ row.end_time }}</span>
              · {{ row.total_days }} hari
            </p>
            <p class="mt-1 text-xs text-slate-400">{{ row.reason }}</p>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="statusBadgeClass[row.status]">
            {{ statusLabels[row.status] }}
          </span>
          <button
            v-if="row.status === 'pending'"
            @click="cancelRequest(row)"
            class="rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-500"
            title="Batalkan"
          >
            <Ban class="h-4 w-4" :stroke-width="1.75" />
          </button>
        </div>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8">
        <div class="flex max-h-full w-full max-w-lg flex-col rounded-2xl bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Ajukan Cuti</h2>
            <button @click="closeModal" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50">
              <X class="h-5 w-5" />
            </button>
          </div>

          <form @submit.prevent="submitRequest" class="flex-1 space-y-4 overflow-y-auto px-6 py-5">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Jenis Cuti</label>
              <select
                v-model.number="form.leave_type_id"
                required
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
              >
                <option v-for="lt in leaveTypes" :key="lt.id" :value="lt.id">{{ lt.name }}</option>
              </select>
            </div>

            <div v-if="selectedLeaveType && (selectedLeaveType.allow_half_day || selectedLeaveType.allow_hourly)">
              <label class="mb-1 block text-sm font-medium text-slate-700">Tipe Pengajuan</label>
              <div class="flex gap-2">
                <button type="button" @click="form.mode = 'full'" class="flex-1 rounded-xl border py-2 text-sm font-medium" :class="form.mode === 'full' ? 'border-primary bg-primary-soft text-primary-dark' : 'border-slate-200 text-slate-500'">
                  Hari Penuh
                </button>
                <button v-if="selectedLeaveType.allow_half_day" type="button" @click="form.mode = 'half'" class="flex-1 rounded-xl border py-2 text-sm font-medium" :class="form.mode === 'half' ? 'border-primary bg-primary-soft text-primary-dark' : 'border-slate-200 text-slate-500'">
                  Setengah Hari
                </button>
                <button v-if="selectedLeaveType.allow_hourly" type="button" @click="form.mode = 'hourly'" class="flex-1 rounded-xl border py-2 text-sm font-medium" :class="form.mode === 'hourly' ? 'border-primary bg-primary-soft text-primary-dark' : 'border-slate-200 text-slate-500'">
                  Per Jam
                </button>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Tanggal Mulai</label>
                <input v-model="form.start_date" type="date" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
              <div v-if="form.mode === 'full'">
                <label class="mb-1 block text-sm font-medium text-slate-700">Tanggal Selesai</label>
                <input v-model="form.end_date" type="date" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
            </div>

            <div v-if="form.mode === 'half'">
              <label class="mb-1 block text-sm font-medium text-slate-700">Sesi</label>
              <select v-model="form.half_day_session" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                <option value="morning">Pagi</option>
                <option value="afternoon">Siang</option>
              </select>
            </div>

            <div v-if="form.mode === 'hourly'" class="grid grid-cols-2 gap-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Jam Mulai</label>
                <input v-model="form.start_time" type="time" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Jam Selesai</label>
                <input v-model="form.end_time" type="time" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Alasan</label>
              <textarea v-model="form.reason" rows="3" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"></textarea>
            </div>

            <div v-if="selectedLeaveType?.requires_attachment">
              <label class="mb-1 block text-sm font-medium text-slate-700">Lampiran (wajib)</label>
              <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-dashed border-slate-300 px-3 py-3 text-sm text-slate-500 hover:bg-slate-50">
                <Paperclip class="h-4 w-4" :stroke-width="1.75" />
                {{ form.attachment_filename || 'Pilih file' }}
                <input type="file" class="hidden" @change="handleFileChange" />
              </label>
              <p v-if="uploading" class="mt-1 text-xs text-slate-400">Mengupload...</p>
            </div>

            <div v-if="formError" class="flex items-start gap-2 rounded-xl bg-red-50 p-3 text-xs text-red-600">
              <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" :stroke-width="1.75" />
              <p>{{ formError }}</p>
            </div>
          </form>

          <div class="border-t border-slate-100 px-6 py-4">
            <button
              @click="submitRequest"
              :disabled="saving || uploading"
              class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary py-2.5 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50"
            >
              <Loader2 v-if="saving" class="h-4 w-4 animate-spin" :stroke-width="2" />
              {{ saving ? 'Mengajukan...' : 'Ajukan' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>