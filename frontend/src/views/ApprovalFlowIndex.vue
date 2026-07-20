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

interface ApprovalFlowRow {
  id: number
  company_id: number
  branch_id: number | null
  department_id: number | null
  name: string
  code: string
  description: string | null
  is_active: boolean
  steps_count: number
  company: Company
  branch: Branch | null
  department: Department | null
}

const router = useRouter()

const flows = ref<ApprovalFlowRow[]>([])
const companies = ref<Company[]>([])
const branches = ref<Branch[]>([])
const departments = ref<Department[]>([])
const loading = ref(true)
const errorMessage = ref('')

const showModal = ref(false)
const isEditing = ref(false)
const saving = ref(false)
const formError = ref('')

const form = reactive({
  id: 0,
  company_id: 0,
  branch_id: null as number | null,
  department_id: null as number | null,
  name: '',
  code: '',
  description: '',
  is_active: true,
})

const filteredBranches = computed(() => branches.value.filter((b) => b.company_id === form.company_id))

const filteredDepartments = computed(() => departments.value.filter((d) => d.company_id === form.company_id))

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
  const [companyRes, branchRes, departmentRes] = await Promise.all([
    apiClient.get('/api/companies'),
    apiClient.get('/api/branches'),
    apiClient.get('/api/departments'),
  ])
  companies.value = companyRes.data.data.data
  branches.value = branchRes.data.data.data
  departments.value = departmentRes.data.data.data
}

function resetForm() {
  form.id = 0
  form.company_id = companies.value[0]?.id ?? 0
  form.branch_id = null
  form.department_id = null
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
  form.branch_id = row.branch_id
  form.department_id = row.department_id
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
    branch_id: form.branch_id,
    department_id: form.department_id,
    name: form.name,
    code: form.code,
    description: form.description || null,
    is_active: form.is_active,
  }

  try {
    if (isEditing.value) {
      await apiClient.put(`/api/approval-flows/${form.id}`, payload)
    } else {
      await apiClient.post('/api/approval-flows', payload)
    }

    showModal.value = false
    await loadFlows()
  } catch (err: any) {
    formError.value = err.response?.data?.message || 'Terjadi kesalahan, silakan coba lagi.'
  } finally {
    saving.value = false
  }
}

async function handleDelete(row: ApprovalFlowRow) {
  if (!confirm(`Hapus approval flow "${row.name}"?`)) return

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

onMounted(() => {
  loadFlows()
  loadReferenceData()
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Approval Flow</h1>
        <p class="mt-1 text-sm text-slate-500">Kelola template alur persetujuan untuk attendance, leave, dan payroll.</p>
      </div>
      <button
        @click="openCreateModal"
        :disabled="companies.length === 0"
        class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-dark disabled:opacity-50"
      >
        <Plus class="h-4 w-4" :stroke-width="2" />
        Tambah Approval Flow
      </button>
    </div>

    <p v-if="companies.length === 0 && !loading" class="rounded-xl bg-amber-50 p-4 text-sm text-amber-700">
      Belum ada company. Tambahkan company terlebih dahulu sebelum membuat approval flow.
    </p>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">
      {{ errorMessage }}
    </div>

    <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <table class="w-full text-left text-sm">
        <thead>
          <tr class="border-b border-slate-100 bg-slate-50/60">
            <th class="px-5 py-3 font-medium text-slate-500">Nama</th>
            <th class="px-5 py-3 font-medium text-slate-500">Company / Branch / Department</th>
            <th class="px-5 py-3 font-medium text-slate-500">Steps</th>
            <th class="px-5 py-3 font-medium text-slate-500">Status</th>
            <th class="px-5 py-3 text-right font-medium text-slate-500">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in flows"
            :key="row.id"
            class="border-b border-slate-50 last:border-0 hover:bg-slate-50/50"
          >
            <td class="px-5 py-3.5">
              <p class="font-medium text-slate-800">{{ row.name }}</p>
              <p class="text-xs text-slate-400">{{ row.code }}</p>
            </td>
            <td class="px-5 py-3.5 text-slate-500">
              {{ row.company.name }}
              <span v-if="row.branch"> / {{ row.branch.name }}</span>
              <span v-if="row.department"> / {{ row.department.name }}</span>
            </td>
            <td class="px-5 py-3.5 text-slate-500">{{ row.steps_count }} step</td>
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
                  @click="goToDetail(row)"
                  class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600"
                  title="Kelola Steps & Assignment"
                >
                  <ListTree class="h-4 w-4" :stroke-width="1.75" />
                </button>
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
        <div class="flex max-h-full w-full max-w-xl flex-col rounded-2xl bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">
              {{ isEditing ? 'Edit Approval Flow' : 'Tambah Approval Flow' }}
            </h2>
            <button @click="closeModal" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50">
              <X class="h-5 w-5" />
            </button>
          </div>

          <form @submit.prevent="handleSubmit" class="flex-1 space-y-5 overflow-y-auto px-6 py-5">
            <div>
              <div class="mb-3 flex items-center gap-2">
                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary-soft text-primary-dark">
                  <Building2 class="h-4 w-4" :stroke-width="1.75" />
                </div>
                <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Scope</h3>
              </div>

              <div class="space-y-3">
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">Company</label>
                  <select
                    v-model.number="form.company_id"
                    required
                    @change="form.branch_id = null; form.department_id = null"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
                  >
                    <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
                  </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Branch</label>
                    <select
                      v-model="form.branch_id"
                      class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
                    >
                      <option :value="null">Semua Branch</option>
                      <option v-for="b in filteredBranches" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                  </div>

                  <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Department</label>
                    <select
                      v-model="form.department_id"
                      class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
                    >
                      <option :value="null">Semua Department</option>
                      <option v-for="d in filteredDepartments" :key="d.id" :value="d.id">{{ d.name }}</option>
                    </select>
                  </div>
                </div>
                <p class="text-xs text-slate-400">
                  Branch dan Department itu scope terpisah (department tidak terikat ke branch tertentu) — kosongkan salah satu/keduanya kalau approval flow ini berlaku lintas branch/department.
                </p>
              </div>
            </div>

            <div class="space-y-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Nama Flow</label>
                <input
                  v-model="form.name"
                  type="text"
                  required
                  placeholder="Approval Cuti Standar"
                  class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
                />
              </div>

              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Kode</label>
                <input
                  v-model="form.code"
                  type="text"
                  required
                  placeholder="LEAVE_STANDARD"
                  class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
                />
              </div>

              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Deskripsi</label>
                <textarea
                  v-model="form.description"
                  rows="2"
                  class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
                ></textarea>
              </div>

              <label class="flex cursor-pointer items-center justify-between rounded-xl border border-slate-200 px-4 py-3">
                <p class="text-sm font-medium text-slate-700">Aktif</p>
                <input v-model="form.is_active" type="checkbox" class="peer sr-only" />
                <div class="relative h-6 w-11 shrink-0 rounded-full bg-slate-200 transition-colors after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow-sm after:transition-transform peer-checked:bg-primary peer-checked:after:translate-x-5"></div>
              </label>
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