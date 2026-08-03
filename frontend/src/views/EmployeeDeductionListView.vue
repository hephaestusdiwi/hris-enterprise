<script setup lang="ts">
import { ref, onMounted, reactive, computed } from 'vue'
import {
  Plus, Filter, Download, Upload, X, Check, Ban, Loader2,
  FileText, User, Calendar, Tag, AlertTriangle, History,
} from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface Employee { id: number; first_name: string; last_name: string | null; department_id: number | null; company_id: number }
interface Department { id: number; name: string }
interface SalaryComponent { id: number; name: string; code: string; company_id: number; category: string; is_addition: boolean }

type Status = 'draft' | 'ready' | 'processed' | 'void'

interface DeductionRow {
  id: number
  employee_id: number
  payroll_period_year: number
  payroll_period_month: number
  amount: string
  remark: string | null
  status: Status
  created_at: string
  processed_at: string | null
  voided_at: string | null
  void_reason: string | null
  employee: Employee & { department: Department | null }
  salary_component: SalaryComponent
  created_by: { id: number; name: string } | null
  voided_by: { id: number; name: string } | null
}

const monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']

const statusLabels: Record<Status, string> = { draft: 'Draft', ready: 'Ready', processed: 'Processed', void: 'Void' }
const statusBadgeClass: Record<Status, string> = {
  draft: 'bg-slate-100 text-slate-500',
  ready: 'bg-blue-50 text-blue-600',
  processed: 'bg-primary-soft text-primary-dark',
  void: 'bg-red-50 text-red-600',
}

function employeeName(e: { first_name: string; last_name: string | null }) {
  return [e.first_name, e.last_name].filter(Boolean).join(' ')
}

function formatCurrency(value: string) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(Number(value))
}

function formatDateTime(value: string | null) {
  if (!value) return '-'
  return new Date(value.replace(' ', 'T')).toLocaleString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

// ---------- DATA ----------
const deductions = ref<DeductionRow[]>([])
const employees = ref<Employee[]>([])
const departments = ref<Department[]>([])
const deductionComponents = ref<SalaryComponent[]>([])
const summary = ref({ draft: 0, ready: 0, processed: 0, void: 0 })
const loading = ref(true)
const errorMessage = ref('')
const meta = ref({ current_page: 1, last_page: 1, total: 0 })

const currentDate = new Date()
const filters = reactive({
  employee_id: null as number | null,
  department_id: null as number | null,
  payroll_period_year: currentDate.getFullYear(),
  payroll_period_month: currentDate.getMonth() + 1,
  salary_component_id: null as number | null,
  status: null as Status | null,
  page: 1,
})

function buildParams() {
  return {
    employee_id: filters.employee_id || undefined,
    department_id: filters.department_id || undefined,
    payroll_period_year: filters.payroll_period_year || undefined,
    payroll_period_month: filters.payroll_period_month || undefined,
    salary_component_id: filters.salary_component_id || undefined,
    status: filters.status || undefined,
    page: filters.page,
  }
}

async function loadDeductions() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/employee-deductions', { params: buildParams() })
    deductions.value = response.data.data.data
    meta.value = {
      current_page: response.data.data.current_page,
      last_page: response.data.data.last_page,
      total: response.data.data.total,
    }
  } catch {
    errorMessage.value = 'Gagal memuat daftar deduction.'
  } finally {
    loading.value = false
  }
}

async function loadSummary() {
  try {
    const response = await apiClient.get('/api/employee-deductions/summary', { params: buildParams() })
    summary.value = response.data.data
  } catch {
    // biarin default 0
  }
}

async function loadReferenceData() {
  const [employeeRes, departmentRes, componentRes] = await Promise.all([
    apiClient.get('/api/employees', { params: { per_page: 100 } }),
    apiClient.get('/api/departments'),
    apiClient.get('/api/salary-components'),
  ])
  employees.value = employeeRes.data.data.data
  departments.value = departmentRes.data.data.data
  deductionComponents.value = componentRes.data.data.data.filter(
    (c: SalaryComponent) => c.category === 'deduction' && !c.is_addition,
  )
}

function applyFilters() {
  filters.page = 1
  loadDeductions()
  loadSummary()
}

function filterByStatus(status: Status | null) {
  filters.status = status
  applyFilters()
}

function goToPage(page: number) {
  filters.page = page
  loadDeductions()
}

// ---------- SELECTION ----------
const selectedIds = ref<Set<number>>(new Set())
const allSelected = computed(() => deductions.value.length > 0 && deductions.value.every((d) => selectedIds.value.has(d.id)))

function toggleSelectAll() {
  if (allSelected.value) deductions.value.forEach((d) => selectedIds.value.delete(d.id))
  else deductions.value.forEach((d) => selectedIds.value.add(d.id))
}
function toggleSelect(id: number) {
  if (selectedIds.value.has(id)) selectedIds.value.delete(id)
  else selectedIds.value.add(id)
}

// ---------- CREATE MODAL ----------
const showCreateModal = ref(false)
const saving = ref(false)
const createError = ref('')

const form = reactive({
  employee_id: null as number | null,
  salary_component_id: null as number | null,
  payroll_period_year: currentDate.getFullYear(),
  payroll_period_month: currentDate.getMonth() + 1,
  amount: null as number | null,
  remark: '',
})

const componentsForEmployee = computed(() => {
  const emp = employees.value.find((e) => e.id === form.employee_id)
  return emp ? deductionComponents.value.filter((c) => c.company_id === emp.company_id) : []
})

function openCreateModal() {
  createError.value = ''
  form.employee_id = employees.value[0]?.id ?? null
  form.salary_component_id = null
  form.payroll_period_year = currentDate.getFullYear()
  form.payroll_period_month = currentDate.getMonth() + 1
  form.amount = null
  form.remark = ''
  showCreateModal.value = true
}

async function submitCreate() {
  createError.value = ''
  saving.value = true

  try {
    await apiClient.post('/api/employee-deductions', form)
    showCreateModal.value = false
    await Promise.all([loadDeductions(), loadSummary()])
  } catch (err: any) {
    createError.value = err.response?.data?.message || 'Gagal membuat deduction.'
  } finally {
    saving.value = false
  }
}

// ---------- BULK ACTIONS ----------
const bulkVoidReason = ref('')
const showBulkVoidModal = ref(false)
const bulkProcessing = ref(false)

async function bulkMarkReady() {
  if (selectedIds.value.size === 0) return
  bulkProcessing.value = true
  try {
    await apiClient.post('/api/employee-deductions/bulk-mark-ready', { ids: Array.from(selectedIds.value) })
    selectedIds.value.clear()
    await Promise.all([loadDeductions(), loadSummary()])
  } catch {
    alert('Gagal memproses bulk action.')
  } finally {
    bulkProcessing.value = false
  }
}

function openBulkVoid() {
  bulkVoidReason.value = ''
  showBulkVoidModal.value = true
}

async function submitBulkVoid() {
  if (!bulkVoidReason.value.trim()) return
  bulkProcessing.value = true
  try {
    await apiClient.post('/api/employee-deductions/bulk-void', {
      ids: Array.from(selectedIds.value),
      reason: bulkVoidReason.value,
    })
    showBulkVoidModal.value = false
    selectedIds.value.clear()
    await Promise.all([loadDeductions(), loadSummary()])
  } catch {
    alert('Gagal memproses bulk void.')
  } finally {
    bulkProcessing.value = false
  }
}

// ---------- IMPORT / EXPORT ----------
const importing = ref(false)
const importResult = ref<{ created: number; errors: string[] } | null>(null)
const fileInputRef = ref<HTMLInputElement | null>(null)

function triggerImport() {
  fileInputRef.value?.click()
}

async function handleImportFile(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (!file) return

  importing.value = true
  importResult.value = null

  try {
    const formData = new FormData()
    formData.append('file', file)
    const response = await apiClient.post('/api/employee-deductions/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    importResult.value = response.data.data
    await Promise.all([loadDeductions(), loadSummary()])
  } catch (err: any) {
    alert(err.response?.data?.message || 'Gagal import file.')
  } finally {
    importing.value = false
    if (fileInputRef.value) fileInputRef.value.value = ''
  }
}

function handleExport() {
  const params = new URLSearchParams()
  Object.entries(buildParams()).forEach(([key, value]) => {
    if (value !== undefined && key !== 'page') params.append(key, String(value))
  })
  const baseUrl = apiClient.defaults.baseURL ?? ''
  window.open(`${baseUrl}/api/employee-deductions/export?${params.toString()}`, '_blank')
}

// ---------- DRAWER DETAIL ----------
const showDrawer = ref(false)
const drawerTarget = ref<DeductionRow | null>(null)
const drawerVoidReason = ref('')
const showDrawerVoidForm = ref(false)

function openDrawer(row: DeductionRow) {
  drawerTarget.value = row
  showDrawerVoidForm.value = false
  drawerVoidReason.value = ''
  showDrawer.value = true
}

function closeDrawer() {
  showDrawer.value = false
  drawerTarget.value = null
}

async function submitDrawerVoid() {
  if (!drawerTarget.value || !drawerVoidReason.value.trim()) return
  bulkProcessing.value = true
  try {
    await apiClient.post(`/api/employee-deductions/${drawerTarget.value.id}/void`, { reason: drawerVoidReason.value })
    closeDrawer()
    await Promise.all([loadDeductions(), loadSummary()])
  } catch (err: any) {
    alert(err.response?.data?.message || 'Gagal void deduction.')
  } finally {
    bulkProcessing.value = false
  }
}

onMounted(() => {
  loadDeductions()
  loadSummary()
  loadReferenceData()
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Employee Deduction</h1>
        <p class="mt-1 text-sm text-slate-500">Potongan sekali-pakai per periode payroll.</p>
      </div>
      <div class="flex items-center gap-2">
        <input ref="fileInputRef" type="file" accept=".xlsx,.csv" class="hidden" @change="handleImportFile" />
        <button @click="triggerImport" :disabled="importing" class="flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-50">
          <Loader2 v-if="importing" class="h-4 w-4 animate-spin" :stroke-width="2" />
          <Upload v-else class="h-4 w-4" :stroke-width="1.75" />
          Import Excel
        </button>
        <button @click="handleExport" class="flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50">
          <Download class="h-4 w-4" :stroke-width="1.75" />
          Export Excel
        </button>
        <button @click="openCreateModal" :disabled="employees.length === 0" class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50">
          <Plus class="h-4 w-4" :stroke-width="2" />
          Tambah Deduction
        </button>
      </div>
    </div>

    <div v-if="importResult" class="rounded-xl border border-slate-100 bg-white p-4 text-sm">
      <p class="font-medium text-slate-700">{{ importResult.created }} deduction berhasil diimport.</p>
      <div v-if="importResult.errors.length > 0" class="mt-2 space-y-1">
        <p class="text-xs font-medium text-red-600">{{ importResult.errors.length }} baris gagal:</p>
        <p v-for="(err, i) in importResult.errors" :key="i" class="text-xs text-red-500">{{ err }}</p>
      </div>
      <button @click="importResult = null" class="mt-2 text-xs text-slate-400 hover:text-slate-600">Tutup</button>
    </div>

    <!-- SUMMARY CARDS -->
    <div class="grid grid-cols-4 gap-4">
      <button
        @click="filterByStatus(filters.status === 'draft' ? null : 'draft')"
        class="rounded-2xl border p-4 text-left transition-colors"
        :class="filters.status === 'draft' ? 'border-slate-400 bg-slate-50' : 'border-slate-100 bg-white hover:bg-slate-50/50'"
      >
        <p class="text-xs font-medium text-slate-400">Draft</p>
        <p class="mt-1 text-2xl font-semibold text-slate-700">{{ summary.draft }}</p>
      </button>
      <button
        @click="filterByStatus(filters.status === 'ready' ? null : 'ready')"
        class="rounded-2xl border p-4 text-left transition-colors"
        :class="filters.status === 'ready' ? 'border-blue-300 bg-blue-50' : 'border-slate-100 bg-white hover:bg-slate-50/50'"
      >
        <p class="text-xs font-medium text-blue-500">Ready</p>
        <p class="mt-1 text-2xl font-semibold text-blue-600">{{ summary.ready }}</p>
      </button>
      <button
        @click="filterByStatus(filters.status === 'processed' ? null : 'processed')"
        class="rounded-2xl border p-4 text-left transition-colors"
        :class="filters.status === 'processed' ? 'border-primary bg-primary-soft' : 'border-slate-100 bg-white hover:bg-slate-50/50'"
      >
        <p class="text-xs font-medium text-primary-dark">Processed</p>
        <p class="mt-1 text-2xl font-semibold text-primary-dark">{{ summary.processed }}</p>
      </button>
      <button
        @click="filterByStatus(filters.status === 'void' ? null : 'void')"
        class="rounded-2xl border p-4 text-left transition-colors"
        :class="filters.status === 'void' ? 'border-red-300 bg-red-50' : 'border-slate-100 bg-white hover:bg-slate-50/50'"
      >
        <p class="text-xs font-medium text-red-500">Void</p>
        <p class="mt-1 text-2xl font-semibold text-red-600">{{ summary.void }}</p>
      </button>
    </div>

    <!-- FILTERS -->
    <div class="flex flex-wrap items-end gap-3 rounded-2xl border border-slate-100 bg-white p-4">
      <div class="flex items-center gap-1.5 text-xs font-medium text-slate-400">
        <Filter class="h-3.5 w-3.5" :stroke-width="1.75" />
        Filter
      </div>
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-500">Employee</label>
        <select v-model="filters.employee_id" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
          <option :value="null">Semua Employee</option>
          <option v-for="e in employees" :key="e.id" :value="e.id">{{ employeeName(e) }}</option>
        </select>
      </div>
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-500">Department</label>
        <select v-model="filters.department_id" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
          <option :value="null">Semua Department</option>
          <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>
      </div>
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-500">Periode</label>
        <div class="flex gap-1.5">
          <select v-model.number="filters.payroll_period_month" class="rounded-xl border border-slate-200 px-2 py-2 text-sm focus:border-primary focus:outline-none">
            <option v-for="(m, i) in monthNames" :key="i" :value="i + 1">{{ m }}</option>
          </select>
          <input v-model.number="filters.payroll_period_year" type="number" class="w-24 rounded-xl border border-slate-200 px-2 py-2 text-sm focus:border-primary focus:outline-none" />
        </div>
      </div>
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-500">Komponen</label>
        <select v-model="filters.salary_component_id" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
          <option :value="null">Semua Komponen</option>
          <option v-for="c in deductionComponents" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
      </div>
      <button @click="applyFilters" class="rounded-xl bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900">
        Terapkan
      </button>
    </div>

    <!-- BULK ACTION BAR -->
    <div v-if="selectedIds.size > 0" class="flex items-center justify-between rounded-2xl bg-primary-soft px-5 py-3">
      <p class="text-sm font-medium text-primary-dark">{{ selectedIds.size }} deduction dipilih</p>
      <div class="flex items-center gap-2">
        <button @click="selectedIds.clear()" class="text-xs font-medium text-primary-dark hover:underline">Batal pilih</button>
        <button @click="bulkMarkReady" :disabled="bulkProcessing" class="flex items-center gap-1.5 rounded-lg bg-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-primary-dark disabled:opacity-50">
          <Check class="h-3.5 w-3.5" :stroke-width="2" />
          Tandai Ready
        </button>
        <button @click="openBulkVoid" :disabled="bulkProcessing" class="flex items-center gap-1.5 rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 disabled:opacity-50">
          <Ban class="h-3.5 w-3.5" :stroke-width="2" />
          Void
        </button>
      </div>
    </div>

    <!-- TABLE -->
    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>
    <div v-else-if="deductions.length === 0" class="rounded-xl bg-slate-50 p-6 text-center text-sm text-slate-400">
      Tidak ada deduction untuk filter ini.
    </div>

    <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <table class="w-full text-left text-sm">
        <thead>
          <tr class="border-b border-slate-100 bg-slate-50/60">
            <th class="w-10 px-5 py-3"><input type="checkbox" :checked="allSelected" @change="toggleSelectAll" class="rounded border-slate-300" /></th>
            <th class="px-5 py-3 font-medium text-slate-500">Employee</th>
            <th class="px-5 py-3 font-medium text-slate-500">Komponen</th>
            <th class="px-5 py-3 font-medium text-slate-500">Periode</th>
            <th class="px-5 py-3 text-right font-medium text-slate-500">Amount</th>
            <th class="px-5 py-3 font-medium text-slate-500">Status</th>
            <th class="px-5 py-3"></th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in deductions"
            :key="row.id"
            class="cursor-pointer border-b border-slate-50 last:border-0 hover:bg-slate-50/50"
            @click="openDrawer(row)"
          >
            <td class="px-5 py-3.5" @click.stop>
              <input type="checkbox" :checked="selectedIds.has(row.id)" @change="toggleSelect(row.id)" class="rounded border-slate-300" />
            </td>
            <td class="px-5 py-3.5">
              <p class="font-medium text-slate-800">{{ employeeName(row.employee) }}</p>
              <p class="text-xs text-slate-400">{{ row.employee.department?.name ?? '-' }}</p>
            </td>
            <td class="px-5 py-3.5 text-slate-500">{{ row.salary_component.name }}</td>
            <td class="px-5 py-3.5 text-slate-500">{{ monthNames[row.payroll_period_month - 1] }} {{ row.payroll_period_year }}</td>
            <td class="px-5 py-3.5 text-right font-medium text-red-600">- {{ formatCurrency(row.amount) }}</td>
            <td class="px-5 py-3.5">
              <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="statusBadgeClass[row.status]">{{ statusLabels[row.status] }}</span>
            </td>
            <td class="px-5 py-3.5 text-right text-slate-300">
              <FileText class="ml-auto h-4 w-4" :stroke-width="1.75" />
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="meta.last_page > 1" class="flex items-center justify-between text-sm text-slate-500">
      <p>Total {{ meta.total }} deduction</p>
      <div class="flex gap-1">
        <button v-for="page in meta.last_page" :key="page" @click="goToPage(page)" class="rounded-lg px-3 py-1.5 text-xs font-medium" :class="page === meta.current_page ? 'bg-primary text-white' : 'text-slate-500 hover:bg-slate-100'">
          {{ page }}
        </button>
      </div>
    </div>

    <!-- CREATE MODAL -->
    <Teleport to="body">
      <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8">
        <div class="w-full max-w-lg rounded-2xl bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Tambah Deduction</h2>
            <button @click="showCreateModal = false" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50"><X class="h-5 w-5" /></button>
          </div>

          <form @submit.prevent="submitCreate" class="space-y-4 px-6 py-5">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Employee</label>
              <select v-model.number="form.employee_id" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                <option v-for="e in employees" :key="e.id" :value="e.id">{{ employeeName(e) }}</option>
              </select>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Salary Component (Deduction)</label>
              <select v-model.number="form.salary_component_id" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                <option :value="null" disabled>Pilih komponen</option>
                <option v-for="c in componentsForEmployee" :key="c.id" :value="c.id">{{ c.name }} ({{ c.code }})</option>
              </select>
              <p v-if="componentsForEmployee.length === 0 && form.employee_id" class="mt-1 text-xs text-amber-600">
                Belum ada Salary Component kategori Deduction untuk company employee ini.
              </p>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Bulan</label>
                <select v-model.number="form.payroll_period_month" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                  <option v-for="(m, i) in monthNames" :key="i" :value="i + 1">{{ m }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Tahun</label>
                <input v-model.number="form.payroll_period_year" type="number" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Amount (Rp)</label>
              <input v-model.number="form.amount" type="number" min="0" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Remark</label>
              <textarea v-model="form.remark" rows="2" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"></textarea>
            </div>

            <p v-if="createError" class="text-sm text-red-600">{{ createError }}</p>
          </form>

          <div class="border-t border-slate-100 px-6 py-4">
            <button @click="submitCreate" :disabled="saving" class="w-full rounded-xl bg-primary py-2.5 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50">
              {{ saving ? 'Menyimpan...' : 'Simpan' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- BULK VOID MODAL -->
    <Teleport to="body">
      <div v-if="showBulkVoidModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8">
        <div class="w-full max-w-md rounded-2xl bg-white shadow-xl">
          <div class="border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Void {{ selectedIds.size }} Deduction</h2>
          </div>
          <div class="space-y-3 px-6 py-5">
            <label class="mb-1 block text-sm font-medium text-slate-700">Alasan (wajib)</label>
            <textarea v-model="bulkVoidReason" rows="3" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"></textarea>
          </div>
          <div class="flex gap-3 border-t border-slate-100 px-6 py-4">
            <button @click="showBulkVoidModal = false" class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50">Batal</button>
            <button @click="submitBulkVoid" :disabled="bulkProcessing || !bulkVoidReason.trim()" class="flex-1 rounded-xl bg-red-600 py-2.5 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50">
              {{ bulkProcessing ? 'Memproses...' : 'Void' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- DRAWER DETAIL -->
    <Teleport to="body">
      <div v-if="showDrawer" class="fixed inset-0 z-50 flex justify-end bg-slate-900/30">
        <div class="h-full w-full max-w-md overflow-y-auto bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Detail Deduction</h2>
            <button @click="closeDrawer" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50"><X class="h-5 w-5" /></button>
          </div>

          <div v-if="drawerTarget" class="space-y-5 px-6 py-5">
            <span class="inline-block rounded-full px-2.5 py-1 text-xs font-medium" :class="statusBadgeClass[drawerTarget.status]">
              {{ statusLabels[drawerTarget.status] }}
            </span>

            <div class="space-y-3">
              <div class="flex items-start gap-3">
                <User class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" :stroke-width="1.75" />
                <div>
                  <p class="text-xs text-slate-400">Employee</p>
                  <p class="text-sm font-medium text-slate-700">{{ employeeName(drawerTarget.employee) }}</p>
                  <p class="text-xs text-slate-400">{{ drawerTarget.employee.department?.name ?? '-' }}</p>
                </div>
              </div>

              <div class="flex items-start gap-3">
                <Calendar class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" :stroke-width="1.75" />
                <div>
                  <p class="text-xs text-slate-400">Payroll Period</p>
                  <p class="text-sm font-medium text-slate-700">{{ monthNames[drawerTarget.payroll_period_month - 1] }} {{ drawerTarget.payroll_period_year }}</p>
                </div>
              </div>

              <div class="flex items-start gap-3">
                <Tag class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" :stroke-width="1.75" />
                <div>
                  <p class="text-xs text-slate-400">Salary Component</p>
                  <p class="text-sm font-medium text-slate-700">{{ drawerTarget.salary_component.name }}</p>
                </div>
              </div>

              <div class="rounded-xl bg-red-50 p-4">
                <p class="text-xs text-red-500">Amount (Potongan)</p>
                <p class="text-xl font-semibold text-red-600">- {{ formatCurrency(drawerTarget.amount) }}</p>
              </div>

              <div v-if="drawerTarget.remark">
                <p class="text-xs text-slate-400">Remark</p>
                <p class="text-sm text-slate-600">{{ drawerTarget.remark }}</p>
              </div>
            </div>

            <div class="border-t border-slate-100 pt-4">
              <div class="mb-2 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-slate-400">
                <History class="h-3.5 w-3.5" :stroke-width="1.75" />
                Audit Trail
              </div>
              <div class="space-y-2 text-xs text-slate-500">
                <p>Dibuat oleh <span class="font-medium text-slate-700">{{ drawerTarget.created_by?.name ?? '-' }}</span> · {{ formatDateTime(drawerTarget.created_at) }}</p>
                <p v-if="drawerTarget.processed_at">Diproses payroll · {{ formatDateTime(drawerTarget.processed_at) }}</p>
                <div v-if="drawerTarget.voided_at" class="rounded-lg bg-red-50 p-2.5">
                  <p class="font-medium text-red-600">Di-void oleh {{ drawerTarget.voided_by?.name ?? '-' }} · {{ formatDateTime(drawerTarget.voided_at) }}</p>
                  <p class="mt-0.5 text-red-500">{{ drawerTarget.void_reason }}</p>
                </div>
              </div>
            </div>

            <div v-if="drawerTarget.status === 'draft' || drawerTarget.status === 'ready'" class="border-t border-slate-100 pt-4">
              <button v-if="!showDrawerVoidForm" @click="showDrawerVoidForm = true" class="flex w-full items-center justify-center gap-2 rounded-xl border border-red-200 py-2.5 text-sm font-medium text-red-600 hover:bg-red-50">
                <Ban class="h-4 w-4" :stroke-width="1.75" />
                Void Deduction Ini
              </button>

              <div v-else class="space-y-3">
                <div class="flex items-start gap-2 rounded-xl bg-amber-50 p-3 text-xs text-amber-700">
                  <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" :stroke-width="1.75" />
                  <p>Setelah di-void, record ini tidak bisa diedit lagi. Buat record baru kalau perlu koreksi.</p>
                </div>
                <textarea v-model="drawerVoidReason" rows="2" placeholder="Alasan void" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"></textarea>
                <div class="flex gap-2">
                  <button @click="showDrawerVoidForm = false" class="flex-1 rounded-xl border border-slate-200 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Batal</button>
                  <button @click="submitDrawerVoid" :disabled="!drawerVoidReason.trim() || bulkProcessing" class="flex-1 rounded-xl bg-red-600 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50">
                    Konfirmasi Void
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>