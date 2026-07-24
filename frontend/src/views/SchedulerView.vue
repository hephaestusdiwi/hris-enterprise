<script setup lang="ts">
import { ref, onMounted, reactive, computed, watch } from 'vue'
import { Search, CalendarClock, Loader2, X, Users, ChevronLeft, ChevronRight, ChevronDown } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface Ref { id: number; name: string }

interface EmployeeRow {
  id: number
  employee_number: string
  first_name: string
  last_name: string | null
  company: Ref
  branch: Ref | null
  department: Ref | null
  position: Ref | null
  current_working_schedule: Ref | null
  next_working_schedule: { working_schedule: Ref; effective_date: string } | null
}

function employeeName(row: { first_name: string; last_name: string | null }) {
  return [row.first_name, row.last_name].filter(Boolean).join(' ')
}

function employeeInitials(row: { first_name: string; last_name: string | null }) {
  return `${row.first_name?.[0] ?? ''}${row.last_name?.[0] ?? ''}`.toUpperCase()
}

function formatDate(value: string) {
  return new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

const employees = ref<EmployeeRow[]>([])
const loading = ref(true)
const errorMessage = ref('')
const meta = ref({ current_page: 1, last_page: 1, total: 0 })

const companies = ref<Ref[]>([])
const branches = ref<Ref[]>([])
const departments = ref<Ref[]>([])
const positions = ref<Ref[]>([])
const jobLevels = ref<Ref[]>([])
const employmentStatuses = ref<Ref[]>([])
const workingSchedules = ref<Ref[]>([])

const showFilters = ref(false)

const filters = reactive({
  search: '',
  company_id: null as number | null,
  branch_id: null as number | null,
  department_id: null as number | null,
  position_id: null as number | null,
  job_level_id: null as number | null,
  employment_status_id: null as number | null,
  page: 1,
})

const activeFilterCount = computed(() =>
  [filters.company_id, filters.branch_id, filters.department_id, filters.position_id, filters.job_level_id, filters.employment_status_id].filter(
    (v) => v !== null,
  ).length,
)

const withoutScheduleCount = computed(() => employees.value.filter((e) => !e.current_working_schedule).length)
const withUpcomingChangeCount = computed(() => employees.value.filter((e) => e.next_working_schedule).length)

const selectedIds = ref<Set<number>>(new Set())
const allSelected = computed(() => employees.value.length > 0 && employees.value.every((e) => selectedIds.value.has(e.id)))

function toggleSelectAll() {
  if (allSelected.value) {
    employees.value.forEach((e) => selectedIds.value.delete(e.id))
  } else {
    employees.value.forEach((e) => selectedIds.value.add(e.id))
  }
}

function toggleSelect(id: number) {
  if (selectedIds.value.has(id)) selectedIds.value.delete(id)
  else selectedIds.value.add(id)
}

async function loadEmployees() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/scheduler/employees', {
      params: {
        search: filters.search || undefined,
        company_id: filters.company_id || undefined,
        branch_id: filters.branch_id || undefined,
        department_id: filters.department_id || undefined,
        position_id: filters.position_id || undefined,
        job_level_id: filters.job_level_id || undefined,
        employment_status_id: filters.employment_status_id || undefined,
        page: filters.page,
      },
    })
    employees.value = response.data.data.data
    meta.value = {
      current_page: response.data.data.current_page,
      last_page: response.data.data.last_page,
      total: response.data.data.total,
    }
  } catch {
    errorMessage.value = 'Gagal memuat daftar employee.'
  } finally {
    loading.value = false
  }
}

async function loadReferenceData() {
  const [companyRes, branchRes, departmentRes, positionRes, jobLevelRes, statusRes, scheduleRes] = await Promise.all([
    apiClient.get('/api/companies'),
    apiClient.get('/api/branches'),
    apiClient.get('/api/departments'),
    apiClient.get('/api/positions'),
    apiClient.get('/api/job-levels'),
    apiClient.get('/api/employment-statuses'),
    apiClient.get('/api/working-schedules'),
  ])
  companies.value = companyRes.data.data.data
  branches.value = branchRes.data.data.data
  departments.value = departmentRes.data.data.data
  positions.value = positionRes.data.data.data
  jobLevels.value = jobLevelRes.data.data.data
  employmentStatuses.value = statusRes.data.data.data
  workingSchedules.value = scheduleRes.data.data.data
}

let searchDebounce: ReturnType<typeof setTimeout> | null = null
watch(() => filters.search, () => {
  if (searchDebounce) clearTimeout(searchDebounce)
  searchDebounce = setTimeout(() => {
    filters.page = 1
    loadEmployees()
  }, 400)
})

watch(
  () => [filters.company_id, filters.branch_id, filters.department_id, filters.position_id, filters.job_level_id, filters.employment_status_id],
  () => {
    filters.page = 1
    loadEmployees()
  },
)

function goToPage(page: number) {
  if (page < 1 || page > meta.value.last_page || page === meta.value.current_page) return
  filters.page = page
  loadEmployees()
}

// Smart pagination range: 1 ... c-1 c c+1 ... last
const paginationItems = computed(() => {
  const total = meta.value.last_page
  const current = meta.value.current_page
  if (total <= 1) return []

  const items: (number | '...')[] = [1]
  const left = Math.max(2, current - 1)
  const right = Math.min(total - 1, current + 1)

  if (left > 2) items.push('...')
  for (let i = left; i <= right; i++) items.push(i)
  if (right < total - 1) items.push('...')
  items.push(total)

  return items
})

// ---------- ASSIGN MODAL ----------
const showAssignModal = ref(false)
const assignTargets = ref<EmployeeRow[]>([])
const saving = ref(false)
const assignError = ref('')

const assignForm = reactive({
  working_schedule_id: null as number | null,
  effective_date: new Date().toISOString().slice(0, 10),
  notes: '',
})

function openAssignSingle(row: EmployeeRow) {
  assignTargets.value = [row]
  assignError.value = ''
  assignForm.working_schedule_id = null
  assignForm.effective_date = new Date().toISOString().slice(0, 10)
  assignForm.notes = ''
  showAssignModal.value = true
}

function openAssignBulk() {
  assignTargets.value = employees.value.filter((e) => selectedIds.value.has(e.id))
  assignError.value = ''
  assignForm.working_schedule_id = null
  assignForm.effective_date = new Date().toISOString().slice(0, 10)
  assignForm.notes = ''
  showAssignModal.value = true
}

function closeAssignModal() {
  showAssignModal.value = false
}

async function submitAssign() {
  if (!assignForm.working_schedule_id) {
    assignError.value = 'Pilih Working Schedule terlebih dahulu.'
    return
  }

  assignError.value = ''
  saving.value = true

  try {
    if (assignTargets.value.length === 1) {
      await apiClient.post('/api/scheduler/assign', {
        employee_id: assignTargets.value[0].id,
        working_schedule_id: assignForm.working_schedule_id,
        effective_date: assignForm.effective_date,
        notes: assignForm.notes || null,
      })
    } else {
      await apiClient.post('/api/scheduler/bulk-assign', {
        employee_ids: assignTargets.value.map((e) => e.id),
        working_schedule_id: assignForm.working_schedule_id,
        effective_date: assignForm.effective_date,
        notes: assignForm.notes || null,
      })
    }

    showAssignModal.value = false
    selectedIds.value.clear()
    await loadEmployees()
  } catch (err: any) {
    assignError.value = err.response?.data?.message || 'Gagal menjadwalkan Working Schedule.'
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  loadEmployees()
  loadReferenceData()
})
</script>

<template>
  <div class="space-y-5">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Scheduler</h1>
      <p class="mt-1 text-sm text-slate-500">Kelola penugasan Working Schedule per employee, satuan maupun massal.</p>
    </div>

    <!-- Filter bar -->
    <div class="rounded-2xl border border-slate-100 bg-white p-4">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <button
          type="button"
          @click="showFilters = !showFilters"
          class="flex items-center gap-1 text-sm font-medium text-primary-dark hover:underline"
        >
          {{ showFilters ? 'Sembunyikan filter' : 'Semua filter' }}
          <span v-if="activeFilterCount > 0" class="rounded-full bg-primary-soft px-1.5 py-0.5 text-[11px] font-semibold text-primary-dark">
            {{ activeFilterCount }}
          </span>
        </button>

        <div class="relative min-w-[240px] flex-1 sm:flex-none">
          <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" :stroke-width="1.75" />
          <input
            v-model="filters.search"
            type="text"
            placeholder="Cari nama atau No. Karyawan"
            class="w-full rounded-xl border border-slate-200 py-2 pl-9 pr-3 text-sm focus:border-primary focus:outline-none sm:w-64"
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
              <option :value="null">Semua</option>
              <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">Branch</label>
            <select v-model="filters.branch_id" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
              <option :value="null">Semua</option>
              <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">Department</label>
            <select v-model="filters.department_id" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
              <option :value="null">Semua</option>
              <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">Position</label>
            <select v-model="filters.position_id" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
              <option :value="null">Semua</option>
              <option v-for="p in positions" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">Job Level</label>
            <select v-model="filters.job_level_id" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
              <option :value="null">Semua</option>
              <option v-for="jl in jobLevels" :key="jl.id" :value="jl.id">{{ jl.name }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">Employment Status</label>
            <select v-model="filters.employment_status_id" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
              <option :value="null">Semua</option>
              <option v-for="s in employmentStatuses" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>
        </div>
      </Transition>
    </div>

    <!-- Summary stat strip -->
    <div v-if="!loading && !errorMessage" class="flex flex-wrap divide-x divide-slate-100 overflow-hidden rounded-2xl border border-slate-100 bg-white">
      <div class="min-w-[130px] flex-1 px-5 py-4">
        <p class="text-xl font-semibold tracking-tight text-slate-900">{{ meta.total }}</p>
        <p class="mt-0.5 text-xs text-slate-500">Total Employee</p>
      </div>
      <div class="min-w-[150px] flex-1 px-5 py-4">
        <p class="text-xl font-semibold tracking-tight" :class="withoutScheduleCount > 0 ? 'text-amber-600' : 'text-slate-300'">
          {{ withoutScheduleCount }}
        </p>
        <p class="mt-0.5 text-xs text-slate-500">Belum Ada Schedule (halaman ini)</p>
      </div>
      <div class="min-w-[150px] flex-1 px-5 py-4">
        <p class="text-xl font-semibold tracking-tight" :class="withUpcomingChangeCount > 0 ? 'text-blue-600' : 'text-slate-300'">
          {{ withUpcomingChangeCount }}
        </p>
        <p class="mt-0.5 text-xs text-slate-500">Ada Perubahan Terjadwal</p>
      </div>
    </div>

    <!-- Bulk action bar -->
    <div
      v-if="selectedIds.size > 0"
      class="flex items-center justify-between rounded-2xl border border-primary/30 bg-primary-soft px-4 py-3"
    >
      <p class="text-sm font-medium text-primary-dark">{{ selectedIds.size }} employee dipilih</p>
      <div class="flex items-center gap-2">
        <button type="button" @click="selectedIds.clear()" class="rounded-lg px-3 py-1.5 text-xs font-medium text-primary-dark hover:bg-primary/10">
          Batal Pilih
        </button>
        <button
          type="button"
          @click="openAssignBulk"
          class="flex items-center gap-1.5 rounded-lg bg-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-primary-dark"
        >
          <CalendarClock class="h-3.5 w-3.5" :stroke-width="2" />
          Assign Schedule
        </button>
      </div>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>
    <div v-else-if="employees.length === 0" class="rounded-xl bg-slate-50 p-6 text-center text-sm text-slate-400">
      Tidak ada employee yang cocok dengan filter ini.
    </div>

    <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead>
            <tr class="border-b border-slate-100 bg-slate-50/60">
              <th class="w-10 px-5 py-3">
                <input type="checkbox" :checked="allSelected" @change="toggleSelectAll" class="rounded border-slate-300 text-primary focus:ring-primary" />
              </th>
              <th class="px-3 py-3 font-medium text-slate-500">Employee</th>
              <th class="px-3 py-3 font-medium text-slate-500">Branch</th>
              <th class="px-3 py-3 font-medium text-slate-500">Department</th>
              <th class="px-3 py-3 font-medium text-slate-500">Current Schedule</th>
              <th class="px-3 py-3 font-medium text-slate-500">Next Schedule</th>
              <th class="px-5 py-3 text-right font-medium text-slate-500">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in employees"
              :key="row.id"
              class="border-b border-slate-50 last:border-0 hover:bg-slate-50/50"
            >
              <td class="px-5 py-3.5">
                <input
                  type="checkbox"
                  :checked="selectedIds.has(row.id)"
                  @change="toggleSelect(row.id)"
                  class="rounded border-slate-300 text-primary focus:ring-primary"
                />
              </td>
              <td class="px-3 py-3.5">
                <div class="flex items-center gap-2.5">
                  <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-soft text-xs font-semibold text-primary-dark">
                    {{ employeeInitials(row) }}
                  </div>
                  <div>
                    <p class="font-medium text-slate-800">{{ employeeName(row) }}</p>
                    <p class="text-xs text-slate-400">{{ row.employee_number }}</p>
                  </div>
                </div>
              </td>
              <td class="px-3 py-3.5 text-slate-500">{{ row.branch?.name ?? '-' }}</td>
              <td class="px-3 py-3.5 text-slate-500">{{ row.department?.name ?? '-' }}</td>
              <td class="px-3 py-3.5">
                <span v-if="row.current_working_schedule" class="rounded-full bg-primary-soft px-2.5 py-1 text-xs font-medium text-primary-dark">
                  {{ row.current_working_schedule.name }}
                </span>
                <span v-else class="text-slate-400">Belum ada</span>
              </td>
              <td class="px-3 py-3.5">
                <div v-if="row.next_working_schedule" class="text-xs">
                  <span class="rounded-full bg-blue-50 px-2 py-0.5 font-medium text-blue-600">
                    {{ row.next_working_schedule.working_schedule.name }}
                  </span>
                  <p class="mt-0.5 text-slate-400">mulai {{ formatDate(row.next_working_schedule.effective_date) }}</p>
                </div>
                <span v-else class="text-slate-400">-</span>
              </td>
              <td class="px-5 py-3.5 text-right">
                <button
                  @click="openAssignSingle(row)"
                  class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 transition-colors hover:border-primary/40 hover:text-primary-dark"
                >
                  <CalendarClock class="h-3.5 w-3.5" :stroke-width="1.75" />
                  Assign
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="meta.last_page > 1" class="flex items-center justify-between text-sm text-slate-500">
      <p>Total {{ meta.total }} employee</p>
      <div class="flex items-center gap-1">
        <button
          type="button"
          @click="goToPage(meta.current_page - 1)"
          :disabled="meta.current_page === 1"
          class="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 disabled:opacity-30 disabled:hover:bg-transparent"
        >
          <ChevronLeft class="h-4 w-4" :stroke-width="2" />
        </button>

        <template v-for="(item, i) in paginationItems" :key="i">
          <span v-if="item === '...'" class="px-2 text-xs text-slate-300">...</span>
          <button
            v-else
            type="button"
            @click="goToPage(item)"
            class="min-w-[32px] rounded-lg px-2.5 py-1.5 text-xs font-medium transition-colors"
            :class="item === meta.current_page ? 'bg-primary text-white' : 'text-slate-500 hover:bg-slate-100'"
          >
            {{ item }}
          </button>
        </template>

        <button
          type="button"
          @click="goToPage(meta.current_page + 1)"
          :disabled="meta.current_page === meta.last_page"
          class="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 disabled:opacity-30 disabled:hover:bg-transparent"
        >
          <ChevronRight class="h-4 w-4" :stroke-width="2" />
        </button>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="showAssignModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8">
        <div class="w-full max-w-md rounded-2xl bg-white shadow-xl">
          <div class="border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Assign Working Schedule</h2>
            <p class="mt-1 flex items-center gap-1.5 text-sm text-slate-500">
              <Users class="h-4 w-4" :stroke-width="1.75" />
              {{ assignTargets.length }} employee: {{ assignTargets.map((e) => employeeName(e)).join(', ') }}
            </p>
          </div>

          <div class="space-y-4 px-6 py-5">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Working Schedule</label>
              <select
                v-model.number="assignForm.working_schedule_id"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
              >
                <option :value="null" disabled>Pilih working schedule</option>
                <option v-for="s in workingSchedules" :key="s.id" :value="s.id">{{ s.name }}</option>
              </select>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Effective Date</label>
              <input
                v-model="assignForm.effective_date"
                type="date"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
              />
              <p class="mt-1 text-xs text-slate-400">
                Kalau tanggal hari ini/sudah lewat, langsung berlaku sekarang. Kalau tanggal masa depan, otomatis diterapkan pas tanggalnya tiba.
              </p>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Catatan (opsional)</label>
              <textarea
                v-model="assignForm.notes"
                rows="2"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
              ></textarea>
            </div>

            <p v-if="assignError" class="text-sm text-red-600">{{ assignError }}</p>
          </div>

          <div class="flex gap-3 border-t border-slate-100 px-6 py-4">
            <button @click="closeAssignModal" class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50">
              Batal
            </button>
            <button
              @click="submitAssign"
              :disabled="saving"
              class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-primary py-2.5 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50"
            >
              <Loader2 v-if="saving" class="h-4 w-4 animate-spin" :stroke-width="2" />
              {{ saving ? 'Menyimpan...' : 'Assign' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>