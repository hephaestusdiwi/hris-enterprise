<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue'
import QrScanner from 'qr-scanner'
import { X, Loader2, AlertTriangle } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

const props = defineProps<{
  mode: 'clock-in' | 'clock-out'
}>()

const emit = defineEmits<{
  close: []
  success: []
}>()

const videoRef = ref<HTMLVideoElement | null>(null)
const errorMessage = ref('')
const processing = ref(false)
let scanner: QrScanner | null = null

async function handleDecoded(token: string) {
  if (processing.value) return
  processing.value = true
  scanner?.stop()

  const endpoint = props.mode === 'clock-in' ? '/api/attendance/clock-in' : '/api/attendance/clock-out'

  try {
    await apiClient.post(endpoint, { office_qr_token: token })
    emit('success')
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Gagal memproses absen dari QR ini.'
    processing.value = false
    scanner?.start()
  }
}

onMounted(async () => {
  if (!videoRef.value) return

  scanner = new QrScanner(
    videoRef.value,
    (result) => handleDecoded(result.data),
    { highlightScanRegion: true, highlightCodeOutline: true },
  )

  try {
    await scanner.start()
  } catch {
    errorMessage.value = 'Tidak bisa mengakses kamera untuk scan QR.'
  }
})

onBeforeUnmount(() => {
  scanner?.stop()
  scanner?.destroy()
})

function handleClose() {
  scanner?.stop()
  emit('close')
}
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8">
      <div class="w-full max-w-md rounded-2xl bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
          <h2 class="text-lg font-semibold text-slate-900">Scan QR Kantor</h2>
          <button @click="handleClose" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50">
            <X class="h-5 w-5" />
          </button>
        </div>

        <div class="px-6 py-5">
          <div class="overflow-hidden rounded-xl bg-slate-900">
            <video ref="videoRef" class="aspect-square w-full object-cover"></video>
          </div>

          <div v-if="processing" class="mt-3 flex items-center justify-center gap-2 text-sm text-slate-500">
            <Loader2 class="h-4 w-4 animate-spin" :stroke-width="1.75" />
            Memproses...
          </div>

          <div v-if="errorMessage" class="mt-3 flex items-start gap-2 rounded-xl bg-red-50 p-3 text-xs text-red-600">
            <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" :stroke-width="1.75" />
            <p>{{ errorMessage }}</p>
          </div>

          <p class="mt-3 text-center text-xs text-slate-400">Arahkan kamera ke QR yang tampil di layar kantor</p>
        </div>
      </div>
    </div>
  </Teleport>
</template>