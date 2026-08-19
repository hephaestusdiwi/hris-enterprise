<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import apiClient from '@/lib/axios'
import { useAuthStore } from '@/stores/auth'
import EmptyState from '@/components/ui/EmptyState.vue'
import { Search, ChevronLeft, ChevronRight, X } from 'lucide-vue-next'

interface JobVacancyOption {
  id: number
  title: string
}

interface CandidateRow {
  id: number
  full_name: string
  email: string
  phone: string
  status: string
  applied_at: string
  job_vacancy: JobVacancyOption | null
}

const router = useRouter()
const authStore = useAuthStore()

const loading = ref(true)
const errorMessage = ref('')
const candidates = ref<CandidateRow[]>([])
const currentPage = ref(1)
const lastPage = ref(1)

const search = ref('')
const statusFilter = ref('')
const jobVacancyFilter = ref<number | ''>('')

const jobVacancies = ref<JobVacancyOption[]>([])

const STATUS_OPTIONS = [
  { value: '', label: 'Semua Status' },
  { value: 'applied', label: 'Applied' },
  { value: 'screening', label: 'Screening' },
  { value: 'interview', label: 'Interview' },
  { value: 'selected', label: 'Selected' },
  { value: 'offering', label: 'Offering' },
  { value: 'offered', label: 'Offered' },
  { value: 'hold', label: 'Hold' },
  { value: 'hired', label: 'Hired' },
  { value: 'rejected', label: 'Rejected' },
]

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
}

async function loadCandidates(page = 1) {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/candidates', {
      params: {
        page,
        job_vacancy_id: jobVacancyFilter.value || undefined,
        status: statusFilter.value || undefined,
        search: search.value || undefined,
      },
    })
    candidates.value = response.data.data.data
    currentPage.value = response.data.data.current_page
    lastPage.value = response.data.data.last_page
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Gagal memuat daftar Candidate.'
  } finally {
    loading.value = false
  }
}

async function loadJobVacancies() {
  const response = await apiClient.get('/api/job-vacancies')
  const list = response.data.data.data ?? response.data.data
  jobVacancies.value = list.map((v: any) => ({ id: v.id, title: v.title }))
}

function resetFilters() {
  search.value = ''
  statusFilter.value = ''
  jobVacancyFilter.value = ''
}

let searchDebounce: ReturnType<typeof setTimeout> | undefined
watch(search, () => {
  clearTimeout(searchDebounce)
  searchDebounce = setTimeout(() => loadCandidates(1), 400)
})

watch([statusFilter, jobVacancyFilter], () => loadCandidates(1))

function goToDetail(id: number) {
  router.push({ name: 'candidates.show', params: { id } })
}

function goToPage(page: number) {
  if (page < 1 || page > lastPage.value) return
  loadCandidates(page)
}

onMounted(async () => {
  await loadCandidates()
  await loadJobVacancies()
})
</script>

<template>
  <div class="space-y-4">
    <div>
      <h1 class="text-lg font-semibold text-slate-800">Candidates</h1>
      <p class="text-sm text-slate-400">Pusat pipeline recruitment — screening, interview, offering, hingga hired dikelola dari detail kandidat.</p>
    </div>

    <div class="flex flex-wrap items-center gap-3">
      <div class="relative flex-1 min-w-[220px] max-w-xs">
        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-300" />
        <input v-model="search" type="text" placeholder="Cari nama atau email..." class="w-full rounded-xl border border-slate-200 py-2 pl-9 pr-3 text-sm" />
      </div>
      <select v-model="statusFilter" class="rounded-xl border border-slate-200 py-2 px-3 text-sm">
        <option v-for="opt in STATUS_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
      </select>
      <select v-model="jobVacancyFilter" class="rounded-xl border border-slate-200 py-2 px-3 text-sm">
        <option value="">Semua Job Vacancy</option>
        <option v-for="v in jobVacancies" :key="v.id" :value="v.id">{{ v.title }}</option>
      </select>
      <button
        v-if="search || statusFilter || jobVacancyFilter"
        class="flex items-center gap-1 rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-500 hover:bg-slate-50"
        @click="resetFilters"
      >
        <X class="h-3.5 w-3.5" /> Reset
      </button>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>
    <EmptyState v-else-if="candidates.length === 0" title="Belum ada Candidate" />
    <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-100 text-xs uppercase text-slate-400">
          <tr>
            <th class="px-4 py-3">Nama</th>
            <th class="px-4 py-3">Kontak</th>
            <th class="px-4 py-3">Job Vacancy</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3">Applied Date</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="c in candidates"
            :key="c.id"
            class="cursor-pointer border-b border-slate-50 hover:bg-slate-50"
            @click="goToDetail(c.id)"
          >
            <td class="px-4 py-3 font-medium text-slate-700">{{ c.full_name }}</td>
            <td class="px-4 py-3 text-slate-500">
              <div>{{ c.email }}</div>
              <div class="text-xs text-slate-400">{{ c.phone }}</div>
            </td>
            <td class="px-4 py-3 text-slate-500">{{ c.job_vacancy?.title || '-' }}</td>
            <td class="px-4 py-3">
              <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="STATUS_BADGE[c.status]">{{ c.status }}</span>
            </td>
            <td class="px-4 py-3 text-slate-500">{{ c.applied_at?.slice(0, 10) }}</td>
          </tr>
        </tbody>
      </table>

      <div v-if="lastPage > 1" class="flex items-center justify-between border-t border-slate-100 px-4 py-3">
        <span class="text-xs text-slate-400">Halaman {{ currentPage }} dari {{ lastPage }}</span>
        <div class="flex gap-2">
          <button :disabled="currentPage === 1" class="rounded-lg border border-slate-200 p-1.5 disabled:opacity-40" @click="goToPage(currentPage - 1)">
            <ChevronLeft class="h-4 w-4" />
          </button>
          <button :disabled="currentPage === lastPage" class="rounded-lg border border-slate-200 p-1.5 disabled:opacity-40" @click="goToPage(currentPage + 1)">
            <ChevronRight class="h-4 w-4" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>