<script setup lang="ts">
import { ref, onMounted, reactive, computed } from 'vue'
import { Plus, Ban, CheckCircle2, X } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface Employee {
  id: number
  employee_number: string
  first_name: string
  last_name: string
}

interface Component {
  id: number
  name: string
  category: string
  is_addition: boolean | null
}

interface InputRow {
  id: number
  employee_id: number
  amount: string
  is_addition: boolean
  note: string | null
  status: 'draft' | 'ready' | 'processed' | 'void'
  payroll_period_year: number
  payroll_period_month: number
  employee: Employee
  component: Component
}

const statusLabels: Record<InputRow['status'], string> = {
  draft: 'Draft',
  ready: 'Ready',
  processed: 'Processed',
  void: 'Void',
}

const statusBadgeClass: Record<InputRow['status'], string> = {
  draft: 'bg-slate-100 text-slate-500',
  ready: 'bg-blue-50 text-blue-600',
  processed: 'bg-primary-soft text-primary-dark',
  void: 'bg-red-50 text-red-500',
}

function formatCurrency(value: string) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(Number(value))
}

const inputs = ref<InputRow[]>([])
const employees = ref<Employee[]>([])
const components = ref<Component[]>([])
const loading = ref(true)
const errorMessage = ref('')
const selectedIds = ref<number[]>([])

const filters = reactive({
  payroll_period_year: new Date().getFullYear(),
  payroll_period_month: new Date().getMonth() + 1,
  status: '' as string,
})

const showModal = ref(false)
const saving = ref(false)
const formError = ref('')
const form = reactive({
  employee_id: 0,
  non_regular_payroll_component_id: 0,
  payroll_period_year: filters.payroll_period_year,
  payroll_period_month: filters.payroll_period_month,
  amount: null as number | null,
  is_addition: null as boolean | null,
  note: '',
})

const showVoidModal = ref(false)
const voidTargetId = ref(0)
const voidReason = ref('')

const selectedComponent = computed(() => components.value.find((c) => c.id === form.non_regular_payroll_component_id))
const needsExplicitDirection = computed(() => selectedComponent.value?.is_addition === null)

async function loadInputs() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/employee-non-regular-inputs', {
      params: {
        payroll_period_year: filters.payroll_period_year,
        payroll_period_month: filters.payroll_period_month,
        status: filters.status || undefined,
      },
    })
    inputs.value = response.data.data.data
    selectedIds.value = []
  } catch {
    errorMessage.value = 'Gagal memuat daftar input payroll non-reguler.'
  } finally {
    loading.value = false
  }
}

async function loadReferenceData() {
  const [employeeRes, componentRes] = await Promise.all([
    apiClient.get('/api/employees', { params: { per_page: 100 } }),
    apiClient.get('/api/non-regular-payroll-components', { params: { is_active: true } }),
  ])
  employees.value = employeeRes.data.data.data
  components.value = componentRes.data.data.data
}

function employeeName(e: Employee) {
  return `${e.first_name} ${e.last_name}`.trim()
}

function openCreateModal() {
  formError.value = ''
  form.employee_id = employees.value[0]?.id ?? 0
  form.non_regular_payroll_component_id = components.value[0]?.id ?? 0
  form.payroll_period_year = filters.payroll_period_year
  form.payroll_period_month = filters.payroll_period_month
  form.amount = null
  form.is_addition = null
  form.note = ''
  showModal.value = true
}

function closeModal() {
  showModal.value = false
}

async function handleSubmit() {
  formError.value = ''
  saving.value = true

  try {
    await apiClient.post('/api/employee-non-regular-inputs', {
      employee_id: form.employee_id,
      non_regular_payroll_component_id: form.non_regular_payroll_component_id,
      payroll_period_year: form.payroll_period_year,
      payroll_period_month: form.payroll_period_month,
      amount: form.amount,
      is_addition: needsExplicitDirection.value ? form.is_addition : undefined,
      note: form.note || undefined,
    })

    showModal.value = false
    await loadInputs()
  } catch (err: any) {
    formError.value = err.response?.data?.message || 'Terjadi kesalahan, silakan coba lagi.'
  } finally {
    saving.value = false
  }
}

function openVoidModal(row: InputRow) {
  voidTargetId.value = row.id
  voidReason.value = ''
  showVoidModal.value = true
}

async function confirmVoid() {
  if (!voidReason.value.trim()) return
  try {
    await apiClient.post(`/api/employee-non-regular-inputs/${voidTargetId.value}/void`, { reason: voidReason.value })
    showVoidModal.value = false
    await loadInputs()
  } catch {
    alert('Gagal void input.')
  }
}

async function markReady(row: InputRow) {
  try {
    await apiClient.post(`/api/employee-non-regular-inputs/${row.id}/mark-ready`)
    await loadInputs()
  } catch {
    alert('Gagal menandai Ready.')
  }
}

async function bulkMarkReady() {
  if (selectedIds.value.length === 0) return
  try {
    await apiClient.post('/api/employee-non-regular-inputs/bulk-mark-ready', { ids: selectedIds.value })
    await loadInputs()
  } catch {
    alert('Gagal menandai Ready secara massal.')
  }
}

function toggleSelect(id: number) {
  const idx = selectedIds.value.indexOf(id)
  if (idx === -1) selectedIds.value.push(id)
  else selectedIds.value.splice(idx, 1)
}

onMounted(() => {
  loadInputs()
  loadReferenceData()
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Employee Non-Regular Input</h1>
        <p class="mt-1 text-sm text-slate-500">Input nominal Bonus/Incentive/Commission/One-Time Earning/Deduction per employee per periode.</p>
      </div>
      <button
        @click="openCreateModal"
        class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-dark"
      >
        <Plus class="h-4 w-4" :stroke-width="2" />
        Tambah Input
      </button>
    </div>

    <div class="flex flex-wrap items-end gap-3 rounded-2xl border border-slate-100 bg-white p-4">
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-500">Tahun</label>
        <input v-model.number="filters.payroll_period_year" type="number" @change="loadInputs" class="w-24 rounded-lg border border-slate-200 px-3 py-1.5 text-sm" />
      </div>
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-500">Bulan</label>
        <input v-model.number="filters.payroll_period_month" type="number" min="1" max="12" @change="loadInputs" class="w-20 rounded-lg border border-slate-200 px-3 py-1.5 text-sm" />
      </div>
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-500">Status</label>
        <select v-model="filters.status" @change="loadInputs" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm">
          <option value="">Semua</option>
          <option value="draft">Draft</option>
          <option value="ready">Ready</option>
          <option value="processed">Processed</option>
          <option value="void">Void</option>
        </select>
      </div>
      <button
        v-if="selectedIds.length > 0"
        @click="bulkMarkReady"
        class="ml-auto flex items-center gap-2 rounded-xl bg-blue-50 px-4 py-2 text-sm font-medium text-blue-600 hover:bg-blue-100"
      >
        <CheckCircle2 class="h-4 w-4" :stroke-width="1.75" />
        Tandai Ready ({{ selectedIds.length }})
      </button>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>
    <div v-else-if="inputs.length === 0" class="rounded-xl bg-slate-50 p-4 text-sm text-slate-400">
      Belum ada input untuk periode ini.
    </div>

    <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <table class="w-full text-left text-sm">
        <thead>
          <tr class="border-b border-slate-100 bg-slate-50/60">
            <th class="w-10 px-5 py-3"></th>
            <th class="px-5 py-3 font-medium text-slate-500">Employee</th>
            <th class="px-5 py-3 font-medium text-slate-500">Component</th>
            <th class="px-5 py-3 font-medium text-slate-500">Amount</th>
            <th class="px-5 py-3 font-medium text-slate-500">Status</th>
            <th class="px-5 py-3 text-right font-medium text-slate-500">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in inputs" :key="row.id" class="border-b border-slate-50 last:border-0 hover:bg-slate-50/50">
            <td class="px-5 py-3.5">
              <input
                v-if="row.status === 'draft'"
                type="checkbox"
                :checked="selectedIds.includes(row.id)"
                @change="toggleSelect(row.id)"
                class="rounded border-slate-300"
              />
            </td>
            <td class="px-5 py-3.5">
              <p class="font-medium text-slate-800">{{ employeeName(row.employee) }}</p>
              <p class="text-xs text-slate-400">{{ row.employee.employee_number }}</p>
            </td>
            <td class="px-5 py-3.5">
              <p class="text-slate-700">{{ row.component.name }}</p>
              <p class="text-xs text-slate-400">{{ row.component.category }}</p>
            </td>
            <td class="px-5 py-3.5">
              <span :class="row.is_addition ? 'text-primary-dark' : 'text-red-600'">
                {{ row.is_addition ? '+' : '-' }}{{ formatCurrency(row.amount) }}
              </span>
            </td>
            <td class="px-5 py-3.5">
              <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="statusBadgeClass[row.status]">
                {{ statusLabels[row.status] }}
              </span>
            </td>
            <td class="px-5 py-3.5">
              <div class="flex items-center justify-end gap-1">
                <button
                  v-if="row.status === 'draft'"
                  @click="markReady(row)"
                  class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-blue-50 hover:text-blue-600"
                  title="Tandai Ready"
                >
                  <CheckCircle2 class="h-4 w-4" :stroke-width="1.75" />
                </button>
                <button
                  v-if="row.status === 'draft' || row.status === 'ready'"
                  @click="openVoidModal(row)"
                  class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-red-50 hover:text-red-500"
                  title="Void"
                >
                  <Ban class="h-4 w-4" :stroke-width="1.75" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8">
        <div class="flex max-h-full w-full max-w-lg flex-col rounded-2xl bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Tambah Input Non-Reguler</h2>
            <button @click="closeModal" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50">
              <X class="h-5 w-5" />
            </button>
          </div>

          <form @submit.prevent="handleSubmit" class="flex-1 space-y-5 overflow-y-auto px-6 py-5">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Employee</label>
              <select v-model.number="form.employee_id" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                <option v-for="e in employees" :key="e.id" :value="e.id">{{ employeeName(e) }} ({{ e.employee_number }})</option>
              </select>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Component</label>
              <select v-model.number="form.non_regular_payroll_component_id" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                <option v-for="c in components" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>

            <div v-if="needsExplicitDirection">
              <label class="mb-1 block text-sm font-medium text-slate-700">Sifat</label>
              <select v-model="form.is_addition" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                <option :value="null" disabled>Pilih sifat</option>
                <option :value="true">Earning (menambah)</option>
                <option :value="false">Deduction (mengurangi)</option>
              </select>
              <p class="mt-1 text-xs text-slate-400">Component Adjustment butuh sifat eksplisit.</p>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Tahun Periode</label>
                <input v-model.number="form.payroll_period_year" type="number" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Bulan Periode</label>
                <input v-model.number="form.payroll_period_month" type="number" min="1" max="12" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Amount (Rp)</label>
              <input v-model.number="form.amount" type="number" min="0" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Catatan (opsional)</label>
              <textarea v-model="form.note" rows="2" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"></textarea>
            </div>

            <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>
          </form>

          <div class="border-t border-slate-100 px-6 py-4">
            <button
              @click="handleSubmit"
              :disabled="saving"
              class="w-full rounded-xl bg-primary py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-dark disabled:opacity-50"
            >
              {{ saving ? 'Menyimpan...' : 'Simpan sebagai Draft' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <Teleport to="body">
      <div v-if="showVoidModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8">
        <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl">
          <h2 class="text-lg font-semibold text-slate-900">Void Input</h2>
          <p class="mt-1 text-sm text-slate-500">Alasan void wajib diisi.</p>
          <textarea v-model="voidReason" rows="3" class="mt-3 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" placeholder="Contoh: salah input nominal"></textarea>
          <div class="mt-4 flex gap-2">
            <button @click="showVoidModal = false" class="flex-1 rounded-xl border border-slate-200 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Batal</button>
            <button @click="confirmVoid" :disabled="!voidReason.trim()" class="flex-1 rounded-xl bg-red-500 py-2 text-sm font-medium text-white hover:bg-red-600 disabled:opacity-50">Void</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>