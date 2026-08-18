<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import apiClient from '@/lib/axios'
import { useAuthStore } from '@/stores/auth'
import BaseModal from '@/components/ui/BaseModal.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import { Plus, Search } from 'lucide-vue-next'

interface RefOption { id: number; name: string }
interface EmployeeOption { id: number; first_name: string; last_name: string | null }

interface HiringRequisitionRow {
  id: number
  reason: string
  headcount_requested: number
  status: string
  requested_at: string
  position: RefOption | null
  department: RefOption | null
  requested_by: EmployeeOption | null
}

const router = useRouter()
const authStore = useAuthStore()

const loading = ref(true)
const errorMessage = ref('')
const requisitions = ref<HiringRequisitionRow[]>([])

const search = ref('')
const statusFilter = ref('')

const STATUS_OPTIONS = [
  { value: '', label: 'Semua Status' },
  { value: 'draft', label: 'Draft' },
  { value: 'pending', label: 'Pending' },
  { value: 'open', label: 'Open' },
  { value: 'rejected', label: 'Rejected' },
  { value: 'cancelled', label: 'Cancelled' },
  { value: 'closed', label: 'Closed' },
]

const STATUS_BADGE: Record<string, string> = {
  draft: 'bg-slate-100 text-slate-600',
  pending: 'bg-amber-50 text-amber-600',
  open: 'bg-emerald-50 text-emerald-600',
  rejected: 'bg-red-50 text-red-600',
  cancelled: 'bg-slate-100 text-slate-400',
  closed: 'bg-primary-soft text-primary-dark',
}

function employeeName(e: EmployeeOption | null): string {
  if (!e) return '-'
  return [e.first_name, e.last_name].filter(Boolean).join(' ')
}

async function loadRequisitions() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/hiring-requisitions')
    requisitions.value = response.data.data.data
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Gagal memuat daftar Hiring Requisition.'
  } finally {
    loading.value = false
  }
}

const filteredRequisitions = computed(() => {
  return requisitions.value.filter((r) => {
    const matchesSearch =
      !search.value ||
      r.position?.name?.toLowerCase().includes(search.value.toLowerCase()) ||
      r.department?.name?.toLowerCase().includes(search.value.toLowerCase())
    const matchesStatus = !statusFilter.value || r.status === statusFilter.value
    return matchesSearch && matchesStatus
  })
})

// ---- Create modal ----
const showCreateModal = ref(false)
const saving = ref(false)
const formError = ref('')

const branches = ref<RefOption[]>([])
const departments = ref<RefOption[]>([])
const positions = ref<RefOption[]>([])
const employmentTypes = ref<RefOption[]>([])
const employees = ref<EmployeeOption[]>([])

const form = reactive({
  branch_id: '' as number | '',
  department_id: '' as number | '',
  position_id: '' as number | '',
  reason: 'new_position',
  replacement_for_employee_id: '' as number | '',
  employment_type_id: '' as number | '',
  headcount_requested: 1,
  target_start_date: '',
  justification: '',
})

function resetForm() {
  form.branch_id = ''
  form.department_id = ''
  form.position_id = ''
  form.reason = 'new_position'
  form.replacement_for_employee_id = ''
  form.employment_type_id = ''
  form.headcount_requested = 1
  form.target_start_date = ''
  form.justification = ''
  formError.value = ''
}

async function loadReferenceData() {
  const [branchRes, deptRes, posRes, typeRes, empRes] = await Promise.all([
    apiClient.get('/api/branches'),
    apiClient.get('/api/departments'),
    apiClient.get('/api/positions'),
    apiClient.get('/api/employment-types'),
    apiClient.get('/api/employees'),
  ])
  branches.value = branchRes.data.data.data ?? branchRes.data.data
  departments.value = deptRes.data.data.data ?? deptRes.data.data
  positions.value = posRes.data.data.data ?? posRes.data.data
  employmentTypes.value = typeRes.data.data.data ?? typeRes.data.data
  employees.value = empRes.data.data.data ?? empRes.data.data
}

function openCreateModal() {
  resetForm()
  showCreateModal.value = true
}

async function submitCreate() {
  saving.value = true
  formError.value = ''

  try {
    const selectedEmploymentType = employmentTypes.value.find(
      (type) => type.id === form.employment_type_id
    )

    await apiClient.post('/api/hiring-requisitions', {
      branch_id: form.branch_id || null,
      department_id: form.department_id,
      position_id: form.position_id,
      reason: form.reason,
      replacement_for_employee_id:
        form.reason === 'replacement'
          ? form.replacement_for_employee_id
          : null,
      employment_type: selectedEmploymentType?.name ?? '',
      headcount_requested: form.headcount_requested,
      target_start_date: form.target_start_date || null,
      justification: form.justification,
    })

    showCreateModal.value = false
    await loadRequisitions()
  } catch (err: any) {
    formError.value =
      err.response?.data?.message ||
      'Gagal mengajukan Hiring Requisition.'
  } finally {
    saving.value = false
  }
}

function goToDetail(id: number) {
  router.push({ name: 'hiring-requisitions.show', params: { id } })
}

onMounted(async () => {
  await loadRequisitions()
  await loadReferenceData()
})
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-lg font-semibold text-slate-800">Hiring Requisitions</h1>
        <p class="text-sm text-slate-400">Pengajuan izin buka posisi/headcount, sebelum Job Vacancy dibuat.</p>
      </div>
      <button
        v-if="authStore.permissions.includes('create hiring requisitions')"
        class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white"
        @click="openCreateModal"
      >
        <Plus class="h-4 w-4" /> Ajukan Requisition
      </button>
    </div>

    <div class="flex items-center gap-3">
      <div class="relative flex-1 max-w-xs">
        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-300" />
        <input v-model="search" type="text" placeholder="Cari position/department..." class="w-full rounded-xl border border-slate-200 py-2 pl-9 pr-3 text-sm" />
      </div>
      <select v-model="statusFilter" class="rounded-xl border border-slate-200 py-2 px-3 text-sm">
        <option v-for="opt in STATUS_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
      </select>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>
    <EmptyState v-else-if="filteredRequisitions.length === 0" title="Belum ada Hiring Requisition" />
    <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-100 text-xs uppercase text-slate-400">
          <tr>
            <th class="px-4 py-3">Position / Department</th>
            <th class="px-4 py-3">Reason</th>
            <th class="px-4 py-3">Headcount</th>
            <th class="px-4 py-3">Requested By</th>
            <th class="px-4 py-3">Status</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="r in filteredRequisitions"
            :key="r.id"
            class="cursor-pointer border-b border-slate-50 hover:bg-slate-50"
            @click="goToDetail(r.id)"
          >
            <td class="px-4 py-3 font-medium text-slate-700">{{ r.position?.name || '-' }} / {{ r.department?.name || '-' }}</td>
            <td class="px-4 py-3 text-slate-500">{{ r.reason === 'replacement' ? 'Replacement' : 'New Position' }}</td>
            <td class="px-4 py-3 text-slate-500">{{ r.headcount_requested }}</td>
            <td class="px-4 py-3 text-slate-500">{{ employeeName(r.requested_by) }}</td>
            <td class="px-4 py-3">
              <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="STATUS_BADGE[r.status]">{{ r.status }}</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <Teleport to="body">
      <BaseModal v-if="showCreateModal" title="Ajukan Hiring Requisition" @close="showCreateModal = false">
        <form class="space-y-3" @submit.prevent="submitCreate">
          <div v-if="formError" class="rounded-xl bg-red-50 p-3 text-sm text-red-600">{{ formError }}</div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-xs font-medium text-slate-500">Department</label>
              <select v-model="form.department_id" required class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm">
                <option value="" disabled>Pilih Department</option>
                <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
              </select>
            </div>
            <div>
              <label class="text-xs font-medium text-slate-500">Position</label>
              <select v-model="form.position_id" required class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm">
                <option value="" disabled>Pilih Position</option>
                <option v-for="p in positions" :key="p.id" :value="p.id">{{ p.name }}</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-xs font-medium text-slate-500">Branch (opsional)</label>
              <select v-model="form.branch_id" class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm">
                <option value="">-</option>
                <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
              </select>
            </div>
            <div>
              <label class="text-xs font-medium text-slate-500">Employment Type</label>
              <select v-model="form.employment_type_id" required class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm">
                <option value="" disabled>Pilih Employment Type</option>
                <option v-for="t in employmentTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
              </select>
            </div>
          </div>

          <div>
            <label class="text-xs font-medium text-slate-500">Reason</label>
            <select v-model="form.reason" class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm">
              <option value="new_position">New Position</option>
              <option value="replacement">Replacement</option>
            </select>
          </div>

          <div v-if="form.reason === 'replacement'">
            <label class="text-xs font-medium text-slate-500">Menggantikan Employee</label>
            <select v-model="form.replacement_for_employee_id" required class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm">
              <option value="" disabled>Pilih Employee</option>
              <option v-for="e in employees" :key="e.id" :value="e.id">{{ employeeName(e) }}</option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-xs font-medium text-slate-500">Headcount Requested</label>
              <input v-model.number="form.headcount_requested" type="number" min="1" required class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm" />
            </div>
            <div>
              <label class="text-xs font-medium text-slate-500">Target Start Date (opsional)</label>
              <input v-model="form.target_start_date" type="date" class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm" />
            </div>
          </div>

          <div>
            <label class="text-xs font-medium text-slate-500">Justifikasi</label>
            <textarea v-model="form.justification" required rows="3" class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm" />
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button type="button" class="rounded-xl border border-slate-200 px-4 py-2 text-sm" @click="showCreateModal = false">Batal</button>
            <button type="submit" :disabled="saving" class="rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white disabled:opacity-50">
              {{ saving ? 'Menyimpan...' : 'Ajukan' }}
            </button>
          </div>
        </form>
      </BaseModal>
    </Teleport>
  </div>
</template>