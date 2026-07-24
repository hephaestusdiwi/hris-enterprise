<script setup lang="ts">
import { ref, onMounted, reactive, computed } from 'vue'
import { Plus, Pencil, Trash2, X } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface Ref {
  id: number
  name: string
  company_id?: number
}

type TargetType = 'company' | 'branch' | 'department' | 'position'

interface WorkingScheduleAssignmentRow {
  id: number
  working_schedule_id: number
  target_type: TargetType
  target_id: number
  is_active: boolean
  working_schedule: Ref
  target: Ref
}

const targetTypeLabels: Record<TargetType, string> = {
  company: 'Company',
  branch: 'Branch',
  department: 'Department',
  position: 'Position',
}

const targetTypeBadgeClass: Record<TargetType, string> = {
  company: 'bg-slate-100 text-slate-600',
  branch: 'bg-blue-50 text-blue-600',
  department: 'bg-violet-50 text-violet-600',
  position: 'bg-amber-50 text-amber-600',
}

const assignments = ref<WorkingScheduleAssignmentRow[]>([])
const companies = ref<Ref[]>([])
const branches = ref<Ref[]>([])
const departments = ref<Ref[]>([])
const positions = ref<Ref[]>([])
const workingSchedules = ref<Ref[]>([])

const loading = ref(true)
const errorMessage = ref('')

const showModal = ref(false)
const isEditing = ref(false)
const saving = ref(false)
const formError = ref('')

const form = reactive({
  id: 0,
  company_id: 0,
  target_type: 'branch' as TargetType,
  target_id: null as number | null,
  working_schedule_id: null as number | null,
  is_active: true,
})

const filteredBranches = computed(() => branches.value.filter((b) => b.company_id === form.company_id))
const filteredDepartments = computed(() => departments.value.filter((d) => d.company_id === form.company_id))
const filteredPositions = computed(() => positions.value.filter((p) => p.company_id === form.company_id))
const filteredSchedules = computed(() => workingSchedules.value.filter((s) => s.company_id === form.company_id))

const targetOptions = computed(() => {
  switch (form.target_type) {
    case 'branch':
      return filteredBranches.value
    case 'department':
      return filteredDepartments.value
    case 'position':
      return filteredPositions.value
    default:
      return []
  }
})

async function loadAssignments() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/working-schedule-assignments')
    assignments.value = response.data.data.data
  } catch {
    errorMessage.value = 'Gagal memuat daftar Working Schedule Assignment.'
  } finally {
    loading.value = false
  }
}

async function loadReferenceData() {
  const [companyRes, branchRes, departmentRes, positionRes, scheduleRes] = await Promise.all([
    apiClient.get('/api/companies'),
    apiClient.get('/api/branches'),
    apiClient.get('/api/departments'),
    apiClient.get('/api/positions'),
    apiClient.get('/api/working-schedules'),
  ])
  companies.value = companyRes.data.data.data
  branches.value = branchRes.data.data.data
  departments.value = departmentRes.data.data.data
  positions.value = positionRes.data.data.data
  workingSchedules.value = scheduleRes.data.data.data
}

function resetForm() {
  form.id = 0
  form.company_id = companies.value[0]?.id ?? 0
  form.target_type = 'branch'
  form.target_id = null
  form.working_schedule_id = null
  form.is_active = true
}

function openCreateModal() {
  isEditing.value = false
  formError.value = ''
  resetForm()
  showModal.value = true
}

function openEditModal(row: WorkingScheduleAssignmentRow) {
  isEditing.value = true
  formError.value = ''
  form.id = row.id
  form.company_id = row.working_schedule.company_id ?? companies.value[0]?.id ?? 0
  form.target_type = row.target_type
  form.target_id = row.target_type === 'company' ? null : row.target_id
  form.working_schedule_id = row.working_schedule_id
  form.is_active = row.is_active
  showModal.value = true
}

function closeModal() {
  showModal.value = false
}

function handleTargetTypeChange() {
  form.target_id = null
}

async function handleSubmit() {
  formError.value = ''
  saving.value = true

  const resolvedTargetId = form.target_type === 'company' ? form.company_id : form.target_id

  const payload = {
    working_schedule_id: form.working_schedule_id,
    target_type: form.target_type,
    target_id: resolvedTargetId,
    is_active: form.is_active,
  }

  try {
    if (isEditing.value) {
      await apiClient.put(`/api/working-schedule-assignments/${form.id}`, payload)
    } else {
      await apiClient.post('/api/working-schedule-assignments', payload)
    }

    showModal.value = false
    await loadAssignments()
  } catch (err: any) {
    formError.value = err.response?.data?.message || 'Terjadi kesalahan, silakan coba lagi.'
  } finally {
    saving.value = false
  }
}

async function handleDelete(row: WorkingScheduleAssignmentRow) {
  if (!confirm(`Hapus assignment "${row.working_schedule.name}" untuk "${row.target.name}"?`)) return

  try {
    await apiClient.delete(`/api/working-schedule-assignments/${row.id}`)
    await loadAssignments()
  } catch {
    alert('Gagal menghapus assignment.')
  }
}

onMounted(() => {
  loadAssignments()
  loadReferenceData()
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Working Schedule Assignment</h1>
        <p class="mt-1 text-sm text-slate-500">
          Tentukan template Working Schedule berlaku untuk Company, Branch, Department, atau Position mana.
        </p>
      </div>
      <button
        @click="openCreateModal"
        :disabled="companies.length === 0"
        class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-dark disabled:opacity-50"
      >
        <Plus class="h-4 w-4" :stroke-width="2" />
        Tambah Assignment
      </button>
    </div>

    <p v-if="companies.length === 0 && !loading" class="rounded-xl bg-amber-50 p-4 text-sm text-amber-700">
      Belum ada company. Tambahkan company terlebih dahulu.
    </p>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">
      {{ errorMessage }}
    </div>
    <div v-else-if="assignments.length === 0" class="rounded-xl bg-slate-50 p-4 text-sm text-slate-400">
      Belum ada Working Schedule Assignment.
    </div>

    <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <table class="w-full text-left text-sm">
        <thead>
          <tr class="border-b border-slate-100 bg-slate-50/60">
            <th class="px-5 py-3 font-medium text-slate-500">Level</th>
            <th class="px-5 py-3 font-medium text-slate-500">Target</th>
            <th class="px-5 py-3 font-medium text-slate-500">Working Schedule</th>
            <th class="px-5 py-3 font-medium text-slate-500">Status</th>
            <th class="px-5 py-3 text-right font-medium text-slate-500">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in assignments"
            :key="row.id"
            class="border-b border-slate-50 last:border-0 hover:bg-slate-50/50"
          >
            <td class="px-5 py-3.5">
              <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="targetTypeBadgeClass[row.target_type]">
                {{ targetTypeLabels[row.target_type] }}
              </span>
            </td>
            <td class="px-5 py-3.5 font-medium text-slate-800">{{ row.target?.name ?? '-' }}</td>
            <td class="px-5 py-3.5 text-slate-500">{{ row.working_schedule.name }}</td>
            <td class="px-5 py-3.5">
              <span
                class="rounded-full px-2.5 py-1 text-xs font-medium"
                :class="row.is_active ? 'bg-primary-soft text-primary-dark' : 'bg-slate-50 text-slate-400'"
              >
                {{ row.is_active ? 'Aktif' : 'Nonaktif' }}
              </span>
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

    <Teleport to="body">
      <div
        v-if="showModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8"
      >
        <div class="flex max-h-full w-full max-w-lg flex-col rounded-2xl bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">
              {{ isEditing ? 'Edit Assignment' : 'Tambah Assignment' }}
            </h2>
            <button @click="closeModal" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50">
              <X class="h-5 w-5" />
            </button>
          </div>

          <form @submit.prevent="handleSubmit" class="flex-1 space-y-4 overflow-y-auto px-6 py-5">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Company</label>
              <select
                v-model.number="form.company_id"
                required
                @change="form.target_id = null; form.working_schedule_id = null"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
              >
                <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Level Target</label>
              <select
                v-model="form.target_type"
                required
                @change="handleTargetTypeChange"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
              >
                <option value="company">Company (berlaku untuk seluruh company)</option>
                <option value="branch">Branch</option>
                <option value="department">Department</option>
                <option value="position">Position</option>
              </select>
            </div>

            <div v-if="form.target_type !== 'company'">
              <label class="mb-1 block text-sm font-medium text-slate-700">{{ targetTypeLabels[form.target_type] }}</label>
              <select
                v-model.number="form.target_id"
                required
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
              >
                <option :value="null" disabled>Pilih {{ targetTypeLabels[form.target_type].toLowerCase() }}</option>
                <option v-for="t in targetOptions" :key="t.id" :value="t.id">{{ t.name }}</option>
              </select>
              <p v-if="targetOptions.length === 0" class="mt-1 text-xs text-slate-400">
                Belum ada {{ targetTypeLabels[form.target_type].toLowerCase() }} di company ini.
              </p>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Working Schedule</label>
              <select
                v-model.number="form.working_schedule_id"
                required
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
              >
                <option :value="null" disabled>Pilih working schedule</option>
                <option v-for="s in filteredSchedules" :key="s.id" :value="s.id">{{ s.name }}</option>
              </select>
              <p v-if="filteredSchedules.length === 0" class="mt-1 text-xs text-slate-400">
                Belum ada Working Schedule di company ini.
              </p>
            </div>

            <label class="flex cursor-pointer items-center justify-between rounded-xl border border-slate-200 px-4 py-3">
              <p class="text-sm font-medium text-slate-700">Aktif</p>
              <input v-model="form.is_active" type="checkbox" class="peer sr-only" />
              <div class="relative h-6 w-11 shrink-0 rounded-full bg-slate-200 transition-colors after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow-sm after:transition-transform peer-checked:bg-primary peer-checked:after:translate-x-5"></div>
            </label>

            <p class="text-xs text-slate-400">
              Prioritas resolusi: Employee override (di form Employee) → Position → Department → Branch → Company. Assignment yang lebih spesifik selalu menang.
            </p>

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