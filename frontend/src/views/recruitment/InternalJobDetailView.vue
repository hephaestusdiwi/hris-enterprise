<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import apiClient from '@/lib/axios'
import BaseModal from '@/components/ui/BaseModal.vue'
import { ArrowLeft } from 'lucide-vue-next'

interface InternalVacancyDetail {
  title: string
  slug: string
  description: string
  requirements: string | null
  employment_type: string | null
  company: string | null
  branch: string | null
  department: string | null
  application_deadline: string | null
}

const route = useRoute()
const router = useRouter()
const slug = computed(() => String(route.params.slug))

const vacancy = ref<InternalVacancyDetail | null>(null)
const loading = ref(true)
const errorMessage = ref('')

const showApplyModal = ref(false)
const cvFile = ref<File | null>(null)
const applying = ref(false)
const applyError = ref('')
const applySuccess = ref(false)

async function loadVacancy() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get(`/api/job-vacancies/self-service/${slug.value}`)
    vacancy.value = response.data.data
  } catch {
    errorMessage.value = 'Lowongan tidak ditemukan atau tidak terbuka untuk internal.'
  } finally {
    loading.value = false
  }
}

function onFileChange(e: Event) {
  cvFile.value = (e.target as HTMLInputElement).files?.[0] || null
}

async function submitApply() {
  if (!cvFile.value) {
    applyError.value = 'CV wajib diupload.'
    return
  }
  applying.value = true
  applyError.value = ''
  try {
    const payload = new FormData()
    payload.append('cv', cvFile.value)
    await apiClient.post(`/api/job-vacancies/self-service/${slug.value}/apply`, payload)
    applySuccess.value = true
  } catch (err: any) {
    applyError.value = err.response?.data?.message || 'Gagal mengirim lamaran.'
  } finally {
    applying.value = false
  }
}

onMounted(loadVacancy)
</script>

<template>
  <div class="space-y-4">
    <button class="flex items-center gap-1 text-sm text-slate-400 hover:text-slate-600" @click="router.push({ name: 'internal-job-vacancies.index' })">
      <ArrowLeft class="h-4 w-4" /> Kembali
    </button>

    <div v-if="loading" class="text-sm text-slate-400">Memuat...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>

    <div v-else-if="vacancy" class="rounded-2xl border border-slate-100 bg-white p-6 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <h1 class="text-lg font-semibold text-slate-800">{{ vacancy.title }}</h1>
      <p class="mt-1 text-sm text-slate-400">
        {{ vacancy.company }} · {{ vacancy.department }} · {{ vacancy.employment_type }}
      </p>

      <div class="mt-4">
        <h2 class="text-sm font-semibold text-slate-700">Deskripsi</h2>
        <p class="mt-1 whitespace-pre-line text-sm text-slate-600">{{ vacancy.description }}</p>
      </div>
      <div v-if="vacancy.requirements" class="mt-4">
        <h2 class="text-sm font-semibold text-slate-700">Requirements</h2>
        <p class="mt-1 whitespace-pre-line text-sm text-slate-600">{{ vacancy.requirements }}</p>
      </div>

      <button class="mt-6 rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white" @click="showApplyModal = true">
        Apply Lowongan Ini
      </button>
    </div>

    <Teleport to="body">
      <BaseModal v-if="showApplyModal" title="Apply Lowongan Internal" @close="showApplyModal = false">
        <div v-if="applySuccess" class="rounded-xl bg-emerald-50 p-4 text-center text-sm text-emerald-600">
          Lamaran berhasil dikirim.
        </div>
        <form v-else class="space-y-3" @submit.prevent="submitApply">
          <div v-if="applyError" class="rounded-xl bg-red-50 p-3 text-sm text-red-600">{{ applyError }}</div>
          <p class="text-xs text-slate-400">Nama, email, dan nomor telepon otomatis diambil dari profil Employee Anda.</p>
          <div>
            <label class="text-xs font-medium text-slate-500">CV (PDF/DOC/DOCX, maks 5MB)</label>
            <input required type="file" accept=".pdf,.doc,.docx" class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm" @change="onFileChange" />
          </div>
          <button type="submit" :disabled="applying" class="w-full rounded-xl bg-primary px-4 py-3 text-sm font-medium text-white disabled:opacity-50">
            {{ applying ? 'Mengirim...' : 'Kirim Lamaran' }}
          </button>
        </form>
      </BaseModal>
    </Teleport>
  </div>
</template>