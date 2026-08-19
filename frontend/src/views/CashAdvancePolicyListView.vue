<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue'
import { Plus, X, Loader2, AlertTriangle, ChevronDown, ChevronUp, Trash2 } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface Category { id: number; name: string; code: string; is_active: boolean }
interface Policy {
  id: number
  name: string
  effective_date: string
  settlement_due_days: number | null
  is_active: boolean
  categories: Category[]
}

function formatDate(value: string) {
  return new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

const policies = ref<Policy[]>([])
const categories = ref<Category[]>([])
const loading = ref(true)
const errorMessage = ref('')

async function loadAll() {
  loading.value = true
  errorMessage.value = ''
  try {
    const [policyRes, categoryRes] = await Promise.all([
      apiClient.get('/api/cash-advance-policies'),
      apiClient.get('/api/cash-advance-categories'),
    ])
    policies.value = policyRes.data.data
    categories.value = categoryRes.data.data
  } catch {
    errorMessage.value = 'Gagal memuat data Cash Advance Policy.'
  } finally {
    loading.value = false
  }
}

// ---------- CATEGORY MASTER ----------
const newCategoryName = ref('')
const newCategoryCode = ref('')
const savingCategory = ref(false)

async function addCategory() {
  if (!newCategoryName.value.trim() || !newCategoryCode.value.trim()) return
  savingCategory.value = true
  try {
    await apiClient.post('/api/cash-advance-categories', { name: newCategoryName.value, code: newCategoryCode.value })
    newCategoryName.value = ''
    newCategoryCode.value = ''
    const res = await apiClient.get('/api/cash-advance-categories')
    categories.value = res.data.data
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Gagal menambah kategori.'
  } finally {
    savingCategory.value = false
  }
}
async function toggleCategoryActive(category: Category) {
  try {
    await apiClient.put(`/api/cash-advance-categories/${category.id}`, { is_active: !category.is_active })
    category.is_active = !category.is_active
  } catch {
    errorMessage.value = 'Gagal mengubah status kategori.'
  }
}
async function deleteCategory(category: Category) {
  if (!confirm(`Hapus kategori "${category.name}"?`)) return
  try {
    await apiClient.delete(`/api/cash-advance-categories/${category.id}`)
    categories.value = categories.value.filter((c) => c.id !== category.id)
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Gagal menghapus kategori (mungkin sudah pernah dipakai).'
  }
}

// ---------- POLICY CREATE/EDIT ----------
const showPolicyModal = ref(false)
const editingPolicy = ref<Policy | null>(null)
const savingPolicy = ref(false)
const policyError = ref('')
const policyForm = reactive({
  name: '', effective_date: new Date().toISOString().slice(0, 10),
  settlement_due_days: null as number | null, is_active: true,
  category_ids: [] as number[],
})

function openCreatePolicy() {
  editingPolicy.value = null
  policyError.value = ''
  policyForm.name = ''
  policyForm.effective_date = new Date().toISOString().slice(0, 10)
  policyForm.settlement_due_days = null
  policyForm.is_active = true
  policyForm.category_ids = []
  showPolicyModal.value = true
}
function openEditPolicy(policy: Policy) {
  editingPolicy.value = policy
  policyError.value = ''
  policyForm.name = policy.name
  policyForm.effective_date = policy.effective_date
  policyForm.settlement_due_days = policy.settlement_due_days
  policyForm.is_active = policy.is_active
  policyForm.category_ids = policy.categories.map((c) => c.id)
  showPolicyModal.value = true
}
function toggleCategoryInPolicy(categoryId: number) {
  const idx = policyForm.category_ids.indexOf(categoryId)
  if (idx === -1) policyForm.category_ids.push(categoryId)
  else policyForm.category_ids.splice(idx, 1)
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
    effective_date: policyForm.effective_date,
    settlement_due_days: policyForm.settlement_due_days,
    is_active: policyForm.is_active,
    category_ids: policyForm.category_ids,
  }
  try {
    if (editingPolicy.value) {
      await apiClient.put(`/api/cash-advance-policies/${editingPolicy.value.id}`, payload)
    } else {
      await apiClient.post('/api/cash-advance-policies', payload)
    }
    showPolicyModal.value = false
    await loadAll()
  } catch (err: any) {
    policyError.value = err.response?.data?.message || 'Gagal menyimpan policy.'
  } finally {
    savingPolicy.value = false
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
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Cash Advance Policy</h1>
        <p class="mt-1 text-sm text-slate-500">Kelola policy, settlement due, dan kategori expense yang diizinkan.</p>
      </div>
      <button @click="openCreatePolicy" class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-white hover:bg-primary-dark">
        <Plus class="h-4 w-4" :stroke-width="2" /> Policy Baru
      </button>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>

    <template v-else>
      <!-- Category master -->
      <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <p class="mb-3 text-sm font-medium text-slate-700">Expense Category (Master)</p>
        <div class="mb-3 flex flex-wrap gap-1.5">
          <span
            v-for="c in categories" :key="c.id"
            class="flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium"
            :class="c.is_active ? 'bg-primary-soft text-primary-dark' : 'bg-slate-100 text-slate-400 line-through'"
          >
            <span @click="toggleCategoryActive(c)" class="cursor-pointer">{{ c.name }} ({{ c.code }})</span>
            <button @click="deleteCategory(c)" class="text-current opacity-60 hover:opacity-100"><Trash2 class="h-3 w-3" :stroke-width="2" /></button>
          </span>
          <span v-if="categories.length === 0" class="text-xs text-slate-400">Belum ada kategori.</span>
        </div>
        <div class="flex gap-2">
          <input v-model="newCategoryName" placeholder="Nama kategori (mis. Transport)" class="flex-1 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs focus:border-primary focus:outline-none" />
          <input v-model="newCategoryCode" placeholder="Kode (mis. TRANSPORT)" class="w-40 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs focus:border-primary focus:outline-none" />
          <button @click="addCategory" :disabled="savingCategory" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-200">Tambah</button>
        </div>
      </div>

      <!-- Policies -->
      <div v-if="policies.length === 0" class="rounded-xl bg-slate-50 p-6 text-center text-sm text-slate-400">Belum ada Cash Advance Policy.</div>
      <div v-else class="space-y-3">
        <div v-for="policy in policies" :key="policy.id" class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
          <button @click="toggleExpand(policy)" class="flex w-full items-center justify-between px-5 py-4 text-left hover:bg-slate-50/50">
            <div>
              <div class="flex items-center gap-2">
                <p class="font-medium text-slate-800">{{ policy.name }}</p>
                <span v-if="!policy.is_active" class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">Nonaktif</span>
              </div>
              <p class="mt-0.5 text-xs text-slate-400">
                Efektif {{ formatDate(policy.effective_date) }}
                <span v-if="policy.settlement_due_days"> · Settlement due {{ policy.settlement_due_days }} hari</span>
                · {{ policy.categories.length }} kategori
              </p>
            </div>
            <div class="flex items-center gap-2">
              <span @click.stop="openEditPolicy(policy)" class="rounded-lg px-2 py-1 text-xs font-medium text-primary hover:bg-primary-soft">Edit</span>
              <ChevronUp v-if="expandedId === policy.id" class="h-4 w-4 text-slate-400" :stroke-width="1.75" />
              <ChevronDown v-else class="h-4 w-4 text-slate-400" :stroke-width="1.75" />
            </div>
          </button>
          <div v-if="expandedId === policy.id" class="border-t border-slate-100 px-5 py-4">
            <p class="mb-2 text-xs font-medium text-slate-500">Kategori diizinkan</p>
            <div class="flex flex-wrap gap-1.5">
              <span v-for="c in policy.categories" :key="c.id" class="rounded-full bg-primary-soft px-2.5 py-1 text-xs font-medium text-primary-dark">{{ c.name }}</span>
              <span v-if="policy.categories.length === 0" class="text-xs text-slate-400">Belum ada kategori dipilih untuk policy ini.</span>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Policy modal -->
    <Teleport to="body">
      <div v-if="showPolicyModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8">
        <div class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-2xl bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">{{ editingPolicy ? 'Edit' : 'Policy Baru' }}</h2>
            <button @click="showPolicyModal = false" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-50"><X class="h-5 w-5" :stroke-width="1.75" /></button>
          </div>
          <div class="space-y-3 px-6 py-5">
            <input v-model="policyForm.name" placeholder="Nama policy (mis. Business Trip)" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="mb-1 block text-xs font-medium text-slate-700">Effective Date</label>
                <input v-model="policyForm.effective_date" type="date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
              <div>
                <label class="mb-1 block text-xs font-medium text-slate-700">Settlement Due (hari)</label>
                <input v-model.number="policyForm.settlement_due_days" type="number" min="1" placeholder="opsional" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-600">
              <input v-model="policyForm.is_active" type="checkbox" class="rounded border-slate-300" /> Aktif
            </label>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-700">Kategori diizinkan</label>
              <div class="flex flex-wrap gap-1.5">
                <span
                  v-for="c in categories" :key="c.id" @click="toggleCategoryInPolicy(c.id)"
                  class="cursor-pointer rounded-full px-2.5 py-1 text-xs font-medium"
                  :class="policyForm.category_ids.includes(c.id) ? 'bg-primary text-white' : 'bg-slate-100 text-slate-500'"
                >
                  {{ c.name }}
                </span>
              </div>
            </div>
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
  </div>
</template>