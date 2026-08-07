<script setup lang="ts">
import { reactive, ref } from 'vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import type { BpjsProgram } from '@/types/bpjs'

const props = defineProps<{
  onCreate: (payload: Record<string, unknown>) => Promise<void>
}>()

const emit = defineEmits<{ close: [] }>()

const form = reactive({
  program: 'kesehatan' as BpjsProgram,
  effective_date: new Date().toISOString().slice(0, 10),
  employee_rate_percentage: null as number | null,
  employer_rate_percentage: null as number | null,
  wage_base_cap: null as number | null,
  notes: '',
})

const error = ref('')
const saving = ref(false)

async function submit() {
  saving.value = true
  error.value = ''
  try {
    await props.onCreate({ ...form })
    emit('close')
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Gagal menyimpan rate config.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <BaseModal title="Tambah Rate Config" @close="$emit('close')">
    <form class="space-y-4 px-6 py-5" @submit.prevent="submit">
      <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Program</label>
        <select
          v-model="form.program"
          class="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm text-slate-700 transition-colors focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary/30"
        >
          <option value="kesehatan">BPJS Kesehatan</option>
          <option value="jht">JHT</option>
          <option value="jkk">JKK</option>
          <option value="jkm">JKM</option>
        </select>
        <p v-if="form.program === 'jkk'" class="mt-1 text-xs text-slate-500">
          Rate JKK diisi lewat tarif kelas risiko — kolom rate company di bawah boleh dikosongkan.
        </p>
      </div>

      <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Berlaku Sejak</label>
        <input
          v-model="form.effective_date"
          type="date"
          required
          class="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm text-slate-700 transition-colors focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary/30"
        />
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="mb-1.5 block text-sm font-medium text-slate-700">Rate Karyawan %</label>
          <input
            v-model.number="form.employee_rate_percentage"
            type="number"
            step="0.01"
            min="0"
            max="100"
            class="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm text-slate-700 transition-colors focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary/30"
          />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-slate-700">Rate Company %</label>
          <input
            v-model.number="form.employer_rate_percentage"
            type="number"
            step="0.01"
            min="0"
            max="100"
            :disabled="form.program === 'jkk'"
            class="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm text-slate-700 transition-colors focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary/30 disabled:bg-slate-50 disabled:text-slate-400"
          />
        </div>
      </div>

      <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Wage Base Cap</label>
        <input
          v-model.number="form.wage_base_cap"
          type="number"
          min="0"
          class="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm text-slate-700 transition-colors focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary/30"
        />
        <p class="mt-1 text-xs text-slate-500">Kosongkan jika tidak ada batas upah untuk perhitungan.</p>
      </div>

      <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Catatan</label>
        <textarea
          v-model="form.notes"
          rows="2"
          class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 transition-colors focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary/30"
        ></textarea>
      </div>

      <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
    </form>

    <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-6 py-4">
      <button
        type="button"
        class="inline-flex h-9 items-center rounded-lg border border-slate-200 px-4 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50"
        @click="$emit('close')"
      >
        Batal
      </button>
      <button
        type="button"
        :disabled="saving"
        class="inline-flex h-9 items-center rounded-lg bg-primary px-4 text-sm font-medium text-white transition-colors hover:bg-primary-dark disabled:opacity-50"
        @click="submit"
      >
        {{ saving ? 'Menyimpan...' : 'Simpan' }}
      </button>
    </div>
  </BaseModal>
</template>