<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { ArrowLeft, Users, UserRound, GitBranch } from 'lucide-vue-next'
import apiClient from '@/lib/axios'
import { useAuthStore } from '@/stores/auth'
import EmployeeMovementFormModal from '@/components/employee/EmployeeMovementFormModal.vue'
import EmployeeReprimandFormModal from '@/components/employee/EmployeeReprimandFormModal.vue'
import EmployeeCompanyDocumentFormModal from '@/components/employee/EmployeeCompanyDocumentFormModal.vue'
import EmployeeAssetFormModal from '@/components/employee/EmployeeAssetFormModal.vue'
import EmployeeDocumentFormModal from '@/components/employee/EmployeeDocumentFormModal.vue'
import EmployeeEducationFormModal from '@/components/employee/EmployeeEducationFormModal.vue'
import EmployeeExperienceFormModal from '@/components/employee/EmployeeExperienceFormModal.vue'

interface EmployeeFileRow {
  id: number
  category: string
  file_name: string
  file_size: number
  url: string
}

const fileCategoryLabels: Record<string, string> = {
  ktp: 'KTP',
  npwp: 'NPWP',
  kartu_keluarga: 'Kartu Keluarga',
  ijazah: 'Ijazah',
  kontrak_kerja: 'Kontrak Kerja',
  skck: 'SKCK',
  lainnya: 'Lainnya',
}

interface EmployeeEducationRow {
  id: number
  education_level: string
  institution_name: string
  major: string | null
  start_date: string | null
  end_date: string | null
  graduation_status: string
  description: string | null
}

const educationLevelLabels: Record<string, string> = {
  sd: 'SD', smp: 'SMP', sma_smk: 'SMA/SMK', d1: 'D1', d2: 'D2', d3: 'D3', d4: 'D4', s1: 'S1', s2: 'S2', s3: 'S3',
}
const graduationStatusLabels: Record<string, string> = {
  ongoing: 'Sedang Berjalan', graduated: 'Lulus', dropped_out: 'Tidak Selesai',
}

interface EmployeeExperienceRow {
  id: number
  company_name: string
  position_title: string
  employment_type: string | null
  start_date: string
  end_date: string | null
  description: string | null
  reason_for_leaving: string | null
}

const employmentTypeLabels: Record<string, string> = {
  full_time: 'Full-time', part_time: 'Part-time', contract: 'Kontrak', internship: 'Magang', freelance: 'Freelance',
}

interface EmployeeAssetRow {
  id: number
  asset_type: string
  asset_name: string
  serial_number: string | null
  condition: string
  assigned_date: string
  returned_date: string | null
}

const assetTypeLabels: Record<string, string> = {
  laptop: 'Laptop',
  mobile_phone: 'HP',
  id_card: 'Kartu ID',
  access_card: 'Kartu Akses',
  vehicle: 'Kendaraan',
  other: 'Lainnya',
}

const assetConditionLabels: Record<string, string> = {
  new: 'Baru',
  good: 'Baik',
  fair: 'Cukup',
  damaged: 'Rusak',
}

interface EmployeeCompanyDocument {
  id: number
  category: string
  visibility: string
  title: string
  description: string | null
  module_context: string | null
  url: string
}

const companyDocumentCategoryLabels: Record<string, string> = {
  sop: 'SOP',
  handbook: 'Handbook',
  work_guide: 'Panduan Kerja',
  form_template: 'Template Formulir',
  ktp: 'KTP',
  bank_account: 'Rekening Bank',
  employment_contract: 'Kontrak Kerja',
  disciplinary: 'Dokumen Disiplin',
}

const authStore = useAuthStore()

interface EmployeeReprimand {
  id: number
  reprimand_type: string
  title: string
  date: string
  reason: string
  status: string
  void_reason: string | null
  attachment_url: string | null
}

const reprimandTypeLabels: Record<string, string> = {
  verbal_warning: 'Teguran Lisan',
  sp1: 'SP 1',
  sp2: 'SP 2',
  sp3: 'SP 3',
  termination_notice: 'Surat Pemutusan',
}

interface HierarchyPerson {
  id: number
  name: string
  position: string | null
  photo_url: string | null
}

interface EmployeeDetail {
  id: number
  employee_number: string
  first_name: string
  last_name: string | null
  photo_url: string | null
  company_id: number | null
  branch_id: number | null
  department_id: number | null
  position_id: number | null
  job_level_id: number | null
  manager_employee_id: number | null
  employment_type_id: number | null
  employment_status_id: number | null
  contract_start_date: string | null
  contract_end_date: string | null
  probation_end_date: string | null
  resign_date: string | null
  join_date: string | null
  company: { id: number; name: string } | null
  branch: { id: number; name: string } | null
  department: { id: number; name: string } | null
  position: { id: number; name: string } | null
  job_level: { id: number; name: string } | null
  employment_status: { id: number; name: string } | null
  employment_type: { id: number; name: string } | null
}

const route = useRoute()
const router = useRouter()

const employee = ref<EmployeeDetail | null>(null)
const manager = ref<HierarchyPerson | null>(null)
const directReports = ref<HierarchyPerson[]>([])

const loading = ref(true)
const errorMessage = ref('')

const employeeId = computed(() => Number(route.params.id))

function remainingDays(dateStr: string | null): number | null {
  if (!dateStr) return null
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  const end = new Date(dateStr)
  end.setHours(0, 0, 0, 0)
  return Math.round((end.getTime() - today.getTime()) / 86400000)
}

const contractRemaining = computed(() => remainingDays(employee.value?.contract_end_date ?? null))
const probationRemaining = computed(() => remainingDays(employee.value?.probation_end_date ?? null))

const movementModalType = ref<string | null>(null)
function onMovementCreated() {
  movementModalType.value = null
  loadEmployee(employeeId.value) // refresh info dasar (nggak berubah sampai movement di-approve, tapi aman di-refresh)
}

const reprimands = ref<EmployeeReprimand[]>([])
const reprimandsLoading = ref(false)
const reprimandError = ref('')
const showReprimandModal = ref(false)
const voidingReprimandId = ref<number | null>(null)
const voidReason = ref('')

async function loadReprimands(id: number) {
  reprimandsLoading.value = true
  try {
    const response = await apiClient.get(`/api/employees/${id}/reprimands`)
    reprimands.value = response.data.data.data ?? response.data.data
  } catch {
    reprimandError.value = 'Gagal memuat riwayat reprimand.'
  } finally {
    reprimandsLoading.value = false
  }
}

function onReprimandCreated() {
  showReprimandModal.value = false
  loadReprimands(employeeId.value)
}

function startVoid(reprimand: EmployeeReprimand) {
  voidingReprimandId.value = reprimand.id
  voidReason.value = ''
}

async function confirmVoid() {
  if (!voidingReprimandId.value || !voidReason.value) return
  reprimandError.value = ''
  try {
    await apiClient.post(`/api/employees/${employeeId.value}/reprimands/${voidingReprimandId.value}/void`, {
      reason: voidReason.value,
    })
    voidingReprimandId.value = null
    loadReprimands(employeeId.value)
  } catch {
    reprimandError.value = 'Gagal meng-void reprimand.'
  }
}

const companyDocuments = ref<EmployeeCompanyDocument[]>([])
const companyDocumentsLoading = ref(false)
const companyDocumentError = ref('')
const showCompanyDocumentModal = ref(false)

async function loadCompanyDocuments(id: number) {
  companyDocumentsLoading.value = true
  try {
    const response = await apiClient.get(`/api/employees/${id}/company-documents`)
    companyDocuments.value = response.data.data
  } catch {
    companyDocumentError.value = 'Gagal memuat dokumen.'
  } finally {
    companyDocumentsLoading.value = false
  }
}

function onCompanyDocumentCreated() {
  showCompanyDocumentModal.value = false
  loadCompanyDocuments(employeeId.value)
}

async function deleteCompanyDocument(doc: EmployeeCompanyDocument) {
  try {
    await apiClient.delete(`/api/employees/${employeeId.value}/company-documents/${doc.id}`)
    companyDocuments.value = companyDocuments.value.filter((d) => d.id !== doc.id)
  } catch {
    companyDocumentError.value = 'Gagal menghapus dokumen (cek permission Anda untuk kategori ini).'
  }
}

const assets = ref<EmployeeAssetRow[]>([])
const assetsLoading = ref(false)
const assetError = ref('')
const showAssetModal = ref(false)

async function loadAssets(id: number) {
  assetsLoading.value = true
  try {
    const response = await apiClient.get(`/api/employees/${id}/assets`)
    assets.value = response.data.data
  } catch {
    assetError.value = 'Gagal memuat data asset.'
  } finally {
    assetsLoading.value = false
  }
}

function onAssetCreated() {
  showAssetModal.value = false
  loadAssets(employeeId.value)
}

async function markAssetReturned(asset: EmployeeAssetRow) {
  try {
    await apiClient.put(`/api/employees/${employeeId.value}/assets/${asset.id}`, {
      returned_date: new Date().toISOString().slice(0, 10),
    })
    loadAssets(employeeId.value)
  } catch {
    assetError.value = 'Gagal update status asset.'
  }
}

async function deleteAsset(asset: EmployeeAssetRow) {
  try {
    await apiClient.delete(`/api/employees/${employeeId.value}/assets/${asset.id}`)
    assets.value = assets.value.filter((a) => a.id !== asset.id)
  } catch {
    assetError.value = 'Gagal menghapus asset.'
  }
}

const files = ref<EmployeeFileRow[]>([])
const filesLoading = ref(false)
const fileError = ref('')
const showFileModal = ref(false)

async function loadFiles(id: number) {
  filesLoading.value = true
  try {
    const response = await apiClient.get(`/api/employees/${id}/documents`)
    files.value = response.data.data
  } catch {
    fileError.value = 'Gagal memuat dokumen (Files).'
  } finally {
    filesLoading.value = false
  }
}

function onFileCreated() {
  showFileModal.value = false
  loadFiles(employeeId.value)
}

async function deleteFile(file: EmployeeFileRow) {
  try {
    await apiClient.delete(`/api/employees/${employeeId.value}/documents/${file.id}`)
    files.value = files.value.filter((f) => f.id !== file.id)
  } catch {
    fileError.value = 'Gagal menghapus dokumen.'
  }
}

const educations = ref<EmployeeEducationRow[]>([])
const educationsLoading = ref(false)
const educationError = ref('')
const showEducationModal = ref(false)
const editingEducation = ref<EmployeeEducationRow | null>(null)

async function loadEducations(id: number) {
  educationsLoading.value = true
  try {
    const response = await apiClient.get(`/api/employees/${id}/educations`)
    educations.value = response.data.data
  } catch {
    educationError.value = 'Gagal memuat riwayat pendidikan.'
  } finally {
    educationsLoading.value = false
  }
}

function openCreateEducation() {
  editingEducation.value = null
  showEducationModal.value = true
}

function openEditEducation(item: EmployeeEducationRow) {
  editingEducation.value = item
  showEducationModal.value = true
}

function onEducationSaved() {
  showEducationModal.value = false
  loadEducations(employeeId.value)
}

async function deleteEducation(item: EmployeeEducationRow) {
  try {
    await apiClient.delete(`/api/employees/${employeeId.value}/educations/${item.id}`)
    educations.value = educations.value.filter((e) => e.id !== item.id)
  } catch {
    educationError.value = 'Gagal menghapus riwayat pendidikan.'
  }
}

const experiences = ref<EmployeeExperienceRow[]>([])
const experiencesLoading = ref(false)
const experienceError = ref('')
const showExperienceModal = ref(false)
const editingExperience = ref<EmployeeExperienceRow | null>(null)

async function loadExperiences(id: number) {
  experiencesLoading.value = true
  try {
    const response = await apiClient.get(`/api/employees/${id}/experiences`)
    experiences.value = response.data.data
  } catch {
    experienceError.value = 'Gagal memuat riwayat pengalaman kerja.'
  } finally {
    experiencesLoading.value = false
  }
}

function openCreateExperience() {
  editingExperience.value = null
  showExperienceModal.value = true
}

function openEditExperience(item: EmployeeExperienceRow) {
  editingExperience.value = item
  showExperienceModal.value = true
}

function onExperienceSaved() {
  showExperienceModal.value = false
  loadExperiences(employeeId.value)
}

async function deleteExperience(item: EmployeeExperienceRow) {
  try {
    await apiClient.delete(`/api/employees/${employeeId.value}/experiences/${item.id}`)
    experiences.value = experiences.value.filter((e) => e.id !== item.id)
  } catch {
    experienceError.value = 'Gagal menghapus riwayat pengalaman kerja.'
  }
}

const fullName = computed(() => {
  if (!employee.value) return ''
  return [employee.value.first_name, employee.value.last_name].filter(Boolean).join(' ')
})

const initials = computed(() =>
  fullName.value
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0]?.toUpperCase())
    .join(''),
)

async function loadEmployee(id: number) {
  loading.value = true
  errorMessage.value = ''
  employee.value = null
  manager.value = null
  directReports.value = []

  try {
    const [detailRes, hierarchyRes] = await Promise.all([
      apiClient.get(`/api/employees/${id}`),
      apiClient.get(`/api/employees/${id}/hierarchy`),
    ])

    employee.value = detailRes.data.data
    manager.value = hierarchyRes.data.data.manager
    directReports.value = hierarchyRes.data.data.direct_reports
  } catch (err: unknown) {
    const status = (err as { response?: { status?: number } })?.response?.status
    if (status === 403) {
      errorMessage.value = 'Kamu tidak punya akses untuk melihat data employee ini.'
    } else if (status === 404) {
      errorMessage.value = 'Employee tidak ditemukan.'
    } else {
      errorMessage.value = 'Gagal memuat data employee.'
    }
  } finally {
    loading.value = false
  }
}

function goToEmployee(id: number) {
  router.push({ name: 'employee-detail', params: { id } })
}

function loadPermissionGatedTabs(id: number) {
  if (authStore.permissions.includes('view employee reprimands')) {
    loadReprimands(id)
  }
  loadCompanyDocuments(id)
  if (authStore.permissions.includes('view employee assets')) {
    loadAssets(id)
  }
  if (authStore.permissions.includes('view employee documents')) {
    loadFiles(id)
  }
  if (authStore.permissions.includes('view employee education')) {
    loadEducations(id)
  }
  if (authStore.permissions.includes('view employee experience')) {
    loadExperiences(id)
  }
}

onMounted(() => {
  loadEmployee(employeeId.value)
  loadPermissionGatedTabs(employeeId.value)
})

// Route param bisa berubah (klik Manager/Direct Report) tanpa component
// di-unmount ulang oleh Vue Router — perlu di-watch supaya data ke-refresh.
watch(employeeId, (id) => {
  if (!Number.isNaN(id)) {
    loadEmployee(id)
    loadPermissionGatedTabs(id)
  }
})
</script>

<template>
  <div class="mx-auto max-w-4xl space-y-6 p-6">
    <button
      type="button"
      class="flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-700"
      @click="router.push({ name: 'employees' })"
    >
      <ArrowLeft class="h-4 w-4" :stroke-width="2" />
      Kembali ke Employee List
    </button>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">
      {{ errorMessage }}
    </div>

    <template v-else-if="employee">
      <!-- Header -->
      <div class="flex items-center gap-4 rounded-2xl border border-slate-100 bg-white p-6 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <img
          v-if="employee.photo_url"
          :src="employee.photo_url"
          class="h-16 w-16 rounded-full object-cover"
          alt=""
        />
        <div v-else class="flex h-16 w-16 items-center justify-center rounded-full bg-primary-soft text-lg font-semibold text-primary-dark">
          {{ initials }}
        </div>

        <div class="min-w-0 flex-1">
          <p class="truncate text-lg font-semibold text-slate-900">{{ fullName }}</p>
          <p class="mt-0.5 truncate text-sm text-slate-500">
            {{ employee.position?.name ?? 'Belum ada posisi' }}
            <span v-if="employee.department"> &middot; {{ employee.department.name }}</span>
          </p>
          <p class="mt-1 text-xs text-slate-400">{{ employee.employee_number }}</p>
        </div>

        <span
          v-if="employee.employment_status"
          class="shrink-0 rounded-full bg-slate-50 px-3 py-1 text-xs font-medium text-slate-500"
        >
          {{ employee.employment_status.name }}
        </span>
      </div>

      <div
        v-if="employee.contract_end_date || employee.probation_end_date"
        class="grid gap-4 sm:grid-cols-2"
      >
        <div v-if="employee.contract_end_date" class="rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
          <p class="text-xs font-medium uppercase tracking-wider text-slate-400">Contract End Date</p>
          <p class="mt-1 text-sm font-semibold text-slate-800">{{ employee.contract_end_date }}</p>
          <p
            v-if="contractRemaining !== null"
            class="mt-1 text-xs"
            :class="contractRemaining < 0 ? 'text-slate-400' : contractRemaining <= 7 ? 'font-medium text-red-600' : 'text-slate-500'"
          >
            {{ contractRemaining < 0 ? 'Sudah lewat' : contractRemaining === 0 ? 'Hari ini' : `${contractRemaining} hari lagi` }}
          </p>
        </div>

        <div v-if="employee.probation_end_date" class="rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
          <p class="text-xs font-medium uppercase tracking-wider text-slate-400">Probation End Date</p>
          <p class="mt-1 text-sm font-semibold text-slate-800">{{ employee.probation_end_date }}</p>
          <p
            v-if="probationRemaining !== null"
            class="mt-1 text-xs"
            :class="probationRemaining < 0 ? 'text-slate-400' : probationRemaining <= 7 ? 'font-medium text-red-600' : 'text-slate-500'"
          >
            {{ probationRemaining < 0 ? 'Sudah lewat' : probationRemaining === 0 ? 'Hari ini' : `${probationRemaining} hari lagi` }}
          </p>
        </div>
      </div>

      <div class="grid gap-6 sm:grid-cols-2">
        <!-- Manager -->
        <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
          <h2 class="text-sm font-semibold text-slate-700">Approval Line</h2>

          <button
            v-if="manager"
            type="button"
            class="mt-3 flex w-full items-center gap-3 rounded-xl border border-slate-100 p-3 text-left transition hover:border-primary/30 hover:bg-primary-soft/40"
            @click="goToEmployee(manager.id)"
          >
            <div class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-slate-100 text-slate-500">
              <img
                v-if="manager.photo_url"
                :src="manager.photo_url"
                :alt="manager.name"
                class="h-full w-full object-cover"
              />
              <UserRound
                v-else
                class="h-4 w-4"
                :stroke-width="2"
              />
            </div>
            <div class="min-w-0">
              <p class="truncate text-sm font-medium text-slate-800">{{ manager.name }}</p>
              <p v-if="manager.position" class="truncate text-xs text-slate-400">{{ manager.position }}</p>
            </div>
          </button>

          <div v-else class="mt-3 rounded-xl bg-slate-50 p-4 text-center text-sm text-slate-400">
            Employee ini tidak memiliki manager (posisi teratas struktur organisasi).
          </div>
        </div>

        <!-- Direct Reports -->
        <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
          <h2 class="flex items-center gap-1.5 text-sm font-semibold text-slate-700">
            Direct Reports
            <span v-if="directReports.length" class="rounded-full bg-slate-50 px-2 py-0.5 text-xs font-medium text-slate-400">
              {{ directReports.length }}
            </span>
          </h2>

          <div v-if="directReports.length" class="mt-3 space-y-2">
            <button
              v-for="report in directReports"
              :key="report.id"
              type="button"
              class="flex w-full items-center gap-3 rounded-xl border border-slate-100 p-3 text-left transition hover:border-primary/30 hover:bg-primary-soft/40"
              @click="goToEmployee(report.id)"
            >
              <div class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-slate-100 text-slate-500">
                <img
                  v-if="report.photo_url"
                  :src="report.photo_url"
                  :alt="report.name"
                  class="h-full w-full object-cover"
                />
                <UserRound
                  v-else
                  class="h-4 w-4"
                  :stroke-width="2"
                />
              </div>
              <div class="min-w-0">
                <p class="truncate text-sm font-medium text-slate-800">{{ report.name }}</p>
                <p v-if="report.position" class="truncate text-xs text-slate-400">{{ report.position }}</p>
              </div>
            </button>
          </div>

          <div v-else class="mt-3 rounded-xl bg-slate-50 p-4 text-center text-sm text-slate-400">
            <Users class="mx-auto mb-1.5 h-5 w-5 text-slate-300" :stroke-width="1.5" />
            Belum ada direct report.
          </div>
        </div>
      </div>

      <!-- Employee Movement actions — SEMUA perubahan lifecycle field lewat
           sini, bukan edit langsung. -->
      <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <div class="flex items-center justify-between">
          <h2 class="text-sm font-semibold text-slate-700">Employment Actions</h2>
          <RouterLink
            :to="{ name: 'employee-movements' }"
            class="flex items-center gap-1 text-xs font-medium text-primary-dark hover:underline"
          >
            <GitBranch class="h-3.5 w-3.5" :stroke-width="2" />
            Lihat Riwayat Movement
          </RouterLink>
        </div>
        <div class="mt-3 flex flex-wrap gap-2">
          <button type="button" @click="movementModalType = 'transfer'" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50">
            Transfer
          </button>
          <button type="button" @click="movementModalType = 'contract_change'" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50">
            Extend Contract
          </button>
          <button type="button" @click="movementModalType = 'probation_confirmed'" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50">
            Change Status
          </button>
          <button type="button" @click="movementModalType = 'resignation'" class="rounded-xl border border-red-100 px-3 py-2 text-xs font-medium text-red-600 hover:bg-red-50">
            Resignation
          </button>
          <button
            type="button"
            @click="movementModalType = 'edit'"
            class="rounded-xl border border-primary/20 bg-primary-soft/40 px-3 py-2 text-xs font-medium text-primary-dark hover:bg-primary-soft"
          >
            Edit Employment Data
          </button>
        </div>
      </div>
      <!-- Reprimand / SP -- history disipliner, "hapus" = void (bukan hard delete) -->
      <div v-if="authStore.permissions.includes('view employee reprimands')" class="rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <div class="flex items-center justify-between">
          <h2 class="text-sm font-semibold text-slate-700">Reprimand / SP</h2>
          <button
            v-if="authStore.permissions.includes('create employee reprimands')"
            type="button"
            class="rounded-xl border border-red-100 px-3 py-2 text-xs font-medium text-red-600 hover:bg-red-50"
            @click="showReprimandModal = true"
          >
            Berikan Reprimand
          </button>
        </div>

        <p v-if="reprimandError" class="mt-2 text-xs text-red-600">{{ reprimandError }}</p>
        <div v-if="reprimandsLoading" class="mt-3 text-sm text-slate-400">Memuat...</div>
        <div v-else-if="reprimands.length === 0" class="mt-3 rounded-xl bg-slate-50 p-4 text-center text-sm text-slate-400">
          Belum ada riwayat reprimand.
        </div>
        <div v-else class="mt-3 divide-y divide-slate-100">
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
            <p class="mt-0.5 text-xs text-slate-500">{{ item.date }} &middot; {{ item.reason }}</p>
            <p v-if="item.status === 'void' && item.void_reason" class="mt-0.5 text-xs italic text-slate-400">Dibatalkan: {{ item.void_reason }}</p>
            <a v-if="item.attachment_url" :href="item.attachment_url" target="_blank" rel="noopener" class="mt-0.5 block text-xs font-medium text-primary hover:underline">Lihat lampiran</a>

            <button
              v-if="item.status === 'active' && authStore.permissions.includes('delete employee reprimands') && voidingReprimandId !== item.id"
              type="button"
              class="mt-1 text-xs font-medium text-slate-500 hover:underline"
              @click="startVoid(item)"
            >
              Void
            </button>
            <div v-if="voidingReprimandId === item.id" class="mt-2 flex items-center gap-2">
              <input v-model="voidReason" type="text" placeholder="Alasan void..." class="flex-1 rounded-xl border border-slate-200 px-3 py-1.5 text-xs" />
              <button type="button" :disabled="!voidReason" class="rounded-xl bg-slate-800 px-3 py-1.5 text-xs font-medium text-white disabled:opacity-50" @click="confirmVoid">
                Konfirmasi
              </button>
              <button type="button" class="text-xs text-slate-400 hover:underline" @click="voidingReprimandId = null">Batal</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Documents -- Hidden/Private (KTP, rekening, kontrak, dsb) & Open/Public
           yang terkait employee ini. List sudah difilter server-side sesuai
           permission view per kategori (lihat CompanyDocumentController::indexForEmployee) -->
      <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <div class="flex items-center justify-between">
          <h2 class="text-sm font-semibold text-slate-700">Documents</h2>
          <button
            type="button"
            class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50"
            @click="showCompanyDocumentModal = true"
          >
            Upload Dokumen
          </button>
        </div>

        <p v-if="companyDocumentError" class="mt-2 text-xs text-red-600">{{ companyDocumentError }}</p>
        <div v-if="companyDocumentsLoading" class="mt-3 text-sm text-slate-400">Memuat...</div>
        <div v-else-if="companyDocuments.length === 0" class="mt-3 rounded-xl bg-slate-50 p-4 text-center text-sm text-slate-400">
          Belum ada dokumen (atau Anda tidak punya permission untuk melihat kategori yang ada).
        </div>
        <div v-else class="mt-3 divide-y divide-slate-100">
          <div v-for="doc in companyDocuments" :key="doc.id" class="flex items-center justify-between py-3">
            <div>
              <div class="flex items-center gap-2">
                <p class="text-sm font-medium text-slate-900">{{ doc.title }}</p>
                <span
                  class="rounded-full px-2 py-0.5 text-xs font-medium"
                  :class="doc.visibility === 'private' ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700'"
                >
                  {{ doc.visibility === 'private' ? 'Private' : 'Public' }}
                </span>
              </div>
              <p class="text-xs text-slate-500">{{ companyDocumentCategoryLabels[doc.category] ?? doc.category }}</p>
            </div>
            <div class="flex items-center gap-3">
              <a :href="doc.url" target="_blank" rel="noopener" class="text-sm font-medium text-primary hover:underline">Lihat</a>
              <button type="button" class="text-sm font-medium text-red-600 hover:underline" @click="deleteCompanyDocument(doc)">Hapus</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Assets -- company property yang di-assign ke employee ini (laptop,
           kartu ID, dsb). Assignment murni wewenang HR/Admin. -->
      <div v-if="authStore.permissions.includes('view employee assets')" class="rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <div class="flex items-center justify-between">
          <h2 class="text-sm font-semibold text-slate-700">Assets</h2>
          <button
            v-if="authStore.permissions.includes('create employee assets')"
            type="button"
            class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50"
            @click="showAssetModal = true"
          >
            Assign Asset
          </button>
        </div>

        <p v-if="assetError" class="mt-2 text-xs text-red-600">{{ assetError }}</p>
        <div v-if="assetsLoading" class="mt-3 text-sm text-slate-400">Memuat...</div>
        <div v-else-if="assets.length === 0" class="mt-3 rounded-xl bg-slate-50 p-4 text-center text-sm text-slate-400">
          Belum ada asset yang di-assign.
        </div>
        <div v-else class="mt-3 divide-y divide-slate-100">
          <div v-for="asset in assets" :key="asset.id" class="py-3">
            <div class="flex items-center justify-between">
              <p class="text-sm font-medium text-slate-900">{{ asset.asset_name }}</p>
              <span class="text-xs text-slate-500">{{ assetTypeLabels[asset.asset_type] ?? asset.asset_type }}</span>
            </div>
            <p class="mt-0.5 text-xs text-slate-500">
              {{ assetConditionLabels[asset.condition] ?? asset.condition }}
              <span v-if="asset.serial_number">&middot; SN: {{ asset.serial_number }}</span>
              &middot; Diterima {{ asset.assigned_date }}
              <span v-if="asset.returned_date">&middot; Dikembalikan {{ asset.returned_date }}</span>
            </p>
            <div v-if="authStore.permissions.includes('edit employee assets') || authStore.permissions.includes('delete employee assets')" class="mt-1 flex items-center gap-3">
              <button
                v-if="!asset.returned_date && authStore.permissions.includes('edit employee assets')"
                type="button"
                class="text-xs font-medium text-slate-500 hover:underline"
                @click="markAssetReturned(asset)"
              >
                Tandai Dikembalikan
              </button>
              <button
                v-if="authStore.permissions.includes('delete employee assets')"
                type="button"
                class="text-xs font-medium text-red-600 hover:underline"
                @click="deleteAsset(asset)"
              >
                Hapus
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Files -- KTP/NPWP/dst yang self-service employee juga bisa kelola
           sendiri (/my-documents); di sini HR bisa upload/lihat/hapus buat
           employee mana pun yang dia punya permission-nya. -->
      <div v-if="authStore.permissions.includes('view employee documents')" class="rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <div class="flex items-center justify-between">
          <h2 class="text-sm font-semibold text-slate-700">Files</h2>
          <button
            v-if="authStore.permissions.includes('create employee documents')"
            type="button"
            class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50"
            @click="showFileModal = true"
          >
            Upload File
          </button>
        </div>

        <p v-if="fileError" class="mt-2 text-xs text-red-600">{{ fileError }}</p>
        <div v-if="filesLoading" class="mt-3 text-sm text-slate-400">Memuat...</div>
        <div v-else-if="files.length === 0" class="mt-3 rounded-xl bg-slate-50 p-4 text-center text-sm text-slate-400">Belum ada file.</div>
        <div v-else class="mt-3 divide-y divide-slate-100">
          <div v-for="doc in files" :key="doc.id" class="flex items-center justify-between py-3">
            <div>
              <p class="text-sm font-medium text-slate-900">{{ fileCategoryLabels[doc.category] ?? doc.category }}</p>
              <p class="text-xs text-slate-500">{{ doc.file_name }}</p>
            </div>
            <div class="flex items-center gap-3">
              <a :href="doc.url" target="_blank" rel="noopener" class="text-sm font-medium text-primary hover:underline">Lihat</a>
              <button
                v-if="authStore.permissions.includes('delete employee documents')"
                type="button"
                class="text-sm font-medium text-red-600 hover:underline"
                @click="deleteFile(doc)"
              >
                Hapus
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Education & Experience -->
      <div v-if="authStore.permissions.includes('view employee education') || authStore.permissions.includes('view employee experience')" class="space-y-4">
        <div v-if="authStore.permissions.includes('view employee education')" class="rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
          <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-700">Riwayat Pendidikan</h2>
            <button
              v-if="authStore.permissions.includes('create employee education')"
              type="button"
              class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50"
              @click="openCreateEducation"
            >
              Tambah
            </button>
          </div>
          <p v-if="educationError" class="mt-2 text-xs text-red-600">{{ educationError }}</p>
          <div v-if="educationsLoading" class="mt-3 text-sm text-slate-400">Memuat...</div>
          <div v-else-if="educations.length === 0" class="mt-3 rounded-xl bg-slate-50 p-4 text-center text-sm text-slate-400">Belum ada riwayat pendidikan.</div>
          <div v-else class="mt-3 divide-y divide-slate-100">
            <div v-for="edu in educations" :key="edu.id" class="flex items-center justify-between py-3">
              <div>
                <p class="text-sm font-medium text-slate-900">{{ educationLevelLabels[edu.education_level] ?? edu.education_level }} &middot; {{ edu.institution_name }}</p>
                <p class="text-xs text-slate-500">
                  {{ edu.major ?? '-' }} &middot; {{ graduationStatusLabels[edu.graduation_status] ?? edu.graduation_status }}
                </p>
              </div>
              <div class="flex items-center gap-3">
                <button
                  v-if="authStore.permissions.includes('edit employee education')"
                  type="button"
                  class="text-sm font-medium text-primary hover:underline"
                  @click="openEditEducation(edu)"
                >
                  Edit
                </button>
                <button
                  v-if="authStore.permissions.includes('delete employee education')"
                  type="button"
                  class="text-sm font-medium text-red-600 hover:underline"
                  @click="deleteEducation(edu)"
                >
                  Hapus
                </button>
              </div>
            </div>
          </div>
        </div>

        <div v-if="authStore.permissions.includes('view employee experience')" class="rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
          <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-700">Pengalaman Kerja</h2>
            <button
              v-if="authStore.permissions.includes('create employee experience')"
              type="button"
              class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50"
              @click="openCreateExperience"
            >
              Tambah
            </button>
          </div>
          <p v-if="experienceError" class="mt-2 text-xs text-red-600">{{ experienceError }}</p>
          <div v-if="experiencesLoading" class="mt-3 text-sm text-slate-400">Memuat...</div>
          <div v-else-if="experiences.length === 0" class="mt-3 rounded-xl bg-slate-50 p-4 text-center text-sm text-slate-400">Belum ada riwayat pengalaman kerja.</div>
          <div v-else class="mt-3 divide-y divide-slate-100">
            <div v-for="exp in experiences" :key="exp.id" class="flex items-center justify-between py-3">
              <div>
                <p class="text-sm font-medium text-slate-900">{{ exp.position_title }} &middot; {{ exp.company_name }}</p>
                <p class="text-xs text-slate-500">
                  {{ exp.employment_type ? employmentTypeLabels[exp.employment_type] ?? exp.employment_type : '-' }}
                  &middot; {{ exp.start_date }} s/d {{ exp.end_date ?? 'sekarang' }}
                </p>
              </div>
              <div class="flex items-center gap-3">
                <button
                  v-if="authStore.permissions.includes('edit employee experience')"
                  type="button"
                  class="text-sm font-medium text-primary hover:underline"
                  @click="openEditExperience(exp)"
                >
                  Edit
                </button>
                <button
                  v-if="authStore.permissions.includes('delete employee experience')"
                  type="button"
                  class="text-sm font-medium text-red-600 hover:underline"
                  @click="deleteExperience(exp)"
                >
                  Hapus
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <EmployeeDocumentFormModal
      v-if="showFileModal && employee"
      :employee-id="employee.id"
      :employee-name="fullName"
      @close="showFileModal = false"
      @created="onFileCreated"
    />

    <EmployeeEducationFormModal
      v-if="showEducationModal && employee"
      :employee-id="employee.id"
      :employee-name="fullName"
      :editing="editingEducation"
      @close="showEducationModal = false"
      @saved="onEducationSaved"
    />

    <EmployeeExperienceFormModal
      v-if="showExperienceModal && employee"
      :employee-id="employee.id"
      :employee-name="fullName"
      :editing="editingExperience"
      @close="showExperienceModal = false"
      @saved="onExperienceSaved"
    />

    <EmployeeAssetFormModal
      v-if="showAssetModal && employee"
      :employee-id="employee.id"
      :employee-name="fullName"
      @close="showAssetModal = false"
      @created="onAssetCreated"
    />

    <EmployeeReprimandFormModal
      v-if="showReprimandModal && employee"
      :employee-id="employee.id"
      :employee-name="fullName"
      @close="showReprimandModal = false"
      @created="onReprimandCreated"
    />

    <EmployeeCompanyDocumentFormModal
      v-if="showCompanyDocumentModal && employee"
      :employee-id="employee.id"
      :employee-name="fullName"
      @close="showCompanyDocumentModal = false"
      @created="onCompanyDocumentCreated"
    />

    <EmployeeMovementFormModal
      v-if="movementModalType && employee"
      :employee-id="employee.id"
      :employee-name="fullName"
      :default-type="movementModalType === 'edit' ? undefined : movementModalType"
      :current-employee="employee"
      @close="movementModalType = null"
      @created="onMovementCreated"
    />
  </div>
</template>