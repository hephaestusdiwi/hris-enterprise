<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import apiClient from '@/lib/axios'
import { useAuthStore } from '@/stores/auth'
import BaseModal from '@/components/ui/BaseModal.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import { Plus, Search, MoreVertical } from 'lucide-vue-next'

interface RefOption {
  id: number
  name: string
}

interface EmployeeOption {
  id: number
  first_name: string
  last_name: string | null
}

interface JobVacancyRow {
  id: number
  title: string
  slug: string
  status: string
  visibility: string
  application_method: string
  created_at: string
  position: RefOption | null
  department: RefOption | null
  hiring_manager: EmployeeOption | null
  recruiter: EmployeeOption | null
}

interface HiringRequisitionOption {
  id: number
  status: string
  headcount_requested: number
  headcount_filled: number
  position: RefOption | null
  department: RefOption | null
}

const router = useRouter()
const authStore = useAuthStore()

const loading = ref(true)
const errorMessage = ref('')
const actionError = ref('')
const vacancies = ref<JobVacancyRow[]>([])

const search = ref('')
const statusFilter = ref('')

const STATUS_OPTIONS = [
  { value: '', label: 'Semua Status' },
  { value: 'draft', label: 'Draft' },
  { value: 'published', label: 'Published' },
  { value: 'paused', label: 'Paused' },
  { value: 'closed', label: 'Closed' },
  { value: 'filled', label: 'Filled' },
  { value: 'cancelled', label: 'Cancelled' },
  { value: 'archived', label: 'Archived' },
]

const STATUS_BADGE: Record<string, string> = {
  draft: 'bg-slate-100 text-slate-600',
  published: 'bg-emerald-50 text-emerald-600',
  paused: 'bg-amber-50 text-amber-600',
  closed: 'bg-slate-100 text-slate-500',
  filled: 'bg-primary-soft text-primary-dark',
  cancelled: 'bg-red-50 text-red-600',
  archived: 'bg-slate-100 text-slate-400',
}

function employeeName(e: EmployeeOption | null): string {
  if (!e) return '-'
  return [e.first_name, e.last_name].filter(Boolean).join(' ')
}

async function loadVacancies() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/job-vacancies')
    vacancies.value = response.data.data.data
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Gagal memuat daftar Job Vacancy.'
  } finally {
    loading.value = false
  }
}

const filteredVacancies = computed(() => {
  return vacancies.value.filter((v) => {
    const matchesSearch = !search.value || v.title.toLowerCase().includes(search.value.toLowerCase())
    const matchesStatus = !statusFilter.value || v.status === statusFilter.value
    return matchesSearch && matchesStatus
  })
})

// ---- Create modal ----
const showCreateModal = ref(false)
const saving = ref(false)
const formError = ref('')

const hiringRequisitions = ref<HiringRequisitionOption[]>([])
const employees = ref<EmployeeOption[]>([])
const employmentTypes = ref<RefOption[]>([])

const openRequisitions = computed(() =>
  hiringRequisitions.value.filter((r) => r.status === 'open' && r.headcount_requested > r.headcount_filled),
)

const form = reactive({
  hiring_requisition_id: '' as number | '',
  hiring_manager_employee_id: '' as number | '',
  recruiter_employee_id: '' as number | '',
  title: '',
  description: '',
  requirements: '',
  employment_type_id: '' as number | '',
  visibility: 'internal',
  application_method: 'internal',
  external_apply_url: '',
  application_deadline: '',
})

function resetForm() {
  form.hiring_requisition_id = ''
  form.hiring_manager_employee_id = ''
  form.recruiter_employee_id = ''
  form.title = ''
  form.description = ''
  form.requirements = ''
  form.employment_type_id = ''
  form.visibility = 'internal'
  form.application_method = 'internal'
  form.external_apply_url = ''
  form.application_deadline = ''
  formError.value = ''
}

async function loadReferenceData() {
  const [reqRes, empRes, typeRes] = await Promise.all([
    apiClient.get('/api/hiring-requisitions'),
    apiClient.get('/api/employees'),
    apiClient.get('/api/employment-types'),
  ])
  hiringRequisitions.value = reqRes.data.data.data ?? reqRes.data.data
  employees.value = empRes.data.data.data ?? empRes.data.data
  employmentTypes.value = typeRes.data.data.data ?? typeRes.data.data
}

function openCreateModal() {
  resetForm()
  showCreateModal.value = true
}

async function submitCreate() {
  saving.value = true
  formError.value = ''
  try {
    await apiClient.post('/api/job-vacancies', {
      hiring_requisition_id: form.hiring_requisition_id,
      hiring_manager_employee_id: form.hiring_manager_employee_id,
      recruiter_employee_id: form.recruiter_employee_id,
      title: form.title,
      description: form.description,
      requirements: form.requirements || null,
      employment_type_id: form.employment_type_id || null,
      visibility: form.visibility,
      application_method: form.application_method,
      external_apply_url: form.application_method === 'external' ? form.external_apply_url : null,
      application_deadline: form.application_deadline || null,
    })
    showCreateModal.value = false
    await loadVacancies()
  } catch (err: any) {
    formError.value = err.response?.data?.message || 'Gagal membuat Job Vacancy.'
  } finally {
    saving.value = false
  }
}

// ---- Lifecycle actions ----
const openRowMenu = ref<number | null>(null)

function availableActions(status: string): Array<{ action: string; label: string }> {
  switch (status) {
    case 'draft':
      return [
        { action: 'publish', label: 'Publish' },
        { action: 'cancel', label: 'Cancel' },
      ]
    case 'published':
      return [
        { action: 'pause', label: 'Pause' },
        { action: 'close', label: 'Close' },
        { action: 'cancel', label: 'Cancel' },
      ]
    case 'paused':
      return [
        { action: 'publish', label: 'Resume' },
        { action: 'close', label: 'Close' },
        { action: 'cancel', label: 'Cancel' },
      ]
    case 'closed':
    case 'filled':
    case 'cancelled':
      return [{ action: 'archive', label: 'Archive' }]
    default:
      return []
  }
}

const ACTION_PERMISSION: Record<string, string> = {
  publish: 'publish job vacancies',
  pause: 'publish job vacancies',
  close: 'close job vacancies',
  cancel: 'cancel job vacancies',
  archive: 'archive job vacancies',
}

function canDoAction(action: string): boolean {
  return authStore.permissions.includes(ACTION_PERMISSION[action])
}

async function runAction(vacancy: JobVacancyRow, action: string) {
  openRowMenu.value = null
  actionError.value = ''
  try {
    await apiClient.post(`/api/job-vacancies/${vacancy.id}/${action}`)
    await loadVacancies()
  } catch (err: any) {
    actionError.value = err.response?.data?.message || 'Aksi gagal dijalankan.'
  }
}

function goToDetail(id: number) {
  router.push({ name: 'job-vacancies.show', params: { id } })
}

onMounted(async () => {
  await loadVacancies()
  await loadReferenceData()
})
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-lg font-semibold text-slate-800">Job Vacancies</h1>
        <p class="text-sm text-slate-400">Kelola lowongan yang terhubung ke Hiring Requisition.</p>
      </div>
      <button
        v-if="authStore.permissions.includes('create job vacancies')"
        class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white"
        @click="openCreateModal"
      >
        <Plus class="h-4 w-4" /> Buat Job Vacancy
      </button>
    </div>

    <div class="flex items-center gap-3">
      <div class="relative flex-1 max-w-xs">
        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-300" />
        <input
          v-model="search"
          type="text"
          placeholder="Cari judul lowongan..."
          class="w-full rounded-xl border border-slate-200 py-2 pl-9 pr-3 text-sm"
        />
      </div>
      <select v-model="statusFilter" class="rounded-xl border border-slate-200 py-2 px-3 text-sm">
        <option v-for="opt in STATUS_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
      </select>
    </div>

    <div v-if="actionError" class="rounded-xl bg-red-50 p-3 text-sm text-red-600">{{ actionError }}</div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>
    <EmptyState v-else-if="filteredVacancies.length === 0" title="Belum ada Job Vacancy" />
    <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-100 text-xs uppercase text-slate-400">
          <tr>
            <th class="px-4 py-3">Judul</th>
            <th class="px-4 py-3">Position / Department</th>
            <th class="px-4 py-3">Hiring Manager</th>
            <th class="px-4 py-3">Recruiter</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="v in filteredVacancies"
            :key="v.id"
            class="cursor-pointer border-b border-slate-50 hover:bg-slate-50"
            @click="goToDetail(v.id)"
          >
            <td class="px-4 py-3 font-medium text-slate-700">{{ v.title }}</td>
            <td class="px-4 py-3 text-slate-500">
              {{ v.position?.name || '-' }} <span class="text-slate-300">/</span> {{ v.department?.name || '-' }}
            </td>
            <td class="px-4 py-3 text-slate-500">{{ employeeName(v.hiring_manager) }}</td>
            <td class="px-4 py-3 text-slate-500">{{ employeeName(v.recruiter) }}</td>
            <td class="px-4 py-3">
              <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="STATUS_BADGE[v.status]">
                {{ v.status }}
              </span>
            </td>
            <td class="relative px-4 py-3 text-right" @click.stop>
              <button
                v-if="availableActions(v.status).length"
                class="rounded-lg p-1.5 hover:bg-slate-100"
                @click="openRowMenu = openRowMenu === v.id ? null : v.id"
              >
                <MoreVertical class="h-4 w-4 text-slate-400" />
              </button>
              <div
                v-if="openRowMenu === v.id"
                class="absolute right-4 top-10 z-10 w-36 rounded-xl border border-slate-100 bg-white py-1 shadow-lg"
              >
                <button
                  v-for="a in availableActions(v.status)"
                  :key="a.action"
                  v-show="canDoAction(a.action)"
                  class="block w-full px-3 py-2 text-left text-sm text-slate-600 hover:bg-slate-50"
                  @click="runAction(v, a.action)"
                >
                  {{ a.label }}
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <Teleport to="body">
      <BaseModal v-if="showCreateModal" title="Buat Job Vacancy" @close="showCreateModal = false">
        <form class="space-y-3" @submit.prevent="submitCreate">
          <div v-if="formError" class="rounded-xl bg-red-50 p-3 text-sm text-red-600">{{ formError }}</div>

          <div>
            <label class="text-xs font-medium text-slate-500">Hiring Requisition</label>
            <select
              v-model="form.hiring_requisition_id"
              :required="openRequisitions.length > 0"
              :disabled="openRequisitions.length === 0"
              class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm disabled:bg-slate-50 disabled:text-slate-400"
            >
              <option v-if="openRequisitions.length === 0" value="">Tidak ada Hiring Requisition Open</option>
              <option v-else value="" disabled>Pilih Requisition (status Open)</option>
              <option v-for="r in openRequisitions" :key="r.id" :value="r.id">
                #{{ r.id }} — {{ r.position?.name }} / {{ r.department?.name }}
                ({{ r.headcount_requested - r.headcount_filled }} sisa headcount)
              </option>
            </select>
            <p v-if="openRequisitions.length === 0" class="mt-1 text-xs text-slate-400">
              Buat atau buka Hiring Requisition terlebih dahulu.
            </p>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-xs font-medium text-slate-500">Hiring Manager</label>
              <select v-model="form.hiring_manager_employee_id" required class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm">
                <option value="" disabled>Pilih Employee</option>
                <option v-for="e in employees" :key="e.id" :value="e.id">{{ employeeName(e) }}</option>
              </select>
            </div>
            <div>
              <label class="text-xs font-medium text-slate-500">Recruiter</label>
              <select v-model="form.recruiter_employee_id" required class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm">
                <option value="" disabled>Pilih Employee</option>
                <option v-for="e in employees" :key="e.id" :value="e.id">{{ employeeName(e) }}</option>
              </select>
            </div>
          </div>

          <div>
            <label class="text-xs font-medium text-slate-500">Judul Lowongan</label>
            <input v-model="form.title" required type="text" class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm" />
          </div>

          <div>
            <label class="text-xs font-medium text-slate-500">Deskripsi</label>
            <textarea v-model="form.description" required rows="3" class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm" />
          </div>

          <div>
            <label class="text-xs font-medium text-slate-500">Requirements (opsional)</label>
            <textarea v-model="form.requirements" rows="2" class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-xs font-medium text-slate-500">Employment Type</label>
              <select v-model="form.employment_type_id" class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm">
                <option value="">Ikuti default Requisition</option>
                <option v-for="t in employmentTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
              </select>
            </div>
            <div>
              <label class="text-xs font-medium text-slate-500">Visibility</label>
              <select v-model="form.visibility" class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm">
                <option value="internal">Internal</option>
                <option value="external">External</option>
                <option value="both">Both</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-xs font-medium text-slate-500">Application Method</label>
              <select v-model="form.application_method" class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm">
                <option value="internal">Internal (lewat sistem ini)</option>
                <option value="external">External (redirect)</option>
              </select>
            </div>
            <div>
              <label class="text-xs font-medium text-slate-500">Deadline (opsional)</label>
              <input v-model="form.application_deadline" type="date" class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm" />
            </div>
          </div>

          <div v-if="form.application_method === 'external'">
            <label class="text-xs font-medium text-slate-500">External Apply URL</label>
            <input v-model="form.external_apply_url" required type="url" class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm" />
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button type="button" class="rounded-xl border border-slate-200 px-4 py-2 text-sm" @click="showCreateModal = false">
              Batal
            </button>
            <button type="submit" :disabled="saving || openRequisitions.length === 0" class="rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white disabled:opacity-50">
              {{ saving ? 'Menyimpan...' : 'Simpan' }}
            </button>
          </div>
        </form>
      </BaseModal>
    </Teleport>
  </div>
</template>