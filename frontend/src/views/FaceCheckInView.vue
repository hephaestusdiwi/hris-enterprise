<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Check, RotateCcw, AlertTriangle, LogIn, LogOut, ArrowLeft, Clock, ScanFace } from 'lucide-vue-next'
import apiClient from '@/lib/axios'
import { useFaceAttendance, type AttendanceType } from '@/composables/useFaceAttendance'

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

// --- Page-level stage: 'loading' -> 'choose' -> delegate to composable stage ---
type PageStage = 'loading' | 'choose' | 'action'
const pageStage = ref<PageStage>('loading')
const loadError = ref('')

const today = ref<TodayAttendance | null>(null)
const activeType = ref<AttendanceType>('clock-in')

const currentTime = ref(new Date())
setInterval(() => {
  currentTime.value = new Date()
}, 1000)

const formattedClock = computed(() =>
  currentTime.value.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }),
)

const {
  stage,
  submitError,
  resultData,
  capturedImage,
  videoRef,
  canvasRef,
  startCamera,
  stopCamera,
  verificationLabel,
  resultTime,
  resultDistance,
  formatTime,
  statusLabel,
  handleStart,
  retry,
  reset,
  livenessLabel,
  livenessPassed,
  livenessLoading,
  livenessError,
  canCapture,
} = useFaceAttendance(
  activeType,
  computed(() => today.value?.requires_face_verification ?? false),
  computed(() => today.value?.requires_location ?? false),
)

const badgeText = computed(() => {
  if (!today.value?.requires_face_verification) {
    return `${activeType.value === 'clock-in' ? 'Clock In' : 'Clock Out'} · ${verificationLabel.value}`
  }
  if (livenessLoading.value) return 'Menyiapkan deteksi wajah...'
  if (livenessError.value) return 'Liveness check dilewati'
  if (livenessPassed.value) return 'Liveness terverifikasi'
  return livenessLabel.value
})

async function loadToday() {
  pageStage.value = 'loading'
  loadError.value = ''
  try {
    const response = await apiClient.get('/api/attendance/today')
    today.value = response.data.data
    pageStage.value = 'choose'
  } catch {
    loadError.value = 'Gagal memuat status attendance hari ini.'
    pageStage.value = 'choose'
  }
}

function startFlow(type: AttendanceType) {
  activeType.value = type
  reset()
  pageStage.value = 'action'
  startCamera()
}

function cancelFlow() {
  stopCamera()
  pageStage.value = 'choose'
}

async function handleDone() {
  stopCamera()
  await loadToday()
}

function backToDashboard() {
  stopCamera()
  router.push({ name: 'dashboard' })
}

onMounted(() => {
  loadToday()
})
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-10 md:py-16">
    <div class="w-full max-w-md sm:max-w-lg md:max-w-xl">

      <button
        @click="backToDashboard"
        class="mb-4 flex items-center gap-1.5 text-sm font-medium text-slate-400 hover:text-slate-600 md:mb-6 md:text-base"
      >
        <ArrowLeft class="h-4 w-4 md:h-5 md:w-5" :stroke-width="1.75" />
        Kembali ke Dashboard
      </button>

      <div class="overflow-hidden rounded-2xl bg-white shadow-[0_1px_3px_rgba(15,23,42,0.06)] md:rounded-3xl md:shadow-[0_4px_20px_rgba(15,23,42,0.08)]">

        <!-- Header -->
        <div class="flex items-center gap-3 border-b border-slate-100 px-6 py-5 md:gap-4 md:px-8 md:py-6">
          <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-primary-soft text-primary-dark md:h-12 md:w-12 md:rounded-2xl">
            <ScanFace class="h-6 w-6 md:h-7 md:w-7" :stroke-width="1.75" />
          </div>
          <div>
            <p class="text-base font-semibold text-slate-900 md:text-lg">Absen Face Recognition</p>
            <p class="text-sm text-slate-400 md:text-base">Alternatif metode absensi</p>
          </div>
        </div>

        <!-- Loading -->
        <div v-if="pageStage === 'loading'" class="p-10 text-center text-sm text-slate-400 md:p-14 md:text-base">
          Memuat status attendance...
        </div>

        <!-- Choose: pick Clock In / Clock Out -->
        <div v-else-if="pageStage === 'choose'" class="space-y-6 p-6 md:space-y-8 md:p-8">
          <div class="text-center">
            <p class="text-4xl font-semibold tabular-nums tracking-tight text-slate-900 md:text-5xl">{{ formattedClock }}</p>
            <p class="mt-1.5 text-sm text-slate-500 md:text-base">
              {{ today ? new Date(today.attendance_date).toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) : '' }}
            </p>
          </div>

          <div v-if="today?.shift" class="flex items-center justify-center gap-2 rounded-xl bg-slate-50/60 px-4 py-3 text-sm text-slate-500 md:px-5 md:py-3.5 md:text-base">
            <Clock class="h-4 w-4 text-slate-400 md:h-5 md:w-5" :stroke-width="1.75" />
            Shift {{ today.shift.name }} · {{ today.shift.start_time }} - {{ today.shift.end_time }}
          </div>

          <p v-if="loadError" class="text-center text-sm text-red-600 md:text-base">{{ loadError }}</p>

          <div class="space-y-3 md:space-y-4">
            <button
              v-if="today?.can_clock_in"
              @click="startFlow('clock-in')"
              class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary py-4 text-sm font-medium text-white transition-colors hover:bg-primary-dark md:py-5 md:text-base"
            >
              <LogIn class="h-4 w-4 md:h-5 md:w-5" :stroke-width="2" />
              Clock In dengan Face Recognition
            </button>
            <button
              v-if="today?.can_clock_out"
              @click="startFlow('clock-out')"
              class="flex w-full items-center justify-center gap-2 rounded-xl bg-slate-800 py-4 text-sm font-medium text-white transition-colors hover:bg-slate-900 md:py-5 md:text-base"
            >
              <LogOut class="h-4 w-4 md:h-5 md:w-5" :stroke-width="2" />
              Clock Out dengan Face Recognition
            </button>
            <p
              v-if="today && !today.can_clock_in && !today.can_clock_out"
              class="rounded-xl bg-slate-50 py-4 text-center text-sm text-slate-400 md:py-5 md:text-base"
            >
              Attendance hari ini sudah selesai
            </p>
          </div>
        </div>

        <!-- Action: camera / submitting / success / error (delegated to composable) -->
        <div v-else-if="pageStage === 'action'" class="p-2 md:p-8">

          <!-- camera -->
          <div v-if="stage === 'camera'">
            <div class="relative overflow-hidden rounded-xl bg-slate-900 md:rounded-2xl">
              <video ref="videoRef" autoplay playsinline muted class="aspect-[3.5/5] w-full -scale-x-100 object-cover"></video>

              <!-- mode badge / liveness instruction -->
              <div class="pointer-events-none absolute inset-x-0 top-0 flex justify-center pt-4 md:pt-6">
                <span
                  class="flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-medium text-white backdrop-blur-sm md:px-4 md:py-2 md:text-sm"
                  :class="livenessPassed ? 'bg-emerald-500/80' : 'bg-black/40'"
                >
                  <Check v-if="livenessPassed" class="h-3.5 w-3.5" :stroke-width="3" />
                  {{ badgeText }}
                </span>
              </div>

              <div class="pointer-events-none absolute inset-4 md:inset-6">
                <span class="absolute left-0 top-0 h-6 w-6 rounded-tl-lg border-l-2 border-t-2 border-white/80 md:h-8 md:w-8 md:border-[3px]"></span>
                <span class="absolute right-0 top-0 h-6 w-6 rounded-tr-lg border-r-2 border-t-2 border-white/80 md:h-8 md:w-8 md:border-[3px]"></span>
                <span class="absolute bottom-0 left-0 h-6 w-6 rounded-bl-lg border-b-2 border-l-2 border-white/80 md:h-8 md:w-8 md:border-[3px]"></span>
                <span class="absolute bottom-0 right-0 h-6 w-6 rounded-br-lg border-b-2 border-r-2 border-white/80 md:h-8 md:w-8 md:border-[3px]"></span>
              </div>

              <div class="pointer-events-none absolute inset-0 flex items-center justify-center pb-10 md:pb-14">
                <div class="h-60 w-48 rounded-[50%] border-2 border-dashed border-white/70 md:h-72 md:w-56"></div>
              </div>

              <!-- bottom overlay: instruction + controls -->
              <div class="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/75 via-black/35 to-transparent px-5 pb-5 pt-16 md:px-7 md:pb-7 md:pt-24">
                <p class="mb-3 text-center text-xs text-white/90 md:mb-4 md:text-sm">
                  Posisikan wajah di dalam bingkai
                </p>
                <div class="pointer-events-auto flex gap-3 md:gap-4">
                  <button
                    @click="cancelFlow"
                    class="flex-1 rounded-xl bg-white/15 py-3 text-sm font-medium text-white ring-1 ring-inset ring-white/25 backdrop-blur-sm hover:bg-white/25 md:py-3.5 md:text-base"
                  >
                    Cancel
                  </button>
                  <button
                    @click="handleStart"
                    :disabled="!canCapture"
                    class="flex-1 rounded-xl bg-primary py-3 text-sm font-medium text-white transition-colors hover:bg-primary-dark disabled:cursor-not-allowed disabled:bg-white/20 disabled:text-white/50 md:py-3.5 md:text-base"
                  >
                    Start
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- submitting -->
          <div v-else-if="stage === 'submitting'" class="space-y-4 md:space-y-5">
            <div class="relative overflow-hidden rounded-xl bg-slate-900 md:rounded-2xl">
              <img :src="capturedImage" class="aspect-[3/5] w-full -scale-x-100 object-cover opacity-90" alt="Captured" />
              <div class="absolute inset-0 bg-gradient-to-b from-transparent via-emerald-400/10 to-transparent">
                <div class="absolute inset-x-0 h-16 animate-scan bg-gradient-to-b from-transparent via-emerald-400/40 to-transparent md:h-20"></div>
              </div>
            </div>
            <p class="text-center text-sm text-slate-500 md:text-base">Memverifikasi wajah...</p>
          </div>

          <!-- success -->
          <div v-else-if="stage === 'success'" class="space-y-5 md:space-y-6">
            <div class="flex flex-col items-center gap-3 pt-2">
              <div class="relative">
                <img
                  v-if="capturedImage"
                  :src="capturedImage"
                  alt="Foto absen"
                  class="h-24 w-24 -scale-x-100 rounded-full border-4 border-white object-cover shadow-md ring-1 ring-slate-100 md:h-28 md:w-28"
                />
                <div v-else class="flex h-24 w-24 items-center justify-center rounded-full bg-emerald-50 md:h-28 md:w-28">
                  <Check class="h-9 w-9 text-emerald-600" :stroke-width="2.5" />
                </div>
                <span class="absolute -bottom-1 -right-1 flex h-8 w-8 items-center justify-center rounded-full bg-emerald-500 ring-4 ring-white md:h-9 md:w-9">
                  <Check class="h-4 w-4 text-white md:h-5 md:w-5" :stroke-width="3" />
                </span>
              </div>
              <div class="text-center">
                <p class="text-lg font-semibold text-slate-900 md:text-xl">
                  {{ resultData?.employee?.first_name }} {{ resultData?.employee?.last_name }}
                </p>
                <p class="text-sm font-medium text-emerald-600 md:text-base">
                  {{ activeType === 'clock-in' ? 'Clock In Berhasil' : 'Clock Out Berhasil' }}
                </p>
              </div>
            </div>

            <dl class="grid grid-cols-2 gap-y-3.5 rounded-xl border border-slate-100 p-5 text-sm md:gap-y-4 md:rounded-2xl md:p-6 md:text-base">
              <dt class="text-slate-400">Type</dt>
              <dd class="text-right font-medium text-slate-800">
                {{ activeType === 'clock-in' ? 'Clock In' : 'Clock Out' }}
              </dd>

              <dt class="text-slate-400">Time</dt>
              <dd class="text-right font-medium text-slate-800">{{ formatTime(resultTime) }}</dd>

              <template v-if="resultData?.shift">
                <dt class="text-slate-400">Shift</dt>
                <dd class="text-right font-medium text-slate-800">{{ resultData.shift.name }}</dd>
              </template>

              <dt class="text-slate-400">Method</dt>
              <dd class="text-right font-medium text-slate-800">{{ verificationLabel }}</dd>

              <template v-if="resultData?.status">
                <dt class="text-slate-400">Status</dt>
                <dd class="text-right font-medium text-slate-800">{{ statusLabel(resultData.status) }}</dd>
              </template>

              <template v-if="resultDistance !== null && resultDistance !== undefined">
                <dt class="text-slate-400">Distance from Office</dt>
                <dd class="text-right font-medium text-slate-800">{{ resultDistance }} m</dd>
              </template>
            </dl>

            <button
              @click="handleDone"
              class="w-full rounded-xl bg-primary py-3 text-sm font-medium text-white hover:bg-primary-dark md:py-3.5 md:text-base"
            >
              Okay
            </button>
          </div>

          <!-- error -->
          <div v-else-if="stage === 'error'" class="space-y-4 md:space-y-5">
            <div class="flex items-start gap-2 rounded-xl bg-red-50 p-4 text-sm text-red-600 md:p-5 md:text-base">
              <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0 md:h-5 md:w-5" :stroke-width="1.75" />
              <p>{{ submitError }}</p>
            </div>
            <div class="flex gap-3 md:gap-4">
              <button
                @click="cancelFlow"
                class="flex-1 rounded-xl border border-slate-200 py-3 text-sm font-medium text-slate-600 hover:bg-slate-50 md:py-3.5 md:text-base"
              >
                Cancel
              </button>
              <button
                @click="retry"
                class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-primary py-3 text-sm font-medium text-white hover:bg-primary-dark md:py-3.5 md:text-base"
              >
                <RotateCcw class="h-4 w-4 md:h-5 md:w-5" :stroke-width="1.75" />
                Coba Lagi
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <canvas ref="canvasRef" class="hidden"></canvas>
  </div>
</template>

<style scoped>
@keyframes scan {
  0% { top: -20%; }
  100% { top: 100%; }
}
.animate-scan {
  animation: scan 1.8s ease-in-out infinite;
}
</style>