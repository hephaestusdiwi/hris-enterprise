<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import apiClient from '@/lib/axios'
import { useAuthStore } from '@/stores/auth'
import BaseModal from '@/components/ui/BaseModal.vue'
import { ArrowLeft, FileText } from 'lucide-vue-next'

interface EmployeeOption {
  id: number
  first_name: string
  last_name: string | null
}

interface JobVacancyOption {
  id: number
  title: string
}

interface InterviewStageOption {
  id: number
  name: string
}

interface StageHistory {
  id: number
  from_status: string | null
  to_status: string
  notes: string | null
  cv_path: string | null
  changed_at: string
  changed_by: { name: string } | null
}

interface CandidateDetail {
  id: number
  full_name: string
  email: string
  phone: string
  source: string
  status: string
  score: number | null
  notes: string | null
  applied_at: string
  job_vacancy: { id: number; title: string } | null
  stage_histories: StageHistory[]
}

interface ScreeningRow {
  id: number
  status: string
  result: string | null
  notes: string | null
  reviewed_at: string | null
  reviewer: EmployeeOption | null
}

interface InterviewRow {
  id: number
  status: string
  result: string | null
  score: number | null
  notes: string | null
  recommendation: string | null
  scheduled_at: string
  stage: { name: string } | null
  interviewer: EmployeeOption | null
}

interface OfferingRow {
  id: number
  status: string
  proposed_start_date: string
  proposed_salary: number | null
  compensation_notes: string | null
  notes: string | null
  sent_at: string | null
}

interface NewJoinerRow {
  id: number
  status: string
}

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const candidateId = computed(() => Number(route.params.id))
const candidate = ref<CandidateDetail | null>(null)
const screenings = ref<ScreeningRow[]>([])
const interviews = ref<InterviewRow[]>([])
const offerings = ref<OfferingRow[]>([])
const newJoiners = ref<NewJoinerRow[]>([])
const employees = ref<EmployeeOption[]>([])
const jobVacancies = ref<JobVacancyOption[]>([])
const interviewStages = ref<InterviewStageOption[]>([])

const loading = ref(true)
const errorMessage = ref('')
const actionError = ref('')
const successMessage = ref('')

// ---- New Joiner Form link modal ----
const showNewJoinerLinkModal = ref(false)
const newJoinerFormLink = ref('')
const newJoinerLinkCopied = ref(false)

async function copyNewJoinerLink() {
  try {
    await navigator.clipboard.writeText(newJoinerFormLink.value)
    newJoinerLinkCopied.value = true
    setTimeout(() => (newJoinerLinkCopied.value = false), 2000)
  } catch {
    alert('Gagal menyalin link, silakan copy manual.')
  }
}

async function getNewJoinerLink(newJoinerId: number) {
  actionError.value = ''
  try {
    const response = await apiClient.get(`/api/new-joiners/${newJoinerId}`)
    const token = response.data.data.token
    newJoinerFormLink.value = `${window.location.origin}/new-joiner-form/${token}`
    newJoinerLinkCopied.value = false
    showNewJoinerLinkModal.value = true
  } catch (err: any) {
    actionError.value = err.response?.data?.message || 'Gagal mengambil link form.'
  }
}

function employeeName(e: EmployeeOption | null): string {
  if (!e) return '-'
  return [e.first_name, e.last_name].filter(Boolean).join(' ')
}

const STATUS_BADGE: Record<string, string> = {
  applied: 'bg-slate-100 text-slate-600',
  screening: 'bg-sky-50 text-sky-600',
  interview: 'bg-violet-50 text-violet-600',
  selected: 'bg-teal-50 text-teal-600',
  offering: 'bg-amber-50 text-amber-600',
  offered: 'bg-amber-100 text-amber-700',
  hold: 'bg-orange-50 text-orange-600',
  hired: 'bg-emerald-50 text-emerald-600',
  rejected: 'bg-red-50 text-red-600',
  pending: 'bg-amber-50 text-amber-600',
  completed: 'bg-emerald-50 text-emerald-600',
  cancelled: 'bg-slate-100 text-slate-400',
  scheduled: 'bg-sky-50 text-sky-600',
  in_progress: 'bg-violet-50 text-violet-600',
  passed: 'bg-emerald-50 text-emerald-600',
  failed: 'bg-red-50 text-red-600',
  draft: 'bg-slate-100 text-slate-600',
  sent: 'bg-sky-50 text-sky-600',
  accepted: 'bg-emerald-50 text-emerald-600',
  declined: 'bg-red-50 text-red-600',
  withdrawn: 'bg-slate-100 text-slate-400',
}

async function loadAll() {
  loading.value = true
  errorMessage.value = ''

  try {
    const [candRes, scrRes, intRes, offRes, njRes] = await Promise.all([
      apiClient.get(`/api/candidates/${candidateId.value}`),
      apiClient.get('/api/screenings', {
        params: { candidate_id: candidateId.value },
      }),
      apiClient.get('/api/interviews', {
        params: { candidate_id: candidateId.value },
      }),
      apiClient.get('/api/offerings', {
        params: { candidate_id: candidateId.value },
      }),
      apiClient.get('/api/new-joiners', {
        params: { candidate_id: candidateId.value },
      }),
    ])

    candidate.value = candRes.data.data
    screenings.value = scrRes.data.data.data
    interviews.value = intRes.data.data.data
    offerings.value = offRes.data.data.data
    newJoiners.value = njRes.data.data.data
  } catch (err: any) {
    errorMessage.value =
      err.response?.data?.message ||
      'Gagal memuat detail Candidate.'
  } finally {
    loading.value = false
  }
}

async function loadReferenceData() {
  const [empRes, vacRes, stageRes] = await Promise.all([
    apiClient.get('/api/employees'),
    apiClient.get('/api/job-vacancies'),
    apiClient.get('/api/interview-stages'),
  ])

  employees.value = empRes.data.data.data ?? empRes.data.data

  const vacList = vacRes.data.data.data ?? vacRes.data.data
  jobVacancies.value = vacList.map((v: any) => ({
    id: v.id,
    title: v.title,
  }))

  interviewStages.value = stageRes.data.data
}

async function runAction(fn: () => Promise<any>) {
  actionError.value = ''
  successMessage.value = ''

  try {
    await fn()
    await loadAll()
  } catch (err: any) {
    actionError.value =
      err.response?.data?.message ||
      'Aksi gagal dijalankan.'
  }
}


function downloadCv() {
  const baseUrl = apiClient.defaults.baseURL ?? ''
  window.open(`${baseUrl}/api/candidates/${candidate.value?.id}/cv`, '_blank')
}

// ---- Select / Hire ----
function selectCandidate() {
  runAction(() =>
    apiClient.post(`/api/candidates/${candidateId.value}/select`)
  )
}

function hireCandidate() {
  runAction(() =>
    apiClient.post(`/api/candidates/${candidateId.value}/hire`)
  )
}

const canOffer = computed(() =>
  offerings.value.some((o) => o.status === 'accepted')
)

// ---- Send New Joiner Form ----
const sendingNewJoiner = ref(false)

async function sendNewJoinerForm() {
  sendingNewJoiner.value = true
  actionError.value = ''
  successMessage.value = ''

  try {
    const response = await apiClient.post('/api/new-joiners', {
      candidate_id: candidateId.value,
    })

    const token = response.data.data.token
    newJoinerFormLink.value = `${window.location.origin}/new-joiner-form/${token}`
    newJoinerLinkCopied.value = false
    showNewJoinerLinkModal.value = true

    successMessage.value = 'New Joiner form berhasil dikirim.'
    await loadAll()
  } catch (err: any) {
    actionError.value =
      err.response?.data?.message ||
      'Gagal mengirim New Joiner form.'
  } finally {
    sendingNewJoiner.value = false
  }
}

// ---- Reconsider ----
const showReconsiderModal = ref(false)
const reconsiderForm = ref({
  job_vacancy_id: '' as number | '',
  notes: '',
})
const reconsiderSaving = ref(false)

async function submitReconsider() {
  reconsiderSaving.value = true

  try {
    await apiClient.post(
      `/api/candidates/${candidateId.value}/reconsider`,
      reconsiderForm.value
    )

    showReconsiderModal.value = false

    router.push({
      name: 'candidates.index',
    })
  } catch (err: any) {
    actionError.value =
      err.response?.data?.message ||
      'Gagal reconsider Candidate.'
  } finally {
    reconsiderSaving.value = false
  }
}

// ---- Hold / Talent Pool ----
const showHoldModal = ref(false)
const holdForm = ref({
  notes: '',
})
const holdSaving = ref(false)

async function submitHold() {
  holdSaving.value = true
  actionError.value = ''

  try {
    await apiClient.post(
      `/api/candidates/${candidateId.value}/hold`,
      {
        notes: holdForm.value.notes || null,
      }
    )

    showHoldModal.value = false
    holdForm.value = {
      notes: '',
    }

    await loadAll()
  } catch (err: any) {
    actionError.value =
      err.response?.data?.message ||
      'Gagal memindahkan Candidate ke Talent Pool.'
  } finally {
    holdSaving.value = false
  }
}

// ---- Screening ----
const showScreeningModal = ref(false)

const screeningForm = ref({
  reviewer_employee_id: '' as number | '',
  notes: '',
})

const screeningSaving = ref(false)

async function submitStartScreening() {
  screeningSaving.value = true

  try {
    await apiClient.post('/api/screenings', {
      candidate_id: candidateId.value,
      reviewer_employee_id: screeningForm.value.reviewer_employee_id,
      notes: screeningForm.value.notes || null,
    })

    showScreeningModal.value = false
    await loadAll()
  } catch (err: any) {
    actionError.value =
      err.response?.data?.message ||
      'Gagal memulai Screening.'
  } finally {
    screeningSaving.value = false
  }
}

function decideScreening(
  screening: ScreeningRow,
  result: 'passed' | 'failed' | 'hold'
) {
  runAction(() =>
    apiClient.post(
      `/api/screenings/${screening.id}/decide`,
      { result }
    )
  )
}

// ---- Interview ----
function startInterview(interview: InterviewRow) {
  runAction(() =>
    apiClient.post(`/api/interviews/${interview.id}/start`)
  )
}

function cancelInterview(interview: InterviewRow) {
  runAction(() =>
    apiClient.post(`/api/interviews/${interview.id}/cancel`, {})
  )
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

  completeForm.value = {
    result: 'passed',
    score: null,
    notes: '',
    recommendation: '',
  }

  showCompleteModal.value = true
}

async function submitComplete() {
  if (!completingInterview.value) return

  completeSaving.value = true

  try {
    await apiClient.post(
      `/api/interviews/${completingInterview.value.id}/complete`,
      completeForm.value
    )

    showCompleteModal.value = false
    await loadAll()
  } catch (err: any) {
    actionError.value =
      err.response?.data?.message ||
      'Gagal menyelesaikan Interview.'
  } finally {
    completeSaving.value = false
  }
}

const showScheduleInterviewModal = ref(false)

const scheduleInterviewForm = ref({
  interview_stage_id: '' as number | '',
  interviewer_employee_id: '' as number | '',
  scheduled_at: '',
  notes: '',
})

const scheduleInterviewSaving = ref(false)

async function submitScheduleInterview() {
  scheduleInterviewSaving.value = true

  try {
    await apiClient.post('/api/interviews', {
      candidate_id: candidateId.value,
      interview_stage_id:
        scheduleInterviewForm.value.interview_stage_id,
      interviewer_employee_id:
        scheduleInterviewForm.value.interviewer_employee_id,
      scheduled_at:
        scheduleInterviewForm.value.scheduled_at,
      notes: scheduleInterviewForm.value.notes || null,
    })

    showScheduleInterviewModal.value = false

    scheduleInterviewForm.value = {
      interview_stage_id: '',
      interviewer_employee_id: '',
      scheduled_at: '',
      notes: '',
    }

    await loadAll()
  } catch (err: any) {
    actionError.value =
      err.response?.data?.message ||
      'Gagal menjadwalkan Interview.'
  } finally {
    scheduleInterviewSaving.value = false
  }
}

// ---- Offering ----
const showOfferingModal = ref(false)

const offeringForm = ref({
  proposed_start_date: '',
  proposed_salary: null as number | null,
  compensation_notes: '',
  notes: '',
})

const offeringSaving = ref(false)

async function submitCreateOffering() {
  offeringSaving.value = true

  try {
    await apiClient.post('/api/offerings', {
      candidate_id: candidateId.value,
      proposed_start_date:
        offeringForm.value.proposed_start_date,
      proposed_salary:
        offeringForm.value.proposed_salary,
      compensation_notes:
        offeringForm.value.compensation_notes || null,
      notes: offeringForm.value.notes || null,
    })

    showOfferingModal.value = false
    await loadAll()
  } catch (err: any) {
    actionError.value =
      err.response?.data?.message ||
      'Gagal membuat Offering.'
  } finally {
    offeringSaving.value = false
  }
}

function sendOffering(offering: OfferingRow) {
  runAction(() =>
    apiClient.post(`/api/offerings/${offering.id}/send`)
  )
}

function respondOffering(
  offering: OfferingRow,
  response: 'accepted' | 'declined'
) {
  runAction(() =>
    apiClient.post(
      `/api/offerings/${offering.id}/respond`,
      { response }
    )
  )
}

function withdrawOffering(offering: OfferingRow) {
  runAction(() =>
    apiClient.post(`/api/offerings/${offering.id}/withdraw`)
  )
}

onMounted(async () => {
  await loadAll()
  await loadReferenceData()
})
</script>

<template>
  <div class="space-y-4">
    <button
      class="flex items-center gap-1 text-sm text-slate-400 hover:text-slate-600"
      @click="router.push({ name: 'candidates.index' })"
    >
      <ArrowLeft class="h-4 w-4" />
      Kembali
    </button>

    <div
      v-if="loading"
      class="text-sm text-slate-400"
    >
      Memuat data...
    </div>

    <div
      v-else-if="errorMessage"
      class="rounded-xl bg-red-50 p-4 text-sm text-red-600"
    >
      {{ errorMessage }}
    </div>

    <div
      v-else-if="candidate"
      class="space-y-4"
    >
      <div
        v-if="actionError"
        class="rounded-xl bg-red-50 p-3 text-sm text-red-600"
      >
        {{ actionError }}
      </div>

      <div
        v-if="successMessage"
        class="rounded-xl bg-emerald-50 p-3 text-sm text-emerald-600"
      >
        {{ successMessage }}
      </div>

      <!-- Header -->
      <div
        class="rounded-2xl border border-slate-100 bg-white p-6 shadow-[0_1px_3px_rgba(15,23,42,0.04)]"
      >
        <div class="flex items-start justify-between">
          <div>
            <h1 class="text-lg font-semibold text-slate-800">
              {{ candidate.full_name }}
            </h1>

            <p class="text-sm text-slate-400">
              {{ candidate.job_vacancy?.title || '-' }}
              · sumber: {{ candidate.source }}
            </p>
          </div>

          <span
            class="rounded-full px-3 py-1 text-xs font-medium"
            :class="STATUS_BADGE[candidate.status]"
          >
            {{ candidate.status }}
          </span>
        </div>

        <dl class="mt-4 grid grid-cols-4 gap-4 text-sm">
          <div>
            <dt class="text-slate-400">Email</dt>
            <dd class="font-medium text-slate-700">
              {{ candidate.email }}
            </dd>
          </div>

          <div>
            <dt class="text-slate-400">Phone</dt>
            <dd class="font-medium text-slate-700">
              {{ candidate.phone }}
            </dd>
          </div>

          <div>
            <dt class="text-slate-400">Applied Date</dt>
            <dd class="font-medium text-slate-700">
              {{ candidate.applied_at?.slice(0, 10) }}
            </dd>
          </div>

          <div v-if="candidate.score !== null">
            <dt class="text-slate-400">Score</dt>
            <dd class="font-medium text-slate-700">
              {{ candidate.score }}
            </dd>
          </div>
        <div v-if="candidate.cv_path">
          <dd class="mt-1">
            <button
              type="button"
              class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-600 transition hover:bg-slate-50 hover:text-slate-800"
              @click="downloadCv"
            >
              <FileText class="h-3.5 w-3.5" />
              Lihat Resume
            </button>
          </dd>
        </div>
        </dl>

        <!-- Action bar -->
        <div
          class="mt-4 flex flex-wrap gap-2 border-t border-slate-100 pt-4"
        >
          <button
            v-if="candidate.status === 'applied'"
            class="rounded-xl bg-primary px-3 py-1.5 text-sm font-medium text-white"
            @click="showScreeningModal = true"
          >
            Start Screening
          </button>

          <button
            v-if="candidate.status === 'interview'"
            class="rounded-xl border border-slate-200 px-3 py-1.5 text-sm hover:bg-slate-50"
            @click="showScheduleInterviewModal = true"
          >
            Schedule Interview
          </button>

          <button
            v-if="candidate.status === 'interview'"
            class="rounded-xl bg-primary px-3 py-1.5 text-sm font-medium text-white"
            @click="selectCandidate"
          >
            Mark Selected
          </button>

          <button
            v-if="candidate.status === 'selected'"
            class="rounded-xl bg-primary px-3 py-1.5 text-sm font-medium text-white"
            @click="showOfferingModal = true"
          >
            Create Offering
          </button>

          <!-- Add to Talent Pool -->
          <button
            v-if="
              ['applied', 'screening', 'interview', 'selected'].includes(candidate.status) &&
              authStore.permissions.includes('hold candidates')
            "
            class="rounded-xl border border-orange-200 px-3 py-1.5 text-sm text-orange-600 hover:bg-orange-50"
            @click="showHoldModal = true"
          >
            Add to Talent Pool
          </button>

          <button
            v-if="candidate.status === 'offered' && canOffer"
            class="rounded-xl bg-primary px-3 py-1.5 text-sm font-medium text-white"
            @click="hireCandidate"
          >
            Mark Hired
          </button>

          <!-- Reconsider -->
          <button
            v-if="candidate.status === 'hold'"
            class="rounded-xl border border-slate-200 px-3 py-1.5 text-sm hover:bg-slate-50"
            @click="showReconsiderModal = true"
          >
            Reconsider
          </button>

          <!-- Send New Joiner Form -->
          <button
            v-if="
              candidate.status === 'hired' &&
              newJoiners.length === 0 &&
              authStore.permissions.includes('manage new joiners')
            "
            :disabled="sendingNewJoiner"
            class="rounded-xl bg-primary px-3 py-1.5 text-sm font-medium text-white disabled:opacity-50"
            @click="sendNewJoinerForm"
          >
            {{
              sendingNewJoiner
                ? 'Mengirim...'
                : 'Send New Joiner Form'
            }}
          </button>

          <span
            v-else-if="
              candidate.status === 'hired' &&
              newJoiners.length > 0 &&
              newJoiners[0].status !== 'sent'
            "
            class="rounded-xl bg-slate-50 px-3 py-1.5 text-sm text-slate-400"
          >
            New Joiner form sudah dikirim
          </span>

          <button
            v-else-if="
              candidate.status === 'hired' &&
              newJoiners.length > 0 &&
              newJoiners[0].status === 'sent' &&
              authStore.permissions.includes('manage new joiners')
            "
            class="rounded-xl border border-slate-200 px-3 py-1.5 text-sm hover:bg-slate-50"
            @click="getNewJoinerLink(newJoiners[0].id)"
          >
            Lihat Link Form
          </button>
        </div>
      </div>

      <!-- Screening -->
      <div
        v-if="screenings.length"
        class="rounded-2xl border border-slate-100 bg-white p-6 shadow-[0_1px_3px_rgba(15,23,42,0.04)]"
      >
        <h2 class="text-sm font-semibold text-slate-700">
          Screening
        </h2>

        <div class="mt-3 space-y-2">
          <div
            v-for="s in screenings"
            :key="s.id"
            class="rounded-xl border border-slate-100 p-3 text-sm"
          >
            <div class="flex items-center justify-between">
              <span>
                Reviewer: {{ employeeName(s.reviewer) }}
              </span>

              <span
                class="rounded-full px-2 py-0.5 text-xs font-medium"
                :class="STATUS_BADGE[s.result || s.status]"
              >
                {{ s.result || s.status }}
              </span>
            </div>

            <p
              v-if="s.notes"
              class="mt-1 text-xs text-slate-400"
            >
              {{ s.notes }}
            </p>

            <div
              v-if="s.status === 'pending'"
              class="mt-2 flex gap-2"
            >
              <button
                class="rounded-lg bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-600"
                @click="decideScreening(s, 'passed')"
              >
                Pass
              </button>

              <button
                class="rounded-lg bg-red-50 px-2.5 py-1 text-xs font-medium text-red-600"
                @click="decideScreening(s, 'failed')"
              >
                Fail
              </button>

              <button
                class="rounded-lg bg-orange-50 px-2.5 py-1 text-xs font-medium text-orange-600"
                @click="decideScreening(s, 'hold')"
              >
                Hold
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Interview -->
      <div
        v-if="interviews.length"
        class="rounded-2xl border border-slate-100 bg-white p-6 shadow-[0_1px_3px_rgba(15,23,42,0.04)]"
      >
        <h2 class="text-sm font-semibold text-slate-700">
          Interview
        </h2>

        <div class="mt-3 space-y-2">
          <div
            v-for="i in interviews"
            :key="i.id"
            class="rounded-xl border border-slate-100 p-3 text-sm"
          >
            <div class="flex items-center justify-between">
              <span>
                {{ i.stage?.name || 'Stage' }}
                — {{ employeeName(i.interviewer) }}
              </span>

              <span
                class="rounded-full px-2 py-0.5 text-xs font-medium"
                :class="STATUS_BADGE[i.result || i.status]"
              >
                {{ i.result || i.status }}
              </span>
            </div>

            <p class="mt-1 text-xs text-slate-400">
              Jadwal: {{ i.scheduled_at }}
            </p>

            <div
              v-if="['scheduled', 'in_progress'].includes(i.status)"
              class="mt-2 flex gap-2"
            >
              <button
                v-if="i.status === 'scheduled'"
                class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs"
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
          </div>
        </div>
      </div>

      <!-- Offering -->
      <div
        v-if="offerings.length"
        class="rounded-2xl border border-slate-100 bg-white p-6 shadow-[0_1px_3px_rgba(15,23,42,0.04)]"
      >
        <h2 class="text-sm font-semibold text-slate-700">
          Offering
        </h2>

        <div class="mt-3 space-y-2">
          <div
            v-for="o in offerings"
            :key="o.id"
            class="rounded-xl border border-slate-100 p-3 text-sm"
          >
            <div class="flex items-center justify-between">
              <span>
                Start: {{ o.proposed_start_date }}

                <span v-if="o.proposed_salary">
                  · Rp {{ o.proposed_salary.toLocaleString('id-ID') }}
                </span>
              </span>

              <span
                class="rounded-full px-2 py-0.5 text-xs font-medium"
                :class="STATUS_BADGE[o.status]"
              >
                {{ o.status }}
              </span>
            </div>

            <div class="mt-2 flex gap-2">
              <button
                v-if="o.status === 'draft'"
                class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs"
                @click="sendOffering(o)"
              >
                Send
              </button>

              <template v-if="o.status === 'sent'">
                <button
                  class="rounded-lg bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-600"
                  @click="respondOffering(o, 'accepted')"
                >
                  Accepted
                </button>

                <button
                  class="rounded-lg bg-red-50 px-2.5 py-1 text-xs font-medium text-red-600"
                  @click="respondOffering(o, 'declined')"
                >
                  Declined
                </button>
              </template>

              <button
                v-if="['draft', 'sent'].includes(o.status)"
                class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs"
                @click="withdrawOffering(o)"
              >
                Withdraw
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Timeline -->
      <div
        v-if="candidate.stage_histories?.length"
        class="rounded-2xl border border-slate-100 bg-white p-6 shadow-[0_1px_3px_rgba(15,23,42,0.04)]"
      >
        <h2 class="text-sm font-semibold text-slate-700">
          History
        </h2>

        <ol
          class="mt-3 space-y-2 border-l border-slate-100 pl-4"
        >
          <li
            v-for="h in candidate.stage_histories"
            :key="h.id"
            class="text-sm"
          >
            <span class="font-medium text-slate-700">
              {{ h.from_status || 'start' }} → {{ h.to_status }}
            </span>

            <span class="text-slate-400">
              · {{ h.changed_at?.slice(0, 16).replace('T', ' ') }}
            </span>

            <span
              v-if="h.changed_by"
              class="text-slate-400"
            >
              · {{ h.changed_by.name }}
            </span>

            <p
              v-if="h.notes"
              class="text-xs text-slate-400"
            >
              {{ h.notes }}
            </p>
          </li>
        </ol>
      </div>
    </div>

    <!-- Modals -->
    <Teleport to="body">
      <!-- New Joiner Form Link -->
      <BaseModal
        v-if="showNewJoinerLinkModal"
        title="Link New Joiner Form"
        @close="showNewJoinerLinkModal = false"
      >
        <div class="space-y-4">
          <div class="flex items-start gap-2 rounded-xl bg-amber-50 p-3 text-xs text-amber-700">
            Kirim link ini ke kandidat lewat email/WA secara manual — sistem belum mengirim otomatis.
          </div>

          <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
            <code class="flex-1 break-all font-mono text-xs text-slate-700">{{ newJoinerFormLink }}</code>
            <button
              class="shrink-0 rounded-lg bg-white px-3 py-1.5 text-xs font-medium text-slate-600 shadow-sm hover:bg-slate-100"
              @click="copyNewJoinerLink"
            >
              {{ newJoinerLinkCopied ? 'Tersalin' : 'Copy' }}
            </button>
          </div>
        </div>
      </BaseModal>

      <!-- Start Screening -->
      <BaseModal
        v-if="showScreeningModal"
        title="Start Screening"
        @close="showScreeningModal = false"
      >
        <form
          class="space-y-3"
          @submit.prevent="submitStartScreening"
        >
          <div>
            <label class="text-xs font-medium text-slate-500">
              Reviewer
            </label>

            <select
              v-model="screeningForm.reviewer_employee_id"
              required
              class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm"
            >
              <option value="" disabled>
                Pilih Employee
              </option>

              <option
                v-for="e in employees"
                :key="e.id"
                :value="e.id"
              >
                {{ employeeName(e) }}
              </option>
            </select>
          </div>

          <div>
            <label class="text-xs font-medium text-slate-500">
              Notes (opsional)
            </label>

            <textarea
              v-model="screeningForm.notes"
              rows="2"
              class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm"
            />
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button
              type="button"
              class="rounded-xl border border-slate-200 px-4 py-2 text-sm"
              @click="showScreeningModal = false"
            >
              Batal
            </button>

            <button
              type="submit"
              :disabled="screeningSaving"
              class="rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
            >
              Mulai
            </button>
          </div>
        </form>
      </BaseModal>

      <!-- Reconsider -->
      <BaseModal
        v-if="showReconsiderModal"
        title="Reconsider Candidate"
        @close="showReconsiderModal = false"
      >
        <form
          class="space-y-3"
          @submit.prevent="submitReconsider"
        >
          <div>
            <label class="text-xs font-medium text-slate-500">
              Job Vacancy Tujuan
            </label>

            <select
              v-model="reconsiderForm.job_vacancy_id"
              required
              class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm"
            >
              <option value="" disabled>
                Pilih Job Vacancy
              </option>

              <option
                v-for="v in jobVacancies"
                :key="v.id"
                :value="v.id"
              >
                {{ v.title }}
              </option>
            </select>
          </div>

          <div>
            <label class="text-xs font-medium text-slate-500">
              Notes (opsional)
            </label>

            <textarea
              v-model="reconsiderForm.notes"
              rows="2"
              class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm"
            />
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button
              type="button"
              class="rounded-xl border border-slate-200 px-4 py-2 text-sm"
              @click="showReconsiderModal = false"
            >
              Batal
            </button>

            <button
              type="submit"
              :disabled="reconsiderSaving"
              class="rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
            >
              Reconsider
            </button>
          </div>
        </form>
      </BaseModal>

      <!-- Add to Talent Pool -->
      <BaseModal
        v-if="showHoldModal"
        title="Add to Talent Pool"
        @close="showHoldModal = false"
      >
        <form
          class="space-y-3"
          @submit.prevent="submitHold"
        >
          <p class="text-xs text-slate-400">
            Candidate akan dipindahkan ke status Hold dan bisa
            di-reconsider ke Job Vacancy lain nanti.
          </p>

          <div>
            <label class="text-xs font-medium text-slate-500">
              Notes (opsional)
            </label>

            <textarea
              v-model="holdForm.notes"
              rows="2"
              class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm"
            />
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button
              type="button"
              class="rounded-xl border border-slate-200 px-4 py-2 text-sm"
              @click="showHoldModal = false"
            >
              Batal
            </button>

            <button
              type="submit"
              :disabled="holdSaving"
              class="rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
            >
              {{
                holdSaving
                  ? 'Memproses...'
                  : 'Add to Talent Pool'
              }}
            </button>
          </div>
        </form>
      </BaseModal>

      <!-- Create Offering -->
      <BaseModal
        v-if="showOfferingModal"
        title="Create Offering"
        @close="showOfferingModal = false"
      >
        <form
          class="space-y-3"
          @submit.prevent="submitCreateOffering"
        >
          <div>
            <label class="text-xs font-medium text-slate-500">
              Proposed Start Date
            </label>

            <input
              v-model="offeringForm.proposed_start_date"
              type="date"
              required
              class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm"
            />
          </div>

          <div>
            <label class="text-xs font-medium text-slate-500">
              Proposed Salary (opsional)
            </label>

            <input
              v-model.number="offeringForm.proposed_salary"
              type="number"
              min="0"
              class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm"
            />
          </div>

          <div>
            <label class="text-xs font-medium text-slate-500">
              Compensation Notes (opsional)
            </label>

            <textarea
              v-model="offeringForm.compensation_notes"
              rows="2"
              class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm"
            />
          </div>

          <div>
            <label class="text-xs font-medium text-slate-500">
              Notes (opsional)
            </label>

            <textarea
              v-model="offeringForm.notes"
              rows="2"
              class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm"
            />
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button
              type="button"
              class="rounded-xl border border-slate-200 px-4 py-2 text-sm"
              @click="showOfferingModal = false"
            >
              Batal
            </button>

            <button
              type="submit"
              :disabled="offeringSaving"
              class="rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
            >
              Simpan
            </button>
          </div>
        </form>
      </BaseModal>

      <!-- Complete Interview -->
      <BaseModal
        v-if="showCompleteModal"
        title="Complete Interview"
        @close="showCompleteModal = false"
      >
        <form
          class="space-y-3"
          @submit.prevent="submitComplete"
        >
          <div>
            <label class="text-xs font-medium text-slate-500">
              Result
            </label>

            <select
              v-model="completeForm.result"
              class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm"
            >
              <option value="passed">Passed</option>
              <option value="failed">Failed</option>
              <option value="hold">Hold</option>
            </select>
          </div>

          <div>
            <label class="text-xs font-medium text-slate-500">
              Score (opsional, 0-100)
            </label>

            <input
              v-model.number="completeForm.score"
              type="number"
              min="0"
              max="100"
              class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm"
            />
          </div>

          <div>
            <label class="text-xs font-medium text-slate-500">
              Notes (opsional)
            </label>

            <textarea
              v-model="completeForm.notes"
              rows="2"
              class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm"
            />
          </div>

          <div>
            <label class="text-xs font-medium text-slate-500">
              Recommendation (opsional)
            </label>

            <textarea
              v-model="completeForm.recommendation"
              rows="2"
              class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm"
            />
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button
              type="button"
              class="rounded-xl border border-slate-200 px-4 py-2 text-sm"
              @click="showCompleteModal = false"
            >
              Batal
            </button>

            <button
              type="submit"
              :disabled="completeSaving"
              class="rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
            >
              Simpan
            </button>
          </div>
        </form>
      </BaseModal>

      <!-- Schedule Interview -->
      <BaseModal
        v-if="showScheduleInterviewModal"
        title="Schedule Interview"
        @close="showScheduleInterviewModal = false"
      >
        <form
          class="space-y-3"
          @submit.prevent="submitScheduleInterview"
        >
          <div>
            <label class="text-xs font-medium text-slate-500">
              Interview Stage
            </label>

            <select
              v-model="scheduleInterviewForm.interview_stage_id"
              required
              class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm"
            >
              <option value="" disabled>
                Pilih Stage
              </option>

              <option
                v-for="s in interviewStages"
                :key="s.id"
                :value="s.id"
              >
                {{ s.name }}
              </option>
            </select>
          </div>

          <div>
            <label class="text-xs font-medium text-slate-500">
              Interviewer
            </label>

            <select
              v-model="scheduleInterviewForm.interviewer_employee_id"
              required
              class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm"
            >
              <option value="" disabled>
                Pilih Employee
              </option>

              <option
                v-for="e in employees"
                :key="e.id"
                :value="e.id"
              >
                {{ employeeName(e) }}
              </option>
            </select>
          </div>

          <div>
            <label class="text-xs font-medium text-slate-500">
              Scheduled Date &amp; Time
            </label>

            <input
              v-model="scheduleInterviewForm.scheduled_at"
              type="datetime-local"
              required
              class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm"
            />
          </div>

          <div>
            <label class="text-xs font-medium text-slate-500">
              Notes (opsional)
            </label>

            <textarea
              v-model="scheduleInterviewForm.notes"
              rows="2"
              class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm"
            />
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button
              type="button"
              class="rounded-xl border border-slate-200 px-4 py-2 text-sm"
              @click="showScheduleInterviewModal = false"
            >
              Batal
            </button>

            <button
              type="submit"
              :disabled="scheduleInterviewSaving"
              class="rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
            >
              Jadwalkan
            </button>
          </div>
        </form>
      </BaseModal>
    </Teleport>
  </div>
</template>