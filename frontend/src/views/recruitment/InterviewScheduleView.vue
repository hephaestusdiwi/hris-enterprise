<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import apiClient from '@/lib/axios'
import { useAuthStore } from '@/stores/auth'
import BaseModal from '@/components/ui/BaseModal.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import { CalendarClock, Search } from 'lucide-vue-next'

interface CandidateOption {
  id: number
  full_name: string
}

interface EmployeeOption {
  id: number
  first_name: string
  last_name: string | null
}

interface InterviewStageOption {
  id: number
  name: string
}

interface InterviewRow {
  id: number
  status: string
  result: string | null
  score: number | null
  notes: string | null
  recommendation: string | null
  scheduled_at: string
  candidate: CandidateOption | null
  job_vacancy: { id: number; title: string } | null
  stage: { name: string } | null
  interviewer: EmployeeOption | null
}

const authStore = useAuthStore()

const loading = ref(true)
const errorMessage = ref('')
const actionError = ref('')

const interviews = ref<InterviewRow[]>([])
const currentPage = ref(1)
const lastPage = ref(1)

const search = ref('')
const statusFilter = ref('')

const STATUS_BADGE: Record<string, string> = {
  scheduled: 'bg-slate-100 text-slate-600',
  in_progress: 'bg-blue-50 text-blue-600',
  completed: 'bg-emerald-50 text-emerald-600',
  cancelled: 'bg-red-50 text-red-600',
  passed: 'bg-emerald-50 text-emerald-600',
  failed: 'bg-red-50 text-red-600',
  hold: 'bg-amber-50 text-amber-600',
}

function employeeName(e: EmployeeOption | null): string {
  if (!e) return '-'
  return [e.first_name, e.last_name].filter(Boolean).join(' ')
}

const filteredInterviews = computed(() => {
  const q = search.value.trim().toLowerCase()
  return interviews.value.filter((i) => {
    const matchSearch = !q || (i.candidate?.full_name ?? '').toLowerCase().includes(q)
    const matchStatus = !statusFilter.value || i.status === statusFilter.value
    return matchSearch && matchStatus
  })
})

async function loadInterviews(page = 1) {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/interviews', { params: { page } })
    interviews.value = response.data.data.data
    currentPage.value = response.data.data.current_page
    lastPage.value = response.data.data.last_page
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Gagal memuat jadwal Interview.'
  } finally {
    loading.value = false
  }
}

function goToPage(page: number) {
  if (page < 1 || page > lastPage.value) return
  loadInterviews(page)
}

// ---- Actions: Start / Complete / Cancel ----
async function startInterview(interview: InterviewRow) {
  actionError.value = ''
  try {
    await apiClient.post(`/api/interviews/${interview.id}/start`)
    await loadInterviews(currentPage.value)
  } catch (err: any) {
    actionError.value = err.response?.data?.message || 'Gagal memulai Interview.'
  }
}

async function cancelInterview(interview: InterviewRow) {
  actionError.value = ''
  try {
    await apiClient.post(`/api/interviews/${interview.id}/cancel`, {})
    await loadInterviews(currentPage.value)
  } catch (err: any) {
    actionError.value = err.response?.data?.message || 'Gagal membatalkan Interview.'
  }
}

const showCompleteModal = ref(false)
const completingInterview = ref<InterviewRow | null>(null)
const completeForm = ref({
  result: 'passed',
  score: null as number | null,
  notes: '',
  recommendation: '',
})
const completeSaving = ref(false)

function openCompleteModal(interview: InterviewRow) {
  completingInterview.value = interview
  completeForm.value = { result: 'passed', score: null, notes: '', recommendation: '' }
  showCompleteModal.value = true
}

async function submitComplete() {
  if (!completingInterview.value) return
  completeSaving.value = true
  actionError.value = ''
  try {
    await apiClient.post(`/api/interviews/${completingInterview.value.id}/complete`, completeForm.value)
    showCompleteModal.value = false
    await loadInterviews(currentPage.value)
  } catch (err: any) {
    actionError.value = err.response?.data?.message || 'Gagal menyelesaikan Interview.'
  } finally {
    completeSaving.value = false
  }
}

// ---- Schedule new interview (HR-only, butuh pilih Candidate) ----
const candidatesInInterview = ref<CandidateOption[]>([])
const employees = ref<EmployeeOption[]>([])
const interviewStages = ref<InterviewStageOption[]>([])
const referenceLoaded = ref(false)

const showScheduleModal = ref(false)
const scheduleForm = ref({
  candidate_id: '' as number | '',
  interview_stage_id: '' as number | '',
  interviewer_employee_id: '' as number | '',
  scheduled_at: '',
  notes: '',
})
const scheduleSaving = ref(false)

async function loadScheduleReferenceData() {
  try {
    const [candRes, empRes, stageRes] = await Promise.all([
      apiClient.get('/api/candidates', { params: { status: 'interview' } }),
      apiClient.get('/api/employees'),
      apiClient.get('/api/interview-stages'),
    ])
    candidatesInInterview.value = candRes.data.data.data ?? candRes.data.data
    employees.value = empRes.data.data.data ?? empRes.data.data
    interviewStages.value = stageRes.data.data
    referenceLoaded.value = true
  } catch {
    actionError.value = 'Gagal memuat data referensi untuk penjadwalan.'
  }
}

async function openScheduleModal() {
  scheduleForm.value = {
    candidate_id: '',
    interview_stage_id: '',
    interviewer_employee_id: '',
    scheduled_at: '',
    notes: '',
  }
  showScheduleModal.value = true
  if (!referenceLoaded.value) await loadScheduleReferenceData()
}

async function submitSchedule() {
  scheduleSaving.value = true
  actionError.value = ''
  try {
    await apiClient.post('/api/interviews', {
      candidate_id: scheduleForm.value.candidate_id,
      interview_stage_id: scheduleForm.value.interview_stage_id,
      interviewer_employee_id: scheduleForm.value.interviewer_employee_id,
      scheduled_at: scheduleForm.value.scheduled_at,
      notes: scheduleForm.value.notes || null,
    })
    showScheduleModal.value = false
    await loadInterviews(1)
  } catch (err: any) {
    actionError.value = err.response?.data?.message || 'Gagal menjadwalkan Interview.'
  } finally {
    scheduleSaving.value = false
  }
}

onMounted(() => loadInterviews())
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-lg font-semibold text-slate-800">Interview Schedule</h1>
        <p class="text-sm text-slate-400">
          Kalau kamu bukan HR, halaman ini otomatis cuma nampilin interview yang jadi tanggung jawab kamu sebagai interviewer.
        </p>
      </div>

      <button
        v-if="authStore.permissions.includes('schedule interviews')"
        class="rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white"
        @click="openScheduleModal"
      >
        Schedule Interview
      </button>
    </div>

    <div v-if="actionError" class="rounded-xl bg-red-50 p-3 text-sm text-red-600">{{ actionError }}</div>

    <div class="flex flex-wrap items-center gap-3">
      <div class="relative flex-1 min-w-[220px] max-w-xs">
        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-300" />
        <input v-model="search" type="text" placeholder="Cari nama kandidat..." class="w-full rounded-xl border border-slate-200 py-2 pl-9 pr-3 text-sm" />
      </div>
      <select v-model="statusFilter" class="rounded-xl border border-slate-200 py-2 px-3 text-sm">
        <option value="">Semua Status</option>
        <option value="scheduled">Scheduled</option>
        <option value="in_progress">In Progress</option>
        <option value="completed">Completed</option>
        <option value="cancelled">Cancelled</option>
      </select>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>
    <EmptyState v-else-if="filteredInterviews.length === 0" title="Belum ada Interview" :icon="CalendarClock" />
    <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-100 text-xs uppercase text-slate-400">
          <tr>
            <th class="px-4 py-3">Kandidat</th>
            <th class="px-4 py-3">Posisi</th>
            <th class="px-4 py-3">Stage</th>
            <th class="px-4 py-3">Jadwal</th>
            <th class="px-4 py-3">Interviewer</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="i in filteredInterviews" :key="i.id" class="border-b border-slate-50 align-top">
            <td class="px-4 py-3 font-medium text-slate-700">{{ i.candidate?.full_name || '-' }}</td>
            <td class="px-4 py-3 text-slate-500">{{ i.job_vacancy?.title || '-' }}</td>
            <td class="px-4 py-3 text-slate-500">{{ i.stage?.name || '-' }}</td>
            <td class="px-4 py-3 text-slate-500">{{ i.scheduled_at?.slice(0, 16).replace('T', ' ') }}</td>
            <td class="px-4 py-3 text-slate-500">{{ employeeName(i.interviewer) }}</td>
            <td class="px-4 py-3">
              <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="STATUS_BADGE[i.result || i.status]">
                {{ i.result || i.status }}
              </span>
            </td>
            <td class="px-4 py-3 text-right">
              <div v-if="['scheduled', 'in_progress'].includes(i.status)" class="flex justify-end gap-2">
                <button
                  v-if="i.status === 'scheduled'"
                  class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs hover:bg-slate-50"
                  @click="startInterview(i)"
                >
                  Start
                </button>
                <button
                  class="rounded-lg bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-600"
                  @click="openCompleteModal(i)"
                >
                  Complete
                </button>
                <button
                  class="rounded-lg bg-red-50 px-2.5 py-1 text-xs font-medium text-red-600"
                  @click="cancelInterview(i)"
                >
                  Cancel
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="lastPage > 1" class="flex items-center justify-between border-t border-slate-100 px-4 py-3">
        <span class="text-xs text-slate-400">Halaman {{ currentPage }} dari {{ lastPage }}</span>
        <div class="flex gap-2">
          <button :disabled="currentPage === 1" class="rounded-lg border border-slate-200 px-2 py-1 text-xs disabled:opacity-40" @click="goToPage(currentPage - 1)">Prev</button>
          <button :disabled="currentPage === lastPage" class="rounded-lg border border-slate-200 px-2 py-1 text-xs disabled:opacity-40" @click="goToPage(currentPage + 1)">Next</button>
        </div>
      </div>
    </div>

    <Teleport to="body">
      <!-- Complete Interview -->
      <BaseModal v-if="showCompleteModal" title="Complete Interview" @close="showCompleteModal = false">
        <form class="space-y-3" @submit.prevent="submitComplete">
          <div>
            <label class="text-xs font-medium text-slate-500">Result</label>
            <select v-model="completeForm.result" required class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm">
              <option value="passed">Passed</option>
              <option value="failed">Failed</option>
              <option value="hold">Hold</option>
            </select>
          </div>
          <div>
            <label class="text-xs font-medium text-slate-500">Score (opsional, 0-100)</label>
            <input v-model.number="completeForm.score" type="number" min="0" max="100" class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm" />
          </div>
          <div>
            <label class="text-xs font-medium text-slate-500">Notes (opsional)</label>
            <textarea v-model="completeForm.notes" rows="2" class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm"></textarea>
          </div>
          <div>
            <label class="text-xs font-medium text-slate-500">Recommendation (opsional)</label>
            <textarea v-model="completeForm.recommendation" rows="2" class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm"></textarea>
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" class="rounded-xl border border-slate-200 px-4 py-2 text-sm" @click="showCompleteModal = false">Batal</button>
            <button type="submit" :disabled="completeSaving" class="rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white disabled:opacity-50">
              {{ completeSaving ? 'Menyimpan...' : 'Simpan' }}
            </button>
          </div>
        </form>
      </BaseModal>

      <!-- Schedule Interview -->
      <BaseModal v-if="showScheduleModal" title="Schedule Interview" @close="showScheduleModal = false">
        <form class="space-y-3" @submit.prevent="submitSchedule">
          <div>
            <label class="text-xs font-medium text-slate-500">Kandidat (status: Interview)</label>
            <select v-model="scheduleForm.candidate_id" required class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm">
              <option value="" disabled>Pilih Kandidat</option>
              <option v-for="c in candidatesInInterview" :key="c.id" :value="c.id">{{ c.full_name }}</option>
            </select>
          </div>
          <div>
            <label class="text-xs font-medium text-slate-500">Interview Stage</label>
            <select v-model="scheduleForm.interview_stage_id" required class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm">
              <option value="" disabled>Pilih Stage</option>
              <option v-for="s in interviewStages" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>
          <div>
            <label class="text-xs font-medium text-slate-500">Interviewer</label>
            <select v-model="scheduleForm.interviewer_employee_id" required class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm">
              <option value="" disabled>Pilih Employee</option>
              <option v-for="e in employees" :key="e.id" :value="e.id">{{ employeeName(e) }}</option>
            </select>
          </div>
          <div>
            <label class="text-xs font-medium text-slate-500">Scheduled Date &amp; Time</label>
            <input v-model="scheduleForm.scheduled_at" type="datetime-local" required class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm" />
          </div>
          <div>
            <label class="text-xs font-medium text-slate-500">Notes (opsional)</label>
            <textarea v-model="scheduleForm.notes" rows="2" class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm"></textarea>
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" class="rounded-xl border border-slate-200 px-4 py-2 text-sm" @click="showScheduleModal = false">Batal</button>
            <button type="submit" :disabled="scheduleSaving" class="rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white disabled:opacity-50">
              {{ scheduleSaving ? 'Menyimpan...' : 'Jadwalkan' }}
            </button>
          </div>
        </form>
      </BaseModal>
    </Teleport>
  </div>
</template>