<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { LogIn, LogOut, Clock, MapPin } from 'lucide-vue-next'
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
  clock_out: string | null
  clock_out_distance_meters: number | null
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
</script>

<template>
  <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
    <div v-if="loading" class="text-sm text-slate-400">Memuat status attendance...</div>

    <template v-else-if="today">
      <div class="flex items-start justify-between">
        <div>
          <p class="text-xs font-medium uppercase tracking-wider text-slate-400">Attendance Hari Ini</p>
          <p class="mt-1 text-3xl font-semibold tabular-nums tracking-tight text-slate-900">{{ formattedClock }}</p>
          <p class="mt-1 text-sm text-slate-500">
            {{ new Date(today.attendance_date).toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) }}
          </p>
        </div>
        <span
          v-if="today.status"
          class="rounded-full bg-primary-soft px-3 py-1 text-xs font-medium text-primary-dark"
        >
          {{ statusLabels[today.status] ?? today.status }}
        </span>
      </div>

      <div v-if="today.shift" class="mt-4 flex items-center gap-2 rounded-xl bg-slate-50/60 px-4 py-2.5 text-sm text-slate-500">
        <Clock class="h-4 w-4 text-slate-400" :stroke-width="1.75" />
        Shift {{ today.shift.name }} · {{ today.shift.start_time }} - {{ today.shift.end_time }}
      </div>

      <div class="mt-5 grid grid-cols-2 gap-3">
        <div class="rounded-xl border border-slate-100 px-4 py-3">
          <p class="text-xs text-slate-400">Clock In</p>
          <p class="mt-0.5 text-lg font-medium text-slate-800">{{ formatTime(today.clock_in) }}</p>
          <p v-if="today.clock_in_distance_meters !== null" class="mt-0.5 flex items-center gap-1 text-xs text-slate-400">
            <MapPin class="h-3 w-3" :stroke-width="1.75" />
            {{ today.clock_in_distance_meters }}m dari kantor
          </p>
        </div>
        <div class="rounded-xl border border-slate-100 px-4 py-3">
          <p class="text-xs text-slate-400">Clock Out</p>
          <p class="mt-0.5 text-lg font-medium text-slate-800">{{ formatTime(today.clock_out) }}</p>
          <p v-if="today.clock_out_distance_meters !== null" class="mt-0.5 flex items-center gap-1 text-xs text-slate-400">
            <MapPin class="h-3 w-3" :stroke-width="1.75" />
            {{ today.clock_out_distance_meters }}m dari kantor
          </p>
        </div>
      </div>

      <p v-if="locationNote" class="mt-3 text-xs text-amber-600">{{ locationNote }}</p>
      <p v-if="errorMessage" class="mt-3 text-sm text-red-600">{{ errorMessage }}</p>

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
</template>