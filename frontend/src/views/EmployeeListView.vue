<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue'
import { Plus, Pencil, Trash2, X } from 'lucide-vue-next'
import apiClient from '@/lib/axios'
import OrgChart from '@/components/employee/OrgChart.vue'

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
  employment_status: Ref | null
}

const view = ref<'directory' | 'orgchart'>('directory')

const employees = ref<EmployeeRow[]>([])
const companies = ref<Ref[]>([])
const branches = ref<Ref[]>([])
const departments = ref<Ref[]>([])
const positions = ref<Ref[]>([])
const jobLevels = ref<Ref[]>([])
const employmentStatuses = ref<Ref[]>([])
const managerOptions = ref<{ id: number; employee_number: string; first_name: string; last_name: string | null }[]>([])

const loading = ref(true)
const errorMessage = ref('')

const showModal = ref(false)
const isEditing = ref(false)
const saving = ref(false)
const formError = ref('')

const form = reactive({
  id: 0,
  employee_number: '',
  company_id: 0,
  branch_id: null as number | null,
  department_id: null as number | null,
  position_id: null as number | null,
  job_level_id: null as number | null,
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
  const [companyRes, branchRes, departmentRes, positionRes, jobLevelRes, statusRes, employeeRes] = await Promise.all([
    apiClient.get('/api/companies'),
    apiClient.get('/api/branches'),
    apiClient.get('/api/departments'),
    apiClient.get('/api/positions'),
    apiClient.get('/api/job-levels'),
    apiClient.get('/api/employment-statuses'),
    apiClient.get('/api/employees'),
  ])

  companies.value = companyRes.data.data.data
  branches.value = branchRes.data.data.data
  departments.value = departmentRes.data.data.data
  positions.value = positionRes.data.data.data
  jobLevels.value = jobLevelRes.data.data.data
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

onMounted(() => {
  loadEmployees()
  loadReferenceData()
})
</script>

<template>
  <div class="space-y-6">
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
      <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
      <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">
        {{ errorMessage }}
      </div>

      <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <table class="w-full text-left text-sm">
          <thead>
            <tr class="border-b border-slate-100 bg-slate-50/60">
              <th class="px-5 py-3 font-medium text-slate-500">No. Karyawan</th>
              <th class="px-5 py-3 font-medium text-slate-500">Nama</th>
              <th class="px-5 py-3 font-medium text-slate-500">Company</th>
              <th class="px-5 py-3 font-medium text-slate-500">Department</th>
              <th class="px-5 py-3 font-medium text-slate-500">Position</th>
              <th class="px-5 py-3 font-medium text-slate-500">Job Level</th>
              <th class="px-5 py-3 font-medium text-slate-500">Status</th>
              <th class="px-5 py-3 text-right font-medium text-slate-500">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in employees"
              :key="row.id"
              class="border-b border-slate-50 last:border-0 hover:bg-slate-50/50"
            >
              <td class="px-5 py-3.5 text-slate-500">{{ row.employee_number }}</td>
              <td class="px-5 py-3.5 font-medium text-slate-800">{{ fullName(row) }}</td>
              <td class="px-5 py-3.5 text-slate-500">{{ row.company.name }}</td>
              <td class="px-5 py-3.5 text-slate-500">{{ row.department?.name ?? '-' }}</td>
              <td class="px-5 py-3.5 text-slate-500">{{ row.position?.name ?? '-' }}</td>
              <td class="px-5 py-3.5 text-slate-500">{{ row.job_level?.name ?? '-' }}</td>
              <td class="px-5 py-3.5">
                <span
                  v-if="row.employment_status"
                  class="rounded-full bg-primary-soft px-2.5 py-1 text-xs font-medium text-primary-dark"
                >
                  {{ row.employment_status.name }}
                </span>
                <span v-else class="text-slate-400">-</span>
              </td>
              <td class="px-5 py-3.5">
                <div class="flex items-center justify-end gap-1">
                  <button
                    @click="openEditModal(row)"
                    class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600"
                  >
                    <Pencil class="h-4 w-4" :stroke-width="1.75" />
                  </button>
                  <button
                    @click="handleDelete(row)"
                    class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-red-50 hover:text-red-500"
                  >
                    <Trash2 class="h-4 w-4" :stroke-width="1.75" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <!-- Org chart view -->
    <OrgChart v-else />

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

          <form @submit.prevent="handleSubmit" class="flex-1 space-y-6 overflow-y-auto px-6 py-5">
            <!-- Employment Information -->
            <div>
              <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Employment Information</h3>
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
              <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Personal Information</h3>
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
              <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Contact Information</h3>
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
              <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Identity Information</h3>
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
  </div>
</template>