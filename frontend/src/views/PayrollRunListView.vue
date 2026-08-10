<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { Plus, X } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface Company { id: number; name: string }
interface Employee { id: number; first_name: string; last_name: string | null }

type RunStatus = 'draft' | 'pending_approval' | 'approved' | 'processed' | 'locked' | 'cancelled'

interface PayrollRunRow {
  id: number
  company: Company
  period_year: number
  period_month: number
  status: RunStatus
  current_revision: number
  published_at: string | null
}

const monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']

const statusLabels: Record<RunStatus, string> = {
  draft: 'Draft', pending_approval: 'Menunggu Approval', approved: 'Approved',
  processed: 'Processed', locked: 'Locked', cancelled: 'Dibatalkan',
}
const statusBadgeClass: Record<RunStatus, string> = {
  draft: 'bg-slate-100 text-slate-500',
  pending_approval: 'bg-amber-50 text-amber-600',
  approved: 'bg-blue-50 text-blue-600',
  processed: 'bg-primary-soft text-primary-dark',
  locked: 'bg-emerald-50 text-emerald-600',
  cancelled: 'bg-red-50 text-red-600',
}

function employeeName(e: { first_name: string; last_name: string | null }) {
  return [e.first_name, e.last_name].filter(Boolean).join(' ')
}

const router = useRouter()
const runs = ref<PayrollRunRow[]>([])
const companies = ref<Company[]>([])
const employees = ref<Employee[]>([])
const loading = ref(true)
const meta = ref({ current_page: 1, last_page: 1, total: 0 })
const filters = reactive({ company_id: null as number | null, status: null as RunStatus | null, page: 1 })

async function loadRuns() {
  loading.value = true
  const response = await apiClient.get('/api/payroll-runs', {
    params: { company_id: filters.company_id || undefined, status: filters.status || undefined, page: filters.page },
  })
  runs.value = response.data.data.data
  meta.value = { current_page: response.data.data.current_page, last_page: response.data.data.last_page, total: response.data.data.total }
  loading.value = false
}

async function loadCompanies() {
  const response = await apiClient.get('/api/companies')
  companies.value = response.data.data.data ?? response.data.data
}

async function loadEmployees() {
  const response = await apiClient.get('/api/employees', { params: { per_page: 200 } })
  employees.value = response.data.data.data
}

function applyFilters() {
  filters.page = 1
  loadRuns()
}
function goToPage(page: number) {
  filters.page = page
  loadRuns()
}

// ---------- CREATE MODAL ----------
const showModal = ref(false)
const saving = ref(false)
const formError = ref('')
const form = reactive({
  company_id: null as number | null,
  period_year: new Date().getFullYear(),
  period_month: new Date().getMonth() + 1,
  cutoff_date: '',
  payment_date: '',
  employee_ids: [] as number[],
})

function openCreateModal() {
  form.company_id = companies.value[0]?.id ?? null
  form.period_year = new Date().getFullYear()
  form.period_month = new Date().getMonth() + 1
  form.cutoff_date = ''
  form.payment_date = ''
  form.employee_ids = []
  formError.value = ''
  showModal.value = true
}

function toggleEmployee(id: number) {
  const idx = form.employee_ids.indexOf(id)
  if (idx >= 0) form.employee_ids.splice(idx, 1)
  else form.employee_ids.push(id)
}
function selectAllEmployees() {
  form.employee_ids = employees.value.map((e) => e.id)
}

async function submitForm() {
  saving.value = true
  formError.value = ''
  try {
    const response = await apiClient.post('/api/payroll-runs', form)
    showModal.value = false
    router.push(`/payroll-runs/${response.data.data.id}`)
  } catch (err: any) {
    formError.value = err.response?.data?.message || 'Gagal membuat payroll run.'
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  loadRuns()
  loadCompanies()
  loadEmployees()
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Payroll History</h1>
        <p class="mt-1 text-sm text-slate-500">Run Payroll per periode — kalkulasi, approval, lock, publish.</p>
      </div>
      <button @click="openCreateModal" class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-white hover:bg-primary-dark">
        <Plus class="h-4 w-4" :stroke-width="2" /> Run Payroll Baru
      </button>
    </div>

    <div class="flex flex-wrap items-end gap-3 rounded-2xl border border-slate-100 bg-white p-4">
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-500">Company</label>
        <select v-model="filters.company_id" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
          <option :value="null">Semua Company</option>
          <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
      </div>
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-500">Status</label>
        <select v-model="filters.status" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
          <option :value="null">Semua Status</option>
          <option v-for="(label, value) in statusLabels" :key="value" :value="value">{{ label }}</option>
        </select>
      </div>
      <button @click="applyFilters" class="rounded-xl bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900">Terapkan</button>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="runs.length === 0" class="rounded-xl bg-slate-50 p-6 text-center text-sm text-slate-400">Belum ada payroll run.</div>

    <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <table class="w-full text-left text-sm">
        <thead>
          <tr class="border-b border-slate-100 bg-slate-50/60">
            <th class="px-5 py-3 font-medium text-slate-500">Periode</th>
            <th class="px-5 py-3 font-medium text-slate-500">Company</th>
            <th class="px-5 py-3 text-center font-medium text-slate-500">Revisi</th>
            <th class="px-5 py-3 font-medium text-slate-500">Status</th>
            <th class="px-5 py-3 font-medium text-slate-500">Publish</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in runs" :key="row.id" class="cursor-pointer border-b border-slate-50 last:border-0 hover:bg-slate-50/50" @click="router.push(`/payroll-runs/${row.id}`)">
            <td class="px-5 py-3.5 font-medium text-slate-800">{{ monthNames[row.period_month - 1] }} {{ row.period_year }}</td>
            <td class="px-5 py-3.5 text-slate-500">{{ row.company.name }}</td>
            <td class="px-5 py-3.5 text-center text-slate-500">{{ row.current_revision || '-' }}</td>
            <td class="px-5 py-3.5"><span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="statusBadgeClass[row.status]">{{ statusLabels[row.status] }}</span></td>
            <td class="px-5 py-3.5 text-xs text-slate-500">{{ row.published_at ? 'Published' : '-' }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="meta.last_page > 1" class="flex items-center justify-between text-sm text-slate-500">
      <p>Total {{ meta.total }} run</p>
      <div class="flex gap-1">
        <button v-for="page in meta.last_page" :key="page" @click="goToPage(page)" class="rounded-lg px-3 py-1.5 text-xs font-medium" :class="page === meta.current_page ? 'bg-primary text-white' : 'text-slate-500 hover:bg-slate-100'">{{ page }}</button>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8">
        <div class="flex max-h-full w-full max-w-lg flex-col rounded-2xl bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Run Payroll Baru</h2>
            <button @click="showModal = false" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50"><X class="h-5 w-5" /></button>
          </div>

          <form @submit.prevent="submitForm" class="flex-1 space-y-4 overflow-y-auto px-6 py-5">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Company</label>
              <select v-model.number="form.company_id" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Bulan</label>
                <select v-model.number="form.period_month" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                  <option v-for="(m, i) in monthNames" :key="i" :value="i + 1">{{ m }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Tahun</label>
                <input v-model.number="form.period_year" type="number" min="2020" max="2100" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Cutoff Date</label>
                <input v-model="form.cutoff_date" type="date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Payment Date</label>
                <input v-model="form.payment_date" type="date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
            </div>

            <div>
              <div class="mb-1 flex items-center justify-between">
                <label class="block text-sm font-medium text-slate-700">Pilih Employee ({{ form.employee_ids.length }} terpilih)</label>
                <button type="button" @click="selectAllEmployees" class="text-xs font-medium text-primary-dark">Pilih Semua</button>
              </div>
              <div class="max-h-48 space-y-1 overflow-y-auto rounded-xl border border-slate-200 p-2">
                <label v-for="e in employees" :key="e.id" class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-slate-50">
                  <input type="checkbox" :checked="form.employee_ids.includes(e.id)" @change="toggleEmployee(e.id)" class="rounded border-slate-300 text-primary focus:ring-primary" />
                  {{ employeeName(e) }}
                </label>
              </div>
            </div>

            <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>
          </form>

          <div class="border-t border-slate-100 px-6 py-4">
            <button @click="submitForm" :disabled="saving || form.employee_ids.length === 0" class="w-full rounded-xl bg-primary py-2.5 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50">
              {{ saving ? 'Menyimpan...' : 'Simpan sebagai Draft' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>