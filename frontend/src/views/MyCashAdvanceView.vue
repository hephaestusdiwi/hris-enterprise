<script setup lang="ts">
import { ref, onMounted, reactive, computed } from 'vue'
import { Plus, X, Loader2, AlertTriangle, Paperclip, Trash2, Receipt, CheckCircle2 } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

type CaStatus = 'pending_approval' | 'approved' | 'rejected' | 'cancelled' | 'need_settlement' | 'settlement_on_review' | 'completed'

interface Category { id: number; name: string; code: string; is_active: boolean }
interface Policy { id: number; name: string; categories: Category[]; settlement_due_days: number | null }
interface Item { id: number; name: string; description: string | null; amount: string; category: { id: number; name: string } }
interface Attachment { id: number; file_name: string; url: string | null }
interface StepDecision { id: number; sequence: number; status: string; approval_step: { name: string | null } }
interface SettlementItem { id: number; description: string; actual_amount: string; returned_amount: string; cash_advance_request_item_id: number | null; category: { id: number; name: string } }
interface Settlement {
  id: number; status: 'pending' | 'approved' | 'rejected'; total_actual_amount: string; total_returned_amount: string
  submitted_at: string; rejected_at: string | null; items: SettlementItem[]; attachments: Attachment[]
  approval_request?: { step_decisions: StepDecision[] } | null
}
interface CashAdvanceRow {
  id: number
  purpose: string
  date_of_use: string
  total_amount: string
  status: CaStatus
  submitted_at: string
  policy: { id: number; name: string }
  items: Item[]
  attachments: Attachment[]
  approval_request?: { step_decisions: StepDecision[] } | null
  settlements: Settlement[]
}

const statusLabels: Record<CaStatus, string> = {
  pending_approval: 'Menunggu Approval', approved: 'Approved', rejected: 'Ditolak', cancelled: 'Dibatalkan',
  need_settlement: 'Perlu Settlement', settlement_on_review: 'Settlement Direview', completed: 'Selesai',
}
const statusBadgeClass: Record<CaStatus, string> = {
  pending_approval: 'bg-amber-50 text-amber-600',
  approved: 'bg-primary-soft text-primary-dark',
  rejected: 'bg-red-50 text-red-600',
  cancelled: 'bg-slate-100 text-slate-500',
  need_settlement: 'bg-orange-50 text-orange-600',
  settlement_on_review: 'bg-amber-50 text-amber-600',
  completed: 'bg-emerald-50 text-emerald-600',
}

function formatCurrency(value: string | number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(Number(value))
}
function formatDate(value: string) {
  return new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

const cashAdvances = ref<CashAdvanceRow[]>([])
const policies = ref<Policy[]>([])
const loading = ref(true)
const errorMessage = ref('')

async function loadAll() {
  loading.value = true
  errorMessage.value = ''
  try {
    const [listRes, policyRes] = await Promise.all([
      apiClient.get('/api/my-cash-advances'),
      apiClient.get('/api/cash-advance-policies'),
    ])
    cashAdvances.value = listRes.data.data.data
    policies.value = policyRes.data.data.filter((p: any) => p.is_active)
  } catch {
    errorMessage.value = 'Gagal memuat data Cash Advance.'
  } finally {
    loading.value = false
  }
}

// ---------- SUBMIT MODAL ----------
const MAX_ATTACHMENTS = 5
const showCreateModal = ref(false)
const saving = ref(false)
const formError = ref('')
const form = reactive({
  cash_advance_policy_id: null as number | null,
  purpose: '',
  date_of_use: new Date().toISOString().slice(0, 10),
  notes: '',
  items: [{ name: '', description: '', cash_advance_category_id: null as number | null, amount: null as number | null }],
  attachments: [] as File[],
})
const selectedPolicy = computed(() => policies.value.find((p) => p.id === form.cash_advance_policy_id) ?? null)
const availableCategories = computed(() => selectedPolicy.value?.categories.filter((c) => c.is_active) ?? [])
const createTotal = computed(() => form.items.reduce((sum, i) => sum + (i.amount ?? 0), 0))

function openCreateModal() {
  formError.value = ''
  form.cash_advance_policy_id = policies.value[0]?.id ?? null
  form.purpose = ''
  form.date_of_use = new Date().toISOString().slice(0, 10)
  form.notes = ''
  form.items = [{ name: '', description: '', cash_advance_category_id: null, amount: null }]
  form.attachments = []
  showCreateModal.value = true
}
function addItem() {
  form.items.push({ name: '', description: '', cash_advance_category_id: null, amount: null })
}
function removeItem(index: number) {
  if (form.items.length > 1) form.items.splice(index, 1)
}
function handleFilesChange(event: Event) {
  const files = Array.from((event.target as HTMLInputElement).files ?? [])
  const combined = [...form.attachments, ...files]
  if (combined.length > MAX_ATTACHMENTS) {
    formError.value = `Maksimal ${MAX_ATTACHMENTS} file attachment.`
    return
  }
  form.attachments = combined
  ;(event.target as HTMLInputElement).value = ''
}
function removeAttachment(index: number) {
  form.attachments = form.attachments.filter((_, i) => i !== index)
}

async function submitCreate() {
  formError.value = ''
  if (!form.cash_advance_policy_id || !form.purpose.trim()) {
    formError.value = 'Policy dan Purpose wajib diisi.'
    return
  }
  if (form.items.some((i) => !i.name.trim() || !i.cash_advance_category_id || !i.amount)) {
    formError.value = 'Setiap detail harus punya nama, kategori, dan amount.'
    return
  }
  saving.value = true
  const formData = new FormData()
  formData.append('cash_advance_policy_id', String(form.cash_advance_policy_id))
  formData.append('purpose', form.purpose)
  formData.append('date_of_use', form.date_of_use)
  if (form.notes) formData.append('notes', form.notes)
  form.items.forEach((item, index) => {
    formData.append(`items[${index}][name]`, item.name)
    formData.append(`items[${index}][cash_advance_category_id]`, String(item.cash_advance_category_id))
    formData.append(`items[${index}][amount]`, String(item.amount))
    if (item.description) formData.append(`items[${index}][description]`, item.description)
  })
  form.attachments.forEach((file) => formData.append('attachments[]', file))

  try {
    await apiClient.post('/api/cash-advances', formData, { headers: { 'Content-Type': 'multipart/form-data' } })
    showCreateModal.value = false
    await loadAll()
  } catch (err: any) {
    formError.value = err.response?.data?.message || 'Gagal mengajukan Cash Advance.'
  } finally {
    saving.value = false
  }
}

// ---------- DETAIL / SETTLEMENT ----------
const showDetail = ref(false)
const detailTarget = ref<CashAdvanceRow | null>(null)
const showSettlementForm = ref(false)
const settlementSaving = ref(false)
const settlementError = ref('')
const settlementForm = reactive({
  notes: '',
  items: [] as { cash_advance_request_item_id: number; cash_advance_category_id: number; description: string; actual_amount: number | null; returned_amount: number | null }[],
  attachments: [] as File[],
})

function openDetail(row: CashAdvanceRow) {
  detailTarget.value = row
  showSettlementForm.value = false
  settlementError.value = ''
  showDetail.value = true
}
function closeDetail() {
  showDetail.value = false
  detailTarget.value = null
}
function latestSettlement(row: CashAdvanceRow): Settlement | null {
  return row.settlements.length > 0 ? row.settlements[row.settlements.length - 1] : null
}

function openSettlementForm(row: CashAdvanceRow) {
  settlementError.value = ''
  settlementForm.notes = ''
  settlementForm.items = row.items.map((item) => ({
    cash_advance_request_item_id: item.id,
    cash_advance_category_id: item.category.id,
    description: item.name,
    actual_amount: Number(item.amount),
    returned_amount: 0,
  }))
  settlementForm.attachments = []
  showSettlementForm.value = true
}
function handleSettlementFiles(event: Event) {
  const files = Array.from((event.target as HTMLInputElement).files ?? [])
  settlementForm.attachments = [...settlementForm.attachments, ...files].slice(0, MAX_ATTACHMENTS)
  ;(event.target as HTMLInputElement).value = ''
}
const settlementTotalActual = computed(() => settlementForm.items.reduce((sum, i) => sum + (i.actual_amount ?? 0), 0))
const settlementTotalReturned = computed(() => settlementForm.items.reduce((sum, i) => sum + (i.returned_amount ?? 0), 0))

async function submitSettlement() {
  if (!detailTarget.value) return
  settlementSaving.value = true
  settlementError.value = ''
  const formData = new FormData()
  if (settlementForm.notes) formData.append('notes', settlementForm.notes)
  settlementForm.items.forEach((item, index) => {
    formData.append(`items[${index}][cash_advance_request_item_id]`, String(item.cash_advance_request_item_id))
    formData.append(`items[${index}][cash_advance_category_id]`, String(item.cash_advance_category_id))
    formData.append(`items[${index}][description]`, item.description)
    formData.append(`items[${index}][actual_amount]`, String(item.actual_amount ?? 0))
    formData.append(`items[${index}][returned_amount]`, String(item.returned_amount ?? 0))
  })
  settlementForm.attachments.forEach((file) => formData.append('attachments[]', file))

  try {
    await apiClient.post(`/api/cash-advances/${detailTarget.value.id}/settlement`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    showSettlementForm.value = false
    await loadAll()
    closeDetail()
  } catch (err: any) {
    settlementError.value = err.response?.data?.message || 'Gagal mengajukan settlement.'
  } finally {
    settlementSaving.value = false
  }
}

onMounted(loadAll)
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Cash Advance Saya</h1>
        <p class="mt-1 text-sm text-slate-500">Pengajuan, approval, disbursement, dan settlement Cash Advance Anda.</p>
      </div>
      <button @click="openCreateModal" :disabled="policies.length === 0" class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50">
        <Plus class="h-4 w-4" :stroke-width="2" /> Ajukan Cash Advance
      </button>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>

    <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <table class="w-full text-left text-sm">
        <thead>
          <tr class="border-b border-slate-100 bg-slate-50/60">
            <th class="px-5 py-3 font-medium text-slate-500">Purpose</th>
            <th class="px-5 py-3 font-medium text-slate-500">Policy</th>
            <th class="px-5 py-3 font-medium text-slate-500">Tanggal Pakai</th>
            <th class="px-5 py-3 text-right font-medium text-slate-500">Total</th>
            <th class="px-5 py-3 font-medium text-slate-500">Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="cashAdvances.length === 0"><td colspan="5" class="px-5 py-6 text-center text-sm text-slate-400">Belum ada pengajuan Cash Advance.</td></tr>
          <tr v-for="row in cashAdvances" :key="row.id" @click="openDetail(row)" class="cursor-pointer border-b border-slate-50 last:border-0 hover:bg-slate-50/50">
            <td class="px-5 py-3.5 font-medium text-slate-800">{{ row.purpose }}</td>
            <td class="px-5 py-3.5 text-slate-500">{{ row.policy.name }}</td>
            <td class="px-5 py-3.5 text-slate-500">{{ formatDate(row.date_of_use) }}</td>
            <td class="px-5 py-3.5 text-right font-medium text-slate-700">{{ formatCurrency(row.total_amount) }}</td>
            <td class="px-5 py-3.5"><span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusBadgeClass[row.status]">{{ statusLabels[row.status] }}</span></td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create modal -->
    <Teleport to="body">
      <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8">
        <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Ajukan Cash Advance</h2>
            <button @click="showCreateModal = false" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-50"><X class="h-5 w-5" :stroke-width="1.75" /></button>
          </div>
          <div class="space-y-4 px-6 py-5">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Policy</label>
              <select v-model="form.cash_advance_policy_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                <option v-for="p in policies" :key="p.id" :value="p.id">{{ p.name }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Purpose</label>
              <input v-model="form.purpose" placeholder="mis. Perjalanan dinas Jakarta" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Tanggal Pakai</label>
              <input v-model="form.date_of_use" type="date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
            </div>

            <div>
              <div class="mb-1 flex items-center justify-between">
                <label class="block text-sm font-medium text-slate-700">Cash Advance Details</label>
                <button @click="addItem" type="button" class="text-xs font-medium text-primary hover:underline">+ Add Row</button>
              </div>
              <div v-for="(item, index) in form.items" :key="index" class="mb-2 space-y-2 rounded-xl border border-slate-200 p-3">
                <div class="flex items-start gap-2">
                  <input v-model="item.name" placeholder="Nama (mis. Transport)" class="flex-1 rounded-lg border border-slate-200 px-2 py-1.5 text-sm focus:border-primary focus:outline-none" />
                  <button v-if="form.items.length > 1" @click="removeItem(index)" type="button" class="mt-1 shrink-0 text-slate-400 hover:text-red-500"><Trash2 class="h-4 w-4" :stroke-width="1.75" /></button>
                </div>
                <div class="flex gap-2">
                  <select v-model="item.cash_advance_category_id" class="w-1/2 rounded-lg border border-slate-200 px-2 py-1.5 text-sm focus:border-primary focus:outline-none">
                    <option :value="null" disabled>Kategori</option>
                    <option v-for="cat in availableCategories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                  </select>
                  <input v-model.number="item.amount" type="number" min="1" placeholder="Amount" class="w-1/2 rounded-lg border border-slate-200 px-2 py-1.5 text-sm focus:border-primary focus:outline-none" />
                </div>
                <input v-model="item.description" placeholder="Deskripsi (opsional)" class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-primary focus:outline-none" />
              </div>
              <p class="text-right text-xs text-slate-400">Total: {{ formatCurrency(createTotal) }}</p>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Notes</label>
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
              <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" :stroke-width="1.75" /><p>{{ formError }}</p>
            </div>
          </div>
          <div class="flex gap-3 border-t border-slate-100 px-6 py-4">
            <button @click="showCreateModal = false" class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50">Batal</button>
            <button @click="submitCreate" :disabled="saving" class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-primary py-2.5 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50">
              <Loader2 v-if="saving" class="h-4 w-4 animate-spin" :stroke-width="2" /> {{ saving ? 'Mengirim...' : 'Submit Cash Advance' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Detail / settlement drawer -->
    <Teleport to="body">
      <div v-if="showDetail && detailTarget" class="fixed inset-0 z-50 flex justify-end bg-slate-900/30">
        <div class="h-full w-full max-w-lg overflow-y-auto bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <div>
              <h2 class="text-lg font-semibold text-slate-900">{{ detailTarget.purpose }}</h2>
              <p class="text-sm text-slate-500">{{ detailTarget.policy.name }}</p>
            </div>
            <button @click="closeDetail" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-50"><X class="h-5 w-5" :stroke-width="1.75" /></button>
          </div>

          <div class="space-y-4 px-6 py-5">
            <span class="inline-block rounded-full px-2 py-0.5 text-xs font-medium" :class="statusBadgeClass[detailTarget.status]">{{ statusLabels[detailTarget.status] }}</span>

            <div class="rounded-xl bg-primary-soft p-4">
              <p class="text-xs text-primary-dark">Total Diajukan</p>
              <p class="text-xl font-semibold text-primary-dark">{{ formatCurrency(detailTarget.total_amount) }}</p>
              <p class="mt-1 text-xs text-slate-500">Tanggal pakai {{ formatDate(detailTarget.date_of_use) }}</p>
            </div>

            <div>
              <p class="mb-1.5 text-xs font-medium text-slate-500">Detail</p>
              <ul class="space-y-1.5">
                <li v-for="item in detailTarget.items" :key="item.id" class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm">
                  <span class="text-slate-700">{{ item.name }} <span class="text-xs text-slate-400">({{ item.category.name }})</span></span>
                  <span class="font-medium text-slate-800">{{ formatCurrency(item.amount) }}</span>
                </li>
              </ul>
            </div>

            <div v-if="detailTarget.attachments.length > 0">
              <p class="mb-1.5 text-xs font-medium text-slate-500">Attachment</p>
              <div class="flex flex-wrap gap-2">
                <a v-for="att in detailTarget.attachments" :key="att.id" :href="att.url ?? '#'" target="_blank" class="flex items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs text-slate-600 hover:bg-slate-50">
                  <Paperclip class="h-3.5 w-3.5" :stroke-width="1.75" /> {{ att.file_name }}
                </a>
              </div>
            </div>

            <div v-if="detailTarget.approval_request" class="space-y-1.5">
              <p class="text-xs font-medium text-slate-500">Approval Timeline</p>
              <div v-for="step in detailTarget.approval_request.step_decisions" :key="step.id" class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-xs">
                <span class="text-slate-600">{{ step.approval_step.name ?? `Step ${step.sequence}` }}</span>
                <span class="font-medium capitalize text-slate-700">{{ step.status }}</span>
              </div>
            </div>

            <!-- Settlement section -->
            <div v-if="latestSettlement(detailTarget)" class="rounded-xl border border-slate-200 p-3">
              <p class="mb-2 text-xs font-medium text-slate-500">Settlement Terakhir</p>
              <div class="grid grid-cols-3 gap-2 text-center text-xs">
                <div class="rounded-lg bg-slate-50 p-2"><p class="text-slate-400">CA Amount</p><p class="font-medium text-slate-700">{{ formatCurrency(detailTarget.total_amount) }}</p></div>
                <div class="rounded-lg bg-slate-50 p-2"><p class="text-slate-400">Actual</p><p class="font-medium text-slate-700">{{ formatCurrency(latestSettlement(detailTarget)!.total_actual_amount) }}</p></div>
                <div class="rounded-lg bg-slate-50 p-2"><p class="text-slate-400">Returned</p><p class="font-medium text-slate-700">{{ formatCurrency(latestSettlement(detailTarget)!.total_returned_amount) }}</p></div>
              </div>
              <p class="mt-2 text-xs" :class="latestSettlement(detailTarget)!.status === 'rejected' ? 'text-red-600' : 'text-slate-500'">
                Status: {{ latestSettlement(detailTarget)!.status }}
              </p>
            </div>

            <div v-if="detailTarget.status === 'completed'" class="flex items-center gap-2 rounded-xl bg-emerald-50 p-3 text-xs text-emerald-700">
              <CheckCircle2 class="h-4 w-4" :stroke-width="1.75" /> Cash Advance ini sudah selesai (settled).
            </div>

            <!-- Settlement form -->
            <div v-if="showSettlementForm" class="space-y-3 rounded-xl border border-primary/30 p-3">
              <p class="text-xs font-medium text-slate-700">Actual Settlement</p>
              <div v-for="(item, index) in settlementForm.items" :key="index" class="space-y-1.5 rounded-lg bg-slate-50 p-2.5">
                <p class="text-xs font-medium text-slate-600">{{ item.description }}</p>
                <div class="flex gap-2">
                  <div class="flex-1">
                    <label class="text-[10px] text-slate-400">Actual</label>
                    <input v-model.number="item.actual_amount" type="number" min="0" class="w-full rounded-lg border border-slate-200 px-2 py-1 text-xs focus:border-primary focus:outline-none" />
                  </div>
                  <div class="flex-1">
                    <label class="text-[10px] text-slate-400">Returned</label>
                    <input v-model.number="item.returned_amount" type="number" min="0" class="w-full rounded-lg border border-slate-200 px-2 py-1 text-xs focus:border-primary focus:outline-none" />
                  </div>
                </div>
              </div>
              <div class="flex justify-between text-xs text-slate-500">
                <span>Total Actual: {{ formatCurrency(settlementTotalActual) }}</span>
                <span>Total Returned: {{ formatCurrency(settlementTotalReturned) }}</span>
              </div>
              <textarea v-model="settlementForm.notes" rows="2" placeholder="Catatan (opsional)" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs focus:border-primary focus:outline-none"></textarea>
              <label class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-dashed border-slate-300 py-2 text-xs text-slate-500 hover:bg-slate-50">
                <Paperclip class="h-3.5 w-3.5" :stroke-width="1.75" /> Bukti settlement
                <input type="file" multiple class="hidden" @change="handleSettlementFiles" />
              </label>
              <p v-if="settlementForm.attachments.length > 0" class="text-xs text-slate-500">{{ settlementForm.attachments.length }} file dipilih.</p>

              <div v-if="settlementError" class="flex items-start gap-2 rounded-lg bg-red-50 p-2.5 text-xs text-red-600">
                <AlertTriangle class="mt-0.5 h-3.5 w-3.5 shrink-0" :stroke-width="1.75" /><p>{{ settlementError }}</p>
              </div>

              <div class="flex gap-2">
                <button @click="showSettlementForm = false" class="flex-1 rounded-lg border border-slate-200 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50">Batal</button>
                <button @click="submitSettlement" :disabled="settlementSaving" class="flex-1 rounded-lg bg-primary py-2 text-xs font-medium text-white hover:bg-primary-dark disabled:opacity-50">Submit Settlement</button>
              </div>
            </div>
          </div>

          <div v-if="!showSettlementForm && detailTarget.status === 'need_settlement'" class="border-t border-slate-100 px-6 py-4">
            <button @click="openSettlementForm(detailTarget)" class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary py-2.5 text-sm font-medium text-white hover:bg-primary-dark">
              <Receipt class="h-4 w-4" :stroke-width="1.75" /> Create Settlement
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>