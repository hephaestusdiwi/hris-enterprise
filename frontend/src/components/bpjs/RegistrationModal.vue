<script setup lang="ts">
import { reactive, ref } from 'vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import type { Branch } from '@/types/bpjs'

const props = defineProps<{
  branches: Branch[]
  onCreate: (payload: Record<string, unknown>) => Promise<void>
}>()

const emit = defineEmits<{ close: [] }>()

const form = reactive({
  branch_id: null as number | null,
  npp_number: '',
  label: '',
  risk_class: 1,
  effective_date: new Date().toISOString().slice(0, 10),
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
    error.value = err.response?.data?.message || 'Gagal menyimpan registrasi NPP.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <BaseModal title="Tambah Registrasi NPP" @close="$emit('close')">
    <form class="space-y-4 px-6 py-5" @submit.prevent="submit">
      <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Office Location</label>
        <select
          v-model="form.branch_id"
          class="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm text-slate-700 transition-colors focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary/30"
        >
          <option :value="null">Company-wide (semua branch)</option>
          <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
        </select>
        <p class="mt-1 text-xs text-slate-500">Kosongkan jika NPP berlaku untuk seluruh cabang perusahaan.</p>
      </div>

      <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Nomor NPP</label>
        <input
          v-model="form.npp_number"
          required
          class="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm text-slate-700 transition-colors focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary/30"
        />
      </div>

      <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Nama Registrasi</label>
        <input
          v-model="form.label"
          placeholder="mis. Kantor Pusat"
          class="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm text-slate-700 transition-colors focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary/30"
        />
        <p class="mt-1 text-xs text-slate-500">Opsional — memudahkan identifikasi jika ada lebih dari satu NPP.</p>
      </div>

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