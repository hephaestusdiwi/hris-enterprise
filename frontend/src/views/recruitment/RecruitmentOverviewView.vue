<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import apiClient from '@/lib/axios'
import { Briefcase, UserPlus, ClipboardCheck, CalendarClock } from 'lucide-vue-next'

const router = useRouter()

interface UpcomingInterview {
  id: number
  scheduled_at: string
  candidate: { id: number; full_name: string } | null
  job_vacancy: { id: number; title: string } | null
  stage: { name: string } | null
  interviewer: { first_name: string; last_name: string | null } | null
}

interface OverviewData {
  active_job_vacancies: number
  candidates_by_status: Record<string, number>
  upcoming_interviews: UpcomingInterview[]
  new_joiners_pending: number
  hiring_requisitions_pending_approval: number
}

const loading = ref(true)
const errorMessage = ref('')
const data = ref<OverviewData | null>(null)

// Urutan funnel sesuai alur progres kandidat — Rejected/Hold ditampilkan
// terpisah di bawah karena statusnya cabang, bukan lanjutan linear.
const PIPELINE_STAGES = [
  { key: 'applied', label: 'Applied' },
  { key: 'screening', label: 'Screening' },
  { key: 'interview', label: 'Interview' },
  { key: 'selected', label: 'Selected' },
  { key: 'offering', label: 'Offering' },
  { key: 'offered', label: 'Offered' },
  { key: 'hired', label: 'Hired' },
]

const pipelineRows = computed(() => {
  if (!data.value) return []
  const counts = data.value.candidates_by_status
  const max = Math.max(1, ...PIPELINE_STAGES.map((s) => counts[s.key] ?? 0))
  return PIPELINE_STAGES.map((s) => ({
    ...s,
    count: counts[s.key] ?? 0,
    percent: Math.round(((counts[s.key] ?? 0) / max) * 100),
  }))
})

const rejectedCount = computed(() => data.value?.candidates_by_status.rejected ?? 0)
const holdCount = computed(() => data.value?.candidates_by_status.hold ?? 0)

function interviewerName(e: UpcomingInterview['interviewer']): string {
  if (!e) return '-'
  return [e.first_name, e.last_name].filter(Boolean).join(' ')
}

function formatDateTime(s: string): string {
  return s?.slice(0, 16).replace('T', ' ')
}

async function loadOverview() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/recruitment-overview')
    data.value = response.data.data
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Gagal memuat Recruitment Overview.'
  } finally {
    loading.value = false
  }
}

onMounted(loadOverview)
</script>

<template>
  <div class="space-y-4">
    <div>
      <h1 class="text-lg font-semibold text-slate-800">Recruitment Overview</h1>
      <p class="text-sm text-slate-400">Snapshot state Recruitment saat ini.</p>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>

    <template v-else-if="data">
      <!-- Stat cards -->
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
          <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-primary-dark">
              <Briefcase class="h-5 w-5" :stroke-width="1.75" />
            </div>
            <div>
              <p class="text-2xl font-bold text-slate-900">{{ data.active_job_vacancies }}</p>
              <p class="text-xs text-slate-400">Active Job Vacancies</p>
            </div>
          </div>
        </div>

        <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
          <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
              <UserPlus class="h-5 w-5" :stroke-width="1.75" />
            </div>
            <div>
              <p class="text-2xl font-bold text-slate-900">{{ data.new_joiners_pending }}</p>
              <p class="text-xs text-slate-400">New Joiner Belum Submit</p>
            </div>
          </div>
        </div>

        <button
          type="button"
          class="rounded-2xl border border-slate-100 bg-white p-5 text-left shadow-[0_1px_3px_rgba(15,23,42,0.04)] transition-colors hover:bg-slate-50"
          @click="router.push({ name: 'hiring-requisitions.index' })"
        >
          <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
              <ClipboardCheck class="h-5 w-5" :stroke-width="1.75" />
            </div>
            <div>
              <p class="text-2xl font-bold text-slate-900">{{ data.hiring_requisitions_pending_approval }}</p>
              <p class="text-xs text-slate-400">Hiring Requisition Pending Approval</p>
            </div>
          </div>
        </button>
      </div>

      <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <!-- Candidate Pipeline -->
        <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)] lg:col-span-2">
          <h3 class="mb-4 text-xs font-semibold uppercase tracking-wider text-slate-400">Candidate Pipeline</h3>

          <div class="space-y-3">
            <div v-for="row in pipelineRows" :key="row.key">
              <div class="mb-1 flex items-center justify-between text-sm">
                <span class="font-medium text-slate-700">{{ row.label }}</span>
                <span class="text-slate-400">{{ row.count }}</span>
              </div>
              <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-primary" :style="{ width: row.percent + '%' }"></div>
              </div>
            </div>
          </div>

          <div class="mt-4 flex gap-4 border-t border-slate-100 pt-4 text-xs text-slate-500">
            <span>Rejected: <span class="font-medium text-slate-700">{{ rejectedCount }}</span></span>
            <span>Hold: <span class="font-medium text-slate-700">{{ holdCount }}</span></span>
          </div>
        </div>

        <!-- Upcoming Interviews -->
        <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
          <div class="mb-4 flex items-center justify-between">
            <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Upcoming Interviews</h3>
            <button type="button" class="text-xs font-medium text-primary-dark hover:underline" @click="router.push({ name: 'interviews.index' })">
              View all &rsaquo;
            </button>
          </div>

          <div v-if="data.upcoming_interviews.length === 0" class="py-8 text-center text-xs text-slate-400">
            Belum ada interview terjadwal.
          </div>
          <div v-else class="space-y-3">
            <div v-for="i in data.upcoming_interviews" :key="i.id" class="flex items-start gap-3">
              <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-50 text-slate-400">
                <CalendarClock class="h-4 w-4" :stroke-width="1.75" />
              </div>
              <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium text-slate-800">{{ i.candidate?.full_name || '-' }}</p>
                <p class="truncate text-xs text-slate-400">{{ i.job_vacancy?.title || '-' }} &middot; {{ i.stage?.name || '-' }}</p>
                <p class="truncate text-xs text-slate-400">{{ formatDateTime(i.scheduled_at) }} &middot; {{ interviewerName(i.interviewer) }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>