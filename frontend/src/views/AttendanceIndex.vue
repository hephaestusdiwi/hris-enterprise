<script setup lang="ts">
import { ref, onMounted, onUnmounted, reactive, watch, computed } from 'vue'
import { Plus, Pencil, Trash2, X, Clock, ChevronDown, Search, Download, SlidersHorizontal, MoreVertical } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface Ref {
  id: number
  name: string
}

interface Employee {
  id: number
  first_name: string
  last_name: string
  employee_number?: string
  branch?: Ref | null
  department?: Ref | null
  position?: Ref | null
  employment_status?: Ref | null
}

interface Shift {
  id: number
  name: string
}

type AttendanceStatus = 'present' | 'late' | 'absent' | 'half_day' | 'leave' | 'sick' | 'alpha'

interface AttendanceRow {
  id: number
  employee_id: number
  attendance_date: string
  shift_id: number | null
  clock_in: string | null
  clock_out: string | null
  status: AttendanceStatus
  notes: string | null
  employee: Employee
  shift: Shift | null
}

const statusLabels: Record<AttendanceStatus, string> = {
  present: 'Present',
  late: 'Late',
  absent: 'Absent',
  half_day: 'Half Day',
  leave: 'Leave',
  sick: 'Sick',
  alpha: 'Alpha',
}

const statusBadgeClass: Record<AttendanceStatus, string> = {
  present: 'bg-primary-soft text-primary-dark',
  late: 'bg-amber-50 text-amber-600',
  absent: 'bg-red-50 text-red-600',
  half_day: 'bg-blue-50 text-blue-600',
  leave: 'bg-violet-50 text-violet-600',
  sick: 'bg-orange-50 text-orange-600',
  alpha: 'bg-slate-100 text-slate-500',
}

function employeeName(employee: Employee): string {
  return `${employee.first_name} ${employee.last_name}`.trim()
}

function employeeInitials(employee: Employee): string {
  return `${employee.first_name?.[0] ?? ''}${employee.last_name?.[0] ?? ''}`.toUpperCase()
}

function formatDateTime(value: string | null): string {
  if (!value) return '-'
  return new Date(value).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
}

function formatDateLabel(value: string): string {
  return new Date(value).toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' })
}

const attendances = ref<AttendanceRow[]>([])
const employees = ref<Employee[]>([])
const shifts = ref<Shift[]>([])
const loading = ref(true)
const errorMessage = ref('')

const filters = reactive({
  employee_id: null as number | null,
  date_from: '',
  date_to: '',
})

const showFilters = ref(false)
const searchQuery = ref('')

const dateRangeLabel = computed(() => {
  if (filters.date_from && filters.date_to) return `${filters.date_from} — ${filters.date_to}`
  if (filters.date_from) return `Sejak ${filters.date_from}`
  if (filters.date_to) return `Sampai ${filters.date_to}`
  return 'Semua Tanggal'
})

const filteredAttendances = computed(() => {
  if (!searchQuery.value.trim()) return attendances.value
  const q = searchQuery.value.toLowerCase()
  return attendances.value.filter(
    (row) =>
      employeeName(row.employee).toLowerCase().includes(q) ||
      (row.employee.employee_number ?? '').toLowerCase().includes(q)
  )
})

const statusCounts = computed(() => {
  const counts: Record<AttendanceStatus, number> = {
    present: 0, late: 0, absent: 0, half_day: 0, leave: 0, sick: 0, alpha: 0,
  }
  for (const row of attendances.value) counts[row.status]++
  return counts
})

const summaryStats = computed(() => [
  { label: 'Tepat Waktu', value: statusCounts.value.present },
  { label: 'Terlambat', value: statusCounts.value.late },
  { label: 'Absen', value: statusCounts.value.absent },
  { label: 'Setengah Hari', value: statusCounts.value.half_day },
  { label: 'Cuti', value: statusCounts.value.leave },
  { label: 'Sakit', value: statusCounts.value.sick },
  { label: 'Alpha', value: statusCounts.value.alpha },
])

// --- Row selection (visual only, belum ada bulk action) ---
const selectedIds = ref<number[]>([])
const allSelected = computed(
  () => filteredAttendances.value.length > 0 && filteredAttendances.value.every((r) => selectedIds.value.includes(r.id))
)
function toggleSelectAll() {
  if (allSelected.value) {
    const ids = new Set(filteredAttendances.value.map((r) => r.id))
    selectedIds.value = selectedIds.value.filter((id) => !ids.has(id))
  } else {
    const newIds = filteredAttendances.value.map((r) => r.id)
    selectedIds.value = Array.from(new Set([...selectedIds.value, ...newIds]))
  }
}
function toggleSelectRow(id: number) {
  selectedIds.value = selectedIds.value.includes(id)
    ? selectedIds.value.filter((x) => x !== id)
    : [...selectedIds.value, id]
}

// --- Per-row actions dropdown ---
const openActionsRow = ref<AttendanceRow | null>(null)
const actionsMenuStyle = ref({ top: '0px', left: '0px' })

function toggleActions(row: AttendanceRow, event: Event) {
  event.stopPropagation()
  if (openActionsRow.value?.id === row.id) {
    openActionsRow.value = null
    return
  }
  const rect = (event.currentTarget as HTMLElement).getBoundingClientRect()
  actionsMenuStyle.value = {
    top: `${rect.bottom + window.scrollY + 4}px`,
    left: `${rect.right + window.scrollX - 144}px`, // 144px = lebar menu (w-36)
  }
  openActionsRow.value = row
}

function closeActions() {
  openActionsRow.value = null
}

onMounted(() => window.addEventListener('click', closeActions))
onUnmounted(() => window.removeEventListener('click', closeActions))

const showModal = ref(false)
const isEditing = ref(false)
const saving = ref(false)
const formError = ref('')

const form = reactive({
  id: 0,
  employee_id: null as number | null,
  attendance_date: '',
  shift_id: null as number | null,
  clock_in: '',
  clock_out: '',
  status: 'present' as AttendanceStatus,
  notes: '',
})

async function loadAttendances() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/attendances', {
      params: {
        employee_id: filters.employee_id || undefined,
        date_from: filters.date_from || undefined,
        date_to: filters.date_to || undefined,
      },
    })
    attendances.value = response.data.data.data
  } catch {
    errorMessage.value = 'Gagal memuat daftar attendance.'
  } finally {
    loading.value = false
  }
}

async function loadReferenceData() {
  const [employeeRes, shiftRes] = await Promise.all([
    apiClient.get('/api/employees', { params: { per_page: 100 } }),
    apiClient.get('/api/shifts', { params: { per_page: 100 } }),
  ])
  employees.value = employeeRes.data.data.data
  shifts.value = shiftRes.data.data.data
}

function resetForm() {
  form.id = 0
  form.employee_id = employees.value[0]?.id ?? null
  form.attendance_date = new Date().toISOString().slice(0, 10)
  form.shift_id = null
  form.clock_in = ''
  form.clock_out = ''
  form.status = 'present'
  form.notes = ''
}

function openCreateModal() {
  isEditing.value = false
  formError.value = ''
  resetForm()
  showModal.value = true
}

function toDatetimeLocal(value: string | null): string {
  if (!value) return ''
  return value.slice(0, 16)
}

function openEditModal(row: AttendanceRow) {
  isEditing.value = true
  formError.value = ''
  form.id = row.id
  form.employee_id = row.employee_id
  form.attendance_date = row.attendance_date.slice(0, 10)
  form.shift_id = row.shift_id
  form.clock_in = toDatetimeLocal(row.clock_in)
  form.clock_out = toDatetimeLocal(row.clock_out)
  form.status = row.status
  form.notes = row.notes ?? ''
  showModal.value = true
}

function closeModal() {
  showModal.value = false
}

async function handleSubmit() {
  formError.value = ''
  saving.value = true

  const payload = {
    employee_id: form.employee_id,
    attendance_date: form.attendance_date,
    shift_id: form.shift_id,
    clock_in: form.clock_in || null,
    clock_out: form.clock_out || null,
    status: form.status,
    notes: form.notes || null,
  }

  try {
    if (isEditing.value) {
      await apiClient.put(`/api/attendances/${form.id}`, payload)
    } else {
      await apiClient.post('/api/attendances', payload)
    }

    showModal.value = false
    await loadAttendances()
  } catch (err: any) {
    formError.value = err.response?.data?.message || 'Terjadi kesalahan, silakan coba lagi.'
  } finally {
    saving.value = false
  }
}

async function handleDelete(row: AttendanceRow) {
  if (!confirm(`Hapus attendance "${employeeName(row.employee)}" tanggal ${row.attendance_date}?`)) return

  try {
    await apiClient.delete(`/api/attendances/${row.id}`)
    await loadAttendances()
  } catch {
    alert('Gagal menghapus attendance.')
  }
}

watch(() => [filters.employee_id, filters.date_from, filters.date_to], () => {
  loadAttendances()
})

onMounted(() => {
  loadAttendances()
  loadReferenceData()
})
</script>

<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Attendance Data</h1>
      <div class="flex items-center gap-2">
        <button type="button" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50">
          Help
        </button>
        <button type="button" class="flex items-center gap-1.5 rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50">
          Import
          <ChevronDown class="h-3.5 w-3.5" :stroke-width="2" />
        </button>
        <button
          @click="openCreateModal"
          :disabled="employees.length === 0"
          class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-dark disabled:opacity-50"
        >
          <Plus class="h-4 w-4" :stroke-width="2" />
          Tambah Attendance
        </button>
      </div>
    </div>

    <!-- Filter bar -->
    <div class="rounded-2xl border border-slate-100 bg-white p-4">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-3">
          <button
            type="button"
            @click="showFilters = !showFilters"
            class="rounded-xl border border-slate-200 px-3.5 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50"
          >
            {{ dateRangeLabel }}
          </button>
          <button
            type="button"
            @click="showFilters = !showFilters"
            class="text-sm font-medium text-primary-dark hover:underline"
          >
            {{ showFilters ? 'Sembunyikan filter' : 'Semua filter' }}
          </button>
        </div>

        <div class="flex items-center gap-1.5">
          <button type="button" class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-50 hover:text-slate-600">
            <SlidersHorizontal class="h-[18px] w-[18px]" :stroke-width="1.75" />
          </button>
          <button type="button" class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-50 hover:text-slate-600">
            <Download class="h-[18px] w-[18px]" :stroke-width="1.75" />
          </button>
          <div class="relative">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" :stroke-width="1.75" />
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Cari nama / ID karyawan"
              class="w-56 rounded-xl border border-slate-200 py-2 pl-9 pr-3 text-sm focus:border-primary focus:outline-none"
            />
          </div>
        </div>
      </div>

      <Transition
        enter-active-class="transition-all duration-150 ease-out"
        enter-from-class="opacity-0 -translate-y-1"
        enter-to-class="opacity-100 translate-y-0"
      >
        <div v-if="showFilters" class="mt-4 flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4">
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">Employee</label>
            <select v-model="filters.employee_id" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
              <option :value="null">Semua Employee</option>
              <option v-for="e in employees" :key="e.id" :value="e.id">{{ employeeName(e) }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">Dari Tanggal</label>
            <input v-model="filters.date_from" type="date" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">Sampai Tanggal</label>
            <input v-model="filters.date_to" type="date" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
          </div>
        </div>
      </Transition>
    </div>

    <!-- Summary stat strip -->
    <div v-if="!loading && !errorMessage" class="flex flex-wrap divide-x divide-slate-100 overflow-hidden rounded-2xl border border-slate-100 bg-white">
      <div v-for="stat in summaryStats" :key="stat.label" class="flex-1 min-w-[110px] px-5 py-4">
        <p class="text-xl font-semibold tracking-tight" :class="stat.value > 0 ? 'text-slate-900' : 'text-slate-300'">
          {{ stat.value }}
        </p>
        <p class="mt-0.5 text-xs text-slate-500">{{ stat.label }}</p>
      </div>
    </div>

    <p v-if="employees.length === 0 && !loading" class="rounded-xl bg-amber-50 p-4 text-sm text-amber-700">
      Belum ada employee. Tambahkan employee terlebih dahulu sebelum mencatat attendance.
    </p>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">
      {{ errorMessage }}
    </div>
    <div v-else-if="filteredAttendances.length === 0" class="rounded-xl bg-slate-50 p-4 text-sm text-slate-400">
      Belum ada record attendance untuk filter ini.
    </div>

    <!-- Table -->
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
              <th class="px-3 py-3 font-medium text-slate-500">Organization</th>
              <th class="px-3 py-3 font-medium text-slate-500">Employment Status</th>
              <th class="px-3 py-3 font-medium text-slate-500">Tanggal</th>
              <th class="px-3 py-3 font-medium text-slate-500">Shift</th>
              <th class="px-3 py-3 font-medium text-slate-500">Clock In / Out</th>
              <th class="px-3 py-3 font-medium text-slate-500">Status</th>
              <th class="px-5 py-3 text-right font-medium text-slate-500">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in filteredAttendances"
              :key="row.id"
              class="border-b border-slate-50 last:border-0 hover:bg-slate-50/50"
            >
              <td class="px-5 py-3.5">
                <input
                  type="checkbox"
                  :checked="selectedIds.includes(row.id)"
                  @change="toggleSelectRow(row.id)"
                  class="rounded border-slate-300 text-primary focus:ring-primary"
                />
              </td>
              <td class="px-3 py-3.5">
                <div class="flex items-center gap-2.5">
                  <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-soft text-xs font-semibold text-primary-dark">
                    {{ employeeInitials(row.employee) }}
                  </div>
                  <div>
                    <p class="font-medium text-slate-800">{{ employeeName(row.employee) }}</p>
                    <p v-if="row.employee.employee_number" class="text-xs text-slate-400">{{ row.employee.employee_number }}</p>
                  </div>
                </div>
              </td>
              <td class="px-3 py-3.5 text-slate-500">{{ row.employee.branch?.name ?? '-' }}</td>
              <td class="px-3 py-3.5 text-slate-500">{{ row.employee.department?.name ?? '-' }}</td>
              <td class="px-3 py-3.5 text-slate-500">{{ row.employee.employment_status?.name ?? '-' }}</td>
              <td class="px-3 py-3.5 whitespace-nowrap text-slate-500">{{ formatDateLabel(row.attendance_date) }}</td>
              <td class="px-3 py-3.5 text-slate-500">{{ row.shift?.name ?? '-' }}</td>
              <td class="px-3 py-3.5 whitespace-nowrap text-slate-500">
                <div class="flex items-center gap-1.5">
                  <Clock class="h-3.5 w-3.5 text-slate-300" :stroke-width="1.75" />
                  {{ formatDateTime(row.clock_in) }} - {{ formatDateTime(row.clock_out) }}
                </div>
              </td>
              <td class="px-3 py-3.5">
                <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="statusBadgeClass[row.status]">
                  {{ statusLabels[row.status] }}
                </span>
              </td>
              <td class="px-5 py-3.5 text-right">
                <button
                  type="button"
                  @click="toggleActions(row, $event)"
                  class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-600 transition-colors hover:bg-slate-50"
                  :class="openActionsRow?.id === row.id ? 'border-primary/40 text-primary-dark' : ''"
                >
                  Actions
                  <ChevronDown class="h-3.5 w-3.5" :stroke-width="2" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <Teleport to="body">
      <div
        v-if="openActionsRow"
        @click.stop
        class="fixed z-50 w-36 overflow-hidden rounded-xl border border-slate-100 bg-white py-1 shadow-lg"
        :style="actionsMenuStyle"
      >
        <button
          @click="openEditModal(openActionsRow!); closeActions()"
          class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-600 hover:bg-slate-50"
        >
          <Pencil class="h-3.5 w-3.5" :stroke-width="1.75" />
          Edit
        </button>
        <button
          @click="handleDelete(openActionsRow!); closeActions()"
          class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-red-500 hover:bg-red-50"
        >
          <Trash2 class="h-3.5 w-3.5" :stroke-width="1.75" />
          Hapus
        </button>
      </div>
    </Teleport>

    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8">
        <div class="flex max-h-full w-full max-w-xl flex-col rounded-2xl bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">
              {{ isEditing ? 'Edit Attendance' : 'Tambah Attendance' }}
            </h2>
            <button @click="closeModal" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50">
              <X class="h-5 w-5" />
            </button>
          </div>

          <form @submit.prevent="handleSubmit" class="flex-1 space-y-4 overflow-y-auto px-6 py-5">
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Employee</label>
                <select
                  v-model.number="form.employee_id"
                  required
                  class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
                >
                  <option :value="null" disabled>Pilih employee</option>
                  <option v-for="e in employees" :key="e.id" :value="e.id">{{ employeeName(e) }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Tanggal</label>
                <input
                  v-model="form.attendance_date"
                  type="date"
                  required
                  class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
                />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Shift</label>
                <select
                  v-model="form.shift_id"
                  class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
                >
                  <option :value="null">Tanpa Shift</option>
                  <option v-for="s in shifts" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
                <select
                  v-model="form.status"
                  required
                  class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
                >
                  <option v-for="(label, value) in statusLabels" :key="value" :value="value">{{ label }}</option>
                </select>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Clock In</label>
                <input
                  v-model="form.clock_in"
                  type="datetime-local"
                  class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
                />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Clock Out</label>
                <input
                  v-model="form.clock_out"
                  type="datetime-local"
                  class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
                />
              </div>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Catatan</label>
              <textarea
                v-model="form.notes"
                rows="2"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
              ></textarea>
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