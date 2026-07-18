<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue'
import { Plus, Pencil, Trash2, X } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface CompanyRow {
  id: number
  name: string
  code: string
  npwp: string | null
  address: string | null
  phone: string | null
  email: string | null
  is_active: boolean
}

const companies = ref<CompanyRow[]>([])
const loading = ref(true)
const errorMessage = ref('')

const showModal = ref(false)
const isEditing = ref(false)
const saving = ref(false)
const formError = ref('')

const form = reactive({
  id: 0,
  name: '',
  code: '',
  npwp: '',
  address: '',
  phone: '',
  email: '',
  is_active: true,
})

async function loadCompanies() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/companies')
    companies.value = response.data.data.data
  } catch {
    errorMessage.value = 'Gagal memuat daftar company.'
  } finally {
    loading.value = false
  }
}

function resetForm() {
  form.id = 0
  form.name = ''
  form.code = ''
  form.npwp = ''
  form.address = ''
  form.phone = ''
  form.email = ''
  form.is_active = true
}

function openCreateModal() {
  isEditing.value = false
  formError.value = ''
  resetForm()
  showModal.value = true
}

function openEditModal(company: CompanyRow) {
  isEditing.value = true
  formError.value = ''
  form.id = company.id
  form.name = company.name
  form.code = company.code
  form.npwp = company.npwp ?? ''
  form.address = company.address ?? ''
  form.phone = company.phone ?? ''
  form.email = company.email ?? ''
  form.is_active = company.is_active
  showModal.value = true
}

function closeModal() {
  showModal.value = false
}

async function handleSubmit() {
  saving.value = true
  formError.value = ''

  const payload = {
    name: form.name,
    code: form.code,
    npwp: form.npwp || null,
    address: form.address || null,
    phone: form.phone || null,
    email: form.email || null,
    is_active: form.is_active,
  }

  try {
    if (isEditing.value) {
      await apiClient.put(`/api/companies/${form.id}`, payload)
    } else {
      await apiClient.post('/api/companies', payload)
    }

    showModal.value = false
    await loadCompanies()
  } catch (err: any) {
    formError.value = err.response?.data?.message || 'Terjadi kesalahan, silakan coba lagi.'
  } finally {
    saving.value = false
  }
}

async function handleDelete(company: CompanyRow) {
  if (!confirm(`Hapus company "${company.name}"?`)) return

  try {
    await apiClient.delete(`/api/companies/${company.id}`)
    await loadCompanies()
  } catch {
    alert('Gagal menghapus company.')
  }
}

onMounted(loadCompanies)
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Company</h1>
        <p class="mt-1 text-sm text-slate-500">Kelola data perusahaan dalam sistem.</p>
      </div>
      <button
        @click="openCreateModal"
        class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-dark"
      >
        <Plus class="h-4 w-4" :stroke-width="2" />
        Tambah Company
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
            <th class="px-5 py-3 font-medium text-slate-500">Nama</th>
            <th class="px-5 py-3 font-medium text-slate-500">Kode</th>
            <th class="px-5 py-3 font-medium text-slate-500">Email</th>
            <th class="px-5 py-3 font-medium text-slate-500">Status</th>
            <th class="px-5 py-3 text-right font-medium text-slate-500">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="company in companies"
            :key="company.id"
            class="border-b border-slate-50 last:border-0 hover:bg-slate-50/50"
          >
            <td class="px-5 py-3.5 font-medium text-slate-800">{{ company.name }}</td>
            <td class="px-5 py-3.5 text-slate-500">{{ company.code }}</td>
            <td class="px-5 py-3.5 text-slate-500">{{ company.email || '-' }}</td>
            <td class="px-5 py-3.5">
              <span
                class="rounded-full px-2.5 py-1 text-xs font-medium"
                :class="company.is_active ? 'bg-primary-soft text-primary-dark' : 'bg-slate-100 text-slate-500'"
              >
                {{ company.is_active ? 'Aktif' : 'Nonaktif' }}
              </span>
            </td>
            <td class="px-5 py-3.5">
              <div class="flex items-center justify-end gap-1">
                <button
                  @click="openEditModal(company)"
                  class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600"
                >
                  <Pencil class="h-4 w-4" :stroke-width="1.75" />
                </button>
                <button
                  @click="handleDelete(company)"
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
              {{ isEditing ? 'Edit Company' : 'Tambah Company' }}
            </h2>
            <button @click="closeModal" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50">
              <X class="h-5 w-5" />
            </button>
          </div>

          <form @submit.prevent="handleSubmit" class="space-y-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Nama Perusahaan</label>
              <input
                v-model="form.name"
                type="text"
                required
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
              <label class="mb-1 block text-sm font-medium text-slate-700">NPWP</label>
              <input
                v-model="form.npwp"
                type="text"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
              />
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Alamat</label>
              <textarea
                v-model="form.address"
                rows="2"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
              ></textarea>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Telepon</label>
                <input
                  v-model="form.phone"
                  type="text"
                  class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
                />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Email</label>
                <input
                  v-model="form.email"
                  type="email"
                  class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
                />
              </div>
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