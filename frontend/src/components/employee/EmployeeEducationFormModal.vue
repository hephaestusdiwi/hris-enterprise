<script setup lang="ts">
import { ref } from 'vue'
import { X } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface EducationRecord {
  id: number
  education_level: string
  institution_name: string
  major: string | null
  start_date: string | null
  end_date: string | null
  graduation_status: string
  description: string | null
}

const props = defineProps<{
  employeeId: number
  employeeName: string
  editing?: EducationRecord | null
}>()
const emit = defineEmits<{ close: []; saved: [] }>()

const LEVELS = [
  { value: 'sd', label: 'SD' },
  { value: 'smp', label: 'SMP' },
  { value: 'sma_smk', label: 'SMA/SMK' },
  { value: 'd1', label: 'D1' },
  { value: 'd2', label: 'D2' },
  { value: 'd3', label: 'D3' },
  { value: 'd4', label: 'D4' },
  { value: 's1', label: 'S1' },
  { value: 's2', label: 'S2' },
  { value: 's3', label: 'S3' },
]
const GRADUATION_STATUSES = [
  { value: 'ongoing', label: 'Sedang Berjalan' },
  { value: 'graduated', label: 'Lulus' },
  { value: 'dropped_out', label: 'Tidak Selesai' },
]

const isEditing = !!props.editing
const form = ref({
  education_level: props.editing?.education_level ?? 's1',
  institution_name: props.editing?.institution_name ?? '',
  major: props.editing?.major ?? '',
  start_date: props.editing?.start_date ?? '',
  end_date: props.editing?.end_date ?? '',
  graduation_status: props.editing?.graduation_status ?? 'graduated',
  description: props.editing?.description ?? '',
})
const saving = ref(false)
const errorMessage = ref('')

async function submit() {
  saving.value = true
  errorMessage.value = ''
  try {
    if (isEditing && props.editing) {
      await apiClient.post(`/api/employees/${props.employeeId}/educations/${props.editing.id}`, form.value)
    } else {
      await apiClient.post(`/api/employees/${props.employeeId}/educations`, form.value)
    }
    emit('saved')
  } catch (err: unknown) {
    const data = (err as { response?: { data?: { message?: string } } })?.response?.data
    errorMessage.value = data?.message ?? 'Gagal menyimpan riwayat pendidikan.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
    <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-slate-800">{{ isEditing ? 'Edit' : 'Tambah' }} Riwayat Pendidikan — {{ employeeName }}</h2>
        <button type="button" @click="emit('close')"><X class="h-4 w-4 text-slate-400" /></button>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">Jenjang</label>
          <select v-model="form.education_level" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
            <option v-for="l in LEVELS" :key="l.value" :value="l.value">{{ l.label }}</option>
          </select>
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">Status</label>
          <select v-model="form.graduation_status" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
            <option v-for="s in GRADUATION_STATUSES" :key="s.value" :value="s.value">{{ s.label }}</option>
          </select>
        </div>
        <div class="col-span-2">
          <label class="mb-1 block text-xs font-medium text-slate-600">Institusi</label>
          <input v-model="form.institution_name" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </div>
        <div class="col-span-2">
          <label class="mb-1 block text-xs font-medium text-slate-600">Jurusan</label>
          <input v-model="form.major" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">Mulai</label>
          <input v-model="form.start_date" type="date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">Selesai</label>
          <input v-model="form.end_date" type="date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </div>
      </div>

      <p v-if="errorMessage" class="mt-3 text-xs text-red-600">{{ errorMessage }}</p>

      <div class="mt-4 flex justify-end gap-2">
        <button type="button" @click="emit('close')" class="rounded-xl px-4 py-2 text-sm text-slate-500 hover:bg-slate-50">Batal</button>
        <button
          type="button"
          :disabled="saving || !form.institution_name"
          @click="submit"
          class="rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50"
        >
          {{ saving ? 'Menyimpan...' : 'Simpan' }}
        </button>
      </div>
    </div>
  </div>
</template>