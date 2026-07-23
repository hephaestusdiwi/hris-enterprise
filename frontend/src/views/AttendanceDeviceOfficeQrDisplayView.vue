<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRoute } from 'vue-router'
import QRCode from 'qrcode'
import { Wifi, WifiOff, RefreshCw } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

const route = useRoute()
const deviceId = route.params.id

const qrImageDataUrl = ref('')
const errorMessage = ref('')
const secondsLeft = ref(30)
const totalSeconds = ref(30)
const refreshing = ref(false)
const currentTime = ref(new Date())

let refreshTimer: ReturnType<typeof setInterval> | null = null
let countdownTimer: ReturnType<typeof setInterval> | null = null
let clockTimer: ReturnType<typeof setInterval> | null = null

const progressPercent = computed(() => {
  if (!totalSeconds.value) return 0
  return Math.max(0, Math.min(100, (secondsLeft.value / totalSeconds.value) * 100))
})

const formattedClock = computed(() =>
  currentTime.value.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
)
const formattedDate = computed(() =>
  currentTime.value.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
)

async function refreshQr() {
  refreshing.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get(`/api/attendance-devices/${deviceId}/office-qr`)
    const { token, expires_in } = response.data.data

    qrImageDataUrl.value = await QRCode.toDataURL(token, { width: 480, margin: 2 })
    secondsLeft.value = expires_in
    totalSeconds.value = expires_in
  } catch {
    errorMessage.value = 'Gagal memuat QR Code. Mencoba lagi...'
  } finally {
    refreshing.value = false
  }
}

onMounted(() => {
  refreshQr()
  refreshTimer = setInterval(refreshQr, 25000)
  countdownTimer = setInterval(() => {
    secondsLeft.value = Math.max(0, secondsLeft.value - 1)
  }, 1000)
  clockTimer = setInterval(() => {
    currentTime.value = new Date()
  }, 1000)
})

onBeforeUnmount(() => {
  if (refreshTimer) clearInterval(refreshTimer)
  if (countdownTimer) clearInterval(countdownTimer)
  if (clockTimer) clearInterval(clockTimer)
})
</script>

<template>
  <div
    class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden bg-slate-900 px-6 py-10 text-white"
  >
    <!-- Ambient glow -->
    <div
      class="pointer-events-none absolute inset-0"
      style="background-image: radial-gradient(circle at 50% 40%, rgba(51,204,204,0.14), transparent 55%)"
    ></div>

    <!-- Top bar -->
    <div class="relative flex w-full max-w-3xl flex-col items-center gap-3 border-b border-white/10 pb-5">
      <div class="flex items-center gap-2.5">
        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-primary text-md font-bold text-white">
          M
        </div>
        <span class="text-xl font-semibold tracking-tight text-white">Mindway HRIS</span>
      </div>

      <div class="text-center">
        <p class="text-lg font-semibold tabular-nums tracking-tight text-white">{{ formattedClock }}</p>
        <p class="text-md text-slate-400">{{ formattedDate }}</p>
      </div>
    </div>

    <!-- Main content -->
    <div class="relative flex flex-1 flex-col items-center justify-center">
      <p class="mb-2 text-xs font-semibold uppercase tracking-[0.2em] text-primary">Scan untuk Absen</p>
      <h1 class="mb-8 max-w-md text-center text-2xl font-semibold text-white">
        Buka aplikasi HRIS Anda, <br> lalu pindai QR ini
      </h1>

      <!-- QR frame -->
      <div class="relative">

        <div class="rounded-3xl bg-white p-6 shadow-2xl">
          <img v-if="qrImageDataUrl" :src="qrImageDataUrl" alt="Office QR Code" class="h-96 w-96" />
          <div v-else class="flex h-96 w-96 items-center justify-center text-slate-400">Memuat...</div>
        </div>
      </div>

      <!-- Countdown progress -->
      <div class="mt-8 w-full max-w-xs">
        <div class="mb-2 flex items-center justify-between text-xs text-slate-400">
          <span class="flex items-center gap-1.5">
            <RefreshCw class="h-3 w-3" :stroke-width="2" :class="refreshing ? 'animate-spin text-primary' : ''" />
            QR aktif
          </span>
          <span class="tabular-nums">{{ secondsLeft }}s</span>
        </div>
        <div class="h-1.5 w-full overflow-hidden rounded-full bg-white/10">
          <div
            class="h-full rounded-full bg-primary transition-all duration-1000 ease-linear"
            :style="{ width: `${progressPercent}%` }"
          ></div>
        </div>
      </div>

      <p v-if="errorMessage" class="mt-4 text-sm text-red-400">{{ errorMessage }}</p>
    </div>

    <!-- Footer -->
    <div class="relative flex w-full max-w-3xl items-center justify-between border-t border-white/10 pt-4 text-xs text-slate-500">
      <span>Device ID: {{ deviceId }}</span>
      <span class="flex items-center gap-1.5">
        <template v-if="!errorMessage">
          <span class="relative flex h-2 w-2">
            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
            <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
          </span>
          <Wifi class="h-3.5 w-3.5" :stroke-width="1.75" />
          Terhubung
        </template>
        <template v-else>
          <WifiOff class="h-3.5 w-3.5 text-red-400" :stroke-width="1.75" />
          Terputus
        </template>
      </span>
    </div>
  </div>
</template>