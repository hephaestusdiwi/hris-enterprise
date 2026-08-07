<script setup lang="ts">
import type { CostBearer, JhtCostBearer } from '@/composables/bpjs'

defineProps<{
  form: {
    default_health_cost_bearer: CostBearer
    default_jht_cost_bearer: JhtCostBearer
  }
  saving: boolean
  saved: boolean
  onSave: () => Promise<void>
}>()
</script>

<template>
  <div class="max-w-md space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <div>
      <label class="mb-1.5 block text-sm font-medium text-slate-700">Default BPJS Kesehatan</label>
      <select
        v-model="form.default_health_cost_bearer"
        class="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm text-slate-700 transition-colors focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary/30"
      >
        <option value="employee_borne">Ditanggung Karyawan (split normal)</option>
        <option value="company_borne">Ditanggung Company</option>
      </select>
    </div>
    <div>
      <label class="mb-1.5 block text-sm font-medium text-slate-700">Default JHT</label>
      <select
        v-model="form.default_jht_cost_bearer"
        class="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm text-slate-700 transition-colors focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary/30"
      >
        <option value="employee_borne">Ditanggung Karyawan (split normal)</option>
        <option value="company_borne">Ditanggung Company</option>
        <option value="not_participating">Tidak Diikutkan</option>
      </select>
    </div>
    <p class="text-xs text-slate-500">
      Ini yang dipakai saat employee pilih "Default" di halaman Employee BPJS Participation.
    </p>
    <button
      type="button"
      :disabled="saving"
      class="inline-flex h-9 w-full items-center justify-center rounded-lg bg-primary text-sm font-medium text-white transition-colors hover:bg-primary-dark disabled:opacity-50"
      @click="onSave"
    >
      {{ saving ? 'Menyimpan...' : 'Simpan' }}
    </button>
    <p v-if="saved" class="text-center text-xs text-emerald-600">Tersimpan.</p>
  </div>
</template>