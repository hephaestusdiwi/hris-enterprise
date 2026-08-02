<script setup lang="ts">
import { ref } from 'vue'
import { Camera, Upload, RotateCcw, Loader2, AlertTriangle, CheckCircle2, XCircle, Search } from 'lucide-vue-next'
import apiClient from '@/lib/axios'
import { useFaceCapture } from '@/composables/useFaceCapture'

interface EmployeeOption {
  id: number
  first_name: string
  last_name: string
  employee_number: string
}

interface TestResult {
  is_live: boolean
  liveness_confidence: number | null
  is_match: boolean
  distance: number | null
  threshold: number | null
  processing_time_ms: number
  message: string
}

const {
  stage,
  errorMessage,
  capturedImage,
  base64Only,
  videoRef,
  canvasRef,
  fileInputRef,
  startCamera,
  stopCamera,
  capturePhoto,
  triggerFileUpload,
  handleFileChange,
  reset,
} = useFaceCapture()

// --- Employee picker ---
const searchQuery = ref('')
const employeeOptions = ref<EmployeeOption[]>([])
const selectedEmployee = ref<EmployeeOption | null>(null)
const searching = ref(false)

async function searchEmployees() {
  if (!searchQuery.value.trim()) {
    employeeOptions.value = []
    return
  }

  searching.value = true
  try {
    // NOTE: sesuaikan path & param dengan endpoint listing Employee yang sebenarnya
    // (belum sempat gua cek app/Modules/Employee/Routes/api.php)
    const { data } = await apiClient.get('/api/employees', {
      params: { search: searchQuery.value, per_page: 10 },
    })
    employeeOptions.value = data.data?.data ?? data.data ?? []
  } finally {
    searching.value = false
  }
}

function selectEmployee(employee: EmployeeOption) {
  selectedEmployee.value = employee
  employeeOptions.value = []
  searchQuery.value = `${employee.first_name} ${employee.last_name}`
}

// --- Test result ---
const testResult = ref<TestResult | null>(null)
const testError = ref('')

async function runTest() {
  if (!selectedEmployee.value) {
    testError.value = 'Pilih employee terlebih dahulu.'
    return
  }

  testError.value = ''
  testResult.value = null
  stage.value = 'processing'

  try {
    const { data } = await apiClient.post('/api/attendance-settings/face-recognition-test', {
      employee_id: selectedEmployee.value.id,
      image_base64: base64Only.value,
    })
    testResult.value = data.data
    stage.value = 'done'
  } catch (err: any) {
    testError.value = err.response?.data?.message || 'Gagal menjalankan test, silakan coba lagi.'
    stage.value = 'preview'
  }
}

function resetAll() {
  reset()
  testResult.value = null
  testError.value = ''
}
</script>

<template>
  <div class="mx-auto max-w-2xl space-y-6 p-6">
    <div>
      <h1 class="text-xl font-semibold text-slate-900">Test Face Recognition</h1>
      <p class="mt-1 text-sm text-slate-500">
        Uji apakah wajah employee bisa dikenali sistem sebelum dipakai untuk absensi. Tidak memengaruhi data absensi.
      </p>
    </div>

    <!-- Employee picker -->
    <div class="relative">
      <label class="mb-1.5 block text-sm font-medium text-slate-700">Employee</label>
      <div class="relative">
        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
        <input
          v-model="searchQuery"
          @input="searchEmployees"
          type="text"
          placeholder="Cari nama atau nomor employee..."
          class="w-full rounded-xl border border-slate-200 py-2.5 pl-9 pr-3 text-sm focus:border-primary focus:outline-none"
        />
      </div>
      <div
        v-if="employeeOptions.length"
        class="absolute z-10 mt-1 w-full rounded-xl border border-slate-200 bg-white shadow-lg"
      >
        <button
          v-for="employee in employeeOptions"
          :key="employee.id"
          @click="selectEmployee(employee)"
          class="flex w-full items-center justify-between px-4 py-2.5 text-left text-sm hover:bg-slate-50"
        >
          <span>{{ employee.first_name }} {{ employee.last_name }}</span>
          <span class="text-xs text-slate-400">{{ employee.employee_number }}</span>
        </button>
      </div>
    </div>

    <!-- Capture area -->
    <div v-if="stage === 'choose'" class="space-y-2.5">
      <button
        @click="startCamera"
        class="flex w-full items-center gap-3 rounded-xl border border-slate-200 p-3.5 text-left hover:border-primary/40 hover:bg-primary-soft/30"
      >
        <Camera class="h-5 w-5 text-primary-dark" />
        <span class="text-sm font-medium text-slate-800">Ambil Foto dari Kamera</span>
      </button>
      <button
        @click="triggerFileUpload"
        class="flex w-full items-center gap-3 rounded-xl border border-slate-200 p-3.5 text-left hover:border-primary/40 hover:bg-primary-soft/30"
      >
        <Upload class="h-5 w-5 text-slate-500" />
        <span class="text-sm font-medium text-slate-800">Upload Foto</span>
      </button>
      <input ref="fileInputRef" type="file" accept="image/*" class="hidden" @change="handleFileChange" />
    </div>

    <div v-else-if="stage === 'camera'" class="space-y-3">
      <div class="relative overflow-hidden rounded-xl bg-slate-900">
        <video ref="videoRef" autoplay playsinline muted class="aspect-[4/3] w-full -scale-x-100 object-cover"></video>
      </div>
      <button
        @click="capturePhoto"
        class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary py-3 text-sm font-medium text-white hover:bg-primary-dark"
      >
        <Camera class="h-4 w-4" /> Ambil Foto
      </button>
    </div>

    <div v-else-if="stage === 'preview'" class="space-y-3">
      <img :src="capturedImage" class="aspect-[4/3] w-full rounded-xl object-cover" alt="Preview" />
      <p v-if="testError" class="rounded-xl bg-red-50 p-3 text-xs text-red-600">{{ testError }}</p>
      <div class="flex gap-3">
        <button
          @click="resetAll"
          class="flex flex-1 items-center justify-center gap-2 rounded-xl border border-slate-200 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50"
        >
          <RotateCcw class="h-4 w-4" /> Ambil Ulang
        </button>
        <button
          @click="runTest"
          class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-primary py-2.5 text-sm font-medium text-white hover:bg-primary-dark"
        >
          Jalankan Test
        </button>
      </div>
    </div>

    <div v-else-if="stage === 'processing'" class="flex flex-col items-center justify-center gap-3 py-10">
      <Loader2 class="h-8 w-8 animate-spin text-primary" />
      <p class="text-sm text-slate-500">Menjalankan liveness & recognition...</p>
    </div>

    <!-- Diagnostic result -->
    <div v-else-if="stage === 'done' && testResult" class="space-y-3">
      <div
        class="flex items-start gap-3 rounded-xl p-4"
        :class="testResult.is_match ? 'bg-emerald-50' : 'bg-red-50'"
      >
        <CheckCircle2 v-if="testResult.is_match" class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
        <XCircle v-else class="mt-0.5 h-5 w-5 shrink-0 text-red-600" />
        <div>
          <p class="text-sm font-medium" :class="testResult.is_match ? 'text-emerald-700' : 'text-red-700'">
            {{ testResult.message }}
          </p>
        </div>
      </div>

      <dl class="grid grid-cols-2 gap-3 rounded-xl border border-slate-200 p-4 text-sm">
        <dt class="text-slate-400">Liveness</dt>
        <dd class="text-right font-medium" :class="testResult.is_live ? 'text-emerald-600' : 'text-red-600'">
          {{ testResult.is_live ? 'Lolos' : 'Gagal' }}
        </dd>
        <dt class="text-slate-400">Liveness Confidence</dt>
        <dd class="text-right font-medium">{{ testResult.liveness_confidence?.toFixed(4) ?? '-' }}</dd>
        <dt class="text-slate-400">Match</dt>
        <dd class="text-right font-medium" :class="testResult.is_match ? 'text-emerald-600' : 'text-red-600'">
          {{ testResult.is_match ? 'Cocok' : 'Tidak Cocok' }}
        </dd>
        <dt class="text-slate-400">Distance</dt>
        <dd class="text-right font-medium">{{ testResult.distance?.toFixed(4) ?? '-' }}</dd>
        <dt class="text-slate-400">Threshold</dt>
        <dd class="text-right font-medium">{{ testResult.threshold ?? '—' }} <span class="text-xs text-slate-400">(lebih kecil = lebih mirip)</span></dd>
        <dt class="text-slate-400">Waktu Proses</dt>
        <dd class="text-right font-medium">{{ testResult.processing_time_ms }} ms</dd>
      </dl>

      <button
        @click="resetAll"
        class="w-full rounded-xl border border-slate-200 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50"
      >
        Test Lagi
      </button>
    </div>

    <p v-if="errorMessage" class="flex items-start gap-2 rounded-xl bg-red-50 p-3 text-xs text-red-600">
      <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" /> {{ errorMessage }}
    </p>

    <canvas ref="canvasRef" class="hidden"></canvas>
  </div>
</template>