<script setup lang="ts">
import { ref, onMounted, onUnmounted, reactive, computed } from 'vue'
import { Plus, Pencil, Trash2, X, ScanFace, Search, ChevronDown, Building2, UserRound, Phone, IdCard } from 'lucide-vue-next'
import apiClient from '@/lib/axios'
import OrgChart from '@/components/employee/OrgChart.vue'
import FaceEnrollmentModal from '@/components/FaceEnrollmentModal.vue'

interface Ref {
  id: number
  name: string
}

interface EmployeeRow {
  id: number
  employee_number: string
  first_name: string
  last_name: string | null
  company: Ref
  department: Ref | null
  position: Ref | null
  job_level: Ref | null
  working_schedule: Ref | null
  employment_status: Ref | null
}

const view = ref<'directory' | 'orgchart'>('directory')

const employees = ref<EmployeeRow[]>([])
const companies = ref<Ref[]>([])
const branches = ref<Ref[]>([])
const departments = ref<Ref[]>([])
const positions = ref<Ref[]>([])
const jobLevels = ref<Ref[]>([])
const workingSchedules = ref<Ref[]>([])
const employmentStatuses = ref<Ref[]>([])
const managerOptions = ref<{ id: number; employee_number: string; first_name: string; last_name: string | null }[]>([])

const loading = ref(true)
const errorMessage = ref('')

const showModal = ref(false)
const isEditing = ref(false)
const saving = ref(false)
const formError = ref('')

const showFaceEnrollment = ref(false)
const faceEnrollmentTarget = ref<EmployeeRow | null>(null)

const form = reactive({
  id: 0,
  employee_number: '',
  company_id: 0,
  branch_id: null as number | null,
  department_id: null as number | null,
  position_id: null as number | null,
  job_level_id: null as number | null,
  working_schedule_id: null as number | null,
  employment_status_id: null as number | null,
  manager_employee_id: null as number | null,
  join_date: '',
  resign_date: '',
  first_name: '',
  last_name: '',
  gender: 'male',
  birth_place: '',
  birth_date: '',
  marital_status: '',
  phone: '',
  personal_email: '',
  address: '',
  emergency_contact_name: '',
  emergency_contact_phone: '',
  national_id_number: '',
  tax_number: '',
  bank_name: '',
  bank_account_number: '',
  bank_account_holder_name: '',
})

function fullName(row: { first_name: string; last_name: string | null }) {
  return [row.first_name, row.last_name].filter(Boolean).join(' ')
}

function initials(row: { first_name: string; last_name: string | null }) {
  return `${row.first_name?.[0] ?? ''}${row.last_name?.[0] ?? ''}`.toUpperCase()
}

// --- Search & filter (client-side, dari data yang lagi ke-load) ---
const searchQuery = ref('')
const showFilters = ref(false)
const filters = reactive({
  company_id: null as number | null,
  department_id: null as number | null,
  employment_status_id: null as number | null,
})

const filteredEmployees = computed(() => {
  return employees.value.filter((e) => {
    if (filters.company_id && e.company?.id !== filters.company_id) return false
    if (filters.department_id && e.department?.id !== filters.department_id) return false
    if (filters.employment_status_id && e.employment_status?.id !== filters.employment_status_id) return false
    if (searchQuery.value.trim()) {
      const q = searchQuery.value.toLowerCase()
      const haystack = `${fullName(e)} ${e.employee_number}`.toLowerCase()
      if (!haystack.includes(q)) return false
    }
    return true
  })
})

const statusBreakdown = computed(() => {
  const counts = new Map<string, number>()
  for (const e of employees.value) {
    const label = e.employment_status?.name ?? 'Belum Diatur'
    counts.set(label, (counts.get(label) ?? 0) + 1)
  }
  return Array.from(counts.entries()).map(([label, value]) => ({ label, value }))
})

// --- Actions dropdown (teleport, biar gak ke-clip sama overflow tabel) ---
const openActionsRow = ref<EmployeeRow | null>(null)
const actionsMenuStyle = ref({ top: '0px', left: '0px' })

function toggleActions(row: EmployeeRow, event: Event) {
  event.stopPropagation()
  if (openActionsRow.value?.id === row.id) {
    openActionsRow.value = null
    return
  }
  const rect = (event.currentTarget as HTMLElement).getBoundingClientRect()
  actionsMenuStyle.value = {
    top: `${rect.bottom + window.scrollY + 4}px`,
    left: `${rect.right + window.scrollX - 176}px`,
  }
  openActionsRow.value = row
}

function closeActions() {
  openActionsRow.value = null
}

onMounted(() => window.addEventListener('click', closeActions))
onUnmounted(() => window.removeEventListener('click', closeActions))

async function loadEmployees() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/employees')
    employees.value = response.data.data.data
  } catch {
    errorMessage.value = 'Gagal memuat daftar employee.'
  } finally {
    loading.value = false
  }
}

async function loadReferenceData() {
  const [companyRes, branchRes, departmentRes, positionRes, jobLevelRes, workingScheduleRes, statusRes, employeeRes] = await Promise.all([
    apiClient.get('/api/companies'),
    apiClient.get('/api/branches'),
    apiClient.get('/api/departments'),
    apiClient.get('/api/positions'),
    apiClient.get('/api/job-levels'),
    apiClient.get('/api/working-schedules'),
    apiClient.get('/api/employment-statuses'),
    apiClient.get('/api/employees'),
  ])
  companies.value = companyRes.data.data.data
  branches.value = branchRes.data.data.data
  departments.value = departmentRes.data.data.data
  positions.value = positionRes.data.data.data
  jobLevels.value = jobLevelRes.data.data.data
  workingSchedules.value = workingScheduleRes.data.data.data
  employmentStatuses.value = statusRes.data.data.data
  managerOptions.value = employeeRes.data.data.data
}

async function resetForm() {
  const response = await apiClient.get('/api/employees/next-number')
  form.id = 0
  form.employee_number = response.data.data.employee_number
  form.company_id = companies.value[0]?.id ?? 0
  form.branch_id = null
  form.department_id = null
  form.position_id = null
  form.job_level_id = null
  form.working_schedule_id = null
  form.employment_status_id = null
  form.manager_employee_id = null
  form.join_date = new Date().toISOString().slice(0, 10)
  form.resign_date = ''
  form.first_name = ''
  form.last_name = ''
  form.gender = 'male'
  form.birth_place = ''
  form.birth_date = ''
  form.marital_status = ''
  form.phone = ''
  form.personal_email = ''
  form.address = ''
  form.emergency_contact_name = ''
  form.emergency_contact_phone = ''
  form.national_id_number = ''
  form.tax_number = ''
  form.bank_name = ''
  form.bank_account_number = ''
  form.bank_account_holder_name = ''
}

async function openCreateModal() {
  isEditing.value = false
  formError.value = ''
  await resetForm()
  showModal.value = true
}

async function openEditModal(row: EmployeeRow) {
  isEditing.value = true
  formError.value = ''

  const response = await apiClient.get(`/api/employees/${row.id}`)
  const e = response.data.data

  form.id = e.id
  form.employee_number = e.employee_number
  form.company_id = e.company?.id ?? 0
  form.branch_id = e.branch?.id ?? null
  form.department_id = e.department?.id ?? null
  form.position_id = e.position?.id ?? null
  form.job_level_id = e.job_level?.id ?? null
  form.working_schedule_id = e.working_schedule?.id ?? null
  form.employment_status_id = e.employment_status?.id ?? null
  form.manager_employee_id = e.manager?.id ?? null
  form.join_date = e.join_date?.slice(0, 10) ?? ''
  form.resign_date = e.resign_date?.slice(0, 10) ?? ''
  form.first_name = e.first_name
  form.last_name = e.last_name ?? ''
  form.gender = e.gender
  form.birth_place = e.birth_place ?? ''
  form.birth_date = e.birth_date?.slice(0, 10) ?? ''
  form.marital_status = e.marital_status ?? ''
  form.phone = e.phone ?? ''
  form.personal_email = e.personal_email ?? ''
  form.address = e.address ?? ''
  form.emergency_contact_name = e.emergency_contact_name ?? ''
  form.emergency_contact_phone = e.emergency_contact_phone ?? ''
  form.national_id_number = e.national_id_number ?? ''
  form.tax_number = e.tax_number ?? ''
  form.bank_name = e.bank_name ?? ''
  form.bank_account_number = e.bank_account_number ?? ''
  form.bank_account_holder_name = e.bank_account_holder_name ?? ''

  showModal.value = true
}

function closeModal() {
  showModal.value = false
}

async function handleSubmit() {
  saving.value = true
  formError.value = ''

  const payload: Record<string, unknown> = {
    employee_number: form.employee_number,
    company_id: form.company_id,
    branch_id: form.branch_id,
    department_id: form.department_id,
    position_id: form.position_id,
    job_level_id: form.job_level_id,
    working_schedule_id: form.working_schedule_id,
    employment_status_id: form.employment_status_id,
    manager_employee_id: form.manager_employee_id,
    join_date: form.join_date,
    resign_date: form.resign_date || null,
    first_name: form.first_name,
    last_name: form.last_name || null,
    gender: form.gender,
    birth_place: form.birth_place || null,
    birth_date: form.birth_date || null,
    marital_status: form.marital_status || null,
    phone: form.phone || null,
    personal_email: form.personal_email || null,
    address: form.address || null,
    emergency_contact_name: form.emergency_contact_name || null,
    emergency_contact_phone: form.emergency_contact_phone || null,
    national_id_number: form.national_id_number || null,
    tax_number: form.tax_number || null,
    bank_name: form.bank_name || null,
    bank_account_number: form.bank_account_number || null,
    bank_account_holder_name: form.bank_account_holder_name || null,
  }

  try {
    if (isEditing.value) {
      await apiClient.put(`/api/employees/${form.id}`, payload)
    } else {
      await apiClient.post('/api/employees', payload)
    }

    showModal.value = false
    await loadEmployees()
    await loadReferenceData()
  } catch (err: any) {
    formError.value = err.response?.data?.message || 'Terjadi kesalahan, silakan coba lagi.'
  } finally {
    saving.value = false
  }
}

async function handleDelete(row: EmployeeRow) {
  if (!confirm(`Hapus employee "${fullName(row)}"?`)) return

  try {
    await apiClient.delete(`/api/employees/${row.id}`)
    await loadEmployees()
  } catch {
    alert('Gagal menghapus employee.')
  }
}

function openFaceEnrollment(row: EmployeeRow) {
  faceEnrollmentTarget.value = row
  showFaceEnrollment.value = true
}

function closeFaceEnrollment() {
  showFaceEnrollment.value = false
  faceEnrollmentTarget.value = null
}

onMounted(() => {
  loadEmployees()
  loadReferenceData()
})
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Employee</h1>
        <p class="mt-1 text-sm text-slate-500">Kelola data karyawan.</p>
      </div>
      <button
        v-if="view === 'directory'"
        @click="openCreateModal"
        :disabled="companies.length === 0"
        class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-dark disabled:opacity-50"
      >
        <Plus class="h-4 w-4" :stroke-width="2" />
        Tambah Employee
      </button>
    </div>

    <!-- Tab switcher -->
    <div class="flex gap-1">
      <button
        @click="view = 'directory'"
        class="rounded-xl px-3 py-1.5 text-sm font-medium transition-colors"
        :class="view === 'directory' ? 'bg-primary-soft text-primary-dark' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-700'"
      >
        Directory
      </button>
      <button
        @click="view = 'orgchart'"
        class="rounded-xl px-3 py-1.5 text-sm font-medium transition-colors"
        :class="view === 'orgchart' ? 'bg-primary-soft text-primary-dark' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-700'"
      >
        Org chart
      </button>
    </div>

    <p v-if="view === 'directory' && companies.length === 0 && !loading" class="rounded-xl bg-amber-50 p-4 text-sm text-amber-700">
      Belum ada company. Tambahkan company terlebih dahulu sebelum membuat employee.
    </p>

    <!-- Directory view -->
    <template v-if="view === 'directory'">
      <!-- Filter bar -->
      <div class="rounded-2xl border border-slate-100 bg-white p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <button
            type="button"
            @click="showFilters = !showFilters"
            class="text-sm font-medium text-primary-dark hover:underline"
          >
            {{ showFilters ? 'Sembunyikan filter' : 'Semua filter' }}
          </button>

          <div class="relative">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" :stroke-width="1.75" />
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Cari nama / No. Karyawan"
              class="w-64 rounded-xl border border-slate-200 py-2 pl-9 pr-3 text-sm focus:border-primary focus:outline-none"
            />
          </div>
        </div>

        <Transition
          enter-active-class="transition-all duration-150 ease-out"
          enter-from-class="opacity-0 -translate-y-1"
          enter-to-class="opacity-100 translate-y-0"
        >
          <div v-if="showFilters" class="mt-4 flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4">
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-500">Company</label>
              <select v-model="filters.company_id" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                <option :value="null">Semua Company</option>
                <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-500">Department</label>
              <select v-model="filters.department_id" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                <option :value="null">Semua Department</option>
                <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-500">Employment Status</label>
              <select v-model="filters.employment_status_id" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                <option :value="null">Semua Status</option>
                <option v-for="s in employmentStatuses" :key="s.id" :value="s.id">{{ s.name }}</option>
              </select>
            </div>
          </div>
        </Transition>
      </div>

      <!-- Summary stat strip -->
      <div v-if="!loading && !errorMessage" class="flex flex-wrap divide-x divide-slate-100 overflow-hidden rounded-2xl border border-slate-100 bg-white">
        <div class="min-w-[110px] flex-1 px-5 py-4">
          <p class="text-xl font-semibold tracking-tight text-slate-900">{{ employees.length }}</p>
          <p class="mt-0.5 text-xs text-slate-500">Total Employee</p>
        </div>
        <div v-for="stat in statusBreakdown" :key="stat.label" class="min-w-[110px] flex-1 px-5 py-4">
          <p class="text-xl font-semibold tracking-tight text-slate-900">{{ stat.value }}</p>
          <p class="mt-0.5 truncate text-xs text-slate-500">{{ stat.label }}</p>
        </div>
      </div>

      <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
      <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">
        {{ errorMessage }}
      </div>
      <div v-else-if="filteredEmployees.length === 0" class="rounded-xl bg-slate-50 p-4 text-sm text-slate-400">
        Gak ada employee yang cocok dengan filter ini.
      </div>

      <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead>
              <tr class="border-b border-slate-100 bg-slate-50/60">
                <th class="px-5 py-3 font-medium text-slate-500">Employee</th>
                <th class="px-3 py-3 font-medium text-slate-500">Company</th>
                <th class="px-3 py-3 font-medium text-slate-500">Department</th>
                <th class="px-3 py-3 font-medium text-slate-500">Position</th>
                <th class="px-3 py-3 font-medium text-slate-500">Job Level</th>
                <th class="px-3 py-3 font-medium text-slate-500">Status</th>
                <th class="px-5 py-3 text-right font-medium text-slate-500">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in filteredEmployees"
                :key="row.id"
                class="border-b border-slate-50 last:border-0 hover:bg-slate-50/50"
              >
                <td class="px-5 py-3.5">
                  <div class="flex items-center gap-2.5">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-soft text-xs font-semibold text-primary-dark">
                      {{ initials(row) }}
                    </div>
                    <div>
                      <p class="font-medium text-slate-800">{{ fullName(row) }}</p>
                      <p class="text-xs text-slate-400">{{ row.employee_number }}</p>
                    </div>
                  </div>
                </td>
                <td class="px-3 py-3.5 text-slate-500">{{ row.company.name }}</td>
                <td class="px-3 py-3.5 text-slate-500">{{ row.department?.name ?? '-' }}</td>
                <td class="px-3 py-3.5 text-slate-500">{{ row.position?.name ?? '-' }}</td>
                <td class="px-3 py-3.5 text-slate-500">{{ row.job_level?.name ?? '-' }}</td>
                <td class="px-3 py-3.5">
                  <span
                    v-if="row.employment_status"
                    class="rounded-full bg-primary-soft px-2.5 py-1 text-xs font-medium text-primary-dark"
                  >
                    {{ row.employment_status.name }}
                  </span>
                  <span v-else class="text-slate-400">-</span>
                </td>
                <td class="px-5 py-3.5 text-right">
                  <button
                    type="button"
                    @click="toggleActions(row, $event)"
                    class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-600 transition-colors hover:bg-slate-50"
                    :class="openActionsRow?.id === row.id ? 'border-primary/40 text-primary-dark' : ''"
                  >
                    Actions
                    <ChevronDown class="h-3.5 w-3.5" :stroke-width="2" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>

    <!-- Org chart view -->
    <OrgChart v-else />

    <!-- Actions dropdown, teleported biar gak ke-clip -->
    <Teleport to="body">
      <div
        v-if="openActionsRow"
        @click.stop
        class="fixed z-50 w-44 overflow-hidden rounded-xl border border-slate-100 bg-white py-1 shadow-lg"
        :style="actionsMenuStyle"
      >
        <button
          @click="openFaceEnrollment(openActionsRow!); closeActions()"
          class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-600 hover:bg-slate-50"
        >
          <ScanFace class="h-3.5 w-3.5" :stroke-width="1.75" />
          Daftarkan Wajah
        </button>
        <button
          @click="openEditModal(openActionsRow!); closeActions()"
          class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-600 hover:bg-slate-50"
        >
          <Pencil class="h-3.5 w-3.5" :stroke-width="1.75" />
          Edit
        </button>
        <button
          @click="handleDelete(openActionsRow!); closeActions()"
          class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-red-500 hover:bg-red-50"
        >
          <Trash2 class="h-3.5 w-3.5" :stroke-width="1.75" />
          Hapus
        </button>
      </div>
    </Teleport>

    <Teleport to="body">
      <div
        v-if="showModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8"
      >
        <div class="flex max-h-full w-full max-w-2xl flex-col rounded-2xl bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">
              {{ isEditing ? 'Edit Employee' : 'Tambah Employee' }}
            </h2>
            <button @click="closeModal" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50">
              <X class="h-5 w-5" />
            </button>
          </div>

          <form @submit.prevent="handleSubmit" class="flex-1 space-y-7 overflow-y-auto px-6 py-5">
            <!-- Employment Information -->
            <div>
              <div class="mb-3 flex items-center gap-2">
                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary-soft text-primary-dark">
                  <Building2 class="h-4 w-4" :stroke-width="1.75" />
                </div>
                <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Employment Information</h3>
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">No. Karyawan</label>
                  <input v-model="form.employee_number" type="text" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
                </div>
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">Tanggal Bergabung</label>
                  <input v-model="form.join_date" type="date" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
                </div>
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">Company</label>
                  <select v-model.number="form.company_id" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                    <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
                  </select>
                </div>
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">Branch</label>
                  <select v-model="form.branch_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                    <option :value="null">-</option>
                    <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                  </select>
                </div>
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">Department</label>
                  <select v-model="form.department_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                    <option :value="null">-</option>
                    <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                  </select>
                </div>
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">Position</label>
                  <select v-model="form.position_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                    <option :value="null">-</option>
                    <option v-for="p in positions" :key="p.id" :value="p.id">{{ p.name }}</option>
                  </select>
                </div>
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">Job Level</label>
                  <select v-model="form.job_level_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                    <option :value="null">-</option>
                    <option v-for="jl in jobLevels" :key="jl.id" :value="jl.id">{{ jl.name }}</option>
                  </select>
                </div>
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">Working Schedule</label>
                  <select v-model="form.working_schedule_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                    <option :value="null">-</option>
                    <option v-for="ws in workingSchedules" :key="ws.id" :value="ws.id">{{ ws.name }}</option>
                  </select>
                </div>
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">Employment Status</label>
                  <select v-model="form.employment_status_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                    <option :value="null">-</option>
                    <option v-for="s in employmentStatuses" :key="s.id" :value="s.id">{{ s.name }}</option>
                  </select>
                </div>
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">Manager</label>
                  <select v-model="form.manager_employee_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                    <option :value="null">-</option>
                    <option v-for="m in managerOptions" :key="m.id" :value="m.id">{{ fullName(m) }}</option>
                  </select>
                </div>
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">Tanggal Resign</label>
                  <input v-model="form.resign_date" type="date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
                </div>
              </div>
            </div>

            <!-- Personal Information -->
            <div>
              <div class="mb-3 flex items-center gap-2">
                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary-soft text-primary-dark">
                  <UserRound class="h-4 w-4" :stroke-width="1.75" />
                </div>
                <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Personal Information</h3>
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">Nama Depan</label>
                  <input v-model="form.first_name" type="text" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
                </div>
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">Nama Belakang</label>
                  <input v-model="form.last_name" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
                </div>
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">Jenis Kelamin</label>
                  <select v-model="form.gender" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                    <option value="male">Laki-laki</option>
                    <option value="female">Perempuan</option>
                  </select>
                </div>
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">Status Pernikahan</label>
                  <select v-model="form.marital_status" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                    <option value="">-</option>
                    <option value="single">Belum Menikah</option>
                    <option value="married">Menikah</option>
                    <option value="divorced">Cerai</option>
                    <option value="widowed">Janda/Duda</option>
                  </select>
                </div>
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">Tempat Lahir</label>
                  <input v-model="form.birth_place" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
                </div>
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">Tanggal Lahir</label>
                  <input v-model="form.birth_date" type="date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
                </div>
              </div>
            </div>

            <!-- Contact Information -->
            <div>
              <div class="mb-3 flex items-center gap-2">
                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary-soft text-primary-dark">
                  <Phone class="h-4 w-4" :stroke-width="1.75" />
                </div>
                <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Contact Information</h3>
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">Telepon</label>
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
                  <label class="mb-1 block text-sm font-medium text-slate-700">Kontak Darurat</label>
                  <input v-model="form.emergency_contact_name" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
                </div>
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">Telepon Darurat</label>
                  <input v-model="form.emergency_contact_phone" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
                </div>
              </div>
            </div>

            <!-- Identity Information -->
            <div>
              <div class="mb-3 flex items-center gap-2">
                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary-soft text-primary-dark">
                  <IdCard class="h-4 w-4" :stroke-width="1.75" />
                </div>
                <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Identity Information</h3>
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">NIK</label>
                  <input v-model="form.national_id_number" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
                </div>
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">NPWP</label>
                  <input v-model="form.tax_number" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
                </div>
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">Nama Bank</label>
                  <input v-model="form.bank_name" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
                </div>
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">No. Rekening</label>
                  <input v-model="form.bank_account_number" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
                </div>
                <div class="col-span-2">
                  <label class="mb-1 block text-sm font-medium text-slate-700">Nama Pemilik Rekening</label>
                  <input v-model="form.bank_account_holder_name" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
                </div>
              </div>
            </div>

            <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>
          </form>

          <div class="border-t border-slate-100 px-6 py-4">
            <button
              @click="handleSubmit"
              :disabled="saving"
              class="w-full rounded-xl bg-primary py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-dark disabled:opacity-50"
            >
              {{ saving ? 'Menyimpan...' : 'Simpan' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <FaceEnrollmentModal
      v-if="showFaceEnrollment && faceEnrollmentTarget"
      :employee-id="faceEnrollmentTarget.id"
      :employee-name="fullName(faceEnrollmentTarget)"
      @close="closeFaceEnrollment"
      @enrolled="closeFaceEnrollment"
    />
  </div>
</template>