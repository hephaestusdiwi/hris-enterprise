<script setup lang="ts">
import { reactive, ref } from 'vue'
import BaseModal from '@/components/ui/BaseModal.vue'

const props = defineProps<{
  onCreate: (payload: Record<string, unknown>) => Promise<void>
}>()

const emit = defineEmits<{ close: [] }>()

const form = reactive({
  risk_class: 1,
  effective_date: new Date().toISOString().slice(0, 10),
  employer_rate_percentage: null as number | null,
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
    error.value = err.response?.data?.message || 'Gagal menyimpan tarif kelas risiko.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <BaseModal title="Tambah Tarif Kelas Risiko" max-width="max-w-sm" @close="$emit('close')">
    <form class="space-y-4 px-6 py-5" @submit.prevent="submit">
      <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Kelas Risiko</label>
        <select
          v-model.number="form.risk_class"
          class="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm text-slate-700 transition-colors focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary/30"
        >
          <option v-for="n in [1, 2, 3, 4, 5]" :key="n" :value="n">Kelas {{ n }}</option>
        </select>
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
      <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Tarif Employer %</label>
        <input
          v-model.number="form.employer_rate_percentage"
          type="number"
          step="0.01"
          min="0"
          max="100"
          required
          class="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm text-slate-700 transition-colors focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary/30"
        />
        <p class="mt-1 text-xs text-slate-500">Berlaku sama untuk semua company (ketentuan pemerintah).</p>
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