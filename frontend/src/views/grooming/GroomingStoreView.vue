<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import apiClient from '@/lib/axios'
import { useFaceCapture } from '@/composables/useFaceCapture'
import { Camera, Upload, RotateCcw, Check, AlertTriangle, ImagePlus, X } from 'lucide-vue-next'

interface StandardItem {
  id: number
  name: string
  description: string | null
  mandatory: boolean
  requires_note_on_fail: boolean
  requires_photo: boolean
}

interface ActiveStandard {
  id: number
  name: string
  version_number: number
  active_items: StandardItem[]
}

interface BranchOption {
  id: number
  name: string
}

// ---- Branch ----
const branches = ref<BranchOption[]>([])
const branchId = ref<number | null>(null)
const branchLoading = ref(true)
const branchError = ref('')

async function loadBranches() {
  branchLoading.value = true
  branchError.value = ''
  try {
    const response = await apiClient.get('/api/grooming-store/accessible-branches')
    branches.value = response.data.data
    if (branches.value.length === 1) {
      branchId.value = branches.value[0].id
    }
  } catch (err: any) {
    branchError.value = err.response?.data?.message || 'Gagal memuat daftar branch.'
  } finally {
    branchLoading.value = false
  }
}

// ---- Active standard + checklist state ----
const loading = ref(true)
const loadError = ref('')
const standard = ref<ActiveStandard | null>(null)
const answers = ref<Record<number, { result: 'pass' | 'not_pass'; note: string; photo: string }>>({})

async function loadActiveStandard() {
  loading.value = true
  loadError.value = ''
  try {
    const response = await apiClient.get('/api/grooming-store/active-standard')
    standard.value = response.data.data
    answers.value = {}
    for (const item of standard.value?.active_items ?? []) {
      answers.value[item.id] = { result: 'pass', note: '', photo: '' }
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

// ---- Photo capture per item ----
// Satu instance composable dipakai bergantian (bukan per-item) — dibuka
// lewat modal untuk item yang lagi aktif, ditutup setelah foto dikonfirmasi.
const {
  stage: photoStage,
  errorMessage: photoError,
  capturedImage,
  videoRef,
  canvasRef,
  fileInputRef,
  startCamera,
  capturePhoto,
  retakePhoto,
  triggerFileUpload,
  handleFileChange,
  reset: resetPhoto,
} = useFaceCapture()

const activeCaptureItemId = ref<number | null>(null)

function openPhotoCapture(itemId: number) {
  resetPhoto()
  activeCaptureItemId.value = itemId
}

function closePhotoCapture() {
  activeCaptureItemId.value = null
  resetPhoto()
}

function confirmPhoto() {
  if (activeCaptureItemId.value === null) return
  answers.value[activeCaptureItemId.value].photo = capturedImage.value
  closePhotoCapture()
}

// ---- Submit ----
const submitError = ref('')
const submitting = ref(false)
const submitted = ref(false)

function canSubmit(): boolean {
  if (!standard.value || !branchId.value) return false
  for (const item of standard.value.active_items) {
    const a = answers.value[item.id]
    if (!a) return false
    if (a.result === 'not_pass' && item.requires_note_on_fail && !a.note.trim()) return false
    if (item.requires_photo && !a.photo) return false
  }
  return true
}

async function submit() {
  if (!standard.value || !branchId.value) return
  submitting.value = true
  submitError.value = ''
  try {
    await apiClient.post('/api/grooming-store', {
      branch_id: branchId.value,
      answers: Object.entries(answers.value).map(([itemId, a]) => ({
        grooming_standard_item_id: Number(itemId),
        result: a.result,
        note: a.note || null,
        photo: a.photo || null,
      })),
    })
    submitted.value = true
  } catch (err: any) {
    submitError.value = err.response?.data?.message || 'Gagal submit Grooming Store.'
  } finally {
    submitting.value = false
  }
}

function resetForm() {
  submitted.value = false
  loadActiveStandard()
}

onMounted(() => {
  loadBranches()
  loadActiveStandard()
})
</script>

<template>
  <div class="mx-auto max-w-lg space-y-4">
    <h1 class="text-lg font-semibold text-slate-800">Grooming Store</h1>

    <div v-if="loading || branchLoading" class="text-sm text-slate-400">Memuat checklist...</div>
    <div v-else-if="loadError" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ loadError }}</div>
    <div v-else-if="branchError" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ branchError }}</div>

    <div v-else-if="submitted" class="rounded-2xl border border-emerald-100 bg-emerald-50 p-8 text-center">
      <Check class="mx-auto h-10 w-10 text-emerald-500" :stroke-width="1.75" />
      <p class="mt-3 text-sm font-medium text-emerald-700">Grooming Store berhasil disubmit</p>
      <p class="mt-1 text-xs text-emerald-600">Hasil: {{ overallResult === 'pass' ? 'PASS' : 'NOT PASS' }}</p>
      <button v-if="overallResult === 'not_pass'" class="mt-4 rounded-xl bg-white px-4 py-2 text-sm font-medium text-emerald-700 shadow-sm" @click="resetForm">
        Periksa &amp; Submit Ulang
      </button>
    </div>

    <div v-else-if="standard" class="space-y-4">
      <!-- Branch selector -->
      <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <label class="mb-1.5 block text-xs font-medium text-slate-500">Store / Branch</label>
        <select
          v-if="branches.length > 1"
          v-model="branchId"
          class="w-full rounded-lg border border-slate-200 p-2 text-sm"
        >
          <option :value="null" disabled>Pilih branch</option>
          <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
        </select>
        <p v-else-if="branches.length === 1" class="text-sm text-slate-700">{{ branches[0].name }}</p>
        <p v-else class="text-sm text-slate-400">Tidak ada branch yang bisa diakses.</p>
      </div>

      <!-- Checklist -->
      <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <p class="mb-3 text-xs text-slate-400">{{ standard.name }} &middot; v{{ standard.version_number }}</p>

        <div v-for="item in standard.active_items" :key="item.id" class="mb-4 last:mb-0">
          <div class="flex items-center justify-between gap-2">
            <p class="text-sm font-medium text-slate-700">{{ item.name }}</p>
            <div class="flex shrink-0 gap-1.5">
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
            v-if="answers[item.id]?.result === 'not_pass' && item.requires_note_on_fail"
            v-model="answers[item.id].note"
            placeholder="Catatan (wajib diisi)"
            rows="2"
            class="mt-2 w-full rounded-lg border border-slate-200 p-2 text-xs"
          ></textarea>

          <!-- Foto per item, cuma muncul kalau item.requires_photo -->
          <div v-if="item.requires_photo" class="mt-2">
            <div v-if="answers[item.id]?.photo" class="flex items-center gap-2">
              <img :src="answers[item.id].photo" class="h-12 w-12 rounded-lg object-cover" alt="Foto item" />
              <button type="button" class="text-xs font-medium text-primary-dark underline" @click="openPhotoCapture(item.id)">
                Ganti Foto
              </button>
            </div>
            <button
              v-else
              type="button"
              class="flex items-center gap-1.5 rounded-lg border border-dashed border-slate-300 px-3 py-1.5 text-xs text-slate-500 hover:bg-slate-50"
              @click="openPhotoCapture(item.id)"
            >
              <ImagePlus class="h-3.5 w-3.5" :stroke-width="1.75" />
              Foto wajib untuk item ini
            </button>
          </div>
        </div>
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

    <!-- Modal capture foto per item -->
    <div v-if="activeCaptureItemId !== null" class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 sm:items-center">
      <div class="w-full max-w-sm rounded-t-2xl bg-white p-4 sm:rounded-2xl">
        <div class="mb-3 flex items-center justify-between">
          <p class="text-sm font-semibold text-slate-700">Foto Item</p>
          <button type="button" class="text-slate-400 hover:text-slate-600" @click="closePhotoCapture">
            <X class="h-5 w-5" :stroke-width="1.75" />
          </button>
        </div>

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
          <div class="flex gap-2">
            <button type="button" class="flex flex-1 items-center justify-center gap-2 rounded-xl border border-slate-200 py-2 text-sm text-slate-600" @click="retakePhoto">
              <RotateCcw class="h-4 w-4" :stroke-width="1.75" />
              Ambil Ulang
            </button>
            <button type="button" class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-primary py-2 text-sm font-medium text-white" @click="confirmPhoto">
              <Check class="h-4 w-4" :stroke-width="2" />
              Gunakan Foto
            </button>
          </div>
        </div>

        <canvas ref="canvasRef" class="hidden"></canvas>
      </div>
    </div>
  </div>
</template>