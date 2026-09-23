<script setup lang="ts">
import { ref } from 'vue'
import { X } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

const props = defineProps<{
  employeeId: number
  employeeName: string
}>()
const emit = defineEmits<{ close: []; created: [] }>()

const ASSET_TYPES = [
  { value: 'laptop', label: 'Laptop' },
  { value: 'mobile_phone', label: 'HP' },
  { value: 'id_card', label: 'Kartu ID' },
  { value: 'access_card', label: 'Kartu Akses' },
  { value: 'vehicle', label: 'Kendaraan' },
  { value: 'other', label: 'Lainnya' },
]
const CONDITIONS = [
  { value: 'new', label: 'Baru' },
  { value: 'good', label: 'Baik' },
  { value: 'fair', label: 'Cukup' },
  { value: 'damaged', label: 'Rusak' },
]

const assetType = ref('laptop')
const assetName = ref('')
const serialNumber = ref('')
const condition = ref('good')
const assignedDate = ref(new Date().toISOString().slice(0, 10))
const notes = ref('')
const saving = ref(false)
const errorMessage = ref('')

async function submit() {
  saving.value = true
  errorMessage.value = ''
  try {
    await apiClient.post(`/api/employees/${props.employeeId}/assets`, {
      asset_type: assetType.value,
      asset_name: assetName.value,
      serial_number: serialNumber.value || null,
      condition: condition.value,
      assigned_date: assignedDate.value,
      notes: notes.value || null,
    })
    emit('created')
  } catch (err: unknown) {
    const data = (err as { response?: { data?: { message?: string } } })?.response?.data
    errorMessage.value = data?.message ?? 'Gagal menyimpan asset.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
    <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-slate-800">Assign Asset — {{ employeeName }}</h2>
        <button type="button" @click="emit('close')"><X class="h-4 w-4 text-slate-400" /></button>
      </div>

      <div class="space-y-4">
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Tipe</label>
            <select v-model="assetType" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
              <option v-for="t in ASSET_TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Kondisi</label>
            <select v-model="condition" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
              <option v-for="c in CONDITIONS" :key="c.value" :value="c.value">{{ c.label }}</option>
            </select>
          </div>
        </div>

        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">Nama Asset</label>
          <input v-model="assetName" type="text" placeholder="mis. Dell Latitude 5420" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Serial Number (opsional)</label>
            <input v-model="serialNumber" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Tanggal Diterima</label>
            <input v-model="assignedDate" type="date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
          </div>
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
            :disabled="saving || !assetName"
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