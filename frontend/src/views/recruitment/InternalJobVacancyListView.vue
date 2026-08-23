<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import apiClient from '@/lib/axios'
import EmptyState from '@/components/ui/EmptyState.vue'
import { Briefcase, MapPin } from 'lucide-vue-next'

interface InternalVacancy {
  title: string
  slug: string
  employment_type: string | null
  company: string | null
  branch: string | null
  application_deadline: string | null
}

const router = useRouter()
const vacancies = ref<InternalVacancy[]>([])
const loading = ref(true)
const errorMessage = ref('')

async function loadVacancies() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/job-vacancies/self-service')
    vacancies.value = response.data.data
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Gagal memuat lowongan internal.'
  } finally {
    loading.value = false
  }
}

function goToDetail(slug: string) {
  router.push({ name: 'internal-job-vacancies.show', params: { slug } })
}

onMounted(loadVacancies)
</script>

<template>
  <div class="space-y-4">
    <div>
      <h1 class="text-lg font-semibold text-slate-800">Lowongan Internal</h1>
      <p class="text-sm text-slate-400">Lowongan yang terbuka untuk karyawan internal.</p>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>
    <EmptyState v-else-if="vacancies.length === 0" title="Belum ada lowongan internal yang dibuka" />

    <div v-else class="space-y-3">
      <button
        v-for="v in vacancies"
        :key="v.slug"
        class="block w-full rounded-2xl border border-slate-100 bg-white p-4 text-left shadow-[0_1px_3px_rgba(15,23,42,0.04)] hover:border-slate-200"
        @click="goToDetail(v.slug)"
      >
        <h2 class="font-medium text-slate-800">{{ v.title }}</h2>
        <div class="mt-1 flex flex-wrap gap-3 text-xs text-slate-400">
          <span v-if="v.company" class="flex items-center gap-1"><Briefcase class="h-3.5 w-3.5" /> {{ v.company }}</span>
          <span v-if="v.branch" class="flex items-center gap-1"><MapPin class="h-3.5 w-3.5" /> {{ v.branch }}</span>
          <span v-if="v.employment_type">{{ v.employment_type }}</span>
        </div>
      </button>
    </div>
  </div>
</template>