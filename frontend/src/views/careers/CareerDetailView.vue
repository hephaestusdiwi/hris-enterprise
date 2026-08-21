<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import apiClient from '@/lib/axios'
import { ArrowLeft, MapPin, Briefcase, Calendar } from 'lucide-vue-next'

interface PublicVacancyDetail {
  title: string
  slug: string
  description: string
  requirements: string | null
  employment_type: string | null
  company: string | null
  branch: string | null
  department: string | null
  application_deadline: string | null
  application_method: string
  external_apply_url: string | null
}

const route = useRoute()
const router = useRouter()
const slug = computed(() => String(route.params.slug))

const vacancy = ref<PublicVacancyDetail | null>(null)
const loading = ref(true)
const errorMessage = ref('')

async function loadVacancy() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get(`/api/careers/vacancies/${slug.value}`)
    vacancy.value = response.data.data
  } catch (err: any) {
    errorMessage.value = 'Lowongan tidak ditemukan atau sudah tidak dibuka.'
  } finally {
    loading.value = false
  }
}

function applyNow() {
  if (!vacancy.value) return
  if (vacancy.value.application_method === 'external' && vacancy.value.external_apply_url) {
    window.open(vacancy.value.external_apply_url, '_blank')
    return
  }
  router.push({ name: 'careers.apply', params: { slug: slug.value } })
}

onMounted(loadVacancy)
</script>

<template>
  <div class="min-h-screen bg-slate-50">
    <div class="mx-auto max-w-3xl px-4 py-12">
      <button class="mb-6 flex items-center gap-1 text-sm text-slate-400 hover:text-slate-600" @click="router.push({ name: 'careers.index' })">
        <ArrowLeft class="h-4 w-4" /> Kembali ke daftar lowongan
      </button>

      <div v-if="loading" class="text-sm text-slate-400">Memuat...</div>
      <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>

      <div v-else-if="vacancy" class="rounded-2xl border border-slate-100 bg-white p-8 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <h1 class="text-xl font-semibold text-slate-800">{{ vacancy.title }}</h1>
        <div class="mt-3 flex flex-wrap gap-4 text-sm text-slate-400">
          <span v-if="vacancy.company" class="flex items-center gap-1"><Briefcase class="h-4 w-4" /> {{ vacancy.company }}</span>
          <span v-if="vacancy.branch" class="flex items-center gap-1"><MapPin class="h-4 w-4" /> {{ vacancy.branch }}</span>
          <span v-if="vacancy.application_deadline" class="flex items-center gap-1"><Calendar class="h-4 w-4" /> Deadline: {{ vacancy.application_deadline }}</span>
        </div>
        <span v-if="vacancy.employment_type" class="mt-3 inline-block rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">
          {{ vacancy.employment_type }}
        </span>

        <div class="mt-6">
          <h2 class="text-sm font-semibold text-slate-700">Deskripsi</h2>
          <p class="mt-1 whitespace-pre-line text-sm text-slate-600">{{ vacancy.description }}</p>
        </div>
        <div v-if="vacancy.requirements" class="mt-4">
          <h2 class="text-sm font-semibold text-slate-700">Requirements</h2>
          <p class="mt-1 whitespace-pre-line text-sm text-slate-600">{{ vacancy.requirements }}</p>
        </div>

        <button class="mt-8 w-full rounded-xl bg-primary px-4 py-3 text-sm font-medium text-white" @click="applyNow">
          {{ vacancy.application_method === 'external' ? 'Apply (buka di situs lain)' : 'Apply Now' }}
        </button>
      </div>
    </div>
  </div>
</template>