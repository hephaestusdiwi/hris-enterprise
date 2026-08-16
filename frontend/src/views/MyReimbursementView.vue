<script setup lang="ts">
import { ref, onMounted, reactive, computed } from 'vue'
import { Plus, X, Loader2, AlertTriangle, Paperclip, Trash2, Wallet, Receipt } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

type ReimbursementStatus = 'pending' | 'approved' | 'rejected' | 'cancelled'

interface Benefit { id: number; name: string; is_active: boolean }
interface Policy { id: number; name: string; benefits: Benefit[] }
interface BalanceRow {
  id: number
  assigned_amount: string | null
  effective_date: string
  expiration_date: string | null
  status: 'active' | 'stopped'
  policy: Policy
}
interface Item { id: number; amount: string; notes: string | null; benefit: { id: number; name: string } }
interface Attachment { id: number; file_name: string; url: string | null }
interface ReimbursementRow {
  id: number
  transaction_date: string
  total_amount: string
  notes: string | null
  status: ReimbursementStatus
  disbursed_at: string | null
  policy: { id: number; name: string }
  items: Item[]
  attachments: Attachment[]
}

const statusLabels: Record<ReimbursementStatus, string> = {
  pending: 'Menunggu Approval', approved: 'Approved', rejected: 'Ditolak', cancelled: 'Dibatalkan',
}
const statusBadgeClass: Record<ReimbursementStatus, string> = {
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

const balances = ref<BalanceRow[]>([])
const requests = ref<ReimbursementRow[]>([])
const loading = ref(true)
const errorMessage = ref('')

async function loadAll() {
  loading.value = true
  errorMessage.value = ''
  try {
    const [balanceRes, reqRes] = await Promise.all([
      apiClient.get('/api/my-reimbursement-balances'),
      apiClient.get('/api/my-reimbursements'),
    ])
    balances.value = balanceRes.data.data
    requests.value = reqRes.data.data.data
  } catch {
    errorMessage.value = 'Gagal memuat data reimbursement.'
  } finally {
    loading.value = false
  }
}

// ---------- SUBMIT MODAL ----------
const MAX_ATTACHMENTS = 5
const showModal = ref(false)
const saving = ref(false)
const formError = ref('')

const form = reactive({
  reimbursement_balance_id: null as number | null,
  transaction_date: new Date().toISOString().slice(0, 10),
  notes: '',
  items: [{ reimbursement_benefit_id: null as number | null, amount: null as number | null, notes: '' }],
  attachments: [] as File[],
})

const selectedBalance = computed(() => balances.value.find((b) => b.id === form.reimbursement_balance_id) ?? null)
const availableBenefits = computed(() => selectedBalance.value?.policy.benefits.filter((b) => b.is_active) ?? [])
const usableBalances = computed(() => balances.value.filter((b) => b.status === 'active'))
const totalAmount = computed(() => form.items.reduce((sum, i) => sum + (i.amount ?? 0), 0))

function openCreateModal() {
  formError.value = ''
  form.reimbursement_balance_id = usableBalances.value[0]?.id ?? null
  form.transaction_date = new Date().toISOString().slice(0, 10)
  form.notes = ''
  form.items = [{ reimbursement_benefit_id: null, amount: null, notes: '' }]
  form.attachments = []
  showModal.value = true
}
function closeModal() {
  showModal.value = false
}
function addItem() {
  form.items.push({ reimbursement_benefit_id: null, amount: null, notes: '' })
}
function removeItem(index: number) {
  if (form.items.length > 1) form.items.splice(index, 1)
}
function handleFilesChange(event: Event) {
  const files = Array.from((event.target as HTMLInputElement).files ?? [])
  const combined = [...form.attachments, ...files]
  if (combined.length > MAX_ATTACHMENTS) {
    formError.value = `Maksimal ${MAX_ATTACHMENTS} file attachment per request.`
    return
  }
  formError.value = ''
  form.attachments = combined
  ;(event.target as HTMLInputElement).value = ''
}
function removeAttachment(index: number) {
  form.attachments = form.attachments.filter((_, i) => i !== index)
}

async function submitRequest() {
  formError.value = ''
  if (!form.reimbursement_balance_id) {
    formError.value = 'Pilih balance dulu.'
    return
  }
  if (form.items.some((i) => !i.reimbursement_benefit_id || !i.amount)) {
    formError.value = 'Setiap item harus punya benefit dan amount.'
    return
  }

  saving.value = true
  const formData = new FormData()
  formData.append('reimbursement_balance_id', String(form.reimbursement_balance_id))
  formData.append('transaction_date', form.transaction_date)
  if (form.notes) formData.append('notes', form.notes)
  form.items.forEach((item, index) => {
    formData.append(`items[${index}][reimbursement_benefit_id]`, String(item.reimbursement_benefit_id))
    formData.append(`items[${index}][amount]`, String(item.amount))
    if (item.notes) formData.append(`items[${index}][notes]`, item.notes)
  })
  form.attachments.forEach((file) => formData.append('attachments[]', file))

  try {
    await apiClient.post('/api/my-reimbursements', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    showModal.value = false
    await loadAll()
  } catch (err: any) {
    formError.value = err.response?.data?.message || 'Gagal mengajukan reimbursement.'
  } finally {
    saving.value = false
  }
}

onMounted(loadAll)
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Reimbursement Saya</h1>
        <p class="mt-1 text-sm text-slate-500">Balance, pengajuan, dan riwayat reimbursement Anda.</p>
      </div>
      <button
        @click="openCreateModal"
        :disabled="usableBalances.length === 0"
        class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50"
      >
        <Plus class="h-4 w-4" :stroke-width="2" /> Ajukan Reimbursement
      </button>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>

    <template v-else>
      <!-- Balance cards -->
      <div v-if="balances.length === 0" class="rounded-xl bg-slate-50 p-6 text-center text-sm text-slate-400">
        Anda belum memiliki reimbursement balance yang di-assign.
      </div>
      <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <div v-for="b in balances" :key="b.id" class="rounded-2xl border border-slate-100 bg-white p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
          <div class="flex items-center gap-2">
            <Wallet class="h-4 w-4 text-primary-dark" :stroke-width="1.75" />
            <p class="text-sm font-medium text-slate-800">{{ b.policy.name }}</p>
            <span v-if="b.status === 'stopped'" class="ml-auto rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">Dihentikan</span>
          </div>
          <p class="mt-2 text-xl font-semibold text-slate-900">
            {{ b.assigned_amount === null ? 'Unlimited' : formatCurrency(b.assigned_amount) }}
          </p>
          <p class="mt-1 text-xs text-slate-400">
            Efektif {{ formatDate(b.effective_date) }}<span v-if="b.expiration_date"> s.d. {{ formatDate(b.expiration_date) }}</span>
          </p>
        </div>
      </div>

      <!-- Request history -->
      <div class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <table class="w-full text-left text-sm">
          <thead>
            <tr class="border-b border-slate-100 bg-slate-50/60">
              <th class="px-5 py-3 font-medium text-slate-500">Tanggal</th>
              <th class="px-5 py-3 font-medium text-slate-500">Policy / Item</th>
              <th class="px-5 py-3 text-right font-medium text-slate-500">Total</th>
              <th class="px-5 py-3 font-medium text-slate-500">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="requests.length === 0">
              <td colspan="4" class="px-5 py-6 text-center text-sm text-slate-400">Belum ada pengajuan reimbursement.</td>
            </tr>
            <tr v-for="r in requests" :key="r.id" class="border-b border-slate-50 last:border-0 hover:bg-slate-50/50">
              <td class="px-5 py-3.5 text-slate-600">{{ formatDate(r.transaction_date) }}</td>
              <td class="px-5 py-3.5">
                <p class="font-medium text-slate-800">{{ r.policy.name }}</p>
                <p class="text-xs text-slate-400">{{ r.items.map(i => i.benefit.name).join(', ') }}</p>
              </td>
              <td class="px-5 py-3.5 text-right font-medium text-slate-700">{{ formatCurrency(r.total_amount) }}</td>
              <td class="px-5 py-3.5">
                <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusBadgeClass[r.status]">{{ statusLabels[r.status] }}</span>
                <span v-if="r.disbursed_at" class="ml-1.5 text-xs text-emerald-600">· Dibayar</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <!-- Submit modal -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8">
        <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Ajukan Reimbursement</h2>
            <button @click="closeModal" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-50"><X class="h-5 w-5" :stroke-width="1.75" /></button>
          </div>

          <div class="space-y-4 px-6 py-5">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Balance / Policy</label>
              <select v-model="form.reimbursement_balance_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                <option v-for="b in usableBalances" :key="b.id" :value="b.id">{{ b.policy.name }}</option>
              </select>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Tanggal Transaksi</label>
              <input v-model="form.transaction_date" type="date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
            </div>

            <div>
              <div class="mb-1 flex items-center justify-between">
                <label class="block text-sm font-medium text-slate-700">Item</label>
                <button @click="addItem" type="button" class="text-xs font-medium text-primary hover:underline">+ Tambah item</button>
              </div>
              <div v-for="(item, index) in form.items" :key="index" class="mb-2 rounded-xl border border-slate-200 p-3">
                <div class="flex items-start gap-2">
                  <select v-model="item.reimbursement_benefit_id" class="w-1/2 rounded-lg border border-slate-200 px-2 py-1.5 text-sm focus:border-primary focus:outline-none">
                    <option :value="null" disabled>Pilih benefit</option>
                    <option v-for="benefit in availableBenefits" :key="benefit.id" :value="benefit.id">{{ benefit.name }}</option>
                  </select>
                  <input v-model.number="item.amount" type="number" min="1" placeholder="Amount" class="w-1/2 rounded-lg border border-slate-200 px-2 py-1.5 text-sm focus:border-primary focus:outline-none" />
                  <button v-if="form.items.length > 1" @click="removeItem(index)" type="button" class="mt-1 shrink-0 text-slate-400 hover:text-red-500">
                    <Trash2 class="h-4 w-4" :stroke-width="1.75" />
                  </button>
                </div>
                <input v-model="item.notes" placeholder="Catatan item (opsional)" class="mt-2 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-primary focus:outline-none" />
              </div>
              <p class="text-right text-xs text-slate-400">Total: {{ formatCurrency(totalAmount) }}</p>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Catatan (opsional)</label>
              <textarea v-model="form.notes" rows="2" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"></textarea>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Attachment</label>
              <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-slate-300 py-3 text-sm text-slate-500 hover:bg-slate-50">
                <Paperclip class="h-4 w-4" :stroke-width="1.75" /> Pilih file
                <input type="file" multiple class="hidden" @change="handleFilesChange" />
              </label>
              <ul v-if="form.attachments.length > 0" class="mt-2 space-y-1.5">
                <li v-for="(file, index) in form.attachments" :key="index" class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-1.5 text-xs text-slate-600">
                  {{ file.name }}
                  <button @click="removeAttachment(index)" type="button" class="text-slate-400 hover:text-red-500"><X class="h-3.5 w-3.5" :stroke-width="2" /></button>
                </li>
              </ul>
            </div>

            <div v-if="formError" class="flex items-start gap-2 rounded-xl bg-red-50 p-3 text-xs text-red-600">
              <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" :stroke-width="1.75" />
              <p>{{ formError }}</p>
            </div>
          </div>

          <div class="flex gap-3 border-t border-slate-100 px-6 py-4">
            <button @click="closeModal" class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50">Batal</button>
            <button @click="submitRequest" :disabled="saving" class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-primary py-2.5 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50">
              <Loader2 v-if="saving" class="h-4 w-4 animate-spin" :stroke-width="2" />
              {{ saving ? 'Mengirim...' : 'Ajukan' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>