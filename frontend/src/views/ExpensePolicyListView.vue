<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue'
import { Plus, X, Loader2, AlertTriangle, ChevronDown, ChevronUp } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface Company { id: number; name: string }
interface Category { id: number; name: string; code: string; is_active: boolean; company_id: number }
interface PolicyCategory extends Category {
  pivot: { limit_amount: string | null }
}
interface Policy {
  id: number
  company_id: number
  name: string
  description: string | null
  effective_date: string
  expiration_date: string | null
  is_active: boolean
  company: Company
  categories: PolicyCategory[]
}

function formatDate(value: string) {
  return new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}
function formatLimit(value: string | null) {
  if (value === null) return 'Unlimited'
  return `Rp${Number(value).toLocaleString('id-ID')}`
}

const policies = ref<Policy[]>([])
const companies = ref<Company[]>([])
const categories = ref<Category[]>([])
const loading = ref(true)
const errorMessage = ref('')

async function loadAll() {
  loading.value = true
  errorMessage.value = ''
  try {
    const [policyRes, companyRes, categoryRes] = await Promise.all([
      apiClient.get('/api/expense-policies'),
      apiClient.get('/api/companies'),
      apiClient.get('/api/expense-categories', { params: { per_page: 100 } }),
    ])
    policies.value = policyRes.data.data
    companies.value = companyRes.data.data.data ?? companyRes.data.data
    categories.value = categoryRes.data.data.data ?? categoryRes.data.data
  } catch {
    errorMessage.value = 'Gagal memuat data Expense Policy.'
  } finally {
    loading.value = false
  }
}

// ---------- POLICY CREATE/EDIT ----------
const showModal = ref(false)
const editingPolicy = ref<Policy | null>(null)
const saving = ref(false)
const formError = ref('')
const form = reactive({
  company_id: null as number | null,
  name: '',
  description: '',
  effective_date: new Date().toISOString().slice(0, 10),
  expiration_date: '' as string,
  is_active: true,
  selectedCategoryIds: [] as number[],
  limits: {} as Record<number, string>, // categoryId -> limit_amount string (kosong = unlimited)
})

function categoriesForCompany() {
  return categories.value.filter((c) => c.company_id === form.company_id)
}

function openCreate() {
  editingPolicy.value = null
  formError.value = ''
  form.company_id = companies.value[0]?.id ?? null
  form.name = ''
  form.description = ''
  form.effective_date = new Date().toISOString().slice(0, 10)
  form.expiration_date = ''
  form.is_active = true
  form.selectedCategoryIds = []
  form.limits = {}
  showModal.value = true
}

function openEdit(policy: Policy) {
  editingPolicy.value = policy
  formError.value = ''
  form.company_id = policy.company_id
  form.name = policy.name
  form.description = policy.description ?? ''
  form.effective_date = policy.effective_date
  form.expiration_date = policy.expiration_date ?? ''
  form.is_active = policy.is_active
  form.selectedCategoryIds = policy.categories.map((c) => c.id)
  form.limits = {}
  for (const c of policy.categories) {
    if (c.pivot.limit_amount !== null) form.limits[c.id] = c.pivot.limit_amount
  }
  showModal.value = true
}

function toggleCategory(categoryId: number) {
  const idx = form.selectedCategoryIds.indexOf(categoryId)
  if (idx === -1) {
    form.selectedCategoryIds.push(categoryId)
  } else {
    form.selectedCategoryIds.splice(idx, 1)
    delete form.limits[categoryId]
  }
}

async function submitPolicy() {
  if (!form.name.trim() || !form.company_id) {
    formError.value = 'Company dan nama policy wajib diisi.'
    return
  }
  saving.value = true
  formError.value = ''

  const categoryLimits = form.selectedCategoryIds
    .filter((id) => form.limits[id] !== undefined && form.limits[id] !== '')
    .map((id) => ({ expense_category_id: id, limit_amount: Number(form.limits[id]) }))

  const payload = {
    company_id: form.company_id,
    name: form.name,
    description: form.description || null,
    effective_date: form.effective_date,
    expiration_date: form.expiration_date || null,
    is_active: form.is_active,
    category_ids: form.selectedCategoryIds,
    category_limits: categoryLimits,
  }

  try {
    if (editingPolicy.value) {
      await apiClient.put(`/api/expense-policies/${editingPolicy.value.id}`, payload)
    } else {
      await apiClient.post('/api/expense-policies', payload)
    }
    showModal.value = false
    await loadAll()
  } catch (err: any) {
    formError.value = err.response?.data?.message || 'Gagal menyimpan policy.'
  } finally {
    saving.value = false
  }
}

const expandedId = ref<number | null>(null)
function toggleExpand(policy: Policy) {
  expandedId.value = expandedId.value === policy.id ? null : policy.id
}

onMounted(loadAll)
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Expense Policy</h1>
        <p class="mt-1 text-sm text-slate-500">Kelola kebijakan expense, kategori yang diizinkan, dan limit per kategori.</p>
      </div>
      <button
        @click="openCreate"
        :disabled="companies.length === 0"
        class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50"
      >
        <Plus class="h-4 w-4" :stroke-width="2" /> Policy Baru
      </button>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>

    <template v-else>
      <div v-if="policies.length === 0" class="rounded-xl bg-slate-50 p-6 text-center text-sm text-slate-400">
        Belum ada Expense Policy.
      </div>
      <div v-else class="space-y-3">
        <div v-for="policy in policies" :key="policy.id" class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
          <button @click="toggleExpand(policy)" class="flex w-full items-center justify-between px-5 py-4 text-left hover:bg-slate-50/50">
            <div>
              <div class="flex items-center gap-2">
                <p class="font-medium text-slate-800">{{ policy.name }}</p>
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">{{ policy.company.name }}</span>
                <span v-if="!policy.is_active" class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">Nonaktif</span>
              </div>
              <p class="mt-0.5 text-xs text-slate-400">
                Efektif {{ formatDate(policy.effective_date) }}
                <span v-if="policy.expiration_date"> s/d {{ formatDate(policy.expiration_date) }}</span>
                · {{ policy.categories.length }} kategori
              </p>
            </div>
            <div class="flex items-center gap-2">
              <span @click.stop="openEdit(policy)" class="rounded-lg px-2 py-1 text-xs font-medium text-primary hover:bg-primary-soft">Edit</span>
              <ChevronUp v-if="expandedId === policy.id" class="h-4 w-4 text-slate-400" :stroke-width="1.75" />
              <ChevronDown v-else class="h-4 w-4 text-slate-400" :stroke-width="1.75" />
            </div>
          </button>
          <div v-if="expandedId === policy.id" class="border-t border-slate-100 px-5 py-4">
            <p class="mb-2 text-xs font-medium text-slate-500">Kategori & limit</p>
            <div class="flex flex-wrap gap-1.5">
              <span v-for="c in policy.categories" :key="c.id" class="rounded-full bg-primary-soft px-2.5 py-1 text-xs font-medium text-primary-dark">
                {{ c.name }} · {{ formatLimit(c.pivot.limit_amount) }}
              </span>
              <span v-if="policy.categories.length === 0" class="text-xs text-slate-400">Belum ada kategori dipilih untuk policy ini.</span>
            </div>
          </div>
        </div>
      </div>
    </template>

    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8">
        <div class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-2xl bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">{{ editingPolicy ? 'Edit' : 'Policy Baru' }}</h2>
            <button @click="showModal = false" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-50"><X class="h-5 w-5" :stroke-width="1.75" /></button>
          </div>
          <div class="space-y-3 px-6 py-5">
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-700">Company</label>
              <select v-model.number="form.company_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <input v-model="form.name" placeholder="Nama policy (mis. Kebijakan Expense 2026)" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
            <textarea v-model="form.description" placeholder="Deskripsi (opsional)" rows="2" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"></textarea>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="mb-1 block text-xs font-medium text-slate-700">Effective Date</label>
                <input v-model="form.effective_date" type="date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
              <div>
                <label class="mb-1 block text-xs font-medium text-slate-700">Expiration Date</label>
                <input v-model="form.expiration_date" type="date" placeholder="opsional" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-600">
              <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300" /> Aktif
            </label>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-700">Kategori diizinkan &amp; limit (kosong = unlimited)</label>
              <div class="space-y-1.5">
                <div
                  v-for="c in categoriesForCompany()" :key="c.id"
                  class="flex items-center gap-2 rounded-lg border border-slate-200 px-2.5 py-1.5"
                  :class="{ 'border-primary bg-primary-soft/30': form.selectedCategoryIds.includes(c.id) }"
                >
                  <input
                    type="checkbox"
                    :checked="form.selectedCategoryIds.includes(c.id)"
                    @change="toggleCategory(c.id)"
                    class="rounded border-slate-300"
                  />
                  <span class="flex-1 text-xs text-slate-700">{{ c.name }}</span>
                  <input
                    v-if="form.selectedCategoryIds.includes(c.id)"
                    v-model="form.limits[c.id]"
                    type="number" min="0" placeholder="Unlimited"
                    class="w-28 rounded-lg border border-slate-200 px-2 py-1 text-xs focus:border-primary focus:outline-none"
                  />
                </div>
                <p v-if="categoriesForCompany().length === 0" class="text-xs text-slate-400">Belum ada kategori untuk company ini.</p>
              </div>
            </div>
            <div v-if="formError" class="flex items-start gap-2 rounded-xl bg-red-50 p-3 text-xs text-red-600">
              <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" :stroke-width="1.75" />
              <p>{{ formError }}</p>
            </div>
          </div>
          <div class="flex gap-3 border-t border-slate-100 px-6 py-4">
            <button @click="showModal = false" class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50">Batal</button>
            <button @click="submitPolicy" :disabled="saving" class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-primary py-2.5 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50">
              <Loader2 v-if="saving" class="h-4 w-4 animate-spin" :stroke-width="2" /> Simpan
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>