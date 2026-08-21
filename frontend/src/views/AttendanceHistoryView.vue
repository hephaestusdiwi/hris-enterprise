<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue'
import {
  Clock,
  MapPin,
  FileText,
  Camera,
  ChevronLeft,
  ChevronRight,
  X,
  Loader2,
  Info,
} from 'lucide-vue-next'
import { VueDatePicker } from '@vuepic/vue-datepicker'
import { id as idLocale } from 'date-fns/locale'
import '@vuepic/vue-datepicker/dist/main.css'
import apiClient from '@/lib/axios'

type AttendanceStatus =
  | 'present'
  | 'late'
  | 'absent'
  | 'half_day'
  | 'leave'
  | 'sick'
  | 'alpha'

type AttendanceMethod =
  | 'self_service'
  | 'dynamic_qr'
  | 'device_employee_code'
  | 'device_face'
  | 'device_qr_card'
  | 'attendance_request'

interface Shift {
  id: number
  name: string
  start_time?: string
  end_time?: string
}

interface AttendanceRow {
  id: number
  attendance_date: string
  shift: Shift | null
  clock_in: string | null
  clock_out: string | null
  clock_in_latitude: string | null
  clock_in_longitude: string | null
  clock_out_latitude: string | null
  clock_out_longitude: string | null
  clock_in_method: AttendanceMethod | null
  clock_out_method: AttendanceMethod | null
  clock_in_photo_url: string | null
  clock_out_photo_url: string | null
  late_minutes: number | null
  within_grace: boolean | null
  detected_overtime_minutes: number | null
  approved_overtime_minutes: number | null
  status: AttendanceStatus
  notes: string | null
}

// Sama persis dengan label AttendanceStatus/AttendanceMethod di backend
// (app/Modules/Attendance/Enums) -- biar konsisten dgn AttendanceIndex.vue
// dan AttendanceReportView.vue.
const statusLabels: Record<AttendanceStatus, string> = {
  present: 'Present',
  late: 'Late',
  absent: 'Absent',
  half_day: 'Half Day',
  leave: 'Leave',
  sick: 'Sick',
  alpha: 'Alpha',
}

const statusBadgeClass: Record<AttendanceStatus, string> = {
  present: 'bg-primary-soft text-primary-dark',
  late: 'bg-amber-50 text-amber-600',
  absent: 'bg-red-50 text-red-600',
  half_day: 'bg-blue-50 text-blue-600',
  leave: 'bg-violet-50 text-violet-600',
  sick: 'bg-orange-50 text-orange-600',
  alpha: 'bg-slate-100 text-slate-500',
}

const methodLabels: Record<AttendanceMethod, string> = {
  self_service: 'Self-Service (App)',
  dynamic_qr: 'Dynamic Office QR',
  device_employee_code: 'Device - Employee Code',
  device_face: 'Device - Face Recognition',
  device_qr_card: 'Device - Employee QR Card',
  attendance_request: 'Attendance Request (Approved)',
}

function formatDateLabel(value: string): string {
  return new Date(value).toLocaleDateString('id-ID', {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })
}

function formatTime(value: string | null): string {
  if (!value) return '-'

  return new Date(value).toLocaleTimeString('id-ID', {
    hour: '2-digit',
    minute: '2-digit',
  })
}

function formatDateTime(value: string | null): string {
  if (!value) return '-'

  return new Date(value).toLocaleString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function mapsUrl(lat: string | null, lng: string | null): string | null {
  if (!lat || !lng) return null

  return `https://www.google.com/maps?q=${lat},${lng}`
}

const attendances = ref<AttendanceRow[]>([])
const loading = ref(true)
const errorMessage = ref('')
const meta = ref({
  current_page: 1,
  last_page: 1,
  total: 0,
})

const filters = reactive({
  status: '' as '' | AttendanceStatus,
  date_from: '',
  date_to: '',
  page: 1,
})

/**
 * Date range picker.
 *
 * UI:
 *   [ 01 Agu 2026 — 21 Agu 2026 ]
 *
 * API tetap:
 *   date_from=2026-08-01
 *   date_to=2026-08-21
 */
const dateRange = ref<[Date, Date] | null>(null)

function formatDateForApi(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')

  return `${year}-${month}-${day}`
}

function handleDateRangeUpdate(value: [Date, Date] | null) {
  dateRange.value = value

  if (!value) {
    filters.date_from = ''
    filters.date_to = ''

    return
  }

  filters.date_from = formatDateForApi(value[0])
  filters.date_to = formatDateForApi(value[1])
}

function clearDateRange() {
  dateRange.value = null
  filters.date_from = ''
  filters.date_to = ''
}

async function loadHistory() {
  loading.value = true
  errorMessage.value = ''

  try {
    const response = await apiClient.get('/api/my-attendances', {
      params: {
        status: filters.status || undefined,
        date_from: filters.date_from || undefined,
        date_to: filters.date_to || undefined,
        page: filters.page,
      },
    })

    attendances.value = response.data.data.data

    meta.value = {
      current_page: response.data.data.current_page,
      last_page: response.data.data.last_page,
      total: response.data.data.total,
    }
  } catch {
    errorMessage.value = 'Gagal memuat riwayat attendance.'
  } finally {
    loading.value = false
  }
}

function goToPage(page: number) {
  if (
    page < 1 ||
    page > meta.value.last_page ||
    page === meta.value.current_page
  ) {
    return
  }

  filters.page = page
  loadHistory()
}

const paginationItems = computed(() => {
  const total = meta.value.last_page
  const current = meta.value.current_page

  if (total <= 1) return []

  const items: (number | '...')[] = [1]
  const left = Math.max(2, current - 1)
  const right = Math.min(total - 1, current + 1)

  if (left > 2) items.push('...')

  for (let i = left; i <= right; i++) {
    items.push(i)
  }

  if (right < total - 1) items.push('...')

  items.push(total)

  return items
})

watch(
  () => [filters.status, filters.date_from, filters.date_to],
  () => {
    filters.page = 1
    loadHistory()
  },
)

// ---------- Detail modal ----------
const detailTarget = ref<AttendanceRow | null>(null)
const detailLoading = ref(false)
const detailError = ref('')

async function openDetail(row: AttendanceRow) {
  detailTarget.value = row
  detailError.value = ''
  detailLoading.value = true

  try {
    const response = await apiClient.get(`/api/my-attendances/${row.id}`)
    detailTarget.value = response.data.data
  } catch {
    detailError.value = 'Gagal memuat detail attendance.'
  } finally {
    detailLoading.value = false
  }
}

function closeDetail() {
  detailTarget.value = null
}

onMounted(() => {
  loadHistory()
})
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight text-slate-900">
        Attendance History
      </h1>

      <p class="mt-1 text-sm text-slate-500">
        Riwayat kehadiran kamu -- clock in, clock out, dan status per hari.
      </p>
    </div>

    <!-- Filters -->
    <div
      class="flex flex-wrap items-end gap-3 rounded-2xl border border-slate-100 bg-white p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)]"
    >
      <!-- Date Range -->
      <div class="min-w-[300px]">
        <label class="mb-1.5 block text-xs font-medium text-slate-500">
          Periode Tanggal
        </label>

        <VueDatePicker
          v-model="dateRange"
          range
          :enable-time-picker="false"
          :clearable="true"
          auto-apply
          :locale="idLocale"
          format="dd MMM yyyy"
          placeholder="Pilih periode tanggal"
          @update:model-value="handleDateRangeUpdate"
          @cleared="clearDateRange"
        />
      </div>

      <!-- Status -->
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-500">
          Status
        </label>

        <select
          v-model="filters.status"
          class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
        >
          <option value="">Semua Status</option>

          <option
            v-for="(label, value) in statusLabels"
            :key="value"
            :value="value"
          >
            {{ label }}
          </option>
        </select>
      </div>

      <!-- Reset -->
      <button
        v-if="filters.status || filters.date_from || filters.date_to"
        type="button"
        @click="filters.status = ''; clearDateRange()"
        class="rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-500 hover:bg-slate-50"
      >
        Reset Filter
      </button>
    </div>

    <!-- Loading -->
    <div
      v-if="loading"
      class="flex items-center gap-2 py-10 text-sm text-slate-400"
    >
      <Loader2 class="h-4 w-4 animate-spin" :stroke-width="2" />
      Memuat riwayat attendance...
    </div>

    <!-- Error -->
    <div
      v-else-if="errorMessage"
      class="rounded-xl bg-red-50 p-4 text-sm text-red-600"
    >
      {{ errorMessage }}
    </div>

    <!-- Empty -->
    <div
      v-else-if="attendances.length === 0"
      class="rounded-2xl border border-slate-100 bg-white p-10 text-center text-sm text-slate-400"
    >
      Belum ada riwayat attendance untuk filter ini.
    </div>

    <!-- Table -->
    <div
      v-else
      class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]"
    >
      <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs font-medium text-slate-500">
          <tr>
            <th class="px-5 py-3">Tanggal</th>
            <th class="px-3 py-3">Schedule</th>
            <th class="px-3 py-3">Clock In - Clock Out</th>
            <th class="px-3 py-3">Status</th>
            <th class="px-5 py-3 text-right">Detail</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-slate-50">
          <tr
            v-for="row in attendances"
            :key="row.id"
            class="cursor-pointer transition-colors hover:bg-slate-50/60"
            @click="openDetail(row)"
          >
            <td
              class="whitespace-nowrap px-5 py-3.5 text-slate-600"
            >
              {{ formatDateLabel(row.attendance_date) }}
            </td>

            <td class="px-3 py-3.5 text-slate-500">
              {{ row.shift?.name ?? '-' }}
            </td>

            <td
              class="whitespace-nowrap px-3 py-3.5 text-slate-500"
            >
              <div class="flex items-center gap-1.5">
                <Clock
                  class="h-3.5 w-3.5 text-slate-300"
                  :stroke-width="1.75"
                />

                {{ formatTime(row.clock_in) }}
                -
                {{ formatTime(row.clock_out) }}
              </div>
            </td>

            <td class="px-3 py-3.5">
              <span
                class="rounded-full px-2.5 py-1 text-xs font-medium"
                :class="statusBadgeClass[row.status]"
              >
                {{ statusLabels[row.status] }}
              </span>
            </td>

            <td class="px-5 py-3.5 text-right">
              <button
                type="button"
                @click.stop="openDetail(row)"
                class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-600 transition-colors hover:bg-slate-50"
              >
                <Info
                  class="h-3.5 w-3.5"
                  :stroke-width="1.75"
                />
                Lihat
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div
      v-if="meta.last_page > 1"
      class="flex items-center justify-between text-sm text-slate-500"
    >
      <p>Total {{ meta.total }} record</p>

      <div class="flex items-center gap-1">
        <button
          type="button"
          @click="goToPage(meta.current_page - 1)"
          :disabled="meta.current_page === 1"
          class="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 disabled:opacity-30 disabled:hover:bg-transparent"
        >
          <ChevronLeft
            class="h-4 w-4"
            :stroke-width="2"
          />
        </button>

        <template
          v-for="(item, i) in paginationItems"
          :key="i"
        >
          <span
            v-if="item === '...'"
            class="px-2 text-xs text-slate-300"
          >
            ...
          </span>

          <button
            v-else
            type="button"
            @click="goToPage(item)"
            class="min-w-[32px] rounded-lg px-2.5 py-1.5 text-xs font-medium transition-colors"
            :class="
              item === meta.current_page
                ? 'bg-primary text-white'
                : 'text-slate-500 hover:bg-slate-100'
            "
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
          <ChevronRight
            class="h-4 w-4"
            :stroke-width="2"
          />
        </button>
      </div>
    </div>

    <!-- Detail modal -->
    <Teleport to="body">
      <div
        v-if="detailTarget"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8"
        @click.self="closeDetail"
      >
        <div
          class="flex max-h-full w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-white shadow-xl"
        >
          <!-- Modal header -->
          <div
            class="flex items-center justify-between border-b border-slate-100 px-6 py-4"
          >
            <div>
              <h2 class="text-lg font-semibold text-slate-900">
                {{ formatDateLabel(detailTarget.attendance_date) }}
              </h2>

              <p class="text-xs text-slate-400">
                {{ detailTarget.shift?.name ?? 'Tanpa Shift' }}
              </p>
            </div>

            <button
              @click="closeDetail"
              class="rounded-lg p-1 text-slate-400 hover:bg-slate-50"
            >
              <X class="h-5 w-5" />
            </button>
          </div>

          <!-- Modal content -->
          <div class="flex-1 space-y-5 overflow-y-auto px-6 py-5">
            <div
              v-if="detailLoading"
              class="flex items-center gap-2 py-6 text-sm text-slate-400"
            >
              <Loader2
                class="h-4 w-4 animate-spin"
                :stroke-width="2"
              />
              Memuat detail...
            </div>

            <p
              v-else-if="detailError"
              class="text-sm text-red-600"
            >
              {{ detailError }}
            </p>

            <template v-else>
              <!-- Status -->
              <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-slate-500">
                  Status
                </span>

                <span
                  class="rounded-full px-2.5 py-1 text-xs font-medium"
                  :class="statusBadgeClass[detailTarget.status]"
                >
                  {{ statusLabels[detailTarget.status] }}
                </span>
              </div>

              <!-- Clock In -->
              <div class="rounded-xl border border-slate-100 p-4">
                <p
                  class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400"
                >
                  Clock In
                </p>

                <div class="flex items-start gap-4">
                  <img
                    v-if="detailTarget.clock_in_photo_url"
                    :src="detailTarget.clock_in_photo_url"
                    alt="Clock-in photo"
                    class="h-16 w-16 shrink-0 rounded-xl object-cover"
                  />

                  <div
                    v-else-if="detailTarget.clock_in"
                    class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl bg-slate-50 text-slate-300"
                  >
                    <Camera
                      class="h-6 w-6"
                      :stroke-width="1.5"
                    />
                  </div>

                  <div class="min-w-0 flex-1 space-y-1 text-sm">
                    <p class="font-medium text-slate-800">
                      {{ formatDateTime(detailTarget.clock_in) }}
                    </p>

                    <p
                      v-if="detailTarget.clock_in_method"
                      class="text-xs text-slate-400"
                    >
                      Source:
                      {{ methodLabels[detailTarget.clock_in_method] }}
                    </p>

                    <a
                      v-if="
                        mapsUrl(
                          detailTarget.clock_in_latitude,
                          detailTarget.clock_in_longitude,
                        )
                      "
                      :href="
                        mapsUrl(
                          detailTarget.clock_in_latitude,
                          detailTarget.clock_in_longitude,
                        )!
                      "
                      target="_blank"
                      rel="noopener"
                      class="inline-flex items-center gap-1 text-xs text-primary-dark hover:underline"
                    >
                      <MapPin
                        class="h-3 w-3"
                        :stroke-width="1.75"
                      />
                      Lihat lokasi
                    </a>
                  </div>
                </div>
              </div>

              <!-- Clock Out -->
              <div class="rounded-xl border border-slate-100 p-4">
                <p
                  class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400"
                >
                  Clock Out
                </p>

                <div class="flex items-start gap-4">
                  <img
                    v-if="detailTarget.clock_out_photo_url"
                    :src="detailTarget.clock_out_photo_url"
                    alt="Clock-out photo"
                    class="h-16 w-16 shrink-0 rounded-xl object-cover"
                  />

                  <div
                    v-else-if="detailTarget.clock_out"
                    class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl bg-slate-50 text-slate-300"
                  >
                    <Camera
                      class="h-6 w-6"
                      :stroke-width="1.5"
                    />
                  </div>

                  <div class="min-w-0 flex-1 space-y-1 text-sm">
                    <p class="font-medium text-slate-800">
                      {{ formatDateTime(detailTarget.clock_out) }}
                    </p>

                    <p
                      v-if="detailTarget.clock_out_method"
                      class="text-xs text-slate-400"
                    >
                      Source:
                      {{ methodLabels[detailTarget.clock_out_method] }}
                    </p>

                    <a
                      v-if="
                        mapsUrl(
                          detailTarget.clock_out_latitude,
                          detailTarget.clock_out_longitude,
                        )
                      "
                      :href="
                        mapsUrl(
                          detailTarget.clock_out_latitude,
                          detailTarget.clock_out_longitude,
                        )!
                      "
                      target="_blank"
                      rel="noopener"
                      class="inline-flex items-center gap-1 text-xs text-primary-dark hover:underline"
                    >
                      <MapPin
                        class="h-3 w-3"
                        :stroke-width="1.75"
                      />
                      Lihat lokasi
                    </a>
                  </div>
                </div>
              </div>

              <!-- Late / Overtime -->
              <div
                v-if="
                  detailTarget.late_minutes ||
                  detailTarget.detected_overtime_minutes
                "
                class="flex flex-wrap gap-2 text-xs"
              >
                <span
                  v-if="detailTarget.late_minutes"
                  class="rounded-full bg-amber-50 px-2.5 py-1 font-medium text-amber-600"
                >
                  Telat {{ detailTarget.late_minutes }} mnt{{
                    detailTarget.within_grace ? ' (grace)' : ''
                  }}
                </span>

                <span
                  v-if="detailTarget.detected_overtime_minutes"
                  class="rounded-full bg-blue-50 px-2.5 py-1 font-medium text-blue-600"
                >
                  Lembur
                  {{
                    detailTarget.approved_overtime_minutes ??
                    detailTarget.detected_overtime_minutes
                  }}
                  mnt
                  {{
                    detailTarget.approved_overtime_minutes
                      ? '(approved)'
                      : '(terdeteksi)'
                  }}
                </span>
              </div>

              <!-- Notes -->
              <div v-if="detailTarget.notes">
                <p
                  class="mb-1 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-slate-400"
                >
                  <FileText
                    class="h-3.5 w-3.5"
                    :stroke-width="1.75"
                  />
                  Catatan
                </p>

                <p class="text-sm text-slate-600">
                  {{ detailTarget.notes }}
                </p>
              </div>
            </template>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style>
.dp__theme_light {
  --dp-primary-color: var(--color-primary, #117c6f);
  --dp-primary-text-color: #ffffff;
  --dp-border-radius: 12px;
  --dp-font-family: inherit;
}

.dp__input {
  height: 40px !important;
  border-radius: 12px !important;
  border-color: #e2e8f0 !important;
  font-size: 0.875rem !important;
  box-shadow: none !important;
}

.dp__input:hover {
  border-color: #cbd5e1 !important;
}

.dp__input:focus {
  border-color: var(--color-primary, #117c6f) !important;
}

.dp__menu {
  border-radius: 16px !important;
  border-color: #f1f5f9 !important;
  box-shadow:
    0 10px 25px -5px rgba(15, 23, 42, 0.08),
    0 8px 10px -6px rgba(15, 23, 42, 0.05) !important;
}
</style>