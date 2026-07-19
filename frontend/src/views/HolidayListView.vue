<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue'
import { Plus, Pencil, Trash2, X } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface Company {
  id: number
  name: string
}

interface HolidayRow {
  id: number
  date: string
  name: string
  type: string
  is_active: boolean
  company: Company | null
}

const holidays = ref<HolidayRow[]>([])
const companies = ref<Company[]>([])
const loading = ref(true)
const errorMessage = ref('')

const showModal = ref(false)
const isEditing = ref(false)
const saving = ref(false)
const formError = ref('')

const form = reactive({
  id: 0,
  company_id: null as number | null,
  date: '',
  name: '',
  type: 'national',
  is_active: true,
})

function typeLabel(type: string) {
  return type === 'collective' ? 'Cuti Bersama' : 'Nasional'
}

async function loadHolidays() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/holidays')
    holidays.value = response.data.data.data
  } catch {
    errorMessage.value = 'Gagal memuat daftar holiday.'
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
  form.company_id = null
  form.date = ''
  form.name = ''
  form.type = 'national'
  form.is_active = true
}

function openCreateModal() {
  isEditing.value = false
  formError.value = ''
  resetForm()
  showModal.value = true
}

function openEditModal(holiday: HolidayRow) {
  isEditing.value = true
  formError.value = ''
  form.id = holiday.id
  form.company_id = holiday.company?.id ?? null
  form.date = holiday.date.slice(0, 10)
  form.name = holiday.name
  form.type = holiday.type
  form.is_active = holiday.is_active
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
    date: form.date,
    name: form.name,
    type: form.type,
    is_active: form.is_active,
  }

  try {
    if (isEditing.value) {
      await apiClient.put(`/api/holidays/${form.id}`, payload)
    } else {
      await apiClient.post('/api/holidays', payload)
    }

    showModal.value = false
    await loadHolidays()
  } catch (err: any) {
    formError.value = err.response?.data?.message || 'Terjadi kesalahan, silakan coba lagi.'
  } finally {
    saving.value = false
  }
}

async function handleDelete(holiday: HolidayRow) {
  if (!confirm(`Hapus holiday "${holiday.name}"?`)) return

  try {
    await apiClient.delete(`/api/holidays/${holiday.id}`)
    await loadHolidays()
  } catch {
    alert('Gagal menghapus holiday.')
  }
}

onMounted(() => {
  loadHolidays()
  loadCompanies()
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Holiday</h1>
        <p class="mt-1 text-sm text-slate-500">Kelola hari libur nasional dan cuti bersama.</p>
      </div>
      <button
        @click="openCreateModal"
        class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-dark"
      >
        <Plus class="h-4 w-4" :stroke-width="2" />
        Tambah Holiday
      </button>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">
      {{ errorMessage }}
    </div>

    <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <table class="w-full text-left text-sm">
        <thead>
          <tr class="border-b border-slate-100 bg-slate-50/60">
            <th class="px-5 py-3 font-medium text-slate-500">Tanggal</th>
            <th class="px-5 py-3 font-medium text-slate-500">Nama</th>
            <th class="px-5 py-3 font-medium text-slate-500">Tipe</th>
            <th class="px-5 py-3 font-medium text-slate-500">Company</th>
            <th class="px-5 py-3 font-medium text-slate-500">Status</th>
            <th class="px-5 py-3 text-right font-medium text-slate-500">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="holiday in holidays"
            :key="holiday.id"
            class="border-b border-slate-50 last:border-0 hover:bg-slate-50/50"
          >
            <td class="px-5 py-3.5 text-slate-500">{{ holiday.date.slice(0, 10) }}</td>
            <td class="px-5 py-3.5 font-medium text-slate-800">{{ holiday.name }}</td>
            <td class="px-5 py-3.5 text-slate-500">{{ typeLabel(holiday.type) }}</td>
            <td class="px-5 py-3.5 text-slate-500">{{ holiday.company?.name ?? 'Semua Company' }}</td>
            <td class="px-5 py-3.5">
              <span
                class="rounded-full px-2.5 py-1 text-xs font-medium"
                :class="holiday.is_active ? 'bg-primary-soft text-primary-dark' : 'bg-slate-100 text-slate-500'"
              >
                {{ holiday.is_active ? 'Aktif' : 'Nonaktif' }}
              </span>
            </td>
            <td class="px-5 py-3.5">
              <div class="flex items-center justify-end gap-1">
                <button
                  @click="openEditModal(holiday)"
                  class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600"
                >
                  <Pencil class="h-4 w-4" :stroke-width="1.75" />
                </button>
                <button
                  @click="handleDelete(holiday)"
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
              {{ isEditing ? 'Edit Holiday' : 'Tambah Holiday' }}
            </h2>
            <button @click="closeModal" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50">
              <X class="h-5 w-5" />
            </button>
          </div>

          <form @submit.prevent="handleSubmit" class="space-y-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Tanggal</label>
              <input
                v-model="form.date"
                type="date"
                required
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
              />
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Nama Hari Libur</label>
              <input
                v-model="form.name"
                type="text"
                required
                placeholder="mis. Hari Kemerdekaan"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
              />
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Tipe</label>
              <select
                v-model="form.type"
                required
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
              >
                <option value="national">Nasional</option>
                <option value="collective">Cuti Bersama</option>
              </select>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Company (opsional)</label>
              <select
                v-model="form.company_id"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
              >
                <option :value="null">Semua Company</option>
                <option v-for="company in companies" :key="company.id" :value="company.id">
                  {{ company.name }}
                </option>
              </select>
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