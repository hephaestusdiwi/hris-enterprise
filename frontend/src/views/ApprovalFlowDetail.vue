<script setup lang="ts">
import { ref, onMounted, reactive, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft, Plus, Pencil, Trash2, X, User, Users } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface Employee {
  id: number
  first_name: string
  last_name: string
}

interface Role {
  id: number
  name: string
}

type ApproverType = 'direct_manager' | 'specific_employee' | 'specific_role'

interface ApprovalStep {
  id: number
  approval_flow_id: number
  sequence: number
  name: string | null
  approver_type: ApproverType
  approver_employee_id: number | null
  approver_role_id: number | null
  is_active: boolean
  approver_employee: Employee | null
  approver_role: Role | null
}

interface ApprovalFlowAssignment {
  id: number
  approval_flow_id: number
  employee_id: number
  is_active: boolean
  employee: Employee
}

interface ApprovalFlowDetailData {
  id: number
  name: string
  code: string
  description: string | null
  is_active: boolean
  company: { id: number; name: string }
  branch: { id: number; name: string } | null
  department: { id: number; name: string } | null
  steps: ApprovalStep[]
  assignments: ApprovalFlowAssignment[]
}

const props = defineProps<{ id?: string | number }>()
const route = useRoute()
const router = useRouter()

const flowId = computed(() => Number(props.id ?? route.params.id))

const flow = ref<ApprovalFlowDetailData | null>(null)
const employees = ref<Employee[]>([])
const roles = ref<Role[]>([])
const loading = ref(true)
const errorMessage = ref('')

const approverTypeLabels: Record<ApproverType, string> = {
  direct_manager: 'Direct Manager',
  specific_employee: 'Specific Employee',
  specific_role: 'Specific Role',
}

const approverTypeBadgeClass: Record<ApproverType, string> = {
  direct_manager: 'bg-blue-50 text-blue-600',
  specific_employee: 'bg-primary-soft text-primary-dark',
  specific_role: 'bg-violet-50 text-violet-600',
}

function employeeName(employee: Employee): string {
  return `${employee.first_name} ${employee.last_name}`.trim()
}

async function loadFlow() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get(`/api/approval-flows/${flowId.value}`)
    flow.value = response.data.data
  } catch {
    errorMessage.value = 'Gagal memuat detail approval flow.'
  } finally {
    loading.value = false
  }
}

async function loadReferenceData() {
  const [employeeRes, roleRes] = await Promise.all([
    apiClient.get('/api/employees', { params: { per_page: 100 } }),
    apiClient.get('/api/approval-flows/roles'),
  ])
  employees.value = employeeRes.data.data.data
  roles.value = roleRes.data.data
}

// ---------- STEP MODAL ----------
const showStepModal = ref(false)
const isEditingStep = ref(false)
const savingStep = ref(false)
const stepFormError = ref('')

const stepForm = reactive({
  id: 0,
  sequence: 1,
  name: '',
  approver_type: 'direct_manager' as ApproverType,
  approver_employee_id: null as number | null,
  approver_role_id: null as number | null,
  is_active: true,
})

function resetStepForm() {
  stepForm.id = 0
  stepForm.sequence = (flow.value?.steps.length ?? 0) + 1
  stepForm.name = ''
  stepForm.approver_type = 'direct_manager'
  stepForm.approver_employee_id = null
  stepForm.approver_role_id = null
  stepForm.is_active = true
}

function openCreateStepModal() {
  isEditingStep.value = false
  stepFormError.value = ''
  resetStepForm()
  showStepModal.value = true
}

function openEditStepModal(step: ApprovalStep) {
  isEditingStep.value = true
  stepFormError.value = ''
  stepForm.id = step.id
  stepForm.sequence = step.sequence
  stepForm.name = step.name ?? ''
  stepForm.approver_type = step.approver_type
  stepForm.approver_employee_id = step.approver_employee_id
  stepForm.approver_role_id = step.approver_role_id
  stepForm.is_active = step.is_active
  showStepModal.value = true
}

function closeStepModal() {
  showStepModal.value = false
}

async function handleStepSubmit() {
  stepFormError.value = ''
  savingStep.value = true

  const payload = {
    sequence: stepForm.sequence,
    name: stepForm.name || null,
    approver_type: stepForm.approver_type,
    approver_employee_id: stepForm.approver_type === 'specific_employee' ? stepForm.approver_employee_id : null,
    approver_role_id: stepForm.approver_type === 'specific_role' ? stepForm.approver_role_id : null,
    is_active: stepForm.is_active,
  }

  try {
    if (isEditingStep.value) {
      await apiClient.put(`/api/approval-flows/${flowId.value}/steps/${stepForm.id}`, payload)
    } else {
      await apiClient.post(`/api/approval-flows/${flowId.value}/steps`, payload)
    }

    showStepModal.value = false
    await loadFlow()
  } catch (err: any) {
    stepFormError.value = err.response?.data?.message || 'Terjadi kesalahan, silakan coba lagi.'
  } finally {
    savingStep.value = false
  }
}

async function handleStepDelete(step: ApprovalStep) {
  if (!confirm(`Hapus step "${step.name || 'Step ' + step.sequence}"?`)) return

  try {
    await apiClient.delete(`/api/approval-flows/${flowId.value}/steps/${step.id}`)
    await loadFlow()
  } catch {
    alert('Gagal menghapus step.')
  }
}

// ---------- ASSIGNMENT MODAL ----------
const showAssignmentModal = ref(false)
const savingAssignment = ref(false)
const assignmentFormError = ref('')

const assignmentForm = reactive({
  employee_id: null as number | null,
  is_active: true,
})

const unassignedEmployees = computed(() =>
  employees.value.filter((e) => !flow.value?.assignments.some((a) => a.employee_id === e.id)),
)

function openAssignmentModal() {
  assignmentFormError.value = ''
  assignmentForm.employee_id = unassignedEmployees.value[0]?.id ?? null
  assignmentForm.is_active = true
  showAssignmentModal.value = true
}

function closeAssignmentModal() {
  showAssignmentModal.value = false
}

async function handleAssignmentSubmit() {
  if (!assignmentForm.employee_id) {
    assignmentFormError.value = 'Pilih employee terlebih dahulu.'
    return
  }

  assignmentFormError.value = ''
  savingAssignment.value = true

  try {
    await apiClient.post(`/api/approval-flows/${flowId.value}/assignments`, {
      employee_id: assignmentForm.employee_id,
      is_active: assignmentForm.is_active,
    })

    showAssignmentModal.value = false
    await loadFlow()
  } catch (err: any) {
    assignmentFormError.value = err.response?.data?.message || 'Terjadi kesalahan, silakan coba lagi.'
  } finally {
    savingAssignment.value = false
  }
}

async function handleUnassign(assignment: ApprovalFlowAssignment) {
  if (!confirm(`Hapus assignment untuk "${employeeName(assignment.employee)}"?`)) return

  try {
    await apiClient.delete(`/api/approval-flow-assignments/${assignment.id}`)
    await loadFlow()
  } catch {
    alert('Gagal menghapus assignment.')
  }
}

onMounted(() => {
  loadFlow()
  loadReferenceData()
})
</script>

<template>
  <div class="space-y-6">
    <button
      @click="router.push('/approval-flows')"
      class="flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-700"
    >
      <ArrowLeft class="h-4 w-4" :stroke-width="1.75" />
      Kembali ke Approval Flow
    </button>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>

    <template v-else-if="flow">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">{{ flow.name }}</h1>
        <p class="mt-1 text-sm text-slate-500">
          {{ flow.code }} · {{ flow.company.name }}
          <span v-if="flow.branch"> / {{ flow.branch.name }}</span>
          <span v-if="flow.department"> / {{ flow.department.name }}</span>
        </p>
      </div>

      <!-- STEPS -->
      <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-slate-800">Approval Steps</h2>
          <button
            @click="openCreateStepModal"
            class="flex items-center gap-1.5 rounded-lg bg-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-primary-dark"
          >
            <Plus class="h-3.5 w-3.5" :stroke-width="2" />
            Tambah Step
          </button>
        </div>

        <p v-if="flow.steps.length === 0" class="text-sm text-slate-400">Belum ada step. Tambahkan minimal 1 step.</p>

        <div v-else class="space-y-2">
          <div
            v-for="step in flow.steps"
            :key="step.id"
            class="flex items-center justify-between rounded-xl border border-slate-100 px-4 py-3"
          >
            <div class="flex items-center gap-3">
              <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-slate-100 text-xs font-semibold text-slate-600">
                {{ step.sequence }}
              </span>
              <div>
                <p class="text-sm font-medium text-slate-800">{{ step.name || `Step ${step.sequence}` }}</p>
                <div class="mt-0.5 flex items-center gap-2">
                  <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="approverTypeBadgeClass[step.approver_type]">
                    {{ approverTypeLabels[step.approver_type] }}
                  </span>
                  <span v-if="step.approver_employee" class="text-xs text-slate-400">{{ employeeName(step.approver_employee) }}</span>
                  <span v-if="step.approver_role" class="text-xs text-slate-400">{{ step.approver_role.name }}</span>
                  <span v-if="!step.is_active" class="text-xs text-slate-400">(nonaktif)</span>
                </div>
              </div>
            </div>
            <div class="flex items-center gap-1">
              <button @click="openEditStepModal(step)" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                <Pencil class="h-4 w-4" :stroke-width="1.75" />
              </button>
              <button @click="handleStepDelete(step)" class="rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-500">
                <Trash2 class="h-4 w-4" :stroke-width="1.75" />
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- ASSIGNMENTS -->
      <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-slate-800">Employee Assignment</h2>
          <button
            @click="openAssignmentModal"
            class="flex items-center gap-1.5 rounded-lg bg-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-primary-dark"
          >
            <Plus class="h-3.5 w-3.5" :stroke-width="2" />
            Assign Employee
          </button>
        </div>

        <p v-if="flow.assignments.length === 0" class="text-sm text-slate-400">Belum ada employee yang di-assign.</p>

        <div v-else class="space-y-2">
          <div
            v-for="assignment in flow.assignments"
            :key="assignment.id"
            class="flex items-center justify-between rounded-xl border border-slate-100 px-4 py-3"
          >
            <div class="flex items-center gap-2.5">
              <User class="h-4 w-4 text-slate-400" :stroke-width="1.75" />
              <p class="text-sm font-medium text-slate-700">{{ employeeName(assignment.employee) }}</p>
              <span v-if="!assignment.is_active" class="text-xs text-slate-400">(nonaktif)</span>
            </div>
            <button @click="handleUnassign(assignment)" class="rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-500">
              <Trash2 class="h-4 w-4" :stroke-width="1.75" />
            </button>
          </div>
        </div>
      </div>
    </template>

    <!-- STEP MODAL -->
    <Teleport to="body">
      <div v-if="showStepModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8">
        <div class="flex max-h-full w-full max-w-lg flex-col rounded-2xl bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">{{ isEditingStep ? 'Edit Step' : 'Tambah Step' }}</h2>
            <button @click="closeStepModal" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50">
              <X class="h-5 w-5" />
            </button>
          </div>

          <form @submit.prevent="handleStepSubmit" class="flex-1 space-y-4 overflow-y-auto px-6 py-5">
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Urutan</label>
                <input
                  v-model.number="stepForm.sequence"
                  type="number"
                  min="1"
                  required
                  class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
                />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Nama Step (opsional)</label>
                <input
                  v-model="stepForm.name"
                  type="text"
                  placeholder="Approval Atasan"
                  class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
                />
              </div>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Tipe Approver</label>
              <select
                v-model="stepForm.approver_type"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
              >
                <option value="direct_manager">Direct Manager</option>
                <option value="specific_employee">Specific Employee</option>
                <option value="specific_role">Specific Role</option>
              </select>
            </div>

            <div v-if="stepForm.approver_type === 'specific_employee'">
              <label class="mb-1 block text-sm font-medium text-slate-700">Employee</label>
              <select
                v-model.number="stepForm.approver_employee_id"
                required
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
              >
                <option :value="null" disabled>Pilih employee</option>
                <option v-for="e in employees" :key="e.id" :value="e.id">{{ employeeName(e) }}</option>
              </select>
            </div>

            <div v-if="stepForm.approver_type === 'specific_role'">
              <label class="mb-1 block text-sm font-medium text-slate-700">Role</label>
              <select
                v-model.number="stepForm.approver_role_id"
                required
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
              >
                <option :value="null" disabled>Pilih role</option>
                <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.name }}</option>
              </select>
            </div>

            <label class="flex cursor-pointer items-center justify-between rounded-xl border border-slate-200 px-4 py-3">
              <p class="text-sm font-medium text-slate-700">Aktif</p>
              <input v-model="stepForm.is_active" type="checkbox" class="peer sr-only" />
              <div class="relative h-6 w-11 shrink-0 rounded-full bg-slate-200 transition-colors after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow-sm after:transition-transform peer-checked:bg-primary peer-checked:after:translate-x-5"></div>
            </label>

            <p v-if="stepFormError" class="text-sm text-red-600">{{ stepFormError }}</p>
          </form>

          <div class="border-t border-slate-100 px-6 py-4">
            <button
              @click="handleStepSubmit"
              :disabled="savingStep"
              class="w-full rounded-xl bg-primary py-2.5 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50"
            >
              {{ savingStep ? 'Menyimpan...' : 'Simpan' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ASSIGNMENT MODAL -->
    <Teleport to="body">
      <div v-if="showAssignmentModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8">
        <div class="flex max-h-full w-full max-w-md flex-col rounded-2xl bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Assign Employee</h2>
            <button @click="closeAssignmentModal" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50">
              <X class="h-5 w-5" />
            </button>
          </div>

          <form @submit.prevent="handleAssignmentSubmit" class="flex-1 space-y-4 px-6 py-5">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Employee</label>
              <select
                v-model.number="assignmentForm.employee_id"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
              >
                <option :value="null" disabled>Pilih employee</option>
                <option v-for="e in unassignedEmployees" :key="e.id" :value="e.id">{{ employeeName(e) }}</option>
              </select>
              <p v-if="unassignedEmployees.length === 0" class="mt-1 text-xs text-slate-400">
                Semua employee sudah di-assign ke flow ini atau flow lain.
              </p>
            </div>

            <label class="flex cursor-pointer items-center justify-between rounded-xl border border-slate-200 px-4 py-3">
              <p class="text-sm font-medium text-slate-700">Aktif</p>
              <input v-model="assignmentForm.is_active" type="checkbox" class="peer sr-only" />
              <div class="relative h-6 w-11 shrink-0 rounded-full bg-slate-200 transition-colors after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow-sm after:transition-transform peer-checked:bg-primary peer-checked:after:translate-x-5"></div>
            </label>

            <p v-if="assignmentFormError" class="text-sm text-red-600">{{ assignmentFormError }}</p>
          </form>

          <div class="border-t border-slate-100 px-6 py-4">
            <button
              @click="handleAssignmentSubmit"
              :disabled="savingAssignment"
              class="w-full rounded-xl bg-primary py-2.5 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50"
            >
              {{ savingAssignment ? 'Menyimpan...' : 'Simpan' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>