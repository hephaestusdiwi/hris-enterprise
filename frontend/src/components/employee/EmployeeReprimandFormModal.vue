<script setup lang="ts">
import { ref } from 'vue'
import { X } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

const props = defineProps<{
  employeeId: number
  employeeName: string
}>()
const emit = defineEmits<{ close: []; created: [] }>()

const REPRIMAND_TYPES = [
  { value: 'verbal_warning', label: 'Teguran Lisan' },
  { value: 'sp1', label: 'SP 1' },
  { value: 'sp2', label: 'SP 2' },
  { value: 'sp3', label: 'SP 3' },
  { value: 'termination_notice', label: 'Surat Pemutusan' },
]

const reprimandType = ref('verbal_warning')
const title = ref('')
const date = ref(new Date().toISOString().slice(0, 10))
const reason = ref('')
const attachment = ref<File | null>(null)
const saving = ref(false)
const errorMessage = ref('')

function onFileSelected(event: Event) {
  const target = event.target as HTMLInputElement
  attachment.value = target.files?.[0] ?? null
}

async function submit() {
  saving.value = true
  errorMessage.value = ''
  try {
    const payload = new FormData()
    payload.append('reprimand_type', reprimandType.value)
    payload.append('title', title.value)
    payload.append('date', date.value)
    payload.append('reason', reason.value)
    if (attachment.value) payload.append('attachment', attachment.value)

    await apiClient.post(`/api/employees/${props.employeeId}/reprimands`, payload, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    emit('created')
  } catch (err: unknown) {
    const data = (err as { response?: { data?: { message?: string } } })?.response?.data
    errorMessage.value = data?.message ?? 'Gagal membuat reprimand.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
    <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-slate-800">Berikan Reprimand — {{ employeeName }}</h2>
        <button type="button" @click="emit('close')"><X class="h-4 w-4 text-slate-400" /></button>
      </div>

      <div class="space-y-4">
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">Tipe</label>
          <select v-model="reprimandType" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
            <option v-for="t in REPRIMAND_TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
          </select>
        </div>

        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">Judul / No. Referensi</label>
          <input v-model="title" type="text" placeholder="mis. SP-2026-001" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </div>

        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">Tanggal</label>
          <input v-model="date" type="date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </div>

        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">Alasan / Kronologi</label>
          <textarea v-model="reason" rows="3" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></textarea>
        </div>

        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">Lampiran (opsional, JPG/PNG/PDF maks 5MB)</label>
          <input type="file" accept=".jpg,.jpeg,.png,.pdf" class="text-sm" @change="onFileSelected" />
        </div>

        <p class="text-xs text-slate-400">
          Setelah dibuat, reprimand tidak bisa dihapus permanen — hanya bisa di-void dengan alasan bila ada kekeliruan.
        </p>
        <p v-if="errorMessage" class="text-xs text-red-600">{{ errorMessage }}</p>

        <div class="flex justify-end gap-2 pt-2">
          <button type="button" @click="emit('close')" class="rounded-xl px-4 py-2 text-sm text-slate-500 hover:bg-slate-50">Batal</button>
          <button
            type="button"
            :disabled="saving || !title || !reason"
            @click="submit"
            class="rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50"
          >
            {{ saving ? 'Menyimpan...' : 'Simpan' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>