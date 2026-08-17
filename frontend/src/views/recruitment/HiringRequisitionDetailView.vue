<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import apiClient from '@/lib/axios'
import { useAuthStore } from '@/stores/auth'
import { ArrowLeft } from 'lucide-vue-next'

interface RefOption { id: number; name: string }
interface EmployeeOption { id: number; first_name: string; last_name: string | null }

interface StepDecision {
  id: number
  sequence: number
  status: string
  notes: string | null
  approval_step: { name: string } | null
}

interface RequisitionDetail {
  id: number
  reason: string
  employment_type_id: number
  headcount_requested: number
  target_start_date: string | null
  justification: string
  status: string
  requested_at: string
  position: RefOption | null
  department: RefOption | null
  requested_by: EmployeeOption | null
  replacement_for: EmployeeOption | null
  approval_request: { id: number; status: string; current_step_sequence: number; step_decisions: StepDecision[] } | null
}

interface PendingDecision {
  id: number
  sequence: number
  request: { id: number; hiring_requisition: { id: number } }
}

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const requisitionId = computed(() => Number(route.params.id))
const requisition = ref<RequisitionDetail | null>(null)
const loading = ref(true)
const errorMessage = ref('')
const actionError = ref('')
const myPendingDecisionId = ref<number | null>(null)

const deciding = ref(false)
const decideNotes = ref('')

function employeeName(e: EmployeeOption | null): string {
  if (!e) return '-'
  return [e.first_name, e.last_name].filter(Boolean).join(' ')
}

async function loadRequisition() {
  loading.value = true
  errorMessage.value = ''
  try {
    const [detailRes, pendingRes] = await Promise.all([
      apiClient.get(`/api/hiring-requisitions/${requisitionId.value}`),
      apiClient.get('/api/hiring-requisitions/approvals/pending'),
    ])
    requisition.value = detailRes.data.data

    const pending: PendingDecision[] = pendingRes.data.data
    const mine = pending.find((d) => d.request.hiring_requisition.id === requisitionId.value)
    myPendingDecisionId.value = mine ? mine.id : null
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Gagal memuat detail Hiring Requisition.'
  } finally {
    loading.value = false
  }
}

async function cancelRequisition() {
  actionError.value = ''
  try {
    await apiClient.post(`/api/hiring-requisitions/${requisitionId.value}/cancel`)
    await loadRequisition()
  } catch (err: any) {
    actionError.value = err.response?.data?.message || 'Gagal membatalkan Requisition.'
  }
}

async function decide(action: 'approve' | 'reject') {
  if (!myPendingDecisionId.value) return
  deciding.value = true
  actionError.value = ''
  try {
    await apiClient.post(`/api/hiring-requisitions/approvals/${myPendingDecisionId.value}/decide`, {
      action,
      notes: decideNotes.value || null,
    })
    decideNotes.value = ''
    await loadRequisition()
  } catch (err: any) {
    actionError.value = err.response?.data?.message || 'Gagal menyimpan keputusan approval.'
  } finally {
    deciding.value = false
  }
}

onMounted(loadRequisition)
</script>

<template>
  <div class="space-y-4">
    <button class="flex items-center gap-1 text-sm text-slate-400 hover:text-slate-600" @click="router.push({ name: 'hiring-requisitions.index' })">
      <ArrowLeft class="h-4 w-4" /> Kembali
    </button>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>

    <div v-else-if="requisition" class="space-y-4">
      <div v-if="actionError" class="rounded-xl bg-red-50 p-3 text-sm text-red-600">{{ actionError }}</div>

      <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <div class="flex items-start justify-between">
          <div>
            <h1 class="text-lg font-semibold text-slate-800">{{ requisition.position?.name || '-' }}</h1>
            <p class="text-sm text-slate-400">{{ requisition.department?.name || '-' }} · {{ requisition.reason === 'replacement' ? 'Replacement' : 'New Position' }}</p>
          </div>
          <button
            v-if="requisition.status === 'pending' && authStore.permissions.includes('cancel hiring requisitions')"
            class="rounded-xl border border-slate-200 px-3 py-1.5 text-sm hover:bg-slate-50"
            @click="cancelRequisition"
          >
            Cancel
          </button>
        </div>

        <dl class="mt-6 grid grid-cols-3 gap-4 text-sm">
          <div><dt class="text-slate-400">Status</dt><dd class="font-medium text-slate-700">{{ requisition.status }}</dd></div>
          <div><dt class="text-slate-400">Headcount Requested</dt><dd class="font-medium text-slate-700">{{ requisition.headcount_requested }}</dd></div>
          <div><dt class="text-slate-400">Requested By</dt><dd class="font-medium text-slate-700">{{ employeeName(requisition.requested_by) }}</dd></div>
          <div v-if="requisition.replacement_for"><dt class="text-slate-400">Menggantikan</dt><dd class="font-medium text-slate-700">{{ employeeName(requisition.replacement_for) }}</dd></div>
          <div v-if="requisition.target_start_date"><dt class="text-slate-400">Target Start Date</dt><dd class="font-medium text-slate-700">{{ requisition.target_start_date }}</dd></div>
        </dl>

        <div class="mt-6">
          <h3 class="text-xs font-medium uppercase text-slate-400">Justifikasi</h3>
          <p class="mt-1 whitespace-pre-line text-sm text-slate-600">{{ requisition.justification }}</p>
        </div>
      </div>

      <div v-if="requisition.approval_request" class="rounded-2xl border border-slate-100 bg-white p-6 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <h2 class="text-sm font-semibold text-slate-700">Approval Progress</h2>
        <ol class="mt-4 space-y-2">
          <li
            v-for="d in requisition.approval_request.step_decisions"
            :key="d.id"
            class="flex items-center justify-between rounded-xl border border-slate-100 px-3 py-2 text-sm"
          >
            <span>{{ d.sequence }}. {{ d.approval_step?.name || 'Step' }}</span>
            <span
              class="rounded-full px-2.5 py-1 text-xs font-medium"
              :class="{
                'bg-amber-50 text-amber-600': d.status === 'pending',
                'bg-emerald-50 text-emerald-600': d.status === 'approved',
                'bg-red-50 text-red-600': d.status === 'rejected',
              }"
            >
              {{ d.status }}
            </span>
          </li>
        </ol>

        <div v-if="myPendingDecisionId" class="mt-4 space-y-2 border-t border-slate-100 pt-4">
          <p class="text-sm font-medium text-slate-700">Giliran approval Anda</p>
          <textarea v-model="decideNotes" rows="2" placeholder="Catatan (opsional)" class="w-full rounded-xl border border-slate-200 p-2 text-sm" />
          <div class="flex gap-2">
            <button :disabled="deciding" class="rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white disabled:opacity-50" @click="decide('approve')">
              Approve
            </button>
            <button :disabled="deciding" class="rounded-xl border border-red-200 px-4 py-2 text-sm font-medium text-red-600 disabled:opacity-50" @click="decide('reject')">
              Reject
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>