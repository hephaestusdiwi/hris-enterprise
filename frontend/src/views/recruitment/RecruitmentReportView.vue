<script setup lang="ts">
import { ref, onMounted } from 'vue'
import apiClient from '@/lib/axios'
import { Clock, Target, CheckCircle2, Users } from 'lucide-vue-next'

interface RefOption {
  id: number
  name: string
}

interface EmployeeOption {
  id: number
  first_name: string
  last_name: string | null
}

interface ReportRow {
  candidate: { id: number; full_name: string }
  job_vacancy: { id: number; title: string } | null
  recruiter: EmployeeOption | null
  company: RefOption | null
  branch: RefOption | null
  published_at: string | null
  total_stages: number
  hired_at: string
  time_to_hire_days: number | null
  time_to_fill_days: number | null
}

interface ReportSummary {
  avg_time_to_hire_days: number | null
  avg_time_to_fill_days: number | null
  acceptance_rate_percent: number | null
  total_hired: number
}

interface JobVacancyOption {
  id: number
  title: string
}

const loading = ref(true)
const errorMessage = ref('')

const summary = ref<ReportSummary | null>(null)
const rows = ref<ReportRow[]>([])
const jobVacancies = ref<JobVacancyOption[]>([])

const dateFrom = ref('')
const dateTo = ref('')
const jobVacancyId = ref<number | ''>('')

function employeeName(e: EmployeeOption | null): string {
  if (!e) return '-'
  return [e.first_name, e.last_name].filter(Boolean).join(' ')
}

function formatDate(s: string | null): string {
  return s ? s.slice(0, 10) : '-'
}

async function loadReport() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/recruitment-report/time-to-hire', {
      params: {
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
        job_vacancy_id: jobVacancyId.value || undefined,
      },
    })
    summary.value = response.data.data.summary
    rows.value = response.data.data.rows
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Gagal memuat Recruitment Report.'
  } finally {
    loading.value = false
  }
}

async function loadJobVacancies() {
  try {
    const response = await apiClient.get('/api/job-vacancies')
    const list = response.data.data.data ?? response.data.data
    jobVacancies.value = list.map((v: any) => ({ id: v.id, title: v.title }))
  } catch {
    // Dropdown filter opsional — kalau gagal, biarkan kosong, jangan blok report utama
  }
}

function exportCsv() {
  const header = ['Candidate', 'Job Vacancy', 'Recruiter', 'Organization', 'Branch', 'Publication Date', 'Total Stages', 'Hired Date', 'Time to Hire (days)', 'Time to Fill (days)']
  const lines = rows.value.map((r) => [
    r.candidate.full_name,
    r.job_vacancy?.title || '-',
    employeeName(r.recruiter),
    r.company?.name || '-',
    r.branch?.name || '-',
    formatDate(r.published_at),
    r.total_stages,
    formatDate(r.hired_at),
    r.time_to_hire_days ?? '-',
    r.time_to_fill_days ?? '-',
  ])
  const csv = [header, ...lines].map((row) => row.map((cell) => `"${String(cell).replace(/"/g, '""')}"`).join(',')).join('\n')
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `time-to-hire-report-${new Date().toISOString().slice(0, 10)}.csv`
  a.click()
  URL.revokeObjectURL(url)
}

onMounted(() => {
  loadJobVacancies()
  loadReport()
})
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-lg font-semibold text-slate-800">Time to Hire Report</h1>
        <p class="text-sm text-slate-400">Time to Hire, Time to Fill, dan Acceptance Rate untuk kandidat yang sudah Hired.</p>
      </div>
      <button v-if="rows.length > 0" class="rounded-xl border border-slate-200 px-4 py-2 text-sm hover:bg-slate-50" @click="exportCsv">
        Export CSV
      </button>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-end gap-3 rounded-2xl border border-slate-100 bg-white p-4">
      <div>
        <label class="text-xs font-medium text-slate-500">Hired Dari</label>
        <input v-model="dateFrom" type="date" class="mt-1 rounded-xl border border-slate-200 p-2 text-sm" @change="loadReport" />
      </div>
      <div>
        <label class="text-xs font-medium text-slate-500">Hired Sampai</label>
        <input v-model="dateTo" type="date" class="mt-1 rounded-xl border border-slate-200 p-2 text-sm" @change="loadReport" />
      </div>
      <div>
        <label class="text-xs font-medium text-slate-500">Job Vacancy</label>
        <select v-model="jobVacancyId" class="mt-1 rounded-xl border border-slate-200 p-2 text-sm" @change="loadReport">
          <option value="">Semua</option>
          <option v-for="v in jobVacancies" :key="v.id" :value="v.id">{{ v.title }}</option>
        </select>
      </div>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>

    <template v-else-if="summary">
      <!-- Summary cards -->
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
        <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
          <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-primary-dark">
              <Clock class="h-5 w-5" :stroke-width="1.75" />
            </div>
            <div>
              <p class="text-2xl font-bold text-slate-900">{{ summary.avg_time_to_hire_days ?? '-' }}</p>
              <p class="text-xs text-slate-400">Avg Time to Hire (hari)</p>
            </div>
          </div>
        </div>
        <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
          <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
              <Target class="h-5 w-5" :stroke-width="1.75" />
            </div>
            <div>
              <p class="text-2xl font-bold text-slate-900">{{ summary.avg_time_to_fill_days ?? '-' }}</p>
              <p class="text-xs text-slate-400">Avg Time to Fill (hari)</p>
            </div>
          </div>
        </div>
        <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
          <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
              <CheckCircle2 class="h-5 w-5" :stroke-width="1.75" />
            </div>
            <div>
              <p class="text-2xl font-bold text-slate-900">{{ summary.acceptance_rate_percent !== null ? summary.acceptance_rate_percent + '%' : '-' }}</p>
              <p class="text-xs text-slate-400">Acceptance Rate</p>
            </div>
          </div>
        </div>
        <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
          <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
              <Users class="h-5 w-5" :stroke-width="1.75" />
            </div>
            <div>
              <p class="text-2xl font-bold text-slate-900">{{ summary.total_hired }}</p>
              <p class="text-xs text-slate-400">Total Hired</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Table -->
      <div v-if="rows.length === 0" class="rounded-2xl border border-slate-100 bg-white p-10 text-center text-sm text-slate-400">
        Belum ada kandidat Hired pada rentang/filter ini.
      </div>
      <div v-else class="overflow-x-auto rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <table class="w-full text-left text-sm">
          <thead class="border-b border-slate-100 text-xs uppercase text-slate-400">
            <tr>
              <th class="px-4 py-3">Candidate</th>
              <th class="px-4 py-3">Job Vacancy</th>
              <th class="px-4 py-3">Recruiter</th>
              <th class="px-4 py-3">Organization</th>
              <th class="px-4 py-3">Branch</th>
              <th class="px-4 py-3">Publication Date</th>
              <th class="px-4 py-3">Total Stages</th>
              <th class="px-4 py-3">Hired Date</th>
              <th class="px-4 py-3">Time to Hire</th>
              <th class="px-4 py-3">Time to Fill</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in rows" :key="r.candidate.id" class="border-b border-slate-50">
              <td class="px-4 py-3 font-medium text-slate-700">{{ r.candidate.full_name }}</td>
              <td class="px-4 py-3 text-slate-500">{{ r.job_vacancy?.title || '-' }}</td>
              <td class="px-4 py-3 text-slate-500">{{ employeeName(r.recruiter) }}</td>
              <td class="px-4 py-3 text-slate-500">{{ r.company?.name || '-' }}</td>
              <td class="px-4 py-3 text-slate-500">{{ r.branch?.name || '-' }}</td>
              <td class="px-4 py-3 text-slate-500">{{ formatDate(r.published_at) }}</td>
              <td class="px-4 py-3 text-slate-500">{{ r.total_stages }}</td>
              <td class="px-4 py-3 text-slate-500">{{ formatDate(r.hired_at) }}</td>
              <td class="px-4 py-3 font-medium text-slate-700">{{ r.time_to_hire_days ?? '-' }} hari</td>
              <td class="px-4 py-3 font-medium text-slate-700">{{ r.time_to_fill_days ?? '-' }} hari</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>