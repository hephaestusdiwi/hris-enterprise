<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue'
import { Plus, X, Loader2, AlertTriangle, ChevronDown, ChevronUp, Ban, Users } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface Benefit { id: number; name: string; is_active: boolean }
interface Policy {
  id: number
  name: string
  description: string | null
  effective_date: string
  expiration_date: string | null
  default_limit_amount: string | null
  is_active: boolean
  benefits_count?: number
  benefits?: Benefit[]
}
interface Employee { id: number; first_name: string; last_name: string | null }
interface Balance {
  id: number
  employee: Employee
  assigned_amount: string | null
  effective_date: string
  expiration_date: string | null
  status: 'active' | 'stopped'
}

function formatCurrency(value: string | number | null) {
  if (value === null) return 'Unlimited'
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(Number(value))
}
function formatDate(value: string) {
  return new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}
function employeeName(e: Employee) {
  return [e.first_name, e.last_name].filter(Boolean).join(' ')
}

const policies = ref<Policy[]>([])
const loading = ref(true)
const errorMessage = ref('')

async function loadPolicies() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/reimbursement-policies')
    policies.value = response.data.data
  } catch {
    errorMessage.value = 'Gagal memuat daftar policy.'
  } finally {
    loading.value = false
  }
}

// ---------- EXPAND / DETAIL PER POLICY ----------
const expandedId = ref<number | null>(null)
const expandedBenefits = ref<Benefit[]>([])
const expandedBalances = ref<Balance[]>([])
const expandLoading = ref(false)

async function toggleExpand(policy: Policy) {
  if (expandedId.value === policy.id) {
    expandedId.value = null
    return
  }
  expandedId.value = policy.id
  expandLoading.value = true
  try {
    const [benefitsRes, balancesRes] = await Promise.all([
      apiClient.get(`/api/reimbursement-policies/${policy.id}/benefits`),
      apiClient.get('/api/reimbursement-balances', { params: { reimbursement_policy_id: policy.id } }),
    ])
    expandedBenefits.value = benefitsRes.data.data
    expandedBalances.value = balancesRes.data.data.filter((b: any) => b.policy.id === policy.id)
  } catch {
    errorMessage.value = 'Gagal memuat detail policy.'
  } finally {
    expandLoading.value = false
  }
}

// ---------- CREATE/EDIT POLICY MODAL ----------
const showPolicyModal = ref(false)
const editingPolicy = ref<Policy | null>(null)
const savingPolicy = ref(false)
const policyError = ref('')
const policyForm = reactive({
  name: '', description: '', effective_date: new Date().toISOString().slice(0, 10),
  expiration_date: '', default_limit_amount: null as number | null, is_active: true,
})

function openCreatePolicy() {
  editingPolicy.value = null
  policyError.value = ''
  policyForm.name = ''
  policyForm.description = ''
  policyForm.effective_date = new Date().toISOString().slice(0, 10)
  policyForm.expiration_date = ''
  policyForm.default_limit_amount = null
  policyForm.is_active = true
  showPolicyModal.value = true
}
function openEditPolicy(policy: Policy) {
  editingPolicy.value = policy
  policyError.value = ''
  policyForm.name = policy.name
  policyForm.description = policy.description ?? ''
  policyForm.effective_date = policy.effective_date
  policyForm.expiration_date = policy.expiration_date ?? ''
  policyForm.default_limit_amount = policy.default_limit_amount ? Number(policy.default_limit_amount) : null
  policyForm.is_active = policy.is_active
  showPolicyModal.value = true
}

async function submitPolicy() {
  if (!policyForm.name.trim()) {
    policyError.value = 'Nama policy wajib diisi.'
    return
  }
  savingPolicy.value = true
  policyError.value = ''
  const payload = {
    name: policyForm.name,
    description: policyForm.description || null,
    effective_date: policyForm.effective_date,
    expiration_date: policyForm.expiration_date || null,
    default_limit_amount: policyForm.default_limit_amount,
    is_active: policyForm.is_active,
  }
  try {
    if (editingPolicy.value) {
      await apiClient.put(`/api/reimbursement-policies/${editingPolicy.value.id}`, payload)
    } else {
      await apiClient.post('/api/reimbursement-policies', payload)
    }
    showPolicyModal.value = false
    await loadPolicies()
  } catch (err: any) {
    policyError.value = err.response?.data?.message || 'Gagal menyimpan policy.'
  } finally {
    savingPolicy.value = false
  }
}

// ---------- BENEFIT ----------
const newBenefitName = ref('')
const savingBenefit = ref(false)

async function addBenefit(policy: Policy) {
  if (!newBenefitName.value.trim()) return
  savingBenefit.value = true
  try {
    await apiClient.post(`/api/reimbursement-policies/${policy.id}/benefits`, { name: newBenefitName.value })
    newBenefitName.value = ''
    await toggleExpand(policy) // refresh (dan toggle balik karena state sudah expanded, jadi ini nutup)
    await toggleExpand(policy) // buka lagi dengan data baru
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Gagal menambah benefit.'
  } finally {
    savingBenefit.value = false
  }
}

async function toggleBenefitActive(benefit: Benefit) {
  try {
    await apiClient.put(`/api/reimbursement-benefits/${benefit.id}`, { is_active: !benefit.is_active })
    benefit.is_active = !benefit.is_active
  } catch {
    errorMessage.value = 'Gagal mengubah status benefit.'
  }
}

// ---------- ASSIGN BALANCE ----------
const showAssignModal = ref(false)
const assignTargetPolicy = ref<Policy | null>(null)
const savingAssign = ref(false)
const assignError = ref('')
const employees = ref<Employee[]>([])
const assignForm = reactive({
  employee_id: null as number | null,
  assigned_amount: null as number | null,
  effective_date: new Date().toISOString().slice(0, 10),
  expiration_date: '',
})

async function openAssignModal(policy: Policy) {
  assignTargetPolicy.value = policy
  assignError.value = ''
  assignForm.employee_id = null
  assignForm.assigned_amount = policy.default_limit_amount ? Number(policy.default_limit_amount) : null
  assignForm.effective_date = new Date().toISOString().slice(0, 10)
  assignForm.expiration_date = ''
  showAssignModal.value = true
  if (employees.value.length === 0) {
    try {
      const response = await apiClient.get('/api/employees', { params: { per_page: 200 } })
      employees.value = response.data.data.data ?? response.data.data
    } catch {
      // employee dropdown fallback tetap kosong, tidak fatal untuk halaman ini
    }
  }
}

async function submitAssign() {
  if (!assignForm.employee_id || !assignTargetPolicy.value) {
    assignError.value = 'Pilih employee dulu.'
    return
  }
  savingAssign.value = true
  assignError.value = ''
  try {
    await apiClient.post('/api/reimbursement-balances', {
      employee_id: assignForm.employee_id,
      reimbursement_policy_id: assignTargetPolicy.value.id,
      assigned_amount: assignForm.assigned_amount,
      effective_date: assignForm.effective_date,
      expiration_date: assignForm.expiration_date || null,
    })
    showAssignModal.value = false
    await toggleExpand(assignTargetPolicy.value)
    await toggleExpand(assignTargetPolicy.value)
  } catch (err: any) {
    assignError.value = err.response?.data?.message || 'Gagal assign balance.'
  } finally {
    savingAssign.value = false
  }
}

async function stopBalance(balance: Balance) {
  const reason = prompt('Alasan menghentikan balance ini?')
  if (!reason) return
  try {
    await apiClient.post(`/api/reimbursement-balances/${balance.id}/stop`, { reason })
    balance.status = 'stopped'
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Gagal menghentikan balance.'
  }
}

onMounted(loadPolicies)
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Reimbursement Policy</h1>
        <p class="mt-1 text-sm text-slate-500">Kelola policy, benefit, dan assignment balance ke employee.</p>
      </div>
      <button @click="openCreatePolicy" class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-white hover:bg-primary-dark">
        <Plus class="h-4 w-4" :stroke-width="2" /> Policy Baru
      </button>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>
    <div v-else-if="policies.length === 0" class="rounded-xl bg-slate-50 p-6 text-center text-sm text-slate-400">Belum ada reimbursement policy.</div>

    <div v-else class="space-y-3">
      <div v-for="policy in policies" :key="policy.id" class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <button @click="toggleExpand(policy)" class="flex w-full items-center justify-between px-5 py-4 text-left hover:bg-slate-50/50">
          <div>
            <div class="flex items-center gap-2">
              <p class="font-medium text-slate-800">{{ policy.name }}</p>
              <span v-if="!policy.is_active" class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">Nonaktif</span>
            </div>
            <p class="mt-0.5 text-xs text-slate-400">
              {{ formatCurrency(policy.default_limit_amount) }} · Efektif {{ formatDate(policy.effective_date) }}
              <span v-if="policy.expiration_date"> s.d. {{ formatDate(policy.expiration_date) }}</span>
              · {{ policy.benefits_count ?? 0 }} benefit
            </p>
          </div>
          <div class="flex items-center gap-2">
            <span @click.stop="openEditPolicy(policy)" class="rounded-lg px-2 py-1 text-xs font-medium text-primary hover:bg-primary-soft">Edit</span>
            <ChevronUp v-if="expandedId === policy.id" class="h-4 w-4 text-slate-400" :stroke-width="1.75" />
            <ChevronDown v-else class="h-4 w-4 text-slate-400" :stroke-width="1.75" />
          </div>
        </button>

        <div v-if="expandedId === policy.id" class="border-t border-slate-100 px-5 py-4">
          <div v-if="expandLoading" class="text-xs text-slate-400">Memuat...</div>
          <template v-else>
            <div class="mb-4">
              <p class="mb-2 text-xs font-medium text-slate-500">Benefit</p>
              <div class="mb-2 flex flex-wrap gap-1.5">
                <span
                  v-for="b in expandedBenefits" :key="b.id" @click="toggleBenefitActive(b)"
                  class="cursor-pointer rounded-full px-2.5 py-1 text-xs font-medium"
                  :class="b.is_active ? 'bg-primary-soft text-primary-dark' : 'bg-slate-100 text-slate-400 line-through'"
                >
                  {{ b.name }}
                </span>
                <span v-if="expandedBenefits.length === 0" class="text-xs text-slate-400">Belum ada benefit.</span>
              </div>
              <div class="flex gap-2">
                <input v-model="newBenefitName" placeholder="Nama benefit baru..." class="flex-1 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs focus:border-primary focus:outline-none" />
                <button @click="addBenefit(policy)" :disabled="savingBenefit" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-200">Tambah</button>
              </div>
            </div>

            <div>
              <div class="mb-2 flex items-center justify-between">
                <p class="text-xs font-medium text-slate-500">Employee Assignment ({{ expandedBalances.length }})</p>
                <button @click="openAssignModal(policy)" class="flex items-center gap-1 rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-200">
                  <Users class="h-3.5 w-3.5" :stroke-width="1.75" /> Assign
                </button>
              </div>
              <div v-if="expandedBalances.length === 0" class="text-xs text-slate-400">Belum ada employee yang di-assign.</div>
              <div v-for="b in expandedBalances" :key="b.id" class="mb-1.5 flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-xs">
                <div>
                  <span class="font-medium text-slate-700">{{ employeeName(b.employee) }}</span>
                  <span class="ml-1.5 text-slate-400">{{ formatCurrency(b.assigned_amount) }}</span>
                  <span v-if="b.status === 'stopped'" class="ml-1.5 rounded-full bg-slate-200 px-1.5 py-0.5 text-slate-500">Dihentikan</span>
                </div>
                <button v-if="b.status === 'active'" @click="stopBalance(b)" class="text-slate-400 hover:text-red-500" title="Hentikan balance">
                  <Ban class="h-3.5 w-3.5" :stroke-width="1.75" />
                </button>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>

    <!-- Policy modal -->
    <Teleport to="body">
      <div v-if="showPolicyModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8">
        <div class="w-full max-w-md rounded-2xl bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">{{ editingPolicy ? 'Edit' : 'Policy Baru' }}</h2>
            <button @click="showPolicyModal = false" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-50"><X class="h-5 w-5" :stroke-width="1.75" /></button>
          </div>
          <div class="space-y-3 px-6 py-5">
            <input v-model="policyForm.name" placeholder="Nama policy (mis. Medical)" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
            <textarea v-model="policyForm.description" rows="2" placeholder="Deskripsi (opsional)" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"></textarea>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="mb-1 block text-xs font-medium text-slate-700">Effective Date</label>
                <input v-model="policyForm.effective_date" type="date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
              <div>
                <label class="mb-1 block text-xs font-medium text-slate-700">Expiration (opsional)</label>
                <input v-model="policyForm.expiration_date" type="date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-700">Default Limit (kosongkan = unlimited)</label>
              <input v-model.number="policyForm.default_limit_amount" type="number" min="0" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-600">
              <input v-model="policyForm.is_active" type="checkbox" class="rounded border-slate-300" /> Aktif
            </label>
            <div v-if="policyError" class="flex items-start gap-2 rounded-xl bg-red-50 p-3 text-xs text-red-600">
              <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" :stroke-width="1.75" />
              <p>{{ policyError }}</p>
            </div>
          </div>
          <div class="flex gap-3 border-t border-slate-100 px-6 py-4">
            <button @click="showPolicyModal = false" class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50">Batal</button>
            <button @click="submitPolicy" :disabled="savingPolicy" class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-primary py-2.5 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50">
              <Loader2 v-if="savingPolicy" class="h-4 w-4 animate-spin" :stroke-width="2" /> Simpan
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Assign balance modal -->
    <Teleport to="body">
      <div v-if="showAssignModal && assignTargetPolicy" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8">
        <div class="w-full max-w-md rounded-2xl bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Assign Balance</h2>
            <button @click="showAssignModal = false" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-50"><X class="h-5 w-5" :stroke-width="1.75" /></button>
          </div>
          <div class="space-y-3 px-6 py-5">
            <p class="text-sm text-slate-500">Policy: <span class="font-medium text-slate-700">{{ assignTargetPolicy.name }}</span></p>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-700">Employee</label>
              <select v-model="assignForm.employee_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                <option :value="null" disabled>Pilih employee</option>
                <option v-for="e in employees" :key="e.id" :value="e.id">{{ employeeName(e) }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-700">Assigned Amount (kosongkan = ikut default policy)</label>
              <input v-model.number="assignForm.assigned_amount" type="number" min="0" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="mb-1 block text-xs font-medium text-slate-700">Effective Date</label>
                <input v-model="assignForm.effective_date" type="date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
              <div>
                <label class="mb-1 block text-xs font-medium text-slate-700">Expiration (opsional)</label>
                <input v-model="assignForm.expiration_date" type="date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
            </div>
            <div v-if="assignError" class="flex items-start gap-2 rounded-xl bg-red-50 p-3 text-xs text-red-600">
              <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" :stroke-width="1.75" />
              <p>{{ assignError }}</p>
            </div>
          </div>
          <div class="flex gap-3 border-t border-slate-100 px-6 py-4">
            <button @click="showAssignModal = false" class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50">Batal</button>
            <button @click="submitAssign" :disabled="savingAssign" class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-primary py-2.5 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50">
              <Loader2 v-if="savingAssign" class="h-4 w-4 animate-spin" :stroke-width="2" /> Assign
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>