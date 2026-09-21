<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Plus, X } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface CompanyDocumentRow {
  id: number
  category: string
  title: string
  description: string | null
  module_context: string | null
  file_name: string
  url: string
  created_at: string
}

const categoryLabels: Record<string, string> = {
  sop: 'SOP',
  handbook: 'Handbook',
  work_guide: 'Panduan Kerja',
  form_template: 'Template Formulir',
}

const documents = ref<CompanyDocumentRow[]>([])
const loading = ref(true)
const errorMessage = ref('')
const filterCategory = ref('')

const showModal = ref(false)
const isEditing = ref(false)
const editingId = ref<number | null>(null)
const saving = ref(false)
const formError = ref('')

const form = ref({
  category: 'sop',
  title: '',
  description: '',
  module_context: '',
})
const file = ref<File | null>(null)

async function loadDocuments() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/company-documents', {
      params: filterCategory.value ? { category: filterCategory.value } : {},
    })
    documents.value = response.data.data
  } catch {
    errorMessage.value = 'Gagal memuat Company Document Library. Pastikan Anda punya akses ke modul ini.'
  } finally {
    loading.value = false
  }
}

function openCreate() {
  isEditing.value = false
  editingId.value = null
  form.value = { category: 'sop', title: '', description: '', module_context: '' }
  file.value = null
  formError.value = ''
  showModal.value = true
}

function openEdit(doc: CompanyDocumentRow) {
  isEditing.value = true
  editingId.value = doc.id
  form.value = {
    category: doc.category,
    title: doc.title,
    description: doc.description ?? '',
    module_context: doc.module_context ?? '',
  }
  file.value = null
  formError.value = ''
  showModal.value = true
}

function onFileSelected(event: Event) {
  const target = event.target as HTMLInputElement
  file.value = target.files?.[0] ?? null
}

async function submit() {
  saving.value = true
  formError.value = ''
  try {
    const payload = new FormData()
    if (!isEditing.value) payload.append('category', form.value.category)
    payload.append('title', form.value.title)
    if (form.value.description) payload.append('description', form.value.description)
    if (form.value.module_context) payload.append('module_context', form.value.module_context)
    if (file.value) payload.append('document', file.value)

    if (isEditing.value && editingId.value) {
      await apiClient.post(`/api/company-documents/${editingId.value}`, payload, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
    } else {
      await apiClient.post('/api/company-documents', payload, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
    }
    showModal.value = false
    loadDocuments()
  } catch (err: unknown) {
    const data = (err as { response?: { data?: { message?: string } } })?.response?.data
    formError.value = data?.message ?? 'Gagal menyimpan dokumen.'
  } finally {
    saving.value = false
  }
}

async function removeDocument(doc: CompanyDocumentRow) {
  try {
    await apiClient.delete(`/api/company-documents/${doc.id}`)
    documents.value = documents.value.filter((d) => d.id !== doc.id)
  } catch {
    errorMessage.value = 'Gagal menghapus dokumen.'
  }
}

onMounted(loadDocuments)
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Company Document Library</h1>
        <p class="mt-1 text-sm text-slate-500">SOP, handbook, panduan kerja, dan template formulir — company-wide.</p>
      </div>
      <button
        type="button"
        class="flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-dark"
        @click="openCreate"
      >
        <Plus class="h-4 w-4" :stroke-width="2" />
        Tambah Dokumen
      </button>
    </div>

    <div class="flex items-center gap-3">
      <select v-model="filterCategory" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" @change="loadDocuments">
        <option value="">Semua Kategori</option>
        <option v-for="(label, value) in categoryLabels" :key="value" :value="value">{{ label }}</option>
      </select>
    </div>

    <p v-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</p>

    <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <div v-if="loading" class="text-sm text-slate-400">Memuat...</div>
      <div v-else-if="documents.length === 0" class="rounded-xl bg-slate-50 p-6 text-center text-sm text-slate-400">
        Belum ada dokumen di kategori ini.
      </div>
      <div v-else class="divide-y divide-slate-100">
        <div v-for="doc in documents" :key="doc.id" class="flex items-center justify-between py-3">
          <div>
            <p class="text-sm font-medium text-slate-900">{{ doc.title }}</p>
            <p class="text-xs text-slate-500">
              {{ categoryLabels[doc.category] ?? doc.category }}
              <span v-if="doc.module_context">&middot; {{ doc.module_context }}</span>
              <span v-if="doc.description">&middot; {{ doc.description }}</span>
            </p>
          </div>
          <div class="flex items-center gap-3">
            <a :href="doc.url" target="_blank" rel="noopener" class="text-sm font-medium text-primary hover:underline">Lihat</a>
            <button type="button" class="text-sm font-medium text-slate-500 hover:underline" @click="openEdit(doc)">Edit</button>
            <button type="button" class="text-sm font-medium text-red-600 hover:underline" @click="removeDocument(doc)">Hapus</button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
      <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-slate-800">{{ isEditing ? 'Edit Dokumen' : 'Tambah Dokumen' }}</h2>
          <button type="button" @click="showModal = false"><X class="h-4 w-4 text-slate-400" /></button>
        </div>

        <div class="space-y-4">
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Kategori</label>
            <select
              v-model="form.category"
              :disabled="isEditing"
              class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm disabled:bg-slate-50 disabled:text-slate-400"
            >
              <option v-for="(label, value) in categoryLabels" :key="value" :value="value">{{ label }}</option>
            </select>
            <p v-if="isEditing" class="mt-1 text-xs text-slate-400">Kategori tidak bisa diubah setelah dibuat. Hapus &amp; buat baru bila salah pilih.</p>
          </div>

          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Judul</label>
            <input v-model="form.title" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
          </div>

          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Konteks Modul (opsional)</label>
            <input v-model="form.module_context" type="text" placeholder="mis. attendance, payroll" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
          </div>

          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Deskripsi</label>
            <textarea v-model="form.description" rows="2" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></textarea>
          </div>

          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">
              File {{ isEditing ? '(opsional, kosongkan bila tidak ganti file)' : '' }}
            </label>
            <input type="file" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx" class="text-sm" @change="onFileSelected" />
          </div>

          <p v-if="formError" class="text-xs text-red-600">{{ formError }}</p>

          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="showModal = false" class="rounded-xl px-4 py-2 text-sm text-slate-500 hover:bg-slate-50">Batal</button>
            <button
              type="button"
              :disabled="saving || !form.title || (!isEditing && !file)"
              @click="submit"
              class="rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50"
            >
              {{ saving ? 'Menyimpan...' : 'Simpan' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>