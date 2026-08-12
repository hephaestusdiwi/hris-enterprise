<script setup lang="ts">
import { ref, onMounted, reactive, computed } from 'vue'
import { Plus, X, Loader2, AlertTriangle, Paperclip, Ban, FileText } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

type AttendanceRequestStatus = 'pending' | 'approved' | 'rejected' | 'cancelled'

interface Attachment {
  id: number
  file_name: string
  file_size: number
  url: string | null
}

interface Shift {
  id: number
  name: string
}

interface AttendanceRequestRow {
  id: number
  attendance_date: string
  requested_clock_in: string | null
  requested_clock_out: string | null
  reason: string
  status: AttendanceRequestStatus
  submitted_at: string
  decided_at: string | null
  shift: Shift | null
  attachments: Attachment[]
}

const statusLabels: Record<AttendanceRequestStatus, string> = {
  pending: 'Menunggu Persetujuan',
  approved: 'Disetujui',
  rejected: 'Ditolak',
  cancelled: 'Dibatalkan',
}

const statusBadgeClass: Record<AttendanceRequestStatus, string> = {
  pending: 'bg-amber-50 text-amber-600',
  approved: 'bg-primary-soft text-primary-dark',
  rejected: 'bg-red-50 text-red-600',
  cancelled: 'bg-slate-100 text-slate-500',
}

function formatDate(value: string) {
  return new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

function formatTime(value: string | null) {
  if (!value) return null
  return new Date(value).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
}

function formatFileSize(bytes: number) {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

const requests = ref<AttendanceRequestRow[]>([])
const loading = ref(true)
const errorMessage = ref('')

async function loadRequests() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/my-attendance-requests')
    requests.value = response.data.data.data
  } catch {
    errorMessage.value = 'Gagal memuat riwayat attendance request.'
  } finally {
    loading.value = false
  }
}

// ---------- SUBMIT MODAL ----------
const MAX_ATTACHMENTS = 5
const MAX_ATTACHMENT_MB = 5

const showModal = ref(false)
const saving = ref(false)
const formError = ref('')

const form = reactive({
  attendance_date: new Date().toISOString().slice(0, 10),
  enableClockIn: true,
  clockInTime: '08:00',
  enableClockOut: false,
  clockOutTime: '17:00',
  reason: '',
  attachments: [] as File[],
})

function openCreateModal() {
  formError.value = ''
  form.attendance_date = new Date().toISOString().slice(0, 10)
  form.enableClockIn = true
  form.clockInTime = '08:00'
  form.enableClockOut = false
  form.clockOutTime = '17:00'
  form.reason = ''
  form.attachments = []
  showModal.value = true
}

function closeModal() {
  showModal.value = false
}

function handleFilesChange(event: Event) {
  const files = Array.from((event.target as HTMLInputElement).files ?? [])
  const combined = [...form.attachments, ...files]

  if (combined.length > MAX_ATTACHMENTS) {
    formError.value = `Maksimal ${MAX_ATTACHMENTS} file attachment per request.`
    return
  }

  const tooLarge = files.find((f) => f.size > MAX_ATTACHMENT_MB * 1024 * 1024)
  if (tooLarge) {
    formError.value = `File "${tooLarge.name}" melebihi ${MAX_ATTACHMENT_MB}MB.`
    return
  }

  formError.value = ''
  form.attachments = combined
  ;(event.target as HTMLInputElement).value = ''
}

function removeAttachment(index: number) {
  form.attachments = form.attachments.filter((_, i) => i !== index)
}

async function submitRequest() {
  formError.value = ''

  if (!form.enableClockIn && !form.enableClockOut) {
    formError.value = 'Pilih minimal salah satu: Clock In atau Clock Out.'
    return
  }
  if (!form.reason.trim()) {
    formError.value = 'Alasan wajib diisi.'
    return
  }

  saving.value = true

  const formData = new FormData()
  formData.append('attendance_date', form.attendance_date)
  if (form.enableClockIn) {
    formData.append('requested_clock_in', `${form.attendance_date}T${form.clockInTime}:00`)
  }
  if (form.enableClockOut) {
    formData.append('requested_clock_out', `${form.attendance_date}T${form.clockOutTime}:00`)
  }
  formData.append('reason', form.reason)
  form.attachments.forEach((file) => formData.append('attachments[]', file))

  try {
    await apiClient.post('/api/attendance-requests', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    showModal.value = false
    await loadRequests()
  } catch (err: any) {
    formError.value = err.response?.data?.message || 'Gagal mengajukan attendance request.'
  } finally {
    saving.value = false
  }
}

async function cancelRequest(row: AttendanceRequestRow) {
  if (!confirm('Batalkan attendance request ini?')) return

  try {
    await apiClient.post(`/api/attendance-requests/${row.id}/cancel`)
    await loadRequests()
  } catch {
    alert('Gagal membatalkan attendance request.')
  }
}

const canAddMoreAttachments = computed(() => form.attachments.length < MAX_ATTACHMENTS)

onMounted(() => {
  loadRequests()
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Attendance Request Saya</h1>
        <p class="mt-1 text-sm text-slate-500">
          Ajukan koreksi/laporan clock-in atau clock-out (mis. sistem down, lupa tap, listrik mati).
        </p>
      </div>
      <button
        @click="openCreateModal"
        class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-white hover:bg-primary-dark"
      >
        <Plus class="h-4 w-4" :stroke-width="2" />
        Ajukan Attendance Request
      </button>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>
    <div v-else-if="requests.length === 0" class="rounded-xl bg-slate-50 p-6 text-center text-sm text-slate-400">
      Belum ada attendance request.
    </div>

    <div v-else class="space-y-3">
      <div
        v-for="row in requests"
        :key="row.id"
        class="flex items-center justify-between rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]"
      >
        <div>
          <p class="font-medium text-slate-800">{{ formatDate(row.attendance_date) }}</p>
          <p class="mt-0.5 text-xs text-slate-500">
            <span v-if="row.requested_clock_in">Clock In {{ formatTime(row.requested_clock_in) }}</span>
            <span v-if="row.requested_clock_in && row.requested_clock_out"> · </span>
            <span v-if="row.requested_clock_out">Clock Out {{ formatTime(row.requested_clock_out) }}</span>
            <span v-if="row.shift"> · {{ row.shift.name }}</span>
          </p>
          <p class="mt-1 text-xs text-slate-400">{{ row.reason }}</p>
          <div v-if="row.attachments.length > 0" class="mt-2 flex flex-wrap gap-2">
            <a
              v-for="att in row.attachments"
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

        <div class="flex shrink-0 items-center gap-2">
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
            <h2 class="text-lg font-semibold text-slate-900">Ajukan Attendance Request</h2>
            <button @click="closeModal" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50">
              <X class="h-5 w-5" />
            </button>
          </div>

          <form @submit.prevent="submitRequest" class="flex-1 space-y-4 overflow-y-auto px-6 py-5">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Tanggal Attendance</label>
              <input
                v-model="form.attendance_date"
                type="date"
                required
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
              />
            </div>

            <div class="space-y-3 rounded-xl border border-slate-200 p-3">
              <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                <input v-model="form.enableClockIn" type="checkbox" class="rounded border-slate-300 text-primary focus:ring-primary" />
                Clock In
              </label>
              <input
                v-if="form.enableClockIn"
                v-model="form.clockInTime"
                type="time"
                required
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
              />
            </div>

            <div class="space-y-3 rounded-xl border border-slate-200 p-3">
              <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                <input v-model="form.enableClockOut" type="checkbox" class="rounded border-slate-300 text-primary focus:ring-primary" />
                Clock Out
              </label>
              <input
                v-if="form.enableClockOut"
                v-model="form.clockOutTime"
                type="time"
                required
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
              />
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Alasan</label>
              <textarea
                v-model="form.reason"
                rows="3"
                required
                placeholder="Contoh: sistem tidak dapat diakses, listrik mati, lupa tap"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
              ></textarea>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">
                Lampiran (opsional, maks {{ MAX_ATTACHMENTS }} file, {{ MAX_ATTACHMENT_MB }}MB/file — JPG/JPEG/PDF/CSV/XLSX)
              </label>
              <label
                v-if="canAddMoreAttachments"
                class="flex cursor-pointer items-center gap-2 rounded-xl border border-dashed border-slate-300 px-3 py-3 text-sm text-slate-500 hover:bg-slate-50"
              >
                <Paperclip class="h-4 w-4" :stroke-width="1.75" />
                Pilih file
                <input
                  type="file"
                  multiple
                  accept=".jpg,.jpeg,.pdf,.csv,.xlsx"
                  class="hidden"
                  @change="handleFilesChange"
                />
              </label>

              <ul v-if="form.attachments.length > 0" class="mt-2 space-y-1.5">
                <li
                  v-for="(file, index) in form.attachments"
                  :key="`${file.name}-${index}`"
                  class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600"
                >
                  <span class="truncate">{{ file.name }} · {{ formatFileSize(file.size) }}</span>
                  <button type="button" @click="removeAttachment(index)" class="shrink-0 text-slate-400 hover:text-red-500">
                    <X class="h-3.5 w-3.5" :stroke-width="2" />
                  </button>
                </li>
              </ul>
            </div>

            <div v-if="formError" class="flex items-start gap-2 rounded-xl bg-red-50 p-3 text-xs text-red-600">
              <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" :stroke-width="1.75" />
              <p>{{ formError }}</p>
            </div>
          </form>

          <div class="border-t border-slate-100 px-6 py-4">
            <button
              @click="submitRequest"
              :disabled="saving"
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
