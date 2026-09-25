<script setup lang="ts">
import { ref, onMounted, computed, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import OfficeQrScanModal from '@/components/OfficeQrScanModal.vue'
import FaceAttendanceModal from './FaceAttendanceModal.vue'
import {
  LogIn,
  LogOut,
  Clock,
  QrCode as QrCodeIcon,
  CalendarClock,
  CircleDot,
  ShieldCheck,
} from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface ShiftInfo {
  id: number
  name: string
  start_time: string
  end_time: string
}

interface TodayAttendance {
  attendance_date: string
  status: string | null
  clock_in: string | null
  clock_out: string | null
  can_clock_in: boolean
  can_clock_out: boolean
  shift: ShiftInfo | null
  requires_photo: boolean
  requires_face_verification: boolean
  requires_location: boolean
}

const router = useRouter()

const today = ref<TodayAttendance | null>(null)
const loading = ref(true)
const submitting = ref(false)
const errorMessage = ref('')

const statusLabels: Record<string, string> = {
  present: 'Present',
  late: 'Late',
  absent: 'Absent',
  half_day: 'Half Day',
  leave: 'Leave',
  sick: 'Sick',
  alpha: 'Alpha',
}

const currentTime = ref(new Date())

let clockTimer: ReturnType<typeof setInterval> | null = null

const formattedClock = computed(() =>
  currentTime.value.toLocaleTimeString('id-ID', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  }),
)

function formatTime(value: string | null): string {
  if (!value) return '-'

  return new Date(value.replace(' ', 'T')).toLocaleTimeString('id-ID', {
    hour: '2-digit',
    minute: '2-digit',
  })
}

function formatDate(value: string): string {
  return new Date(value).toLocaleDateString('id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
}

function shiftLabel(shift: ShiftInfo | null): string {
  if (!shift) return '-'
  return `${shift.name} (${shift.start_time} - ${shift.end_time})`
}

/**
 * Working time:
 * - 0% when employee has not clocked in.
 * - When clocked in, elapsed time is measured against the scheduled shift duration.
 * - When clocked out, the actual clock-in → clock-out duration is used.
 *
 * This is intentionally frontend-only because /api/attendance/today in the
 * supplied contract does not expose a working-time percentage field.
 */
function toDateTime(value: string): Date | null {
  if (!value) return null
  const parsed = new Date(value.replace(' ', 'T'))
  return Number.isNaN(parsed.getTime()) ? null : parsed
}

function shiftDurationMinutes(shift: ShiftInfo | null): number {
  if (!shift) return 0

  const [startHour, startMinute] = shift.start_time.split(':').map(Number)
  const [endHour, endMinute] = shift.end_time.split(':').map(Number)

  if (
    Number.isNaN(startHour) ||
    Number.isNaN(startMinute) ||
    Number.isNaN(endHour) ||
    Number.isNaN(endMinute)
  ) {
    return 0
  }

  let start = startHour * 60 + startMinute
  let end = endHour * 60 + endMinute

  if (end <= start) end += 24 * 60

  return Math.max(0, end - start)
}

const workingMinutes = computed(() => {
  if (!today.value?.clock_in) return 0

  const start = toDateTime(today.value.clock_in)
  if (!start) return 0

  const end = today.value.clock_out
    ? toDateTime(today.value.clock_out)
    : currentTime.value

  if (!end) return 0

  return Math.max(0, Math.floor((end.getTime() - start.getTime()) / 60000))
})

const workingTimePercent = computed(() => {
  const shiftMinutes = shiftDurationMinutes(today.value?.shift ?? null)
  if (!shiftMinutes) return 0

  return Math.min(100, Math.round((workingMinutes.value / shiftMinutes) * 100))
})

const workingTimeLabel = computed(() => {
  const minutes = workingMinutes.value
  if (minutes <= 0) return '0h 0m'

  const hours = Math.floor(minutes / 60)
  const remainingMinutes = minutes % 60

  return `${hours}h ${remainingMinutes}m`
})

const circumference = 2 * Math.PI * 42

const progressDashOffset = computed(() => {
  return circumference - (workingTimePercent.value / 100) * circumference
})

const attendanceRequirementText = computed(() => {
  if (!today.value) return ''

  const requirements: string[] = []

  if (today.value.requires_face_verification || today.value.requires_photo) {
    requirements.push('verifikasi wajah')
  }

  if (today.value.requires_location) {
    requirements.push('lokasi')
  }

  if (requirements.length === 0) return ''

  if (requirements.length === 1) {
    return `Kantor mewajibkan ${requirements[0]} saat absen`
  }

  return `Kantor mewajibkan ${requirements.join(' & ')} saat absen`
})

const showQrScanModal = ref(false)
const qrScanMode = ref<'clock-in' | 'clock-out'>('clock-in')

function openQrScan(mode: 'clock-in' | 'clock-out') {
  qrScanMode.value = mode
  showQrScanModal.value = true
}

function handleQrScanSuccess() {
  showQrScanModal.value = false
  loadToday()
}

const showFaceModal = ref(false)
const faceModalType = ref<'clock-in' | 'clock-out'>('clock-in')

function openFaceModal(type: 'clock-in' | 'clock-out') {
  faceModalType.value = type
  showFaceModal.value = true
}

function handleFaceAttendanceSuccess() {
  showFaceModal.value = false
  loadToday()
}

async function loadToday() {
  loading.value = true
  errorMessage.value = ''

  try {
    const response = await apiClient.get('/api/attendance/today')
    today.value = response.data.data
  } catch {
    errorMessage.value = 'Gagal memuat status attendance hari ini.'
  } finally {
    loading.value = false
  }
}

async function handleClockIn() {
  if (today.value?.requires_photo || today.value?.requires_face_verification) {
    openFaceModal('clock-in')
    return
  }

  submitting.value = true
  errorMessage.value = ''

  try {
    await apiClient.post('/api/attendance/clock-in')
    await loadToday()
  } catch (err: any) {
    errorMessage.value =
      err.response?.data?.message || 'Gagal melakukan clock-in.'
  } finally {
    submitting.value = false
  }
}

async function handleClockOut() {
  if (today.value?.requires_photo || today.value?.requires_face_verification) {
    openFaceModal('clock-out')
    return
  }

  submitting.value = true
  errorMessage.value = ''

  try {
    await apiClient.post('/api/attendance/clock-out')
    await loadToday()
  } catch (err: any) {
    errorMessage.value =
      err.response?.data?.message || 'Gagal melakukan clock-out.'
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  loadToday()

  clockTimer = setInterval(() => {
    currentTime.value = new Date()
  }, 1000)
})

onUnmounted(() => {
  if (clockTimer) clearInterval(clockTimer)
})
</script>

<template>
  <div>
    <!-- =========================================================
         DESKTOP — layout existing, UI tidak diubah.
         ========================================================= -->
    <div
      class="hidden rounded-2xl border border-slate-100 bg-white p-6 shadow-[0_1px_3px_rgba(15,23,42,0.04)] lg:block"
    >
      <div v-if="loading" class="text-sm text-slate-400">
        Memuat status attendance...
      </div>

      <template v-else-if="today">
        <div class="flex items-start justify-between">
          <div>
            <p
              class="text-xs font-medium uppercase tracking-wider text-slate-400"
            >
              Attendance Hari Ini
            </p>
            <p
              class="mt-1 text-3xl font-semibold tabular-nums tracking-tight text-slate-900"
            >
              {{ formattedClock }}
            </p>
            <p class="mt-1 text-sm text-slate-500">
              {{ formatDate(today.attendance_date) }}
            </p>
          </div>

          <span
            v-if="today.status"
            class="rounded-full bg-primary-soft px-3 py-1 text-xs font-medium text-primary-dark"
          >
            {{ statusLabels[today.status] ?? today.status }}
          </span>
        </div>

        <div
          v-if="today.shift"
          class="mt-4 flex items-center gap-2 rounded-xl bg-slate-50/60 px-4 py-2.5 text-sm text-slate-500"
        >
          <Clock class="h-4 w-4 text-slate-400" :stroke-width="1.75" />
          Shift {{ today.shift.name }} · {{ today.shift.start_time }} -
          {{ today.shift.end_time }}
        </div>

        <div class="mt-5 grid grid-cols-2 gap-3">
          <div class="rounded-xl border border-slate-100 px-4 py-3">
            <p class="text-xs text-slate-400">Clock In</p>
            <p class="mt-0.5 text-lg font-medium text-slate-800">
              {{ formatTime(today.clock_in) }}
            </p>
          </div>

          <div class="rounded-xl border border-slate-100 px-4 py-3">
            <p class="text-xs text-slate-400">Clock Out</p>
            <p class="mt-0.5 text-lg font-medium text-slate-800">
              {{ formatTime(today.clock_out) }}
            </p>
          </div>
        </div>

        <button
          v-if="today.can_clock_in"
          @click="openQrScan('clock-in')"
          class="mt-2 flex w-full items-center justify-center gap-1.5 text-xs font-medium text-slate-400 hover:text-slate-600"
        >
          <QrCodeIcon class="h-3.5 w-3.5" :stroke-width="1.75" />
          atau scan QR kantor
        </button>

        <button
          v-if="today.can_clock_out"
          @click="openQrScan('clock-out')"
          class="mt-2 flex w-full items-center justify-center gap-1.5 text-xs font-medium text-slate-400 hover:text-slate-600"
        >
          <QrCodeIcon class="h-3.5 w-3.5" :stroke-width="1.75" />
          atau scan QR kantor
        </button>

        <p v-if="errorMessage" class="mt-3 text-sm text-red-600">
          {{ errorMessage }}
        </p>

        <div class="mt-5 flex gap-3">
          <button
            v-if="today.can_clock_in"
            @click="handleClockIn"
            :disabled="submitting"
            class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-primary py-3 text-sm font-medium text-white transition-colors hover:bg-primary-dark disabled:opacity-50"
          >
            <LogIn class="h-4 w-4" :stroke-width="2" />
            {{ submitting ? 'Memproses...' : 'Clock In' }}
          </button>

          <button
            v-if="today.can_clock_out"
            @click="handleClockOut"
            :disabled="submitting"
            class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-slate-800 py-3 text-sm font-medium text-white transition-colors hover:bg-slate-900 disabled:opacity-50"
          >
            <LogOut class="h-4 w-4" :stroke-width="2" />
            {{ submitting ? 'Memproses...' : 'Clock Out' }}
          </button>

          <p
            v-if="!today.can_clock_in && !today.can_clock_out"
            class="flex flex-1 items-center justify-center rounded-xl bg-slate-50 py-3 text-sm text-slate-400"
          >
            Attendance hari ini sudah selesai
          </p>
        </div>
      </template>
    </div>

    <!-- =========================================================
         MOBILE — app-like white card inspired by the supplied
         mobile reference. UI only; existing attendance logic stays.
         ========================================================= -->
    <div
      class="lg:hidden overflow-hidden rounded-[28px] border border-slate-100 bg-white shadow-[0_8px_28px_rgba(15,23,42,0.05)]"
    >
      <div v-if="loading" class="px-5 py-12 text-center text-sm text-slate-400">
        Memuat status attendance...
      </div>

      <template v-else-if="today">
        <div class="px-5 pb-5 pt-6 sm:px-6 sm:pb-6">
          <div class="flex items-center justify-between gap-3">
            <p class="text-[12px] font-semibold uppercase tracking-[0.15em] text-slate-400">
              Attendance Hari Ini
            </p>

            <button
              type="button"
              class="shrink-0 text-[13px] font-semibold text-primary-dark"
              @click="router.push('/my-attendances')"
            >
              Lihat detail ›
            </button>
          </div>

          <div class="mt-7">
            <p class="text-[41px] font-semibold leading-none tracking-[-0.035em] text-slate-950 tabular-nums">
              {{ formattedClock }}
            </p>

            <p class="mt-2.5 text-[15px] text-slate-400">
              {{ formatDate(today.attendance_date) }}
            </p>
          </div>

          <div class="mt-7 space-y-4">
            <div class="flex items-center gap-3">
              <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-slate-300">
                <LogIn class="h-5 w-5" :stroke-width="1.7" />
              </div>
              <span class="text-[16px] text-slate-500">Clock In</span>
              <span class="ml-auto text-[16px] font-medium tabular-nums text-slate-700">
                {{ formatTime(today.clock_in) }}
              </span>
            </div>

            <div class="flex items-center gap-3">
              <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-slate-300">
                <LogOut class="h-5 w-5" :stroke-width="1.7" />
              </div>
              <span class="text-[16px] text-slate-500">Clock Out</span>
              <span class="ml-auto text-[16px] font-medium tabular-nums text-slate-700">
                {{ formatTime(today.clock_out) }}
              </span>
            </div>

            <!-- Working Hours: horizontal progress bar -->
            <div>
              <div class="flex items-center gap-3">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-slate-300">
                  <Clock class="h-5 w-5" :stroke-width="1.7" />
                </div>

                <span class="text-[16px] text-slate-500">Working Hours</span>

                <span class="ml-auto text-[16px] font-semibold tabular-nums text-slate-800">
                  {{ workingTimeLabel }}
                </span>
              </div>

              <div class="ml-11 mt-3">
                <div class="mb-1.5 flex items-center justify-between gap-3">
                  <span class="text-[11px] font-medium text-slate-400">
                    Today's Working Time
                  </span>
                  <span class="text-[11px] font-semibold tabular-nums text-primary-dark">
                    {{ workingTimePercent }}%
                  </span>
                </div>

                <div
                  class="h-2.5 w-full overflow-hidden rounded-full bg-slate-100"
                  role="progressbar"
                  :aria-valuenow="workingTimePercent"
                  aria-valuemin="0"
                  aria-valuemax="100"
                >
                  <div
                    class="h-full rounded-full bg-primary transition-[width] duration-500"
                    :style="{ width: `${workingTimePercent}%` }"
                  ></div>
                </div>
              </div>
            </div>

            <div class="flex items-center gap-3">
              <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-slate-300">
                <CircleDot class="h-5 w-5" :stroke-width="1.7" />
              </div>

              <span class="text-[16px] text-slate-500">Status</span>

              <span
                class="ml-auto text-[16px] font-medium"
                :class="today.status ? 'text-slate-700' : 'text-slate-400'"
              >
                {{ today.status ? (statusLabels[today.status] ?? today.status) : '-' }}
              </span>
            </div>

            <!-- Shift stays visible -->
            <div
              v-if="today.shift"
              class="mt-1 rounded-2xl bg-slate-50 px-4 py-3.5"
            >
              <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white text-slate-400 ring-1 ring-slate-100">
                  <CalendarClock class="h-5 w-5" :stroke-width="1.7" />
                </div>

                <div class="min-w-0 flex-1">
                  <p class="text-[11px] font-semibold uppercase tracking-[0.09em] text-slate-400">
                    Shift
                  </p>

                  <div class="mt-1 flex items-center justify-between gap-3">
                    <p class="truncate text-[16px] font-semibold text-slate-800">
                      {{ today.shift.name }}
                    </p>

                    <p class="shrink-0 text-[13px] font-medium tabular-nums text-slate-500">
                      {{ today.shift.start_time }} - {{ today.shift.end_time }}
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div
            v-if="attendanceRequirementText"
            class="mt-5 flex items-center gap-3 rounded-2xl bg-slate-50 px-4 py-3.5"
          >
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-primary-dark">
              <ShieldCheck class="h-5 w-5" :stroke-width="1.7" />
            </div>

            <p class="text-[13px] leading-5 text-slate-500">
              {{ attendanceRequirementText }}
            </p>
          </div>

          <p
            v-if="errorMessage"
            class="mt-4 rounded-xl bg-red-50 px-3 py-2 text-[13px] leading-5 text-red-600"
          >
            {{ errorMessage }}
          </p>

          <div class="mt-5">
            <button
              v-if="today.can_clock_in"
              @click="handleClockIn"
              :disabled="submitting"
              class="flex min-h-[62px] w-full items-center justify-center gap-3 rounded-[18px] bg-primary px-5 text-[18px] font-semibold text-white shadow-sm transition hover:bg-primary-dark disabled:cursor-not-allowed disabled:opacity-50"
            >
              <LogIn class="h-6 w-6" :stroke-width="2" />
              {{ submitting ? 'Memproses...' : 'Clock In' }}
            </button>

            <button
              v-else-if="today.can_clock_out"
              @click="handleClockOut"
              :disabled="submitting"
              class="flex min-h-[62px] w-full items-center justify-center gap-3 rounded-[18px] bg-primary px-5 text-[18px] font-semibold text-white shadow-sm transition hover:bg-primary-dark disabled:cursor-not-allowed disabled:opacity-50"
            >
              <LogOut class="h-6 w-6" :stroke-width="2" />
              {{ submitting ? 'Memproses...' : 'Clock Out' }}
            </button>

            <div
              v-else
              class="flex min-h-[62px] w-full items-center justify-center rounded-[18px] bg-slate-50 px-5 text-center text-[14px] text-slate-400"
            >
              Attendance hari ini sudah selesai
            </div>
          </div>

          <button
            v-if="today.can_clock_in"
            @click="openQrScan('clock-in')"
            class="mt-3 flex w-full items-center justify-center gap-2 py-1 text-[12px] font-medium text-slate-400 transition hover:text-slate-600"
          >
            <QrCodeIcon class="h-4 w-4" :stroke-width="1.75" />
            atau scan QR kantor
          </button>

          <button
            v-if="today.can_clock_out"
            @click="openQrScan('clock-out')"
            class="mt-3 flex w-full items-center justify-center gap-2 py-1 text-[12px] font-medium text-slate-400 transition hover:text-slate-600"
          >
            <QrCodeIcon class="h-4 w-4" :stroke-width="1.75" />
            atau scan QR kantor
          </button>
        </div>
      </template>
    </div>

    <!-- Shared modals -->
    <OfficeQrScanModal
      v-if="showQrScanModal"
      :mode="qrScanMode"
      @close="showQrScanModal = false"
      @success="handleQrScanSuccess"
    />

    <FaceAttendanceModal
      v-if="showFaceModal && today"
      :type="faceModalType"
      :requires-face-verification="today.requires_face_verification"
      :requires-location="today.requires_location"
      @close="showFaceModal = false"
      @success="handleFaceAttendanceSuccess"
    />
  </div>
</template>
