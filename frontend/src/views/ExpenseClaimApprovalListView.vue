<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue'
import { Check, X, Loader2, AlertTriangle, Clock, Receipt } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface Decision {
  id: number
  sequence: number
  approval_step: { name: string | null; sequence: number }
  request: {
    employee: { id: number; first_name: string; last_name: string | null }
    claim: {
      id: number
      amount: string
      expense_date: string
      description: string | null
      category: { name: string }
      subcategory: { name: string } | null
    }
  }
}

function employeeName(e: { first_name: string; last_name: string | null }) {
  return [e.first_name, e.last_name].filter(Boolean).join(' ')
}
function formatCurrency(value: string | number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(Number(value))
}
function formatDate(value: string) {
  return new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

const decisions = ref<Decision[]>([])
const loading = ref(true)
const errorMessage = ref('')

async function loadAll() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/expense-claim-approvals')
    decisions.value = response.data.data
  } catch {
    errorMessage.value = 'Gagal memuat daftar approval.'
  } finally {
    loading.value = false
  }
}

const showModal = ref(false)
const modalMode = ref<'approve' | 'reject'>('approve')
const target = ref<Decision | null>(null)
const submitting = ref(false)
const modalError = ref('')
const form = reactive({ notes: '' })

function openDecision(decision: Decision, mode: 'approve' | 'reject') {
  target.value = decision
  modalMode.value = mode
  modalError.value = ''
  form.notes = ''
  showModal.value = true
}
function closeModal() {
  showModal.value = false
  target.value = null
}

async function submitDecision() {
  if (!target.value) return
  if (modalMode.value === 'reject' && !form.notes.trim()) {
    modalError.value = 'Catatan wajib diisi kalau menolak.'
    return
  }
  submitting.value = true
  modalError.value = ''
  try {
    await apiClient.post(`/api/expense-claim-approvals/${target.value.id}/decide`, {
      action: modalMode.value,
      notes: form.notes || null,
    })
    closeModal()
    await loadAll()
  } catch (err: any) {
    modalError.value = err.response?.data?.message || 'Gagal memproses approval.'
  } finally {
    submitting.value = false
  }
}

onMounted(loadAll)
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Expense Claim Approval</h1>
      <p class="mt-1 text-sm text-slate-500">Expense Claim yang menunggu keputusan Anda.</p>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>

    <div v-else-if="decisions.length === 0" class="rounded-xl bg-slate-50 p-6 text-center">
      <Receipt class="mx-auto h-8 w-8 text-slate-300" :stroke-width="1.5" />
      <p class="mt-2 text-sm text-slate-400">Tidak ada Expense Claim yang menunggu approval kamu.</p>
    </div>

    <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <table class="w-full text-left text-sm">
        <thead>
          <tr class="border-b border-slate-100 bg-slate-50/60">
            <th class="px-5 py-3 font-medium text-slate-500">Employee</th>
            <th class="px-5 py-3 font-medium text-slate-500">Category</th>
            <th class="px-5 py-3 font-medium text-slate-500">Tanggal</th>
            <th class="px-5 py-3 text-right font-medium text-slate-500">Amount</th>
            <th class="px-5 py-3 font-medium text-slate-500">Step</th>
            <th class="px-5 py-3 text-right font-medium text-slate-500">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="d in decisions" :key="d.id" class="border-b border-slate-50 last:border-0 hover:bg-slate-50/50">
            <td class="px-5 py-3.5">
              <div class="flex items-center gap-2">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary-soft text-primary-dark">
                  <Receipt class="h-4 w-4" :stroke-width="1.75" />
                </div>
                <span class="font-medium text-slate-800">{{ employeeName(d.request.employee) }}</span>
              </div>
            </td>
            <td class="px-5 py-3.5 text-slate-500">
              {{ d.request.claim.category.name }}<span v-if="d.request.claim.subcategory" class="text-xs text-slate-400"> / {{ d.request.claim.subcategory.name }}</span>
            </td>
            <td class="px-5 py-3.5 text-slate-500">{{ formatDate(d.request.claim.expense_date) }}</td>
            <td class="px-5 py-3.5 text-right font-medium text-slate-700">{{ formatCurrency(d.request.claim.amount) }}</td>
            <td class="px-5 py-3.5">
              <span class="flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-600">
                <Clock class="h-3 w-3" :stroke-width="2" />{{ d.approval_step.name ?? `Step ${d.approval_step.sequence}` }}
              </span>
            </td>
            <td class="px-5 py-3.5 text-right">
              <div class="flex justify-end gap-1.5">
                <button @click="openDecision(d, 'reject')" class="rounded-lg border border-red-200 p-2 text-red-600 hover:bg-red-50"><X class="h-4 w-4" :stroke-width="2" /></button>
                <button @click="openDecision(d, 'approve')" class="rounded-lg bg-primary p-2 text-white hover:bg-primary-dark"><Check class="h-4 w-4" :stroke-width="2" /></button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <Teleport to="body">
      <div v-if="showModal && target" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8">
        <div class="w-full max-w-md rounded-2xl bg-white shadow-xl">
          <div class="border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">{{ modalMode === 'approve' ? 'Setujui' : 'Tolak' }} Expense Claim</h2>
            <p class="mt-1 text-sm text-slate-500">
              {{ employeeName(target.request.employee) }} · {{ formatCurrency(target.request.claim.amount) }}
            </p>
          </div>
          <div class="space-y-4 px-6 py-5">
            <div v-if="target.request.claim.description" class="rounded-xl bg-slate-50 p-3 text-xs text-slate-600">
              {{ target.request.claim.description }}
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Catatan {{ modalMode === 'reject' ? '(wajib)' : '(opsional)' }}</label>
              <textarea v-model="form.notes" rows="3" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"></textarea>
            </div>
            <div v-if="modalError" class="flex items-start gap-2 rounded-xl bg-red-50 p-3 text-xs text-red-600">
              <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" :stroke-width="1.75" /><p>{{ modalError }}</p>
            </div>
          </div>
          <div class="flex gap-3 border-t border-slate-100 px-6 py-4">
            <button @click="closeModal" class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50">Batal</button>
            <button
              @click="submitDecision" :disabled="submitting"
              class="flex flex-1 items-center justify-center gap-2 rounded-xl py-2.5 text-sm font-medium text-white disabled:opacity-50"
              :class="modalMode === 'approve' ? 'bg-primary hover:bg-primary-dark' : 'bg-red-600 hover:bg-red-700'"
            >
              <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" :stroke-width="2" />
              {{ submitting ? 'Memproses...' : modalMode === 'approve' ? 'Setujui' : 'Tolak' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>