<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Check, X, Clock3, Loader2 } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface PendingDecision {
  id: number
  sequence: number
  approval_step: { id: number; name: string | null; sequence: number }
  request: {
    id: number
    employee: { id: number; first_name: string; last_name: string | null }
    overtime_request: {
      id: number
      attendance_date: string
      planned_minutes: number
      reason: string
    }
  }
}

function employeeName(e: { first_name: string; last_name: string | null }) {
  return [e.first_name, e.last_name].filter(Boolean).join(' ')
}

function formatDate(value: string) {
  return new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

function formatMinutes(minutes: number) {
  const h = Math.floor(minutes / 60)
  const m = minutes % 60
  return h > 0 ? `${h}j ${m > 0 ? `${m}m` : ''}`.trim() : `${m}m`
}

const decisions = ref<PendingDecision[]>([])
const loading = ref(true)
const errorMessage = ref('')
const actingOnId = ref<number | null>(null)

async function loadDecisions() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/overtime-request-approvals')
    decisions.value = response.data.data
  } catch {
    errorMessage.value = 'Gagal memuat daftar approval.'
  } finally {
    loading.value = false
  }
}

onMounted(loadDecisions)

async function decide(decision: PendingDecision, action: 'approve' | 'reject') {
  actingOnId.value = decision.id
  try {
    await apiClient.post(`/api/overtime-request-approvals/${decision.id}/decide`, { action })
    decisions.value = decisions.value.filter((d) => d.id !== decision.id)
  } catch {
    // gagal decide -- biarin row apa adanya, user bisa coba lagi
  } finally {
    actingOnId.value = null
  }
}
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Overtime Request Approval</h1>
      <p class="mt-1 text-sm text-slate-500">Overtime request yang menunggu keputusan kamu.</p>
    </div>

    <div v-if="loading" class="flex items-center gap-2 py-10 text-sm text-slate-400">
      <Loader2 class="h-4 w-4 animate-spin" :stroke-width="2" />
      Memuat...
    </div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>
    <div v-else-if="decisions.length === 0" class="rounded-2xl border border-slate-100 bg-white p-10 text-center text-sm text-slate-400">
      Tidak ada overtime request yang menunggu approval kamu.
    </div>

    <div v-else class="space-y-3">
      <div
        v-for="d in decisions"
        :key="d.id"
        class="rounded-2xl border border-slate-100 bg-white p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)]"
      >
        <div class="flex items-start justify-between gap-4">
          <div class="min-w-0">
            <p class="font-medium text-slate-800">{{ employeeName(d.request.employee) }}</p>
            <p class="mt-0.5 flex items-center gap-1.5 text-xs text-slate-500">
              {{ formatDate(d.request.overtime_request.attendance_date) }}
              <span class="text-slate-300">&middot;</span>
              <Clock3 class="h-3 w-3" :stroke-width="1.75" />
              {{ formatMinutes(d.request.overtime_request.planned_minutes) }}
            </p>
            <p class="mt-2 text-sm text-slate-600">{{ d.request.overtime_request.reason }}</p>
          </div>
          <div class="flex shrink-0 gap-2">
            <button
              type="button"
              :disabled="actingOnId === d.id"
              @click="decide(d, 'reject')"
              class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 disabled:opacity-50"
            >
              <X class="h-3.5 w-3.5" :stroke-width="2" />
              Tolak
            </button>
            <button
              type="button"
              :disabled="actingOnId === d.id"
              @click="decide(d, 'approve')"
              class="inline-flex items-center gap-1 rounded-lg bg-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-primary-dark disabled:opacity-50"
            >
              <Check class="h-3.5 w-3.5" :stroke-width="2" />
              Setujui
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>