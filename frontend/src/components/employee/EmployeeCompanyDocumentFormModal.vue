<script setup lang="ts">
import { ref, computed } from 'vue'
import { X } from 'lucide-vue-next'
import apiClient from '@/lib/axios'
import { useAuthStore } from '@/stores/auth'

const props = defineProps<{
  employeeId: number
  employeeName: string
}>()
const emit = defineEmits<{ close: []; created: [] }>()

const authStore = useAuthStore()

const ALL_CATEGORY_LABELS: Record<string, string> = {
  sop: 'SOP',
  handbook: 'Handbook',
  work_guide: 'Panduan Kerja',
  form_template: 'Template Formulir',
  ktp: 'KTP',
  bank_account: 'Rekening Bank',
  employment_contract: 'Kontrak Kerja',
  disciplinary: 'Dokumen Disiplin',
}
const PUBLIC_CATEGORIES = ['sop', 'handbook', 'work_guide', 'form_template']

// Cuma tampilkan kategori yang user ini beneran boleh upload -- kategori
// public butuh 'create company documents', kategori private butuh
// 'manage private documents' (fixed, sesuai CompanyDocument::managePermissionFor
// di backend). Filter di sini cuma buat UX; backend tetap jadi otoritas.
const availableCategories = computed(() => {
  const canPublic = authStore.permissions.includes('create company documents')
  const canPrivate = authStore.permissions.includes('manage private documents')
  return Object.entries(ALL_CATEGORY_LABELS).filter(([key]) =>
    PUBLIC_CATEGORIES.includes(key) ? canPublic : canPrivate,
  )
})

const category = ref(availableCategories.value[0]?.[0] ?? 'sop')
const title = ref('')
const description = ref('')
const moduleContext = ref('')
const file = ref<File | null>(null)
const saving = ref(false)
const errorMessage = ref('')

function onFileSelected(event: Event) {
  const target = event.target as HTMLInputElement
  file.value = target.files?.[0] ?? null
}

async function submit() {
  saving.value = true
  errorMessage.value = ''
  try {
    const payload = new FormData()
    payload.append('category', category.value)
    payload.append('title', title.value)
    if (description.value) payload.append('description', description.value)
    if (moduleContext.value) payload.append('module_context', moduleContext.value)
    if (file.value) payload.append('document', file.value)

    await apiClient.post(`/api/employees/${props.employeeId}/company-documents`, payload, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    emit('created')
  } catch (err: unknown) {
    const data = (err as { response?: { data?: { message?: string } } })?.response?.data
    errorMessage.value = data?.message ?? 'Gagal mengupload dokumen.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
    <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-slate-800">Upload Dokumen — {{ employeeName }}</h2>
        <button type="button" @click="emit('close')"><X class="h-4 w-4 text-slate-400" /></button>
      </div>

      <div class="space-y-4">
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">Kategori</label>
          <select v-model="category" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
            <option v-for="[value, label] in availableCategories" :key="value" :value="value">{{ label }}</option>
          </select>
          <p v-if="availableCategories.length === 0" class="mt-1 text-xs text-red-600">
            Anda tidak punya permission untuk upload dokumen kategori apa pun.
          </p>
        </div>

        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">Judul</label>
          <input v-model="title" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </div>

        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">Deskripsi (opsional)</label>
          <textarea v-model="description" rows="2" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></textarea>
        </div>

        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">File</label>
          <input type="file" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx" class="text-sm" @change="onFileSelected" />
        </div>

        <p v-if="errorMessage" class="text-xs text-red-600">{{ errorMessage }}</p>

        <div class="flex justify-end gap-2 pt-2">
          <button type="button" @click="emit('close')" class="rounded-xl px-4 py-2 text-sm text-slate-500 hover:bg-slate-50">Batal</button>
          <button
            type="button"
            :disabled="saving || !title || !file || availableCategories.length === 0"
            @click="submit"
            class="rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50"
          >
            {{ saving ? 'Mengupload...' : 'Upload' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>