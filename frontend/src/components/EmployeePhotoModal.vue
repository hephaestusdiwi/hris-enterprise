<script setup lang="ts">
import { ref } from 'vue'
import { X, Upload, Trash2, UserRound } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

const props = defineProps<{
  employeeId: number
  employeeName: string
  currentPhotoUrl: string | null
}>()

const emit = defineEmits<{
  close: []
  updated: []
}>()

const selectedFile = ref<File | null>(null)
const previewUrl = ref<string | null>(null)
const uploading = ref(false)
const deleting = ref(false)
const errorMessage = ref('')

function handleFileChange(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return

  if (!['image/jpeg', 'image/jpg', 'image/png', 'image/webp'].includes(file.type)) {
    errorMessage.value = 'Format harus JPG, PNG, atau WebP.'
    return
  }
  if (file.size > 5 * 1024 * 1024) {
    errorMessage.value = 'Ukuran file maksimal 5MB.'
    return
  }

  errorMessage.value = ''
  selectedFile.value = file
  previewUrl.value = URL.createObjectURL(file)
}

async function handleUpload() {
  if (!selectedFile.value) return

  uploading.value = true
  errorMessage.value = ''

  const formData = new FormData()
  formData.append('photo', selectedFile.value)

  try {
    await apiClient.post(`/api/employees/${props.employeeId}/photo`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    emit('updated')
    emit('close')
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Gagal upload foto.'
  } finally {
    uploading.value = false
  }
}

async function handleDelete() {
  if (!confirm('Hapus foto profil ini?')) return

  deleting.value = true
  try {
    await apiClient.delete(`/api/employees/${props.employeeId}/photo`)
    emit('updated')
    emit('close')
  } catch {
    errorMessage.value = 'Gagal menghapus foto.'
  } finally {
    deleting.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8">
      <div class="w-full max-w-sm rounded-2xl bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
          <h2 class="text-lg font-semibold text-slate-900">Foto Profil</h2>
          <button @click="emit('close')" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50">
            <X class="h-5 w-5" />
          </button>
        </div>

        <div class="space-y-4 px-6 py-5">
          <p class="text-sm text-slate-500">{{ employeeName }}</p>

          <div class="flex justify-center">
            <div class="flex h-32 w-32 items-center justify-center overflow-hidden rounded-full bg-slate-100">
              <img v-if="previewUrl || currentPhotoUrl" :src="previewUrl || currentPhotoUrl!" alt="" class="h-full w-full object-cover" />
              <UserRound v-else class="h-12 w-12 text-slate-300" :stroke-width="1.5" />
            </div>
          </div>

          <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-slate-300 px-4 py-3 text-sm text-slate-500 transition-colors hover:border-primary hover:text-primary-dark">
            <Upload class="h-4 w-4" :stroke-width="1.75" />
            {{ selectedFile ? selectedFile.name : 'Pilih foto (JPG/PNG/WebP, maks 5MB)' }}
            <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="handleFileChange" />
          </label>

          <p v-if="errorMessage" class="text-sm text-red-600">{{ errorMessage }}</p>
        </div>

        <div class="flex items-center justify-between border-t border-slate-100 px-6 py-4">
          <button
            v-if="currentPhotoUrl"
            @click="handleDelete"
            :disabled="deleting"
            class="flex items-center gap-1.5 rounded-xl px-3 py-2 text-sm font-medium text-red-500 transition-colors hover:bg-red-50 disabled:opacity-50"
          >
            <Trash2 class="h-4 w-4" :stroke-width="1.75" />
            {{ deleting ? 'Menghapus...' : 'Hapus Foto' }}
          </button>
          <div v-else />

          <button
            @click="handleUpload"
            :disabled="!selectedFile || uploading"
            class="rounded-xl bg-primary px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-dark disabled:opacity-50"
          >
            {{ uploading ? 'Mengunggah...' : 'Simpan Foto' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
