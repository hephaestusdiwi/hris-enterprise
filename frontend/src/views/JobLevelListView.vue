<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue'
import { Plus, Pencil, Trash2, X } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface Company {
  id: number
  name: string
}

interface JobLevelRow {
  id: number
  name: string
  code: string
  level_order: number
  is_active: boolean
  company: Company
}

const jobLevels = ref<JobLevelRow[]>([])
const companies = ref<Company[]>([])
const loading = ref(true)
const errorMessage = ref('')

const showModal = ref(false)
const isEditing = ref(false)
const saving = ref(false)
const formError = ref('')

const form = reactive({
  id: 0,
  company_id: 0,
  name: '',
  code: '',
  level_order: 1,
  is_active: true,
})

async function loadJobLevels() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/job-levels')
    jobLevels.value = response.data.data.data
  } catch {
    errorMessage.value = 'Gagal memuat daftar job level.'
  } finally {
    loading.value = false
  }
}

async function loadCompanies() {
  try {
    const response = await apiClient.get('/api/companies')
    companies.value = response.data.data.data
  } catch {
    companies.value = []
  }
}

function resetForm() {
  form.id = 0
  form.company_id = companies.value[0]?.id ?? 0
  form.name = ''
  form.code = ''
  form.level_order = 1
  form.is_active = true
}

function openCreateModal() {
  isEditing.value = false
  formError.value = ''
  resetForm()
  showModal.value = true
}

function openEditModal(jobLevel: JobLevelRow) {
  isEditing.value = true
  formError.value = ''
  form.id = jobLevel.id
  form.company_id = jobLevel.company.id
  form.name = jobLevel.name
  form.code = jobLevel.code
  form.level_order = jobLevel.level_order
  form.is_active = jobLevel.is_active
  showModal.value = true
}

function closeModal() {
  showModal.value = false
}

async function handleSubmit() {
  saving.value = true
  formError.value = ''

  const payload = {
    company_id: form.company_id,
    name: form.name,
    code: form.code,
    level_order: form.level_order,
    is_active: form.is_active,
  }

  try {
    if (isEditing.value) {
      await apiClient.put(`/api/job-levels/${form.id}`, payload)
    } else {
      await apiClient.post('/api/job-levels', payload)
    }

    showModal.value = false
    await loadJobLevels()
  } catch (err: any) {
    formError.value = err.response?.data?.message || 'Terjadi kesalahan, silakan coba lagi.'
  } finally {
    saving.value = false
  }
}

async function handleDelete(jobLevel: JobLevelRow) {
  if (!confirm(`Hapus job level "${jobLevel.name}"?`)) return

  try {
    await apiClient.delete(`/api/job-levels/${jobLevel.id}`)
    await loadJobLevels()
  } catch {
    alert('Gagal menghapus job level.')
  }
}

onMounted(() => {
  loadJobLevels()
  loadCompanies()
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Job Level</h1>
        <p class="mt-1 text-sm text-slate-500">
          Kelola tingkat senioritas/grade, terpisah dari Position. Dipakai untuk aturan insentif dan approval berbasis level.
        </p>
      </div>
      <button
        @click="openCreateModal"
        :disabled="companies.length === 0"
        class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-dark disabled:opacity-50"
      >
        <Plus class="h-4 w-4" :stroke-width="2" />
        Tambah Job Level
      </button>
    </div>

    <p v-if="companies.length === 0 && !loading" class="rounded-xl bg-amber-50 p-4 text-sm text-amber-700">
      Belum ada company. Tambahkan company terlebih dahulu sebelum membuat job level.
    </p>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">
      {{ errorMessage }}
    </div>

    <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <table class="w-full text-left text-sm">
        <thead>
          <tr class="border-b border-slate-100 bg-slate-50/60">
            <th class="px-5 py-3 font-medium text-slate-500">Urutan</th>
            <th class="px-5 py-3 font-medium text-slate-500">Nama</th>
            <th class="px-5 py-3 font-medium text-slate-500">Kode</th>
            <th class="px-5 py-3 font-medium text-slate-500">Company</th>
            <th class="px-5 py-3 font-medium text-slate-500">Status</th>
            <th class="px-5 py-3 text-right font-medium text-slate-500">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="jobLevel in jobLevels"
            :key="jobLevel.id"
            class="border-b border-slate-50 last:border-0 hover:bg-slate-50/50"
          >
            <td class="px-5 py-3.5 text-slate-500">
              <span class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600">
                {{ jobLevel.level_order }}
              </span>
            </td>
            <td class="px-5 py-3.5 font-medium text-slate-800">{{ jobLevel.name }}</td>
            <td class="px-5 py-3.5 text-slate-500">{{ jobLevel.code }}</td>
            <td class="px-5 py-3.5 text-slate-500">{{ jobLevel.company.name }}</td>
            <td class="px-5 py-3.5">
              <span
                class="rounded-full px-2.5 py-1 text-xs font-medium"
                :class="jobLevel.is_active ? 'bg-primary-soft text-primary-dark' : 'bg-slate-100 text-slate-500'"
              >
                {{ jobLevel.is_active ? 'Aktif' : 'Nonaktif' }}
              </span>
            </td>
            <td class="px-5 py-3.5">
              <div class="flex items-center justify-end gap-1">
                <button
                  @click="openEditModal(jobLevel)"
                  class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600"
                >
                  <Pencil class="h-4 w-4" :stroke-width="1.75" />
                </button>
                <button
                  @click="handleDelete(jobLevel)"
                  class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-red-50 hover:text-red-500"
                >
                  <Trash2 class="h-4 w-4" :stroke-width="1.75" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <Teleport to="body">
      <div
        v-if="showModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4"
      >
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
          <div class="mb-5 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-slate-900">
              {{ isEditing ? 'Edit Job Level' : 'Tambah Job Level' }}
            </h2>
            <button @click="closeModal" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50">
              <X class="h-5 w-5" />
            </button>
          </div>

          <form @submit.prevent="handleSubmit" class="space-y-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Company</label>
              <select
                v-model.number="form.company_id"
                required
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
              >
                <option v-for="company in companies" :key="company.id" :value="company.id">
                  {{ company.name }}
                </option>
              </select>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Nama Level</label>
              <input
                v-model="form.name"
                type="text"
                required
                placeholder="mis. Senior"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
              />
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Kode</label>
              <input
                v-model="form.code"
                type="text"
                required
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
              />
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Urutan Level</label>
              <input
                v-model.number="form.level_order"
                type="number"
                min="1"
                required
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
              />
              <p class="mt-1 text-xs text-slate-400">Angka lebih besar = lebih senior. Dipakai untuk aturan approval berjenjang.</p>
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-700">
              <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-primary focus:ring-primary" />
              Aktif
            </label>

            <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>

            <button
              type="submit"
              :disabled="saving"
              class="w-full rounded-xl bg-primary py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-dark disabled:opacity-50"
            >
              {{ saving ? 'Menyimpan...' : 'Simpan' }}
            </button>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>