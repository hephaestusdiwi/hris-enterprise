<script setup lang="ts">
import { ref } from 'vue'
import { Plus, Percent } from 'lucide-vue-next'
import ActionMenu from '@/components/ui/ActionMenu.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import RateConfigModal from './RateConfigModal.vue'
import { formatDate, formatCurrency, isFutureOrToday, programLabels } from '@/lib/bpjsFormat'
import type { RateConfig } from '@/types/bpjs'

const props = defineProps<{
  configs: RateConfig[]
  loading: boolean
  onCreate: (payload: Record<string, unknown>) => Promise<void>
  onDelete: (id: number) => Promise<void>
}>()

const showModal = ref(false)

async function handleDelete(config: RateConfig) {
  if (!confirm('Hapus rate config ini?')) return
  try {
    await props.onDelete(config.id)
  } catch (err: any) {
    alert(err.response?.data?.message || 'Gagal menghapus.')
  }
}
</script>

<template>
  <div class="space-y-3">
    <div class="flex justify-end">
      <button
        type="button"
        class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-primary px-4 text-sm font-medium text-white transition-colors hover:bg-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
        @click="showModal = true"
      >
        <Plus class="h-4 w-4" :stroke-width="2" /> Tambah Rate Config
      </button>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
      <EmptyState
        v-if="!loading && configs.length === 0"
        :icon="Percent"
        title="Belum ada rate config"
        description="Tambahkan rate config untuk menentukan tarif iuran BPJS yang dipakai saat payroll dijalankan."
        action-label="Tambah Rate Config"
        @action="showModal = true"
      />
      <table v-else class="w-full text-left text-sm">
        <thead>
          <tr class="h-11 border-b border-slate-200 bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-500">
            <th class="px-4">Program</th>
            <th class="px-4">Berlaku Sejak</th>
            <th class="px-4 text-right">Rate Karyawan</th>
            <th class="px-4 text-right">Rate Company</th>
            <th class="px-4 text-right">Wage Cap</th>
            <th class="w-12 px-4"></th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in configs"
            :key="row.id"
            class="h-[52px] border-b border-slate-100 transition-colors last:border-0 hover:bg-slate-50/70"
          >
            <td class="px-4 font-medium text-slate-800">{{ programLabels[row.program] }}</td>
            <td class="px-4 text-slate-500">{{ formatDate(row.effective_date) }}</td>
            <td class="px-4 text-right tabular-nums text-slate-600">
              {{ row.employee_rate_percentage ? `${row.employee_rate_percentage}%` : '-' }}
            </td>
            <td class="px-4 text-right tabular-nums text-slate-600">
              {{ row.employer_rate_percentage ? `${row.employer_rate_percentage}%` : 'via risk class' }}
            </td>
            <td class="px-4 text-right tabular-nums text-slate-600">{{ formatCurrency(row.wage_base_cap) }}</td>
            <td class="px-4 text-right">
              <ActionMenu
                v-if="isFutureOrToday(row.effective_date)"
                :show-edit="false"
                @delete="handleDelete(row)"
              />
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <RateConfigModal v-if="showModal" :on-create="onCreate" @close="showModal = false" />
  </div>
</template>