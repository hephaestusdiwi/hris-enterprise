<script setup lang="ts">
import { ref, onMounted, computed, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import {
  LogIn, LogOut, MapPin, Camera, X, RotateCcw, Check,
  Clock, CircleDot, CalendarClock, ShieldCheck,
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
  requires_photo: boolean
  requires_face_verification: boolean
  clock_in: string | null
  clock_in_distance_meters: number | null
  late_minutes: number | null
  within_grace: boolean | null
  clock_out: string | null
  clock_out_distance_meters: number | null
  detected_overtime_minutes: number | null
  can_clock_in: boolean
  can_clock_out: boolean
  shift: ShiftInfo | null
}

const router = useRouter()

const today = ref<TodayAttendance | null>(null)
const loading = ref(true)
const submitting = ref(false)
const errorMessage = ref('')
const locationNote = ref('')

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
setInterval(() => {
  currentTime.value = new Date()
}, 1000)

const formattedClock = computed(() =>
  currentTime.value.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }),
)

function formatTime(value: string | null): string {
  if (!value) return '-'
  return new Date(value.replace(' ', 'T')).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
}

function toMinutes(t: string): number {
  const [h, m] = t.split(':').map(Number)
  return h * 60 + m
}

const workingMinutes = computed(() => {
  if (!today.value?.clock_in) return 0
  const start = new Date(today.value.clock_in.replace(' ', 'T'))
  const end = today.value.clock_out ? new Date(today.value.clock_out.replace(' ', 'T')) : currentTime.value
  return Math.max(0, Math.round((end.getTime() - start.getTime()) / 60000))
})

const workingHoursLabel = computed(() => {
  const mins = workingMinutes.value
  if (mins <= 0) return '-'
  const h = Math.floor(mins / 60)
  const m = mins % 60
  return m ? `${h}j ${m}m` : `${h}j`
})

const shiftDurationMinutes = computed(() => {
  if (!today.value?.shift) return 0
  let dur = toMinutes(today.value.shift.end_time) - toMinutes(today.value.shift.start_time)
  if (dur <= 0) dur += 24 * 60
  return dur
})

function formatShiftTime(time: string): string {
  return time.slice(0, 5)
}

const ringPercent = computed(() => {
  if (!shiftDurationMinutes.value) return 0
  return Math.min(100, Math.round((workingMinutes.value / shiftDurationMinutes.value) * 100))
})

const ringCircumference = 2 * Math.PI * 44
const ringDashOffset = computed(() => ringCircumference * (1 - ringPercent.value / 100))

function getCurrentPosition(): Promise<GeolocationPosition> {
  return new Promise((resolve, reject) => {
    if (!navigator.geolocation) {
      reject(new Error('Browser tidak mendukung GPS.'))
      return
    }
    navigator.geolocation.getCurrentPosition(resolve, reject, { enableHighAccuracy: true, timeout: 10000 })
  })
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

async function resolveCoords(): Promise<{ latitude?: number; longitude?: number }> {
  locationNote.value = ''
  try {
    const position = await getCurrentPosition()
    return { latitude: position.coords.latitude, longitude: position.coords.longitude }
  } catch {
    locationNote.value = 'Tidak bisa mengambil lokasi GPS. Jika kantor mewajibkan verifikasi lokasi, absen akan ditolak.'
    return {}
  }
}

// ---------- Kamera capture ----------
const showCamera = ref(false)
const cameraMode = ref<'clock-in' | 'clock-out'>('clock-in')
const capturedPhoto = ref('')
const videoRef = ref<HTMLVideoElement | null>(null)
const canvasRef = ref<HTMLCanvasElement | null>(null)
const cameraError = ref('')
let mediaStream: MediaStream | null = null

async function openCamera(mode: 'clock-in' | 'clock-out') {
  cameraMode.value = mode
  capturedPhoto.value = ''
  cameraError.value = ''
  showCamera.value = true

  await nextTick()

  try {
    mediaStream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
    })
    if (videoRef.value) {
      videoRef.value.srcObject = mediaStream
      await videoRef.value.play()
    }
  } catch {
    cameraError.value = 'Tidak bisa mengakses kamera. Pastikan izin kamera browser diaktifkan.'
  }
}

function stopCamera() {
  mediaStream?.getTracks().forEach((track) => track.stop())
  mediaStream = null
}

function closeCamera() {
  stopCamera()
  showCamera.value = false
  capturedPhoto.value = ''
}

function capturePhoto() {
  if (!videoRef.value || !canvasRef.value) return
  const video = videoRef.value
  const canvas = canvasRef.value
  canvas.width = video.videoWidth
  canvas.height = video.videoHeight
  const ctx = canvas.getContext('2d')
  if (!ctx) return
  ctx.translate(canvas.width, 0)
  ctx.scale(-1, 1)
  ctx.drawImage(video, 0, 0, canvas.width, canvas.height)
  capturedPhoto.value = canvas.toDataURL('image/jpeg', 0.85)
  stopCamera()
}

function retakePhoto() {
  capturedPhoto.value = ''
  openCamera(cameraMode.value)
}

async function confirmPhotoAndSubmit() {
  showCamera.value = false
  if (cameraMode.value === 'clock-in') {
    await performClockIn(capturedPhoto.value)
  } else {
    await performClockOut(capturedPhoto.value)
  }
}

// ---------- Clock in/out ----------
async function handleClockIn() {
  if (today.value?.requires_face_verification) {
    router.push({ name: 'attendance.face-checkin' })
    return
  }

  if (today.value?.requires_photo) {
    openCamera('clock-in')
    return
  }
  await performClockIn()
}

async function handleClockOut() {
  if (today.value?.requires_face_verification) {
    router.push({ name: 'attendance.face-checkin' })
    return
  }

  if (today.value?.requires_photo) {
    openCamera('clock-out')
    return
  }
  await performClockOut()
}

async function performClockIn(photoBase64?: string) {
  submitting.value = true
  errorMessage.value = ''
  try {
    const coords = await resolveCoords()
    await apiClient.post('/api/attendance/clock-in', { ...coords, photo_base64: photoBase64 || undefined })
    await loadToday()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Gagal melakukan clock-in.'
  } finally {
    submitting.value = false
  }
}

async function performClockOut(photoBase64?: string) {
  submitting.value = true
  errorMessage.value = ''
  try {
    const coords = await resolveCoords()
    await apiClient.post('/api/attendance/clock-out', { ...coords, photo_base64: photoBase64 || undefined })
    await loadToday()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Gagal melakukan clock-out.'
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  loadToday()
})

defineExpose({ loadToday })
</script>

<template>
  <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
    <div v-if="loading" class="text-sm text-slate-400">Memuat status attendance...</div>

    <template v-else-if="today">
      <div class="mb-4 flex items-center justify-between">
        <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Attendance Hari Ini</h3>
        <button type="button" class="text-xs font-medium text-primary-dark hover:underline">Lihat detail ›</button>
      </div>

      <div class="flex items-center gap-4">
        <div class="flex-1">
          <p class="text-2xl font-bold tabular-nums tracking-tight text-slate-900 sm:text-3xl">{{ formattedClock }}</p>
          <p class="mt-0.5 text-xs text-slate-400 sm:text-sm">
            {{ new Date(today.attendance_date).toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) }}
          </p>

          <dl class="mt-4 space-y-2.5 text-xs sm:text-sm">
            <div class="flex items-center justify-between gap-4">
              <dt class="flex items-center gap-2 text-slate-500">
                <LogIn class="h-4 w-4 text-slate-300" :stroke-width="1.75" />
                Clock In
              </dt>
              <dd class="font-medium text-slate-700">{{ formatTime(today.clock_in) }}</dd>
            </div>

            <div class="flex items-center justify-between gap-4">
              <dt class="flex items-center gap-2 text-slate-500">
                <LogOut class="h-4 w-4 text-slate-300" :stroke-width="1.75" />
                Clock Out
              </dt>
              <dd class="font-medium text-slate-700">{{ formatTime(today.clock_out) }}</dd>
            </div>

            <div class="flex items-center justify-between gap-4">
              <dt class="flex items-center gap-2 text-slate-500">
                <Clock class="h-4 w-4 text-slate-300" :stroke-width="1.75" />
                Working Hours
              </dt>
              <dd class="font-medium text-slate-700">{{ workingHoursLabel }}</dd>
            </div>

            <div class="flex items-center justify-between gap-4">
              <dt class="flex items-center gap-2 text-slate-500">
                <CircleDot class="h-4 w-4 text-slate-300" :stroke-width="1.75" />
                Status
              </dt>
              <dd class="font-medium text-primary-dark">{{ today.status ? (statusLabels[today.status] ?? today.status) : '-' }}</dd>
            </div>

            <div v-if="today.shift" class="flex items-center justify-between gap-4">
              <dt class="flex items-center gap-2 text-slate-500">
                <CalendarClock class="h-4 w-4 text-slate-300" :stroke-width="1.75" />
                Shift
              </dt>
              <dd class="text-right font-medium text-slate-700">
                {{ today.shift.name }} ({{ formatShiftTime(today.shift.start_time) }} - {{ formatShiftTime(today.shift.end_time) }})
              </dd>
            </div>
          </dl>
        </div>

        <div v-if="today.shift" class="flex shrink-0 flex-col items-center">
          <div class="relative flex h-24 w-24 items-center justify-center sm:h-28 sm:w-28">
            <svg viewBox="0 0 100 100" class="h-24 w-24 -rotate-90 sm:h-28 sm:w-28">
              <circle cx="50" cy="50" r="44" fill="none" stroke="#E2E8F0" stroke-width="9" />
              <circle
                cx="50" cy="50" r="44" fill="none" stroke-width="9" stroke-linecap="round"
                class="stroke-primary transition-[stroke-dashoffset] duration-500"
                :stroke-dasharray="ringCircumference"
                :stroke-dashoffset="ringDashOffset"
              />
            </svg>
            <span class="absolute text-base font-bold text-slate-900 sm:text-lg">{{ ringPercent }}%</span>
          </div>
          <p class="mt-1.5 text-center text-[11px] text-slate-400 sm:text-xs">Today's Working Time</p>
        </div>
      </div>

      <!-- Notice: photo / face verification requirement -->
      <div
        v-if="today.requires_face_verification || today.requires_photo"
        class="mt-4 flex items-center gap-2.5 rounded-xl bg-slate-50 px-4 py-2.5"
      >
        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary-soft text-primary-dark">
          <ShieldCheck class="h-4 w-4" :stroke-width="1.75" />
        </div>
        <p class="text-xs text-slate-500 sm:text-sm">
          {{ today.requires_face_verification ? 'Kantor mewajibkan verifikasi wajah saat absen' : 'Kantor mewajibkan foto saat absen' }}
        </p>
      </div>

      <p v-if="today.late_minutes !== null" class="mt-2 text-xs" :class="today.within_grace ? 'text-slate-400' : 'text-amber-600'">
        Terlambat {{ today.late_minutes }} menit{{ today.within_grace ? ' (masih grace period)' : '' }}
      </p>
      <p v-if="today.detected_overtime_minutes" class="mt-1 text-xs text-blue-600">
        Lembur terdeteksi {{ today.detected_overtime_minutes }} menit (menunggu approval)
      </p>
      <p v-if="today.clock_in_distance_meters !== null" class="mt-1 flex items-center gap-1 text-xs text-slate-400">
        <MapPin class="h-3 w-3" :stroke-width="1.75" />
        Clock-in {{ today.clock_in_distance_meters }}m dari kantor
      </p>

      <p v-if="locationNote" class="mt-2 text-xs text-amber-600">{{ locationNote }}</p>
      <p v-if="errorMessage" class="mt-2 text-xs text-red-600">{{ errorMessage }}</p>

      <div class="mt-4 flex gap-3">
        <button
          v-if="today.can_clock_in"
          @click="handleClockIn"
          :disabled="submitting"
          class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-primary py-4 text-sm font-semibold text-white transition-colors hover:bg-primary-dark disabled:opacity-50 sm:text-base"
        >
          <LogIn class="h-5 w-5" :stroke-width="2" />
          {{ submitting ? 'Memproses...' : 'Clock In' }}
        </button>
        <button
          v-if="today.can_clock_out"
          @click="handleClockOut"
          :disabled="submitting"
          class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-slate-800 py-4 text-sm font-semibold text-white transition-colors hover:bg-slate-900 disabled:opacity-50 sm:text-base"
        >
          <LogOut class="h-5 w-5" :stroke-width="2" />
          {{ submitting ? 'Memproses...' : 'Clock Out' }}
        </button>
        <p
          v-if="!today.can_clock_in && !today.can_clock_out"
          class="flex flex-1 items-center justify-center rounded-xl bg-slate-50 py-4 text-sm text-slate-400"
        >
          Attendance hari ini sudah selesai
        </p>
      </div>
    </template>

    <!-- Modal kamera -->
    <Teleport to="body">
      <div v-if="showCamera" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 px-4">
        <div class="w-full max-w-sm rounded-2xl bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3.5">
            <h3 class="text-sm font-semibold text-slate-900">
              {{ cameraMode === 'clock-in' ? 'Foto Clock In' : 'Foto Clock Out' }}
            </h3>
            <button @click="closeCamera" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50"><X class="h-4 w-4" /></button>
          </div>

          <div class="p-5">
            <p v-if="cameraError" class="mb-3 text-xs text-red-600">{{ cameraError }}</p>

            <div class="relative aspect-square w-full overflow-hidden rounded-xl bg-slate-900">
              <video v-show="!capturedPhoto" ref="videoRef" class="h-full w-full -scale-x-100 object-cover" muted playsinline></video>
              <img v-if="capturedPhoto" :src="capturedPhoto" alt="" class="h-full w-full object-cover" />
              <canvas ref="canvasRef" class="hidden"></canvas>
            </div>

            <div class="mt-4 flex gap-2">
              <template v-if="!capturedPhoto">
                <button
                  @click="capturePhoto"
                  :disabled="!!cameraError"
                  class="flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-primary py-2.5 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50"
                >
                  <Camera class="h-4 w-4" :stroke-width="1.75" />
                  Ambil Foto
                </button>
              </template>
              <template v-else>
                <button
                  @click="retakePhoto"
                  class="flex flex-1 items-center justify-center gap-1.5 rounded-xl border border-slate-200 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50"
                >
                  <RotateCcw class="h-4 w-4" :stroke-width="1.75" />
                  Ambil Ulang
                </button>
                <button
                  @click="confirmPhotoAndSubmit"
                  :disabled="submitting"
                  class="flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-primary py-2.5 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50"
                >
                  <Check class="h-4 w-4" :stroke-width="1.75" />
                  {{ submitting ? 'Mengirim...' : 'Gunakan Foto' }}
                </button>
              </template>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>