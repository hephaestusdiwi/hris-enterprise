<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { LogIn, LogOut, MapPin } from 'lucide-vue-next'
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

// --- Working hours & ring progress (VISUAL APPROXIMATION di frontend, lihat catatan di chat) ---
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
  if (dur <= 0) dur += 24 * 60 // asumsi shift lintas hari, kasar karena gak ada flag is_overnight di sini
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
    navigator.geolocation.getCurrentPosition(resolve, reject, {
      enableHighAccuracy: true,
      timeout: 10000,
    })
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

async function handleClockIn() {
  submitting.value = true
  errorMessage.value = ''
  try {
    const coords = await resolveCoords()
    await apiClient.post('/api/attendance/clock-in', coords)
    await loadToday()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Gagal melakukan clock-in.'
  } finally {
    submitting.value = false
  }
}

async function handleClockOut() {
  submitting.value = true
  errorMessage.value = ''
  try {
    const coords = await resolveCoords()
    await apiClient.post('/api/attendance/clock-out', coords)
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
        <button type="button" class="text-xs font-medium text-primary-dark hover:underline">View detail ›</button>
      </div>

       <div class="flex items-center gap-4">
        <div class="flex-1">
          <p class="text-2xl font-bold tabular-nums tracking-tight text-slate-900">{{ formattedClock }}</p>
          <p class="mt-0.5 text-xs text-slate-400">
            {{ new Date(today.attendance_date).toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long' }) }}
          </p>

          <dl class="mt-3 grid grid-cols-[auto_1fr] items-baseline gap-x-4 gap-y-1.5 text-xs">
            <dt class="text-slate-400">Clock In</dt>
            <dd class="text-right font-medium text-slate-700">{{ formatTime(today.clock_in) }}</dd>

            <dt class="text-slate-400">Clock Out</dt>
            <dd class="text-right font-medium text-slate-700">{{ formatTime(today.clock_out) }}</dd>

            <dt class="text-slate-400">Working Hours</dt>
            <dd class="text-right font-medium text-slate-700">{{ workingHoursLabel }}</dd>

            <dt class="text-slate-400">Status</dt>
            <dd class="text-right font-medium text-primary-dark">{{ today.status ? (statusLabels[today.status] ?? today.status) : '-' }}</dd>

            <template v-if="today.shift">
              <dt class="text-slate-400">Shift</dt>
              <dd class="text-right font-medium text-slate-700">
  {{ today.shift.name }}
  ({{ formatShiftTime(today.shift.start_time) }} -
   {{ formatShiftTime(today.shift.end_time) }})
</dd>
            </template>
          </dl>
        </div>

        <div v-if="today.shift" class="flex shrink-0 flex-col items-center">
          <div class="relative flex h-24 w-24 items-center justify-center">
            <svg viewBox="0 0 100 100" class="h-24 w-24 -rotate-90">
              <circle cx="50" cy="50" r="44" fill="none" stroke="#E2E8F0" stroke-width="9" />
              <circle
                cx="50" cy="50" r="44" fill="none" stroke-width="9" stroke-linecap="round"
                class="stroke-primary transition-[stroke-dashoffset] duration-500"
                :stroke-dasharray="ringCircumference"
                :stroke-dashoffset="ringDashOffset"
              />
            </svg>
            <span class="absolute text-base font-bold text-slate-900">{{ ringPercent }}%</span>
          </div>
          <p class="mt-1.5 text-[11px] text-slate-400">Today's Working Time</p>
        </div>
      </div>

      <p v-if="today.late_minutes !== null" class="mt-3 text-xs" :class="today.within_grace ? 'text-slate-400' : 'text-amber-600'">
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

      <div class="mt-4 flex gap-2">
        <button
          v-if="today.can_clock_in"
          @click="handleClockIn"
          :disabled="submitting"
          class="flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-primary py-2.5 text-xs font-medium text-white transition-colors hover:bg-primary-dark disabled:opacity-50"
        >
          <LogIn class="h-3.5 w-3.5" :stroke-width="2" />
          {{ submitting ? 'Memproses...' : 'Clock In' }}
        </button>
        <button
          v-if="today.can_clock_out"
          @click="handleClockOut"
          :disabled="submitting"
          class="flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-slate-800 py-2.5 text-xs font-medium text-white transition-colors hover:bg-slate-900 disabled:opacity-50"
        >
          <LogOut class="h-3.5 w-3.5" :stroke-width="2" />
          {{ submitting ? 'Memproses...' : 'Clock Out' }}
        </button>
        <p
          v-if="!today.can_clock_in && !today.can_clock_out"
          class="flex flex-1 items-center justify-center rounded-xl bg-slate-50 py-2.5 text-xs text-slate-400"
        >
          Attendance hari ini sudah selesai
        </p>
      </div>
    </template>
  </div>
</template>