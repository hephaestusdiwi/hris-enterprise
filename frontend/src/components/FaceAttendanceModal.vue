<script setup lang="ts">
import { onMounted, onBeforeUnmount, computed } from 'vue'
import { X, Check, RotateCcw, AlertTriangle, LogIn, LogOut } from 'lucide-vue-next'
import { useFaceAttendance } from '@/composables/useFaceAttendance'

const props = defineProps<{
  type: 'clock-in' | 'clock-out'
  requiresFaceVerification: boolean
  requiresLocation: boolean
}>()

const emit = defineEmits<{
  close: []
  success: []
}>()

const {
  stage: localStage,
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
 } = useFaceAttendance(
   computed(() => props.type),
   computed(() => props.requiresFaceVerification),
   computed(() => props.requiresLocation),
 )

function handleClose() {
  stopCamera()
  emit('close')
}

function handleDone() {
  stopCamera()
  emit('success')
}

onMounted(() => {
  startCamera()
})

onBeforeUnmount(() => {
  stopCamera()
})
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4 py-8">
      <div class="flex w-full max-w-sm flex-col overflow-hidden rounded-2xl bg-white shadow-xl">

        <!-- Header -->
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
          <h2 class="text-base font-semibold text-slate-900">
            {{ type === 'clock-in' ? 'Clock In' : 'Clock Out' }} · {{ verificationLabel }}
          </h2>
          <button @click="handleClose" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50">
            <X class="h-5 w-5" />
          </button>
        </div>

        <!-- Stage: camera (live capture with oval guide) -->
        <div v-if="localStage === 'camera'" class="space-y-4 p-5">
          <div class="relative overflow-hidden rounded-xl bg-slate-900">
            <video ref="videoRef" autoplay playsinline muted class="aspect-[3/4] w-full -scale-x-100 object-cover"></video>

            <!-- corner brackets -->
            <div class="pointer-events-none absolute inset-4">
              <span class="absolute left-0 top-0 h-6 w-6 border-l-2 border-t-2 border-white/80 rounded-tl-lg"></span>
              <span class="absolute right-0 top-0 h-6 w-6 border-r-2 border-t-2 border-white/80 rounded-tr-lg"></span>
              <span class="absolute bottom-0 left-0 h-6 w-6 border-b-2 border-l-2 border-white/80 rounded-bl-lg"></span>
              <span class="absolute bottom-0 right-0 h-6 w-6 border-b-2 border-r-2 border-white/80 rounded-br-lg"></span>
            </div>

            <!-- oval face guide -->
            <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
              <div class="h-56 w-44 rounded-[50%] border-2 border-dashed border-white/70"></div>
            </div>
          </div>

          <p v-if="requiresFaceVerification" class="text-center text-xs text-slate-400">
            Posisikan wajah di dalam bingkai, lalu tekan Start
          </p>

          <div class="flex gap-3">
            <button
              @click="handleClose"
              class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50"
            >
              Cancel
            </button>
            <button
              @click="handleStart"
              class="flex-1 rounded-xl bg-primary py-2.5 text-sm font-medium text-white hover:bg-primary-dark"
            >
              Start
            </button>
          </div>
        </div>

        <!-- Stage: submitting (scanning animation on frozen frame) -->
        <div v-else-if="localStage === 'submitting'" class="space-y-4 p-5">
          <div class="relative overflow-hidden rounded-xl bg-slate-900">
            <img :src="capturedImage" class="aspect-[3/4] w-full -scale-x-100 object-cover opacity-90" alt="Captured" />
            <div class="absolute inset-0 bg-gradient-to-b from-transparent via-emerald-400/10 to-transparent">
              <div class="absolute inset-x-0 h-16 animate-scan bg-gradient-to-b from-transparent via-emerald-400/40 to-transparent"></div>
            </div>
          </div>
          <p class="text-center text-sm text-slate-500">Memverifikasi wajah...</p>
        </div>

        <!-- Stage: success -->
        <div v-else-if="localStage === 'success'" class="space-y-4 p-5">
          <div class="flex flex-col items-center gap-2 pt-2">
            <div class="flex h-14 w-14 items-center justify-center rounded-full bg-emerald-50">
              <Check class="h-7 w-7 text-emerald-600" :stroke-width="2.5" />
            </div>
            <p class="text-sm font-semibold text-slate-800">
              {{ type === 'clock-in' ? 'Clock In Berhasil' : 'Clock Out Berhasil' }}
            </p>
          </div>

          <dl class="grid grid-cols-2 gap-y-3 rounded-xl border border-slate-100 p-4 text-sm">
            <dt class="text-slate-400">Employee</dt>
            <dd class="text-right font-medium text-slate-800">
              {{ resultData?.employee?.first_name }} {{ resultData?.employee?.last_name }}
            </dd>

            <dt class="text-slate-400">Type</dt>
            <dd class="text-right font-medium text-slate-800">
              {{ type === 'clock-in' ? 'Clock In' : 'Clock Out' }}
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
+               <dt class="text-slate-400">Status</dt>
                <dd class="text-right font-medium text-slate-800">
                  {{ statusLabel(resultData.status) }}
                </dd>
            </template>

            <template v-if="resultDistance !== null && resultDistance !== undefined">
              <dt class="text-slate-400">Distance from Office</dt>
              <dd class="text-right font-medium text-slate-800">{{ resultDistance }} m</dd>
            </template>
          </dl>

          <button
            @click="handleDone"
            class="w-full rounded-xl bg-primary py-2.5 text-sm font-medium text-white hover:bg-primary-dark"
          >
            Okay
          </button>
        </div>

        <!-- Stage: error -->
        <div v-else-if="localStage === 'error'" class="space-y-4 p-5">
          <div class="flex items-start gap-2 rounded-xl bg-red-50 p-3 text-sm text-red-600">
            <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" :stroke-width="1.75" />
            <p>{{ submitError }}</p>
          </div>
          <div class="flex gap-3">
            <button
              @click="handleClose"
              class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50"
            >
              Cancel
            </button>
            <button
              @click="retry"
              class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-primary py-2.5 text-sm font-medium text-white hover:bg-primary-dark"
            >
              <RotateCcw class="h-4 w-4" :stroke-width="1.75" />
              Coba Lagi
            </button>
          </div>
        </div>
      </div>
    </div>

    <canvas ref="canvasRef" class="hidden"></canvas>
  </Teleport>
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