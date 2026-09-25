<script setup lang="ts">
import { ref, onMounted, reactive, computed } from 'vue'
import { Plus, Pencil, Trash2, X } from 'lucide-vue-next'
import apiClient from '@/lib/axios'
import { useAuthStore } from '@/stores/auth'

interface Company {
  id: number
  name: string
}

interface CategoryRow {
  id: number
  name: string
  code: string
  description: string | null
  is_active: boolean
  company: Company
}

const authStore = useAuthStore()
const canCreate = computed(() => authStore.permissions.includes('create training categories'))
const canEdit = computed(() => authStore.permissions.includes('edit training categories'))
const canDelete = computed(() => authStore.permissions.includes('delete training categories'))

const categories = ref<CategoryRow[]>([])
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
  description: '',
  is_active: true,
})

async function loadCategories() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/training-categories')
    categories.value = response.data.data.data
  } catch {
    errorMessage.value = 'Gagal memuat daftar Training Category.'
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
  form.description = ''
  form.is_active = true
}

function openCreateModal() {
  isEditing.value = false
  formError.value = ''
  resetForm()
  showModal.value = true
}

function openEditModal(category: CategoryRow) {
  isEditing.value = true
  formError.value = ''
  form.id = category.id
  form.company_id = category.company.id
  form.name = category.name
  form.code = category.code
  form.description = category.description ?? ''
  form.is_active = category.is_active
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
    description: form.description || null,
    is_active: form.is_active,
  }

  try {
    if (isEditing.value) {
      await apiClient.put(`/api/training-categories/${form.id}`, payload)
    } else {
      await apiClient.post('/api/training-categories', payload)
    }

    showModal.value = false
    await loadCategories()
  } catch (err: any) {
    formError.value = err.response?.data?.message || 'Terjadi kesalahan, silakan coba lagi.'
  } finally {
    saving.value = false
  }
}

async function handleDelete(category: CategoryRow) {
  if (!confirm(`Hapus Training Category "${category.name}"?`)) return

  try {
    await apiClient.delete(`/api/training-categories/${category.id}`)
    await loadCategories()
  } catch (err: any) {
    alert(err.response?.data?.message || 'Gagal menghapus Training Category.')
  }
}

onMounted(() => {
  loadCategories()
  loadCompanies()
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Training Category</h1>
        <p class="mt-1 text-sm text-slate-500">Kategori pelatihan per company, dipakai saat membuat Training Program.</p>
      </div>
      <button
        v-if="canCreate"
        @click="openCreateModal"
        :disabled="companies.length === 0"
        class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-dark disabled:opacity-50"
      >
        <Plus class="h-4 w-4" :stroke-width="2" />
        Tambah Kategori
      </button>
    </div>

    <p v-if="companies.length === 0 && !loading" class="rounded-xl bg-amber-50 p-4 text-sm text-amber-700">
      Belum ada company. Tambahkan company terlebih dahulu sebelum membuat Training Category.
    </p>

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
            <th class="px-5 py-3 font-medium text-slate-500">Company</th>
            <th class="px-5 py-3 font-medium text-slate-500">Status</th>
            <th class="px-5 py-3 text-right font-medium text-slate-500">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="categories.length === 0"><td colspan="5" class="px-5 py-6 text-center text-sm text-slate-400">Tidak ada data.</td></tr>
          <tr
            v-for="category in categories"
            :key="category.id"
            class="border-b border-slate-50 last:border-0 hover:bg-slate-50/50"
          >
            <td class="px-5 py-3.5 font-medium text-slate-800">{{ category.name }}</td>
            <td class="px-5 py-3.5 text-slate-500">{{ category.code }}</td>
            <td class="px-5 py-3.5 text-slate-500">{{ category.company.name }}</td>
            <td class="px-5 py-3.5">
              <span
                class="rounded-full px-2.5 py-1 text-xs font-medium"
                :class="category.is_active ? 'bg-primary-soft text-primary-dark' : 'bg-slate-100 text-slate-500'"
              >
                {{ category.is_active ? 'Aktif' : 'Nonaktif' }}
              </span>
            </td>
            <td class="px-5 py-3.5">
              <div class="flex items-center justify-end gap-1">
                <button
                  v-if="canEdit"
                  @click="openEditModal(category)"
                  class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600"
                >
                  <Pencil class="h-4 w-4" :stroke-width="1.75" />
                </button>
                <button
                  v-if="canDelete"
                  @click="handleDelete(category)"
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
              {{ isEditing ? 'Edit Kategori' : 'Tambah Kategori' }}
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
              <label class="mb-1 block text-sm font-medium text-slate-700">Nama Kategori</label>
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
              <label class="mb-1 block text-sm font-medium text-slate-700">Deskripsi</label>
              <textarea
                v-model="form.description"
                rows="2"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
              ></textarea>
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