<script setup lang="ts">
import { ref, computed, onBeforeUnmount } from 'vue'
import { Camera, Upload, X, Check, RotateCcw, Loader2, AlertTriangle, ChevronRight } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

const props = defineProps<{
  employeeId: number
  employeeName: string
}>()

const emit = defineEmits<{
  close: []
  enrolled: []
}>()

type Stage = 'choose' | 'camera' | 'preview' | 'uploading' | 'success'

const stage = ref<Stage>('choose')
const errorMessage = ref('')
const capturedImage = ref('')
const videoRef = ref<HTMLVideoElement | null>(null)
const canvasRef = ref<HTMLCanvasElement | null>(null)
const fileInputRef = ref<HTMLInputElement | null>(null)
let mediaStream: MediaStream | null = null

// --- Step indicator ---
const steps = ['Pilih Metode', 'Ambil Foto', 'Konfirmasi']
const currentStepIndex = computed(() => {
  if (stage.value === 'choose') return 0
  if (stage.value === 'camera') return 1
  return 2 // preview, uploading, success
})

async function startCamera() {
  errorMessage.value = ''
  stage.value = 'camera'

  try {
    mediaStream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
    })
    if (videoRef.value) {
      videoRef.value.srcObject = mediaStream
      await videoRef.value.play()
    }
  } catch {
    errorMessage.value = 'Tidak bisa mengakses kamera. Silakan upload foto sebagai alternatif.'
    stage.value = 'choose'
  }
}

function stopCamera() {
  mediaStream?.getTracks().forEach((track) => track.stop())
  mediaStream = null
}

function capturePhoto() {
  if (!videoRef.value || !canvasRef.value) return

  const video = videoRef.value
  const canvas = canvasRef.value
  canvas.width = video.videoWidth
  canvas.height = video.videoHeight

  const ctx = canvas.getContext('2d')
  ctx?.drawImage(video, 0, 0, canvas.width, canvas.height)

  capturedImage.value = canvas.toDataURL('image/jpeg', 0.9)
  stopCamera()
  stage.value = 'preview'
}

function retakePhoto() {
  capturedImage.value = ''
  startCamera()
}

function triggerFileUpload() {
  fileInputRef.value?.click()
}

function handleFileChange(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (!file) return

  const reader = new FileReader()
  reader.onload = () => {
    capturedImage.value = reader.result as string
    stage.value = 'preview'
  }
  reader.readAsDataURL(file)
}

async function confirmEnrollment() {
  errorMessage.value = ''
  stage.value = 'uploading'

  const base64Only = capturedImage.value.split(',')[1]

  try {
    await apiClient.post(`/api/employees/${props.employeeId}/face/enroll`, {
      image_base64: base64Only,
    })
    stage.value = 'success'
    emit('enrolled')
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Gagal mendaftarkan wajah, silakan coba lagi.'
    stage.value = 'preview'
  }
}

function retryFromPreview() {
  capturedImage.value = ''
  errorMessage.value = ''
  stage.value = 'choose'
}

function handleClose() {
  stopCamera()
  emit('close')
}

onBeforeUnmount(() => {
  stopCamera()
})
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8">
      <div class="flex w-full max-w-md flex-col rounded-2xl bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
          <h2 class="text-lg font-semibold text-slate-900">Daftarkan Wajah</h2>
          <button @click="handleClose" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50">
            <X class="h-5 w-5" />
          </button>
        </div>

        <!-- Step indicator -->
        <div v-if="stage !== 'success'" class="flex items-center px-6 pt-5">
          <template v-for="(label, i) in steps" :key="label">
            <div class="flex flex-col items-center">
              <div
                class="flex h-6 w-6 items-center justify-center rounded-full text-[11px] font-semibold transition-colors"
                :class="
                  i < currentStepIndex
                    ? 'bg-primary text-white'
                    : i === currentStepIndex
                      ? 'bg-primary-soft text-primary-dark ring-2 ring-primary/30'
                      : 'bg-slate-100 text-slate-400'
                "
              >
                <Check v-if="i < currentStepIndex" class="h-3 w-3" :stroke-width="2.5" />
                <span v-else>{{ i + 1 }}</span>
              </div>
              <span class="mt-1 text-[10px] font-medium" :class="i <= currentStepIndex ? 'text-slate-600' : 'text-slate-300'">
                {{ label }}
              </span>
            </div>
            <div
              v-if="i < steps.length - 1"
              class="mx-1.5 mb-4 h-px flex-1 transition-colors"
              :class="i < currentStepIndex ? 'bg-primary' : 'bg-slate-100'"
            ></div>
          </template>
        </div>

        <div class="px-6 py-5">
          <p class="mb-4 text-sm text-slate-500">{{ employeeName }}</p>

          <div v-if="stage === 'choose'" class="space-y-2.5">
            <button
              @click="startCamera"
              class="flex w-full items-center gap-3 rounded-xl border border-slate-200 p-3.5 text-left transition-colors hover:border-primary/40 hover:bg-primary-soft/30"
            >
              <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-primary-dark">
                <Camera class="h-5 w-5" :stroke-width="1.75" />
              </div>
              <div class="flex-1">
                <p class="text-sm font-medium text-slate-800">Ambil Foto dari Kamera</p>
                <p class="text-xs text-slate-400">Gunakan kamera perangkat secara langsung</p>
              </div>
              <ChevronRight class="h-4 w-4 shrink-0 text-slate-300" :stroke-width="1.75" />
            </button>

            <button
              @click="triggerFileUpload"
              class="flex w-full items-center gap-3 rounded-xl border border-slate-200 p-3.5 text-left transition-colors hover:border-primary/40 hover:bg-primary-soft/30"
            >
              <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-500">
                <Upload class="h-5 w-5" :stroke-width="1.75" />
              </div>
              <div class="flex-1">
                <p class="text-sm font-medium text-slate-800">Upload Foto</p>
                <p class="text-xs text-slate-400">Pilih foto dari galeri perangkat</p>
              </div>
              <ChevronRight class="h-4 w-4 shrink-0 text-slate-300" :stroke-width="1.75" />
            </button>
            <input ref="fileInputRef" type="file" accept="image/*" class="hidden" @change="handleFileChange" />

            <div v-if="errorMessage" class="flex items-start gap-2 rounded-xl bg-red-50 p-3 text-xs text-red-600">
              <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" :stroke-width="1.75" />
              <p>{{ errorMessage }}</p>
            </div>
          </div>

          <div v-else-if="stage === 'camera'" class="space-y-3">
            <div class="relative overflow-hidden rounded-xl bg-slate-900">
              <video ref="videoRef" autoplay playsinline muted class="aspect-[4/3] w-full -scale-x-100 object-cover"></video>

              <!-- Face guide overlay -->
              <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                <div class="h-40 w-32 rounded-[50%] border-2 border-dashed border-white/70"></div>
                <p class="mt-3 rounded-full bg-slate-900/60 px-3 py-1 text-[11px] text-white">
                  Posisikan wajah di dalam oval
                </p>
              </div>
            </div>
            <button
              @click="capturePhoto"
              class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary py-3 text-sm font-medium text-white hover:bg-primary-dark"
            >
              <Camera class="h-4 w-4" :stroke-width="2" />
              Ambil Foto
            </button>
          </div>

          <div v-else-if="stage === 'preview'" class="space-y-3">
            <div class="overflow-hidden rounded-xl bg-slate-900">
              <img :src="capturedImage" class="aspect-[4/3] w-full object-cover" alt="Preview wajah" />
            </div>

            <p v-if="errorMessage" class="rounded-xl bg-red-50 p-3 text-xs text-red-600">{{ errorMessage }}</p>

            <div class="flex gap-3">
              <button
                @click="retryFromPreview"
                class="flex flex-1 items-center justify-center gap-2 rounded-xl border border-slate-200 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50"
              >
                <RotateCcw class="h-4 w-4" :stroke-width="1.75" />
                Ambil Ulang
              </button>
              <button
                @click="confirmEnrollment"
                class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-primary py-2.5 text-sm font-medium text-white hover:bg-primary-dark"
              >
                <Check class="h-4 w-4" :stroke-width="2" />
                Konfirmasi & Simpan
              </button>
            </div>
          </div>

          <div v-else-if="stage === 'uploading'" class="flex flex-col items-center justify-center gap-3 py-10">
            <Loader2 class="h-8 w-8 animate-spin text-primary" :stroke-width="1.75" />
            <p class="text-sm text-slate-500">Memproses wajah, mohon tunggu...</p>
          </div>

          <div v-else-if="stage === 'success'" class="flex flex-col items-center justify-center gap-3 py-10">
            <div class="flex h-14 w-14 items-center justify-center rounded-full bg-primary-soft">
              <Check class="h-7 w-7 text-primary-dark" :stroke-width="2" />
            </div>
            <p class="text-sm font-medium text-slate-700">Wajah berhasil didaftarkan</p>
            <button
              @click="handleClose"
              class="rounded-xl bg-primary px-6 py-2 text-sm font-medium text-white hover:bg-primary-dark"
            >
              Selesai
            </button>
          </div>
        </div>
      </div>
    </div>

    <canvas ref="canvasRef" class="hidden"></canvas>
  </Teleport>
</template>