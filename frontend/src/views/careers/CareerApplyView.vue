<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import apiClient from '@/lib/axios'
import { ArrowLeft } from 'lucide-vue-next'

const route = useRoute()
const router = useRouter()
const slug = computed(() => String(route.params.slug))

const vacancyTitle = ref('')
const loadingVacancy = ref(true)
const loadError = ref('')

const form = ref({ full_name: '', email: '', phone: '' })
const cvFile = ref<File | null>(null)
const submitting = ref(false)
const submitError = ref('')
const submitted = ref(false)

async function loadVacancy() {
  try {
    const response = await apiClient.get(`/api/careers/vacancies/${slug.value}`)
    vacancyTitle.value = response.data.data.title
  } catch {
    loadError.value = 'Lowongan tidak ditemukan atau sudah tidak dibuka.'
  } finally {
    loadingVacancy.value = false
  }
}

function onFileChange(e: Event) {
  const target = e.target as HTMLInputElement
  cvFile.value = target.files?.[0] || null
}

async function submitApplication() {
  if (!cvFile.value) {
    submitError.value = 'CV wajib diupload.'
    return
  }
  submitting.value = true
  submitError.value = ''
  try {
    const payload = new FormData()
    payload.append('full_name', form.value.full_name)
    payload.append('email', form.value.email)
    payload.append('phone', form.value.phone)
    payload.append('source', 'career_site') // otomatis — kandidat memang apply lewat career site ini
    payload.append('cv', cvFile.value)

    await apiClient.post(`/api/careers/vacancies/${slug.value}/apply`, payload)
    submitted.value = true
  } catch (err: any) {
    submitError.value = err.response?.data?.message || 'Gagal mengirim lamaran. Coba lagi.'
  } finally {
    submitting.value = false
  }
}

onMounted(loadVacancy)
</script>

<template>
  <div class="min-h-screen bg-slate-50">
    <div class="mx-auto max-w-xl px-4 py-12">
      <button class="mb-6 flex items-center gap-1 text-sm text-slate-400 hover:text-slate-600" @click="router.push({ name: 'careers.show', params: { slug } })">
        <ArrowLeft class="h-4 w-4" /> Kembali
      </button>

      <div v-if="loadingVacancy" class="text-sm text-slate-400">Memuat...</div>
      <div v-else-if="loadError" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ loadError }}</div>

      <div v-else-if="submitted" class="rounded-2xl border border-emerald-100 bg-emerald-50 p-8 text-center">
        <h1 class="text-lg font-semibold text-emerald-700">Lamaran Terkirim</h1>
        <p class="mt-2 text-sm text-emerald-600">Terima kasih sudah melamar untuk posisi {{ vacancyTitle }}. Tim kami akan menghubungi Anda jika profil Anda sesuai.</p>
        <button class="mt-6 rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white" @click="router.push({ name: 'careers.index' })">
          Lihat Lowongan Lain
        </button>
      </div>

      <div v-else class="rounded-2xl border border-slate-100 bg-white p-8 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <h1 class="text-lg font-semibold text-slate-800">Lamar: {{ vacancyTitle }}</h1>
        <form class="mt-6 space-y-4" @submit.prevent="submitApplication">
          <div v-if="submitError" class="rounded-xl bg-red-50 p-3 text-sm text-red-600">{{ submitError }}</div>
          <div>
            <label class="text-xs font-medium text-slate-500">Nama Lengkap</label>
            <input v-model="form.full_name" required type="text" class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm" />
          </div>
          <div>
            <label class="text-xs font-medium text-slate-500">Email</label>
            <input v-model="form.email" required type="email" class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm" />
          </div>
          <div>
            <label class="text-xs font-medium text-slate-500">No. Telepon</label>
            <input v-model="form.phone" required type="tel" class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm" />
          </div>
          <div>
            <label class="text-xs font-medium text-slate-500">CV (PDF/DOC/DOCX, maks 5MB)</label>
            <input required type="file" accept=".pdf,.doc,.docx" class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm" @change="onFileChange" />
          </div>
          <button type="submit" :disabled="submitting" class="w-full rounded-xl bg-primary px-4 py-3 text-sm font-medium text-white disabled:opacity-50">
            {{ submitting ? 'Mengirim...' : 'Kirim Lamaran' }}
          </button>
        </form>
      </div>
    </div>
  </div>
</template>