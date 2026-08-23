<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import apiClient from '@/lib/axios'
import EmptyState from '@/components/ui/EmptyState.vue'
import { Search, ChevronLeft, ChevronRight } from 'lucide-vue-next'

interface JobVacancyOption {
  id: number
  title: string
}

interface CandidateRow {
  id: number
  full_name: string
  email: string
  phone: string
  notes: string | null
  applied_at: string
  job_vacancy: JobVacancyOption | null
}

const router = useRouter()

const loading = ref(true)
const errorMessage = ref('')
const candidates = ref<CandidateRow[]>([])
const currentPage = ref(1)
const lastPage = ref(1)

const search = ref('')
const jobVacancyFilter = ref<number | ''>('')
const jobVacancies = ref<JobVacancyOption[]>([])

async function loadTalentPool(page = 1) {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/candidates', {
      params: {
        page,
        status: 'hold', // dedicated ke Talent Pool — bukan filter yang bisa diubah user
        job_vacancy_id: jobVacancyFilter.value || undefined,
        search: search.value || undefined,
      },
    })
    candidates.value = response.data.data.data
    currentPage.value = response.data.data.current_page
    lastPage.value = response.data.data.last_page
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Gagal memuat Talent Pool.'
  } finally {
    loading.value = false
  }
}

async function loadJobVacancies() {
  const response = await apiClient.get('/api/job-vacancies')
  const list = response.data.data.data ?? response.data.data
  jobVacancies.value = list.map((v: any) => ({ id: v.id, title: v.title }))
}

let searchDebounce: ReturnType<typeof setTimeout> | undefined
watch(search, () => {
  clearTimeout(searchDebounce)
  searchDebounce = setTimeout(() => loadTalentPool(1), 400)
})
watch(jobVacancyFilter, () => loadTalentPool(1))

function goToDetail(id: number) {
  router.push({ name: 'candidates.show', params: { id } }) // reuse Detail existing — tidak duplikasi logic reconsider
}

function goToPage(page: number) {
  if (page < 1 || page > lastPage.value) return
  loadTalentPool(page)
}

onMounted(async () => {
  await loadTalentPool()
  await loadJobVacancies()
})
</script>

<template>
  <div class="space-y-4">
    <div>
      <h1 class="text-lg font-semibold text-slate-800">Talent Pool</h1>
      <p class="text-sm text-slate-400">Kandidat yang disimpan (Hold) — bisa dipertimbangkan ulang untuk vacancy lain lewat Reconsider.</p>
    </div>

    <div class="flex flex-wrap items-center gap-3">
      <div class="relative flex-1 min-w-[220px] max-w-xs">
        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-300" />
        <input v-model="search" type="text" placeholder="Cari nama atau email..." class="w-full rounded-xl border border-slate-200 py-2 pl-9 pr-3 text-sm" />
      </div>
      <select v-model="jobVacancyFilter" class="rounded-xl border border-slate-200 py-2 px-3 text-sm">
        <option value="">Semua Job Vacancy Asal</option>
        <option v-for="v in jobVacancies" :key="v.id" :value="v.id">{{ v.title }}</option>
      </select>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>
    <EmptyState
    v-else-if="candidates.length === 0"
    :icon="Search"
    title="Talent Pool masih kosong"
    description="Belum ada kandidat dengan status Hold yang tersedia di Talent Pool."
    />
    <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-100 text-xs uppercase text-slate-400">
          <tr>
            <th class="px-4 py-3">Nama</th>
            <th class="px-4 py-3">Kontak</th>
            <th class="px-4 py-3">Job Vacancy Asal</th>
            <th class="px-4 py-3">Notes</th>
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
            <td class="px-4 py-3 text-xs text-slate-400">{{ c.notes || '-' }}</td>
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