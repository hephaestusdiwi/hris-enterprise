<script setup lang="ts">
import { ref, onMounted } from 'vue'
import apiClient from '@/lib/axios'

interface RefOption {
  id: number
  name?: string
  code?: string
}

interface EmployeeProfile {
  id: number
  employee_number: string
  first_name: string
  last_name: string | null
  photo_url: string | null
  gender: 'male' | 'female'
  birth_place: string | null
  birth_date: string | null
  marital_status: 'single' | 'married' | 'divorced' | 'widowed' | null
  phone: string | null
  personal_email: string | null
  address: string | null
  emergency_contact_name: string | null
  emergency_contact_phone: string | null
  national_id_number: string | null
  join_date: string | null
  company: RefOption | null
  branch: RefOption | null
  department: RefOption | null
  position: RefOption | null
  jobLevel: RefOption | null
  employmentType: RefOption | null
  employmentStatus: RefOption | null
  manager: { id: number; first_name: string; last_name: string | null } | null
}

interface EmployeeDocument {
  id: number
  category: string
  file_name: string
  file_size: number
  url: string
  notes: string | null
  created_at: string
}

const documentCategories: Record<string, string> = {
  ktp: 'KTP',
  npwp: 'NPWP',
  kartu_keluarga: 'Kartu Keluarga',
  ijazah: 'Ijazah',
  kontrak_kerja: 'Kontrak Kerja',
  skck: 'SKCK',
  lainnya: 'Lainnya',
}

const activeTab = ref<'personal' | 'employment' | 'files'>('personal')
const profile = ref<EmployeeProfile | null>(null)
const loading = ref(true)
const errorMessage = ref('')
const saving = ref(false)
const saveSuccess = ref(false)

const documents = ref<EmployeeDocument[]>([])
const documentsLoading = ref(false)
const documentsLoaded = ref(false)
const uploadCategory = ref('ktp')
const uploadFile = ref<File | null>(null)
const uploading = ref(false)
const documentError = ref('')

const maritalStatusLabels: Record<string, string> = {
  single: 'Belum Menikah',
  married: 'Menikah',
  divorced: 'Cerai',
  widowed: 'Janda/Duda',
}

const form = ref({
  birth_place: '',
  marital_status: '',
  phone: '',
  personal_email: '',
  address: '',
  emergency_contact_name: '',
  emergency_contact_phone: '',
})

function fillFormFromProfile(data: EmployeeProfile) {
  form.value = {
    birth_place: data.birth_place ?? '',
    marital_status: data.marital_status ?? '',
    phone: data.phone ?? '',
    personal_email: data.personal_email ?? '',
    address: data.address ?? '',
    emergency_contact_name: data.emergency_contact_name ?? '',
    emergency_contact_phone: data.emergency_contact_phone ?? '',
  }
}

async function loadProfile() {
  loading.value = true
  errorMessage.value = ''

  try {
    const response = await apiClient.get('/api/my-profile')
    const data = response.data.data

    profile.value = {
      id: data.id,
      employee_number: data.employee_number,
      first_name: data.first_name,
      last_name: data.last_name,
      photo_url: data.photo_url,
      gender: data.gender,
      birth_place: data.birth_place,
      birth_date: data.birth_date,
      marital_status: data.marital_status,
      phone: data.phone,
      personal_email: data.personal_email,
      address: data.address,
      emergency_contact_name: data.emergency_contact_name,
      emergency_contact_phone: data.emergency_contact_phone,
      national_id_number: data.national_id_number,
      join_date: data.join_date,

      company: data.company ?? null,
      branch: data.branch ?? null,
      department: data.department ?? null,
      position: data.position ?? null,
      jobLevel: data.job_level ?? null,
      employmentType: data.employment_type ?? null,
      employmentStatus: data.employment_status ?? null,
      manager: data.manager ?? null,
    }

    fillFormFromProfile(profile.value)
  } catch {
    errorMessage.value = 'Gagal memuat data profile.'
  } finally {
    loading.value = false
  }
}

async function saveProfile() {
  saving.value = true
  saveSuccess.value = false
  errorMessage.value = ''
  try {
    const response = await apiClient.put('/api/my-profile', form.value)
    profile.value = response.data.data
    fillFormFromProfile(profile.value as EmployeeProfile)
    saveSuccess.value = true
  } catch {
    errorMessage.value = 'Gagal menyimpan perubahan.'
  } finally {
    saving.value = false
  }
}

function refName(item: RefOption | null) {
  return item?.name ?? '-'
}

function formatFileSize(bytes: number) {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

async function loadDocuments() {
  documentsLoading.value = true
  documentError.value = ''
  try {
    const response = await apiClient.get('/api/my-documents')
    documents.value = response.data.data
    documentsLoaded.value = true
  } catch {
    documentError.value = 'Gagal memuat daftar dokumen.'
  } finally {
    documentsLoading.value = false
  }
}

function onFileSelected(event: Event) {
  const target = event.target as HTMLInputElement
  uploadFile.value = target.files?.[0] ?? null
}

async function uploadDocument() {
  if (!uploadFile.value) return
  uploading.value = true
  documentError.value = ''
  try {
    const payload = new FormData()
    payload.append('category', uploadCategory.value)
    payload.append('document', uploadFile.value)
    const response = await apiClient.post('/api/my-documents', payload, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    documents.value = [response.data.data, ...documents.value]
    uploadFile.value = null
  } catch {
    documentError.value = 'Gagal mengupload dokumen. Pastikan format JPG/PNG/PDF dan ukuran maksimal 5MB.'
  } finally {
    uploading.value = false
  }
}

async function deleteDocument(doc: EmployeeDocument) {
  try {
    await apiClient.delete(`/api/my-documents/${doc.id}`)
    documents.value = documents.value.filter((item) => item.id !== doc.id)
  } catch {
    documentError.value = 'Gagal menghapus dokumen.'
  }
}

function selectTab(tab: 'personal' | 'employment' | 'files') {
  activeTab.value = tab
  if (tab === 'files' && !documentsLoaded.value) {
    loadDocuments()
  }
}

function formatDate(date: string | null) {
  if (!date) return '-'

  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
    timeZone: 'Asia/Jakarta',
  }).format(new Date(date))
}

onMounted(loadProfile)
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Profile Saya</h1>
      <p class="mt-1 text-sm text-slate-500">Data pribadi dan kepegawaian Anda.</p>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>

    <template v-else-if="profile">
      <div class="rounded-xl bg-white p-5 shadow-sm">
        <div class="flex items-center gap-4">
            <div class="h-14 w-14 shrink-0 overflow-hidden rounded-full bg-primary-soft">
            <img
                v-if="profile.photo_url"
                :src="profile.photo_url"
                :alt="`${profile.first_name} ${profile.last_name ?? ''}`"
                class="h-full w-full object-cover"
            />

            <div
                v-else
                class="flex h-full w-full items-center justify-center text-lg font-semibold text-primary-dark"
            >
                {{ profile.first_name.charAt(0) }}{{ (profile.last_name ?? '').charAt(0) }}
            </div>
            </div>

            <div>
            <p class="text-lg font-semibold text-slate-900">
                {{ profile.first_name }} {{ profile.last_name }}
            </p>

            <p class="text-sm text-slate-500">
                {{ profile.employee_number }} &middot; {{ refName(profile.position) }}
            </p>
            </div>
        </div>
      </div>

      <div class="flex gap-1 border-b border-slate-200">
        <button
          type="button"
          class="px-4 py-2 text-sm font-medium"
          :class="activeTab === 'personal' ? 'border-b-2 border-primary text-primary' : 'text-slate-500 hover:text-slate-700'"
          @click="selectTab('personal')"
        >
          Personal
        </button>
        <button
          type="button"
          class="px-4 py-2 text-sm font-medium"
          :class="activeTab === 'employment' ? 'border-b-2 border-primary text-primary' : 'text-slate-500 hover:text-slate-700'"
          @click="selectTab('employment')"
        >
          Employment
        </button>
        <button
          type="button"
          class="px-4 py-2 text-sm font-medium"
          :class="activeTab === 'files' ? 'border-b-2 border-primary text-primary' : 'text-slate-500 hover:text-slate-700'"
          @click="selectTab('files')"
        >
          Files
        </button>
      </div>

      <div v-if="activeTab === 'personal'" class="rounded-xl bg-white p-5 shadow-sm">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Nama Lengkap</label>
            <input :value="`${profile.first_name} ${profile.last_name ?? ''}`" type="text" disabled class="w-full rounded-xl border border-slate-100 bg-slate-50 px-3 py-2 text-sm text-slate-500" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">NIK</label>
            <input :value="profile.national_id_number ?? '-'" type="text" disabled class="w-full rounded-xl border border-slate-100 bg-slate-50 px-3 py-2 text-sm text-slate-500" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Tempat Lahir</label>
            <input v-model="form.birth_place" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Status Pernikahan</label>
            <select v-model="form.marital_status" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
              <option value="">-</option>
              <option v-for="(label, value) in maritalStatusLabels" :key="value" :value="value">{{ label }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">No. Telepon</label>
            <input v-model="form.phone" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Email Pribadi</label>
            <input v-model="form.personal_email" type="email" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
          </div>
          <div class="col-span-2">
            <label class="mb-1 block text-sm font-medium text-slate-700">Alamat</label>
            <textarea v-model="form.address" rows="2" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"></textarea>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Nama Kontak Darurat</label>
            <input v-model="form.emergency_contact_name" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">No. Telepon Kontak Darurat</label>
            <input v-model="form.emergency_contact_phone" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
          </div>
        </div>

        <p class="mt-3 text-xs text-slate-400">Nama, NIK, jenis kelamin, dan tanggal lahir hanya bisa diubah oleh HR.</p>

        <div class="mt-5 flex items-center gap-3">
          <button
            type="button"
            :disabled="saving"
            class="rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
            @click="saveProfile"
          >
            {{ saving ? 'Menyimpan...' : 'Simpan Perubahan' }}
          </button>
          <span v-if="saveSuccess" class="text-sm text-emerald-600">Tersimpan.</span>
          <span v-if="errorMessage" class="text-sm text-red-600">{{ errorMessage }}</span>
        </div>
      </div>

      <div v-else-if="activeTab === 'employment'" class="rounded-xl bg-white p-5 shadow-sm">
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div>
            <p class="text-slate-500">Company</p>
            <p class="font-medium text-slate-900">{{ refName(profile.company) }}</p>
          </div>
          <div>
            <p class="text-slate-500">Branch</p>
            <p class="font-medium text-slate-900">{{ refName(profile.branch) }}</p>
          </div>
          <div>
            <p class="text-slate-500">Department</p>
            <p class="font-medium text-slate-900">{{ refName(profile.department) }}</p>
          </div>
          <div>
            <p class="text-slate-500">Position</p>
            <p class="font-medium text-slate-900">{{ refName(profile.position) }}</p>
          </div>
          <div>
            <p class="text-slate-500">Job Level</p>
            <p class="font-medium text-slate-900">{{ refName(profile.jobLevel) }}</p>
          </div>
          <div>
            <p class="text-slate-500">Employment Type</p>
            <p class="font-medium text-slate-900">{{ refName(profile.employmentType) }}</p>
          </div>
          <div>
            <p class="text-slate-500">Employment Status</p>
            <p class="font-medium text-slate-900">{{ refName(profile.employmentStatus) }}</p>
          </div>
          <div>
            <p class="text-slate-500">Manager</p>
            <p class="font-medium text-slate-900">{{ profile.manager ? `${profile.manager.first_name} ${profile.manager.last_name ?? ''}` : '-' }}</p>
          </div>
          <div>
            <p class="text-slate-500">Join Date</p>
            <p class="font-medium text-slate-900">{{ formatDate(profile.join_date) }}</p>
          </div>
        </div>
        <p class="mt-4 text-xs text-slate-400">Data Employment hanya bisa diubah lewat Employee Movement oleh HR/Admin.</p>
      </div>

      <div v-else class="rounded-xl bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-end gap-3">
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Kategori</label>
            <select v-model="uploadCategory" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
              <option v-for="(label, value) in documentCategories" :key="value" :value="value">{{ label }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">File (JPG/PNG/PDF, maks 5MB)</label>
            <input type="file" accept=".jpg,.jpeg,.png,.pdf" class="text-sm" @change="onFileSelected" />
          </div>
          <button
            type="button"
            :disabled="!uploadFile || uploading"
            class="rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
            @click="uploadDocument"
          >
            {{ uploading ? 'Mengupload...' : 'Upload' }}
          </button>
        </div>
        <p v-if="documentError" class="mt-2 text-sm text-red-600">{{ documentError }}</p>

        <div class="mt-5">
          <div v-if="documentsLoading" class="text-sm text-slate-400">Memuat dokumen...</div>
          <div v-else-if="documents.length === 0" class="rounded-xl bg-slate-50 p-6 text-center text-sm text-slate-400">
            Belum ada dokumen.
          </div>
          <div v-else class="divide-y divide-slate-100">
            <div v-for="document in documents" :key="document.id" class="flex items-center justify-between py-3">
              <div>
                <p class="text-sm font-medium text-slate-900">{{ documentCategories[document.category] ?? document.category }}</p>
                <p class="text-xs text-slate-500">{{ document.file_name }} &middot; {{ formatFileSize(document.file_size) }}</p>
              </div>
              <div class="flex items-center gap-3">
                <a :href="document.url" target="_blank" rel="noopener" class="text-sm font-medium text-primary hover:underline">Lihat</a>
                <button type="button" class="text-sm font-medium text-red-600 hover:underline" @click="deleteDocument(document)">Hapus</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>
  </div>
</template>