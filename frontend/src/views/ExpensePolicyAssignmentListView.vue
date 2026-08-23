<script setup lang="ts">
import { ref, onMounted, reactive, computed } from 'vue'
import { Plus, Pencil, X } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface Employee { id: number; first_name: string; last_name: string | null; company_id: number }
interface Policy { id: number; name: string; company_id: number }

interface Assignment {
  id: number
  employee_id: number
  expense_policy_id: number
  effective_date: string
  expiration_date: string | null
  is_active: boolean
  employee: Employee
  policy: Policy
}

function employeeName(e: Employee) {
  return [e.first_name, e.last_name].filter(Boolean).join(' ')
}
function formatDate(value: string) {
  return new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

const assignments = ref<Assignment[]>([])
const employees = ref<Employee[]>([])
const policies = ref<Policy[]>([])
const loading = ref(true)
const errorMessage = ref('')

const filterEmployeeId = ref<number | null>(null)

async function loadAssignments() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/expense-policy-assignments', {
      params: filterEmployeeId.value ? { employee_id: filterEmployeeId.value } : {},
    })
    assignments.value = response.data.data
  } catch {
    errorMessage.value = 'Gagal memuat daftar Expense Policy Assignment.'
  } finally {
    loading.value = false
  }
}

async function loadReferenceData() {
  const [employeeRes, policyRes] = await Promise.all([
    apiClient.get('/api/employees', { params: { per_page: 200 } }),
    apiClient.get('/api/expense-policies'),
  ])
  employees.value = employeeRes.data.data.data ?? employeeRes.data.data
  policies.value = policyRes.data.data
}

// ---------- CREATE ----------
const showCreateModal = ref(false)
const saving = ref(false)
const formError = ref('')
const form = reactive({
  employee_id: null as number | null,
  expense_policy_id: null as number | null,
  effective_date: new Date().toISOString().slice(0, 10),
  expiration_date: '' as string,
  is_active: true,
})

const policiesForSelectedEmployee = computed(() => {
  const employee = employees.value.find((e) => e.id === form.employee_id)
  if (!employee) return policies.value
  return policies.value.filter((p) => p.company_id === employee.company_id)
})

function openCreateModal() {
  formError.value = ''
  form.employee_id = null
  form.expense_policy_id = null
  form.effective_date = new Date().toISOString().slice(0, 10)
  form.expiration_date = ''
  form.is_active = true
  showCreateModal.value = true
}

async function handleCreate() {
  if (!form.employee_id || !form.expense_policy_id) {
    formError.value = 'Employee dan Policy wajib dipilih.'
    return
  }
  saving.value = true
  formError.value = ''
  try {
    await apiClient.post('/api/expense-policy-assignments', {
      employee_id: form.employee_id,
      expense_policy_id: form.expense_policy_id,
      effective_date: form.effective_date,
      expiration_date: form.expiration_date || null,
      is_active: form.is_active,
    })
    showCreateModal.value = false
    await loadAssignments()
  } catch (err: any) {
    formError.value = err.response?.data?.message || 'Gagal membuat assignment. Cek kembali tanggal & company employee/policy.'
  } finally {
    saving.value = false
  }
}

// ---------- EDIT (cuma is_active + expiration_date, effective_date immutable) ----------
const showEditModal = ref(false)
const editingAssignment = ref<Assignment | null>(null)
const editForm = reactive({ is_active: true, expiration_date: '' as string })

function openEditModal(row: Assignment) {
  editingAssignment.value = row
  editForm.is_active = row.is_active
  editForm.expiration_date = row.expiration_date ?? ''
  formError.value = ''
  showEditModal.value = true
}

async function handleUpdate() {
  if (!editingAssignment.value) return
  saving.value = true
  formError.value = ''
  try {
    await apiClient.put(`/api/expense-policy-assignments/${editingAssignment.value.id}`, {
      is_active: editForm.is_active,
      expiration_date: editForm.expiration_date || null,
    })
    showEditModal.value = false
    await loadAssignments()
  } catch (err: any) {
    formError.value = err.response?.data?.message || 'Gagal memperbarui assignment.'
  } finally {
    saving.value = false
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
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Expense Policy Assignment</h1>
        <p class="mt-1 text-sm text-slate-500">Tentukan Expense Policy mana yang berlaku untuk setiap employee.</p>
      </div>
      <button
        @click="openCreateModal"
        :disabled="employees.length === 0 || policies.length === 0"
        class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50"
      >
        <Plus class="h-4 w-4" :stroke-width="2" /> Assign Policy
      </button>
    </div>

    <div class="flex items-center gap-2">
      <select
        v-model.number="filterEmployeeId"
        @change="loadAssignments"
        class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
      >
        <option :value="null">Semua employee</option>
        <option v-for="e in employees" :key="e.id" :value="e.id">{{ employeeName(e) }}</option>
      </select>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>
    <div v-else-if="assignments.length === 0" class="rounded-xl bg-slate-50 p-4 text-sm text-slate-400">
      Belum ada Expense Policy Assignment.
    </div>

    <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <table class="w-full text-left text-sm">
        <thead>
          <tr class="border-b border-slate-100 bg-slate-50/60">
            <th class="px-5 py-3 font-medium text-slate-500">Employee</th>
            <th class="px-5 py-3 font-medium text-slate-500">Policy</th>
            <th class="px-5 py-3 font-medium text-slate-500">Efektif</th>
            <th class="px-5 py-3 font-medium text-slate-500">Kedaluwarsa</th>
            <th class="px-5 py-3 font-medium text-slate-500">Status</th>
            <th class="px-5 py-3 text-right font-medium text-slate-500">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in assignments" :key="row.id" class="border-b border-slate-50 last:border-0 hover:bg-slate-50/50">
            <td class="px-5 py-3.5 font-medium text-slate-800">{{ employeeName(row.employee) }}</td>
            <td class="px-5 py-3.5 text-slate-600">{{ row.policy.name }}</td>
            <td class="px-5 py-3.5 text-slate-500">{{ formatDate(row.effective_date) }}</td>
            <td class="px-5 py-3.5 text-slate-500">{{ row.expiration_date ? formatDate(row.expiration_date) : '-' }}</td>
            <td class="px-5 py-3.5">
              <span
                class="rounded-full px-2.5 py-1 text-xs font-medium"
                :class="row.is_active ? 'bg-primary-soft text-primary-dark' : 'bg-slate-50 text-slate-400'"
              >
                {{ row.is_active ? 'Aktif' : 'Nonaktif' }}
              </span>
            </td>
            <td class="px-5 py-3.5">
              <div class="flex items-center justify-end">
                <button @click="openEditModal(row)" class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600">
                  <Pencil class="h-4 w-4" :stroke-width="1.75" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create modal -->
    <Teleport to="body">
      <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8">
        <div class="flex max-h-full w-full max-w-lg flex-col rounded-2xl bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Assign Policy</h2>
            <button @click="showCreateModal = false" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50"><X class="h-5 w-5" /></button>
          </div>
          <form @submit.prevent="handleCreate" class="flex-1 space-y-4 overflow-y-auto px-6 py-5">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Employee</label>
              <select v-model.number="form.employee_id" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                <option :value="null" disabled>Pilih employee</option>
                <option v-for="e in employees" :key="e.id" :value="e.id">{{ employeeName(e) }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Expense Policy</label>
              <select v-model.number="form.expense_policy_id" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                <option :value="null" disabled>Pilih policy</option>
                <option v-for="p in policiesForSelectedEmployee" :key="p.id" :value="p.id">{{ p.name }}</option>
              </select>
              <p v-if="form.employee_id && policiesForSelectedEmployee.length === 0" class="mt-1 text-xs text-slate-400">
                Belum ada Expense Policy untuk company employee ini.
              </p>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Effective Date</label>
                <input v-model="form.effective_date" type="date" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Expiration Date</label>
                <input v-model="form.expiration_date" type="date" placeholder="opsional" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
            </div>
            <label class="flex cursor-pointer items-center justify-between rounded-xl border border-slate-200 px-4 py-3">
              <p class="text-sm font-medium text-slate-700">Aktif</p>
              <input v-model="form.is_active" type="checkbox" class="peer sr-only" />
              <div class="relative h-6 w-11 shrink-0 rounded-full bg-slate-200 transition-colors after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow-sm after:transition-transform peer-checked:bg-primary peer-checked:after:translate-x-5"></div>
            </label>
            <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>
          </form>
          <div class="border-t border-slate-100 px-6 py-4">
            <button @click="handleCreate" :disabled="saving" class="w-full rounded-xl bg-primary py-2.5 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50">
              {{ saving ? 'Menyimpan...' : 'Simpan' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Edit modal: is_active + expiration_date saja, effective_date tidak bisa diubah -->
    <Teleport to="body">
      <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8">
        <div class="w-full max-w-md rounded-2xl bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Edit Assignment</h2>
            <button @click="showEditModal = false" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50"><X class="h-5 w-5" /></button>
          </div>
          <div class="space-y-4 px-6 py-5">
            <p class="text-xs text-slate-400">
              Employee, policy, dan effective date tidak bisa diubah di sini -- kalau mau ganti policy, buat assignment baru.
            </p>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Expiration Date</label>
              <input v-model="editForm.expiration_date" type="date" placeholder="opsional" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
            </div>
            <label class="flex cursor-pointer items-center justify-between rounded-xl border border-slate-200 px-4 py-3">
              <p class="text-sm font-medium text-slate-700">Aktif</p>
              <input v-model="editForm.is_active" type="checkbox" class="peer sr-only" />
              <div class="relative h-6 w-11 shrink-0 rounded-full bg-slate-200 transition-colors after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow-sm after:transition-transform peer-checked:bg-primary peer-checked:after:translate-x-5"></div>
            </label>
            <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>
          </div>
          <div class="border-t border-slate-100 px-6 py-4">
            <button @click="handleUpdate" :disabled="saving" class="w-full rounded-xl bg-primary py-2.5 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50">
              {{ saving ? 'Menyimpan...' : 'Simpan' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>