<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import apiClient from '@/lib/axios'
import { useFaceCapture } from '@/composables/useFaceCapture'
import { Camera, Upload, RotateCcw, Check, AlertTriangle } from 'lucide-vue-next'

interface StandardItem {
  id: number
  name: string
  description: string | null
  mandatory: boolean
  requires_note_on_fail: boolean
}

interface ActiveStandard {
  id: number
  name: string
  version_number: number
  active_items: StandardItem[]
}

interface HistoryRow {
  id: number
  overall_result: 'pass' | 'not_pass'
  submitted_at: string
  standard: { name: string; version_number: number }
}

const tab = ref<'submit' | 'history'>('submit')

// ---- Active standard + checklist state ----
const loading = ref(true)
const loadError = ref('')
const standard = ref<ActiveStandard | null>(null)
const answers = ref<Record<number, { result: 'pass' | 'not_pass'; note: string }>>({})

async function loadActiveStandard() {
  loading.value = true
  loadError.value = ''
  try {
    const response = await apiClient.get('/api/grooming-self/active-standard')
    standard.value = response.data.data
    answers.value = {}
    for (const item of standard.value?.active_items ?? []) {
      answers.value[item.id] = { result: 'pass', note: '' }
    }
  } catch (err: any) {
    loadError.value = err.response?.data?.message || 'Gagal memuat Grooming Standard.'
  } finally {
    loading.value = false
  }
}

const overallResult = computed<'pass' | 'not_pass'>(() => {
  const items = standard.value?.active_items ?? []
  const anyFail = items.some((item) => item.mandatory && answers.value[item.id]?.result === 'not_pass')
  return anyFail ? 'not_pass' : 'pass'
})

// ---- Photo capture (reuse composable yang sudah ada, sama seperti Face Enrollment) ----
const {
  stage: photoStage,
  errorMessage: photoError,
  capturedImage,
  videoRef,
  canvasRef,
  fileInputRef,
  startCamera,
  stopCamera,
  capturePhoto,
  retakePhoto,
  triggerFileUpload,
  handleFileChange,
  reset: resetPhoto,
} = useFaceCapture()

// ---- Submit ----
const submitError = ref('')
const submitting = ref(false)
const submitted = ref(false)

function canSubmit(): boolean {
  if (!standard.value || !capturedImage.value) return false
  for (const item of standard.value.active_items) {
    const a = answers.value[item.id]
    if (!a) return false
    if (a.result === 'not_pass' && item.requires_note_on_fail && !a.note.trim()) return false
  }
  return true
}

async function submit() {
  if (!standard.value) return
  submitting.value = true
  submitError.value = ''
  try {
    await apiClient.post('/api/grooming-self', {
      photo: capturedImage.value,
      answers: Object.entries(answers.value).map(([itemId, a]) => ({
        grooming_standard_item_id: Number(itemId),
        result: a.result,
        note: a.note || null,
      })),
    })
    submitted.value = true
  } catch (err: any) {
    submitError.value = err.response?.data?.message || 'Gagal submit Grooming Self.'
  } finally {
    submitting.value = false
  }
}

function resetForm() {
  submitted.value = false
  resetPhoto()
  loadActiveStandard()
}

// ---- History ----
const historyLoading = ref(false)
const historyError = ref('')
const historyRows = ref<HistoryRow[]>([])

async function loadHistory() {
  historyLoading.value = true
  historyError.value = ''
  try {
    const response = await apiClient.get('/api/grooming-self/my-history')
    historyRows.value = response.data.data.data
  } catch (err: any) {
    historyError.value = err.response?.data?.message || 'Gagal memuat histori.'
  } finally {
    historyLoading.value = false
  }
}

function switchTab(t: 'submit' | 'history') {
  tab.value = t
  if (t === 'history' && historyRows.value.length === 0) loadHistory()
}

onMounted(loadActiveStandard)
</script>

<template>
  <div class="mx-auto max-w-lg space-y-4">
    <h1 class="text-lg font-semibold text-slate-800">Grooming Self</h1>

    <div class="flex gap-2 border-b border-slate-100">
      <button class="px-4 py-2 text-sm font-medium" :class="tab === 'submit' ? 'border-b-2 border-primary text-primary-dark' : 'text-slate-400'" @click="switchTab('submit')">
        Isi Checklist
      </button>
      <button class="px-4 py-2 text-sm font-medium" :class="tab === 'history' ? 'border-b-2 border-primary text-primary-dark' : 'text-slate-400'" @click="switchTab('history')">
        Riwayat Saya
      </button>
    </div>

    <!-- SUBMIT TAB -->
    <template v-if="tab === 'submit'">
      <div v-if="loading" class="text-sm text-slate-400">Memuat checklist...</div>
      <div v-else-if="loadError" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ loadError }}</div>

      <div v-else-if="submitted" class="rounded-2xl border border-emerald-100 bg-emerald-50 p-8 text-center">
        <Check class="mx-auto h-10 w-10 text-emerald-500" :stroke-width="1.75" />
        <p class="mt-3 text-sm font-medium text-emerald-700">Grooming Self berhasil disubmit</p>
        <p class="mt-1 text-xs text-emerald-600">Hasil: {{ overallResult === 'pass' ? 'PASS' : 'NOT PASS' }}</p>
        <button v-if="overallResult === 'not_pass'" class="mt-4 rounded-xl bg-white px-4 py-2 text-sm font-medium text-emerald-700 shadow-sm" @click="resetForm">
          Perbaiki &amp; Submit Ulang
        </button>
      </div>

      <div v-else-if="standard" class="space-y-4">
        <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
          <p class="mb-3 text-xs text-slate-400">{{ standard.name }} &middot; v{{ standard.version_number }}</p>

          <div v-for="item in standard.active_items" :key="item.id" class="mb-4 last:mb-0">
            <div class="flex items-center justify-between">
              <p class="text-sm font-medium text-slate-700">{{ item.name }}</p>
              <div class="flex gap-1.5">
                <button
                  type="button"
                  class="rounded-lg px-3 py-1 text-xs font-medium"
                  :class="answers[item.id]?.result === 'pass' ? 'bg-emerald-500 text-white' : 'bg-slate-100 text-slate-500'"
                  @click="answers[item.id].result = 'pass'"
                >
                  PASS
                </button>
                <button
                  type="button"
                  class="rounded-lg px-3 py-1 text-xs font-medium"
                  :class="answers[item.id]?.result === 'not_pass' ? 'bg-red-500 text-white' : 'bg-slate-100 text-slate-500'"
                  @click="answers[item.id].result = 'not_pass'"
                >
                  NOT PASS
                </button>
              </div>
            </div>
            <textarea
              v-if="answers[item.id]?.result === 'not_pass'"
              v-model="answers[item.id].note"
              placeholder="Catatan (wajib diisi)"
              rows="2"
              class="mt-2 w-full rounded-lg border border-slate-200 p-2 text-xs"
            ></textarea>
          </div>
        </div>

        <!-- Photo evidence -->
        <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
          <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Photo Evidence</p>

          <div v-if="photoStage === 'choose'" class="space-y-2">
            <button type="button" class="flex w-full items-center gap-3 rounded-xl border border-slate-200 p-3 text-left hover:bg-slate-50" @click="startCamera">
              <Camera class="h-5 w-5 text-slate-500" :stroke-width="1.75" />
              <span class="text-sm text-slate-700">Ambil Foto dari Kamera</span>
            </button>
            <button type="button" class="flex w-full items-center gap-3 rounded-xl border border-slate-200 p-3 text-left hover:bg-slate-50" @click="triggerFileUpload">
              <Upload class="h-5 w-5 text-slate-500" :stroke-width="1.75" />
              <span class="text-sm text-slate-700">Upload Foto</span>
            </button>
            <input ref="fileInputRef" type="file" accept="image/*" class="hidden" @change="handleFileChange" />
            <p v-if="photoError" class="flex items-start gap-2 rounded-xl bg-red-50 p-2 text-xs text-red-600">
              <AlertTriangle class="mt-0.5 h-3.5 w-3.5 shrink-0" :stroke-width="1.75" />
              {{ photoError }}
            </p>
          </div>

          <div v-else-if="photoStage === 'camera'" class="space-y-3">
            <div class="overflow-hidden rounded-xl bg-slate-900">
              <video ref="videoRef" autoplay playsinline muted class="aspect-[4/3] w-full -scale-x-100 object-cover"></video>
            </div>
            <button type="button" class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary py-2.5 text-sm font-medium text-white" @click="capturePhoto">
              <Camera class="h-4 w-4" :stroke-width="2" />
              Ambil Foto
            </button>
          </div>

          <div v-else-if="photoStage === 'preview'" class="space-y-3">
            <div class="overflow-hidden rounded-xl bg-slate-900">
              <img :src="capturedImage" class="aspect-[4/3] w-full object-cover" alt="Preview" />
            </div>
            <button type="button" class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 py-2 text-sm text-slate-600" @click="retakePhoto">
              <RotateCcw class="h-4 w-4" :stroke-width="1.75" />
              Ambil Ulang
            </button>
          </div>

          <canvas ref="canvasRef" class="hidden"></canvas>
        </div>

        <div class="rounded-xl px-1 text-center text-sm font-medium" :class="overallResult === 'pass' ? 'text-emerald-600' : 'text-red-500'">
          Overall: {{ overallResult === 'pass' ? 'PASS' : 'NOT PASS' }}
        </div>

        <div v-if="submitError" class="rounded-xl bg-red-50 p-3 text-sm text-red-600">{{ submitError }}</div>

        <button
          type="button"
          :disabled="!canSubmit() || submitting"
          class="w-full rounded-xl bg-primary py-3 text-sm font-medium text-white disabled:opacity-40"
          @click="submit"
        >
          {{ submitting ? 'Mengirim...' : 'Submit' }}
        </button>
      </div>
    </template>

    <!-- HISTORY TAB -->
    <template v-else>
      <div v-if="historyLoading" class="text-sm text-slate-400">Memuat histori...</div>
      <div v-else-if="historyError" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ historyError }}</div>
      <div v-else-if="historyRows.length === 0" class="py-10 text-center text-sm text-slate-400">Belum ada riwayat submission.</div>
      <div v-else class="space-y-2">
        <div v-for="row in historyRows" :key="row.id" class="flex items-center justify-between rounded-xl border border-slate-100 bg-white p-3">
          <div>
            <p class="text-sm text-slate-700">{{ row.submitted_at?.slice(0, 16).replace('T', ' ') }}</p>
            <p class="text-xs text-slate-400">{{ row.standard.name }} &middot; v{{ row.standard.version_number }}</p>
          </div>
          <span
            class="rounded-full px-2.5 py-1 text-xs font-medium"
            :class="row.overall_result === 'pass' ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-600'"
          >
            {{ row.overall_result === 'pass' ? 'PASS' : 'NOT PASS' }}
          </span>
        </div>
      </div>
    </template>
  </div>
</template>