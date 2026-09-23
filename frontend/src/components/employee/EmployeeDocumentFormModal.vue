<script setup lang="ts">
import { ref } from 'vue'
import { X } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

const props = defineProps<{
  employeeId: number
  employeeName: string
}>()
const emit = defineEmits<{ close: []; created: [] }>()

const CATEGORIES = [
  { value: 'ktp', label: 'KTP' },
  { value: 'npwp', label: 'NPWP' },
  { value: 'kartu_keluarga', label: 'Kartu Keluarga' },
  { value: 'ijazah', label: 'Ijazah' },
  { value: 'kontrak_kerja', label: 'Kontrak Kerja' },
  { value: 'skck', label: 'SKCK' },
  { value: 'lainnya', label: 'Lainnya' },
]

const category = ref('ktp')
const notes = ref('')
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
    if (notes.value) payload.append('notes', notes.value)
    if (file.value) payload.append('document', file.value)

    await apiClient.post(`/api/employees/${props.employeeId}/documents`, payload, {
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
            <option v-for="c in CATEGORIES" :key="c.value" :value="c.value">{{ c.label }}</option>
          </select>
        </div>

        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">File (JPG/PNG/PDF, maks 5MB)</label>
          <input type="file" accept=".jpg,.jpeg,.png,.pdf" class="text-sm" @change="onFileSelected" />
        </div>

        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">Catatan (opsional)</label>
          <textarea v-model="notes" rows="2" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></textarea>
        </div>

        <p v-if="errorMessage" class="text-xs text-red-600">{{ errorMessage }}</p>

        <div class="flex justify-end gap-2 pt-2">
          <button type="button" @click="emit('close')" class="rounded-xl px-4 py-2 text-sm text-slate-500 hover:bg-slate-50">Batal</button>
          <button
            type="button"
            :disabled="saving || !file"
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