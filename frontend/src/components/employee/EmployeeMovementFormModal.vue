<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { X } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface Ref { id: number; name: string; code?: string }

const props = defineProps<{
  employeeId: number
  employeeName: string
  defaultType?: string
}>()
const emit = defineEmits<{ close: []; created: [] }>()

const MOVEMENT_TYPES = [
  { value: 'transfer', label: 'Transfer' },
  { value: 'promotion', label: 'Promotion' },
  { value: 'demotion', label: 'Demotion' },
  { value: 'contract_change', label: 'Extend / Ubah Contract' },
  { value: 'probation_confirmed', label: 'Change Status' },
  { value: 'resignation', label: 'Resignation' },
  { value: 'rehire', label: 'Rehire' },
]

const movementType = ref(props.defaultType ?? 'transfer')
const effectiveDate = ref(new Date().toISOString().slice(0, 10))
const reason = ref('')
const saving = ref(false)
const errorMessage = ref('')

const form = ref<Record<string, string | number | null>>({
  company_id: null,
  branch_id: null,
  department_id: null,
  position_id: null,
  manager_employee_id: null,
  job_level_id: null,
  employment_type_id: null,
  employment_status_id: null,
  contract_start_date: null,
  contract_end_date: null,
  probation_end_date: null,
  resign_date: null,
  join_date: null,
})

const companies = ref<Ref[]>([])
const branches = ref<Ref[]>([])
const departments = ref<Ref[]>([])
const positions = ref<Ref[]>([])
const jobLevels = ref<Ref[]>([])
const employmentTypes = ref<Ref[]>([])
const employmentStatuses = ref<Ref[]>([])
const employees = ref<{ id: number; first_name: string; last_name: string | null }[]>([])

onMounted(async () => {
  const [c, b, d, p, jl, et, es, emp] = await Promise.all([
    apiClient.get('/api/companies'),
    apiClient.get('/api/branches'),
    apiClient.get('/api/departments'),
    apiClient.get('/api/positions'),
    apiClient.get('/api/job-levels'),
    apiClient.get('/api/employment-types'),
    apiClient.get('/api/employment-statuses'),
    apiClient.get('/api/employees'),
  ])
  companies.value = c.data.data.data
  branches.value = b.data.data.data
  departments.value = d.data.data.data
  positions.value = p.data.data.data
  jobLevels.value = jl.data.data.data
  employmentTypes.value = et.data.data.data.filter((t: Ref & { is_active: boolean }) => t.is_active)
  employmentStatuses.value = es.data.data.data
  employees.value = emp.data.data.data
})

// Field yang relevan per movement_type (cermin dari
// EmployeeMovementType::relevantFields() di backend — cuma buat nentuin
// field mana yang ditampilkan, validasi sebenarnya tetap di backend).
const visibleFields = computed<string[]>(() => {
  switch (movementType.value) {
    case 'transfer':
      return ['company_id', 'branch_id', 'department_id', 'position_id', 'manager_employee_id']
    case 'promotion':
    case 'demotion':
      return ['job_level_id', 'position_id']
    case 'contract_change':
      return ['employment_type_id', 'contract_start_date', 'contract_end_date']
    case 'probation_confirmed':
      return ['probation_end_date', 'employment_type_id', 'employment_status_id']
    case 'resignation':
      return ['employment_status_id', 'resign_date']
    case 'rehire':
      return ['employment_status_id', 'resign_date', 'join_date']
    default:
      return []
  }
})

function isVisible(field: string) {
  return visibleFields.value.includes(field)
}

async function submit() {
  saving.value = true
  errorMessage.value = ''
  try {
    const payload: Record<string, string | number | null> = {
      movement_type: movementType.value,
      effective_date: effectiveDate.value,
      reason: reason.value || null,
    }
    for (const field of visibleFields.value) {
      payload[field] = form.value[field]
    }

    await apiClient.post(`/api/employees/${props.employeeId}/movements`, payload)
    emit('created')
  } catch (err: unknown) {
    const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
    errorMessage.value = message ?? 'Gagal mengajukan movement. Pastikan Approval Flow sudah dikonfigurasi.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
    <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-slate-800">Ajukan Employee Movement — {{ employeeName }}</h2>
        <button type="button" @click="emit('close')"><X class="h-4 w-4 text-slate-400" /></button>
      </div>

      <div class="space-y-4">
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">Movement Type</label>
          <select v-model="movementType" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
            <option v-for="t in MOVEMENT_TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
          </select>
        </div>

        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">Effective Date</label>
          <input v-model="effectiveDate" type="date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </div>

        <div v-if="isVisible('company_id')">
          <label class="mb-1 block text-xs font-medium text-slate-600">Company</label>
          <select v-model="form.company_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
            <option :value="null">-</option>
            <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
        <div v-if="isVisible('branch_id')">
          <label class="mb-1 block text-xs font-medium text-slate-600">Branch</label>
          <select v-model="form.branch_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
            <option :value="null">-</option>
            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
          </select>
        </div>
        <div v-if="isVisible('department_id')">
          <label class="mb-1 block text-xs font-medium text-slate-600">Department</label>
          <select v-model="form.department_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
            <option :value="null">-</option>
            <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
          </select>
        </div>
        <div v-if="isVisible('position_id')">
          <label class="mb-1 block text-xs font-medium text-slate-600">Position</label>
          <select v-model="form.position_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
            <option :value="null">-</option>
            <option v-for="p in positions" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </div>
        <div v-if="isVisible('job_level_id')">
          <label class="mb-1 block text-xs font-medium text-slate-600">Job Level</label>
          <select v-model="form.job_level_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
            <option :value="null">-</option>
            <option v-for="jl in jobLevels" :key="jl.id" :value="jl.id">{{ jl.name }}</option>
          </select>
        </div>
        <div v-if="isVisible('manager_employee_id')">
          <label class="mb-1 block text-xs font-medium text-slate-600">Manager</label>
          <select v-model="form.manager_employee_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
            <option :value="null">-</option>
            <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.first_name }} {{ e.last_name }}</option>
          </select>
        </div>
        <div v-if="isVisible('employment_type_id')">
          <label class="mb-1 block text-xs font-medium text-slate-600">Employment Type</label>
          <select v-model="form.employment_type_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
            <option :value="null">-</option>
            <option v-for="et in employmentTypes" :key="et.id" :value="et.id">{{ et.name }}</option>
          </select>
        </div>
        <div v-if="isVisible('employment_status_id')">
          <label class="mb-1 block text-xs font-medium text-slate-600">Employment Status</label>
          <select v-model="form.employment_status_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
            <option :value="null">-</option>
            <option v-for="es in employmentStatuses" :key="es.id" :value="es.id">{{ es.name }}</option>
          </select>
        </div>
        <div v-if="isVisible('contract_start_date')">
          <label class="mb-1 block text-xs font-medium text-slate-600">Contract Start Date</label>
          <input v-model="form.contract_start_date" type="date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </div>
        <div v-if="isVisible('contract_end_date')">
          <label class="mb-1 block text-xs font-medium text-slate-600">Contract End Date</label>
          <input v-model="form.contract_end_date" type="date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </div>
        <div v-if="isVisible('probation_end_date')">
          <label class="mb-1 block text-xs font-medium text-slate-600">Probation End Date</label>
          <input v-model="form.probation_end_date" type="date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </div>
        <div v-if="isVisible('resign_date')">
          <label class="mb-1 block text-xs font-medium text-slate-600">Resign Date</label>
          <input v-model="form.resign_date" type="date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </div>
        <div v-if="isVisible('join_date')">
          <label class="mb-1 block text-xs font-medium text-slate-600">Join Date (Rehire)</label>
          <input v-model="form.join_date" type="date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </div>

        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">Reason</label>
          <textarea v-model="reason" rows="2" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></textarea>
        </div>

        <p class="text-xs text-slate-400">
          Field yang tidak diisi TIDAK akan diubah (tetap pakai nilai current employee) — cuma field yang kamu isi di atas yang benar-benar berubah.
        </p>
        <p v-if="errorMessage" class="text-xs text-red-600">{{ errorMessage }}</p>

        <div class="flex justify-end gap-2 pt-2">
          <button type="button" @click="emit('close')" class="rounded-xl px-4 py-2 text-sm text-slate-500 hover:bg-slate-50">Batal</button>
          <button
            type="button"
            :disabled="saving"
            @click="submit"
            class="rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50"
          >
            {{ saving ? 'Mengajukan...' : 'Submit' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
