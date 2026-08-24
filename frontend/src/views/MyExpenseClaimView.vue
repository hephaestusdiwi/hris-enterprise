<script setup lang="ts">
import { ref, onMounted, reactive, computed } from 'vue'
import { Plus, X, Loader2, AlertTriangle, Paperclip, Receipt } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

type ClaimStatus = 'pending' | 'approved' | 'rejected' | 'cancelled'

interface Category { id: number; name: string; company_id: number }
interface Subcategory { id: number; name: string; expense_category_id: number }
interface Attachment { id: number; file_name: string; url: string | null }
interface StepDecision { id: number; sequence: number; status: string; approval_step: { name: string | null } }
interface ClaimRow {
  id: number
  expense_date: string
  amount: string
  description: string | null
  status: ClaimStatus
  cancel_reason: string | null
  category: { id: number; name: string }
  subcategory: { id: number; name: string } | null
  attachments: Attachment[]
  approval_request?: { step_decisions: StepDecision[] } | null
}

const statusLabels: Record<ClaimStatus, string> = {
  pending: 'Menunggu Approval', approved: 'Approved', rejected: 'Ditolak', cancelled: 'Dibatalkan',
}
const statusBadgeClass: Record<ClaimStatus, string> = {
  pending: 'bg-amber-50 text-amber-600',
  approved: 'bg-primary-soft text-primary-dark',
  rejected: 'bg-red-50 text-red-600',
  cancelled: 'bg-slate-100 text-slate-500',
}

function formatCurrency(value: string | number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(Number(value))
}
function formatDate(value: string) {
  return new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

const claims = ref<ClaimRow[]>([])
const categories = ref<Category[]>([])
const subcategories = ref<Subcategory[]>([])
const loading = ref(true)
const errorMessage = ref('')

async function loadAll() {
  loading.value = true
  errorMessage.value = ''
  try {
    const [claimRes, categoryRes, subcategoryRes] = await Promise.all([
      apiClient.get('/api/my-expense-claims'),
      apiClient.get('/api/expense-categories', { params: { per_page: 100 } }),
      apiClient.get('/api/expense-subcategories', { params: { per_page: 200 } }),
    ])
    claims.value = claimRes.data.data.data
    // Categories tidak difilter company di backend (admin bisa kelola
    // multi-company) -- filter client-side ke company sendiri saja,
    // backend tetap yang menentukan validasi akhir.
    const myCompanyId = categoryRes.data.data.data?.[0]?.company_id
    categories.value = (categoryRes.data.data.data ?? categoryRes.data.data).filter(
      (c: Category) => !myCompanyId || c.company_id === myCompanyId
    )
    subcategories.value = subcategoryRes.data.data.data ?? subcategoryRes.data.data
  } catch {
    errorMessage.value = 'Gagal memuat data Expense Claim.'
  } finally {
    loading.value = false
  }
}

// ---------- CREATE ----------
const showCreateModal = ref(false)
const saving = ref(false)
const formError = ref('')
const form = reactive({
  expense_category_id: null as number | null,
  expense_subcategory_id: null as number | null,
  expense_date: new Date().toISOString().slice(0, 10),
  amount: '',
  description: '',
  attachments: [] as File[],
})

const subcategoriesForSelectedCategory = computed(() =>
  subcategories.value.filter((s) => s.expense_category_id === form.expense_category_id)
)

function openCreateModal() {
  formError.value = ''
  form.expense_category_id = null
  form.expense_subcategory_id = null
  form.expense_date = new Date().toISOString().slice(0, 10)
  form.amount = ''
  form.description = ''
  form.attachments = []
  showCreateModal.value = true
}

function handleFileChange(event: Event) {
  const input = event.target as HTMLInputElement
  if (input.files) form.attachments = Array.from(input.files)
}

async function handleCreate() {
  if (!form.expense_category_id || !form.expense_date || !form.amount) {
    formError.value = 'Category, tanggal, dan amount wajib diisi.'
    return
  }
  saving.value = true
  formError.value = ''

  const formData = new FormData()
  formData.append('expense_category_id', String(form.expense_category_id))
  if (form.expense_subcategory_id) formData.append('expense_subcategory_id', String(form.expense_subcategory_id))
  formData.append('expense_date', form.expense_date)
  formData.append('amount', form.amount)
  if (form.description) formData.append('description', form.description)
  form.attachments.forEach((file) => formData.append('attachments[]', file))

  try {
    await apiClient.post('/api/my-expense-claims', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    showCreateModal.value = false
    await loadAll()
  } catch (err: any) {
    formError.value = err.response?.data?.message || 'Gagal mengajukan Expense Claim. Cek kembali policy/limit yang berlaku.'
  } finally {
    saving.value = false
  }
}

// ---------- CANCEL ----------
const cancellingId = ref<number | null>(null)

async function handleCancel(claim: ClaimRow) {
  const reason = window.prompt('Alasan pembatalan:')
  if (!reason) return

  cancellingId.value = claim.id
  try {
    await apiClient.post(`/api/expense-claims/${claim.id}/cancel`, { reason })
    await loadAll()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Gagal membatalkan claim.'
  } finally {
    cancellingId.value = null
  }
}

function canCancel(claim: ClaimRow) {
  return claim.status === 'pending' || claim.status === 'approved'
}

onMounted(loadAll)
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">My Expense Claims</h1>
        <p class="mt-1 text-sm text-slate-500">Ajukan dan pantau klaim expense kamu.</p>
      </div>
      <button
        @click="openCreateModal"
        :disabled="categories.length === 0"
        class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50"
      >
        <Plus class="h-4 w-4" :stroke-width="2" /> Create Expense
      </button>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>

    <template v-else>
      <div v-if="claims.length === 0" class="rounded-xl bg-slate-50 p-8 text-center">
        <Receipt class="mx-auto h-8 w-8 text-slate-300" :stroke-width="1.5" />
        <p class="mt-2 text-sm text-slate-400">Belum ada Expense Claim. Klik "Create Expense" untuk mulai.</p>
      </div>

      <div v-else class="space-y-3">
        <div v-for="claim in claims" :key="claim.id" class="rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
          <div class="flex items-start justify-between">
            <div>
              <div class="flex items-center gap-2">
                <p class="font-medium text-slate-800">{{ claim.category.name }}</p>
                <span v-if="claim.subcategory" class="text-xs text-slate-400">/ {{ claim.subcategory.name }}</span>
              </div>
              <p class="mt-0.5 text-xs text-slate-400">{{ formatDate(claim.expense_date) }}</p>
              <p v-if="claim.description" class="mt-1.5 text-sm text-slate-600">{{ claim.description }}</p>
            </div>
            <div class="text-right">
              <p class="font-semibold text-slate-800">{{ formatCurrency(claim.amount) }}</p>
              <span class="mt-1 inline-block rounded-full px-2.5 py-1 text-xs font-medium" :class="statusBadgeClass[claim.status]">
                {{ statusLabels[claim.status] }}
              </span>
            </div>
          </div>

          <div v-if="claim.attachments.length > 0" class="mt-3 flex flex-wrap gap-2">
            <a
              v-for="a in claim.attachments" :key="a.id" :href="a.url ?? '#'" target="_blank"
              class="flex items-center gap-1 rounded-lg bg-slate-50 px-2.5 py-1 text-xs text-slate-500 hover:bg-slate-100"
            >
              <Paperclip class="h-3 w-3" :stroke-width="1.75" /> {{ a.file_name }}
            </a>
          </div>

          <p v-if="claim.status === 'cancelled' && claim.cancel_reason" class="mt-2 text-xs text-slate-400">
            Alasan dibatalkan: {{ claim.cancel_reason }}
          </p>

          <div v-if="canCancel(claim)" class="mt-3 flex justify-end">
            <button
              @click="handleCancel(claim)"
              :disabled="cancellingId === claim.id"
              class="rounded-lg px-3 py-1.5 text-xs font-medium text-red-500 hover:bg-red-50 disabled:opacity-50"
            >
              {{ cancellingId === claim.id ? 'Membatalkan...' : 'Batalkan' }}
            </button>
          </div>
        </div>
      </div>
    </template>

    <Teleport to="body">
      <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8">
        <div class="flex max-h-full w-full max-w-md flex-col rounded-2xl bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Create Expense</h2>
            <button @click="showCreateModal = false" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-50"><X class="h-5 w-5" :stroke-width="1.75" /></button>
          </div>
          <form @submit.prevent="handleCreate" class="flex-1 space-y-3 overflow-y-auto px-6 py-5">
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-700">Category</label>
              <select v-model.number="form.expense_category_id" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                <option :value="null" disabled>Pilih category</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div v-if="form.expense_category_id">
              <label class="mb-1 block text-xs font-medium text-slate-700">Subcategory (opsional)</label>
              <select v-model.number="form.expense_subcategory_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                <option :value="null">Tidak ada</option>
                <option v-for="s in subcategoriesForSelectedCategory" :key="s.id" :value="s.id">{{ s.name }}</option>
              </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="mb-1 block text-xs font-medium text-slate-700">Expense Date</label>
                <input v-model="form.expense_date" type="date" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
              <div>
                <label class="mb-1 block text-xs font-medium text-slate-700">Amount</label>
                <input v-model="form.amount" type="number" min="0.01" step="0.01" required placeholder="0" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
            </div>
            <textarea v-model="form.description" placeholder="Deskripsi (opsional)" rows="2" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"></textarea>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-700">Attachment (opsional, maks 5 file, 5MB/file)</label>
              <input type="file" multiple accept=".jpg,.jpeg,.pdf,.csv,.xlsx" @change="handleFileChange" class="w-full text-xs text-slate-500" />
            </div>
            <div v-if="formError" class="flex items-start gap-2 rounded-xl bg-red-50 p-3 text-xs text-red-600">
              <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" :stroke-width="1.75" />
              <p>{{ formError }}</p>
            </div>
          </form>
          <div class="border-t border-slate-100 px-6 py-4">
            <button @click="handleCreate" :disabled="saving" class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary py-2.5 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50">
              <Loader2 v-if="saving" class="h-4 w-4 animate-spin" :stroke-width="2" /> Submit
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>