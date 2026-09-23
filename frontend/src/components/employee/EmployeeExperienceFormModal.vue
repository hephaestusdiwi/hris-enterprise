<script setup lang="ts">
import { ref } from 'vue'
import { X } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface ExperienceRecord {
  id: number
  company_name: string
  position_title: string
  employment_type: string | null
  start_date: string
  end_date: string | null
  description: string | null
  reason_for_leaving: string | null
}

const props = defineProps<{
  employeeId: number
  employeeName: string
  editing?: ExperienceRecord | null
}>()
const emit = defineEmits<{ close: []; saved: [] }>()

const EMPLOYMENT_TYPES = [
  { value: 'full_time', label: 'Full-time' },
  { value: 'part_time', label: 'Part-time' },
  { value: 'contract', label: 'Kontrak' },
  { value: 'internship', label: 'Magang' },
  { value: 'freelance', label: 'Freelance' },
]

const isEditing = !!props.editing
const form = ref({
  company_name: props.editing?.company_name ?? '',
  position_title: props.editing?.position_title ?? '',
  employment_type: props.editing?.employment_type ?? '',
  start_date: props.editing?.start_date ?? '',
  end_date: props.editing?.end_date ?? '',
  description: props.editing?.description ?? '',
  reason_for_leaving: props.editing?.reason_for_leaving ?? '',
})
const saving = ref(false)
const errorMessage = ref('')

async function submit() {
  saving.value = true
  errorMessage.value = ''
  try {
    if (isEditing && props.editing) {
      await apiClient.put(`/api/employees/${props.employeeId}/experiences/${props.editing.id}`, form.value)
    } else {
      await apiClient.post(`/api/employees/${props.employeeId}/experiences`, form.value)
    }
    emit('saved')
  } catch (err: unknown) {
    const data = (err as { response?: { data?: { message?: string } } })?.response?.data
    errorMessage.value = data?.message ?? 'Gagal menyimpan riwayat pengalaman kerja.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
    <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-slate-800">{{ isEditing ? 'Edit' : 'Tambah' }} Pengalaman Kerja — {{ employeeName }}</h2>
        <button type="button" @click="emit('close')"><X class="h-4 w-4 text-slate-400" /></button>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div class="col-span-2">
          <label class="mb-1 block text-xs font-medium text-slate-600">Perusahaan</label>
          <input v-model="form.company_name" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </div>
        <div class="col-span-2">
          <label class="mb-1 block text-xs font-medium text-slate-600">Posisi</label>
          <input v-model="form.position_title" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">Tipe</label>
          <select v-model="form.employment_type" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
            <option value="">-</option>
            <option v-for="t in EMPLOYMENT_TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
          </select>
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">Alasan Keluar</label>
          <input v-model="form.reason_for_leaving" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">Mulai</label>
          <input v-model="form.start_date" type="date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">Selesai</label>
          <input v-model="form.end_date" type="date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </div>
        <div class="col-span-2">
          <label class="mb-1 block text-xs font-medium text-slate-600">Deskripsi</label>
          <textarea v-model="form.description" rows="2" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></textarea>
        </div>
      </div>

      <p v-if="errorMessage" class="mt-3 text-xs text-red-600">{{ errorMessage }}</p>

      <div class="mt-4 flex justify-end gap-2">
        <button type="button" @click="emit('close')" class="rounded-xl px-4 py-2 text-sm text-slate-500 hover:bg-slate-50">Batal</button>
        <button
          type="button"
          :disabled="saving || !form.company_name || !form.position_title || !form.start_date"
          @click="submit"
          class="rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50"
        >
          {{ saving ? 'Menyimpan...' : 'Simpan' }}
        </button>
      </div>
    </div>
  </div>
</template>