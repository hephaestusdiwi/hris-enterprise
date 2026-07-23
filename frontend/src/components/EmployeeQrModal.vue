<script setup lang="ts">
import { ref, computed } from 'vue'
import QRCode from 'qrcode'
import { QrCode, X, Check, AlertTriangle, Download, Loader2, Printer, ShieldCheck, ShieldOff } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

const props = defineProps<{
  employeeId: number
  employeeName: string
  qrGeneratedAt: string | null
}>()

const emit = defineEmits<{
  close: []
  updated: []
}>()

type Stage = 'idle' | 'generating' | 'result'

const stage = ref<Stage>('idle')
const errorMessage = ref('')
const qrImageDataUrl = ref('')
const generatedAt = ref<string | null>(props.qrGeneratedAt)

const alreadyGenerated = computed(() => !!generatedAt.value)

function initials(name: string): string {
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0]?.toUpperCase())
    .join('')
}

function formatDate(value: string | null): string {
  if (!value) return '-'
  return new Date(value.replace(' ', 'T')).toLocaleString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

async function generateQr() {
  errorMessage.value = ''
  stage.value = 'generating'

  try {
    const response = await apiClient.post(`/api/employees/${props.employeeId}/qr/generate`)
    const token = response.data.data.qr_token
    generatedAt.value = response.data.data.qr_generated_at

    qrImageDataUrl.value = await QRCode.toDataURL(token, { width: 320, margin: 2 })
    stage.value = 'result'
    emit('updated')
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Gagal generate QR Code.'
    stage.value = 'idle'
  }
}

async function revokeQr() {
  if (!confirm(`Cabut QR Code untuk "${props.employeeName}"? QR yang sudah dicetak langsung tidak berlaku.`)) return

  try {
    await apiClient.delete(`/api/employees/${props.employeeId}/qr`)
    generatedAt.value = null
    qrImageDataUrl.value = ''
    stage.value = 'idle'
    emit('updated')
  } catch {
    alert('Gagal mencabut QR Code.')
  }
}

function downloadQr() {
  const link = document.createElement('a')
  link.href = qrImageDataUrl.value
  link.download = `qr-${props.employeeName.replace(/\s+/g, '-').toLowerCase()}.png`
  link.click()
}

function printQr() {
  const printWindow = window.open('', '_blank', 'width=400,height=560')
  if (!printWindow) return

  printWindow.document.write(`
    <html>
      <head>
        <title>QR Attendance - ${props.employeeName}</title>
        <style>
          body { font-family: sans-serif; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100vh; margin: 0; gap: 12px; }
          img { width: 260px; height: 260px; }
          p { margin: 0; font-size: 14px; font-weight: 600; color: #1e293b; }
        </style>
      </head>
      <body>
        <img src="${qrImageDataUrl.value}" />
        <p>${props.employeeName}</p>
      </body>
    </html>
  `)
  printWindow.document.close()
  printWindow.focus()
  printWindow.print()
}

function handleClose() {
  emit('close')
}
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8">
      <div class="flex w-full max-w-md flex-col rounded-2xl bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
          <h2 class="text-lg font-semibold text-slate-900">QR Code Attendance</h2>
          <button @click="handleClose" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50">
            <X class="h-5 w-5" />
          </button>
        </div>

        <div class="px-6 py-5">
          <!-- Employee identity strip -->
          <div class="mb-4 flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-soft text-sm font-semibold text-primary-dark">
              {{ initials(employeeName) }}
            </div>
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-medium text-slate-800">{{ employeeName }}</p>
              <div class="mt-0.5 flex items-center gap-1 text-xs" :class="alreadyGenerated ? 'text-primary-dark' : 'text-slate-400'">
                <ShieldCheck v-if="alreadyGenerated" class="h-3 w-3" :stroke-width="2" />
                <ShieldOff v-else class="h-3 w-3" :stroke-width="2" />
                {{ alreadyGenerated ? 'QR aktif' : 'Belum ada QR' }}
              </div>
            </div>
          </div>

          <div v-if="stage === 'idle'" class="space-y-4">
            <div class="rounded-xl border border-slate-100 bg-slate-50/60 p-3 text-xs text-slate-500">
              <template v-if="alreadyGenerated">
                Terakhir digenerate <span class="font-medium text-slate-700">{{ formatDate(generatedAt) }}</span>. Token sebelumnya tidak bisa ditampilkan ulang — generate ulang kalau perlu cetak lagi.
              </template>
              <template v-else>
                Employee ini belum punya QR Code buat absensi.
              </template>
            </div>

            <div v-if="errorMessage" class="flex items-start gap-2 rounded-xl bg-red-50 p-3 text-xs text-red-600">
              <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" :stroke-width="1.75" />
              <p>{{ errorMessage }}</p>
            </div>

            <div class="flex gap-3">
              <button
                v-if="alreadyGenerated"
                @click="revokeQr"
                class="flex-1 rounded-xl border border-red-200 py-2.5 text-sm font-medium text-red-600 hover:bg-red-50"
              >
                Cabut QR
              </button>
              <button
                @click="generateQr"
                class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-primary py-2.5 text-sm font-medium text-white hover:bg-primary-dark"
              >
                <QrCode class="h-4 w-4" :stroke-width="1.75" />
                {{ alreadyGenerated ? 'Generate Ulang' : 'Generate QR' }}
              </button>
            </div>
          </div>

          <div v-else-if="stage === 'generating'" class="flex flex-col items-center justify-center gap-3 py-10">
            <Loader2 class="h-8 w-8 animate-spin text-primary" :stroke-width="1.75" />
            <p class="text-sm text-slate-500">Membuat QR Code...</p>
          </div>

          <div v-else-if="stage === 'result'" class="space-y-4">
            <div class="flex items-start gap-2 rounded-xl bg-amber-50 p-3 text-xs text-amber-700">
              <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" :stroke-width="1.75" />
              <p>Simpan/cetak QR ini sekarang. Setelah ditutup, QR ini tidak bisa ditampilkan ulang — perlu generate ulang kalau hilang.</p>
            </div>

            <!-- ID badge preview -->
            <div class="overflow-hidden rounded-2xl border border-slate-200">
              <div class="bg-primary px-4 py-2.5 text-center text-xs font-semibold uppercase tracking-wider text-white">
                Mindway HRIS — Attendance Pass
              </div>
              <div class="flex flex-col items-center gap-3 bg-white px-6 py-5">
                <img :src="qrImageDataUrl" alt="QR Code" class="h-56 w-56" />
                <div class="text-center">
                  <p class="text-sm font-semibold text-slate-800">{{ employeeName }}</p>
                  <p class="mt-0.5 text-xs text-slate-400">Digenerate {{ formatDate(generatedAt) }}</p>
                </div>
              </div>
            </div>

            <div class="flex gap-3">
              <button
                @click="downloadQr"
                class="flex flex-1 items-center justify-center gap-2 rounded-xl border border-slate-200 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50"
              >
                <Download class="h-4 w-4" :stroke-width="1.75" />
                Download
              </button>
              <button
                @click="printQr"
                class="flex flex-1 items-center justify-center gap-2 rounded-xl border border-slate-200 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50"
              >
                <Printer class="h-4 w-4" :stroke-width="1.75" />
                Print
              </button>
            </div>
          </div>
        </div>

        <div v-if="stage === 'result'" class="border-t border-slate-100 px-6 py-4">
          <button
            @click="handleClose"
            class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary py-2.5 text-sm font-medium text-white hover:bg-primary-dark"
          >
            <Check class="h-4 w-4" :stroke-width="2" />
            Sudah Disimpan, Tutup
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>