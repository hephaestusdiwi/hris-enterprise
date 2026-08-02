<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted, onUnmounted } from 'vue'
import {
  Plus, Trash2, X, Copy, Eye, Loader2, Search, Users, Wallet,
  UserPlus, SlidersHorizontal, ChevronDown, ChevronRight, MoreVertical,
  History, ScrollText, Clock, Info, Ban, FileEdit, Inbox,
} from 'lucide-vue-next'
import apiClient from '@/lib/axios'

// ==================================================
// TYPES
// ==================================================

interface Employee {
  id: number
  employee_number: string
  first_name: string
  last_name: string | null
  photo_url: string | null
  company_id: number
}

interface SalaryComponent {
  id: number
  name: string
  code: string
  company_id: number
  calculation_method: 'fixed' | 'percentage'
  amount: string | null
  percentage_value: string | null
}

interface OverrideRow {
  id: number
  override_amount: string | null
  override_percentage_value: string | null
  override_percentage_base: string | null
  salary_component: SalaryComponent
}

interface EmployeeSalaryRow {
  id: number
  employee_id: number
  salary_structure_code: string
  effective_date: string
  is_active: boolean
  employee: Employee
  overrides: OverrideRow[]
  // Optional — hanya tampil jika backend menyertakannya di response.
  created_at?: string
}

interface FormOverrideRow {
  salary_component_id: number | null
  override_amount: number | null
  override_percentage_value: number | null
  override_percentage_base: 'basic_salary' | 'gross_salary'
}

interface ResolvedLine {
  component: { id: number; name: string; category: string; is_addition: boolean }
  amount: string | null
  percentage_value: string | null
  percentage_base: string | null
  source: string
}

interface EmployeeGroup {
  employeeId: number
  employee: Employee
  current: EmployeeSalaryRow | null
  versions: EmployeeSalaryRow[]
  nextScheduled: EmployeeSalaryRow | null
}

// ==================================================
// HELPERS — FORMAT
// ==================================================

function employeeName(e: Employee) {
  return [e.first_name, e.last_name].filter(Boolean).join(' ')
}

function employeeInitials(e: Employee) {
  return [e.first_name, e.last_name]
    .filter((s): s is string => !!s)
    .map((s) => s[0])
    .join('')
    .slice(0, 2)
    .toUpperCase()
}

function formatDate(value: string | undefined | null) {
  if (!value) return '-'
  return new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

function formatCurrency(value: number | string | null | undefined) {
  if (value === null || value === undefined || Number.isNaN(Number(value))) return '-'
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(Number(value))
}

function formatSignedCurrency(value: number) {
  const formatted = formatCurrency(Math.abs(value))
  if (value > 0) return `+${formatted}`
  if (value < 0) return `-${formatted}`
  return formatted
}

function formatSignedPercentage(value: number) {
  const sign = value > 0 ? '+' : value < 0 ? '-' : ''
  return `${sign}${Math.abs(Number(value.toFixed(2)))}%`
}

function diffColorClass(value: number) {
  if (value > 0) return 'text-emerald-600'
  if (value < 0) return 'text-red-500'
  return 'text-slate-400'
}

const todayStr = new Date().toISOString().slice(0, 10)

function isFutureDate(date: string) {
  return date > todayStr
}

// ==================================================
// DATA LOADING
// ==================================================

const salaries = ref<EmployeeSalaryRow[]>([])
const employees = ref<Employee[]>([])
const salaryComponents = ref<SalaryComponent[]>([])
const structureCodes = ref<{ code: string; name: string; company_id: number }[]>([])
const loading = ref(true)
const errorMessage = ref('')
const previewLines = ref<ResolvedLine[]>([])
const previewLoading = ref(false)
let previewTimer: ReturnType<typeof setTimeout> | null = null
let previewRequestId = 0

async function loadSalaries() {
  loading.value = true
  errorMessage.value = ''
  try {
    // per_page diperbesar karena Summary Cards & filter butuh keseluruhan data,
    // bukan hanya satu halaman. Samakan konvensinya dengan fetch /api/employees di bawah.
    const response = await apiClient.get('/api/employee-salaries', { params: { per_page: 200 } })
    salaries.value = response.data.data.data
    warmResolvedCache()
  } catch {
    errorMessage.value = 'Gagal memuat daftar employee salary.'
  } finally {
    loading.value = false
  }
}

async function loadReferenceData() {
  const [employeeRes, componentRes, structureRes] = await Promise.all([
    apiClient.get('/api/employees', { params: { per_page: 100 } }),
    apiClient.get('/api/salary-components'),
    apiClient.get('/api/salary-structures'),
  ])
  employees.value = employeeRes.data.data.data
  salaryComponents.value = componentRes.data.data.data

  const seen = new Set<string>()
  structureCodes.value = structureRes.data.data.data
    .filter((s: any) => {
      const key = `${s.company_id}:${s.code}`
      if (seen.has(key)) return false
      seen.add(key)
      return true
    })
    .map((s: any) => ({ code: s.code, name: s.name, company_id: s.company_id }))
}

function structureName(row: EmployeeSalaryRow) {
  return structureCodes.value.find((s) => s.code === row.salary_structure_code && s.company_id === row.employee.company_id)?.name
    ?? row.salary_structure_code
}

function buildDraftOverrides() {
  return rows.value
    .filter((r) => r.salary_component_id)
    .map((r) => {
      const component = componentFor(r)
      const isPercentage = component?.calculation_method === 'percentage'
      return {
        salary_component_id: r.salary_component_id,
        override_amount: !isPercentage ? r.override_amount : null,
        override_percentage_value: isPercentage ? r.override_percentage_value : null,
        override_percentage_base: isPercentage ? r.override_percentage_base : null,
      }
    })
}

async function fetchPreview() {
  if (!form.employee_id || !form.salary_structure_code || !form.effective_date) {
    previewLines.value = []
    previewLoading.value = false
    return
  }

  const requestId = ++previewRequestId
  try {
    const response = await apiClient.post(`/api/employees/${form.employee_id}/resolved-salary/preview`, {
      salary_structure_code: form.salary_structure_code,
      effective_date: form.effective_date,
      overrides: buildDraftOverrides(),
    })
    if (requestId !== previewRequestId) return // ada request lebih baru nyusul, buang hasil basi ini
    previewLines.value = response.data.data
  } catch {
    if (requestId !== previewRequestId) return
    previewLines.value = []
  } finally {
    if (requestId === previewRequestId) previewLoading.value = false
  }
}

function schedulePreview() {
  previewLoading.value = true // spinner nyala LANGSUNG begitu ada perubahan, ga nunggu debounce selesai
  if (previewTimer) clearTimeout(previewTimer)
  previewTimer = setTimeout(fetchPreview, 300) // sedikit diturunin dari 400ms, masih cukup nahan spam
}

const structureFilterOptions = computed(() => {
  const seen = new Map<string, string>()
  for (const s of structureCodes.value) {
    if (!seen.has(s.code)) seen.set(s.code, s.name)
  }
  return Array.from(seen.entries()).map(([code, name]) => ({ code, name }))
})

// ==================================================
// GROUPING — satu baris per employee, versi lain masuk Version History
// ==================================================

const groupedEmployees = computed<EmployeeGroup[]>(() => {
  const rowsByEmployee = new Map<number, EmployeeSalaryRow[]>()
  for (const row of salaries.value) {
    if (!rowsByEmployee.has(row.employee_id)) rowsByEmployee.set(row.employee_id, [])
    rowsByEmployee.get(row.employee_id)!.push(row)
  }

  return employees.value.map((emp) => {
    const versions = [...(rowsByEmployee.get(emp.id) ?? [])].sort((a, b) => a.effective_date.localeCompare(b.effective_date))
    const pastOrToday = versions.filter((v) => v.effective_date <= todayStr)
    const future = versions.filter((v) => v.effective_date > todayStr)
    return {
      employeeId: emp.id,
      employee: emp,
      current: pastOrToday.length ? pastOrToday[pastOrToday.length - 1] : null,
      versions,
      nextScheduled: future.length ? future[0] : null,
    }
  })
})

// ==================================================
// SUMMARY CARDS
// ==================================================

const totalEmployeeCount = computed(() => employees.value.length)
const assignedCount = computed(() => groupedEmployees.value.filter((g) => g.current?.is_active).length)
const needAssignmentCount = computed(() => groupedEmployees.value.filter((g) => !g.current).length)
const personalOverrideCount = computed(() => groupedEmployees.value.filter((g) => g.current && g.current.overrides.length > 0).length)

// ==================================================
// FILTER
// ==================================================

const filters = reactive({
  search: '',
  structureCode: '',
  status: 'all' as 'all' | 'active' | 'inactive',
  overrideStatus: 'all' as 'all' | 'yes' | 'no',
  effectiveDate: '',
})

function resetFilters() {
  filters.search = ''
  filters.structureCode = ''
  filters.status = 'all'
  filters.overrideStatus = 'all'
  filters.effectiveDate = ''
}

const hasActiveFilters = computed(() =>
  !!(filters.search || filters.structureCode || filters.status !== 'all' || filters.overrideStatus !== 'all' || filters.effectiveDate),
)

const filteredGroups = computed(() => {
  return groupedEmployees.value.filter((g) => {
    if (filters.search && !employeeName(g.employee).toLowerCase().includes(filters.search.toLowerCase())) return false
    if (filters.structureCode && g.current?.salary_structure_code !== filters.structureCode) return false
    if (filters.status === 'active' && !g.current?.is_active) return false
    if (filters.status === 'inactive' && (!g.current || g.current.is_active)) return false
    if (filters.overrideStatus === 'yes' && !(g.current && g.current.overrides.length > 0)) return false
    if (filters.overrideStatus === 'no' && g.current && g.current.overrides.length > 0) return false
    if (filters.effectiveDate && g.current?.effective_date !== filters.effectiveDate) return false
    return true
  })
})

// ==================================================
// RESOLVED SALARY CACHE (dipakai bareng oleh: kolom Total Salary,
// Row Expansion, dan Drawer tab "Salary Components" / "Overview")
// ==================================================

const resolvedCache = reactive<Record<number, ResolvedLine[]>>({})
const resolvedLoading = reactive<Record<number, boolean>>({})

async function ensureResolved(row: EmployeeSalaryRow | null | undefined) {
  if (!row) return
  if (resolvedCache[row.id] || resolvedLoading[row.id]) return
  resolvedLoading[row.id] = true
  try {
    const response = await apiClient.get(`/api/employees/${row.employee_id}/resolved-salary`, {
      params: { date: row.effective_date },
    })
    resolvedCache[row.id] = response.data.data
  } catch {
    resolvedCache[row.id] = []
  } finally {
    resolvedLoading[row.id] = false
  }
}

async function warmResolvedCache() {
  const targets = groupedEmployees.value.map((g) => g.current).filter(Boolean) as EmployeeSalaryRow[]
  const chunkSize = 5
  for (let i = 0; i < targets.length; i += chunkSize) {
    await Promise.all(targets.slice(i, i + chunkSize).map((r) => ensureResolved(r)))
  }
}

const sourceLabels: Record<string, string> = {
  employee_override: 'Override Personal',
  structure: 'Salary Structure',
  component_default: 'Default Global',
}

const sourceBadgeClass: Record<string, string> = {
  employee_override: 'bg-amber-50 text-amber-600',
  structure: 'bg-blue-50 text-blue-600',
  component_default: 'bg-slate-100 text-slate-500',
}

function resolvedLineValue(line: ResolvedLine) {
  if (line.amount !== null) return Number(line.amount)
  if (line.percentage_value !== null) return null // ditampilkan sebagai % pada UI, bukan nominal
  return 0
}

function resolvedLineDefault(line: ResolvedLine) {
  const master = salaryComponents.value.find((c) => c.id === line.component.id)
  if (!master) return null
  return master.calculation_method === 'percentage' ? master.percentage_value : master.amount
}

// Estimasi Gross/Net — HANYA untuk tampilan informasi (Drawer Overview),
// bukan angka payroll final. Lihat catatan di respons chat soal batasan ini.
function computeSalarySummary(lines: ResolvedLine[] | undefined) {
  if (!lines || lines.length === 0) return null
  const basicLine = lines.find((l) => l.component.category === 'basic_salary')
  const basicValue = basicLine?.amount ? Number(basicLine.amount) : 0

  let fixedAdd = 0
  let fixedDed = 0
  for (const l of lines) {
    if (l.amount !== null) {
      const v = Number(l.amount)
      if (l.component.is_addition) fixedAdd += v
      else fixedDed += v
    }
  }
  const approxGross = basicValue + fixedAdd - fixedDed

  let pctAdd = 0
  let pctDed = 0
  for (const l of lines) {
    if (l.percentage_value !== null) {
      const base = l.percentage_base === 'gross_salary' ? approxGross : basicValue
      const v = (Number(l.percentage_value) / 100) * base
      if (l.component.is_addition) pctAdd += v
      else pctDed += v
    }
  }

  const gross = fixedAdd + pctAdd
  const net = gross - fixedDed - pctDed
  return { gross, net, isEstimate: true }
}

// ==================================================
// OVERRIDE DIFF (untuk badge tabel, tooltip, row expansion, drawer)
// ==================================================

function overrideDiff(o: OverrideRow) {
  const comp = o.salary_component
  const isPercentage = comp.calculation_method === 'percentage'
  const defaultVal = Number((isPercentage ? comp.percentage_value : comp.amount) ?? 0)
  const overrideVal = Number((isPercentage ? o.override_percentage_value : o.override_amount) ?? defaultVal)
  return { isPercentage, defaultVal, overrideVal, diff: overrideVal - defaultVal }
}

// ==================================================
// ROW EXPANSION
// ==================================================

const expandedEmployeeId = ref<number | null>(null)

function toggleExpand(group: EmployeeGroup) {
  if (!group.current) return
  expandedEmployeeId.value = expandedEmployeeId.value === group.employeeId ? null : group.employeeId
  if (expandedEmployeeId.value) ensureResolved(group.current)
}

// ==================================================
// ACTION DROPDOWN
// ==================================================

const openActionMenuId = ref<number | null>(null)

function toggleActionMenu(employeeId: number) {
  openActionMenuId.value = openActionMenuId.value === employeeId ? null : employeeId
}

function closeActionMenu() {
  openActionMenuId.value = null
}

onMounted(() => {
  document.addEventListener('click', closeActionMenu)
})
onUnmounted(() => {
  document.removeEventListener('click', closeActionMenu)
})

// ==================================================
// DRAWER DETAIL
// ==================================================

type DrawerTab = 'overview' | 'components' | 'override' | 'history' | 'audit'

const showDrawer = ref(false)
const drawerGroup = ref<EmployeeGroup | null>(null)
const drawerTab = ref<DrawerTab>('overview')

const drawerTabs: { id: DrawerTab; label: string; icon: any }[] = [
  { id: 'overview', label: 'Overview', icon: Info },
  { id: 'components', label: 'Salary Components', icon: Wallet },
  { id: 'override', label: 'Override', icon: SlidersHorizontal },
  { id: 'history', label: 'Version History', icon: History },
  { id: 'audit', label: 'Audit Log', icon: ScrollText },
]

function openDrawer(group: EmployeeGroup, tab: DrawerTab = 'overview') {
  drawerGroup.value = group
  drawerTab.value = tab
  showDrawer.value = true
  closeActionMenu()
  if (group.current) ensureResolved(group.current)
}

function closeDrawer() {
  showDrawer.value = false
}

// ==================================================
// CREATE / NEW VERSION (DUPLICATE) MODAL
// ==================================================

const showModal = ref(false)
const modalTitle = ref('Assign Salary')
const lockEmployee = ref(false)
const saving = ref(false)
const formError = ref('')

const form = reactive({
  employee_id: null as number | null,
  salary_structure_code: '',
  effective_date: '',
  is_active: true,
})

const rows = ref<FormOverrideRow[]>([])

watch(
  () => [form.employee_id, form.salary_structure_code, form.effective_date, JSON.stringify(rows.value)],
  () => { if (showModal.value) schedulePreview() },
)

const selectedEmployee = computed(() => employees.value.find((e) => e.id === form.employee_id) ?? null)
const availableStructureCodes = computed(() =>
  structureCodes.value.filter((s) => s.company_id === selectedEmployee.value?.company_id),
)
const availableComponents = computed(() =>
  salaryComponents.value.filter((c) => c.company_id === selectedEmployee.value?.company_id),
)

function resetForm() {
  form.employee_id = employees.value[0]?.id ?? null
  form.salary_structure_code = ''
  form.effective_date = new Date().toISOString().slice(0, 10)
  form.is_active = true
  rows.value = []
}

function openCreateModal() {
  modalTitle.value = 'Assign Salary'
  lockEmployee.value = false
  formError.value = ''
  resetForm()
  showModal.value = true
}

function openNewVersion(row: EmployeeSalaryRow) {
  modalTitle.value = `Duplicate Assignment — ${employeeName(row.employee)}`
  lockEmployee.value = true
  formError.value = ''
  form.employee_id = row.employee_id
  form.salary_structure_code = row.salary_structure_code
  form.effective_date = ''
  form.is_active = true

  rows.value = row.overrides.map((o) => ({
    salary_component_id: o.salary_component.id,
    override_amount: o.override_amount !== null ? Number(o.override_amount) : null,
    override_percentage_value: o.override_percentage_value !== null ? Number(o.override_percentage_value) : null,
    override_percentage_base: (o.override_percentage_base as any) ?? 'basic_salary',
  }))

  closeActionMenu()
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  previewLines.value = []
  previewLoading.value = false
  previewRequestId++
  if (previewTimer) clearTimeout(previewTimer)
}

function addRow() {
  rows.value.push({
    salary_component_id: null,
    override_amount: null,
    override_percentage_value: null,
    override_percentage_base: 'basic_salary',
  })
}

function removeRow(index: number) {
  rows.value.splice(index, 1)
}

function componentFor(row: FormOverrideRow): SalaryComponent | null {
  return salaryComponents.value.find((c) => c.id === row.salary_component_id) ?? null
}

function formOverrideDiff(row: FormOverrideRow) {
  const comp = componentFor(row)
  if (!comp) return null
  const isPercentage = comp.calculation_method === 'percentage'
  const defaultVal = Number((isPercentage ? comp.percentage_value : comp.amount) ?? 0)
  const overrideVal = Number((isPercentage ? row.override_percentage_value : row.override_amount) ?? defaultVal)
  return { isPercentage, defaultVal, overrideVal, diff: overrideVal - defaultVal }
}

// Total adjustment nominal (hanya komponen fixed — lihat catatan di chat kenapa
// tidak dijumlah dengan komponen persentase / tidak dihitung sebagai Estimasi Gross-Net).
const formNominalAdjustmentTotal = computed(() => {
  let total = 0
  for (const row of rows.value) {
    const d = formOverrideDiff(row)
    if (d && !d.isPercentage) total += d.diff
  }
  return total
})

const formPercentageEntries = computed(() =>
  rows.value
    .map((row) => ({ row, comp: componentFor(row), diff: formOverrideDiff(row) }))
    .filter((x) => x.comp && x.diff && x.diff.isPercentage),
)

async function handleSubmit() {
  formError.value = ''
  saving.value = true

  const payload = {
    employee_id: form.employee_id,
    salary_structure_code: form.salary_structure_code,
    effective_date: form.effective_date,
    is_active: form.is_active,
    overrides: rows.value
      .filter((r) => r.salary_component_id)
      .map((r) => {
        const component = componentFor(r)
        const isPercentage = component?.calculation_method === 'percentage'

        return {
          salary_component_id: r.salary_component_id,
          override_amount: !isPercentage ? r.override_amount : null,
          override_percentage_value: isPercentage ? r.override_percentage_value : null,
          override_percentage_base: isPercentage ? r.override_percentage_base : null,
        }
      }),
  }

  try {
    await apiClient.post('/api/employee-salaries', payload)
    showModal.value = false
    await loadSalaries()
  } catch (err: any) {
    formError.value = err.response?.data?.message || 'Terjadi kesalahan, silakan coba lagi.'
  } finally {
    saving.value = false
  }
}

async function handleDelete(row: EmployeeSalaryRow) {
  if (!confirm(`Hapus employee salary "${employeeName(row.employee)}" (efektif ${formatDate(row.effective_date)})? Tindakan ini permanen.`)) return

  try {
    await apiClient.delete(`/api/employee-salaries/${row.id}`)
    closeActionMenu()
    if (drawerGroup.value?.employeeId === row.employee_id) closeDrawer()
    await loadSalaries()
  } catch (err: any) {
    alert(err.response?.data?.message || 'Gagal menghapus employee salary.')
  }
}

onMounted(async () => {
  await Promise.all([loadSalaries(), loadReferenceData()])
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Employee Salary</h1>
        <p class="mt-1 text-sm text-slate-500">Assignment gaji per employee, ter-versi berdasarkan Effective Date.</p>
      </div>
      <button
        @click="openCreateModal"
        :disabled="employees.length === 0"
        class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-dark disabled:opacity-50"
      >
        <Plus class="h-4 w-4" :stroke-width="2" />
        Assign Salary
      </button>
    </div>

    <!-- SUMMARY CARDS -->
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
      <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <div class="flex items-center gap-3">
          <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-500">
            <Users class="h-5 w-5" :stroke-width="1.75" />
          </div>
          <div>
            <p class="text-xs font-medium text-slate-400">Total Employee</p>
            <p class="text-xl font-semibold text-slate-900">{{ totalEmployeeCount }}</p>
          </div>
        </div>
      </div>

      <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <div class="flex items-center gap-3">
          <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-primary-dark">
            <Wallet class="h-5 w-5" :stroke-width="1.75" />
          </div>
          <div>
            <p class="text-xs font-medium text-slate-400">Salary Assigned</p>
            <p class="text-xl font-semibold text-slate-900">{{ assignedCount }}</p>
          </div>
        </div>
      </div>

      <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <div class="flex items-center gap-3">
          <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
            <UserPlus class="h-5 w-5" :stroke-width="1.75" />
          </div>
          <div>
            <p class="text-xs font-medium text-slate-400">Need Assignment</p>
            <p class="text-xl font-semibold text-slate-900">{{ needAssignmentCount }}</p>
          </div>
        </div>
      </div>

      <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <div class="flex items-center gap-3">
          <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
            <SlidersHorizontal class="h-5 w-5" :stroke-width="1.75" />
          </div>
          <div>
            <p class="text-xs font-medium text-slate-400">Personal Override</p>
            <p class="text-xl font-semibold text-slate-900">{{ personalOverrideCount }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- FILTER -->
    <div class="rounded-2xl border border-slate-100 bg-white p-4">
      <div class="flex flex-wrap items-end gap-3">
        <div class="min-w-[200px] flex-1">
          <label class="mb-1 block text-xs font-medium text-slate-500">Search Employee</label>
          <div class="relative">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-300" :stroke-width="1.75" />
            <input
              v-model="filters.search"
              type="text"
              placeholder="Cari nama employee..."
              class="w-full rounded-xl border border-slate-200 py-2 pl-9 pr-3 text-sm focus:border-primary focus:outline-none"
            />
          </div>
        </div>

        <div>
          <label class="mb-1 block text-xs font-medium text-slate-500">Salary Structure</label>
          <select v-model="filters.structureCode" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
            <option value="">Semua Structure</option>
            <option v-for="s in structureFilterOptions" :key="s.code" :value="s.code">{{ s.name }}</option>
          </select>
        </div>

        <div>
          <label class="mb-1 block text-xs font-medium text-slate-500">Status</label>
          <select v-model="filters.status" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
            <option value="all">Semua Status</option>
            <option value="active">Aktif</option>
            <option value="inactive">Nonaktif</option>
          </select>
        </div>

        <div>
          <label class="mb-1 block text-xs font-medium text-slate-500">Has Override</label>
          <select v-model="filters.overrideStatus" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
            <option value="all">Semua</option>
            <option value="yes">Ada Override</option>
            <option value="no">Tanpa Override</option>
          </select>
        </div>

        <div>
          <label class="mb-1 block text-xs font-medium text-slate-500">Effective Date</label>
          <input v-model="filters.effectiveDate" type="date" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
        </div>

        <button
          v-if="hasActiveFilters"
          @click="resetFilters"
          class="rounded-xl px-3 py-2 text-sm font-medium text-slate-400 hover:text-slate-600"
        >
          Reset
        </button>
      </div>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>

    <!-- EMPTY STATE -->
    <div v-else-if="groupedEmployees.length === 0" class="flex flex-col items-center gap-3 rounded-2xl border border-slate-100 bg-white px-6 py-16 text-center">
      <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50 text-slate-300">
        <Inbox class="h-7 w-7" :stroke-width="1.5" />
      </div>
      <h3 class="text-base font-semibold text-slate-800">Belum ada Employee Salary.</h3>
      <p class="max-w-sm text-sm text-slate-400">Assign salary pertama untuk mulai proses payroll.</p>
      <button
        @click="openCreateModal"
        class="mt-1 flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-dark"
      >
        <Plus class="h-4 w-4" :stroke-width="2" />
        Assign Salary
      </button>
    </div>

    <div v-else-if="filteredGroups.length === 0" class="rounded-2xl border border-slate-100 bg-white px-6 py-12 text-center text-sm text-slate-400">
      Tidak ada employee yang cocok dengan filter saat ini.
    </div>

    <!-- TABLE -->
    <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <table class="w-full text-left text-sm">
        <thead>
          <tr class="border-b border-slate-100 bg-slate-50/60">
            <th class="w-8 px-3 py-3"></th>
            <th class="px-3 py-3 font-medium text-slate-500">Employee</th>
            <th class="px-3 py-3 font-medium text-slate-500">Salary Package</th>
            <th class="px-3 py-3 font-medium text-slate-500">Total Salary</th>
            <th class="px-3 py-3 font-medium text-slate-500">Effective Date</th>
            <th class="px-3 py-3 font-medium text-slate-500">Next Scheduled Change</th>
            <th class="px-3 py-3 font-medium text-slate-500">Override</th>
            <th class="px-3 py-3 font-medium text-slate-500">Status</th>
            <th class="px-3 py-3 text-right font-medium text-slate-500">Action</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="group in filteredGroups" :key="group.employeeId">
            <tr
              class="border-b border-slate-50 last:border-0 hover:bg-slate-50/50"
              :class="group.current ? 'cursor-pointer' : ''"
              @click="toggleExpand(group)"
            >
              <td class="px-3 py-3.5 text-slate-300">
                <ChevronDown v-if="group.current && expandedEmployeeId === group.employeeId" class="h-4 w-4" :stroke-width="1.75" />
                <ChevronRight v-else-if="group.current" class="h-4 w-4" :stroke-width="1.75" />
              </td>

              <td class="px-3 py-3.5">
                <div class="flex items-center gap-2.5">
                  <img
                    v-if="group.employee?.photo_url"
                    :src="group.employee.photo_url"
                    alt=""
                    class="h-8 w-8 shrink-0 rounded-full object-cover"
                  />

                  <div
                    v-else
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-soft text-xs font-semibold text-primary-dark"
                  >
                    {{ employeeInitials(group.employee) }}
                  </div>

                  <div>
                    <p class="font-medium text-slate-800">
                      {{ employeeName(group.employee) }}
                    </p>

                    <p
                      v-if="group.employee?.employee_number"
                      class="text-xs text-slate-400"
                    >
                      {{ group.employee.employee_number }}
                    </p>
                  </div>
                </div>
              </td>

              <td class="px-3 py-3.5">
                <div v-if="group.current">
                  <p class="font-medium text-slate-700">{{ structureName(group.current) }}</p>
                  <p class="text-xs text-slate-400">
                    {{ group.current.salary_structure_code }}
                    <template v-if="resolvedCache[group.current.id]"> · {{ resolvedCache[group.current.id].length }} komponen</template>
                  </p>
                </div>
                <span v-else class="text-slate-300">-</span>
              </td>

              <td class="px-3 py-3.5">
                <template v-if="group.current">
                  <Loader2 v-if="resolvedLoading[group.current.id]" class="h-4 w-4 animate-spin text-slate-300" :stroke-width="2" />
                  <template v-else-if="computeSalarySummary(resolvedCache[group.current.id])">
                    <div class="group/total relative inline-block">
                      <p class="font-medium text-slate-700">
                        {{ formatCurrency(computeSalarySummary(resolvedCache[group.current.id])!.gross) }}
                        <span class="text-xs font-normal text-slate-300">est.</span>
                      </p>
                      <div class="pointer-events-none absolute left-0 top-full z-10 mt-1 hidden w-56 rounded-xl border border-slate-100 bg-white p-3 text-xs shadow-lg group-hover/total:block">
                        <p class="mb-1 flex items-center gap-1 font-medium text-slate-500"><Info class="h-3.5 w-3.5" :stroke-width="1.75" /> Estimasi</p>
                        <p class="text-slate-500">Dihitung dari data resolved, bukan hasil akhir mesin payroll.</p>
                      </div>
                    </div>
                  </template>
                  <span v-else class="text-slate-300">-</span>
                </template>
                <span v-else class="text-slate-300">-</span>
              </td>

              <td class="px-3 py-3.5 text-slate-500">{{ group.current ? formatDate(group.current.effective_date) : '-' }}</td>

              <td class="px-3 py-3.5">
                <span v-if="group.nextScheduled" class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-600">
                  <Clock class="h-3 w-3" :stroke-width="2" />
                  {{ formatDate(group.nextScheduled.effective_date) }}
                </span>
                <span v-else class="text-slate-300">-</span>
              </td>

              <td class="px-3 py-3.5">
                <div v-if="group.current && group.current.overrides.length > 0" class="group/ov relative inline-block">
                  <span class="cursor-default rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-600">
                    {{ group.current.overrides.length }} Components
                  </span>
                  <div class="pointer-events-none absolute left-0 top-full z-10 mt-1 hidden w-64 space-y-1.5 rounded-xl border border-slate-100 bg-white p-3 text-xs shadow-lg group-hover/ov:block">
                    <div v-for="o in group.current.overrides" :key="o.id" class="flex items-center justify-between gap-2">
                      <span class="text-slate-500">{{ o.salary_component.name }}</span>
                      <span :class="diffColorClass(overrideDiff(o).diff)" class="font-medium">
                        {{ overrideDiff(o).isPercentage ? formatSignedPercentage(overrideDiff(o).diff) : formatSignedCurrency(overrideDiff(o).diff) }}
                      </span>
                    </div>
                  </div>
                </div>
                <span v-else-if="group.current" class="rounded-full bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-400">No Override</span>
                <span v-else class="text-slate-300">-</span>
              </td>

              <td class="px-3 py-3.5">
                <span v-if="!group.current" class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-600">Belum Ditugaskan</span>
                <span v-else class="rounded-full px-2.5 py-1 text-xs font-medium" :class="group.current.is_active ? 'bg-primary-soft text-primary-dark' : 'bg-slate-50 text-slate-400'">
                  {{ group.current.is_active ? 'Aktif' : 'Nonaktif' }}
                </span>
              </td>

              <td class="px-3 py-3.5 text-right" @click.stop>
                <button
                  v-if="!group.current"
                  @click="openCreateModal(); form.employee_id = group.employeeId"
                  class="rounded-lg px-3 py-1.5 text-xs font-medium text-primary-dark hover:bg-primary-soft"
                >
                  Assign Salary
                </button>
                <div v-else class="relative inline-block text-left">
                  <button @click="toggleActionMenu(group.employeeId)" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                    <MoreVertical class="h-4 w-4" :stroke-width="1.75" />
                  </button>

                  <div
                    v-if="openActionMenuId === group.employeeId"
                    class="absolute right-0 z-20 mt-1 w-52 overflow-hidden rounded-xl border border-slate-100 bg-white py-1 shadow-lg"
                  >
                    <button @click="openDrawer(group, 'overview')" class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm text-slate-600 hover:bg-slate-50">
                      <Eye class="h-4 w-4 text-slate-400" :stroke-width="1.75" /> View Detail
                    </button>
                    <button
                      class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm text-slate-300"
                      title="Butuh endpoint update di backend"
                      disabled
                    >
                      <FileEdit class="h-4 w-4" :stroke-width="1.75" /> Edit Assignment
                      <span class="ml-auto rounded bg-slate-50 px-1.5 py-0.5 text-[10px] text-slate-400">Segera</span>
                    </button>
                    <button @click="openNewVersion(group.current)" class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm text-slate-600 hover:bg-slate-50">
                      <Copy class="h-4 w-4 text-slate-400" :stroke-width="1.75" /> Duplicate Assignment
                    </button>
                    <button @click="openDrawer(group, 'history')" class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm text-slate-600 hover:bg-slate-50">
                      <History class="h-4 w-4 text-slate-400" :stroke-width="1.75" /> Version History
                    </button>
                    <div class="my-1 border-t border-slate-50"></div>
                    <button
                      class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm text-slate-300"
                      title="Butuh endpoint deactivate terpisah di backend"
                      disabled
                    >
                      <Ban class="h-4 w-4" :stroke-width="1.75" /> Deactivate
                      <span class="ml-auto rounded bg-slate-50 px-1.5 py-0.5 text-[10px] text-slate-400">Segera</span>
                    </button>
                    <button @click="handleDelete(group.current)" class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm text-red-500 hover:bg-red-50">
                      <Trash2 class="h-4 w-4" :stroke-width="1.75" /> Hapus Assignment
                    </button>
                  </div>
                </div>
              </td>
            </tr>

            <!-- ROW EXPANSION -->
            <tr v-if="group.current && expandedEmployeeId === group.employeeId" class="border-b border-slate-50 bg-slate-50/40">
              <td colspan="9" class="px-6 py-4">
                <div class="mb-3 flex items-center justify-between">
                  <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">
                    Salary Structure — {{ structureName(group.current) }}
                  </p>
                  <button @click="openDrawer(group, 'components')" class="text-xs font-medium text-primary-dark hover:underline">
                    Buka detail lengkap →
                  </button>
                </div>

                <div v-if="resolvedLoading[group.current.id]" class="flex items-center gap-2 py-4 text-sm text-slate-400">
                  <Loader2 class="h-4 w-4 animate-spin" :stroke-width="2" /> Memuat komponen...
                </div>

                <div v-else-if="(resolvedCache[group.current.id]?.length ?? 0) === 0" class="py-2 text-sm text-slate-400">
                  Tidak ada komponen ter-resolve.
                </div>

                <div v-else class="overflow-hidden rounded-xl border border-slate-100 bg-white">
                  <table class="w-full text-left text-xs">
                    <thead>
                      <tr class="border-b border-slate-100 bg-slate-50/60 text-slate-400">
                        <th class="px-4 py-2 font-medium">Komponen</th>
                        <th class="px-4 py-2 font-medium">Nilai Default</th>
                        <th class="px-4 py-2 font-medium">Sumber</th>
                        <th class="px-4 py-2 font-medium">Nilai Akhir</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="line in resolvedCache[group.current.id]" :key="line.component.id" class="border-b border-slate-50 last:border-0">
                        <td class="px-4 py-2.5 text-slate-700">{{ line.component.name }}</td>
                        <td class="px-4 py-2.5 text-slate-400">
                          {{ line.percentage_value !== null ? `${resolvedLineDefault(line) ?? '-'}%` : formatCurrency(resolvedLineDefault(line)) }}
                        </td>
                        <td class="px-4 py-2.5">
                          <span class="rounded-full px-2 py-0.5 font-medium" :class="sourceBadgeClass[line.source]">{{ sourceLabels[line.source] ?? line.source }}</span>
                        </td>
                        <td class="px-4 py-2.5 font-medium" :class="line.component.is_addition ? 'text-emerald-600' : 'text-red-500'">
                          {{ line.component.is_addition ? '+' : '-' }}
                          {{ line.percentage_value !== null ? `${line.percentage_value}%` : formatCurrency(resolvedLineValue(line)) }}
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <!-- ============================================== -->
    <!-- ASSIGN SALARY MODAL -->
    <!-- ============================================== -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8">
        <div class="flex max-h-full w-full max-w-4xl flex-col rounded-2xl bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">{{ modalTitle }}</h2>
            <button @click="closeModal" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50">
              <X class="h-5 w-5" />
            </button>
          </div>

          <form @submit.prevent="handleSubmit" class="flex-1 overflow-y-auto px-6 py-5">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_300px]">
              <div class="space-y-6">
                <!-- SECTION 1 -->
                <div>
                  <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Assignment Information</h3>
                  <div class="grid grid-cols-2 gap-3">
                    <div class="col-span-2">
                      <label class="mb-1 block text-sm font-medium text-slate-700">Employee</label>
                      <select v-model.number="form.employee_id" required :disabled="lockEmployee" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none disabled:bg-slate-50 disabled:text-slate-400">
                        <option v-for="e in employees" :key="e.id" :value="e.id">{{ employeeName(e) }}</option>
                      </select>
                    </div>
                    <div>
                      <label class="mb-1 block text-sm font-medium text-slate-700">Salary Structure</label>
                      <select v-model="form.salary_structure_code" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                        <option value="" disabled>Pilih salary structure</option>
                        <option v-for="s in availableStructureCodes" :key="s.code" :value="s.code">{{ s.name }} ({{ s.code }})</option>
                      </select>
                      <p v-if="availableStructureCodes.length === 0 && selectedEmployee" class="mt-1 text-xs text-amber-600">
                        Belum ada Salary Structure untuk company employee ini.
                      </p>
                    </div>
                    <div>
                      <label class="mb-1 block text-sm font-medium text-slate-700">Effective Date</label>
                      <input v-model="form.effective_date" type="date" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
                    </div>
                    <label class="col-span-2 flex cursor-pointer items-center justify-between rounded-xl border border-slate-200 px-4 py-3">
                      <p class="text-sm font-medium text-slate-700">Aktif</p>
                      <input v-model="form.is_active" type="checkbox" class="peer sr-only" />
                      <div class="relative h-6 w-11 shrink-0 rounded-full bg-slate-200 transition-colors after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow-sm after:transition-transform peer-checked:bg-primary peer-checked:after:translate-x-5"></div>
                    </label>
                  </div>
                </div>

                <!-- SECTION 2 -->
                <div>
                  <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Personal Override (opsional)</h3>
                    <button type="button" @click="addRow" class="flex items-center gap-1.5 rounded-lg bg-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-primary-dark">
                      <Plus class="h-3.5 w-3.5" :stroke-width="2" />
                      Add Override
                    </button>
                  </div>

                  <div v-if="rows.length === 0" class="rounded-xl bg-slate-50 p-4 text-center text-xs text-slate-400">
                    Tidak ada override personal — employee mengikuti Salary Structure apa adanya.
                  </div>

                  <div v-else class="space-y-2">
                    <div v-for="(row, index) in rows" :key="index" class="rounded-xl border border-slate-200 p-3">
                      <div class="flex items-start gap-2">
                        <div class="flex-1 space-y-2">
                          <select v-model.number="row.salary_component_id" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                            <option :value="null" disabled>Pilih komponen</option>
                            <option v-for="c in availableComponents" :key="c.id" :value="c.id">{{ c.name }} ({{ c.code }})</option>
                          </select>

                          <div v-if="componentFor(row)" class="grid grid-cols-2 gap-2">
                            <template v-if="componentFor(row)!.calculation_method === 'fixed'">
                              <div class="col-span-2">
                                <label class="mb-0.5 block text-xs text-slate-500">
                                  Override Nominal (kosongkan = default {{ formatCurrency(componentFor(row)!.amount) }})
                                </label>
                                <input v-model.number="row.override_amount" type="number" min="0" placeholder="Kosongkan = default" class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-sm focus:border-primary focus:outline-none" />
                              </div>
                            </template>
                            <template v-else>
                              <div>
                                <label class="mb-0.5 block text-xs text-slate-500">
                                  Override % (default {{ componentFor(row)!.percentage_value }}%)
                                </label>
                                <input v-model.number="row.override_percentage_value" type="number" min="0" max="100" step="0.01" placeholder="Kosongkan = default" class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-sm focus:border-primary focus:outline-none" />
                              </div>
                              <div>
                                <label class="mb-0.5 block text-xs text-slate-500">Basis %</label>
                                <select v-model="row.override_percentage_base" class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-sm focus:border-primary focus:outline-none">
                                  <option value="basic_salary">Gaji Pokok</option>
                                  <option value="gross_salary">Gaji Kotor</option>
                                </select>
                              </div>
                            </template>
                          </div>

                          <div v-if="formOverrideDiff(row)" class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-1.5 text-xs">
                            <span class="text-slate-400">
                              Default {{ formOverrideDiff(row)!.isPercentage ? `${formOverrideDiff(row)!.defaultVal}%` : formatCurrency(formOverrideDiff(row)!.defaultVal) }}
                            </span>
                            <span class="font-medium" :class="diffColorClass(formOverrideDiff(row)!.diff)">
                              {{ formOverrideDiff(row)!.isPercentage ? formatSignedPercentage(formOverrideDiff(row)!.diff) : formatSignedCurrency(formOverrideDiff(row)!.diff) }}
                            </span>
                          </div>
                        </div>

                        <button type="button" @click="removeRow(index)" class="mt-1 shrink-0 rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-500">
                          <Trash2 class="h-4 w-4" :stroke-width="1.75" />
                        </button>
                      </div>
                    </div>
                  </div>
                </div>

                <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>
              </div>

              <!-- SECTION 3 — SALARY PREVIEW -->
              <div class="lg:border-l lg:border-slate-100 lg:pl-6">
                <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Salary Preview</h3>
                <div class="space-y-3 rounded-xl bg-slate-50 p-4">
                  <div>
                    <p class="text-xs text-slate-400">Salary Structure</p>
                    <p class="text-sm font-medium text-slate-800">
                      {{ form.salary_structure_code ? (availableStructureCodes.find(s => s.code === form.salary_structure_code)?.name ?? form.salary_structure_code) : '-' }}
                    </p>
                  </div>

                  <div class="border-t border-slate-200 pt-3">
                    <p class="mb-2 text-xs text-slate-400">Personal Override</p>
                    <div v-if="rows.length === 0" class="text-xs text-slate-300">Belum ada override</div>
                    <div v-else class="space-y-2">
                      <div v-for="(row, index) in rows" :key="index" class="flex items-center justify-between text-xs">
                        <span class="truncate pr-2 text-slate-500">{{ componentFor(row)?.name ?? '-' }}</span>
                        <template v-if="formOverrideDiff(row)">
                          <span
                            v-if="formOverrideDiff(row)!.diff === 0"
                            class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 font-medium text-slate-400"
                          >
                            {{ formOverrideDiff(row)!.isPercentage ? `${formOverrideDiff(row)!.defaultVal}%` : formatCurrency(formOverrideDiff(row)!.defaultVal) }} (default)
                          </span>
                          <span v-else class="shrink-0 font-medium" :class="diffColorClass(formOverrideDiff(row)!.diff)">
                            {{ formOverrideDiff(row)!.isPercentage ? formatSignedPercentage(formOverrideDiff(row)!.diff) : formatSignedCurrency(formOverrideDiff(row)!.diff) }}
                          </span>
                        </template>
                      </div>
                    </div>
                  </div>

                  <div class="border-t border-slate-200 pt-3">
                    <div class="flex items-center justify-between">
                      <p class="text-xs font-medium text-slate-500">Total Adjustment (nominal)</p>
                      <p class="text-sm font-semibold" :class="diffColorClass(formNominalAdjustmentTotal)">{{ formatSignedCurrency(formNominalAdjustmentTotal) }}</p>
                    </div>
                    <p v-if="formPercentageEntries.length > 0" class="mt-1 text-[11px] text-slate-400">
                      + {{ formPercentageEntries.length }} override berbasis persentase (lihat di atas)
                    </p>
                  </div>

                  <div class="border-t border-slate-200 pt-3">
                    <div class="mb-2 flex items-center justify-between">
                      <p class="text-xs font-medium text-slate-500">Estimasi Gross / Net</p>
                      <Loader2 v-if="previewLoading" class="h-3.5 w-3.5 animate-spin text-slate-300" :stroke-width="2" />
                    </div>
                    <template v-if="computeSalarySummary(previewLines)">
                      <div class="flex items-center justify-between text-sm">
                        <span class="text-slate-400">Gross</span>
                        <span class="font-semibold text-slate-800">{{ formatCurrency(computeSalarySummary(previewLines)!.gross) }}</span>
                      </div>
                      <div class="mt-1 flex items-center justify-between text-sm">
                        <span class="text-slate-400">Net</span>
                        <span class="font-semibold text-slate-800">{{ formatCurrency(computeSalarySummary(previewLines)!.net) }}</span>
                      </div>
                    </template>
                    <p v-else class="text-xs text-slate-300">Lengkapi structure &amp; effective date untuk melihat estimasi.</p>
                  </div>

                  <p class="flex items-start gap-1.5 text-[11px] text-slate-400">
                    <Info class="mt-0.5 h-3 w-3 shrink-0" :stroke-width="1.75" />
                    Estimasi dari hasil resolve draft — bukan output final payroll engine.
                  </p>
                </div>
              </div>
            </div>
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

    <!-- ============================================== -->
    <!-- DRAWER DETAIL -->
    <!-- ============================================== -->
    <Teleport to="body">
      <Transition name="drawer">
        <div v-if="showDrawer" class="fixed inset-0 z-50 flex justify-end bg-slate-900/30">
          <div class="flex h-full w-full max-w-md flex-col bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
              <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-primary-soft text-xs font-semibold text-primary-dark">
                  {{ drawerGroup ? employeeInitials(drawerGroup.employee) : '' }}
                </div>
                <h2 class="text-base font-semibold text-slate-900">{{ drawerGroup ? employeeName(drawerGroup.employee) : '' }}</h2>
              </div>
              <button @click="closeDrawer" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50">
                <X class="h-5 w-5" />
              </button>
            </div>

            <div class="flex gap-1 overflow-x-auto border-b border-slate-100 px-4 pt-2">
              <button
                v-for="tab in drawerTabs"
                :key="tab.id"
                @click="drawerTab = tab.id"
                class="flex shrink-0 items-center gap-1.5 rounded-t-lg px-3 py-2 text-xs font-medium transition-colors"
                :class="drawerTab === tab.id ? 'border-b-2 border-primary text-primary-dark' : 'text-slate-400 hover:text-slate-600'"
              >
                <component :is="tab.icon" class="h-3.5 w-3.5" :stroke-width="1.75" />
                {{ tab.label }}
              </button>
            </div>

            <div v-if="drawerGroup" class="flex-1 overflow-y-auto px-6 py-5">
              <!-- OVERVIEW -->
              <div v-if="drawerTab === 'overview'" class="space-y-4">
                <div v-if="!drawerGroup.current" class="rounded-xl bg-amber-50 p-4 text-sm text-amber-700">
                  Employee ini belum memiliki salary assignment.
                </div>
                <template v-else>
                  <div class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                      <p class="text-xs text-slate-400">Salary Structure</p>
                      <p class="font-medium text-slate-800">{{ structureName(drawerGroup.current) }}</p>
                    </div>
                    <div>
                      <p class="text-xs text-slate-400">Effective Date</p>
                      <p class="font-medium text-slate-800">{{ formatDate(drawerGroup.current.effective_date) }}</p>
                    </div>
                    <div>
                      <p class="text-xs text-slate-400">Status</p>
                      <p class="font-medium text-slate-800">{{ drawerGroup.current.is_active ? 'Aktif' : 'Nonaktif' }}</p>
                    </div>
                    <div v-if="drawerGroup.nextScheduled">
                      <p class="text-xs text-slate-400">Next Scheduled Change</p>
                      <p class="font-medium text-blue-600">{{ formatDate(drawerGroup.nextScheduled.effective_date) }}</p>
                    </div>
                  </div>

                  <div v-if="drawerGroup.nextScheduled" class="flex items-center gap-3 rounded-xl border border-blue-100 bg-blue-50/50 p-3 text-xs">
                    <span class="rounded-lg bg-white px-2 py-1 font-medium text-slate-600">Current<br /><span class="text-slate-400">{{ formatDate(drawerGroup.current.effective_date) }}</span></span>
                    <span class="text-slate-300">→</span>
                    <span class="rounded-lg bg-white px-2 py-1 font-medium text-blue-600">Next<br /><span class="text-slate-400">{{ formatDate(drawerGroup.nextScheduled.effective_date) }}</span></span>
                    <span class="ml-auto rounded-full bg-blue-100 px-2 py-1 font-medium text-blue-600">Scheduled</span>
                  </div>

                  <div class="border-t border-slate-200 pt-3">
                    <div class="mb-2 flex items-center justify-between">
                      <p class="text-xs font-medium text-slate-500">Estimasi Gross / Net</p>
                      <Loader2 v-if="previewLoading" class="h-3.5 w-3.5 animate-spin text-slate-300" :stroke-width="2" />
                    </div>
                    <template v-if="computeSalarySummary(previewLines)">
                      <div class="flex items-center justify-between text-sm">
                        <span class="text-slate-400">Gross</span>
                        <span class="font-semibold text-slate-800">{{ formatCurrency(computeSalarySummary(previewLines)!.gross) }}</span>
                      </div>
                      <div class="mt-1 flex items-center justify-between text-sm">
                        <span class="text-slate-400">Net</span>
                        <span class="font-semibold text-slate-800">{{ formatCurrency(computeSalarySummary(previewLines)!.net) }}</span>
                      </div>
                    </template>
                    <p v-else class="text-xs text-slate-300">Lengkapi structure &amp; effective date untuk melihat estimasi.</p>
                  </div>

                  <p class="flex items-start gap-1.5 text-[11px] text-slate-400">
                    <Info class="mt-0.5 h-3 w-3 shrink-0" :stroke-width="1.75" />
                    Estimasi dari hasil resolve draft — bukan output final payroll engine.
                  </p>

                  <p class="flex items-start gap-1.5 text-[11px] text-slate-400">
                    <Info class="mt-0.5 h-3 w-3 shrink-0" :stroke-width="1.75" />
                    Estimasi dari hasil resolve draft — bukan output final payroll engine.
                  </p>
                </template>
              </div>

              <!-- SALARY COMPONENTS -->
              <div v-else-if="drawerTab === 'components'">
                <div v-if="!drawerGroup.current" class="text-sm text-slate-400">Belum ada assignment.</div>
                <div v-else-if="resolvedLoading[drawerGroup.current.id]" class="flex items-center gap-2 text-sm text-slate-400">
                  <Loader2 class="h-4 w-4 animate-spin" :stroke-width="2" /> Memuat komponen...
                </div>
                <div v-else-if="(resolvedCache[drawerGroup.current.id]?.length ?? 0) === 0" class="text-sm text-slate-400">Tidak ada komponen ter-resolve.</div>
                <div v-else class="space-y-2">
                  <div v-for="line in resolvedCache[drawerGroup.current.id]" :key="line.component.id" class="rounded-xl border border-slate-100 px-4 py-2.5">
                    <div class="flex items-center justify-between">
                      <span class="text-sm text-slate-700">{{ line.component.name }}</span>
                      <span class="text-sm font-medium" :class="line.component.is_addition ? 'text-emerald-600' : 'text-red-500'">
                        {{ line.component.is_addition ? '+' : '-' }}
                        {{ line.percentage_value !== null ? `${line.percentage_value}%` : formatCurrency(resolvedLineValue(line)) }}
                      </span>
                    </div>
                    <span class="mt-1 inline-block rounded-full px-2 py-0.5 text-xs font-medium" :class="sourceBadgeClass[line.source]">
                      {{ sourceLabels[line.source] ?? line.source }}
                    </span>
                  </div>
                </div>
              </div>

              <!-- OVERRIDE -->
              <div v-else-if="drawerTab === 'override'">
                <div v-if="!drawerGroup.current || drawerGroup.current.overrides.length === 0" class="text-sm text-slate-400">Tidak ada override personal.</div>
                <div v-else class="space-y-2">
                  <div v-for="o in drawerGroup.current.overrides" :key="o.id" class="rounded-xl border border-slate-200 p-3">
                    <p class="mb-1.5 text-sm font-medium text-slate-700">{{ o.salary_component.name }}</p>
                    <div class="flex items-center justify-between text-xs">
                      <span class="text-slate-400">
                        Default {{ overrideDiff(o).isPercentage ? `${overrideDiff(o).defaultVal}%` : formatCurrency(overrideDiff(o).defaultVal) }}
                      </span>
                      <span class="text-slate-500">
                        Override {{ overrideDiff(o).isPercentage ? `${overrideDiff(o).overrideVal}%` : formatCurrency(overrideDiff(o).overrideVal) }}
                      </span>
                      <span class="font-medium" :class="diffColorClass(overrideDiff(o).diff)">
                        {{ overrideDiff(o).isPercentage ? formatSignedPercentage(overrideDiff(o).diff) : formatSignedCurrency(overrideDiff(o).diff) }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- VERSION HISTORY -->
              <div v-else-if="drawerTab === 'history'">
                <div v-if="drawerGroup.versions.length === 0" class="text-sm text-slate-400">Belum ada riwayat versi.</div>
                <div v-else class="space-y-0">
                  <div v-for="version in [...drawerGroup.versions].reverse()" :key="version.id" class="relative border-l border-slate-100 py-3 pl-5 last:pb-0">
                    <span
                      class="absolute -left-[5px] top-4 h-2.5 w-2.5 rounded-full"
                      :class="version.id === drawerGroup.current?.id ? 'bg-primary' : isFutureDate(version.effective_date) ? 'bg-blue-400' : 'bg-slate-300'"
                    ></span>
                    <div class="flex items-center gap-2">
                      <p class="text-sm font-medium text-slate-800">{{ formatDate(version.effective_date) }}</p>
                      <span v-if="version.id === drawerGroup.current?.id" class="rounded-full bg-primary-soft px-2 py-0.5 text-[10px] font-medium text-primary-dark">Current</span>
                      <span v-else-if="isFutureDate(version.effective_date)" class="rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-medium text-blue-600">Scheduled</span>
                    </div>
                    <p class="text-xs text-slate-500">{{ structureName(version) }} · {{ version.overrides.length }} override</p>
                    <p v-if="version.created_at" class="text-[11px] text-slate-300">Dibuat {{ formatDate(version.created_at) }}</p>
                  </div>
                </div>
              </div>

              <!-- AUDIT LOG -->
              <div v-else-if="drawerTab === 'audit'" class="flex flex-col items-center gap-3 py-10 text-center">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-50 text-slate-300">
                  <ScrollText class="h-6 w-6" :stroke-width="1.5" />
                </div>
                <p class="text-sm font-medium text-slate-600">Audit Log belum tersedia</p>
                <p class="max-w-xs text-xs text-slate-400">
                  Backend perlu menyediakan data audit trail (siapa, kapan, perubahan apa) sebelum tab ini bisa menampilkan riwayat perubahan.
                </p>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.drawer-enter-active,
.drawer-leave-active {
  transition: opacity 0.2s ease;
}
.drawer-enter-active > div,
.drawer-leave-active > div {
  transition: transform 0.25s ease;
}
.drawer-enter-from,
.drawer-leave-to {
  opacity: 0;
}
.drawer-enter-from > div,
.drawer-leave-to > div {
  transform: translateX(100%);
}
</style>