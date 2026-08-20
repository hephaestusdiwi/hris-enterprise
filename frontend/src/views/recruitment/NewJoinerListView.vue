<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import apiClient from '@/lib/axios'
import BaseModal from '@/components/ui/BaseModal.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import { Search } from 'lucide-vue-next'

interface CandidateInfo {
  id: number
  full_name: string
  email: string
  phone: string
}

interface RefOption { id: number; name: string }
interface EmployeeOption { id: number; first_name: string; last_name: string | null }

interface NewJoinerRow {
  id: number
  status: string
  sent_at: string
  expires_at: string | null
  submitted_at: string | null
  ready_for_employee_at: string | null
  employee_id: number | null
  candidate: CandidateInfo | null
}

const loading = ref(true)
const errorMessage = ref('')
const actionError = ref('')
const newJoiners = ref<NewJoinerRow[]>([])
const currentPage = ref(1)
const lastPage = ref(1)
const authStore = useAuthStore()

const search = ref('')
const statusFilter = ref('')

function employeeName(e: EmployeeOption | null): string {
  if (!e) return '-'
  return [e.first_name, e.last_name].filter(Boolean).join(' ')
}

// Label tampilan turunan dari kombinasi status + ready_for_employee_at + employee_id
// (BUKAN status baru di backend — cuma cara nampilin 4 kondisi nyata jadi 1 badge)
function displayStatus(row: NewJoinerRow): { label: string; class: string } {
  if (row.employee_id) return { label: 'Employee Created', class: 'bg-emerald-50 text-emerald-600' }
  if (row.ready_for_employee_at) return { label: 'Ready for Employee', class: 'bg-teal-50 text-teal-600' }
  if (row.status === 'submitted') return { label: 'Submitted', class: 'bg-violet-50 text-violet-600' }
  return { label: 'Sent', class: 'bg-sky-50 text-sky-600' }
}

async function loadNewJoiners(page = 1) {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/new-joiners', {
      params: { page, status: statusFilter.value || undefined },
    })
    newJoiners.value = response.data.data.data
    currentPage.value = response.data.data.current_page
    lastPage.value = response.data.data.last_page
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Gagal memuat daftar New Joiner.'
  } finally {
    loading.value = false
  }
}

// search NAMA belum didukung query param backend (index() cuma terima candidate_id/status) —
// jadi ini filter client-side di halaman yang sedang di-load, bukan server-side
const filteredNewJoiners = computed(() => {
  if (!search.value) return newJoiners.value
  const q = search.value.toLowerCase()
  return newJoiners.value.filter((n) => n.candidate?.full_name?.toLowerCase().includes(q))
})

function goToPage(page: number) {
  if (page < 1 || page > lastPage.value) return
  loadNewJoiners(page)
}

// ---- Proceed as Employee ----
async function proceedAsEmployee(row: NewJoinerRow) {
  actionError.value = ''
  try {
    await apiClient.post(`/api/new-joiners/${row.id}/proceed-as-employee`)
    await loadNewJoiners(currentPage.value)
  } catch (err: any) {
    actionError.value = err.response?.data?.message || 'Gagal memproses "Proceed as Employee".'
  }
}

// ---- Convert to Employee ----
const showConvertModal = ref(false)
const convertingRow = ref<NewJoinerRow | null>(null)
const convertSaving = ref(false)
const convertForm = ref({
  job_level_id: '' as number | '',
  working_schedule_id: '' as number | '',
  employment_status_id: '' as number | '',
  manager_employee_id: '' as number | '',
})

const jobLevels = ref<RefOption[]>([])
const workingSchedules = ref<RefOption[]>([])
const employmentStatuses = ref<RefOption[]>([])
const employees = ref<EmployeeOption[]>([])
const referenceLoaded = ref(false)

async function loadConvertReferenceData() {
  if (referenceLoaded.value) return
  try {
    const [jlRes, wsRes, esRes, empRes] = await Promise.all([
      apiClient.get('/api/job-levels'),
      apiClient.get('/api/working-schedules'),
      apiClient.get('/api/employment-statuses'),
      apiClient.get('/api/employees'),
    ])
    jobLevels.value = jlRes.data.data.data ?? jlRes.data.data
    workingSchedules.value = wsRes.data.data.data ?? wsRes.data.data
    employmentStatuses.value = esRes.data.data.data ?? esRes.data.data
    employees.value = empRes.data.data.data ?? empRes.data.data
    referenceLoaded.value = true
  } catch (err: any) {
    actionError.value = 'Gagal memuat data referensi untuk konversi Employee.'
  }
}

async function openConvertModal(row: NewJoinerRow) {
  convertingRow.value = row
  convertForm.value = { job_level_id: '', working_schedule_id: '', employment_status_id: '', manager_employee_id: '' }
  showConvertModal.value = true
  await loadConvertReferenceData()
}

async function submitConvert() {
  if (!convertingRow.value) return
  convertSaving.value = true
  actionError.value = ''
  try {
    await apiClient.post(`/api/new-joiners/${convertingRow.value.id}/convert-to-employee`, {
      job_level_id: convertForm.value.job_level_id || null,
      working_schedule_id: convertForm.value.working_schedule_id || null,
      employment_status_id: convertForm.value.employment_status_id || null,
      manager_employee_id: convertForm.value.manager_employee_id || null,
    })
    showConvertModal.value = false
    await loadNewJoiners(currentPage.value)
  } catch (err: any) {
    actionError.value = err.response?.data?.message || 'Gagal mengonversi New Joiner menjadi Employee.'
  } finally {
    convertSaving.value = false
  }
}

onMounted(() => loadNewJoiners())
</script>

<template>
  <div class="space-y-4">
    <div>
      <h1 class="text-lg font-semibold text-slate-800">New Joiner</h1>
      <p class="text-sm text-slate-400">Kandidat yang sudah Hired, sedang/selesai isi data, sampai diproses jadi Employee.</p>
    </div>

    <div v-if="actionError" class="rounded-xl bg-red-50 p-3 text-sm text-red-600">{{ actionError }}</div>

    <div class="flex flex-wrap items-center gap-3">
      <div class="relative flex-1 min-w-[220px] max-w-xs">
        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-300" />
        <input v-model="search" type="text" placeholder="Cari nama kandidat..." class="w-full rounded-xl border border-slate-200 py-2 pl-9 pr-3 text-sm" />
      </div>
      <select v-model="statusFilter" class="rounded-xl border border-slate-200 py-2 px-3 text-sm" @change="loadNewJoiners(1)">
        <option value="">Semua Status</option>
        <option value="sent">Sent</option>
        <option value="submitted">Submitted</option>
      </select>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>
    <EmptyState v-else-if="filteredNewJoiners.length === 0" title="Belum ada New Joiner" />
    <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-100 text-xs uppercase text-slate-400">
          <tr>
            <th class="px-4 py-3">Kandidat</th>
            <th class="px-4 py-3">Sent / Expires</th>
            <th class="px-4 py-3">Submitted</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="n in filteredNewJoiners" :key="n.id" class="border-b border-slate-50">
            <td class="px-4 py-3">
              <div class="font-medium text-slate-700">{{ n.candidate?.full_name || '-' }}</div>
              <div class="text-xs text-slate-400">{{ n.candidate?.email }}</div>
            </td>
            <td class="px-4 py-3 text-slate-500">
              <div>{{ n.sent_at?.slice(0, 10) }}</div>
              <div v-if="n.expires_at" class="text-xs text-slate-400">exp: {{ n.expires_at.slice(0, 10) }}</div>
            </td>
            <td class="px-4 py-3 text-slate-500">{{ n.submitted_at?.slice(0, 10) || '-' }}</td>
            <td class="px-4 py-3">
              <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="displayStatus(n).class">{{ displayStatus(n).label }}</span>
            </td>
            <td class="px-4 py-3 text-right">
              <button
                v-if="n.status === 'submitted' && !n.ready_for_employee_at && authStore.permissions.includes('proceed as employee')"
                class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs hover:bg-slate-50"
                @click="proceedAsEmployee(n)"
                >
                Proceed as Employee
                </button>
                <button
                v-else-if="n.ready_for_employee_at && !n.employee_id && authStore.permissions.includes('proceed as employee')"
                class="rounded-lg bg-primary px-3 py-1.5 text-xs font-medium text-white"
                @click="openConvertModal(n)"
                >
                Convert to Employee
              </button>
              <span v-else-if="n.employee_id" class="text-xs text-emerald-600">Employee #{{ n.employee_id }}</span>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="lastPage > 1" class="flex items-center justify-between border-t border-slate-100 px-4 py-3">
        <span class="text-xs text-slate-400">Halaman {{ currentPage }} dari {{ lastPage }}</span>
        <div class="flex gap-2">
          <button :disabled="currentPage === 1" class="rounded-lg border border-slate-200 px-2 py-1 text-xs disabled:opacity-40" @click="goToPage(currentPage - 1)">Prev</button>
          <button :disabled="currentPage === lastPage" class="rounded-lg border border-slate-200 px-2 py-1 text-xs disabled:opacity-40" @click="goToPage(currentPage + 1)">Next</button>
        </div>
      </div>
    </div>

    <Teleport to="body">
      <BaseModal v-if="showConvertModal" title="Convert to Employee" @close="showConvertModal = false">
        <form class="space-y-3" @submit.prevent="submitConvert">
          <p class="text-xs text-slate-400">
            Data personal/kontak/bank sudah diambil dari submission New Joiner. Field di bawah ini murni penempatan organisasi yang belum ada sumbernya dari Recruitment — isi manual.
          </p>
          <div>
            <label class="text-xs font-medium text-slate-500">Job Level (opsional)</label>
            <select v-model="convertForm.job_level_id" class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm">
              <option value="">-</option>
              <option v-for="jl in jobLevels" :key="jl.id" :value="jl.id">{{ jl.name }}</option>
            </select>
          </div>
          <div>
            <label class="text-xs font-medium text-slate-500">Working Schedule (opsional)</label>
            <select v-model="convertForm.working_schedule_id" class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm">
              <option value="">-</option>
              <option v-for="ws in workingSchedules" :key="ws.id" :value="ws.id">{{ ws.name }}</option>
            </select>
          </div>
          <div>
            <label class="text-xs font-medium text-slate-500">Employment Status (opsional)</label>
            <select v-model="convertForm.employment_status_id" class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm">
              <option value="">-</option>
              <option v-for="es in employmentStatuses" :key="es.id" :value="es.id">{{ es.name }}</option>
            </select>
          </div>
          <div>
            <label class="text-xs font-medium text-slate-500">Manager (opsional)</label>
            <select v-model="convertForm.manager_employee_id" class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm">
              <option value="">-</option>
              <option v-for="e in employees" :key="e.id" :value="e.id">{{ employeeName(e) }}</option>
            </select>
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" class="rounded-xl border border-slate-200 px-4 py-2 text-sm" @click="showConvertModal = false">Batal</button>
            <button type="submit" :disabled="convertSaving" class="rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white disabled:opacity-50">
              {{ convertSaving ? 'Memproses...' : 'Convert' }}
            </button>
          </div>
        </form>
      </BaseModal>
    </Teleport>
  </div>
</template>