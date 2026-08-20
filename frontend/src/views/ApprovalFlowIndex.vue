<script setup lang="ts">
import { ref, onMounted, reactive, computed } from 'vue'
import { useRouter } from 'vue-router'
import { Plus, Pencil, Trash2, X, Building2, ListTree } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface Company {
  id: number
  name: string
}

interface Branch {
  id: number
  name: string
  company_id: number
}

interface Department {
  id: number
  name: string
  company_id: number
}

interface JobLevel {
  id: number
  name: string
  company_id: number
}

interface ApprovalFlowRow {
  id: number
  company_id: number
  approval_type: string
  branch_id: number | null
  department_id: number | null
  job_level_id: number | null
  name: string
  code: string
  description: string | null
  is_active: boolean
  steps_count: number
  company: Company
  branch: Branch | null
  department: Department | null
  job_level: JobLevel | null
}

const router = useRouter()

const flows = ref<ApprovalFlowRow[]>([])
const companies = ref<Company[]>([])
const branches = ref<Branch[]>([])
const departments = ref<Department[]>([])
const jobLevels = ref<JobLevel[]>([])

const loading = ref(true)
const errorMessage = ref('')

const showModal = ref(false)
const isEditing = ref(false)
const saving = ref(false)
const formError = ref('')

const approvalTypes = [
  {
    value: 'hiring_requisition',
    label: 'Hiring Requisition',
  },
  {
    value: 'leave',
    label: 'Leave',
  },
  {
    value: 'attendance',
    label: 'Attendance',
  },
  {
    value: 'attendance_request',
    label: 'Attendance Request',
  },
  {
    value: 'reimbursement',
    label: 'Reimbursement',
  },
  {
    value: 'loan',
    label: 'Loan',
  },
  {
    value: 'cash_advance',
    label: 'Cash Advance',
  },
  {
    value: 'payroll',
    label: 'Payroll',
  },
  {
    value: 'employee_movement',
    label: 'Employee Movement',
  },
]

const form = reactive({
  id: 0,
  company_id: 0,
  approval_type: 'leave',
  branch_id: null as number | null,
  department_id: null as number | null,
  job_level_id: null as number | null,
  name: '',
  code: '',
  description: '',
  is_active: true,
})

const filteredBranches = computed(() =>
  branches.value.filter((branch) => branch.company_id === form.company_id)
)

const filteredDepartments = computed(() =>
  departments.value.filter(
    (department) => department.company_id === form.company_id
  )
)

const filteredJobLevels = computed(() =>
  jobLevels.value.filter(
    (jobLevel) => jobLevel.company_id === form.company_id
  )
)

const currentScope = computed(() => {
  if (form.branch_id !== null) {
    return 'branch'
  }

  if (form.department_id !== null) {
    return 'department'
  }

  if (form.job_level_id !== null) {
    return 'job_level'
  }

  return 'company'
})

function handleScopeChange(event: Event) {
  const value = (event.target as HTMLSelectElement).value

  form.branch_id = null
  form.department_id = null
  form.job_level_id = null

  if (value === 'branch') {
    form.branch_id = filteredBranches.value[0]?.id ?? null
  }

  if (value === 'department') {
    form.department_id = filteredDepartments.value[0]?.id ?? null
  }

  if (value === 'job_level') {
    form.job_level_id = filteredJobLevels.value[0]?.id ?? null
  }
}

async function loadFlows() {
  loading.value = true
  errorMessage.value = ''

  try {
    const response = await apiClient.get('/api/approval-flows')

    flows.value = response.data.data.data
  } catch {
    errorMessage.value = 'Gagal memuat daftar approval flow.'
  } finally {
    loading.value = false
  }
}

async function loadReferenceData() {
  const [
    companyRes,
    branchRes,
    departmentRes,
    jobLevelRes,
  ] = await Promise.all([
    apiClient.get('/api/companies'),
    apiClient.get('/api/branches'),
    apiClient.get('/api/departments'),
    apiClient.get('/api/job-levels'),
  ])

  companies.value = companyRes.data.data.data
  branches.value = branchRes.data.data.data
  departments.value = departmentRes.data.data.data
  jobLevels.value = jobLevelRes.data.data.data
}

function resetForm() {
  form.id = 0
  form.company_id = companies.value[0]?.id ?? 0
  form.approval_type = 'leave'
  form.branch_id = null
  form.department_id = null
  form.job_level_id = null
  form.name = ''
  form.code = ''
  form.description = ''
  form.is_active = true
}

function openCreateModal() {
  isEditing.value = false
  formError.value = ''

  resetForm()

  showModal.value = true
}

function openEditModal(row: ApprovalFlowRow) {
  isEditing.value = true
  formError.value = ''

  form.id = row.id
  form.company_id = row.company_id
  form.approval_type = row.approval_type
  form.branch_id = row.branch_id
  form.department_id = row.department_id
  form.job_level_id = row.job_level_id
  form.name = row.name
  form.code = row.code
  form.description = row.description ?? ''
  form.is_active = row.is_active

  showModal.value = true
}

function closeModal() {
  showModal.value = false
}

async function handleSubmit() {
  formError.value = ''
  saving.value = true

  const payload = {
    company_id: form.company_id,
    approval_type: form.approval_type,
    branch_id: form.branch_id,
    department_id: form.department_id,
    job_level_id: form.job_level_id,
    name: form.name,
    code: form.code,
    description: form.description || null,
    is_active: form.is_active,
  }

  try {
    if (isEditing.value) {
      await apiClient.put(
        `/api/approval-flows/${form.id}`,
        payload
      )
    } else {
      await apiClient.post(
        '/api/approval-flows',
        payload
      )
    }

    showModal.value = false

    await loadFlows()
  } catch (err: any) {
    formError.value =
      err.response?.data?.message ||
      'Terjadi kesalahan, silakan coba lagi.'
  } finally {
    saving.value = false
  }
}

async function handleDelete(row: ApprovalFlowRow) {
  if (!confirm(`Hapus approval flow "${row.name}"?`)) {
    return
  }

  try {
    await apiClient.delete(`/api/approval-flows/${row.id}`)

    await loadFlows()
  } catch {
    alert('Gagal menghapus approval flow.')
  }
}

function goToDetail(row: ApprovalFlowRow) {
  router.push(`/approval-flows/${row.id}`)
}

function scopeLabel(row: ApprovalFlowRow) {
  if (row.branch) {
    return `${row.company.name} / Branch: ${row.branch.name}`
  }

  if (row.department) {
    return `${row.company.name} / Department: ${row.department.name}`
  }

  if (row.job_level) {
    return `${row.company.name} / Job Level: ${row.job_level.name}`
  }

  return `${row.company.name} / Company-wide`
}

onMounted(() => {
  loadFlows()
  loadReferenceData()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">
          Approval Flow
        </h1>

        <p class="mt-1 text-sm text-slate-500">
          Kelola template alur persetujuan untuk attendance, leave, payroll,
          reimbursement, dan modul lainnya.
        </p>
      </div>

      <button
        @click="openCreateModal"
        :disabled="companies.length === 0"
        class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-dark disabled:opacity-50"
      >
        <Plus
          class="h-4 w-4"
          :stroke-width="2"
        />

        Tambah Approval Flow
      </button>
    </div>

    <!-- No Company -->
    <p
      v-if="companies.length === 0 && !loading"
      class="rounded-xl bg-amber-50 p-4 text-sm text-amber-700"
    >
      Belum ada company. Tambahkan company terlebih dahulu sebelum membuat
      approval flow.
    </p>

    <!-- Loading -->
    <div
      v-if="loading"
      class="text-sm text-slate-400"
    >
      Memuat data...
    </div>

    <!-- Error -->
    <div
      v-else-if="errorMessage"
      class="rounded-xl bg-red-50 p-4 text-sm text-red-600"
    >
      {{ errorMessage }}
    </div>

    <!-- Table -->
    <div
      v-else
      class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]"
    >
      <table class="w-full text-left text-sm">
        <thead>
          <tr class="border-b border-slate-100 bg-slate-50/60">
            <th class="px-5 py-3 font-medium text-slate-500">
              Nama
            </th>

            <th class="px-5 py-3 font-medium text-slate-500">
              Approval For
            </th>

            <th class="px-5 py-3 font-medium text-slate-500">
              Scope
            </th>

            <th class="px-5 py-3 font-medium text-slate-500">
              Steps
            </th>

            <th class="px-5 py-3 font-medium text-slate-500">
              Status
            </th>

            <th class="px-5 py-3 text-right font-medium text-slate-500">
              Aksi
            </th>
          </tr>
        </thead>

        <tbody>
          <tr
            v-for="row in flows"
            :key="row.id"
            class="border-b border-slate-50 last:border-0 hover:bg-slate-50/50"
          >
            <!-- Name -->
            <td class="px-5 py-3.5">
              <p class="font-medium text-slate-800">
                {{ row.name }}
              </p>

              <p class="text-xs text-slate-400">
                {{ row.code }}
              </p>
            </td>

            <td class="px-5 py-3.5">
              <span class="rounded-full bg-primary-soft px-2.5 py-1 text-xs font-medium text-primary-dark">
                {{
                  approvalTypes.find(
                    (type) => type.value === row.approval_type
                  )?.label ?? row.approval_type
                }}
              </span>
            </td>

            <!-- Scope -->
            <td class="px-5 py-3.5 text-slate-500">
              {{ scopeLabel(row) }}
            </td>

            <!-- Steps -->
            <td class="px-5 py-3.5 text-slate-500">
              {{ row.steps_count }} step
            </td>

            <!-- Status -->
            <td class="px-5 py-3.5">
              <span
                class="rounded-full px-2.5 py-1 text-xs font-medium"
                :class="
                  row.is_active
                    ? 'bg-primary-soft text-primary-dark'
                    : 'bg-slate-50 text-slate-400'
                "
              >
                {{ row.is_active ? 'Aktif' : 'Nonaktif' }}
              </span>
            </td>

            <!-- Actions -->
            <td class="px-5 py-3.5">
              <div class="flex items-center justify-end gap-1">
                <button
                  @click="goToDetail(row)"
                  class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600"
                  title="Kelola Steps & Assignment"
                >
                  <ListTree
                    class="h-4 w-4"
                    :stroke-width="1.75"
                  />
                </button>

                <button
                  @click="openEditModal(row)"
                  class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600"
                  title="Edit"
                >
                  <Pencil
                    class="h-4 w-4"
                    :stroke-width="1.75"
                  />
                </button>

                <button
                  @click="handleDelete(row)"
                  class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-red-50 hover:text-red-500"
                  title="Hapus"
                >
                  <Trash2
                    class="h-4 w-4"
                    :stroke-width="1.75"
                  />
                </button>
              </div>
            </td>
          </tr>

          <tr v-if="flows.length === 0">
            <td
              colspan="6"
              class="px-5 py-10 text-center text-sm text-slate-400"
            >
              Belum ada approval flow.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <Teleport to="body">
      <div
        v-if="showModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8"
      >
        <div
          class="flex max-h-full w-full max-w-xl flex-col rounded-2xl bg-white shadow-xl"
        >
          <!-- Modal Header -->
          <div
            class="flex items-center justify-between border-b border-slate-100 px-6 py-4"
          >
            <h2 class="text-lg font-semibold text-slate-900">
              {{ isEditing ? 'Edit Approval Flow' : 'Tambah Approval Flow' }}
            </h2>

            <button
              @click="closeModal"
              class="rounded-lg p-1 text-slate-400 hover:bg-slate-50"
            >
              <X class="h-5 w-5" />
            </button>
          </div>

          <!-- Form -->
          <form
            @submit.prevent="handleSubmit"
            class="flex-1 space-y-5 overflow-y-auto px-6 py-5"
          >
            <!-- Scope -->
            <div>
              <div class="mb-3 flex items-center gap-2">
                <div
                  class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary-soft text-primary-dark"
                >
                  <Building2
                    class="h-4 w-4"
                    :stroke-width="1.75"
                  />
                </div>

                <h3
                  class="text-xs font-semibold uppercase tracking-wider text-slate-400"
                >
                  Scope
                </h3>
              </div>

              <div class="space-y-3">
                <!-- Company -->
                <div>
                  <label
                    class="mb-1 block text-sm font-medium text-slate-700"
                  >
                    Company
                  </label>

                  <select
                    v-model.number="form.company_id"
                    required
                    @change="
                      form.branch_id = null;
                      form.department_id = null;
                      form.job_level_id = null;
                    "
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
                  >
                    <option
                      v-for="company in companies"
                      :key="company.id"
                      :value="company.id"
                    >
                      {{ company.name }}
                    </option>
                  </select>
                </div>

                <!-- Approval For -->
                <div>
                  <label
                    class="mb-1 block text-sm font-medium text-slate-700"
                  >
                    Approval For
                  </label>

                  <select
                    v-model="form.approval_type"
                    required
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
                  >
                    <option
                      v-for="type in approvalTypes"
                      :key="type.value"
                      :value="type.value"
                    >
                      {{ type.label }}
                    </option>
                  </select>

                  <p class="mt-1 text-xs text-slate-400">
                    Menentukan proses bisnis yang menggunakan approval flow ini.
                  </p>
                </div>

                <!-- Scope Type -->
                <div>
                  <label
                    class="mb-1 block text-sm font-medium text-slate-700"
                  >
                    Scope Type
                  </label>

                  <select
                    :value="currentScope"
                    @change="handleScopeChange"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
                  >
                    <option value="company">
                      Company-wide
                    </option>

                    <option value="branch">
                      Branch
                    </option>

                    <option value="department">
                      Department
                    </option>

                    <option value="job_level">
                      Job Level
                    </option>
                  </select>
                </div>

                <!-- Branch -->
                <div v-if="currentScope === 'branch'">
                  <label
                    class="mb-1 block text-sm font-medium text-slate-700"
                  >
                    Branch
                  </label>

                  <select
                    v-model="form.branch_id"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
                  >
                    <option
                      v-for="branch in filteredBranches"
                      :key="branch.id"
                      :value="branch.id"
                    >
                      {{ branch.name }}
                    </option>
                  </select>

                  <p
                    v-if="filteredBranches.length === 0"
                    class="mt-1 text-xs text-red-500"
                  >
                    Belum ada branch untuk company ini.
                  </p>
                </div>

                <!-- Department -->
                <div v-if="currentScope === 'department'">
                  <label
                    class="mb-1 block text-sm font-medium text-slate-700"
                  >
                    Department
                  </label>

                  <select
                    v-model="form.department_id"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
                  >
                    <option
                      v-for="department in filteredDepartments"
                      :key="department.id"
                      :value="department.id"
                    >
                      {{ department.name }}
                    </option>
                  </select>

                  <p
                    v-if="filteredDepartments.length === 0"
                    class="mt-1 text-xs text-red-500"
                  >
                    Belum ada department untuk company ini.
                  </p>
                </div>

                <!-- Job Level -->
                <div v-if="currentScope === 'job_level'">
                  <label
                    class="mb-1 block text-sm font-medium text-slate-700"
                  >
                    Job Level
                  </label>

                  <select
                    v-model="form.job_level_id"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
                  >
                    <option
                      v-for="jobLevel in filteredJobLevels"
                      :key="jobLevel.id"
                      :value="jobLevel.id"
                    >
                      {{ jobLevel.name }}
                    </option>
                  </select>

                  <p
                    v-if="filteredJobLevels.length === 0"
                    class="mt-1 text-xs text-red-500"
                  >
                    Belum ada job level untuk company ini.
                  </p>
                </div>

                <!-- Scope Explanation -->
                <p class="text-xs text-slate-400">
                  Satu Approval Flow hanya memiliki satu scope. Pilih
                  Company-wide sebagai fallback company, atau gunakan Branch,
                  Department, maupun Job Level untuk flow yang lebih spesifik.
                </p>
              </div>
            </div>

            <!-- Basic Information -->
            <div class="space-y-3">
              <!-- Name -->
              <div>
                <label
                  class="mb-1 block text-sm font-medium text-slate-700"
                >
                  Nama Flow
                </label>

                <input
                  v-model="form.name"
                  type="text"
                  required
                  placeholder="Approval Cuti Standar"
                  class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
                />
              </div>

              <!-- Code -->
              <div>
                <label
                  class="mb-1 block text-sm font-medium text-slate-700"
                >
                  Kode
                </label>

                <input
                  v-model="form.code"
                  type="text"
                  required
                  placeholder="LEAVE_STANDARD"
                  class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
                />
              </div>

              <!-- Description -->
              <div>
                <label
                  class="mb-1 block text-sm font-medium text-slate-700"
                >
                  Deskripsi
                </label>

                <textarea
                  v-model="form.description"
                  rows="2"
                  class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
                ></textarea>
              </div>

              <!-- Active -->
              <label
                class="flex cursor-pointer items-center justify-between rounded-xl border border-slate-200 px-4 py-3"
              >
                <p class="text-sm font-medium text-slate-700">
                  Aktif
                </p>

                <input
                  v-model="form.is_active"
                  type="checkbox"
                  class="peer sr-only"
                />

                <div
                  class="relative h-6 w-11 shrink-0 rounded-full bg-slate-200 transition-colors after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow-sm after:transition-transform peer-checked:bg-primary peer-checked:after:translate-x-5"
                ></div>
              </label>
            </div>

            <!-- Error -->
            <p
              v-if="formError"
              class="text-sm text-red-600"
            >
              {{ formError }}
            </p>
          </form>

          <!-- Footer -->
          <div
            class="border-t border-slate-100 px-6 py-4"
          >
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
  </div>
</template>