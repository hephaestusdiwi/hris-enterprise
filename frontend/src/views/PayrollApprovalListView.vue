<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { Check, X, Loader2, AlertTriangle, Clock, Wallet } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

const monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']

interface PendingDecision {
  id: number
  sequence: number
  approval_step: { id: number; name: string | null; sequence: number }
  request: {
    id: number
    payroll_run: { id: number; period_year: number; period_month: number; company: { name: string } }
  }
}

const router = useRouter()
const decisions = ref<PendingDecision[]>([])
const loading = ref(true)
const errorMessage = ref('')

async function loadDecisions() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/payroll-approvals')
    decisions.value = response.data.data
  } catch {
    errorMessage.value = 'Gagal memuat daftar approval.'
  } finally {
    loading.value = false
  }
}

const showModal = ref(false)
const modalMode = ref<'approve' | 'reject'>('approve')
const target = ref<PendingDecision | null>(null)
const submitting = ref(false)
const modalError = ref('')
const form = reactive({ notes: '' })

function openDecision(decision: PendingDecision, mode: 'approve' | 'reject') {
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
    await apiClient.post(`/api/payroll-approvals/${target.value.id}/decide`, { action: modalMode.value, notes: form.notes || null })
    closeModal()
    await loadDecisions()
  } catch (err: any) {
    modalError.value = err.response?.data?.message || 'Gagal memproses approval.'
  } finally {
    submitting.value = false
  }
}

onMounted(loadDecisions)
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Payroll Approval</h1>
      <p class="mt-1 text-sm text-slate-500">Payroll run yang menunggu keputusan Anda.</p>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>
    <div v-else-if="decisions.length === 0" class="rounded-xl bg-slate-50 p-6 text-center text-sm text-slate-400">Tidak ada payroll run yang menunggu persetujuan Anda.</div>

    <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <table class="w-full text-left text-sm">
        <thead>
          <tr class="border-b border-slate-100 bg-slate-50/60">
            <th class="px-5 py-3 font-medium text-slate-500">Periode</th>
            <th class="px-5 py-3 font-medium text-slate-500">Company</th>
            <th class="px-5 py-3 font-medium text-slate-500">Step</th>
            <th class="px-5 py-3 text-right font-medium text-slate-500">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="decision in decisions" :key="decision.id" class="border-b border-slate-50 last:border-0 hover:bg-slate-50/50">
            <td class="px-5 py-3.5 cursor-pointer" @click="router.push(`/payroll-runs/${decision.request.payroll_run.id}`)">
              <div class="flex items-center gap-2">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary-soft text-primary-dark"><Wallet class="h-4 w-4" :stroke-width="1.75" /></div>
                <p class="font-medium text-slate-800">{{ monthNames[decision.request.payroll_run.period_month - 1] }} {{ decision.request.payroll_run.period_year }}</p>
              </div>
            </td>
            <td class="px-5 py-3.5 text-slate-500">{{ decision.request.payroll_run.company.name }}</td>
            <td class="px-5 py-3.5">
              <span class="flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-600">
                <Clock class="h-3 w-3" :stroke-width="2" /> {{ decision.approval_step.name ?? `Step ${decision.approval_step.sequence}` }}
              </span>
            </td>
            <td class="px-5 py-3.5 text-right">
              <div class="flex justify-end gap-1.5">
                <button @click="openDecision(decision, 'reject')" class="rounded-lg border border-red-200 p-2 text-red-600 hover:bg-red-50" title="Tolak"><X class="h-4 w-4" :stroke-width="2" /></button>
                <button @click="openDecision(decision, 'approve')" class="rounded-lg bg-primary p-2 text-white hover:bg-primary-dark" title="Setujui"><Check class="h-4 w-4" :stroke-width="2" /></button>
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
            <h2 class="text-lg font-semibold text-slate-900">{{ modalMode === 'approve' ? 'Setujui' : 'Tolak' }} Payroll Run</h2>
            <p class="mt-1 text-sm text-slate-500">{{ monthNames[target.request.payroll_run.period_month - 1] }} {{ target.request.payroll_run.period_year }} — {{ target.request.payroll_run.company.name }}</p>
          </div>
          <div class="space-y-4 px-6 py-5">
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
            <button @click="submitDecision" :disabled="submitting" class="flex flex-1 items-center justify-center gap-2 rounded-xl py-2.5 text-sm font-medium text-white disabled:opacity-50" :class="modalMode === 'approve' ? 'bg-primary hover:bg-primary-dark' : 'bg-red-600 hover:bg-red-700'">
              <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" :stroke-width="2" />
              {{ submitting ? 'Memproses...' : modalMode === 'approve' ? 'Setujui' : 'Tolak' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>