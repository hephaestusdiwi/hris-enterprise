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

interface EmployeeAsset {
  id: number
  asset_type: string
  asset_name: string
  serial_number: string | null
  condition: string
  assigned_date: string
  returned_date: string | null
  notes: string | null
}

const assetTypeLabels: Record<string, string> = {
  laptop: 'Laptop',
  mobile_phone: 'HP',
  id_card: 'Kartu ID',
  access_card: 'Kartu Akses',
  vehicle: 'Kendaraan',
  other: 'Lainnya',
}

const conditionLabels: Record<string, string> = {
  new: 'Baru',
  good: 'Baik',
  fair: 'Cukup',
  damaged: 'Rusak',
}

interface EmployeeEducation {
  id: number
  education_level: string
  institution_name: string
  major: string | null
  start_date: string | null
  end_date: string | null
  graduation_status: string
  description: string | null
  attachment_name: string | null
  attachment_url: string | null
}

interface EmployeeExperience {
  id: number
  company_name: string
  position_title: string
  employment_type: string | null
  start_date: string
  end_date: string | null
  description: string | null
  reason_for_leaving: string | null
}

const educationLevelLabels: Record<string, string> = {
  sd: 'SD',
  smp: 'SMP',
  sma_smk: 'SMA/SMK',
  d1: 'D1',
  d2: 'D2',
  d3: 'D3',
  d4: 'D4',
  s1: 'S1',
  s2: 'S2',
  s3: 'S3',
}

const graduationStatusLabels: Record<string, string> = {
  ongoing: 'Sedang Berjalan',
  graduated: 'Lulus',
  dropped_out: 'Tidak Selesai',
}

const employmentTypeLabels: Record<string, string> = {
  full_time: 'Full-time',
  part_time: 'Part-time',
  contract: 'Kontrak',
  internship: 'Magang',
  freelance: 'Freelance',
}

interface EmployeeReprimand {
  id: number
  reprimand_type: string
  title: string
  date: string
  reason: string
  status: string
  attachment_name: string | null
  attachment_url: string | null
  void_reason: string | null
}

const reprimandTypeLabels: Record<string, string> = {
  verbal_warning: 'Teguran Lisan',
  sp1: 'SP 1',
  sp2: 'SP 2',
  sp3: 'SP 3',
  termination_notice: 'Surat Pemutusan',
}

const activeTab = ref<'personal' | 'employment' | 'files' | 'assets' | 'education' | 'reprimands'>('personal')
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

const assets = ref<EmployeeAsset[]>([])
const assetsLoading = ref(false)
const assetsLoaded = ref(false)
const assetError = ref('')

const educations = ref<EmployeeEducation[]>([])
const educationsLoading = ref(false)
const educationsLoaded = ref(false)
const educationError = ref('')
const editingEducationId = ref<number | null>(null)
const educationForm = ref({
  education_level: 's1',
  institution_name: '',
  major: '',
  start_date: '',
  end_date: '',
  graduation_status: 'graduated',
  description: '',
})

const experiences = ref<EmployeeExperience[]>([])
const experiencesLoading = ref(false)
const experiencesLoaded = ref(false)
const experienceError = ref('')
const editingExperienceId = ref<number | null>(null)
const experienceForm = ref({
  company_name: '',
  position_title: '',
  employment_type: '',
  start_date: '',
  end_date: '',
  description: '',
  reason_for_leaving: '',
})

const reprimands = ref<EmployeeReprimand[]>([])
const reprimandsLoading = ref(false)
const reprimandsLoaded = ref(false)
const reprimandError = ref('')

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

function normalizeProfile(data: any): EmployeeProfile {
  return {
    id: data.id,
    employee_number: data.employee_number,
    first_name: data.first_name,
    last_name: data.last_name ?? null,
    photo_url: data.photo_url ?? null,
    gender: data.gender,
    birth_place: data.birth_place ?? null,
    birth_date: data.birth_date ?? null,
    marital_status: data.marital_status ?? null,
    phone: data.phone ?? null,
    personal_email: data.personal_email ?? null,
    address: data.address ?? null,
    emergency_contact_name: data.emergency_contact_name ?? null,
    emergency_contact_phone: data.emergency_contact_phone ?? null,
    national_id_number: data.national_id_number ?? null,
    join_date: data.join_date ?? null,
    company: data.company ?? null,
    branch: data.branch ?? null,
    department: data.department ?? null,
    position: data.position ?? null,
    jobLevel: data.job_level ?? data.jobLevel ?? null,
    employmentType: data.employment_type ?? data.employmentType ?? null,
    employmentStatus: data.employment_status ?? data.employmentStatus ?? null,
    manager: data.manager ?? null,
  }
}

async function loadProfile() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/my-profile')
    profile.value = normalizeProfile(response.data.data)
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
    profile.value = normalizeProfile(response.data.data)
    fillFormFromProfile(profile.value)
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

function formatDate(date: string | null) {
  if (!date) return '-'

  const parsed = new Date(date)
  if (Number.isNaN(parsed.getTime())) return date

  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
    timeZone: 'Asia/Jakarta',
  }).format(parsed)
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

async function loadAssets() {
  assetsLoading.value = true
  assetError.value = ''
  try {
    const response = await apiClient.get('/api/my-assets')
    assets.value = response.data.data
    assetsLoaded.value = true
  } catch {
    assetError.value = 'Gagal memuat daftar asset.'
  } finally {
    assetsLoading.value = false
  }
}

async function loadEducations() {
  educationsLoading.value = true
  educationError.value = ''
  try {
    const response = await apiClient.get('/api/my-educations')
    educations.value = response.data.data
    educationsLoaded.value = true
  } catch {
    educationError.value = 'Gagal memuat riwayat pendidikan.'
  } finally {
    educationsLoading.value = false
  }
}

function resetEducationForm() {
  editingEducationId.value = null
  educationForm.value = {
    education_level: 's1',
    institution_name: '',
    major: '',
    start_date: '',
    end_date: '',
    graduation_status: 'graduated',
    description: '',
  }
}

function editEducation(item: EmployeeEducation) {
  editingEducationId.value = item.id
  educationForm.value = {
    education_level: item.education_level,
    institution_name: item.institution_name,
    major: item.major ?? '',
    start_date: item.start_date ?? '',
    end_date: item.end_date ?? '',
    graduation_status: item.graduation_status,
    description: item.description ?? '',
  }
}

async function saveEducation() {
  educationError.value = ''
  try {
    if (editingEducationId.value) {
      const response = await apiClient.post(`/api/my-educations/${editingEducationId.value}`, educationForm.value)
      educations.value = educations.value.map((item) => (item.id === editingEducationId.value ? response.data.data : item))
    } else {
      const response = await apiClient.post('/api/my-educations', educationForm.value)
      educations.value = [response.data.data, ...educations.value]
    }
    resetEducationForm()
  } catch {
    educationError.value = 'Gagal menyimpan riwayat pendidikan.'
  }
}

async function deleteEducation(item: EmployeeEducation) {
  try {
    await apiClient.delete(`/api/my-educations/${item.id}`)
    educations.value = educations.value.filter((e) => e.id !== item.id)
  } catch {
    educationError.value = 'Gagal menghapus riwayat pendidikan.'
  }
}

async function loadExperiences() {
  experiencesLoading.value = true
  experienceError.value = ''
  try {
    const response = await apiClient.get('/api/my-experiences')
    experiences.value = response.data.data
    experiencesLoaded.value = true
  } catch {
    experienceError.value = 'Gagal memuat riwayat pengalaman kerja.'
  } finally {
    experiencesLoading.value = false
  }
}

function resetExperienceForm() {
  editingExperienceId.value = null
  experienceForm.value = {
    company_name: '',
    position_title: '',
    employment_type: '',
    start_date: '',
    end_date: '',
    description: '',
    reason_for_leaving: '',
  }
}

function editExperience(item: EmployeeExperience) {
  editingExperienceId.value = item.id
  experienceForm.value = {
    company_name: item.company_name,
    position_title: item.position_title,
    employment_type: item.employment_type ?? '',
    start_date: item.start_date,
    end_date: item.end_date ?? '',
    description: item.description ?? '',
    reason_for_leaving: item.reason_for_leaving ?? '',
  }
}

async function saveExperience() {
  experienceError.value = ''
  try {
    if (editingExperienceId.value) {
      const response = await apiClient.put(`/api/my-experiences/${editingExperienceId.value}`, experienceForm.value)
      experiences.value = experiences.value.map((item) => (item.id === editingExperienceId.value ? response.data.data : item))
    } else {
      const response = await apiClient.post('/api/my-experiences', experienceForm.value)
      experiences.value = [response.data.data, ...experiences.value]
    }
    resetExperienceForm()
  } catch {
    experienceError.value = 'Gagal menyimpan riwayat pengalaman kerja.'
  }
}

async function deleteExperience(item: EmployeeExperience) {
  try {
    await apiClient.delete(`/api/my-experiences/${item.id}`)
    experiences.value = experiences.value.filter((e) => e.id !== item.id)
  } catch {
    experienceError.value = 'Gagal menghapus riwayat pengalaman kerja.'
  }
}

async function loadReprimands() {
  reprimandsLoading.value = true
  reprimandError.value = ''
  try {
    const response = await apiClient.get('/api/my-reprimands')
    reprimands.value = response.data.data
    reprimandsLoaded.value = true
  } catch {
    reprimandError.value = 'Gagal memuat riwayat reprimand.'
  } finally {
    reprimandsLoading.value = false
  }
}

function selectTab(tab: 'personal' | 'employment' | 'files' | 'assets' | 'education' | 'reprimands') {
  activeTab.value = tab
  if (tab === 'files' && !documentsLoaded.value) {
    loadDocuments()
  }
  if (tab === 'assets' && !assetsLoaded.value) {
    loadAssets()
  }
  if (tab === 'education') {
    if (!educationsLoaded.value) loadEducations()
    if (!experiencesLoaded.value) loadExperiences()
  }
  if (tab === 'reprimands' && !reprimandsLoaded.value) {
    loadReprimands()
  }
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
            <p class="text-lg font-semibold text-slate-900">{{ profile.first_name }} {{ profile.last_name }}</p>
            <p class="text-sm text-slate-500">{{ profile.employee_number }} &middot; {{ refName(profile.position) }}</p>
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
        <button
          type="button"
          class="px-4 py-2 text-sm font-medium"
          :class="activeTab === 'assets' ? 'border-b-2 border-primary text-primary' : 'text-slate-500 hover:text-slate-700'"
          @click="selectTab('assets')"
        >
          Assets
        </button>
        <button
          type="button"
          class="px-4 py-2 text-sm font-medium"
          :class="activeTab === 'education' ? 'border-b-2 border-primary text-primary' : 'text-slate-500 hover:text-slate-700'"
          @click="selectTab('education')"
        >
          Education &amp; Experience
        </button>
        <button
          type="button"
          class="px-4 py-2 text-sm font-medium"
          :class="activeTab === 'reprimands' ? 'border-b-2 border-primary text-primary' : 'text-slate-500 hover:text-slate-700'"
          @click="selectTab('reprimands')"
        >
          History
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

      <div v-else-if="activeTab === 'files'" class="rounded-xl bg-white p-5 shadow-sm">
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
            <div v-for="doc in documents" :key="doc.id" class="flex items-center justify-between py-3">
              <div>
                <p class="text-sm font-medium text-slate-900">{{ documentCategories[doc.category] ?? doc.category }}</p>
                <p class="text-xs text-slate-500">{{ doc.file_name }} &middot; {{ formatFileSize(doc.file_size) }}</p>
              </div>
              <div class="flex items-center gap-3">
                <a :href="doc.url" target="_blank" rel="noopener" class="text-sm font-medium text-primary hover:underline">Lihat</a>
                <button type="button" class="text-sm font-medium text-red-600 hover:underline" @click="deleteDocument(doc)">Hapus</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-else-if="activeTab === 'assets'" class="rounded-xl bg-white p-5 shadow-sm">
        <p v-if="assetError" class="mb-3 text-sm text-red-600">{{ assetError }}</p>
        <div v-if="assetsLoading" class="text-sm text-slate-400">Memuat asset...</div>
        <div v-else-if="assets.length === 0" class="rounded-xl bg-slate-50 p-6 text-center text-sm text-slate-400">
          Belum ada asset yang di-assign.
        </div>
        <div v-else class="divide-y divide-slate-100">
          <div v-for="asset in assets" :key="asset.id" class="py-3">
            <div class="flex items-center justify-between">
              <p class="text-sm font-medium text-slate-900">{{ asset.asset_name }}</p>
              <span class="text-xs text-slate-500">{{ assetTypeLabels[asset.asset_type] ?? asset.asset_type }}</span>
            </div>
            <p class="text-xs text-slate-500">
              {{ conditionLabels[asset.condition] ?? asset.condition }}
              <span v-if="asset.serial_number">&middot; SN: {{ asset.serial_number }}</span>
              &middot; Diterima {{ asset.assigned_date }}
              <span v-if="asset.returned_date">&middot; Dikembalikan {{ asset.returned_date }}</span>
            </p>
          </div>
        </div>
        <p class="mt-4 text-xs text-slate-400">Assignment asset hanya bisa dilakukan HR/Admin.</p>
      </div>

      <div v-else-if="activeTab === 'education'" class="space-y-4">
        <div class="rounded-xl bg-white p-5 shadow-sm">
          <h2 class="mb-3 text-sm font-semibold text-slate-900">Riwayat Pendidikan</h2>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Jenjang</label>
              <select v-model="educationForm.education_level" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                <option v-for="(label, value) in educationLevelLabels" :key="value" :value="value">{{ label }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
              <select v-model="educationForm.graduation_status" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                <option v-for="(label, value) in graduationStatusLabels" :key="value" :value="value">{{ label }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Institusi</label>
              <input v-model="educationForm.institution_name" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Jurusan</label>
              <input v-model="educationForm.major" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Mulai</label>
              <input v-model="educationForm.start_date" type="date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Selesai</label>
              <input v-model="educationForm.end_date" type="date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
            </div>
          </div>
          <div class="mt-3 flex items-center gap-3">
            <button type="button" class="rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white" @click="saveEducation">
              {{ editingEducationId ? 'Simpan Perubahan' : 'Tambah' }}
            </button>
            <button v-if="editingEducationId" type="button" class="text-sm text-slate-500 hover:underline" @click="resetEducationForm">Batal</button>
            <span v-if="educationError" class="text-sm text-red-600">{{ educationError }}</span>
          </div>

          <div class="mt-5">
            <div v-if="educationsLoading" class="text-sm text-slate-400">Memuat...</div>
            <div v-else-if="educations.length === 0" class="rounded-xl bg-slate-50 p-6 text-center text-sm text-slate-400">Belum ada riwayat pendidikan.</div>
            <div v-else class="divide-y divide-slate-100">
              <div v-for="edu in educations" :key="edu.id" class="flex items-center justify-between py-3">
                <div>
                  <p class="text-sm font-medium text-slate-900">{{ educationLevelLabels[edu.education_level] ?? edu.education_level }} &middot; {{ edu.institution_name }}</p>
                  <p class="text-xs text-slate-500">
                    {{ edu.major ?? '-' }} &middot; {{ graduationStatusLabels[edu.graduation_status] ?? edu.graduation_status }}
                    <span v-if="edu.start_date">&middot; {{ edu.start_date }} s/d {{ edu.end_date ?? 'sekarang' }}</span>
                  </p>
                </div>
                <div class="flex items-center gap-3">
                  <button type="button" class="text-sm font-medium text-primary hover:underline" @click="editEducation(edu)">Edit</button>
                  <button type="button" class="text-sm font-medium text-red-600 hover:underline" @click="deleteEducation(edu)">Hapus</button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="rounded-xl bg-white p-5 shadow-sm">
          <h2 class="mb-3 text-sm font-semibold text-slate-900">Pengalaman Kerja</h2>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Perusahaan</label>
              <input v-model="experienceForm.company_name" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Posisi</label>
              <input v-model="experienceForm.position_title" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Tipe</label>
              <select v-model="experienceForm.employment_type" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                <option value="">-</option>
                <option v-for="(label, value) in employmentTypeLabels" :key="value" :value="value">{{ label }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Alasan Keluar</label>
              <input v-model="experienceForm.reason_for_leaving" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Mulai</label>
              <input v-model="experienceForm.start_date" type="date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Selesai</label>
              <input v-model="experienceForm.end_date" type="date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
            </div>
            <div class="col-span-2">
              <label class="mb-1 block text-sm font-medium text-slate-700">Deskripsi</label>
              <textarea v-model="experienceForm.description" rows="2" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"></textarea>
            </div>
          </div>
          <div class="mt-3 flex items-center gap-3">
            <button type="button" class="rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white" @click="saveExperience">
              {{ editingExperienceId ? 'Simpan Perubahan' : 'Tambah' }}
            </button>
            <button v-if="editingExperienceId" type="button" class="text-sm text-slate-500 hover:underline" @click="resetExperienceForm">Batal</button>
            <span v-if="experienceError" class="text-sm text-red-600">{{ experienceError }}</span>
          </div>

          <div class="mt-5">
            <div v-if="experiencesLoading" class="text-sm text-slate-400">Memuat...</div>
            <div v-else-if="experiences.length === 0" class="rounded-xl bg-slate-50 p-6 text-center text-sm text-slate-400">Belum ada riwayat pengalaman kerja.</div>
            <div v-else class="divide-y divide-slate-100">
              <div v-for="exp in experiences" :key="exp.id" class="flex items-center justify-between py-3">
                <div>
                  <p class="text-sm font-medium text-slate-900">{{ exp.position_title }} &middot; {{ exp.company_name }}</p>
                  <p class="text-xs text-slate-500">
                    {{ exp.employment_type ? employmentTypeLabels[exp.employment_type] ?? exp.employment_type : '-' }}
                    &middot; {{ exp.start_date }} s/d {{ exp.end_date ?? 'sekarang' }}
                  </p>
                </div>
                <div class="flex items-center gap-3">
                  <button type="button" class="text-sm font-medium text-primary hover:underline" @click="editExperience(exp)">Edit</button>
                  <button type="button" class="text-sm font-medium text-red-600 hover:underline" @click="deleteExperience(exp)">Hapus</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-else class="rounded-xl bg-white p-5 shadow-sm">
        <p v-if="reprimandError" class="mb-3 text-sm text-red-600">{{ reprimandError }}</p>
        <div v-if="reprimandsLoading" class="text-sm text-slate-400">Memuat riwayat...</div>
        <div v-else-if="reprimands.length === 0" class="rounded-xl bg-slate-50 p-6 text-center text-sm text-slate-400">
          Tidak ada riwayat reprimand.
        </div>
        <div v-else class="divide-y divide-slate-100">
          <div v-for="item in reprimands" :key="item.id" class="py-3">
            <div class="flex items-center justify-between">
              <p class="text-sm font-medium text-slate-900">{{ reprimandTypeLabels[item.reprimand_type] ?? item.reprimand_type }} &middot; {{ item.title }}</p>
              <span
                class="rounded-full px-2 py-0.5 text-xs font-medium"
                :class="item.status === 'void' ? 'bg-slate-100 text-slate-500' : 'bg-red-50 text-red-600'"
              >
                {{ item.status === 'void' ? 'Void' : 'Aktif' }}
              </span>
            </div>
            <p class="text-xs text-slate-500">{{ item.date }} &middot; {{ item.reason }}</p>
            <p v-if="item.status === 'void' && item.void_reason" class="text-xs italic text-slate-400">Dibatalkan: {{ item.void_reason }}</p>
            <a v-if="item.attachment_url" :href="item.attachment_url" target="_blank" rel="noopener" class="text-xs font-medium text-primary hover:underline">Lihat lampiran</a>
          </div>
        </div>
        <p class="mt-4 text-xs text-slate-400">Riwayat reprimand hanya bisa dibuat/diubah oleh HR/Admin dan tidak pernah dihapus permanen (void saja).</p>
      </div>
    </template>

    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>
  </div>
</template>